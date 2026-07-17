<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../includes/PaymentAudit.php';

class Paiement {
    private $pdo;
    /** @var array<string>|null */
    private static $tableColumns = null;

    private function scopeTenant(array &$where, array &$params, string $alias = 'p'): void
    {
        TenantScope::appendWhere($this->pdo, 'paiements', $where, $params, $alias);
    }
    
    public function __construct() {
        $this->pdo = getDB();
    }

    private function hasColumn(string $column): bool
    {
        if (self::$tableColumns === null) {
            try {
                self::$tableColumns = $this->pdo->query('SHOW COLUMNS FROM paiements')->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                self::$tableColumns = [];
            }
        }
        return in_array($column, self::$tableColumns, true);
    }

    private function financeSyncEnabled(): bool
    {
        return function_exists('payment_finance_sync_enabled') && payment_finance_sync_enabled();
    }

    private function requireFinanceSyncFeature(): void
    {
        if (!$this->financeSyncEnabled()) {
            throw new RuntimeException(
                'La synchronisation Paiements / Finances / Analyses n\'est pas activée pour cet établissement. Contactez l\'administrateur plateforme.'
            );
        }
    }

    /** Paiement encaissé — champs financiers verrouillés. */
    public function isEncaisseVerrouille(array $paiement): bool
    {
        return ($paiement['statut'] ?? '') === 'paye';
    }

    /** Paiement clos (annulé ou remboursé) — plus aucune modification. */
    public function isHistoriqueClos(array $paiement): bool
    {
        return in_array($paiement['statut'] ?? '', ['annule', 'rembourse'], true);
    }

