<?php
/**
 * Import d'un dump SQL Efficasante / Se.Santé vers un tenant isolé.
 * Les autres établissements (autres tenant_id) ne sont pas modifiés.
 */
declare(strict_types=1);

require_once __DIR__ . '/TenantSchema.php';

class TenantSqlImporter
{
    /** Tables plateforme / globales — jamais importées depuis le dump. */
    private const SKIP_TABLES = [
        'tenants',
        'subscription_orders',
        'subscription_invoices',
        'roles',
        'system_licenses',
        'prix_licences',
        'modules_licences',
        'licences',
        'renouvellements_licences',
        'login_attempts',
        'active_sessions',
        'suspicious_activities',
        'tenant_role_modules',
    ];

    /**
     * Ordre d'import (parents avant enfants) + clés étrangères à remapper.
     *
     * @var array<string, array{fks: array<string, string>}>
     */
    private const TABLE_PLAN = [
        'parametres_systeme' => ['fks' => []],
        'comptes_comptables' => ['fks' => []],
        'categories_hospitalisation' => ['fks' => []],
        'tarifs_consultation' => ['fks' => []],
        'tarifs_analyses_laboratoire' => ['fks' => []],
        'medicaments' => ['fks' => []],
        'assurances' => ['fks' => []],
        'equipements' => ['fks' => []],
        'soins_consultation' => ['fks' => []],
        'utilisateurs' => ['fks' => []],
        'medecins' => ['fks' => ['utilisateur_id' => 'utilisateurs']],
        'personnel' => ['fks' => ['utilisateur_id' => 'utilisateurs']],
        'budgets' => ['fks' => []],
        'patients' => ['fks' => ['medecin_referent_id' => 'medecins']],
        'dossiers' => ['fks' => ['patient_id' => 'patients']],
        'documents_patients' => ['fks' => ['patient_id' => 'patients']],
        'consultations' => ['fks' => ['patient_id' => 'patients', 'medecin_id' => 'medecins']],
        'consultation_soins' => ['fks' => ['consultation_id' => 'consultations', 'soin_id' => 'soins_consultation']],
        'consultation_hospitalisation' => ['fks' => ['consultation_id' => 'consultations', 'categorie_hospitalisation_id' => 'categories_hospitalisation']],
        'sejours_hospitalisation' => ['fks' => ['consultation_id' => 'consultations', 'patient_id' => 'patients', 'categorie_id' => 'categories_hospitalisation']],
        'tickets_consultation' => ['fks' => ['consultation_id' => 'consultations']],
        'rendez_vous' => ['fks' => ['patient_id' => 'patients', 'medecin_id' => 'medecins']],
        'analyses' => ['fks' => ['patient_id' => 'patients', 'medecin_id' => 'medecins']],
        'paiements' => ['fks' => ['patient_id' => 'patients', 'consultation_id' => 'consultations']],
        'contrats_assurance' => ['fks' => ['patient_id' => 'patients', 'assurance_id' => 'assurances']],
        'remboursements' => ['fks' => ['contrat_id' => 'contrats_assurance', 'paiement_id' => 'paiements']],
        'ecritures_comptables' => ['fks' => ['compte_id' => 'comptes_comptables']],
        'interventions_maintenance' => ['fks' => ['equipement_id' => 'equipements']],
        'horaires_personnel' => ['fks' => ['personnel_id' => 'personnel']],
        'conges_personnel' => ['fks' => ['personnel_id' => 'personnel']],
        'mouvements_stock_pharmacie' => ['fks' => ['medicament_id' => 'medicaments']],
        'annonces' => ['fks' => []],
        'messages_internes' => ['fks' => ['expediteur_id' => 'utilisateurs', 'destinataire_id' => 'utilisateurs']],
        'notifications' => ['fks' => []],
        'system_logs' => ['fks' => []],
        'connexions' => ['fks' => []],
    ];

