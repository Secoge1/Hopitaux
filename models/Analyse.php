<?php
/**
 * Modèle pour la gestion des analyses de laboratoire
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/medecin_profil.php';

class Analyse {
    private $pdo;

    private function scopeTenant(array &$where, array &$params, string $alias = 'a'): void
    {
        TenantScope::appendWhere($this->pdo, 'analyses', $where, $params, $alias);
    }

    private function scopeStaff(array &$where, array &$params, string $alias = 'a'): void
    {
        StaffScope::appendAnalyseFilter($where, $params, $alias);
    }

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Récupérer toutes les analyses avec pagination
     */
    public function getAll($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR a.type_analyse LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['statut'])) {
            $where[] = "a.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['type_analyse'])) {
            $where[] = "a.type_analyse = ?";
            $params[] = $filters['type_analyse'];
        }
        
        if (!empty($filters['patient_id'])) {
            $where[] = "a.patient_id = ?";
            $params[] = $filters['patient_id'];
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT a.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN medecins m ON a.medecin_id = m.id
                $whereClause
                ORDER BY a.date_creation DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche rapide pour l'autocomplétion (module laboratoire).
     */
    public function searchAutocomplete(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $where = ['(p.nom LIKE ? OR p.prenom LIKE ? OR a.type_analyse LIKE ? OR m.nom LIKE ? OR m.prenom LIKE ? OR a.numero_ticket LIKE ?)'];
        $term = '%' . $query . '%';
        $params = [$term, $term, $term, $term, $term, $term];
        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        $limit = max(1, min(25, $limit));
        $sql = 'SELECT a.id, a.type_analyse, a.statut, a.numero_ticket, a.date_creation,
                       p.nom AS patient_nom, p.prenom AS patient_prenom, p.numero_dossier,
                       m.nom AS medecin_nom, m.prenom AS medecin_prenom, m.type_profil AS medecin_type_profil
                FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN medecins m ON a.medecin_id = m.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.date_creation DESC
                LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id) {
        $sql = "SELECT a.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                       p.sexe, p.date_naissance, p.telephone, p.email, p.adresse,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil,
                       t.nom as technicien_nom, t.prenom as technicien_prenom, t.poste as technicien_poste
                FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN medecins m ON a.medecin_id = m.id
                LEFT JOIN personnel t ON a.technicien_id = t.id
                WHERE a.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'analyses', 'a');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'analyses')));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !StaffScope::canAccessAnalyse($row)) {
            return false;
        }
        return $row;
    }

    /**
     * Créer une nouvelle analyse
     */
    public function create($data) {
        // Générer un numéro de ticket unique
        $numero_ticket = $this->generateTicketNumber();
        
        $columns = [
            'patient_id', 'medecin_id', 'type_analyse', 'priorite', 'description',
            'instructions', 'prix_analyse', 'numero_ticket', 'statut', 'date_creation', 'date_modification',
        ];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', 'NOW()', 'NOW()'];
        $values = [
            $data['patient_id'],
            $data['medecin_id'],
            $data['type_analyse'],
            $data['priorite'] ?? 'normale',
            $data['description'] ?? null,
            $data['instructions'] ?? null,
            (isset($data['prix_analyse']) && $data['prix_analyse'] !== '' && $data['prix_analyse'] !== null)
                ? (float) $data['prix_analyse']
                : $this->getDefaultPrice($data['type_analyse']),
            $numero_ticket,
            $data['statut'] ?? 'en_attente',
        ];
        if (!empty($data['technicien_id'])) {
            $columns[] = 'technicien_id';
            $placeholders[] = '?';
            $values[] = (int) $data['technicien_id'];
        }
        TenantScope::bindInsert($this->pdo, 'analyses', $columns, $placeholders, $values);
        $sql = 'INSERT INTO analyses (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        return $result ? $this->pdo->lastInsertId() : false;
    }

    /**
     * Mettre à jour une analyse
     */
    public function update($id, $data) {
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
        if (isset($data['type_analyse'])) {
            $fields[] = "type_analyse = ?";
            $values[] = $data['type_analyse'];
        }
        if (isset($data['priorite'])) {
            $fields[] = "priorite = ?";
            $values[] = $data['priorite'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $values[] = $data['description'];
        }
        if (isset($data['instructions'])) {
            $fields[] = "instructions = ?";
            $values[] = $data['instructions'];
        }
        if (isset($data['statut'])) {
            $fields[] = "statut = ?";
            $values[] = $data['statut'];
        }
        if (isset($data['resultats'])) {
            $fields[] = "resultats = ?";
            $values[] = $data['resultats'];
        }
        if (isset($data['date_analyse'])) {
            $fields[] = "date_analyse = ?";
            $values[] = $data['date_analyse'];
        }
        if (isset($data['date_resultats'])) {
            $fields[] = "date_resultats = ?";
            $values[] = $data['date_resultats'];
        }
        if (isset($data['prix_analyse'])) {
            $fields[] = "prix_analyse = ?";
            $values[] = $data['prix_analyse'];
        }
        if (isset($data['numero_ticket'])) {
            $fields[] = "numero_ticket = ?";
            $values[] = $data['numero_ticket'];
        }
        if (array_key_exists('technicien_id', $data)) {
            $fields[] = 'technicien_id = ?';
            $values[] = $data['technicien_id'];
        }
        
        // Toujours mettre à jour la date de modification
        $fields[] = "date_modification = NOW()";
        
        // Ajouter l'ID à la fin pour la clause WHERE
        $values[] = $id;
        
        if (empty($fields)) {
            return false; // Aucun champ à mettre à jour
        }
        
        $sql = 'UPDATE analyses SET ' . implode(', ', $fields) . ' WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'analyses');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'analyses', $values));
    }

    /**
     * Supprimer une analyse et invalider le cache
     */
    public function delete($id) {
        try {
            $analyse = $this->getById((int) $id);
            if (!$analyse) {
                return false;
            }

            if (!empty($analyse['fichier_image'])) {
                $imagePath = __DIR__ . '/../uploads/imagerie/' . basename((string) $analyse['fichier_image']);
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }

            $sql = 'DELETE FROM analyses WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'analyses');
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(TenantScope::paramsForId($this->pdo, 'analyses', (int) $id));

            if ($stmt->rowCount() < 1) {
                return false;
            }

            try {
                require_once __DIR__ . '/../includes/CacheSystem.php';
                CacheSystem::getInstance()->invalidateDashboardCache();
            } catch (Exception $e) {
                // Ignorer les erreurs de cache, la suppression a réussi
            }

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression de l'analyse ID $id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les statistiques des analyses
     */
    public function getStats() {
        $pdo = $this->pdo;
        $where = [];
        $params = [];
        TenantScope::appendWhere($pdo, 'analyses', $where, $params);
        StaffScope::appendAnalyseFilter($where, $params, '');
        $wc = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM analyses$wc GROUP BY statut");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'en_attente' => 0,
            'en_cours' => 0,
            'termine' => 0,
            'annule' => 0,
            'total' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['statut']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Récupérer les analyses d'un patient
     */
    public function getPatientAnalyses($patient_id, $limit = 10) {
        $where = ['a.patient_id = ?'];
        $params = [(int) $patient_id];
        $this->scopeTenant($where, $params);
        $limit = (int) $limit;

        $sql = "SELECT a.*, 
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM analyses a
                LEFT JOIN medecins m ON a.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.date_creation DESC 
                LIMIT $limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les analyses d'un médecin
     */
    public function getMedecinAnalyses($medecin_id, $limit = 10) {
        $where = ['a.medecin_id = ?'];
        $params = [(int) $medecin_id];
        $this->scopeTenant($where, $params);
        $limit = (int) $limit;

        $sql = "SELECT a.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier
                FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.date_creation DESC 
                LIMIT $limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter le total des analyses avec filtres
     */
    public function count($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR a.type_analyse LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['statut'])) {
            $where[] = "a.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['type_analyse'])) {
            $where[] = "a.type_analyse = ?";
            $params[] = $filters['type_analyse'];
        }
        
        if (!empty($filters['patient_id'])) {
            $where[] = "a.patient_id = ?";
            $params[] = $filters['patient_id'];
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT COUNT(*) FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Récupérer les types d'analyses disponibles
     */
    public function getTypesAnalyses() {
        require_once __DIR__ . '/TarifAnalyseLaboratoire.php';
        return TarifAnalyseLaboratoire::getTypesMapForTenant();
    }

    /** @return array<string, float> code => prix (types actifs) */
    public function getPrixParType(): array
    {
        require_once __DIR__ . '/TarifAnalyseLaboratoire.php';
        return TarifAnalyseLaboratoire::getPrixMapForTenant();
    }
    
    /**
     * Récupérer les types d'examens d'imagerie disponibles
     */
    public function getTypesExamensImagerie() {
        return [
            'laboratoire' => 'Laboratoire',
            'radiologie' => 'Radiologie (RX)',
            'echographie' => 'Échographie',
            'scanner' => 'Scanner (CT)',
            'irm' => 'IRM',
            'mammographie' => 'Mammographie',
            'autre' => 'Autre'
        ];
    }
    
    /**
     * Vérifier si une analyse nécessite un fichier image
     */
    public function requiresImageFile($type_examen) {
        $imageTypes = ['radiologie', 'echographie', 'scanner', 'irm', 'mammographie'];
        return in_array($type_examen, $imageTypes);
    }
    
    /**
     * Mettre à jour le fichier image d'une analyse
     */
    public function updateImageFile($id, $fichier_image) {
        $sql = 'UPDATE analyses SET fichier_image = ? WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'analyses');
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(TenantScope::appendOwned($this->pdo, 'analyses', [$fichier_image, (int) $id]));
    }
    
    /**
     * Récupérer les analyses d'imagerie
     */
    public function getImagerie($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $where = ["a.type_examen IN ('radiologie', 'echographie', 'scanner', 'irm', 'mammographie')"];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(p.nom LIKE ? OR p.prenom LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['type_examen'])) {
            $where[] = "a.type_examen = ?";
            $params[] = $filters['type_examen'];
        }
        
        if (!empty($filters['statut'])) {
            $where[] = "a.statut = ?";
            $params[] = $filters['statut'];
        }

        $this->scopeTenant($where, $params);
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT a.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil,
                       t.nom as technicien_nom, t.prenom as technicien_prenom
                FROM analyses a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN medecins m ON a.medecin_id = m.id
                LEFT JOIN personnel t ON a.technicien_id = t.id
                $whereClause
                ORDER BY a.date_creation DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les priorités disponibles
     */
    public function getPriorites() {
        return [
            'normale' => 'Normale',
            'urgente' => 'Urgente',
            'critique' => 'Critique'
        ];
    }

    /**
     * Récupérer les statuts disponibles
     */
    public function getStatuts() {
        return [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'termine' => 'Terminé',
            'annule' => 'Annulé'
        ];
    }
    
    /**
     * Générer un numéro de ticket unique pour l'analyse
     */
    private function generateTicketNumber() {
        $prefix = 'ANAL';
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
            TenantScope::appendWhere($this->pdo, 'analyses', $where, $params);
            $check_sql = 'SELECT COUNT(*) FROM analyses WHERE ' . implode(' AND ', $where);
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
     * Obtenir le prix par défaut selon le type d'analyse
     */
    private function getDefaultPrice($type_analyse) {
        require_once __DIR__ . '/TarifAnalyseLaboratoire.php';
        return TarifAnalyseLaboratoire::getPrixForCode((string) $type_analyse);
    }
    
    /**
     * Générer le contenu HTML du ticket d'analyse
     */
    public function generateTicketHTML($analyse_id) {
        $analyse = $this->getById($analyse_id);
        if (!$analyse) {
            return false;
        }
        
        require_once __DIR__ . '/../includes/pdf_branding.php';
        $systemParams = pdf_tenant_system_params();
        $logoHTML = $systemParams->getPdfLogoBlockHtml([
            'max_height' => 80,
            'max_width' => 200,
            'margin_bottom' => '15px',
        ]);
        
        $nomClinique = $systemParams->get('nom_etablissement') ?: 'Clinique et Hôpital';
        
        $html = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ticket d\'Analyse #' . $analyse['numero_ticket'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                .header .logo { margin-bottom: 15px; }
                .header .logo img { max-height: 90px; max-width: 320px; width: auto; height: auto; object-fit: contain; object-position: center; margin: 0 auto; display: block; }
                .info-section { margin-bottom: 15px; }
                .info-section h3 { color: #333; margin-bottom: 10px; }
                .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .label { font-weight: bold; }
                .value { color: #666; }
                .total { font-size: 18px; font-weight: bold; color: #2c3e50; border-top: 2px solid #333; padding-top: 10px; margin-top: 20px; }
                @media print {
                    @page {
                        size: A4;
                        margin: 0.5cm;
                    }
                    body {
                        margin: 0;
                        padding: 0;
                        font-size: 10px;
                        line-height: 1.2;
                    }
                    .header {
                        padding-bottom: 5px;
                        margin-bottom: 8px;
                        border-bottom-width: 1px;
                    }
                    .header .logo {
                        margin-bottom: 5px;
                    }
                    .header .logo img {
                        max-height: 35px;
                        max-width: 100px;
                    }
                    .header h1 {
                        font-size: 14px;
                        margin: 3px 0;
                    }
                    .header h2 {
                        font-size: 12px;
                        margin: 2px 0;
                    }
                    .header h3 {
                        font-size: 11px;
                        margin: 2px 0;
                    }
                    .header p {
                        font-size: 9px;
                        margin: 2px 0;
                    }
                    .info-section {
                        margin-bottom: 6px;
                    }
                    .info-section h3 {
                        font-size: 10px;
                        margin-bottom: 4px;
                    }
                    .info-row {
                        margin-bottom: 2px;
                        font-size: 9px;
                    }
                    .label {
                        font-size: 9px;
                    }
                    .value {
                        font-size: 9px;
                    }
                    .total {
                        font-size: 12px;
                        padding-top: 5px;
                        margin-top: 8px;
                        border-top-width: 1px;
                    }
                    p {
                        font-size: 9px;
                        margin: 3px 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                ' . $logoHTML . '
                <h1>' . htmlspecialchars($nomClinique) . '</h1>
                <h2>LABORATOIRE MÉDICAL</h2>
                <h3>Ticket d\'Analyse</h3>
                <p>N° ' . $analyse['numero_ticket'] . '</p>
            </div>
            
            <div class="info-section">
                <h3>Informations Patient</h3>
                <div class="info-row">
                    <span class="label">Nom :</span>
                    <span class="value">' . htmlspecialchars($analyse['patient_prenom'] . ' ' . $analyse['patient_nom']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Dossier :</span>
                    <span class="value">' . htmlspecialchars($analyse['numero_dossier']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">' . htmlspecialchars(medecin_profil_attribution_label_from_row($analyse)) . ' :</span>
                    <span class="value">' . htmlspecialchars(medecin_profil_format_joined($analyse)) . '</span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Détails de l\'Analyse</h3>
                <div class="info-row">
                    <span class="label">Type :</span>
                    <span class="value">' . htmlspecialchars($this->getTypesAnalyses()[$analyse['type_analyse']] ?? $analyse['type_analyse']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Priorité :</span>
                    <span class="value">' . htmlspecialchars($this->getPriorites()[$analyse['priorite']] ?? ucfirst($analyse['priorite'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Statut :</span>
                    <span class="value">' . htmlspecialchars($this->getStatuts()[$analyse['statut']] ?? ucfirst($analyse['statut'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date de création :</span>
                    <span class="value">' . date('d/m/Y H:i', strtotime($analyse['date_creation'])) . '</span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Description</h3>
                <p>' . ($analyse['description'] ? nl2br(htmlspecialchars($analyse['description'])) : 'Aucune description') . '</p>
            </div>
            
            <div class="info-section">
                <h3>Instructions</h3>
                <p>' . ($analyse['instructions'] ? nl2br(htmlspecialchars($analyse['instructions'])) : 'Aucune instruction') . '</p>
            </div>';
        
        if ($analyse['resultats']) {
            $html .= '
            <div class="info-section">
                <h3>Résultats</h3>
                <p>' . nl2br(htmlspecialchars($analyse['resultats'])) . '</p>
            </div>';
        }
        
        $html .= '
            <div class="info-section">
                <h3>Tarification</h3>
                <div class="info-row">
                    <span class="label">Prix de l\'analyse :</span>
                    <span class="value">' . number_format($analyse['prix_analyse'], 0, ',', ' ') . ' FCFA</span>
                </div>
                <div class="total">
                    <div class="info-row">
                        <span class="label">TOTAL :</span>
                        <span class="value">' . number_format($analyse['prix_analyse'], 0, ',', ' ') . ' FCFA</span>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
                <p>Ticket généré le ' . date('d/m/Y H:i') . '</p>
                <p>Merci de votre confiance</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}

