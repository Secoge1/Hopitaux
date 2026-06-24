<?php
/**
 * Modèle Patient - Version simplifiée
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/staff_scope.php';

class Patient {

    private string $lastDeleteError = '';

    public function getLastDeleteError(): string
    {
        return $this->lastDeleteError;
    }

    private function scopeTenant(array &$where, array &$params, string $alias = ''): void
    {
        TenantScope::appendWhere(getDB(), 'patients', $where, $params, $alias);
    }

    /**
     * Patient encore listé et compté (hors suppression logique).
     * Important : en SQL, « statut != 'supprime' » exclut les lignes où statut est NULL.
     */
    private const SQL_NON_SUPPRIME = '(statut IS NULL OR statut <> \'supprime\')';

    /**
     * Compteur unique pour accueil / cache / API — même base que getCount() sans filtre.
     */
    public static function countForDashboard(): int {
        try {
            $pdo = getDB();
            $where = [self::SQL_NON_SUPPRIME];
            $params = [];
            TenantScope::appendWhere($pdo, 'patients', $where, $params);
            StaffScope::appendPatientFilter($where, $params, '');
            $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM patients WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            $row = $stmt->fetch();
            return (int) (($row === false) ? 0 : ($row['total'] ?? 0));
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function invalidatePatientCaches(): void {
        try {
            $path = __DIR__ . '/../includes/CacheSystem.php';
            if (is_file($path)) {
                require_once $path;
                CacheSystem::getInstance()->invalidatePatientsCache();
            }
        } catch (Throwable $e) {
            // le TTL du cache finira par rafraîchir
        }
    }

    /**
     * Filtres liste / comptage : hors supprimés par défaut ; filtre "supprime" = uniquement la corbeille.
     * Ordre des paramètres : critères de recherche (5×) puis statut précis (actif, inactif, archive).
     */
    private function buildPatientListingConditions($search, $statut, &$params, string $alias = '') {
        $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $where = [];
        if ($statut === 'supprime') {
            $where[] = $p . "statut = 'supprime'";
        } else {
            $where[] = $alias !== ''
                ? '(' . $p . 'statut IS NULL OR ' . $p . "statut <> 'supprime')"
                : self::SQL_NON_SUPPRIME;
        }
        if ($search) {
            $search_clean = preg_replace('/\s+/', ' ', trim($search));
            $where[] = "(LOWER({$p}nom) LIKE LOWER(?) OR LOWER({$p}prenom) LIKE LOWER(?) OR {$p}numero_dossier LIKE ? OR LOWER(CONCAT({$p}prenom, ' ', {$p}nom)) LIKE LOWER(?) OR LOWER(CONCAT({$p}nom, ' ', {$p}prenom)) LIKE LOWER(?))";
            $search_param = "%$search_clean%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        if ($statut !== '' && $statut !== 'supprime') {
            $where[] = $p . 'statut = ?';
            $params[] = $statut;
        }
        $this->scopeTenant($where, $params, $alias !== '' ? $alias : '');
        StaffScope::appendPatientFilter($where, $params, $alias !== '' ? $alias : '');
        return $where;
    }
    
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $params = [];
        $where = $this->buildPatientListingConditions($search, $statut, $params, 'p');
        
        $where_clause = "WHERE " . implode(" AND ", $where);
        $sql = "SELECT p.*, m.nom AS medecin_referent_nom, m.prenom AS medecin_referent_prenom, m.specialite AS medecin_referent_specialite, m.type_profil AS medecin_referent_type_profil
                FROM patients p
                LEFT JOIN medecins m ON m.id = p.medecin_referent_id
                $where_clause ORDER BY p.date_creation DESC, p.nom, p.prenom LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();
        
        foreach ($patients as &$patient) {
            $this->enrichPatientRow($patient);
        }
        
        return $patients;
    }
    
    public function getCount($search = '', $statut = '') {
        $pdo = getDB();
        
        $params = [];
        $where = $this->buildPatientListingConditions($search, $statut, $params);
        
        $where_clause = "WHERE " . implode(" AND ", $where);
        $sql = "SELECT COUNT(*) as total FROM patients $where_clause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) (($result === false) ? 0 : ($result['total'] ?? 0));
    }
    
    public function getById($id) {
        $pdo = getDB();
        $sql = 'SELECT * FROM patients WHERE id = ? AND ' . self::SQL_NON_SUPPRIME
            . TenantScope::andOwnedByTenant($pdo, 'patients');
        $params = array_merge([$id], TenantScope::ownedParam($pdo, 'patients'));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patient = $stmt->fetch();
        
        if ($patient && !StaffScope::canAccessPatient($patient)) {
            return false;
        }

        // Ajouter l'âge calculé si le patient existe
        if ($patient) {
            $this->enrichPatientRow($patient);
        }
        
        return $patient;
    }

    private function enrichPatientRow(array &$patient): void
    {
        $patient['age'] = $this->calculateAge($patient['date_naissance']);
        if (!empty($patient['medecin_referent_id']) && empty($patient['medecin_referent_nom'])) {
            $pdo = getDB();
            $where = ['id = ?'];
            $params = [(int) $patient['medecin_referent_id']];
            TenantScope::appendWhere($pdo, 'medecins', $where, $params);
            $stmt = $pdo->prepare('SELECT nom, prenom, specialite, type_profil FROM medecins WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            $med = $stmt->fetch();
            if ($med) {
                $patient['medecin_referent_nom'] = $med['nom'];
                $patient['medecin_referent_prenom'] = $med['prenom'];
                $patient['medecin_referent_specialite'] = $med['specialite'];
                $patient['medecin_referent_type_profil'] = $med['type_profil'] ?? 'medecin';
            }
        }
    }

    public function assignMedecinReferent(int $patientId, ?int $medecinId): bool
    {
        if (!StaffScope::canAssignPatientMedecin()) {
            return false;
        }
        $patient = $this->getById($patientId);
        if (!$patient) {
            return false;
        }
        $previousMedecinId = (int) ($patient['medecin_referent_id'] ?? 0);
        $medecinId = StaffScope::resolveMedecinReferentIdForForm($medecinId);
        $pdo = getDB();
        $sql = 'UPDATE patients SET medecin_referent_id = ?, date_modification = NOW() WHERE id = ?'
            . TenantScope::andOwnedByTenant($pdo, 'patients');
        $params = [$medecinId, $patientId];
        $params = array_merge($params, TenantScope::ownedParam($pdo, 'patients'));
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok) {
            $this->invalidatePatientCaches();
            if ($medecinId && (int) $medecinId !== $previousMedecinId) {
                $this->notifyMedecinPatientAssigned($patientId, (int) $medecinId);
            }
        }
        return $ok;
    }

    /**
     * Notifie le compte utilisateur lié au médecin lors d'une assignation patient.
     */
    private function notifyMedecinPatientAssigned(int $patientId, int $medecinId): void
    {
        if ($medecinId < 1) {
            return;
        }
        try {
            $pdo = getDB();
            if (!StaffScope::columnExists($pdo, 'medecins', 'utilisateur_id')) {
                return;
            }
            $where = ['id = ?'];
            $params = [$medecinId];
            TenantScope::appendWhere($pdo, 'medecins', $where, $params);
            $stmt = $pdo->prepare('SELECT utilisateur_id FROM medecins WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId < 1) {
                return;
            }

            $patient = $this->getById($patientId);
            if (!$patient) {
                return;
            }

            $assigner = 'Un utilisateur';
            if (class_exists('Auth')) {
                $auth = Auth::getInstance();
                if ($auth->estConnecte()) {
                    $u = $auth->getUtilisateur();
                    $assigner = trim((string) ($u['nom_utilisateur'] ?? $assigner)) ?: $assigner;
                }
            }
            $patientName = trim(($patient['prenom'] ?? '') . ' ' . ($patient['nom'] ?? ''));

            require_once __DIR__ . '/../includes/NotificationSystem.php';
            NotificationSystem::getInstance()->createNotification(
                $userId,
                'info',
                'Nouveau patient assigné',
                "{$assigner} vous a assigné le patient {$patientName}.",
                'patients',
                'patients/voir.php?id=' . $patientId
            );
        } catch (Throwable $e) {
            // notification optionnelle
        }
    }
    
    public function create($data) {
        $pdo = getDB();
        $maxAttempts = 5;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if (empty($data['numero_dossier'])) {
                $data['numero_dossier'] = $this->generateNumeroDossier();
            }

            $columns = [
                'numero_dossier', 'nom', 'prenom', 'date_naissance', 'sexe', 'groupe_sanguin',
                'telephone', 'email', 'adresse', 'ville', 'code_postal', 'pays', 'profession',
                'statut', 'antecedents_medicaux', 'allergies', 'notes', 'date_creation',
            ];
            $placeholders = array_fill(0, count($columns) - 1, '?');
            $placeholders[] = 'NOW()';
            $values = [
                $data['numero_dossier'],
                $data['nom'],
                $data['prenom'],
                $data['date_naissance'],
                $data['genre'],
                $data['groupe_sanguin'] ?? null,
                $data['telephone'] ?? null,
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['code_postal'] ?? null,
                $data['pays'] ?? 'France',
                $data['profession'] ?? null,
                $data['statut'] ?? 'actif',
                $data['antecedents_medicaux'] ?? null,
                $data['allergies'] ?? null,
                $data['notes'] ?? null,
            ];
            $referentId = array_key_exists('medecin_referent_id', $data)
                ? StaffScope::resolveMedecinReferentIdForForm(
                    $data['medecin_referent_id'] !== null && $data['medecin_referent_id'] !== ''
                        ? (int) $data['medecin_referent_id']
                        : null
                )
                : StaffScope::medecinReferentIdForPatientCreate();
            if ($referentId !== null) {
                $columns[] = 'medecin_referent_id';
                $placeholders[] = '?';
                $values[] = $referentId;
            }

            TenantScope::bindInsert($pdo, 'patients', $columns, $placeholders, $values);
            $sql = 'INSERT INTO patients (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);

            try {
                $result = $stmt->execute($values);
                if ($result) {
                    $newId = (int) $pdo->lastInsertId();
                    $this->invalidatePatientCaches();
                    if ($referentId) {
                        $this->notifyMedecinPatientAssigned($newId, (int) $referentId);
                    }
                    return $newId;
                }
                return false;
            } catch (PDOException $e) {
                $isDuplicateDossier = (int) ($e->errorInfo[1] ?? 0) === 1062
                    && stripos($e->getMessage(), 'numero_dossier') !== false;
                if ($isDuplicateDossier && $attempt < $maxAttempts - 1) {
                    $data['numero_dossier'] = null;
                    continue;
                }
                throw $e;
            }
        }

        return false;
    }
    
    public function update($id, $data) {
        $pdo = getDB();
        $patientBefore = $this->getById($id);
        if (!$patientBefore) {
            return false;
        }
        $previousReferentId = (int) ($patientBefore['medecin_referent_id'] ?? 0);
        $newReferentId = null;
        $sets = [
            'nom = ?', 'prenom = ?', 'date_naissance = ?', 'sexe = ?', 'groupe_sanguin = ?',
            'telephone = ?', 'email = ?', 'adresse = ?', 'ville = ?', 'code_postal = ?', 'pays = ?',
            'profession = ?', 'statut = ?', 'antecedents_medicaux = ?', 'allergies = ?', 'notes = ?',
            'date_modification = NOW()',
        ];
        $params = [
            $data['nom'],
            $data['prenom'],
            $data['date_naissance'],
            $data['genre'],
            $data['groupe_sanguin'] ?? null,
            $data['telephone'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['code_postal'] ?? null,
            $data['pays'] ?? 'France',
            $data['profession'] ?? null,
            $data['statut'] ?? 'actif',
            $data['antecedents_medicaux'] ?? null,
            $data['allergies'] ?? null,
            $data['notes'] ?? null,
        ];
        if (array_key_exists('medecin_referent_id', $data) && StaffScope::canAssignPatientMedecin()) {
            $newReferentId = StaffScope::resolveMedecinReferentIdForForm(
                $data['medecin_referent_id'] !== null && $data['medecin_referent_id'] !== ''
                    ? (int) $data['medecin_referent_id']
                    : null
            );
            $sets[] = 'medecin_referent_id = ?';
            $params[] = $newReferentId;
        }
        $params[] = $id;
        $sql = 'UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'patients');
        $params = array_merge($params, TenantScope::ownedParam($pdo, 'patients'));
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok) {
            $this->invalidatePatientCaches();
            if ($newReferentId && (int) $newReferentId !== $previousReferentId) {
                $this->notifyMedecinPatientAssigned((int) $id, (int) $newReferentId);
            }
        }
        return $ok;
    }

    
    /**
     * Suppression logique d'un patient (soft delete)
     * Marque le patient comme supprimé au lieu de le supprimer physiquement
     */
    public function delete($id) {
        $pdo = getDB();
        
        // Vérifier d'abord si le patient existe
        $patient = $this->getById($id);
        if (!$patient) {
            return false;
        }
        
        try {
            // Désactiver temporairement les vérifications de contraintes pour éviter les problèmes avec PHPMyAdmin
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Marquer le patient comme supprimé (soft delete)
            $sql = "UPDATE patients SET statut = 'supprime', date_suppression = NOW() WHERE id = ?"
                . TenantScope::andOwnedByTenant($pdo, 'patients');
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'patients')));
            
            // Réactiver les vérifications de contraintes
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            if ($result) {
                $this->invalidatePatientCaches();
            }
            return $result;
        } catch (Exception $e) {
            // S'assurer que les vérifications sont réactivées même en cas d'erreur
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            error_log("Erreur lors de la suppression du patient ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suppression physique d'un patient (hard delete)
     * Supprime complètement le patient et toutes ses données liées
     * ATTENTION: Cette méthode est irréversible !
     */
    public function hardDelete($id) {
        $pdo = getDB();
        $this->lastDeleteError = '';

        try {
            require_once __DIR__ . '/Consultation.php';
            $consultationModel = new Consultation();

            $pdo->beginTransaction();

            $consultationModel->deleteAllForPatient((int) $id, $pdo);
            $this->deletePatientAssuranceData($pdo, (int) $id);

            foreach (['sejours_hospitalisation', 'dossiers', 'rendez_vous', 'paiements', 'analyses', 'documents_patients'] as $table) {
                TenantScope::deleteWhere($pdo, $table, ['patient_id = ?'], [$id]);
            }

            $sql = 'DELETE FROM patients WHERE id = ?' . TenantScope::andOwnedByTenant($pdo, 'patients');
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'patients')));

            $pdo->commit();
            if ($result && $stmt->rowCount() > 0) {
                $this->invalidatePatientCaches();
                return true;
            }
            $this->lastDeleteError = 'Aucune ligne supprimée (droits ou données liées).';
            return false;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur lors de la suppression physique du patient ID $id: " . $e->getMessage());
            $this->lastDeleteError = $e->getMessage();
            return false;
        }
    }

    private function deletePatientAssuranceData(PDO $pdo, int $patientId): void
    {
        $where = ['c.patient_id = ?'];
        $params = [$patientId];
        TenantScope::appendWhere($pdo, 'contrats_assurance', $where, $params, 'c');
        $stmt = $pdo->prepare('SELECT c.id FROM contrats_assurance c WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $contratId) {
            TenantScope::deleteWhere($pdo, 'remboursements', ['contrat_id = ?'], [(int) $contratId]);
        }
        $pdo->prepare('DELETE c FROM contrats_assurance c WHERE ' . implode(' AND ', $where))->execute($params);
    }
    
    public function generateNumeroDossier() {
        $pdo = getDB();
        $year = date('Y');
        $prefix = 'P' . $year;

        $where = ['numero_dossier LIKE ?'];
        $params = [$prefix . '%'];
        TenantScope::appendWhere($pdo, 'patients', $where, $params);
        $stmt = $pdo->prepare(
            'SELECT numero_dossier FROM patients
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY LENGTH(numero_dossier) DESC, numero_dossier DESC
             LIMIT 1'
        );
        $stmt->execute($params);
        $last = $stmt->fetchColumn();

        $next = 1;
        if ($last && preg_match('/^P' . preg_quote($year, '/') . '(\d{4})$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        for ($i = 0; $i < 10000; $i++, $next++) {
            $numero = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            if (!$this->numeroDossierExists($numero)) {
                return $numero;
            }
        }

        throw new RuntimeException('Impossible de générer un numéro de dossier unique.');
    }
    
    /**
     * Conditions communes stats / KPI (tenant + périmètre personnel, hors supprimés).
     *
     * @return list<string>
     */
    private function buildStatsScopeConditions(array &$params, bool $includeNonSupprime = true): array
    {
        $where = [];
        if ($includeNonSupprime) {
            $where[] = self::SQL_NON_SUPPRIME;
        }
        $this->scopeTenant($where, $params);
        StaffScope::appendPatientFilter($where, $params, '');
        return $where;
    }

    public function getStats() {
        $pdo = getDB();
        $stats = [];

        $stats['total'] = self::countForDashboard();

        $params = [];
        $where = $this->buildStatsScopeConditions($params);
        $wc = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM patients WHERE $wc GROUP BY statut");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }

        $paramsSup = [];
        $whereSup = ["statut = 'supprime'"];
        $this->scopeTenant($whereSup, $paramsSup);
        StaffScope::appendPatientFilter($whereSup, $paramsSup, '');
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM patients WHERE ' . implode(' AND ', $whereSup));
        $stmt->execute($paramsSup);
        $stats['supprime'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END), 0) AS hommes,
            COALESCE(SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END), 0) AS femmes
            FROM patients WHERE $wc");
        $stmt->execute($params);
        $rowSexe = $stmt->fetch() ?: [];
        $stats['hommes'] = (int) ($rowSexe['hommes'] ?? 0);
        $stats['femmes'] = (int) ($rowSexe['femmes'] ?? 0);

        $paramsN = [];
        $whereN = $this->buildStatsScopeConditions($paramsN);
        $whereN[] = 'MONTH(date_creation) = MONTH(CURDATE())';
        $whereN[] = 'YEAR(date_creation) = YEAR(CURDATE())';
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM patients WHERE ' . implode(' AND ', $whereN));
        $stmt->execute($paramsN);
        $stats['nouveaux_mois'] = (int) $stmt->fetchColumn();

        try {
            $whereC = [];
            $paramsC = [];
            TenantScope::appendWhere($pdo, 'consultations', $whereC, $paramsC);
            StaffScope::appendConsultationFilter($whereC, $paramsC, '');
            $wcC = $whereC !== [] ? ' WHERE ' . implode(' AND ', $whereC) : '';
            $stmt = $pdo->prepare("SELECT AVG(consultation_count) as moyenne FROM (
                SELECT COUNT(*) as consultation_count FROM consultations$wcC GROUP BY patient_id
            ) as counts");
            $stmt->execute($paramsC);
            $result = $stmt->fetch();
            $stats['consultations_moyenne'] = round($result['moyenne'] ?? 0, 1);
        } catch (Exception $e) {
            $stats['consultations_moyenne'] = 0;
        }

        return $stats;
    }

    /**
     * Calculer l'âge d'un patient
     */
    public function calculateAge($date_naissance) {
        if (!$date_naissance || empty($date_naissance)) return null;
        
        try {
            $birth = new DateTime($date_naissance);
            $today = new DateTime();
            
            // Vérifier que la date de naissance n'est pas dans le futur
            if ($birth > $today) {
                return 0; // ou null selon votre préférence
            }
            
            $age = $today->diff($birth);
            return $age->y;
        } catch (Exception $e) {
            // En cas d'erreur de parsing de date, retourner null
            return null;
        }
    }

    /**
     * Vérifier si un numéro de dossier existe déjà
     */
    public function numeroDossierExists($numero_dossier, $exclude_id = null) {
        $pdo = getDB();
        $where = ['numero_dossier = ?'];
        $params = [$numero_dossier];
        if ($exclude_id) {
            $where[] = 'id != ?';
            $params[] = $exclude_id;
        }
        TenantScope::appendWhere($pdo, 'patients', $where, $params);
        $sql = 'SELECT COUNT(*) as count FROM patients WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0) > 0;
    }

    /**
     * Récupérer les patients récents
     */
    public function getRecent($limit = 5) {
        $pdo = getDB();
        $where = [self::SQL_NON_SUPPRIME];
        $params = [];
        TenantScope::appendWhere($pdo, 'patients', $where, $params);
        $sql = 'SELECT * FROM patients WHERE ' . implode(' AND ', $where) . " ORDER BY date_creation DESC LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();
        
        // Ajouter l'âge calculé pour chaque patient
        foreach ($patients as &$patient) {
            $patient['age'] = $this->calculateAge($patient['date_naissance']);
        }
        
        return $patients;
    }

    /**
     * Rechercher des patients
     */
    public function search($query, $limit = 20) {
        $pdo = getDB();
        // Nettoyer le terme de recherche (enlever les espaces multiples)
        $query_clean = preg_replace('/\s+/', ' ', trim($query));
        $where = [
            '(LOWER(nom) LIKE LOWER(?) OR LOWER(prenom) LIKE LOWER(?) OR numero_dossier LIKE ? OR LOWER(CONCAT(prenom, \' \', nom)) LIKE LOWER(?) OR LOWER(CONCAT(nom, \' \', prenom)) LIKE LOWER(?))',
            self::SQL_NON_SUPPRIME,
        ];
        $searchTerm = "%$query_clean%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        TenantScope::appendWhere($pdo, 'patients', $where, $params);
        $sql = 'SELECT * FROM patients WHERE ' . implode(' AND ', $where) . " ORDER BY nom, prenom LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();
        
        // Ajouter l'âge calculé pour chaque patient
        foreach ($patients as &$patient) {
            $patient['age'] = $this->calculateAge($patient['date_naissance']);
        }
        
        return $patients;
    }
    
    /**
     * Récupérer les patients supprimés (pour l'administration)
     */
    public function getDeleted($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        $where = ["statut = 'supprime'"];
        $params = [];
        TenantScope::appendWhere($pdo, 'patients', $where, $params);
        $sql = 'SELECT * FROM patients WHERE ' . implode(' AND ', $where) . " ORDER BY date_suppression DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();
        
        // Ajouter l'âge calculé pour chaque patient
        foreach ($patients as &$patient) {
            $patient['age'] = $this->calculateAge($patient['date_naissance']);
        }
        
        return $patients;
    }
    
    /**
     * Restaurer un patient supprimé
     */
    public function restore($id) {
        $pdo = getDB();
        
        try {
            $sql = "UPDATE patients SET statut = 'actif', date_suppression = NULL WHERE id = ? AND statut = 'supprime'"
                . TenantScope::andOwnedByTenant($pdo, 'patients');
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute(array_merge([$id], TenantScope::ownedParam($pdo, 'patients')));
            if ($ok && $stmt->rowCount() > 0) {
                $this->invalidatePatientCaches();
            }
            return $ok;
        } catch (Exception $e) {
            error_log("Erreur lors de la restauration du patient ID $id: " . $e->getMessage());
            return false;
        }
    }
}
?>
