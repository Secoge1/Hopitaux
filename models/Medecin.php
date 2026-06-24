<?php
/**
 * Modèle Medecin - Gestion des médecins
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/medecin_profil.php';
require_once __DIR__ . '/../includes/staff_mirror.php';

class Medecin {
    private $pdo;
    private string $lastDeleteError = '';

    public function getLastDeleteError(): string
    {
        return $this->lastDeleteError;
    }

    private function scopeTenant(array &$where, array &$params, string $alias = ''): void
    {
        TenantScope::appendWhere($this->pdo, 'medecins', $where, $params, $alias);
    }

    private function scopeStaff(array &$where, array &$params, string $alias = ''): void
    {
        StaffScope::appendMedecinFilter($where, $params, $alias);
    }

    private function columnExists(string $column): bool
    {
        static $cache = [];
        if (!isset($cache[$column])) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'medecins\' AND COLUMN_NAME = ?'
            );
            $stmt->execute([$column]);
            $cache[$column] = (bool) $stmt->fetchColumn();
        }
        return $cache[$column];
    }
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
        /**
     * Récupérer tous les médecins avec pagination
     */
    public function getAll($page = 1, $limit = 10, $search = '', $specialite = '', $typeProfil = '') {
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Exclure automatiquement les médecins supprimés
        $where[] = "statut != 'supprime'";

        if (!empty($search)) {
            $where[] = "(nom LIKE ? OR prenom LIKE ? OR numero_licence LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($specialite)) {
            $where[] = "specialite = ?";
            $params[] = $specialite;
        }

        if ($typeProfil !== '' && $this->columnExists('type_profil')) {
            $where[] = 'type_profil = ?';
            $params[] = $typeProfil;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT * FROM medecins $whereClause ORDER BY nom, prenom LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Liste des médecins actifs pour affectation patient (sans filtre staff). */
    public function listForAssignment(): array
    {
        $where = ["statut != 'supprime'"];
        $params = [];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT id, nom, prenom, specialite, type_profil FROM medecins WHERE ' . implode(' AND ', $where)
            . ' ORDER BY nom, prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Compter le nombre total de médecins
     */
    public function getCount($search = '', $specialite = '', $typeProfil = '') {
        $where = [];
        $params = [];
        
        // Exclure automatiquement les médecins supprimés
        $where[] = "statut != 'supprime'";
        
        if (!empty($search)) {
            $where[] = "(nom LIKE ? OR prenom LIKE ? OR numero_licence LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($specialite)) {
            $where[] = "specialite = ?";
            $params[] = $specialite;
        }

        if ($typeProfil !== '' && $this->columnExists('type_profil')) {
            $where[] = 'type_profil = ?';
            $params[] = $typeProfil;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT COUNT(*) as total FROM medecins $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Récupérer un médecin par ID (exclut les supprimés)
     */
    public function getById($id) {
        $sql = "SELECT * FROM medecins WHERE id = ? AND (statut IS NULL OR statut != 'supprime')"
            . TenantScope::andOwnedByTenant($this->pdo, 'medecins');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'medecins')));
        $row = $stmt->fetch();
        if ($row && !StaffScope::canAccessMedecin($row)) {
            return false;
        }
        return $row;
    }

    /**
     * Récupérer un médecin par ID sans filtrer par statut (pour suppression définitive, etc.)
     */
    public function getByIdIncludeDeleted($id) {
        $sql = 'SELECT * FROM medecins WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'medecins');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'medecins')));
        return $stmt->fetch();
    }
    
    /**
     * Prochain numéro d'ordre unique par établissement (tenant).
     */
    public function generateNumeroOrdre() {
        $where = ["numero_ordre REGEXP '^M[0-9]+$'"];
        $params = [];
        $this->scopeTenant($where, $params);

        $sql = 'SELECT COALESCE(MAX(CAST(SUBSTRING(numero_ordre, 2) AS UNSIGNED)), 0) + 1 AS n
                FROM medecins WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $n = (int) ($stmt->fetchColumn() ?: 1);
        if ($n < 1) {
            $n = 1;
        }

        return 'M' . $n;
    }

    /**
     * Créer un nouveau médecin
     */
    public function create($data) {
        $numeroOrdre = $data['numero_ordre'] ?? $this->generateNumeroOrdre();
        $typeProfil = $data['type_profil'] ?? 'medecin';
        if (!medecin_profil_is_valid($typeProfil)) {
            $typeProfil = 'medecin';
        }

        $columns = [
            'numero_ordre', 'numero_licence', 'nom', 'prenom', 'specialite', 'telephone', 'email',
            'adresse', 'ville', 'code_postal', 'pays', 'date_embauche', 'statut', 'date_creation',
        ];
        $values = [
            $numeroOrdre,
            $data['numero_licence'],
            $data['nom'],
            $data['prenom'],
            $data['specialite'] ?? medecin_profil_label($typeProfil),
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['code_postal'] ?? null,
            $data['pays'] ?? 'France',
            $data['date_embauche'],
            $data['statut'] ?? 'actif',
        ];
        if ($this->columnExists('type_profil')) {
            array_splice($columns, 5, 0, ['type_profil']);
            array_splice($values, 5, 0, [$typeProfil]);
        }
        $placeholders = array_fill(0, count($columns) - 1, '?');
        $placeholders[] = 'NOW()';
        TenantScope::bindInsert($this->pdo, 'medecins', $columns, $placeholders, $values);
        $sql = 'INSERT INTO medecins (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute($values)) {
            return false;
        }
        $newId = (int) $this->pdo->lastInsertId();
        if ($newId > 0) {
            StaffMirror::syncPersonnelFromMedecin($newId);
        }
        return $newId;
    }
    
    /**
     * Mettre à jour un médecin
     */
    public function update($id, $data) {
        $sets = [
            'numero_licence = ?', 'nom = ?', 'prenom = ?', 'specialite = ?',
            'telephone = ?', 'email = ?', 'adresse = ?', 'ville = ?',
            'code_postal = ?', 'pays = ?', 'date_embauche = ?', 'statut = ?',
        ];
        $params = [
            $data['numero_licence'],
            $data['nom'],
            $data['prenom'],
            $data['specialite'],
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['code_postal'] ?? null,
            $data['pays'] ?? 'France',
            $data['date_embauche'],
            $data['statut'] ?? 'actif',
        ];
        if ($this->columnExists('type_profil') && isset($data['type_profil'])) {
            $typeProfil = $data['type_profil'];
            if (medecin_profil_is_valid($typeProfil)) {
                $sets[] = 'type_profil = ?';
                $params[] = $typeProfil;
            }
        }
        $params[] = $id;
        $sql = 'UPDATE medecins SET ' . implode(', ', $sets) . ' WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'medecins');
        $params = array_merge($params, TenantScope::ownedParam($this->pdo, 'medecins'));
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok) {
            StaffMirror::syncPersonnelFromMedecin((int) $id);
        }
        return $ok;
    }
    
    /**
     * Suppression logique d'un médecin (soft delete)
     * Marque le médecin comme supprimé. Si la table n'a pas 'supprime' dans l'enum
     * ou pas de colonne date_suppression, tente une mise à jour partielle puis hard delete en secours.
     */
    public function delete($id) {
        $pdo = getDB();
        $medecin = $this->getById($id);
        if (!$medecin) {
            return false;
        }

        // 1) Tenter soft delete complet (statut + date_suppression)
        try {
            $sql = "UPDATE medecins SET statut = 'supprime', date_suppression = NOW() WHERE id = ?"
                . TenantScope::andOwnedByTenant($pdo, 'medecins');
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'medecins')));
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Colonne date_suppression absente ou autre erreur : on continue
        }

        // 2) Tenter uniquement statut = 'supprime' (si enum contient 'supprime')
        try {
            $stmt = $pdo->prepare(
                "UPDATE medecins SET statut = 'supprime' WHERE id = ?" . TenantScope::andOwnedByTenant($pdo, 'medecins')
            );
            $stmt->execute(TenantScope::paramsForId($pdo, 'medecins', (int) $id));
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Enum sans 'supprime' ou autre erreur
        }

        // 3) Secours : suppression physique pour que le médecin disparaisse de la liste
        try {
            return $this->hardDelete($id);
        } catch (Throwable $e) {
            error_log("Medecin::delete($id) échec: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suppression physique d'un médecin (hard delete)
     * Supprime complètement le médecin et toutes ses données liées
     * ATTENTION: Cette méthode est irréversible !
     */
    public function hardDelete($id) {
        $this->lastDeleteError = '';
        try {
            require_once __DIR__ . '/Consultation.php';
            $consultationModel = new Consultation();

            $this->pdo->beginTransaction();

            // Consultations : supprimer d'abord les tables enfants (soins, tickets, etc.)
            $consultationModel->deleteAllForMedecin((int) $id, $this->pdo);

            foreach (['rendez_vous', 'analyses'] as $table) {
                TenantScope::deleteWhere($this->pdo, $table, ['medecin_id = ?'], [$id]);
            }

            $sql = 'DELETE FROM medecins WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'medecins');
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'medecins')));

            $this->pdo->commit();
            return $result && $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erreur lors de la suppression physique du médecin ID $id: " . $e->getMessage());
            $this->lastDeleteError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Générer un numéro de licence unique
     */
    public function generateNumeroLicence() {
        $prefix = 'MED';
        $year = date('Y');
        $where = ['numero_licence LIKE ?'];
        $params = ["$prefix$year%"];
        TenantScope::appendWhere($this->pdo, 'medecins', $where, $params);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM medecins WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        $count = $result['total'] + 1;
        return $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Récupérer les statistiques des médecins
     */
    public function getStats() {
        $pdo = $this->pdo;
        $stats = [];

        $stats['total'] = TenantScope::count($pdo, 'medecins', ["statut != 'supprime'"]);

        $where = ["statut != 'supprime'"];
        $params = [];
        TenantScope::appendWhere($pdo, 'medecins', $where, $params);
        $stmt = $pdo->prepare('SELECT statut, COUNT(*) as count FROM medecins WHERE ' . implode(' AND ', $where) . ' GROUP BY statut');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }

        $stats['supprime'] = TenantScope::count($pdo, 'medecins', ["statut = 'supprime'"]);

        $whereSpec = ["statut != 'supprime'", 'specialite IS NOT NULL', "specialite != ''"];
        $paramsSpec = [];
        TenantScope::appendWhere($pdo, 'medecins', $whereSpec, $paramsSpec);
        $stmt = $pdo->prepare(
            'SELECT specialite, COUNT(*) as count FROM medecins WHERE ' . implode(' AND ', $whereSpec)
            . ' GROUP BY specialite ORDER BY count DESC LIMIT 5'
        );
        $stmt->execute($paramsSpec);
        $stats['specialites'] = $stmt->fetchAll();

        return $stats;
    }
    
    /**
     * Récupérer toutes les spécialités
     */
    public function getSpecialites() {
        $where = ["statut != 'supprime'"];
        $params = [];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT DISTINCT specialite FROM medecins WHERE ' . implode(' AND ', $where) . ' ORDER BY specialite';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Récupérer les médecins supprimés (pour l'administration)
     */
    public function getDeleted($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = ["statut = 'supprime'"];
        $params = [];
        $this->scopeTenant($where, $params);
        
        $sql = 'SELECT * FROM medecins WHERE ' . implode(' AND ', $where) . " ORDER BY date_suppression DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Restaurer un médecin supprimé
     */
    public function restore($id) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE medecins SET statut = 'actif', date_suppression = NULL WHERE id = ? AND statut = 'supprime'"
                . TenantScope::andOwnedByTenant($this->pdo, 'medecins')
            );
            return $stmt->execute(TenantScope::paramsForId($this->pdo, 'medecins', (int) $id));
        } catch (Exception $e) {
            error_log("Erreur lors de la restauration du médecin ID $id: " . $e->getMessage());
            return false;
        }
    }
}
?>
