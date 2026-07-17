<?php
/**
 * Modèle Finances - Gestion de la comptabilité et finances
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Finances {

    private static function rollbackIfActive(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private function scopeTenant(PDO $pdo, array &$where, array &$params, string $table = 'comptes_comptables', string $alias = ''): void
    {
        TenantScope::appendWhere($pdo, $table, $where, $params, $alias);
    }

    private function appendTenantToDynamicInsert(PDO $pdo, string $table, array &$fields, array &$values): void
    {
        $cols = $this->getComptesComptablesColumns();
        if (!in_array('tenant_id', $cols, true)) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = \'tenant_id\''
            );
            $stmt->execute([$table]);
            if (!$stmt->fetchColumn()) {
                return;
            }
        }
        $owned = TenantScope::ownedParam($pdo, $table);
        if ($owned) {
            $fields[] = 'tenant_id';
            $values[] = $owned[0];
        }
    }

    private function ownedCompteWhere(PDO $pdo, int $compteId): array
    {
        return [
            'sql' => ' WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'comptes_comptables'),
            'params' => TenantScope::paramsForId($pdo, 'comptes_comptables', $compteId),
        ];
    }
    
    public function getComptes($page = 1, $limit = 20, $search = '', $type_compte = '', $statut = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(LOWER(numero_compte) LIKE LOWER(?) OR LOWER(libelle) LIKE LOWER(?))";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($type_compte) {
            $where[] = "type_compte = ?";
            $params[] = $type_compte;
        }

        if ($statut) {
            $where[] = 'statut = ?';
            $params[] = $statut;
        }

        $this->scopeTenant($pdo, $where, $params);
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM comptes_comptables $where_clause ORDER BY numero_compte LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getCountComptes($search = '', $type_compte = '', $statut = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(LOWER(numero_compte) LIKE LOWER(?) OR LOWER(libelle) LIKE LOWER(?))";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($type_compte) {
            $where[] = "type_compte = ?";
            $params[] = $type_compte;
        }

        if ($statut) {
            $where[] = 'statut = ?';
            $params[] = $statut;
        }

        $this->scopeTenant($pdo, $where, $params);
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT COUNT(*) as total FROM comptes_comptables $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    public function getCompteById($id) {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM comptes_comptables WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'comptes_comptables')
        );
        $stmt->execute(TenantScope::paramsForId($pdo, 'comptes_comptables', (int) $id));
        return $stmt->fetch();
    }
    
    /** @var array<string>|null */
    private static $comptesComptablesColumns = null;
    /** @var bool|null */
    private static $soldeActuelIsGenerated = null;
    
    /**
     * Colonnes réelles de la table (schémas variés selon les migrations).
     *
     * @return array<int, string>
     */
    private function getComptesComptablesColumns(): array {
        if (self::$comptesComptablesColumns === null) {
            $pdo = getDB();
            self::$comptesComptablesColumns = $pdo->query('SHOW COLUMNS FROM comptes_comptables')->fetchAll(PDO::FETCH_COLUMN, 0);
        }
        return self::$comptesComptablesColumns;
    }
    
    private function isSoldeActuelGeneratedColumn(): bool {
        if (self::$soldeActuelIsGenerated !== null) {
            return self::$soldeActuelIsGenerated;
        }
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comptes_comptables' AND COLUMN_NAME = 'solde_actuel'");
            $extra = $stmt ? $stmt->fetchColumn() : '';
            self::$soldeActuelIsGenerated = is_string($extra) && stripos($extra, 'GENERATED') !== false;
        } catch (Exception $e) {
            self::$soldeActuelIsGenerated = false;
        }
        return self::$soldeActuelIsGenerated;
    }

    /**
     * Applique une écriture sur les soldes (débit +, crédit -).
     * Montant négatif pour annuler une écriture validée.
     */
    private function applyEcritureSoldes(PDO $pdo, int $compteDebitId, int $compteCreditId, float $montant): void
    {
        if ($montant == 0.0) {
            return;
        }

        $debit = $this->ownedCompteWhere($pdo, $compteDebitId);
        $credit = $this->ownedCompteWhere($pdo, $compteCreditId);

        if ($this->isSoldeActuelGeneratedColumn()) {
            $pdo->prepare('UPDATE comptes_comptables SET solde_debit = solde_debit + ?' . $debit['sql'])
                ->execute(array_merge([$montant], $debit['params']));
            $pdo->prepare('UPDATE comptes_comptables SET solde_credit = solde_credit + ?' . $credit['sql'])
                ->execute(array_merge([$montant], $credit['params']));
            return;
        }

        $pdo->prepare('UPDATE comptes_comptables SET solde_actuel = solde_actuel + ?' . $debit['sql'])
            ->execute(array_merge([$montant], $debit['params']));
        $pdo->prepare('UPDATE comptes_comptables SET solde_actuel = solde_actuel - ?' . $credit['sql'])
            ->execute(array_merge([$montant], $credit['params']));
    }
    
    /**
     * Indique si un numéro de compte est déjà pris (contrainte UNIQUE).
     */
    public function numeroCompteExiste(string $numero): bool {
        $pdo = getDB();
        $where = ['numero_compte = ?'];
        $params = [$numero];
        $this->scopeTenant($pdo, $where, $params);
        $stmt = $pdo->prepare('SELECT 1 FROM comptes_comptables WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Crée un compte en s'adaptant aux colonnes présentes (libelle vs nom_compte, solde_initial vs solde_debit/credit).
     *
     * @param array<string, mixed> $data
     * @return int|false ID du compte créé, ou false si échec
     */
    public function createCompte($data) {
        $pdo = getDB();
        $cols = $this->getComptesComptablesColumns();
        
        if (empty($data['numero_compte'])) {
            $data['numero_compte'] = $this->generateNumeroCompte($data['type_compte']);
        }
        $data['numero_compte'] = trim((string) $data['numero_compte']);
        
        if ($data['numero_compte'] !== '' && $this->numeroCompteExiste($data['numero_compte'])) {
            throw new RuntimeException(
                'Le numéro « ' . $data['numero_compte'] . ' » est déjà utilisé. Choisissez un autre numéro ou laissez le champ vide pour une attribution automatique.'
            );
        }
        
        $solde = (float) str_replace(',', '.', (string) ($data['solde_initial'] ?? 0));
        $statut = $data['statut'] ?? 'actif';
        
        $fields = [];
        $values = [];
        $add = function (string $name, $value) use (&$fields, &$values, $cols) {
            if (in_array($name, $cols, true)) {
                $fields[] = '`' . str_replace('`', '``', $name) . '`';
                $values[] = $value;
            }
        };
        
        $add('numero_compte', $data['numero_compte']);
        if (in_array('libelle', $cols, true)) {
            $add('libelle', $data['libelle']);
        } elseif (in_array('nom_compte', $cols, true)) {
            $add('nom_compte', $data['libelle']);
        }
        $add('type_compte', $data['type_compte']);
        $add('classe', $data['classe'] ?? null);
        if (in_array('solde_initial', $cols, true)) {
            $add('solde_initial', $solde);
        }
        if (in_array('statut', $cols, true)) {
            $add('statut', $statut);
        } elseif (in_array('actif', $cols, true)) {
            $add('actif', $statut === 'actif' ? 1 : 0);
        }
        
        if (!in_array('solde_initial', $cols, true) && in_array('solde_debit', $cols, true)) {
            $add('solde_debit', $solde);
            if (in_array('solde_credit', $cols, true)) {
                $add('solde_credit', 0);
            }
        }
        
        if (count($fields) < 2) {
            return false;
        }

        $this->appendTenantToDynamicInsert($pdo, 'comptes_comptables', $fields, $values);
        
        $sql = 'INSERT INTO comptes_comptables (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute($values);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) {
                throw new RuntimeException(
                    'Ce numéro de compte est déjà utilisé. Modifiez-le ou laissez le champ vide pour une attribution automatique.',
                    0,
                    $e
                );
            }
            throw $e;
        }
        
        $newId = (int) $pdo->lastInsertId();
        if ($newId < 1) {
            $where = ['numero_compte = ?'];
            $params = [$data['numero_compte']];
            $this->scopeTenant($pdo, $where, $params);
            $stmt = $pdo->prepare('SELECT id FROM comptes_comptables WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1');
            $stmt->execute($params);
            $row = $stmt->fetch();
            $newId = $row ? (int) $row['id'] : 0;
        }
        
        if ($newId < 1) {
            return false;
        }
        
        if (in_array('solde_actuel', $cols, true) && in_array('solde_initial', $cols, true) && !$this->isSoldeActuelGeneratedColumn()) {
            try {
                $owned = $this->ownedCompteWhere($pdo, $newId);
                $pdo->prepare('UPDATE comptes_comptables SET solde_actuel = solde_initial' . $owned['sql'])->execute($owned['params']);
            } catch (Exception $e) {
                // Colonne non modifiable ou autre schéma : ignorer
            }
        }
        
        return $newId;
    }

    public function countEcrituresForCompte(int $compteId): int
    {
        if ($compteId <= 0) {
            return 0;
        }
        $pdo = getDB();
        $where = ['(e.compte_debit_id = ? OR e.compte_credit_id = ?)'];
        $params = [$compteId, $compteId];
        $this->scopeTenant($pdo, $where, $params, 'ecritures_comptables', 'e');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ecritures_comptables e WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCompte(int $id, array $data): bool
    {
        if ($id <= 0 || !$this->getCompteById($id)) {
            return false;
        }

        $pdo = getDB();
        $cols = $this->getComptesComptablesColumns();
        $sets = [];
        $params = [];

        $libelle = trim((string) ($data['libelle'] ?? ''));
        if ($libelle !== '') {
            if (in_array('libelle', $cols, true)) {
                $sets[] = 'libelle = ?';
                $params[] = $libelle;
            } elseif (in_array('nom_compte', $cols, true)) {
                $sets[] = 'nom_compte = ?';
                $params[] = $libelle;
            }
        }

        if (!empty($data['type_compte']) && in_array('type_compte', $cols, true)) {
            $sets[] = 'type_compte = ?';
            $params[] = $data['type_compte'];
        }

        if (array_key_exists('classe', $data) && in_array('classe', $cols, true)) {
            $sets[] = 'classe = ?';
            $params[] = $data['classe'] ?: null;
        }

        if (!empty($data['statut'])) {
            if (in_array('statut', $cols, true)) {
                $sets[] = 'statut = ?';
                $params[] = $data['statut'];
            } elseif (in_array('actif', $cols, true)) {
                $sets[] = 'actif = ?';
                $params[] = $data['statut'] === 'actif' ? 1 : 0;
            }
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE comptes_comptables SET ' . implode(', ', $sets) . ' WHERE id = ?'
            . TenantScope::andOwnedByTenant($pdo, 'comptes_comptables');
        $params = array_merge($params, TenantScope::ownedParam($pdo, 'comptes_comptables'));
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Supprime le compte s'il n'a pas d'écritures, sinon le désactive.
     *
     * @return array{ok: bool, soft: bool, message: string}
     */
    public function deleteCompte(int $id): array
    {
        $compte = $this->getCompteById($id);
        if (!$compte) {
            return ['ok' => false, 'soft' => false, 'message' => 'Compte introuvable.'];
        }

        $nbEcritures = $this->countEcrituresForCompte($id);
        if ($nbEcritures > 0) {
            $ok = $this->updateCompte($id, ['libelle' => $compte['libelle'] ?? $compte['nom_compte'] ?? '', 'statut' => 'inactif']);
            return [
                'ok' => $ok,
                'soft' => true,
                'message' => "Le compte a été désactivé ({$nbEcritures} écriture(s) liée(s) — suppression impossible).",
            ];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare(
            'DELETE FROM comptes_comptables WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'comptes_comptables')
        );
        $ok = $stmt->execute(TenantScope::paramsForId($pdo, 'comptes_comptables', $id));
        return [
            'ok' => $ok,
            'soft' => false,
            'message' => 'Compte supprimé définitivement.',
        ];
    }
    
    public function createEcriture($data) {
        $pdo = getDB();
        
        if (empty($data['numero_ecriture'])) {
            $data['numero_ecriture'] = $this->generateNumeroEcriture();
        }
        
        $pdo->beginTransaction();
        
        try {
            if (!$this->getCompteById($data['compte_debit_id']) || !$this->getCompteById($data['compte_credit_id'])) {
                throw new RuntimeException('Compte comptable introuvable pour ce tenant.');
            }

            $columns = [
                'numero_ecriture', 'date_ecriture', 'compte_debit_id', 'compte_credit_id',
                'montant', 'libelle', 'reference', 'piece_jointe', 'valide', 'cree_par',
            ];
            $placeholders = array_fill(0, count($columns), '?');
            $values = [
                $data['numero_ecriture'],
                $data['date_ecriture'],
                $data['compte_debit_id'],
                $data['compte_credit_id'],
                $data['montant'],
                $data['libelle'],
                $data['reference'] ?? null,
                $data['piece_jointe'] ?? null,
                $data['valide'] ?? 0,
                $data['cree_par'] ?? null,
            ];
            TenantScope::bindInsert($pdo, 'ecritures_comptables', $columns, $placeholders, $values);
            $sql = 'INSERT INTO ecritures_comptables (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            $ecriture_id = $pdo->lastInsertId();
            
            if ($data['valide']) {
                $this->applyEcritureSoldes(
                    $pdo,
                    (int) $data['compte_debit_id'],
                    (int) $data['compte_credit_id'],
                    (float) $data['montant']
                );
            }
            
            $pdo->commit();
            return $ecriture_id;
            
        } catch (Exception $e) {
            self::rollbackIfActive($pdo);
            throw $e;
        }
    }
    
    public function validerEcriture($id, $user_id) {
        $pdo = getDB();
        
        $stmt = $pdo->prepare(
            'SELECT * FROM ecritures_comptables WHERE id = ? AND valide = 0'
            . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables')
        );
        $stmt->execute(TenantScope::paramsForId($pdo, 'ecritures_comptables', (int) $id));
        $ecriture = $stmt->fetch();
        
        if (!$ecriture) {
            return false;
        }
        
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare(
                'UPDATE ecritures_comptables SET valide = 1, valide_par = ?, date_validation = NOW() WHERE id = ?'
                . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables')
            );
            $stmt->execute(array_merge([$user_id, (int) $id], TenantScope::ownedParam($pdo, 'ecritures_comptables')));
            
            $this->applyEcritureSoldes(
                $pdo,
                (int) $ecriture['compte_debit_id'],
                (int) $ecriture['compte_credit_id'],
                (float) $ecriture['montant']
            );
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            self::rollbackIfActive($pdo);
            throw $e;
        }
    }
    
    public function getEcritureById($id) {
        $pdo = getDB();
        $sql = "SELECT e.*, 
                       cd.numero_compte as compte_debit_numero, cd.libelle as compte_debit_libelle,
                       cc.numero_compte as compte_credit_numero, cc.libelle as compte_credit_libelle,
                       u.nom_utilisateur as cree_par_nom
                FROM ecritures_comptables e
                INNER JOIN comptes_comptables cd ON e.compte_debit_id = cd.id
                INNER JOIN comptes_comptables cc ON e.compte_credit_id = cc.id
                LEFT JOIN utilisateurs u ON e.cree_par = u.id
                WHERE e.id = ?" . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables', 'e');
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($pdo, 'ecritures_comptables', (int) $id));
        return $stmt->fetch();
    }
    
    public function deleteEcriture($id) {
        $pdo = getDB();
        
        $stmt = $pdo->prepare(
            'SELECT * FROM ecritures_comptables WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables')
        );
        $stmt->execute(TenantScope::paramsForId($pdo, 'ecritures_comptables', (int) $id));
        $ecriture = $stmt->fetch();
        
        if (!$ecriture) {
            return false;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Si l'écriture est validée, annuler son impact sur les soldes
            if ($ecriture['valide']) {
                $this->applyEcritureSoldes(
                    $pdo,
                    (int) $ecriture['compte_debit_id'],
                    (int) $ecriture['compte_credit_id'],
                    -(float) $ecriture['montant']
                );
            }
            
            $stmt = $pdo->prepare(
                'DELETE FROM ecritures_comptables WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables')
            );
            $result = $stmt->execute(TenantScope::paramsForId($pdo, 'ecritures_comptables', (int) $id));
            
            $pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            self::rollbackIfActive($pdo);
            throw $e;
        }
    }
    
    public function updateEcriture($id, $data) {
        $pdo = getDB();
        
        // Récupérer l'ancienne écriture
        $stmt = $pdo->prepare(
            'SELECT * FROM ecritures_comptables WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables')
        );
        $stmt->execute(TenantScope::paramsForId($pdo, 'ecritures_comptables', (int) $id));
        $ancienne_ecriture = $stmt->fetch();
        
        if (!$ancienne_ecriture) {
            return false;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Si l'ancienne écriture était validée, annuler son impact sur les soldes
            if ($ancienne_ecriture['valide']) {
                $this->applyEcritureSoldes(
                    $pdo,
                    (int) $ancienne_ecriture['compte_debit_id'],
                    (int) $ancienne_ecriture['compte_credit_id'],
                    -(float) $ancienne_ecriture['montant']
                );
            }
            
            $sql = 'UPDATE ecritures_comptables SET
                date_ecriture = ?,
                compte_debit_id = ?,
                compte_credit_id = ?,
                montant = ?,
                libelle = ?,
                reference = ?,
                valide = ?
                WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'ecritures_comptables');
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(TenantScope::appendOwned($pdo, 'ecritures_comptables', [
                $data['date_ecriture'],
                $data['compte_debit_id'],
                $data['compte_credit_id'],
                $data['montant'],
                $data['libelle'],
                $data['reference'] ?? null,
                $data['valide'] ?? 0,
                (int) $id,
            ]));
            
            if ($data['valide']) {
                if (!$this->getCompteById($data['compte_debit_id']) || !$this->getCompteById($data['compte_credit_id'])) {
                    throw new RuntimeException('Compte comptable introuvable pour ce tenant.');
                }
                $this->applyEcritureSoldes(
                    $pdo,
                    (int) $data['compte_debit_id'],
                    (int) $data['compte_credit_id'],
                    (float) $data['montant']
                );
            }
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            self::rollbackIfActive($pdo);
            throw $e;
        }
    }
    
    public function getEcritures($page = 1, $limit = 20, $date_debut = '', $date_fin = '', $valide = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($date_debut) {
            $where[] = "e.date_ecriture >= ?";
            $params[] = $date_debut;
        }
        
        if ($date_fin) {
            $where[] = "e.date_ecriture <= ?";
            $params[] = $date_fin;
        }
        
        if ($valide !== '') {
            $where[] = "e.valide = ?";
            $params[] = $valide;
        }

        $this->scopeTenant($pdo, $where, $params, 'ecritures_comptables', 'e');
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT e.*, 
                       cd.numero_compte as compte_debit_num, cd.libelle as compte_debit_lib,
                       cc.numero_compte as compte_credit_num, cc.libelle as compte_credit_lib
                FROM ecritures_comptables e
                INNER JOIN comptes_comptables cd ON e.compte_debit_id = cd.id
                INNER JOIN comptes_comptables cc ON e.compte_credit_id = cc.id
                $where_clause
                ORDER BY e.date_ecriture DESC, e.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCountEcritures($date_debut = '', $date_fin = '', $valide = '') {
        $pdo = getDB();
        $where = [];
        $params = [];
        if ($date_debut) {
            $where[] = 'date_ecriture >= ?';
            $params[] = $date_debut;
        }
        if ($date_fin) {
            $where[] = 'date_ecriture <= ?';
            $params[] = $date_fin;
        }
        if ($valide !== '') {
            $where[] = 'valide = ?';
            $params[] = $valide;
        }
        $this->scopeTenant($pdo, $where, $params, 'ecritures_comptables');
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM ecritures_comptables ' . $where_clause);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? (int) $result['total'] : 0;
    }
    
    public function getBudgets($annee = null) {
        $pdo = getDB();
        
        if (!$annee) {
            $annee = date('Y');
        }

        $where = ['annee = ?'];
        $params = [$annee];
        TenantScope::appendWhere($pdo, 'budgets', $where, $params);
        
        $stmt = $pdo->prepare('SELECT * FROM budgets WHERE ' . implode(' AND ', $where) . ' ORDER BY departement, categorie');
        $stmt->execute($params);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($budgets as &$budget) {
            $budget['montant_utilise'] = $this->computeBudgetMontantUtilise($budget);
        }
        unset($budget);

        return $budgets;
    }

    public function getBudgetById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        TenantScope::appendWhere($pdo, 'budgets', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM budgets WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$budget) {
            return null;
        }
        $budget['montant_utilise'] = $this->computeBudgetMontantUtilise($budget);
        return $budget;
    }

    /**
     * Consommation estimée : écritures de charges validées de l'année, rapprochées par catégorie / département.
     */
    public function computeBudgetMontantUtilise(array $budget): float
    {
        $pdo = getDB();
        $annee = (int) ($budget['annee'] ?? date('Y'));
        $categorie = (string) ($budget['categorie'] ?? 'autre');
        $departement = trim((string) ($budget['departement'] ?? ''));

        $keywords = $this->budgetCategoryKeywords()[$categorie] ?? [];
        $where = [
            'e.valide = 1',
            'YEAR(e.date_ecriture) = ?',
            "cd.type_compte = 'charge'",
        ];
        $params = [$annee];
        $this->scopeTenant($pdo, $where, $params, 'ecritures_comptables', 'e');

        $matchParts = [];
        if ($departement !== '') {
            $matchParts[] = '(e.libelle LIKE ? OR cd.libelle LIKE ?)';
            $likeDept = '%' . $departement . '%';
            $params[] = $likeDept;
            $params[] = $likeDept;
        }
        foreach ($keywords as $kw) {
            $matchParts[] = '(e.libelle LIKE ? OR cd.libelle LIKE ?)';
            $likeKw = '%' . $kw . '%';
            $params[] = $likeKw;
            $params[] = $likeKw;
        }

        if (empty($matchParts)) {
            return (float) ($budget['montant_utilise'] ?? 0);
        }

        $where[] = '(' . implode(' OR ', $matchParts) . ')';

        $sql = 'SELECT COALESCE(SUM(e.montant), 0) AS total
                FROM ecritures_comptables e
                INNER JOIN comptes_comptables cd ON cd.id = e.compte_debit_id
                WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<string, list<string>> */
    private function budgetCategoryKeywords(): array
    {
        return [
            'medecine'    => ['medecin', 'médecine', 'medecine', 'consultation', 'soin', 'clinique'],
            'pharmacie'   => ['pharmacie', 'medicament', 'médicament', 'pharma'],
            'equipement'  => ['equipement', 'materiel', 'matériel', 'achat', 'appareil'],
            'personnel'   => ['personnel', 'salaire', 'paie', 'rh', 'honoraire', 'employé', 'employe'],
            'maintenance' => ['maintenance', 'reparation', 'réparation', 'entretien'],
            'autre'       => [],
        ];
    }
    
    public function createBudget($data) {
        $pdo = getDB();
        
        $columns = ['annee', 'departement', 'categorie', 'montant_alloue', 'statut', 'notes', 'cree_par'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['annee'],
            $data['departement'] ?? null,
            $data['categorie'],
            $data['montant_alloue'],
            $data['statut'] ?? 'planifie',
            $data['notes'] ?? null,
            $data['cree_par'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'budgets', $columns, $placeholders, $values);
        $sql = 'INSERT INTO budgets (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }

    public function updateBudget(int $id, array $data): bool
    {
        $pdo = getDB();
        $existing = $this->getBudgetById($id);
        if (!$existing) {
            return false;
        }

        $sets = [];
        $params = [];
        $allowed = ['annee', 'departement', 'categorie', 'montant_alloue', 'statut', 'notes'];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = $field . ' = ?';
            $params[] = $data[$field];
        }
        if (empty($sets)) {
            return false;
        }

        $where = ['id = ?'];
        $params[] = $id;
        TenantScope::appendWhere($pdo, 'budgets', $where, $params);

        $stmt = $pdo->prepare('UPDATE budgets SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where));
        return $stmt->execute($params);
    }

    public function deleteBudget(int $id): bool
    {
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        TenantScope::appendWhere($pdo, 'budgets', $where, $params);
        $stmt = $pdo->prepare('DELETE FROM budgets WHERE ' . implode(' AND ', $where));
        return $stmt->execute($params);
    }
    
    public function getStats() {
        $pdo = getDB();

        $stats = [
            'comptes_actifs' => TenantScope::count($pdo, 'comptes_comptables', ["statut = 'actif'"]),
            'ecritures_en_attente' => TenantScope::count($pdo, 'ecritures_comptables', ['valide = 0']),
        ];

        $whereMontant = ['valide = 1', 'DATE(date_ecriture) = CURDATE()'];
        $paramsMontant = [];
        TenantScope::appendWhere($pdo, 'ecritures_comptables', $whereMontant, $paramsMontant);
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant), 0) as total FROM ecritures_comptables WHERE ' . implode(' AND ', $whereMontant));
        $stmt->execute($paramsMontant);
        $stats['montant_aujourd_hui'] = (float) ($stmt->fetchColumn() ?: 0);

        $whereBudget = ["annee = YEAR(CURDATE())", "statut = 'approuve'"];
        $paramsBudget = [];
        TenantScope::appendWhere($pdo, 'budgets', $whereBudget, $paramsBudget);
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant_alloue), 0) as total FROM budgets WHERE ' . implode(' AND ', $whereBudget));
        $stmt->execute($paramsBudget);
        $stats['budget_annuel'] = (float) ($stmt->fetchColumn() ?: 0);

        return $stats;
    }
    
    public function getBilan($date_debut, $date_fin) {
        $pdo = getDB();

        $whereActifs = ["type_compte = 'actif'", "statut = 'actif'"];
        $paramsActifs = [];
        $this->scopeTenant($pdo, $whereActifs, $paramsActifs);
        $stmt = $pdo->prepare('SELECT SUM(solde_actuel) as total FROM comptes_comptables WHERE ' . implode(' AND ', $whereActifs));
        $stmt->execute($paramsActifs);
        $actifs = $stmt->fetch();

        $wherePassifs = ["type_compte = 'passif'", "statut = 'actif'"];
        $paramsPassifs = [];
        $this->scopeTenant($pdo, $wherePassifs, $paramsPassifs);
        $stmt = $pdo->prepare('SELECT SUM(solde_actuel) as total FROM comptes_comptables WHERE ' . implode(' AND ', $wherePassifs));
        $stmt->execute($paramsPassifs);
        $passifs = $stmt->fetch();

        $whereProduits = ['e.valide = 1', 'e.date_ecriture BETWEEN ? AND ?'];
        $paramsProduits = [$date_debut, $date_fin];
        $this->scopeTenant($pdo, $whereProduits, $paramsProduits, 'ecritures_comptables', 'e');
        $subWhere = ["type_compte = 'produit'"];
        $subParams = [];
        $this->scopeTenant($pdo, $subWhere, $subParams);
        $produitSql = 'SELECT SUM(e.montant) as total FROM ecritures_comptables e WHERE '
            . implode(' AND ', $whereProduits)
            . ' AND e.compte_credit_id IN (SELECT id FROM comptes_comptables WHERE ' . implode(' AND ', $subWhere) . ')';
        $stmt = $pdo->prepare($produitSql);
        $stmt->execute(array_merge($paramsProduits, $subParams));
        $produits = $stmt->fetch();

        $whereCharges = ['e.valide = 1', 'e.date_ecriture BETWEEN ? AND ?'];
        $paramsCharges = [$date_debut, $date_fin];
        $this->scopeTenant($pdo, $whereCharges, $paramsCharges, 'ecritures_comptables', 'e');
        $subWhereCharge = ["type_compte = 'charge'"];
        $subParamsCharge = [];
        $this->scopeTenant($pdo, $subWhereCharge, $subParamsCharge);
        $chargeSql = 'SELECT SUM(e.montant) as total FROM ecritures_comptables e WHERE '
            . implode(' AND ', $whereCharges)
            . ' AND e.compte_debit_id IN (SELECT id FROM comptes_comptables WHERE ' . implode(' AND ', $subWhereCharge) . ')';
        $stmt = $pdo->prepare($chargeSql);
        $stmt->execute(array_merge($paramsCharges, $subParamsCharge));
        $charges = $stmt->fetch();
        
        return [
            'actifs' => (float)($actifs['total'] ?? 0),
            'passifs' => (float)($passifs['total'] ?? 0),
            'produits' => (float)($produits['total'] ?? 0),
            'charges' => (float)($charges['total'] ?? 0),
            'resultat' => (float)($produits['total'] ?? 0) - (float)($charges['total'] ?? 0)
        ];
    }
    
    private function generateNumeroCompte($type) {
        $prefixes = [
            'actif' => '2',
            'passif' => '1',
            'produit' => '7',
            'charge' => '6'
        ];
        
        $prefix = $prefixes[$type] ?? '9';
        $pdo = getDB();
        $where = ["numero_compte REGEXP '^[0-9]+$'", 'numero_compte LIKE ?'];
        $params = [$prefix . '%'];
        $this->scopeTenant($pdo, $where, $params);
        $stmt = $pdo->prepare(
            'SELECT MAX(CAST(numero_compte AS UNSIGNED)) FROM comptes_comptables WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        $max = $stmt->fetchColumn();
        $next = (int) ($max ?: 0) + 1;
        $minNum = (int) ($prefix . '0001');
        if ($next < $minNum) {
            $next = $minNum;
        }
        return (string) $next;
    }
    
    private function generateNumeroEcriture() {
        $pdo = getDB();
        $prefix = 'ECR';
        $year = date('Y');
        $where = ['numero_ecriture LIKE ?'];
        $params = ["$prefix$year%"];
        $this->scopeTenant($pdo, $where, $params, 'ecritures_comptables');
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM ecritures_comptables WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        $count = $result ? (int) $result['count'] : 0;
        
        return $prefix . $year . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}