    private ?PDO $pdo;
    private int $tenantId;
    private string $sqlFile;
    private string $stagingPrefix;
    /** @var array<string, array<int, int>> */
    private array $idMaps = [];
    /** @var list<string> */
    private array $log = [];
    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(?PDO $pdo, int $tenantId, string $sqlFile)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->sqlFile = $sqlFile;
        $this->stagingPrefix = '_imp_t' . $tenantId . '_';
    }

    /** @return list<string> */
    public function getLog(): array
    {
        return $this->log;
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Analyse le fichier SQL sans écrire en base.
     *
     * @return array<string, int> nombre d'INSERT par table
     */
    public function analyze(): array
    {
        $counts = [];
        foreach ($this->analyzeDetails() as $table => $detail) {
            $counts[$table] = $detail['inserts'];
        }
        return $counts;
    }

    /**
     * Analyse détaillée : requêtes INSERT, lignes par table, statuts patients/médecins.
     *
     * @return array<string, array{inserts: int, rows: int, actif: ?int, supprime: ?int}>
     */
    public function analyzeDetails(): array
    {
        $details = [];
        $this->foreachInsertStatement(function (string $table, string $stmt) use (&$details): void {
            if ($this->shouldSkipTable($table)) {
                return;
            }
            if (!isset($details[$table])) {
                $details[$table] = ['inserts' => 0, 'rows' => 0, 'actif' => null, 'supprime' => null];
            }
            $details[$table]['inserts']++;
            $details[$table]['rows'] += $this->countRowsInInsertStatement($stmt);

            if (in_array($table, ['patients', 'medecins'], true)) {
                $statut = $this->countStatutInInsertStatement($stmt);
                $details[$table]['actif'] = ($details[$table]['actif'] ?? 0) + $statut['actif'];
                $details[$table]['supprime'] = ($details[$table]['supprime'] ?? 0) + $statut['supprime'];
            }
        });
        ksort($details);
        return $details;
    }

    public function run(bool $dryRun = false): bool
    {
        if ($this->pdo === null) {
            $this->log[] = 'ERREUR : connexion base de données requise pour l\'import.';
            return false;
        }

        if (!$this->tenantExists()) {
            $this->log[] = 'ERREUR : tenant_id ' . $this->tenantId . ' introuvable.';
            return false;
        }

        $counts = $this->countInsertsByTable();
        if ($counts === []) {
            $this->log[] = 'ERREUR : aucun INSERT détecté dans le fichier SQL.';
            return false;
        }

        if ($dryRun) {
            foreach ($counts as $table => $n) {
                $this->log[] = "  [dry-run] {$table} : {$n} requête(s) INSERT";
            }
            $this->log[] = 'Dry-run terminé — aucune écriture.';
            return true;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::TABLE_PLAN as $table => $plan) {
                if (!isset($counts[$table]) || $this->shouldSkipTable($table)) {
                    continue;
                }
                if (!$this->tableExists($table)) {
                    $this->log[] = "  [skip] {$table} : table absente de la base cible";
                    continue;
                }
                $statements = $this->collectInsertsForTable($table);
                if ($statements === []) {
                    continue;
                }
                $this->importTable($table, $statements, $plan['fks']);
            }

            $this->log[] = 'Import terminé pour le tenant #' . $this->tenantId . ' (autres établissements non modifiés).';
        } finally {
            $this->dropAllStagingTables();
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return true;
    }

    /** @param list<string> $statements */
    private function importTable(string $table, array $statements, array $fks): void
    {
        $staging = $this->stagingPrefix . $table;
        $this->createStagingTable($table, $staging);
        $loaded = $this->loadStaging($table, $staging, $statements);
        if ($loaded === 0) {
            $this->dropStagingTable($staging);
            $this->log[] = "  [{$table}] aucune ligne chargée en staging";
            return;
        }

        $copied = $this->copyStagingToTenant($table, $staging, $fks);
        $this->stats[$table] = $copied;
        $this->log[] = "  [{$table}] {$copied} ligne(s) importée(s) (staging: {$loaded})";
        $this->dropStagingTable($staging);
    }

    private function createStagingTable(string $table, string $staging): void
    {
        $this->dropStagingTable($staging);
        $this->pdo->exec("CREATE TABLE `{$staging}` LIKE `{$table}`");
    }

    private function dropStagingTable(string $staging): void
    {
        if ($this->tableExists($staging)) {
            $this->pdo->exec("DROP TABLE `{$staging}`");
        }
    }

    private function dropAllStagingTables(): void
    {
        $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $this->stagingPrefix) . '%';
        $stmt = $this->pdo->query("SHOW TABLES LIKE '{$like}'");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $row[0]) . '`');
        }
    }

    /** @param list<string> $statements */
    private function loadStaging(string $table, string $staging, array $statements): int
    {
        $loaded = 0;
        foreach ($statements as $stmt) {
            $rewritten = $this->rewriteInsertToStaging($stmt, $table, $staging);
            try {
                $this->pdo->exec($rewritten);
                $loaded += max(1, (int) $this->pdo->query('SELECT ROW_COUNT()')->fetchColumn());
            } catch (PDOException $e) {
                $this->log[] = "  [warn] {$table} INSERT staging : " . $e->getMessage();
            }
        }
        if ($loaded === 0) {
            try {
                $loaded = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$staging}`")->fetchColumn();
            } catch (PDOException $e) {
                // ignore
            }
        }
        return $loaded;
    }

    private function copyStagingToTenant(string $table, string $staging, array $fks): int
    {
        if (!isset($this->idMaps[$table])) {
            $this->idMaps[$table] = [];
        }

        $rows = $this->pdo->query("SELECT * FROM `{$staging}` ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $hasTenantCol = $this->columnExists($table, 'tenant_id');
        $copied = 0;

        foreach ($rows as $row) {
            $oldId = isset($row['id']) ? (int) $row['id'] : 0;
            unset($row['id']);

            if ($hasTenantCol) {
                $row['tenant_id'] = $this->tenantId;
            }

            foreach ($fks as $col => $refTable) {
                if (!array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') {
                    continue;
                }
                $oldFk = (int) $row[$col];
                $row[$col] = $this->idMaps[$refTable][$oldFk] ?? null;
            }

            if ($table === 'parametres_systeme' && isset($row['cle'])) {
                if ($this->parametreExists((string) $row['cle'])) {
                    continue;
                }
            }

            if ($table === 'utilisateurs' && !empty($row['nom_utilisateur'])) {
                if ($this->utilisateurExists((string) $row['nom_utilisateur'])) {
                    $existingId = $this->findUtilisateurId((string) $row['nom_utilisateur']);
                    if ($oldId > 0 && $existingId > 0) {
                        $this->idMaps[$table][$oldId] = $existingId;
                    }
                    continue;
                }
            }

            try {
                $newId = $this->insertRow($table, $row);
                if ($oldId > 0) {
                    $this->idMaps[$table][$oldId] = $newId;
                }
                $copied++;
            } catch (PDOException $e) {
                $this->log[] = "  [warn] {$table} ligne old_id={$oldId} : " . $e->getMessage();
            }
        }

        return $copied;
    }

    /** @param array<string, mixed> $row */
    private function insertRow(string $table, array $row): int
    {
        $cols = array_keys($row);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (`'
            . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($row));
        return (int) $this->pdo->lastInsertId();
    }

    private function parametreExists(string $cle): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM parametres_systeme WHERE cle = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$cle, $this->tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function utilisateurExists(string $username): bool
    {
        return $this->findUtilisateurId($username) > 0;
    }

    private function findUtilisateurId(string $username): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$username, $this->tenantId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function tenantExists(): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$this->tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function shouldSkipTable(string $table): bool
    {
        if (in_array($table, self::SKIP_TABLES, true)) {
            return true;
        }
        if (strpos($table, '_imp_t') === 0) {
            return true;
        }
        return strncmp($table, 'v_', 2) === 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function foreachInsertStatement(callable $callback): void
    {
        if (!is_readable($this->sqlFile)) {
            throw new RuntimeException('Fichier SQL illisible : ' . $this->sqlFile);
        }

        $fh = fopen($this->sqlFile, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Impossible d\'ouvrir : ' . $this->sqlFile);
        }

        $buffer = '';
        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '/*') === 0) {
                continue;
            }
            $buffer .= $line;
            if (substr(rtrim($line), -1) === ';') {
                $stmt = trim($buffer);
                $buffer = '';
                if (preg_match('/^INSERT\s+(?:IGNORE\s+INTO|INTO)\s+[`"]?(\w+)[`"]?/i', $stmt, $m)) {
                    $callback(strtolower($m[1]), $stmt);
                }
            }
        }
        fclose($fh);
    }

    /** @return list<string> */
    private function collectInsertsForTable(string $table): array
    {
        $statements = [];
        $this->foreachInsertStatement(function (string $foundTable, string $stmt) use ($table, &$statements): void {
            if ($foundTable === $table) {
                $statements[] = $stmt;
            }
        });
        return $statements;
    }

    /**
     * @return array<string, int>
     */
    private function countInsertsByTable(): array
    {
        return $this->analyze();
    }

    private function countRowsInInsertStatement(string $stmt): int
    {
        if (!preg_match('/\bVALUES\s*(.+)$/is', $stmt, $m)) {
            return 0;
        }
        $values = rtrim(trim($m[1]), ';');

        $lineRows = 0;
        foreach (explode("\n", $values) as $line) {
            if (preg_match('/^\s*\(/', $line)) {
                $lineRows++;
            }
        }
        if ($lineRows > 0) {
            return $lineRows;
        }

        if ($values === '') {
            return 0;
        }

        return max(1, substr_count($values, '),(') + 1);
    }

    /**
     * @return array{actif: int, supprime: int}
     */
    private function countStatutInInsertStatement(string $stmt): array
    {
        $actif = 0;
        $supprime = 0;
        foreach (explode("\n", $stmt) as $line) {
            if (!preg_match('/^\s*\(/', $line)) {
                continue;
            }
            if (preg_match("/,\s*'supprime'\s*,\s*'\d{4}-\d{2}-\d{2}/", $line)) {
                $supprime++;
            } elseif (preg_match("/,\s*'actif'\s*,\s*'\d{4}-\d{2}-\d{2}/", $line)) {
                $actif++;
            }
        }
        return ['actif' => $actif, 'supprime' => $supprime];
    }

    private function rewriteInsertToStaging(string $stmt, string $table, string $staging): string
    {
        return (string) preg_replace(
            '/^(INSERT\s+(?:IGNORE\s+INTO|INTO))\s+[`"]?' . preg_quote($table, '/') . '[`"]?/i',
            '$1 `' . $staging . '`',
            $stmt,
            1
        );
    }
}
