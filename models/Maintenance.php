<?php
/**
 * Modèle Maintenance - Gestion de la maintenance et logistique
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Maintenance {

    private string $lastDeleteError = '';

    public function getLastDeleteError(): string
    {
        return $this->lastDeleteError;
    }

    private function scopeEquipements(PDO $pdo, array &$where, array &$params, string $alias = ''): void
    {
        TenantScope::appendWhere($pdo, 'equipements', $where, $params, $alias);
    }

    private function scopeInterventions(PDO $pdo, array &$where, array &$params, string $alias = 'i'): void
    {
        TenantScope::appendWhere($pdo, 'interventions_maintenance', $where, $params, $alias);
    }
    
    public function getEquipements($page = 1, $limit = 10, $search = '', $statut = '', $categorie = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(nom) LIKE LOWER(?) OR numero_serie LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        }
        
        if ($categorie) {
            $where[] = "categorie = ?";
            $params[] = $categorie;
        }

        $this->scopeEquipements($pdo, $where, $params);
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM equipements $where_clause ORDER BY nom LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getCount($search = '', $statut = '', $categorie = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(LOWER(nom) LIKE LOWER(?) OR numero_serie LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        }
        
        if ($categorie) {
            $where[] = "categorie = ?";
            $params[] = $categorie;
        }

        $this->scopeEquipements($pdo, $where, $params);
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) as total FROM equipements $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    public function getEquipementById($id) {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM equipements WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'equipements')
        );
        $stmt->execute(TenantScope::paramsForId($pdo, 'equipements', (int) $id));
        return $stmt->fetch();
    }
    
    public function createEquipement($data) {
        $pdo = getDB();
        
        if (empty($data['numero_serie'])) {
            $data['numero_serie'] = $this->generateNumeroSerie();
        }

        $columns = [
            'numero_serie', 'nom', 'categorie', 'marque', 'modele', 'date_acquisition',
            'valeur', 'localisation', 'statut', 'date_derniere_maintenance', 'prochaine_maintenance', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['numero_serie'],
            $data['nom'],
            $data['categorie'] ?? null,
            $data['marque'] ?? null,
            $data['modele'] ?? null,
            $data['date_acquisition'] ?? null,
            $data['valeur'] ?? null,
            $data['localisation'] ?? null,
            $data['statut'] ?? 'disponible',
            $data['date_derniere_maintenance'] ?? null,
            $data['prochaine_maintenance'] ?? null,
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'equipements', $columns, $placeholders, $values);
        $sql = 'INSERT INTO equipements (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function updateEquipement($id, $data) {
        $pdo = getDB();
        if (!$this->getEquipementById($id)) {
            return false;
        }
        
        $sql = 'UPDATE equipements SET
            nom = ?, categorie = ?, marque = ?, modele = ?, date_acquisition = ?,
            valeur = ?, localisation = ?, statut = ?, date_derniere_maintenance = ?,
            prochaine_maintenance = ?, notes = ?
            WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'equipements');
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($pdo, 'equipements', [
            $data['nom'],
            $data['categorie'] ?? null,
            $data['marque'] ?? null,
            $data['modele'] ?? null,
            $data['date_acquisition'] ?? null,
            $data['valeur'] ?? null,
            $data['localisation'] ?? null,
            $data['statut'] ?? 'disponible',
            $data['date_derniere_maintenance'] ?? null,
            $data['prochaine_maintenance'] ?? null,
            $data['notes'] ?? null,
            (int) $id,
        ]));
    }
    
    public function deleteEquipement($id) {
        $pdo = getDB();
        $this->lastDeleteError = '';
        $equipement = $this->getEquipementById($id);
        if (!$equipement) {
            return false;
        }
        
        $pdo->beginTransaction();
        
        try {
            $whereInt = ['i.equipement_id = ?'];
            $paramsInt = [(int) $id];
            $this->scopeInterventions($pdo, $whereInt, $paramsInt, 'i');
            $stmt = $pdo->prepare(
                'DELETE i FROM interventions_maintenance i WHERE ' . implode(' AND ', $whereInt)
            );
            $stmt->execute($paramsInt);
            
            $stmt = $pdo->prepare(
                'DELETE FROM equipements WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'equipements')
            );
            $result = $stmt->execute(TenantScope::paramsForId($pdo, 'equipements', (int) $id));
            
            $pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $this->lastDeleteError = $e->getMessage();
            throw $e;
        }
    }
    
    public function getInterventions($equipement_id = null, $statut = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($equipement_id) {
            if (!$this->getEquipementById($equipement_id)) {
                return [];
            }
            $where[] = 'i.equipement_id = ?';
            $params[] = $equipement_id;
        }
        
        if ($statut) {
            $where[] = 'i.statut = ?';
            $params[] = $statut;
        }

        $this->scopeInterventions($pdo, $where, $params);
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT i.*, e.nom as equipement_nom, e.numero_serie
                FROM interventions_maintenance i
                INNER JOIN equipements e ON i.equipement_id = e.id
                $where_clause
                ORDER BY i.date_intervention DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createIntervention($data) {
        $pdo = getDB();
        if (!$this->getEquipementById($data['equipement_id'])) {
            return false;
        }

        $columns = [
            'equipement_id', 'type_intervention', 'date_intervention', 'technicien', 'cout',
            'description', 'resultat', 'statut', 'prochaine_intervention', 'cree_par',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['equipement_id'],
            $data['type_intervention'],
            $data['date_intervention'],
            $data['technicien'] ?? null,
            $data['cout'] ?? 0.00,
            $data['description'],
            $data['resultat'] ?? null,
            $data['statut'] ?? 'planifiee',
            $data['prochaine_intervention'] ?? null,
            $data['cree_par'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'interventions_maintenance', $columns, $placeholders, $values);
        $sql = 'INSERT INTO interventions_maintenance (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $intervention_id = $pdo->lastInsertId();
            $this->syncEquipementAfterIntervention($pdo, (int) $data['equipement_id'], $data);
            return $intervention_id;
        }
        
        return false;
    }

    public function getInterventionById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['i.id = ?'];
        $params = [$id];
        $this->scopeInterventions($pdo, $where, $params);
        $sql = 'SELECT i.*, e.nom AS equipement_nom, e.numero_serie, e.id AS equipement_id
                FROM interventions_maintenance i
                INNER JOIN equipements e ON i.equipement_id = e.id
                WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateIntervention(int $id, array $data): bool
    {
        $pdo = getDB();
        $intervention = $this->getInterventionById($id);
        if (!$intervention) {
            return false;
        }

        $sql = 'UPDATE interventions_maintenance SET
            type_intervention = ?, date_intervention = ?, technicien = ?, cout = ?,
            description = ?, resultat = ?, statut = ?, prochaine_intervention = ?
            WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'interventions_maintenance');

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute(TenantScope::appendOwned($pdo, 'interventions_maintenance', [
            $data['type_intervention'],
            $data['date_intervention'],
            $data['technicien'] ?? null,
            $data['cout'] ?? 0.00,
            $data['description'],
            $data['resultat'] ?? null,
            $data['statut'] ?? 'planifiee',
            $data['prochaine_intervention'] ?? null,
            $id,
        ]));

        if ($ok) {
            $this->syncEquipementAfterIntervention($pdo, (int) $intervention['equipement_id'], $data);
        }

        return $ok;
    }

    public function deleteIntervention(int $id): bool
    {
        $pdo = getDB();
        if (!$this->getInterventionById($id)) {
            return false;
        }
        $stmt = $pdo->prepare(
            'DELETE FROM interventions_maintenance WHERE id = ?'
            . TenantScope::andOwnedByTenant($pdo, 'interventions_maintenance')
        );
        return $stmt->execute(TenantScope::paramsForId($pdo, 'interventions_maintenance', $id));
    }

    public function setInterventionStatut(int $id, string $statut): bool
    {
        $intervention = $this->getInterventionById($id);
        if (!$intervention) {
            return false;
        }
        return $this->updateIntervention($id, array_merge($intervention, ['statut' => $statut]));
    }

    private function syncEquipementAfterIntervention(PDO $pdo, int $equipementId, array $data): void
    {
        if (($data['statut'] ?? '') !== 'terminee') {
            return;
        }
        $owned = TenantScope::paramsForId($pdo, 'equipements', $equipementId);
        $stmtUp = $pdo->prepare(
            'UPDATE equipements SET date_derniere_maintenance = ? WHERE id = ?'
            . TenantScope::andOwnedByTenant($pdo, 'equipements')
        );
        $stmtUp->execute(array_merge([$data['date_intervention']], $owned));

        if (!empty($data['prochaine_intervention'])) {
            $stmtUp2 = $pdo->prepare(
                'UPDATE equipements SET prochaine_maintenance = ? WHERE id = ?'
                . TenantScope::andOwnedByTenant($pdo, 'equipements')
            );
            $stmtUp2->execute(array_merge([$data['prochaine_intervention']], $owned));
        }
    }
    
    public function getMaintenanceAlertes() {
        $pdo = getDB();
        $where = [
            'prochaine_maintenance IS NOT NULL',
            'prochaine_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            "statut IN ('disponible', 'en_utilisation')",
        ];
        $params = [];
        $this->scopeEquipements($pdo, $where, $params);
        $stmt = $pdo->prepare(
            'SELECT * FROM equipements WHERE ' . implode(' AND ', $where) . ' ORDER BY prochaine_maintenance ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStats() {
        $pdo = getDB();

        return [
            'total_equipements' => TenantScope::count($pdo, 'equipements'),
            'equipements_disponibles' => TenantScope::count($pdo, 'equipements', ["statut = 'disponible'"]),
            'equipements_en_maintenance' => TenantScope::count($pdo, 'equipements', ["statut = 'en_maintenance'"]),
            'interventions_planifiees' => TenantScope::count($pdo, 'interventions_maintenance', ["statut = 'planifiee'"]),
        ];
    }
    
    private function generateNumeroSerie() {
        $pdo = getDB();
        $prefix = 'EQP';
        $year = date('Y');
        $where = ['numero_serie LIKE ?'];
        $params = ["$prefix$year%"];
        $this->scopeEquipements($pdo, $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM equipements WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        $count = $result ? (int)$result['count'] : 0;
        
        return $prefix . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
