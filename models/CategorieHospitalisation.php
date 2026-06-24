<?php
/**
 * Modèle CategorieHospitalisation - Gestion des catégories d'hospitalisation
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class CategorieHospitalisation {
    private $pdo;

    private function scopeTenant(array &$where, array &$params): void
    {
        TenantScope::appendWhere($this->pdo, 'categories_hospitalisation', $where, $params);
    }
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    public function getAll($statut = null) {
        $where = [];
        $params = [];
        if ($statut) {
            $where[] = 'statut = ?';
            $params[] = $statut;
        }
        $this->scopeTenant($where, $params);
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM categories_hospitalisation $whereSql ORDER BY nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $sql = 'SELECT * FROM categories_hospitalisation WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'categories_hospitalisation');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($this->pdo, 'categories_hospitalisation', (int) $id));
        return $stmt->fetch();
    }
    
    public function create($data) {
        $columns = ['nom', 'description', 'prix_jour', 'statut'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['nom'],
            $data['description'] ?? null,
            $data['prix_jour'] ?? 0.00,
            $data['statut'] ?? 'actif',
        ];
        TenantScope::bindInsert($this->pdo, 'categories_hospitalisation', $columns, $placeholders, $values);
        $sql = 'INSERT INTO categories_hospitalisation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function update($id, $data) {
        if (!$this->getById($id)) {
            return false;
        }
        $fields = [];
        $values = [];
        foreach (['nom', 'description', 'prix_jour', 'statut'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "$key = ?";
                $values[] = $data[$key];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $values[] = (int) $id;
        $sql = 'UPDATE categories_hospitalisation SET ' . implode(', ', $fields) . ' WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'categories_hospitalisation');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'categories_hospitalisation', $values));
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare(
            'DELETE FROM categories_hospitalisation WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'categories_hospitalisation')
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'categories_hospitalisation', (int) $id));
    }
}
