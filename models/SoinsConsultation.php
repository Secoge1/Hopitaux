<?php
/**
 * Modèle pour la gestion des soins de consultation
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class SoinsConsultation {
    private $pdo;

    private function scopeTenant(array &$where, array &$params): void
    {
        TenantScope::appendWhere($this->pdo, 'soins_consultation', $where, $params);
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
        $sql = "SELECT * FROM soins_consultation $whereSql ORDER BY nom ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $sql = 'SELECT * FROM soins_consultation WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'soins_consultation');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($this->pdo, 'soins_consultation', (int) $id));
        return $stmt->fetch();
    }
    
    public function create($data) {
        $columns = ['nom', 'description', 'prix', 'type_soin', 'duree_minutes', 'statut', 'date_creation'];
        $placeholders = ['?', '?', '?', '?', '?', '?', 'NOW()'];
        $values = [
            $data['nom'],
            $data['description'] ?? null,
            $data['prix'],
            $data['type_soin'],
            $data['duree_minutes'] ?? 30,
            $data['statut'] ?? 'actif',
        ];
        TenantScope::bindInsert($this->pdo, 'soins_consultation', $columns, $placeholders, $values);
        $sql = 'INSERT INTO soins_consultation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function update($id, $data) {
        if (!$this->getById($id)) {
            return false;
        }
        $sql = 'UPDATE soins_consultation SET 
                nom = ?, description = ?, prix = ?, type_soin = ?, duree_minutes = ?, statut = ?, date_modification = NOW()
                WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'soins_consultation');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'soins_consultation', [
            $data['nom'],
            $data['description'] ?? null,
            $data['prix'],
            $data['type_soin'],
            $data['duree_minutes'] ?? 30,
            $data['statut'],
            (int) $id,
        ]));
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare(
            'DELETE FROM soins_consultation WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'soins_consultation')
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'soins_consultation', (int) $id));
    }
    
    public function getByType($type) {
        $where = ['type_soin = ?', "statut = 'actif'"];
        $params = [$type];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT * FROM soins_consultation WHERE ' . implode(' AND ', $where) . ' ORDER BY nom ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getTypes() {
        $where = ["statut = 'actif'"];
        $params = [];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT DISTINCT type_soin FROM soins_consultation WHERE ' . implode(' AND ', $where) . ' ORDER BY type_soin';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
