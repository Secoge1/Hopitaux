<?php
/**
 * Modèle SejourHospitalisation - Gestion des séjours d'hospitalisation
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class SejourHospitalisation {
    private $pdo;

    private function scopeTenant(array &$where, array &$params, string $alias = 's'): void
    {
        TenantScope::appendWhere($this->pdo, 'sejours_hospitalisation', $where, $params, $alias);
    }
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    public function getAll($page = 1, $limit = 10, $statut = '') {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        
        if (!empty($statut)) {
            $where[] = 's.statut = ?';
            $params[] = $statut;
        }

        $this->scopeTenant($where, $params);
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT s.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       c.nom as categorie_nom, c.prix_jour as categorie_prix,
                       cons.date_consultation, cons.type_consultation
                FROM sejours_hospitalisation s
                LEFT JOIN patients p ON s.patient_id = p.id
                LEFT JOIN categories_hospitalisation c ON s.categorie_id = c.id
                LEFT JOIN consultations cons ON s.consultation_id = cons.id
                $whereClause 
                ORDER BY s.date_admission DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $where = ['s.id = ?'];
        $params = [(int) $id];
        $this->scopeTenant($where, $params);
        $sql = "SELECT s.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       c.nom as categorie_nom, c.prix_jour as categorie_prix,
                       cons.date_consultation, cons.type_consultation
                FROM sejours_hospitalisation s
                LEFT JOIN patients p ON s.patient_id = p.id
                LEFT JOIN categories_hospitalisation c ON s.categorie_id = c.id
                LEFT JOIN consultations cons ON s.consultation_id = cons.id
                WHERE " . implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function getByConsultation($consultation_id) {
        $where = ['s.consultation_id = ?'];
        $params = [(int) $consultation_id];
        $this->scopeTenant($where, $params);
        $sql = "SELECT s.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       c.nom as categorie_nom, c.prix_jour as categorie_prix,
                       CASE 
                           WHEN s.date_sortie IS NOT NULL THEN 
                               DATEDIFF(s.date_sortie, s.date_admission) + 1
                           ELSE 
                               DATEDIFF(CURDATE(), s.date_admission) + 1
                       END as duree_jours,
                       CASE 
                           WHEN s.date_sortie IS NOT NULL THEN 
                               (DATEDIFF(s.date_sortie, s.date_admission) + 1) * c.prix_jour
                           ELSE 
                               (DATEDIFF(CURDATE(), s.date_admission) + 1) * c.prix_jour
                       END as prix_total
                FROM sejours_hospitalisation s
                LEFT JOIN patients p ON s.patient_id = p.id
                LEFT JOIN categories_hospitalisation c ON s.categorie_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.date_admission DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        require_once __DIR__ . '/CategorieHospitalisation.php';
        $catModel = new CategorieHospitalisation();
        $categorie = $catModel->getById($data['categorie_id']);
        if (!$categorie) {
            return false;
        }

        $date_admission = new DateTime($data['date_admission']);
        $date_sortie = isset($data['date_sortie']) && $data['date_sortie'] ? new DateTime($data['date_sortie']) : null;
        $duree_jours = 1;
        if ($date_sortie) {
            $duree_jours = $date_admission->diff($date_sortie)->days + 1;
        }
        $prix_jour = (float) ($categorie['prix_jour'] ?? 0);
        $prix_total = $duree_jours * $prix_jour;

        $columns = [
            'consultation_id', 'patient_id', 'categorie_id', 'date_admission', 'date_sortie',
            'duree_jours', 'prix_total', 'statut', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['consultation_id'],
            $data['patient_id'],
            $data['categorie_id'],
            $data['date_admission'],
            $data['date_sortie'] ?? null,
            $duree_jours,
            $prix_total,
            $data['statut'] ?? 'en_cours',
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($this->pdo, 'sejours_hospitalisation', $columns, $placeholders, $values);
        $sql = 'INSERT INTO sejours_hospitalisation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function update($id, $data) {
        if (!$this->getById($id)) {
            return false;
        }
        $fields = [];
        $values = [];
        foreach (['categorie_id', 'date_admission', 'date_sortie', 'statut', 'notes'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "$key = ?";
                $values[] = $data[$key];
            }
        }
        
        if (isset($data['date_admission']) || isset($data['date_sortie']) || isset($data['categorie_id'])) {
            $sejour = $this->getById($id);
            $date_admission = new DateTime($data['date_admission'] ?? $sejour['date_admission']);
            $date_sortie = isset($data['date_sortie']) && $data['date_sortie']
                ? new DateTime($data['date_sortie'])
                : ($sejour['date_sortie'] ? new DateTime($sejour['date_sortie']) : null);
            $duree_jours = 1;
            if ($date_sortie) {
                $duree_jours = $date_admission->diff($date_sortie)->days + 1;
            }
            $categorie_id = $data['categorie_id'] ?? $sejour['categorie_id'];
            require_once __DIR__ . '/CategorieHospitalisation.php';
            $categorie = (new CategorieHospitalisation())->getById($categorie_id);
            $prix_jour = (float) ($categorie['prix_jour'] ?? 0);
            $fields[] = 'duree_jours = ?';
            $values[] = $duree_jours;
            $fields[] = 'prix_total = ?';
            $values[] = $duree_jours * $prix_jour;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'date_modification = NOW()';
        $values[] = (int) $id;
        $sql = 'UPDATE sejours_hospitalisation SET ' . implode(', ', $fields) . ' WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'sejours_hospitalisation');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'sejours_hospitalisation', $values));
    }
    
    public function terminer($id, $date_sortie = null) {
        if (!$this->getById($id)) {
            return false;
        }
        $date_sortie = $date_sortie ?: date('Y-m-d H:i:s');
        $sql = "UPDATE sejours_hospitalisation SET date_sortie = ?, statut = 'termine', date_modification = NOW() WHERE id = ?"
            . TenantScope::andOwnedByTenant($this->pdo, 'sejours_hospitalisation');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'sejours_hospitalisation', [$date_sortie, (int) $id]));
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sejours_hospitalisation WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'sejours_hospitalisation')
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'sejours_hospitalisation', (int) $id));
    }
    
    public function getStats() {
        $stats = [];
        $stats['total'] = TenantScope::count($this->pdo, 'sejours_hospitalisation');
        $stats['en_cours'] = TenantScope::count($this->pdo, 'sejours_hospitalisation', ["statut = 'en_cours'"]);

        $where = [];
        $params = [];
        $this->scopeTenant($where, $params);
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT statut, COUNT(*) as count FROM sejours_hospitalisation $whereSql GROUP BY statut");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }
        
        return $stats;
    }
}
