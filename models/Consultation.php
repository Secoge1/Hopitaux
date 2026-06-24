<?php
/**
 * Modèle Consultation - Gestion des consultations
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/staff_scope.php';

class Consultation {
    private $pdo;

    private function scopeTenant(array &$where, array &$params, string $alias = 'c'): void
    {
        TenantScope::appendWhere($this->pdo, 'consultations', $where, $params, $alias);
    }

    private function scopeStaff(array &$where, array &$params, string $alias = 'c'): void
    {
        StaffScope::appendConsultationFilter($where, $params, $alias);
    }

    private function tenantAnd(): string
    {
        return TenantScope::andOwnedByTenant($this->pdo, 'consultations');
    }
    
    public function __construct() {
        $this->pdo = getDB();
        // Définir le fuseau horaire par défaut - Afrique de l'Ouest
        date_default_timezone_set('Africa/Dakar');
    }
    
    /**
     * Récupérer toutes les consultations avec pagination
     */
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '', $date = '') {
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            // Nettoyer le terme de recherche (enlever les espaces multiples)
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(p.nom) LIKE LOWER(?) OR LOWER(p.prenom) LIKE LOWER(?) OR LOWER(CONCAT(p.prenom, ' ', p.nom)) LIKE LOWER(?) OR LOWER(m.nom) LIKE LOWER(?) OR LOWER(m.prenom) LIKE LOWER(?) OR LOWER(CONCAT(m.prenom, ' ', m.nom)) LIKE LOWER(?) OR p.numero_dossier LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($statut)) {
            $where[] = "c.statut = ?";
            $params[] = $statut;
        }
        
        if (!empty($date)) {
            $where[] = "DATE(c.date_consultation) = ?";
            $params[] = $date;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Utiliser LIMIT avec offset intégré pour éviter les problèmes de paramètres
        $sql = "SELECT c.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                $whereClause 
                ORDER BY c.date_consultation DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Compter le nombre total de consultations
     */
    public function getCount($search = '', $statut = '', $date = '') {
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            // Nettoyer le terme de recherche (enlever les espaces multiples)
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER(p.nom) LIKE LOWER(?) OR LOWER(p.prenom) LIKE LOWER(?) OR LOWER(CONCAT(p.prenom, ' ', p.nom)) LIKE LOWER(?) OR LOWER(m.nom) LIKE LOWER(?) OR LOWER(m.prenom) LIKE LOWER(?) OR LOWER(CONCAT(m.prenom, ' ', m.nom)) LIKE LOWER(?) OR p.numero_dossier LIKE ?)";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($statut)) {
            $where[] = "c.statut = ?";
            $params[] = $statut;
        }
        
        if (!empty($date)) {
            $where[] = "DATE(c.date_consultation) = ?";
            $params[] = $date;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total 
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Récupérer une consultation par ID
     */
    public function getById($id) {
        $sql = "SELECT c.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE c.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'consultations', 'c');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'consultations')));
        $row = $stmt->fetch();
        if ($row && !StaffScope::canAccessConsultation($row)) {
            return false;
        }
        return $row;
    }

    /**
     * Vérifie qu'une consultation appartient au patient (et au tenant courant).
     */
    public function getByIdForPatient(int $consultationId, int $patientId): ?array
    {
        $row = $this->getById($consultationId);
        if (!$row || (int) ($row['patient_id'] ?? 0) !== $patientId) {
            return null;
        }
        return $row;
    }

    /**
     * Consultation pour impression ticket depuis le module patients
     * (accès via fiche patient, sans filtre consultation médecin/soins).
     */
    public function getByIdForPatientModule(int $consultationId): ?array
    {
        $sql = "SELECT c.*,
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE c.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'consultations', 'c');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$consultationId], TenantScope::ownedParam($this->pdo, 'consultations')));
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        require_once __DIR__ . '/Patient.php';
        $patientModel = new Patient();
        if (!$patientModel->getById((int) ($row['patient_id'] ?? 0))) {
            return null;
        }

        return $row;
    }

    /**
     * Créer une nouvelle consultation
     */
    public function create($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Suppression de la contrainte de date future pour autoriser les dates passées
            // Générer un numéro de ticket unique
            $numero_ticket = $this->generateTicketNumber();
            
            $columns = [
                'patient_id', 'medecin_id', 'date_consultation', 'symptomes', 'diagnostic', 'traitement',
                'ordonnance', 'statut', 'prix_consultation', 'type_consultation', 'hospitalisation_requise',
                'numero_ticket', 'date_creation',
            ];
            $placeholders = array_fill(0, count($columns) - 1, '?');
            $placeholders[] = 'NOW()';
            $values = [
                $data['patient_id'],
                $data['medecin_id'],
                $data['date_consultation'],
                $data['symptomes'] ?? null,
                $data['diagnostic'] ?? null,
                $data['traitement'] ?? null,
                $data['ordonnance'] ?? null,
                $data['statut'] ?? 'planifie',
                $data['prix_consultation'] ?? 0.00,
                $data['type_consultation'] ?? 'consultation_simple',
                $data['hospitalisation_requise'] ?? false,
                $numero_ticket,
            ];
            TenantScope::bindInsert($this->pdo, 'consultations', $columns, $placeholders, $values);
            $sql = 'INSERT INTO consultations (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                throw new Exception("Erreur lors de la création de la consultation");
            }
            
            $consultation_id = $this->pdo->lastInsertId();
            
            // Enregistrer les soins si fournis
            if (isset($data['soins_data']) && !empty($data['soins_data'])) {
                $this->saveConsultationSoins($consultation_id, $data['soins_data']);
            }
            
            // Enregistrer l'hospitalisation si fournie
            if (isset($data['hospitalisation_data']) && !empty($data['hospitalisation_data'])) {
                $this->saveConsultationHospitalisation($consultation_id, $data['hospitalisation_data']);
            }
            
            $this->pdo->commit();
            return $consultation_id;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mettre à jour une consultation
     */
    public function update($id, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Suppression de la contrainte de date future lors de la modification
            $fields = [];
            $values = [];
            
            // Construire dynamiquement la requête SQL selon les champs fournis
            if (isset($data['patient_id'])) {
                $fields[] = "patient_id = ?";
                $values[] = $data['patient_id'];
            }
            if (isset($data['medecin_id'])) {
                $fields[] = "medecin_id = ?";
                $values[] = $data['medecin_id'];
            }
            if (isset($data['date_consultation'])) {
                $fields[] = "date_consultation = ?";
                $values[] = $data['date_consultation'];
            }
            if (isset($data['symptomes'])) {
                $fields[] = "symptomes = ?";
                $values[] = $data['symptomes'];
            }
            if (isset($data['diagnostic'])) {
                $fields[] = "diagnostic = ?";
                $values[] = $data['diagnostic'];
            }
            if (isset($data['traitement'])) {
                $fields[] = "traitement = ?";
                $values[] = $data['traitement'];
            }
            if (isset($data['ordonnance'])) {
                $fields[] = "ordonnance = ?";
                $values[] = $data['ordonnance'];
            }
            if (isset($data['statut'])) {
                $fields[] = "statut = ?";
                $values[] = $data['statut'];
            }
            if (isset($data['prix_consultation'])) {
                $fields[] = "prix_consultation = ?";
                $values[] = $data['prix_consultation'];
            }
            if (isset($data['type_consultation'])) {
                $fields[] = "type_consultation = ?";
                $values[] = $data['type_consultation'];
            }
            if (isset($data['hospitalisation_requise'])) {
                $fields[] = "hospitalisation_requise = ?";
                $values[] = $data['hospitalisation_requise'];
            }
            
            // Vérifier si la colonne date_modification existe avant de l'utiliser
            try {
                $checkColumn = $this->pdo->query("SHOW COLUMNS FROM consultations LIKE 'date_modification'");
                if ($checkColumn->rowCount() > 0) {
                    $fields[] = "date_modification = NOW()";
                }
            } catch (Exception $e) {
                // Si la colonne n'existe pas, on l'ignore
            }
            
            // Ajouter l'ID à la fin pour la clause WHERE
            $values[] = $id;
            
            if (!empty($fields)) {
                $sql = 'UPDATE consultations SET ' . implode(', ', $fields) . ' WHERE id = ?' . $this->tenantAnd();
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute(TenantScope::appendOwned($this->pdo, 'consultations', $values));
                
                if (!$result) {
                    throw new Exception("Erreur lors de la mise à jour de la consultation");
                }
            }
            
            // Mettre à jour les soins si fournis
            if (isset($data['soins_data'])) {
                $this->saveConsultationSoins($id, $data['soins_data']);
            }
            
            // Mettre à jour l'hospitalisation si fournie
            if (isset($data['hospitalisation_data'])) {
                $this->saveConsultationHospitalisation($id, $data['hospitalisation_data']);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Supprime les enregistrements dépendants d'une consultation (sans transaction).
     */
    public function deleteDependencies(int $id, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? $this->pdo;
        foreach (['consultation_soins', 'consultation_hospitalisation', 'tickets_consultation', 'sejours_hospitalisation'] as $table) {
            $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE consultation_id = ?");
            $stmt->execute([$id]);
        }
    }

    /**
     * Supprime toutes les consultations d'un médecin (dépendances incluses).
     */
    public function deleteAllForMedecin(int $medecinId, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? $this->pdo;
        $where = ['c.medecin_id = ?'];
        $params = [$medecinId];
        $this->scopeTenant($where, $params);
        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT c.id FROM consultations c WHERE $whereSql");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $consultationId) {
            $this->deleteDependencies((int) $consultationId, $pdo);
        }
        $stmt = $pdo->prepare("DELETE c FROM consultations c WHERE $whereSql");
        $stmt->execute($params);
    }

    /**
     * Supprime toutes les consultations d'un patient (dépendances incluses).
     */
    public function deleteAllForPatient(int $patientId, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? $this->pdo;
        $where = ['c.patient_id = ?'];
        $params = [$patientId];
        $this->scopeTenant($where, $params);
        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT c.id FROM consultations c WHERE $whereSql");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $consultationId) {
            $this->deleteDependencies((int) $consultationId, $pdo);
        }
        $stmt = $pdo->prepare("DELETE c FROM consultations c WHERE $whereSql");
        $stmt->execute($params);
    }

    private function invalidateConsultationCaches(): void
    {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) {
            // Ignorer les erreurs de cache
        }
    }

    /**
     * Supprimer une consultation
     */
    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            $this->deleteDependencies((int) $id, $this->pdo);
            $stmt = $this->pdo->prepare('DELETE FROM consultations WHERE id = ?' . $this->tenantAnd());
            $result = $stmt->execute(TenantScope::paramsForId($this->pdo, 'consultations', (int) $id));
            $this->pdo->commit();
            if ($result) {
                $this->invalidateConsultationCaches();
            }
            return $result;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Récupérer les consultations du jour
     */
    public function getToday() {
        $where = ['DATE(c.date_consultation) = CURDATE()'];
        $params = [];
        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        $sql = "SELECT c.*,
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.date_consultation ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer les consultations de la semaine
     */
    public function getWeek() {
        $where = ['c.date_consultation BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'];
        $params = [];
        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        $sql = "SELECT c.*,
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.date_consultation ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer l'historique des consultations d'un patient
     */
    public function getPatientHistory($patient_id, $limit = 10, $page = 1, $per_page = null) {
        $where = ['c.patient_id = ?'];
        $params = [$patient_id];
        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        if ($per_page !== null) {
            $offset = ($page - 1) * $per_page;
            $sql = "SELECT c.*,
                           m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil,
                           p.nom as patient_nom, p.prenom as patient_prenom
                    FROM consultations c
                    LEFT JOIN medecins m ON c.medecin_id = m.id
                    LEFT JOIN patients p ON c.patient_id = p.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY c.date_consultation DESC
                    LIMIT $per_page OFFSET $offset";
        } else {
            $sql = "SELECT c.*,
                           m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil,
                           p.nom as patient_nom, p.prenom as patient_prenom
                    FROM consultations c
                    LEFT JOIN medecins m ON c.medecin_id = m.id
                    LEFT JOIN patients p ON c.patient_id = p.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY c.date_consultation DESC
                    LIMIT $limit";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Compter le nombre de consultations d'un patient
     */
    public function getPatientConsultationCount($patient_id) {
        $where = ['c.patient_id = ?'];
        $params = [$patient_id];
        $this->scopeTenant($where, $params);
        $sql = 'SELECT COUNT(*) as total FROM consultations c WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
    }

    /**
     * Récupérer les statistiques des consultations
     */
    public function getStats() {
        $pdo = $this->pdo;
        $stats = [];

        $where = [];
        $params = [];
        TenantScope::appendWhere($pdo, 'consultations', $where, $params);
        StaffScope::appendConsultationFilter($where, $params, '');
        $wc = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE $wc");
        $stmt->execute($params);
        $stats['total'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM consultations WHERE $wc GROUP BY statut");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE $wc AND DATE(date_consultation) = CURDATE()");
        $stmt->execute($params);
        $stats['aujourd_hui'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE $wc AND date_consultation BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $stmt->execute($params);
        $stats['cette_semaine'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Commencer une consultation
     */
    public function commencer($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE consultations SET statut = 'en_cours', date_modification = NOW() WHERE id = ?" . $this->tenantAnd()
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'consultations', (int) $id));
    }

    /**
     * Terminer une consultation
     */
    public function terminer($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE consultations SET statut = 'terminee', date_modification = NOW() WHERE id = ?" . $this->tenantAnd()
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'consultations', (int) $id));
    }

    /**
     * Annuler une consultation
     */
    public function annuler($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE consultations SET statut = 'annulee', date_modification = NOW() WHERE id = ?" . $this->tenantAnd()
        );
        return $stmt->execute(TenantScope::paramsForId($this->pdo, 'consultations', (int) $id));
    }
    
    /**
     * Formater une date avec le fuseau horaire
     */
    public function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        if (!$datetime) return '';
        
        $date = new DateTime($datetime);
        return $date->format($format);
    }
    
    /**
     * Vérifier si une consultation est dans le futur
     */
    public function isFuture($datetime) {
        if (!$datetime) return false;
        
        $consultation_time = new DateTime($datetime);
        $current_time = new DateTime();
        
        return $consultation_time > $current_time;
    }
    
    /**
     * Obtenir le fuseau horaire actuel
     */
    public function getCurrentTimezone() {
        return date_default_timezone_get();
    }
    
    /**
     * Obtenir l'heure actuelle formatée
     */
    public function getCurrentTime($format = 'H:i') {
        return date($format);
    }
    
    /**
     * Générer un numéro de ticket unique
     */
    private function generateTicketNumber() {
        $prefix = 'CONS';
        $date = date('Ymd');
        
        // Utiliser un timestamp avec microsecondes pour garantir l'unicité
        $microtime = microtime(true);
        $timestamp_part = str_pad(intval($microtime * 1000000) % 10000, 4, '0', STR_PAD_LEFT);
        $ticket_number = $prefix . $date . $timestamp_part;
        
        // Vérifier l'unicité et ajuster si nécessaire
        $max_attempts = 10;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $where = ['numero_ticket = ?'];
            $params = [$ticket_number];
            TenantScope::appendWhere($this->pdo, 'consultations', $where, $params);
            $check_sql = 'SELECT COUNT(*) FROM consultations WHERE ' . implode(' AND ', $where);
            $check_stmt = $this->pdo->prepare($check_sql);
            $check_stmt->execute($params);
            
            if ($check_stmt->fetchColumn() == 0) {
                return $ticket_number;
            }
            
            // Si le numéro existe, essayer avec un nouveau timestamp
            usleep(1000); // Attendre 1ms pour avoir un nouveau timestamp
            $microtime = microtime(true);
            $timestamp_part = str_pad(intval($microtime * 1000000) % 10000, 4, '0', STR_PAD_LEFT);
            $ticket_number = $prefix . $date . $timestamp_part;
            $attempt++;
        }
        
        // En dernier recours, utiliser un UUID court
        $uuid_part = str_pad(hexdec(substr(uniqid(), -4)), 4, '0', STR_PAD_LEFT);
        return $prefix . $date . $uuid_part;
    }
    
    /**
     * Récupérer les séjours d'hospitalisation d'une consultation
     */
    public function getSejoursHospitalisation($consultation_id) {
        require_once __DIR__ . '/SejourHospitalisation.php';
        $sejourModel = new SejourHospitalisation();
        return $sejourModel->getByConsultation($consultation_id);
    }
    
    /**
     * Calculer le prix total d'une consultation (prix + hospitalisation)
     */
    public function getPrixTotal($consultation_id) {
        $consultation = $this->getById($consultation_id);
        $prix_consultation = $consultation['prix_consultation'] ?? 0;
        
        $sejours = $this->getSejoursHospitalisation($consultation_id);
        $prix_hospitalisation = 0;
        
        foreach ($sejours as $sejour) {
            $prix_hospitalisation += $sejour['prix_total'] ?? 0;
        }
        
        return $prix_consultation + $prix_hospitalisation;
    }
    
    /**
     * Générer le contenu HTML du ticket de consultation
     */
    public function generateTicketHTML($consultation_id) {
        require_once __DIR__ . '/../includes/consultation_ticket_render.php';
        $data = consultation_ticket_load_data($this, (int) $consultation_id);
        if (!$data) {
            return false;
        }
        return consultation_ticket_render_page($data, false, false);
    }
    
    /**
     * Sauvegarder les soins d'une consultation
     */
    public function saveConsultationSoins($consultation_id, $soins_data) {
        TenantScope::deleteWhere($this->pdo, 'consultation_soins', ['consultation_id = ?'], [$consultation_id]);
        
        // Si pas de soins, on s'arrête ici
        if (empty($soins_data)) {
            return true;
        }
        
        // Décoder les données JSON si nécessaire
        if (is_string($soins_data)) {
            $soins = json_decode($soins_data, true);
        } else {
            $soins = $soins_data;
        }
        
        if (!is_array($soins)) {
            return false;
        }
        
        foreach ($soins as $soin) {
            $columns = ['consultation_id', 'soin_id', 'quantite', 'prix_unitaire', 'prix_total'];
            $placeholders = ['?', '?', '?', '?', '?'];
            $values = [
                $consultation_id,
                $soin['id'],
                $soin['quantite'],
                $soin['prix'],
                $soin['total'],
            ];
            TenantScope::bindInsert($this->pdo, 'consultation_soins', $columns, $placeholders, $values);
            $dynSql = 'INSERT INTO consultation_soins (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $dynStmt = $this->pdo->prepare($dynSql);
            $dynStmt->execute($values);
        }
        
        return true;
    }
    
    /**
     * Sauvegarder l'hospitalisation d'une consultation
     */
    public function saveConsultationHospitalisation($consultation_id, $hospitalisation_data) {
        TenantScope::deleteWhere($this->pdo, 'consultation_hospitalisation', ['consultation_id = ?'], [$consultation_id]);
        
        // Si pas d'hospitalisation, on s'arrête ici
        if (empty($hospitalisation_data)) {
            return true;
        }
        
        // Décoder les données JSON si nécessaire
        if (is_string($hospitalisation_data)) {
            $hospitalisation = json_decode($hospitalisation_data, true);
        } else {
            $hospitalisation = $hospitalisation_data;
        }
        
        if (!is_array($hospitalisation)) {
            return false;
        }
        
        $columns = [
            'consultation_id', 'categorie_hospitalisation_id', 'duree_jours', 'prix_jour', 'prix_total',
            'date_admission', 'date_sortie', 'notes', 'statut',
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $consultation_id,
            $hospitalisation['categorie_id'],
            $hospitalisation['duree'],
            $hospitalisation['prix_jour'],
            $hospitalisation['prix_total'],
            $hospitalisation['date_admission'] ?? date('Y-m-d H:i:s'),
            $hospitalisation['date_sortie'] ?? null,
            $hospitalisation['notes'] ?? null,
            $hospitalisation['statut'] ?? 'en_cours',
        ];
        TenantScope::bindInsert($this->pdo, 'consultation_hospitalisation', $columns, $placeholders, $values);
        $sql = 'INSERT INTO consultation_hospitalisation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($values);
    }
    
    /**
     * Récupérer les soins d'une consultation
     */
    public function getConsultationSoins($consultation_id) {
        $where = ['cs.consultation_id = ?'];
        $params = [$consultation_id];
        TenantScope::appendWhere($this->pdo, 'consultation_soins', $where, $params, 'cs');
        $sql = "SELECT cs.*, sc.nom, sc.type_soin, sc.description
                FROM consultation_soins cs
                LEFT JOIN soins_consultation sc ON cs.soin_id = sc.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY cs.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer l'hospitalisation d'une consultation
     */
    public function getConsultationHospitalisation($consultation_id) {
        $where = ['ch.consultation_id = ?'];
        $params = [$consultation_id];
        TenantScope::appendWhere($this->pdo, 'consultation_hospitalisation', $where, $params, 'ch');
        $sql = "SELECT ch.*, cat.nom as categorie_nom, cat.prix_jour as categorie_prix
                FROM consultation_hospitalisation ch
                JOIN categories_hospitalisation cat ON ch.categorie_hospitalisation_id = cat.id
                WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculer le total des soins d'une consultation
     */
    public function getTotalSoins($consultation_id) {
        $where = ['consultation_id = ?'];
        $params = [$consultation_id];
        $row = TenantScope::aggregate($this->pdo, 'consultation_soins', 'SUM(prix_total) as total', $where, $params);
        return $row['total'] ?? 0;
    }
    
    /**
     * Calculer le total de l'hospitalisation d'une consultation
     */
    public function getTotalHospitalisation($consultation_id) {
        $where = ['consultation_id = ?'];
        $params = [$consultation_id];
        $row = TenantScope::aggregate($this->pdo, 'consultation_hospitalisation', 'SUM(prix_total) as total', $where, $params);
        return $row['total'] ?? 0;
    }

    /**
     * Ajouter un soin à une consultation (API unifiée multi-tenant).
     */
    public function addConsultationSoinItem(int $consultation_id, int $soin_id, int $quantite, float $prix_unitaire, ?string $notes = null): bool
    {
        if (!$this->getById($consultation_id)) {
            return false;
        }
        $prix_total = $prix_unitaire * $quantite;
        $columns = ['consultation_id', 'soin_id', 'quantite', 'prix_unitaire', 'prix_total', 'notes'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [$consultation_id, $soin_id, $quantite, $prix_unitaire, $prix_total, $notes];
        TenantScope::bindInsert($this->pdo, 'consultation_soins', $columns, $placeholders, $values);
        $sql = 'INSERT INTO consultation_soins (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function removeConsultationSoinItem(int $soin_consultation_id, int $consultation_id): bool
    {
        if (!$this->getById($consultation_id)) {
            return false;
        }
        return TenantScope::deleteWhere(
            $this->pdo,
            'consultation_soins',
            ['id = ?', 'consultation_id = ?'],
            [$soin_consultation_id, $consultation_id]
        ) > 0;
    }

    public function updateConsultationSoinItem(int $soin_consultation_id, int $consultation_id, int $soin_id, int $quantite, float $prix_unitaire, ?string $notes = null): bool
    {
        if (!$this->getById($consultation_id)) {
            return false;
        }
        $prix_total = $prix_unitaire * $quantite;
        return TenantScope::updateWhere(
            $this->pdo,
            'consultation_soins',
            'soin_id = ?, quantite = ?, prix_unitaire = ?, prix_total = ?, notes = ?',
            ['id = ?', 'consultation_id = ?'],
            [$soin_id, $quantite, $prix_unitaire, $prix_total, $notes, $soin_consultation_id, $consultation_id]
        );
    }
    
    /**
     * Calculer le prix total d'une consultation (prix + soins + hospitalisation)
     */
    public function getPrixTotalComplet($consultation_id) {
        $consultation = $this->getById($consultation_id);
        $prix_consultation = $consultation['prix_consultation'] ?? 0;
        $prix_soins = $this->getTotalSoins($consultation_id);
        $prix_hospitalisation = $this->getTotalHospitalisation($consultation_id);
        
        return $prix_consultation + $prix_soins + $prix_hospitalisation;
    }
    
    /**
     * Sauvegarder le ticket de consultation
     */
    public function saveTicket($consultation_id) {
        $html = $this->generateTicketHTML($consultation_id);
        if (!$html) {
            return false;
        }
        
        $consultation = $this->getById($consultation_id);
        if (!$consultation) {
            return false;
        }
        
        // S'assurer que la consultation a un numero_ticket
        if (empty($consultation['numero_ticket'])) {
            $numero_ticket = $this->generateTicketNumber();
            
            // Mettre à jour la consultation avec le numero_ticket
            $update_sql = 'UPDATE consultations SET numero_ticket = ? WHERE id = ?'
                . TenantScope::andOwnedByTenant($this->pdo, 'consultations');
            $update_stmt = $this->pdo->prepare($update_sql);
            if (!$update_stmt->execute(TenantScope::appendOwned($this->pdo, 'consultations', [$numero_ticket, $consultation_id]))) {
                return false;
            }
            
            // Recharger les données de la consultation
            $consultation = $this->getById($consultation_id);
        }
        
        $where = ['consultation_id = ?'];
        $params = [$consultation_id];
        TenantScope::appendWhere($this->pdo, 'tickets_consultation', $where, $params);
        $check_sql = 'SELECT id FROM tickets_consultation WHERE ' . implode(' AND ', $where);
        $check_stmt = $this->pdo->prepare($check_sql);
        $check_stmt->execute($params);

        if ($check_stmt->fetch()) {
            return TenantScope::updateWhere(
                $this->pdo,
                'tickets_consultation',
                "contenu_html = ?, statut = 'genere', date_generation = CURRENT_TIMESTAMP",
                ['consultation_id = ?'],
                [$html, $consultation_id]
            );
        } else {
            // Créer un nouveau ticket
            $columns = ['consultation_id', 'numero_ticket', 'contenu_html', 'statut'];
            $placeholders = ['?', '?', '?', '?'];
            $values = [$consultation_id, $consultation['numero_ticket'], $html, 'genere'];
            TenantScope::bindInsert($this->pdo, 'tickets_consultation', $columns, $placeholders, $values);
            $sql = 'INSERT INTO tickets_consultation (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        }
    }
}
?>
