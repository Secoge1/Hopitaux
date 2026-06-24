<?php
/**
 * Modèle Medicament - Gestion des médicaments de la pharmacie
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Medicament {

    private string $lastDeleteError = '';

    public function getLastDeleteError(): string
    {
        return $this->lastDeleteError;
    }

    private function scopeTenant(PDO $pdo, array &$where, array &$params): void
    {
        TenantScope::appendWhere($pdo, 'medicaments', $where, $params);
    }

    /** Médicaments visibles dans les listes (hors corbeille). */
    private function appendActiveOnly(array &$where): void
    {
        $where[] = "statut != 'retire'";
    }
    
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '', $categorie = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(nom_commercial) LIKE LOWER(?) OR LOWER(nom_generique) LIKE LOWER(?) OR code_medicament LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        } else {
            $this->appendActiveOnly($where);
        }
        
        if ($categorie) {
            $where[] = "categorie = ?";
            $params[] = $categorie;
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM medicaments $where_clause ORDER BY nom_commercial LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getCount($search = '', $statut = '', $categorie = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(nom_commercial) LIKE LOWER(?) OR LOWER(nom_generique) LIKE LOWER(?) OR code_medicament LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        } else {
            $this->appendActiveOnly($where);
        }
        
        if ($categorie) {
            $where[] = "categorie = ?";
            $params[] = $categorie;
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT COUNT(*) as total FROM medicaments $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    public function getById($id) {
        $pdo = getDB();
        $sql = 'SELECT * FROM medicaments WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'medicaments');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'medicaments')));
        return $stmt->fetch();
    }
    
    public function create($data) {
        $pdo = getDB();
        
        if (empty($data['code_medicament'])) {
            $data['code_medicament'] = $this->generateCode();
        }
        
        $columns = [
            'code_medicament', 'nom_commercial', 'nom_generique', 'categorie', 'forme', 'dosage', 'unite',
            'stock_actuel', 'stock_minimum', 'stock_maximum', 'prix_unitaire', 'fournisseur',
            'date_peremption', 'lot', 'statut', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['code_medicament'],
            $data['nom_commercial'],
            $data['nom_generique'] ?? null,
            $data['categorie'] ?? null,
            $data['forme'] ?? 'comprime',
            $data['dosage'] ?? null,
            $data['unite'] ?? null,
            $data['stock_actuel'] ?? 0,
            $data['stock_minimum'] ?? 10,
            $data['stock_maximum'] ?? 1000,
            $data['prix_unitaire'],
            $data['fournisseur'] ?? null,
            $data['date_peremption'] ?? null,
            $data['lot'] ?? null,
            $data['statut'] ?? 'disponible',
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'medicaments', $columns, $placeholders, $values);
        $sql = 'INSERT INTO medicaments (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function update($id, $data) {
        $pdo = getDB();
        
        $sql = 'UPDATE medicaments SET
            nom_commercial = ?, nom_generique = ?, categorie = ?, forme = ?, dosage = ?, unite = ?,
            stock_actuel = ?, stock_minimum = ?, stock_maximum = ?, prix_unitaire = ?, fournisseur = ?,
            date_peremption = ?, lot = ?, statut = ?, notes = ?
            WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'medicaments');
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($pdo, 'medicaments', [
            $data['nom_commercial'],
            $data['nom_generique'] ?? null,
            $data['categorie'] ?? null,
            $data['forme'] ?? 'comprime',
            $data['dosage'] ?? null,
            $data['unite'] ?? null,
            $data['stock_actuel'] ?? 0,
            $data['stock_minimum'] ?? 10,
            $data['stock_maximum'] ?? 1000,
            $data['prix_unitaire'],
            $data['fournisseur'] ?? null,
            $data['date_peremption'] ?? null,
            $data['lot'] ?? null,
            $data['statut'] ?? 'disponible',
            $data['notes'] ?? null,
            (int) $id,
        ]));
    }
    
    public function delete($id) {
        try {
            $pdo = getDB();
            if (!$this->getById($id)) {
                return false;
            }
            $stmt = $pdo->prepare(
                "UPDATE medicaments SET statut = 'retire' WHERE id = ?" . TenantScope::andOwnedByTenant($pdo, 'medicaments')
            );
            $stmt->execute(TenantScope::paramsForId($pdo, 'medicaments', (int) $id));
            if ($stmt->rowCount() > 0) {
                $this->invalidateDashboardCache();
                return true;
            }
        } catch (Exception $e) {
            error_log("Medicament::delete($id) soft: " . $e->getMessage());
        }

        return $this->hardDelete($id);
    }

    /**
     * Suppression physique d'un médicament et de ses mouvements de stock.
     */
    public function hardDelete($id) {
        $this->lastDeleteError = '';
        $pdo = getDB();
        try {
            if (!$this->getById($id)) {
                return false;
            }
            $pdo->beginTransaction();

            TenantScope::deleteWhere($pdo, 'mouvements_stock_pharmacie', ['medicament_id = ?'], [(int) $id]);

            $stmt = $pdo->prepare(
                'DELETE FROM medicaments WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'medicaments')
            );
            $stmt->execute(TenantScope::paramsForId($pdo, 'medicaments', (int) $id));
            $deleted = $stmt->rowCount() > 0;
            $pdo->commit();

            if ($deleted) {
                $this->invalidateDashboardCache();
            }
            return $deleted;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur lors de la suppression physique du médicament ID $id: " . $e->getMessage());
            $this->lastDeleteError = $e->getMessage();
            return false;
        }
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
    
    public function getStockAlertes() {
        $pdo = getDB();
        $where = ['stock_actuel <= stock_minimum', "statut = 'disponible'"];
        $params = [];
        $this->scopeTenant($pdo, $where, $params);
        $sql = 'SELECT * FROM medicaments WHERE ' . implode(' AND ', $where) . ' ORDER BY stock_actuel ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getPeremptionAlertes() {
        $pdo = getDB();
        $where = [
            'date_peremption IS NOT NULL',
            'date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
            "statut = 'disponible'",
        ];
        $params = [];
        $this->scopeTenant($pdo, $where, $params);
        $sql = 'SELECT * FROM medicaments WHERE ' . implode(' AND ', $where) . ' ORDER BY date_peremption ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getMouvementsStock($medicament_id, $limit = 20) {
        $pdo = getDB();
        if (!$this->getById($medicament_id)) {
            return [];
        }
        $limit = (int) $limit;
        $where = ['medicament_id = ?'];
        $params = [(int) $medicament_id];
        TenantScope::appendWhere($pdo, 'mouvements_stock_pharmacie', $where, $params);
        $stmt = $pdo->prepare(
            'SELECT * FROM mouvements_stock_pharmacie WHERE ' . implode(' AND ', $where)
            . " ORDER BY date_mouvement DESC LIMIT $limit"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function addMouvement($medicament_id, $type, $quantite, $motif = null, $reference = null, $user_id = null) {
        $pdo = getDB();
        if (!$this->getById($medicament_id)) {
            return false;
        }
        $quantite = (int) $quantite;
        $signe = in_array($type, ['entree', 'ajustement', 'retour'], true) ? '+' : '-';
        $stmt = $pdo->prepare(
            'UPDATE medicaments SET stock_actuel = stock_actuel ' . $signe . ' ? WHERE id = ?'
            . TenantScope::andOwnedByTenant($pdo, 'medicaments')
        );
        if (!$stmt->execute(TenantScope::appendOwned($pdo, 'medicaments', [$quantite, (int) $medicament_id]))) {
            return false;
        }
        
        $columns = ['medicament_id', 'type_mouvement', 'quantite', 'motif', 'reference', 'utilisateur_id'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [$medicament_id, $type, $quantite, $motif, $reference, $user_id];
        TenantScope::bindInsert($pdo, 'mouvements_stock_pharmacie', $columns, $placeholders, $values);
        $sql = 'INSERT INTO mouvements_stock_pharmacie (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function getStats() {
        $pdo = getDB();

        return [
            'total' => TenantScope::count($pdo, 'medicaments', ["statut != 'retire'"]),
            'disponibles' => TenantScope::count($pdo, 'medicaments', ["statut = 'disponible'"]),
            'alertes_stock' => TenantScope::count($pdo, 'medicaments', [
                "statut = 'disponible'",
                'stock_actuel <= stock_minimum',
            ]),
            'alertes_peremption' => TenantScope::count($pdo, 'medicaments', [
                "statut = 'disponible'",
                'date_peremption IS NOT NULL',
                'date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
            ]),
        ];
    }
    
    private function generateCode() {
        $pdo = getDB();
        $prefix = 'MED';
        $year = date('Y');
        $where = ['code_medicament LIKE ?'];
        $params = ["$prefix$year%"];
        TenantScope::appendWhere($pdo, 'medicaments', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM medicaments WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        $count = $result ? (int)$result['count'] : 0;
        
        return $prefix . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}