    /**
     * @throws RuntimeException
     */
    private function assertUpdateAllowed(array $ancien, array $nouveau): void
    {
        if ($this->isHistoriqueClos($ancien)) {
            throw new RuntimeException(
                'Ce paiement est clos (annulé ou remboursé). Aucune modification n\'est autorisée.'
            );
        }

        if (!$this->isEncaisseVerrouille($ancien)) {
            return;
        }

        $newStatut = $nouveau['statut'] ?? $ancien['statut'];
        $allowed = ['annule', 'rembourse'];
        if (!in_array($newStatut, $allowed, true)) {
            throw new RuntimeException(
                'Paiement encaissé verrouillé. Seuls les statuts « Annulé » ou « Remboursé » sont autorisés.'
            );
        }

        $lockedFields = [
            'patient_id', 'consultation_id', 'analyse_id', 'numero_facture',
            'montant', 'type_paiement', 'date_paiement', 'reference_paiement',
        ];
        foreach ($lockedFields as $field) {
            $oldVal = $ancien[$field] ?? null;
            $newVal = array_key_exists($field, $nouveau) ? $nouveau[$field] : $oldVal;
            if ((string) $oldVal !== (string) $newVal) {
                throw new RuntimeException(
                    'Modification du montant, de la date ou du mode de paiement interdite sur un encaissement. Utilisez Annulation ou Remboursement.'
                );
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function assertDeleteAllowed(array $paiement): void
    {
        $statut = $paiement['statut'] ?? '';
        if (in_array($statut, ['paye', 'annule', 'rembourse'], true)) {
            throw new RuntimeException(
                'Suppression interdite pour un paiement encaissé ou clos. Conservez l\'historique ou passez par Annulation.'
            );
        }
    }
    
    /**
     * Récupère tous les paiements avec pagination et filtres
     */
    public function getAll($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        // Filtre par recherche
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.numero_facture LIKE ? OR pat.nom LIKE ? OR pat.prenom LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtre par statut
        if (!empty($filters['statut'])) {
            $whereConditions[] = "p.statut = ?";
            $params[] = $filters['statut'];
        }
        
        // Filtre par type de paiement
        if (!empty($filters['type_paiement'])) {
            $whereConditions[] = "p.type_paiement = ?";
            $params[] = $filters['type_paiement'];
        }
        
        // Filtre par date
        if (!empty($filters['date_debut'])) {
            $whereConditions[] = "p.date_paiement >= ?";
            $params[] = $filters['date_debut'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_fin'])) {
            $whereConditions[] = "p.date_paiement <= ?";
            $params[] = $filters['date_fin'] . ' 23:59:59';
        }

        $this->scopeTenant($whereConditions, $params);
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        $sql = "SELECT p.*, 
                       pat.nom as patient_nom, pat.prenom as patient_prenom, pat.numero_dossier,
                       c.id as consultation_id, c.date_consultation,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM paiements p
                LEFT JOIN patients pat ON p.patient_id = pat.id
                LEFT JOIN consultations c ON p.consultation_id = c.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                $whereClause
                ORDER BY p.date_paiement DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère un paiement par son ID
     */
    public function getById($id) {
        $sql = "SELECT p.*, 
                       pat.nom as patient_nom, pat.prenom as patient_prenom, pat.numero_dossier,
                       pat.sexe, pat.date_naissance, pat.telephone, pat.email, pat.adresse,
                       c.id as consultation_id, c.date_consultation, c.diagnostic, c.traitement, c.ordonnance,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM paiements p
                LEFT JOIN patients pat ON p.patient_id = pat.id
                LEFT JOIN consultations c ON p.consultation_id = c.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE p.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'paiements', 'p');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'paiements')));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Paiement lié à une consultation (le plus récent).
     */
    public function getByConsultationId(int $consultationId): ?array
    {
        $where = ['p.consultation_id = ?'];
        $params = [$consultationId];
        $this->scopeTenant($where, $params, 'p');

        $sql = 'SELECT p.* FROM paiements p WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.date_creation DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Écriture comptable liée à un paiement.
     */
    public function getLinkedEcriture(int $paiementId): ?array
    {
        $paiement = $this->getById($paiementId);
        if (!$paiement || empty($paiement['ecriture_comptable_id'])) {
            return null;
        }

        if (!class_exists('Finances')) {
            require_once __DIR__ . '/Finances.php';
        }

        $ecriture = (new Finances())->getEcritureById((int) $paiement['ecriture_comptable_id']);
        return $ecriture ?: null;
    }

    /**
     * Extrait l'ID paiement depuis une référence d'écriture (PAI-{id}-…).
     */
    public static function parsePaiementIdFromReference(?string $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }
        if (preg_match('/^PAI-(\d+)(?:-|$)/', $reference, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Paiement lié à une analyse de laboratoire (le plus récent).
     */
    public function getByAnalyseId(int $analyseId): ?array
    {
        if (!$this->hasColumn('analyse_id')) {
            return null;
        }

        $where = ['p.analyse_id = ?'];
        $params = [$analyseId];
        $this->scopeTenant($where, $params, 'p');

        $sql = 'SELECT p.* FROM paiements p WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.date_creation DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Crée un paiement en attente à partir d'une analyse laboratoire.
     *
     * @throws RuntimeException
     */
    public function createFromAnalyse(int $analyseId, array $options = [])
    {
        $this->requireFinanceSyncFeature();

        if (!$this->hasColumn('analyse_id')) {
            throw new RuntimeException(
                'Schéma paiements incomplet (colonne analyse_id). Ouvrez une page de l\'application pour lancer la mise à jour automatique, puis réessayez.'
            );
        }

        if (!class_exists('Analyse')) {
            require_once __DIR__ . '/Analyse.php';
        }

        $analyseModel = new Analyse();
        $analyse = $analyseModel->getById($analyseId);
        if (!$analyse) {
            throw new RuntimeException('Analyse introuvable.');
        }

        if (($analyse['statut'] ?? '') === 'annule') {
            throw new RuntimeException('Impossible de facturer une analyse annulée.');
        }

        $existing = $this->getByAnalyseId($analyseId);
        if ($existing) {
            throw new RuntimeException('Un paiement existe déjà pour cette analyse.');
        }

        $montant = (float) ($analyse['prix_analyse'] ?? 0);
        if ($montant <= 0) {
            throw new RuntimeException('Le prix de l\'analyse est nul.');
        }

        $ticket = $analyse['numero_ticket'] ?? ('#' . $analyseId);
        $typeLabel = $analyse['type_analyse'] ?? 'analyse';

        $data = [
            'patient_id' => (int) $analyse['patient_id'],
            'analyse_id' => $analyseId,
            'montant' => $montant,
            'type_paiement' => $options['type_paiement'] ?? 'especes',
            'statut' => $options['statut'] ?? 'en_attente',
            'description' => $options['description'] ?? ('Paiement analyse ' . $typeLabel . ' ' . $ticket),
            'date_paiement' => $options['date_paiement'] ?? date('Y-m-d H:i:s'),
            'reference_paiement' => $options['reference_paiement'] ?? null,
            'notes' => $options['notes'] ?? null,
            'cree_par' => $options['cree_par'] ?? null,
        ];
        if (!empty($options['numero_facture'])) {
            $data['numero_facture'] = $options['numero_facture'];
        }

        return $this->create($data);
    }

    /**
     * Crée un paiement en attente à partir d'une consultation (montant total calculé).
     *
     * @throws RuntimeException
     */
    public function createFromConsultation(int $consultationId, array $options = [])
    {
        $this->requireFinanceSyncFeature();

        if (!class_exists('Consultation')) {
            require_once __DIR__ . '/Consultation.php';
        }

        $consultationModel = new Consultation();
        $consultation = $consultationModel->getById($consultationId);
        if (!$consultation) {
            throw new RuntimeException('Consultation introuvable.');
        }

        if (($consultation['statut'] ?? '') === 'annulee') {
            throw new RuntimeException('Impossible de facturer une consultation annulée.');
        }

        $existing = $this->getByConsultationId($consultationId);
        if ($existing) {
            throw new RuntimeException('Un paiement existe déjà pour cette consultation.');
        }

        $montant = (float) $consultationModel->getPrixTotalComplet($consultationId);
        if ($montant <= 0) {
            throw new RuntimeException('Le montant de la consultation est nul.');
        }

        $ticket = $consultation['numero_ticket'] ?? ('#' . $consultationId);

        $data = [
            'patient_id' => (int) $consultation['patient_id'],
            'consultation_id' => $consultationId,
            'montant' => $montant,
            'type_paiement' => $options['type_paiement'] ?? 'especes',
            'statut' => $options['statut'] ?? 'en_attente',
            'description' => $options['description'] ?? ('Paiement consultation ' . $ticket),
            'date_paiement' => $options['date_paiement'] ?? date('Y-m-d H:i:s'),
            'reference_paiement' => $options['reference_paiement'] ?? null,
            'notes' => $options['notes'] ?? null,
            'cree_par' => $options['cree_par'] ?? null,
        ];
        if (!empty($options['numero_facture'])) {
            $data['numero_facture'] = $options['numero_facture'];
        }

        return $this->create($data);
    }
    
    private function rollbackTransactionIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function isDuplicatePaymentInsertError(PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * Crée un nouveau paiement et synchronise avec le module Finances
     */
    public function create($data) {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->rollbackTransactionIfActive();
            $this->pdo->beginTransaction();

            try {
                $ref = $data['reference_paiement'] ?? null;
                if ($ref === null || $ref === '' || (is_string($ref) && trim($ref) === '')) {
                    $data['reference_paiement'] = $this->generateReferencePaiement();
                } elseif (is_string($ref)) {
                    $data['reference_paiement'] = trim($ref);
                }

                if (empty($data['numero_facture'])) {
                    $data['numero_facture'] = $this->generateNumeroFacture();
                }

                $columns = [
                    'patient_id', 'consultation_id', 'numero_facture', 'montant',
                    'type_paiement', 'statut', 'description', 'date_paiement',
                    'reference_paiement', 'notes', 'date_creation',
                ];
                $placeholders = array_fill(0, count($columns) - 1, '?');
                $placeholders[] = 'NOW()';
                $values = [
                    $data['patient_id'],
                    $data['consultation_id'] ?? null,
                    $data['numero_facture'],
                    $data['montant'],
                    $data['type_paiement'],
                    $data['statut'],
                    $data['description'] ?? null,
                    $data['date_paiement'] ?? date('Y-m-d H:i:s'),
                    $data['reference_paiement'],
                    $data['notes'] ?? null,
                ];
                if ($this->hasColumn('analyse_id')) {
                    $columns[] = 'analyse_id';
                    array_splice($placeholders, count($placeholders) - 1, 0, '?');
                    $values[] = $data['analyse_id'] ?? null;
                }
                TenantScope::bindInsert($this->pdo, 'paiements', $columns, $placeholders, $values);
                $sql = 'INSERT INTO paiements (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);

                $paiement_id = (int) $this->pdo->lastInsertId();

                $this->pdo->commit();

                if (($data['statut'] ?? 'en_attente') === 'paye' && $this->financeSyncEnabled()) {
                    if (!$this->syncWithFinances($paiement_id, $data)) {
                        throw new RuntimeException(
                            'Paiement enregistré mais la synchronisation comptable a échoué. Vérifiez le module Finances.'
                        );
                    }
                }

                $this->invalidateDashboardCache();
                PaymentAudit::log('paiement_create', [
                    'paiement_id' => $paiement_id,
                    'statut' => $data['statut'] ?? 'en_attente',
                    'montant' => (float) ($data['montant'] ?? 0),
                    'numero_facture' => $data['numero_facture'] ?? '',
                ]);

                return $paiement_id;
            } catch (PDOException $e) {
                $this->rollbackTransactionIfActive();
                if ($attempt < $maxAttempts && $this->isDuplicatePaymentInsertError($e)) {
                    $data['numero_facture'] = '';
                    $data['reference_paiement'] = null;
                    continue;
                }
                throw $e;
            } catch (Exception $e) {
                $this->rollbackTransactionIfActive();
                throw $e;
            }
        }

        return false;
    }
    
    /**
     * Synchronise un paiement avec le module Finances (idempotent).
     */
    private function syncWithFinances($paiement_id, $paiement_data) {
        if (!$this->financeSyncEnabled()) {
            return false;
        }

        try {
            $paiement_id = (int) $paiement_id;
            $existing = $this->getById($paiement_id);
            if ($existing && !empty($existing['ecriture_comptable_id'])) {
                return (int) $existing['ecriture_comptable_id'];
            }

            if (!class_exists('Finances')) {
                require_once __DIR__ . '/Finances.php';
            }

            $financesModel = new Finances();
            $ecriture_data = $this->buildEcritureData($paiement_id, $paiement_data);

            $compte_debit = $this->getOrCreateCompteEncaissement($paiement_data['type_paiement'] ?? 'especes');
            $compte_recettes = $this->getOrCreateCompteRecettes();

            if (!$compte_debit || !$compte_recettes) {
                error_log("Impossible de synchroniser le paiement #$paiement_id : comptes comptables manquants");
                return false;
            }

            $ecriture_data['compte_debit_id'] = $compte_debit['id'];
            $ecriture_data['compte_credit_id'] = $compte_recettes['id'];

            $ecriture_id = $financesModel->createEcriture($ecriture_data);
            $this->linkEcritureToPaiement($paiement_id, (int) $ecriture_id);

            PaymentAudit::log('paiement_sync_finances', [
                'paiement_id' => $paiement_id,
                'ecriture_id' => (int) $ecriture_id,
                'montant' => (float) ($ecriture_data['montant'] ?? 0),
            ]);

            return $ecriture_id;
        } catch (Exception $e) {
            error_log("Erreur lors de la synchronisation avec Finances: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour l'écriture comptable liée après modification d'un paiement encaissé.
     */
    private function updateFinancesSync(int $paiement_id, array $paiement_data, array $ancien_paiement): bool
    {
        if (!$this->financeSyncEnabled()) {
            return false;
        }

        $ecriture_id = (int) ($ancien_paiement['ecriture_comptable_id'] ?? 0);
        if (!$ecriture_id) {
            return (bool) $this->syncWithFinances($paiement_id, $paiement_data);
        }

        if (!class_exists('Finances')) {
            require_once __DIR__ . '/Finances.php';
        }

        $financesModel = new Finances();
        $ecriture_data = $this->buildEcritureData($paiement_id, $paiement_data);

        $compte_debit = $this->getOrCreateCompteEncaissement($paiement_data['type_paiement'] ?? 'especes');
        $compte_recettes = $this->getOrCreateCompteRecettes();
        if (!$compte_debit || !$compte_recettes) {
            return false;
        }

        $ecriture_data['compte_debit_id'] = $compte_debit['id'];
        $ecriture_data['compte_credit_id'] = $compte_recettes['id'];
        $ecriture_data['valide'] = 1;

        return $financesModel->updateEcriture($ecriture_id, $ecriture_data);
    }

    /**
     * Contre-passation comptable — conserve l'écriture d'origine (bonnes pratiques ERP).
     */
    private function reverseFinancesSync(int $paiement_id, array $paiement, string $motif): bool
    {
        if (!$this->financeSyncEnabled()) {
            return true;
        }

        $ecriture_id = (int) ($paiement['ecriture_comptable_id'] ?? 0);
        if (!$ecriture_id) {
            return true;
        }

        if (!class_exists('Finances')) {
            require_once __DIR__ . '/Finances.php';
        }

        $financesModel = new Finances();
        $suffix = $this->contrepassationSuffixForMotif($motif);
        $libellePrefix = $this->contrepassationLibelleForMotif($motif);

        try {
            $contreId = $this->createContrePassationEcriture(
                $financesModel,
                $ecriture_id,
                $paiement_id,
                $suffix,
                $libellePrefix,
                $paiement['cree_par'] ?? null
            );

            PaymentAudit::log('paiement_contrepassation', [
                'paiement_id' => $paiement_id,
                'motif' => $motif,
                'ecriture_originale_id' => $ecriture_id,
                'ecriture_contrepassation_id' => $contreId,
            ]);

            return $contreId !== null;
        } catch (Exception $e) {
            error_log("Erreur reverseFinancesSync paiement #$paiement_id : " . $e->getMessage());
            return false;
        }
    }

    private function contrepassationSuffixForMotif(string $motif): string
    {
        $map = [
            'rembourse' => 'REM',
            'annule' => 'ANN',
            'suppression' => 'SUP',
        ];
        if (isset($map[$motif])) {
            return $map[$motif];
        }
        if (strpos($motif, 'statut_') === 0) {
            return 'REV';
        }
        return 'ANN';
    }

    private function contrepassationLibelleForMotif(string $motif): string
    {
        $map = [
            'rembourse' => 'Remboursement',
            'annule' => 'Annulation',
            'suppression' => 'Suppression paiement',
        ];
        if (isset($map[$motif])) {
            return $map[$motif];
        }
        if (strpos($motif, 'statut_') === 0) {
            return 'Contrepassation';
        }
        return 'Annulation';
    }

    private function contrepassationReferenceExists(Finances $financesModel, string $reference): bool
    {
        $pdo = getDB();
        $where = ['reference = ?'];
        $params = [$reference];
        TenantScope::appendWhere($pdo, 'ecritures_comptables', $where, $params);
        $stmt = $pdo->prepare('SELECT 1 FROM ecritures_comptables WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crée une écriture inverse sans supprimer l'originale.
     */
    private function createContrePassationEcriture(
        Finances $financesModel,
        int $ecritureOriginaleId,
        int $paiementId,
        string $referenceSuffix,
        string $libellePrefix,
        ?int $creePar
    ): ?int {
        $ecriture = $financesModel->getEcritureById($ecritureOriginaleId);
        if (!$ecriture) {
            return null;
        }

        $reference = 'PAI-' . $referenceSuffix . '-' . $paiementId;
        if ($this->contrepassationReferenceExists($financesModel, $reference)) {
            return null;
        }

        return (int) $financesModel->createEcriture([
            'date_ecriture' => date('Y-m-d'),
            'compte_debit_id' => (int) $ecriture['compte_credit_id'],
            'compte_credit_id' => (int) $ecriture['compte_debit_id'],
            'montant' => (float) $ecriture['montant'],
            'libelle' => $libellePrefix . ' - ' . ($ecriture['libelle'] ?? 'Paiement patient'),
            'reference' => $reference,
            'valide' => 1,
            'cree_par' => $creePar,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEcritureData(int $paiement_id, array $paiement_data): array
    {
        $datePaiement = $paiement_data['date_paiement'] ?? date('Y-m-d H:i:s');
        $numeroFacture = $paiement_data['numero_facture'] ?? 'N/A';
        $libelle = 'Paiement patient - Facture ' . $numeroFacture;

        if (!empty($paiement_data['consultation_id'])) {
            $libelle .= ' (Consultation #' . (int) $paiement_data['consultation_id'] . ')';
        }
        if (!empty($paiement_data['analyse_id'])) {
            $libelle .= ' (Analyse #' . (int) $paiement_data['analyse_id'] . ')';
        }

        return [
            'date_ecriture' => date('Y-m-d', strtotime((string) $datePaiement)),
            'montant' => (float) ($paiement_data['montant'] ?? 0),
            'libelle' => $libelle,
            'reference' => 'PAI-' . $paiement_id . '-' . $numeroFacture,
            'valide' => 1,
            'cree_par' => $paiement_data['cree_par'] ?? null,
        ];
    }

    private function linkEcritureToPaiement(int $paiement_id, ?int $ecriture_id): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE paiements SET ecriture_comptable_id = ? WHERE id = ?'
                . TenantScope::andOwnedByTenant($this->pdo, 'paiements')
            );
            $stmt->execute(TenantScope::appendOwned($this->pdo, 'paiements', [$ecriture_id, $paiement_id]));
        } catch (Exception $e) {
            // Colonne absente sur d'anciens schémas
        }
    }

    private function paiementNeedsEcritureUpdate(array $nouveau, array $ancien): bool
    {
        return (float) ($nouveau['montant'] ?? 0) !== (float) ($ancien['montant'] ?? 0)
            || ($nouveau['type_paiement'] ?? '') !== ($ancien['type_paiement'] ?? '')
            || ($nouveau['numero_facture'] ?? '') !== ($ancien['numero_facture'] ?? '')
            || date('Y-m-d', strtotime((string) ($nouveau['date_paiement'] ?? 'now')))
                !== date('Y-m-d', strtotime((string) ($ancien['date_paiement'] ?? 'now')));
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

    /**
     * Compte d'encaissement selon le mode de paiement (caisse ou banque).
     */
    private function getOrCreateCompteEncaissement(string $type_paiement)
    {
        if ($type_paiement === 'especes') {
            return $this->getOrCreateCompteCaisse();
        }
        return $this->getOrCreateCompteBanque();
    }

    /**
     * Récupère ou crée le compte Banque
     */
    private function getOrCreateCompteBanque()
    {
        try {
            if (!class_exists('Finances')) {
                require_once __DIR__ . '/Finances.php';
            }

            $financesModel = new Finances();

            $comptes = $financesModel->getComptes(1, 100, 'banque', '');
            foreach ($comptes as $compte) {
                if (stripos($compte['libelle'], 'banque') !== false && $compte['type_compte'] === 'actif') {
                    return $compte;
                }
            }

            $comptes51 = $financesModel->getComptes(1, 5, '51', 'actif');
            foreach ($comptes51 as $compte) {
                if (strpos((string) ($compte['numero_compte'] ?? ''), '51') === 0
                    && strpos((string) ($compte['numero_compte'] ?? ''), '53') !== 0) {
                    return $compte;
                }
            }

            $compte_data = [
                'numero_compte' => '512000',
                'libelle' => 'Banque principale',
                'type_compte' => 'actif',
                'classe' => '5',
                'solde_initial' => 0.00,
                'statut' => 'actif',
            ];

            $compte_id = $financesModel->createCompte($compte_data);
            if ($compte_id) {
                return $financesModel->getCompteById($compte_id);
            }

            return null;
        } catch (Exception $e) {
            error_log('Erreur getOrCreateCompteBanque: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Récupère ou crée le compte Caisse
     */
    private function getOrCreateCompteCaisse() {
        try {
            if (!class_exists('Finances')) {
                require_once __DIR__ . '/Finances.php';
            }
            
            $financesModel = new Finances();
            
            // Chercher le compte Caisse
            $comptes = $financesModel->getComptes(1, 100, 'caisse', '');
            foreach ($comptes as $compte) {
                if (stripos($compte['libelle'], 'caisse') !== false && $compte['type_compte'] === 'actif') {
                    return $compte;
                }
            }
            
            // Si pas trouvé, chercher par numéro de compte (généralement 53xxx pour caisse)
            $comptes53 = $financesModel->getComptes(1, 5, '53', 'actif');
            foreach ($comptes53 as $compte) {
                if (strpos((string) ($compte['numero_compte'] ?? ''), '53') === 0) {
                    return $compte;
                }
            }
            
            // Créer le compte Caisse s'il n'existe pas
            $compte_data = [
                'numero_compte' => '531000',
                'libelle' => 'Caisse principale',
                'type_compte' => 'actif',
                'classe' => '5',
                'solde_initial' => 0.00,
                'statut' => 'actif'
            ];
            
            $compte_id = $financesModel->createCompte($compte_data);
            if ($compte_id) {
                return $financesModel->getCompteById($compte_id);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération/création du compte Caisse: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Récupère ou crée le compte Recettes
     */
    private function getOrCreateCompteRecettes() {
        try {
            if (!class_exists('Finances')) {
                require_once __DIR__ . '/Finances.php';
            }
            
            $financesModel = new Finances();
            
            // Chercher le compte Recettes
            $comptes = $financesModel->getComptes(1, 100, 'recette', '');
            foreach ($comptes as $compte) {
                if (stripos($compte['libelle'], 'recette') !== false && $compte['type_compte'] === 'produit') {
                    return $compte;
                }
            }
            
            $comptes70 = $financesModel->getComptes(1, 5, '70', 'produit');
            foreach ($comptes70 as $compte) {
                if (strpos((string) ($compte['numero_compte'] ?? ''), '70') === 0) {
                    return $compte;
                }
            }
            
            // Créer le compte Recettes s'il n'existe pas
            $compte_data = [
                'numero_compte' => '701000',
                'libelle' => 'Recettes d\'exploitation - Soins médicaux',
                'type_compte' => 'produit',
                'classe' => '7',
                'solde_initial' => 0.00,
                'statut' => 'actif'
            ];
            
            $compte_id = $financesModel->createCompte($compte_data);
            if ($compte_id) {
                return $financesModel->getCompteById($compte_id);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération/création du compte Recettes: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sync comptable après commit paiement (évite transactions PDO imbriquées avec Finances).
     */
    private function applyFinanceSyncAfterPaymentChange(
        int $paiementId,
        array $ancienPaiement,
        array $nouveauData,
        string $ancienStatut,
        string $nouveauStatut
    ): void {
        if (!$this->financeSyncEnabled()) {
            return;
        }

        $merged = array_merge($ancienPaiement, $nouveauData);
        $wasPaye = $ancienStatut === 'paye';
        $isPaye = $nouveauStatut === 'paye';
        $isReversed = in_array($nouveauStatut, ['annule', 'rembourse'], true);
        $hadEcriture = !empty($ancienPaiement['ecriture_comptable_id']);

        if ($isReversed && $wasPaye && $hadEcriture) {
            if (!$this->reverseFinancesSync($paiementId, $merged, $nouveauStatut)) {
                throw new RuntimeException('Paiement mis à jour mais la contre-passation comptable a échoué.');
            }
            return;
        }

        if (!$isPaye && $wasPaye && $hadEcriture) {
            if (!$this->reverseFinancesSync($paiementId, $merged, 'statut_' . $nouveauStatut)) {
                throw new RuntimeException('Paiement mis à jour mais la contre-passation comptable a échoué.');
            }
            return;
        }

        if ($isPaye && !$wasPaye) {
            if (!$this->syncWithFinances($paiementId, $merged)) {
                throw new RuntimeException('Paiement mis à jour mais la synchronisation comptable a échoué.');
            }
            return;
        }

        if ($isPaye && $wasPaye && !$hadEcriture) {
            if (!$this->syncWithFinances($paiementId, $merged)) {
                throw new RuntimeException('Paiement mis à jour mais la synchronisation comptable a échoué.');
            }
        }
    }

    /**
     * Met à jour un paiement existant et synchronise avec Finances si nécessaire
     */
    public function update($id, $data) {
        $ancien_paiement = $this->getById($id);
        if (!$ancien_paiement) {
            throw new RuntimeException('Paiement introuvable.');
        }

        $this->assertUpdateAllowed($ancien_paiement, $data);

        $ancien_statut = $ancien_paiement['statut'] ?? 'en_attente';
        $nouveau_statut = $data['statut'] ?? $ancien_statut;

        $this->pdo->beginTransaction();

        try {
            // Vérifier si la colonne date_modification existe
            $hasDateModification = false;
            try {
                $checkColumn = $this->pdo->query("SHOW COLUMNS FROM paiements LIKE 'date_modification'");
                $hasDateModification = $checkColumn->rowCount() > 0;
            } catch (Exception $e) {
                // Si la vérification échoue, on suppose que la colonne n'existe pas
            }
            
            if ($hasDateModification) {
                $sql = "UPDATE paiements SET 
                        patient_id = ?, consultation_id = ?, numero_facture = ?, montant = ?,
                        type_paiement = ?, statut = ?, description = ?, date_paiement = ?,
                        reference_paiement = ?, notes = ?, date_modification = NOW()
                        WHERE id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'paiements');
                $updateValues = [
                    $data['patient_id'],
                    $data['consultation_id'] ?? null,
                    $data['numero_facture'],
                    $data['montant'],
                    $data['type_paiement'],
                    $data['statut'],
                    $data['description'] ?? null,
                    $data['date_paiement'] ?? date('Y-m-d H:i:s'),
                    $data['reference_paiement'] ?? null,
                    $data['notes'] ?? null,
                    (int) $id,
                ];
            } else {
                $sql = "UPDATE paiements SET 
                        patient_id = ?, consultation_id = ?, numero_facture = ?, montant = ?,
                        type_paiement = ?, statut = ?, description = ?, date_paiement = ?,
                        reference_paiement = ?, notes = ?
                        WHERE id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'paiements');
                $updateValues = [
                    $data['patient_id'],
                    $data['consultation_id'] ?? null,
                    $data['numero_facture'],
                    $data['montant'],
                    $data['type_paiement'],
                    $data['statut'],
                    $data['description'] ?? null,
                    $data['date_paiement'] ?? date('Y-m-d H:i:s'),
                    $data['reference_paiement'] ?? null,
                    $data['notes'] ?? null,
                    (int) $id,
                ];
            }

            if ($this->hasColumn('analyse_id')) {
                $analyseValue = array_key_exists('analyse_id', $data)
                    ? ($data['analyse_id'] !== null && $data['analyse_id'] !== '' ? (int) $data['analyse_id'] : null)
                    : ($ancien_paiement['analyse_id'] ?? null);
                $sql = preg_replace('/ WHERE id = \?/', ', analyse_id = ? WHERE id = ?', $sql, 1);
                array_splice($updateValues, count($updateValues) - 1, 0, [$analyseValue]);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(TenantScope::appendOwned($this->pdo, 'paiements', $updateValues));

            $this->pdo->commit();
        } catch (RuntimeException $e) {
            $this->rollbackTransactionIfActive();
            throw $e;
        } catch (Exception $e) {
            $this->rollbackTransactionIfActive();
            throw $e;
        }

        $this->applyFinanceSyncAfterPaymentChange(
            (int) $id,
            $ancien_paiement,
            $data,
            $ancien_statut,
            $nouveau_statut
        );

        PaymentAudit::log('paiement_update', [
            'paiement_id' => (int) $id,
            'ancien_statut' => $ancien_statut,
            'nouveau_statut' => $nouveau_statut,
            'montant' => (float) ($data['montant'] ?? $ancien_paiement['montant'] ?? 0),
        ]);

        $this->invalidateDashboardCache();

        return true;
    }
    
    /**
     * Supprime un paiement (interdit si encaissé ou clos).
     */
    public function delete($id) {
        $this->pdo->beginTransaction();

        try {
            $paiement = $this->getById((int) $id);
            if (!$paiement) {
                $this->rollbackTransactionIfActive();
                return false;
            }

            $this->assertDeleteAllowed($paiement);

            $sql = 'DELETE FROM paiements WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'paiements');
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(TenantScope::paramsForId($this->pdo, 'paiements', (int) $id));

            if ($result) {
                $this->pdo->commit();
                PaymentAudit::log('paiement_delete', [
                    'paiement_id' => (int) $id,
                    'statut' => $paiement['statut'] ?? '',
                    'montant' => (float) ($paiement['montant'] ?? 0),
                ]);
                $this->invalidateDashboardCache();
                return true;
            }

            $this->rollbackTransactionIfActive();
            return false;
        } catch (Exception $e) {
            $this->rollbackTransactionIfActive();
            error_log("Erreur lors de la suppression du paiement ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Compte le nombre total de paiements avec filtres
     */
    public function count($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.numero_facture LIKE ? OR pat.nom LIKE ? OR pat.prenom LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['statut'])) {
            $whereConditions[] = "p.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['type_paiement'])) {
            $whereConditions[] = "p.type_paiement = ?";
            $params[] = $filters['type_paiement'];
        }

        $this->scopeTenant($whereConditions, $params);
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM paiements p
                LEFT JOIN patients pat ON p.patient_id = pat.id
                $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Récupère les statistiques des paiements
     */
    public function getStats() {
        $where = ['1=1'];
        $params = [];
        TenantScope::appendWhere($this->pdo, 'paiements', $where, $params);
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as total_encaisse,
                SUM(CASE WHEN statut = 'en_attente' THEN montant ELSE 0 END) as en_attente,
                SUM(CASE WHEN statut = 'partiel' THEN montant ELSE 0 END) as partiel,
                COUNT(CASE WHEN statut = 'paye' THEN 1 END) as nb_payes,
                COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as nb_en_attente,
                COUNT(CASE WHEN statut = 'partiel' THEN 1 END) as nb_partiels,
                COUNT(CASE WHEN statut = 'annule' THEN 1 END) as nb_annules
                FROM paiements $whereClause";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Récupère les paiements d'un patient
     */
    public function getPatientPaiements($patient_id, $limit = 10) {
        $where = ['p.patient_id = ?'];
        $params = [(int) $patient_id];
        $this->scopeTenant($where, $params);
        $limit = (int) $limit;

        $sql = "SELECT p.*, c.date_consultation, m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM paiements p
                LEFT JOIN consultations c ON p.consultation_id = c.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.date_paiement DESC
                LIMIT $limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Génère un numéro de facture unique (par tenant, séquence du jour dans le préfixe FACT + Ymd).
     */
    public function generateNumeroFacture() {
        return $this->generateDailySequenceNumber('FACT', 4);
    }
    
    /**
     * Génère une référence de paiement unique (préfixe REF + date + séquence du jour)
     */
    public function generateReferencePaiement() {
        return $this->generateDailySequenceNumber('REF', 4);
    }

    /**
     * Prochain numéro séquentiel du jour : MAX(suffixe existant) + 1 pour le préfixe donné.
     * Évite les doublons liés à COUNT(date_creation) vs fuseau PHP/MySQL.
     */
    private function generateDailySequenceNumber(string $prefix, int $seqLen): string
    {
        $date = date('Ymd');
        $stem = $prefix . $date;
        $like = $stem . '%';

        $where = ['numero_facture LIKE ?'];
        $params = [$like];
        if ($prefix === 'REF') {
            $where = ['reference_paiement LIKE ?'];
            $params = [$like];
        }
        $this->scopeTenant($where, $params, '');

        $column = $prefix === 'REF' ? 'reference_paiement' : 'numero_facture';
        $startPos = strlen($stem) + 1;

        $sql = 'SELECT COALESCE(MAX(CAST(SUBSTRING(' . $column . ', ' . $startPos . ', ' . $seqLen . ') AS UNSIGNED)), 0)
                FROM paiements WHERE ' . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $maxSeq = (int) $stmt->fetchColumn();

        return $stem . str_pad((string) ($maxSeq + 1), $seqLen, '0', STR_PAD_LEFT);
    }
    
    /**
     * Récupère les types de paiement disponibles
     */
    public function getTypesPaiement() {
        return [
            'carte' => 'Carte bancaire',
            'virement' => 'Virement bancaire',
            'especes' => 'Espèces',
            'cheque' => 'Chèque',
            'securite_sociale' => 'Sécurité sociale',
            'mutuelle' => 'Mutuelle',
            'mobile_money' => 'Mobile Money',
            'autre' => 'Autre'
        ];
    }
    
    /**
     * Récupère les statuts disponibles
     */
    public function getStatuts() {
        return [
            'en_attente' => 'En attente',
            'partiel' => 'Paiement partiel',
            'paye' => 'Payé',
            'annule' => 'Annulé',
            'rembourse' => 'Remboursé'
        ];
    }
    
    /**
     * Récupère les statistiques par mois
     */
    public function getStatsByMonth($year = null) {
        if (!$year) {
            $year = date('Y');
        }

        $where = ["YEAR(date_paiement) = ?", "statut = 'paye'"];
        $params = [$year];
        TenantScope::appendWhere($this->pdo, 'paiements', $where, $params);
        
        $sql = 'SELECT 
                MONTH(date_paiement) as mois,
                SUM(CASE WHEN statut = \'paye\' THEN montant ELSE 0 END) as encaisse,
                COUNT(CASE WHEN statut = \'paye\' THEN 1 END) as nb_paiements
                FROM paiements 
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY MONTH(date_paiement)
                ORDER BY mois';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les paiements récents
     */
    public function getRecent($limit = 5) {
        $where = [];
        $params = [];
        $this->scopeTenant($where, $params);
        $limit = (int) $limit;
        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT p.*, pat.nom as patient_nom, pat.prenom as patient_prenom
                FROM paiements p
                LEFT JOIN patients pat ON p.patient_id = pat.id
                $whereClause
                ORDER BY p.date_paiement DESC
                LIMIT $limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les consultations disponibles pour les paiements
     */
    public function getConsultationsDisponibles($limit = 100) {
        try {
            // Vérifier d'abord si la table consultations existe
            $checkTable = "SHOW TABLES LIKE 'consultations'";
            $stmt = $this->pdo->query($checkTable);
            if ($stmt->rowCount() == 0) {
                // Si la table consultations n'existe pas, retourner un tableau vide
                return [];
            }
            
            // Vérifier si les tables patients et medecins existent
            $checkPatients = "SHOW TABLES LIKE 'patients'";
            $checkMedecins = "SHOW TABLES LIKE 'medecins'";
            
            $stmtPatients = $this->pdo->query($checkPatients);
            $stmtMedecins = $this->pdo->query($checkMedecins);
            
            $hasPatients = $stmtPatients->rowCount() > 0;
            $hasMedecins = $stmtMedecins->rowCount() > 0;
            
            $where = [];
            $params = [];
            TenantScope::appendWhere($this->pdo, 'consultations', $where, $params, 'c');
            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

            if ($hasPatients && $hasMedecins) {
                $sql = "SELECT c.id, c.date_consultation, c.diagnostic, c.symptomes,
                               p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                               m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                        FROM consultations c
                        LEFT JOIN patients p ON c.patient_id = p.id
                        LEFT JOIN medecins m ON c.medecin_id = m.id
                        $whereClause
                        ORDER BY c.date_consultation DESC
                        LIMIT $limit";
            } elseif ($hasPatients) {
                $sql = "SELECT c.id, c.date_consultation, c.diagnostic, c.symptomes,
                               p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                               '' as medecin_nom, '' as medecin_prenom, '' as specialite
                        FROM consultations c
                        LEFT JOIN patients p ON c.patient_id = p.id
                        $whereClause
                        ORDER BY c.date_consultation DESC
                        LIMIT $limit";
            } else {
                $sql = "SELECT c.id, c.date_consultation, c.diagnostic, c.symptomes,
                               '' as patient_nom, '' as patient_prenom, '' as numero_dossier,
                               '' as medecin_nom, '' as medecin_prenom, '' as specialite
                        FROM consultations c
                        $whereClause
                        ORDER BY c.date_consultation DESC
                        LIMIT $limit";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            // En cas d'erreur, retourner un tableau vide et logger l'erreur
            error_log("Erreur dans getConsultationsDisponibles: " . $e->getMessage());
            return [];
        }
    }
}
?>

