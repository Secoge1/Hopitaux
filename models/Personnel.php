<?php
/**
 * Modèle Personnel - Gestion du personnel hospitalier
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Personnel {

    private function scopeTenant(PDO $pdo, array &$where, array &$params): void
    {
        TenantScope::appendWhere($pdo, 'personnel', $where, $params);
    }
    
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '', $poste = '', $departement = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(nom) LIKE LOWER(?) OR LOWER(prenom) LIKE LOWER(?) OR numero_employe LIKE ? OR LOWER(CONCAT(prenom, ' ', nom)) LIKE LOWER(?))";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        }
        
        if ($poste) {
            $where[] = "poste = ?";
            $params[] = $poste;
        }
        
        if ($departement) {
            $where[] = "departement = ?";
            $params[] = $departement;
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM personnel $where_clause ORDER BY nom, prenom LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $personnel = $stmt->fetchAll();
        
        foreach ($personnel as &$person) {
            $person['age'] = $this->calculateAge($person['date_naissance']);
        }
        
        return $personnel;
    }
    
    public function getCount($search = '', $statut = '', $poste = '', $departement = '') {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(nom) LIKE LOWER(?) OR LOWER(prenom) LIKE LOWER(?) OR numero_employe LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($statut) {
            $where[] = "statut = ?";
            $params[] = $statut;
        }
        
        if ($poste) {
            $where[] = "poste = ?";
            $params[] = $poste;
        }
        
        if ($departement) {
            $where[] = "departement = ?";
            $params[] = $departement;
        }

        $this->scopeTenant($pdo, $where, $params);
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT COUNT(*) as total FROM personnel $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Personnel actif du laboratoire (pour assignation analyses).
     *
     * @return list<array<string, mixed>>
     */
    public function listTechniciensLaboratoire(int $limit = 500): array
    {
        $pdo = getDB();
        $where = ["statut = 'actif'"];
        $params = [];
        $this->scopeTenant($pdo, $where, $params);
        $sql = 'SELECT id, nom, prenom, poste, departement FROM personnel WHERE '
            . implode(' AND ', $where) . ' ORDER BY nom, prenom LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lab = array_values(array_filter($rows, static function (array $p): bool {
            $dept = strtolower((string) ($p['departement'] ?? ''));
            $poste = strtolower((string) ($p['poste'] ?? ''));
            return strpos($dept, 'labor') !== false
                || strpos($poste, 'laborantin') !== false
                || strpos($poste, 'technicien') !== false;
        }));
        return $lab !== [] ? $lab : $rows;
    }

    public function getById($id) {
        $pdo = getDB();
        $sql = 'SELECT * FROM personnel WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'personnel');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'personnel')));
        $personnel = $stmt->fetch();
        
        if ($personnel) {
            $personnel['age'] = $this->calculateAge($personnel['date_naissance']);
        }
        
        return $personnel;
    }
    
    public function create($data) {
        $pdo = getDB();
        
        // Générer un numéro d'employé unique
        if (empty($data['numero_employe'])) {
            $data['numero_employe'] = $this->generateNumeroEmploye();
        }
        
        $columns = [
            'numero_employe', 'nom', 'prenom', 'date_naissance', 'sexe', 'telephone', 'email',
            'adresse', 'ville', 'code_postal', 'pays', 'poste', 'departement', 'date_embauche',
            'salaire', 'type_contrat', 'statut', 'photo', 'notes',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['numero_employe'],
            $data['nom'],
            $data['prenom'],
            $data['date_naissance'] ?? null,
            $data['sexe'] ?? null,
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['code_postal'] ?? null,
            $data['pays'] ?? 'Mali',
            $data['poste'],
            $data['departement'] ?? null,
            $data['date_embauche'],
            $data['salaire'] ?? null,
            $data['type_contrat'] ?? 'CDI',
            $data['statut'] ?? 'actif',
            $data['photo'] ?? null,
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'personnel', $columns, $placeholders, $values);
        $sql = 'INSERT INTO personnel (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
    
    public function update($id, $data) {
        $pdo = getDB();
        
        $sql = 'UPDATE personnel SET
            nom = ?, prenom = ?, date_naissance = ?, sexe = ?, telephone = ?, email = ?,
            adresse = ?, ville = ?, code_postal = ?, pays = ?, poste = ?, departement = ?,
            date_embauche = ?, salaire = ?, type_contrat = ?, statut = ?, photo = ?, notes = ?
            WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'personnel');
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($pdo, 'personnel', [
            $data['nom'],
            $data['prenom'],
            $data['date_naissance'] ?? null,
            $data['sexe'] ?? null,
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['code_postal'] ?? null,
            $data['pays'] ?? 'Mali',
            $data['poste'],
            $data['departement'] ?? null,
            $data['date_embauche'],
            $data['salaire'] ?? null,
            $data['type_contrat'] ?? 'CDI',
            $data['statut'] ?? 'actif',
            $data['photo'] ?? null,
            $data['notes'] ?? null,
            (int) $id,
        ]));
    }
    
    public function delete($id) {
        try {
            $pdo = getDB();
            $row = $this->getById($id);
            if (!$row) {
                return false;
            }
            if (($row['statut'] ?? '') === 'inactif') {
                return true;
            }

            $stmt = $pdo->prepare(
                "UPDATE personnel SET statut = 'inactif' WHERE id = ?" . TenantScope::andOwnedByTenant($pdo, 'personnel')
            );
            $result = $stmt->execute(TenantScope::paramsForId($pdo, 'personnel', (int) $id));

            if ($result) {
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) {
                    // Ignorer les erreurs de cache
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du personnel ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStats() {
        $pdo = getDB();

        $stats = [
            'total' => TenantScope::count($pdo, 'personnel'),
            'actif' => TenantScope::count($pdo, 'personnel', ["statut = 'actif'"]),
        ];

        $wherePoste = ["statut = 'actif'"];
        $paramsPoste = [];
        TenantScope::appendWhere($pdo, 'personnel', $wherePoste, $paramsPoste);
        $stmt = $pdo->prepare('SELECT poste, COUNT(*) as count FROM personnel WHERE ' . implode(' AND ', $wherePoste) . ' GROUP BY poste');
        $stmt->execute($paramsPoste);
        $stats['par_poste'] = $stmt->fetchAll();

        $whereDept = ["statut = 'actif'"];
        $paramsDept = [];
        TenantScope::appendWhere($pdo, 'personnel', $whereDept, $paramsDept);
        $stmt = $pdo->prepare('SELECT departement, COUNT(*) as count FROM personnel WHERE ' . implode(' AND ', $whereDept) . ' GROUP BY departement');
        $stmt->execute($paramsDept);
        $stats['par_departement'] = $stmt->fetchAll();

        return $stats;
    }
    
    public function getHoraires($personnel_id) {
        if (!$this->getById($personnel_id)) {
            return [];
        }
        $pdo = getDB();
        $where = ['personnel_id = ?', 'actif = 1'];
        $params = [$personnel_id];
        TenantScope::appendWhere($pdo, 'horaires_personnel', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM horaires_personnel WHERE ' . implode(' AND ', $where) . ' ORDER BY jour_semaine');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function setHoraires($personnel_id, $horaires) {
        if (!$this->getById($personnel_id)) {
            return false;
        }
        $pdo = getDB();
        
        TenantScope::updateWhere($pdo, 'horaires_personnel', 'actif = 0', ['personnel_id = ?'], [$personnel_id]);

        foreach ($horaires as $horaire) {
            if (!empty($horaire['heure_debut']) && !empty($horaire['heure_fin'])) {
                $columns = ['personnel_id', 'jour_semaine', 'heure_debut', 'heure_fin', 'pause_debut', 'pause_fin'];
                $placeholders = array_fill(0, count($columns), '?');
                $values = [
                    $personnel_id,
                    $horaire['jour_semaine'],
                    $horaire['heure_debut'],
                    $horaire['heure_fin'],
                    $horaire['pause_debut'] ?? null,
                    $horaire['pause_fin'] ?? null,
                ];
                TenantScope::bindInsert($pdo, 'horaires_personnel', $columns, $placeholders, $values);
                $sql = 'INSERT INTO horaires_personnel (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            }
        }
        
        return true;
    }
    
    public function getConges($personnel_id, $statut = '') {
        if (!$this->getById($personnel_id)) {
            return [];
        }
        $pdo = getDB();
        
        $where = ['personnel_id = ?'];
        $params = [$personnel_id];
        if ($statut) {
            $where[] = 'statut = ?';
            $params[] = $statut;
        }
        TenantScope::appendWhere($pdo, 'conges_personnel', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM conges_personnel WHERE ' . implode(' AND ', $where) . ' ORDER BY date_debut DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createConge($data) {
        if (empty($data['personnel_id']) || !$this->getById($data['personnel_id'])) {
            return false;
        }
        $pdo = getDB();
        
        // Calculer le nombre de jours
        $date_debut = new DateTime($data['date_debut']);
        $date_fin = new DateTime($data['date_fin']);
        $nombre_jours = $date_debut->diff($date_fin)->days + 1;
        
        $columns = ['personnel_id', 'type_conge', 'date_debut', 'date_fin', 'nombre_jours', 'statut', 'motif', 'notes'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['personnel_id'],
            $data['type_conge'],
            $data['date_debut'],
            $data['date_fin'],
            $nombre_jours,
            $data['statut'] ?? 'en_attente',
            $data['motif'] ?? null,
            $data['notes'] ?? null,
        ];
        TenantScope::bindInsert($pdo, 'conges_personnel', $columns, $placeholders, $values);
        $sql = 'INSERT INTO conges_personnel (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    private function generateNumeroEmploye() {
        $pdo = getDB();
        $prefix = 'EMP';
        $year = date('Y');
        $where = ["numero_employe LIKE ?"];
        $params = ["$prefix$year%"];
        TenantScope::appendWhere($pdo, 'personnel', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM personnel WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        $count = $result ? (int)$result['count'] : 0;
        
        $numero = $prefix . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        return $numero;
    }
    
    private function calculateAge($date_naissance) {
        if (!$date_naissance) return null;
        
        $birth = new DateTime($date_naissance);
        $today = new DateTime();
        return $today->diff($birth)->y;
    }
}

