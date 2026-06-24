<?php
/**
 * Modèle TarifConsultation - Gestion des tarifs de consultation
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class TarifConsultation {
    private $pdo;

    private function scopeTenant(array &$where, array &$params): void
    {
        TenantScope::appendWhere($this->pdo, 'tarifs_consultation', $where, $params);
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
        $sql = "SELECT * FROM tarifs_consultation $whereSql ORDER BY type_consultation, specialite";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $sql = 'SELECT * FROM tarifs_consultation WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_consultation');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($this->pdo, 'tarifs_consultation', (int) $id));
        return $stmt->fetch();
    }
    
    public function getByTypeAndSpecialite($type, $specialite = null) {
        $where = ['type_consultation = ?', "statut = 'actif'"];
        $params = [$type];
        if ($specialite) {
            $where[] = 'specialite = ?';
            $params[] = $specialite;
        }
        $this->scopeTenant($where, $params);
        $sql = 'SELECT * FROM tarifs_consultation WHERE ' . implode(' AND ', $where) . ' ORDER BY prix ASC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $columns = ['type_consultation', 'specialite', 'prix', 'description', 'statut'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['type_consultation'],
            $data['specialite'] ?? null,
            $data['prix'],
            $data['description'] ?? null,
            $data['statut'] ?? 'actif',
        ];
        TenantScope::bindInsert($this->pdo, 'tarifs_consultation', $columns, $placeholders, $values);
        $sql = 'INSERT INTO tarifs_consultation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function update($id, $data) {
        if (!$this->getById($id)) {
            return false;
        }
        $fields = [];
        $values = [];
        foreach (['type_consultation', 'specialite', 'prix', 'description', 'statut'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "$key = ?";
                $values[] = $data[$key];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $fields[] = 'date_modification = NOW()';
        $values[] = (int) $id;
        $sql = 'UPDATE tarifs_consultation SET ' . implode(', ', $fields) . ' WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_consultation');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'tarifs_consultation', $values));
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tarifs_consultation WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_consultation')
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'tarifs_consultation', (int) $id));
    }
    
    public function getTypes() {
        $where = ["statut = 'actif'"];
        $params = [];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT DISTINCT type_consultation FROM tarifs_consultation WHERE ' . implode(' AND ', $where) . ' ORDER BY type_consultation';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getSpecialites() {
        $where = ["statut = 'actif'", 'specialite IS NOT NULL'];
        $params = [];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT DISTINCT specialite FROM tarifs_consultation WHERE ' . implode(' AND ', $where) . ' ORDER BY specialite';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
