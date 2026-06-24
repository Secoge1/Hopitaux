<?php
/**
 * Modèle Assurance - Gestion des assurances et mutuelles
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Assurance {

    private string $lastDeleteError = '';

    public function getLastDeleteError(): string
    {
        return $this->lastDeleteError;
    }

    private function scopeTenant(PDO $pdo, array &$where, array &$params): void
    {
        TenantScope::appendWhere($pdo, 'assurances', $where, $params);
    }
    
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "LOWER(nom) LIKE LOWER(?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        } else {
            $where[] = "statut != 'inactif'";
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM assurances $where_clause ORDER BY nom LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getCount($search = '', $statut = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "LOWER(nom) LIKE LOWER(?)";
            $params[] = "%$search%";
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        } else {
            $where[] = "statut != 'inactif'";
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT COUNT(*) as total FROM assurances $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    public function getById($id) {
        $pdo = getDB();
        $sql = 'SELECT * FROM assurances WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'assurances');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'assurances')));
        return $stmt->fetch();
    }
    
    public function create($data) {
        $pdo = getDB();
        
        $columns = ['nom', 'type', 'numero_agrement', 'telephone', 'email', 'adresse', 'taux_remboursement', 'statut', 'notes'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['nom'],
            $data['type'] ?? 'assurance',
            $data['numero_agrement'] ?? null,
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['taux_remboursement'] ?? 0.00,
            $data['statut'] ?? 'actif',
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'assurances', $columns, $placeholders, $values);
        $sql = 'INSERT INTO assurances (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function update($id, $data) {
        $pdo = getDB();
        
        $sql = 'UPDATE assurances SET
            nom = ?, type = ?, numero_agrement = ?, telephone = ?, email = ?, adresse = ?,
            taux_remboursement = ?, statut = ?, notes = ?
            WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'assurances');
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($pdo, 'assurances', [
            $data['nom'],
            $data['type'] ?? 'assurance',
            $data['numero_agrement'] ?? null,
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['taux_remboursement'] ?? 0.00,
            $data['statut'] ?? 'actif',
            $data['notes'] ?? null,
            (int) $id,
        ]));
    }
    
    /**
     * Supprimer une assurance (suppression logique)
     * Marque l'assurance comme inactive au lieu de la supprimer physiquement
     */
    public function delete($id) {
        try {
            $pdo = getDB();
            if (!$this->getById($id)) {
                return false;
            }
            $stmt = $pdo->prepare(
                "UPDATE assurances SET statut = 'inactif' WHERE id = ?" . TenantScope::andOwnedByTenant($pdo, 'assurances')
            );
            $stmt->execute(TenantScope::paramsForId($pdo, 'assurances', (int) $id));
            if ($stmt->rowCount() > 0) {
                $this->invalidateDashboardCache();
                return true;
            }
        } catch (Exception $e) {
            error_log("Assurance::delete($id) soft: " . $e->getMessage());
        }

        return $this->hardDelete($id);
    }

    private function invalidateDashboardCache(): void
    {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) {
            // Ignorer
        }
    }
    
    /**
     * Suppression physique d'une assurance (hard delete)
     * ATTENTION: Cette méthode est irréversible !
     */
    public function hardDelete($id) {
        $this->lastDeleteError = '';
        try {
            $pdo = getDB();
            if (!$this->getById($id)) {
                return false;
            }
            $pdo->beginTransaction();

            $where = ['c.assurance_id = ?'];
            $params = [(int) $id];
            TenantScope::appendWhere($pdo, 'contrats_assurance', $where, $params, 'c');
            $stmt = $pdo->prepare('SELECT c.id FROM contrats_assurance c WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $contratId) {
                TenantScope::deleteWhere($pdo, 'remboursements', ['contrat_id = ?'], [(int) $contratId]);
            }
            $stmt = $pdo->prepare('DELETE c FROM contrats_assurance c WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);

            $stmt = $pdo->prepare(
                'DELETE FROM assurances WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'assurances')
            );
            $result = $stmt->execute(TenantScope::paramsForId($pdo, 'assurances', (int) $id));
            $pdo->commit();
            
            if ($result && $stmt->rowCount() > 0) {
                $this->invalidateDashboardCache();
            }

            return $result && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur lors de la suppression physique de l'assurance ID $id: " . $e->getMessage());
            $this->lastDeleteError = $e->getMessage();
            return false;
        }
    }
    
    public function getContratsByPatient($patient_id) {
        $pdo = getDB();
        $where = ['c.patient_id = ?', "c.statut = 'actif'"];
        $params = [$patient_id];
        TenantScope::appendWhere($pdo, 'contrats_assurance', $where, $params, 'c');
        $stmt = $pdo->prepare("
            SELECT c.*,
                   c.numero_adherent AS numero_police,
                   c.taux_remboursement AS taux_couverture,
                   a.nom AS assurance_nom,
                   a.taux_remboursement AS assurance_taux_remboursement
            FROM contrats_assurance c
            INNER JOIN assurances a ON c.assurance_id = a.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.date_debut DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer tous les contrats pour une assurance donnée
     * @param int $assurance_id ID de l'assurance
     * @return array Liste des contrats
     */
    public function getContrats($assurance_id) {
        $pdo = getDB();
        $where = ['c.assurance_id = ?'];
        $params = [$assurance_id];
        TenantScope::appendWhere($pdo, 'contrats_assurance', $where, $params, 'c');
        $stmt = $pdo->prepare("
            SELECT c.*,
                   c.numero_adherent AS numero_police,
                   c.taux_remboursement AS taux_couverture,
                   p.nom AS patient_nom,
                   p.prenom AS patient_prenom
            FROM contrats_assurance c
            LEFT JOIN patients p ON c.patient_id = p.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.date_debut DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createContrat($data) {
        $pdo = getDB();
        
        if (empty($data['numero_contrat'])) {
            $data['numero_contrat'] = $this->generateNumeroContrat();
        }
        
        $columns = [
            'patient_id', 'assurance_id', 'numero_contrat', 'numero_adherent', 'date_debut', 'date_fin',
            'taux_remboursement', 'franchise', 'plafond_annuel', 'statut', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['patient_id'],
            $data['assurance_id'],
            $data['numero_contrat'],
            $data['numero_adherent'] ?? $data['numero_police'] ?? null,
            $data['date_debut'],
            $data['date_fin'] ?? null,
            $data['taux_remboursement'] ?? $data['taux_couverture'] ?? 100.00,
            $data['franchise'] ?? 0.00,
            $data['plafond_annuel'] ?? null,
            $data['statut'] ?? 'actif',
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'contrats_assurance', $columns, $placeholders, $values);

        $sql = 'INSERT INTO contrats_assurance (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function createRemboursement($data) {
        $pdo = getDB();
        
        $columns = [
            'contrat_id', 'paiement_id', 'consultation_id', 'montant_total', 'montant_rembourse',
            'date_demande', 'statut', 'numero_dossier', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['contrat_id'],
            $data['paiement_id'] ?? null,
            $data['consultation_id'] ?? null,
            $data['montant_total'],
            $data['montant_rembourse'],
            $data['date_demande'],
            $data['statut'] ?? 'en_attente',
            $data['numero_dossier'] ?? null,
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'remboursements', $columns, $placeholders, $values);

        $sql = 'INSERT INTO remboursements (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function getStats() {
        $pdo = getDB();

        $stats = [
            'assurances_actives' => TenantScope::count($pdo, 'assurances', ["statut = 'actif'"]),
            'contrats_actifs' => TenantScope::count($pdo, 'contrats_assurance', ["statut = 'actif'"]),
            'remboursements_en_attente' => TenantScope::count($pdo, 'remboursements', ["statut = 'en_attente'"]),
        ];

        $where = ["statut = 'rembourse'"];
        $params = [];
        TenantScope::appendWhere($pdo, 'remboursements', $where, $params);
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant_rembourse), 0) as total FROM remboursements WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $stats['montant_rembourse_total'] = (float) ($stmt->fetchColumn() ?: 0);

        return $stats;
    }
    
    private function generateNumeroContrat() {
        $pdo = getDB();
        $prefix = 'CTR';
        $year = date('Y');
        $where = ['numero_contrat LIKE ?'];
        $params = [$prefix . $year . '%'];
        TenantScope::appendWhere($pdo, 'contrats_assurance', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM contrats_assurance WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        return $prefix . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}

