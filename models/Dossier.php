<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Dossier {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }

    private function scopeTenant(array &$where, array &$params, string $alias = 'd'): void
    {
        TenantScope::appendWhere($this->pdo, 'dossiers', $where, $params, $alias);
    }
    
    public function getAll($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR p.numero_dossier LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['statut'])) {
            $whereConditions[] = "d.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['priorite'])) {
            $whereConditions[] = "d.priorite = ?";
            $params[] = $filters['priorite'];
        }

        $this->scopeTenant($whereConditions, $params);
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT d.*, p.nom, p.prenom, p.sexe, p.date_naissance, p.numero_dossier
                FROM dossiers d
                INNER JOIN patients p ON d.patient_id = p.id
                $whereClause
                ORDER BY d.date_creation DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $sql = "SELECT d.*, p.nom, p.prenom, p.sexe, p.date_naissance, p.numero_dossier
                FROM dossiers d
                INNER JOIN patients p ON d.patient_id = p.id
                WHERE d.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'dossiers', 'd');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($this->pdo, 'dossiers', (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getByPatientId($patient_id) {
        $where = ['d.patient_id = ?'];
        $params = [(int) $patient_id];
        $this->scopeTenant($where, $params);

        $sql = "SELECT d.*, p.nom, p.prenom, p.sexe, p.date_naissance, p.numero_dossier
                FROM dossiers d
                INNER JOIN patients p ON d.patient_id = p.id
                WHERE " . implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function create($data) {
        $columns = ['patient_id', 'groupe_sanguin', 'priorite', 'antecedents', 'allergies', 'statut', 'notes', 'date_creation'];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', 'NOW()'];
        $values = [
            $data['patient_id'],
            $data['groupe_sanguin'] ?? null,
            $data['priorite'] ?? 'basse',
            $data['antecedents'] ?? null,
            $data['allergies'] ?? null,
            $data['statut'] ?? 'actif',
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($this->pdo, 'dossiers', $columns, $placeholders, $values);
        $sql = 'INSERT INTO dossiers (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $stmt = $this->pdo->prepare(
            'UPDATE dossiers SET groupe_sanguin = ?, priorite = ?, antecedents = ?, allergies = ?,
                statut = ?, notes = ?, date_modification = NOW()
                WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'dossiers')
        );
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'dossiers', [
            $data['groupe_sanguin'] ?? null,
            $data['priorite'] ?? 'basse',
            $data['antecedents'] ?? null,
            $data['allergies'] ?? null,
            $data['statut'] ?? 'actif',
            $data['notes'] ?? null,
            (int) $id,
        ]));
    }
    
    public function delete($id) {
        try {
            $sql = 'DELETE FROM dossiers WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'dossiers');
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(TenantScope::paramsForId($this->pdo, 'dossiers', (int) $id));
            
            // Invalider le cache du dashboard pour mettre à jour les compteurs
            if ($result) {
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) {
                    // Ignorer les erreurs de cache, la suppression a réussi
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du dossier ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    public function count($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR p.numero_dossier LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $this->scopeTenant($whereConditions, $params);
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM dossiers d
                INNER JOIN patients p ON d.patient_id = p.id
                $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    public function getStats() {
        $where = ['1=1'];
        $params = [];
        TenantScope::appendWhere($this->pdo, 'dossiers', $where, $params);
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN statut = 'actif' THEN 1 END) as actifs,
                COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as inactifs,
                COUNT(CASE WHEN statut = 'archive' THEN 1 END) as archives
                FROM dossiers $whereClause";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    public function getGroupesSanguins() {
        return [
            'A+' => 'A+',
            'A-' => 'A-',
            'B+' => 'B+',
            'B-' => 'B-',
            'AB+' => 'AB+',
            'AB-' => 'AB-',
            'O+' => 'O+',
            'O-' => 'O-'
        ];
    }
    
    public function getPriorites() {
        return [
            'basse' => 'Basse',
            'moyenne' => 'Moyenne',
            'haute' => 'Haute'
        ];
    }
    
    public function getStatuts() {
        return [
            'actif' => 'Actif',
            'inactif' => 'Inactif',
            'archive' => 'Archivé'
        ];
    }
}
?>
