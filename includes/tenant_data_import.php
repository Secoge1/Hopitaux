<?php
/**
 * Import de données SQL depuis l'interface Paramètres (par tenant).
 */
declare(strict_types=1);

require_once __DIR__ . '/saas/TenantSqlImporter.php';
require_once __DIR__ . '/medecin_profil.php';

class TenantDataImportWeb
{
    private const SESSION_KEY = 'tenant_import_pending';
    private const MAX_BYTES = 52428800; // 50 Mo

    public static function maxUploadLabel(): string
    {
        return '50 Mo';
    }

    public static function uploadBaseDir(): string
    {
        return dirname(__DIR__) . '/backups/tenant_imports';
    }

    public static function tenantUploadDir(int $tenantId): string
    {
        return self::uploadBaseDir() . '/t' . $tenantId;
    }

    public static function ensureTenantUploadDir(int $tenantId): string
    {
        $dir = self::tenantUploadDir($tenantId);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $htaccess = self::uploadBaseDir() . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }
        $index = $dir . '/index.html';
        if (!is_file($index)) {
            file_put_contents($index, '');
        }
        return $dir;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{success: bool, message: string, path?: string, original?: string}
     */
    public static function storeUpload(int $tenantId, array $file): array
    {
        if ($tenantId < 1) {
            return ['success' => false, 'message' => 'Établissement invalide.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return ['success' => false, 'message' => self::uploadErrorMessage($code)];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            return ['success' => false, 'message' => 'Fichier vide.'];
        }
        if ($size > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'Fichier trop volumineux (max ' . self::maxUploadLabel() . ').'];
        }

        $original = (string) ($file['name'] ?? 'import.sql');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            return ['success' => false, 'message' => 'Seuls les fichiers .sql sont acceptés.'];
        }

        $dir = self::ensureTenantUploadDir($tenantId);
        $safeName = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $dest = $dir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Impossible d\'enregistrer le fichier uploadé.'];
        }

        return [
            'success' => true,
            'message' => 'Fichier reçu.',
            'path' => $dest,
            'original' => $original,
        ];
    }

    public static function assertPathOwnedByTenant(int $tenantId, string $path): bool
    {
        $realFile = realpath($path);
        $realDir = realpath(self::tenantUploadDir($tenantId));
        if ($realFile === false || $realDir === false || !is_file($realFile)) {
            return false;
        }
        $normFile = str_replace('\\', '/', $realFile);
        $normDir = rtrim(str_replace('\\', '/', $realDir), '/') . '/';
        return strpos($normFile, $normDir) === 0;
    }

    /**
     * @param array<string, int> $counts
     * @param ?array<string, array{inserts: int, rows: int, actif: ?int, supprime: ?int}> $details
     */
    public static function savePending(int $tenantId, string $path, string $original, array $counts, ?array $details = null): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'tenant_id' => $tenantId,
            'path' => $path,
            'original' => $original,
            'counts' => $counts,
            'details' => $details,
            'at' => time(),
        ];
    }

    /** @return ?array{tenant_id: int, path: string, original: string, counts: array<string, int>, details: ?array<string, array{inserts: int, rows: int, actif: ?int, supprime: ?int}>, at: int} */
    public static function getPending(int $tenantId): ?array
    {
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($data) || (int) ($data['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }
        if ((time() - (int) ($data['at'] ?? 0)) > 3600) {
            self::clearPending($tenantId);
            return null;
        }
        if (!self::assertPathOwnedByTenant($tenantId, (string) ($data['path'] ?? ''))) {
            self::clearPending($tenantId);
            return null;
        }
        return $data;
    }

    public static function clearPending(int $tenantId): void
    {
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($data) && (int) ($data['tenant_id'] ?? 0) === $tenantId) {
            $path = (string) ($data['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * @return array{success: bool, message: string, stats?: array<string, int>, log?: list<string>}
     */
    public static function runImport(int $tenantId, string $path): array
    {
        if (!self::assertPathOwnedByTenant($tenantId, $path)) {
            return ['success' => false, 'message' => 'Fichier d\'import invalide ou expiré.'];
        }

        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/saas/TenantSchema.php';

        TenantSchema::ensure();
        $pdo = getDB();

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        try {
            $importer = new TenantSqlImporter($pdo, $tenantId, $path);
            $ok = $importer->run(false);
            $log = $importer->getLog();
            $stats = $importer->getStats();

            if (!$ok) {
                return [
                    'success' => false,
                    'message' => 'Import échoué. Consultez le détail ci-dessous.',
                    'log' => $log,
                    'stats' => $stats,
                ];
            }

            $backfill = self::backfillMedecinTypeProfilForTenant($pdo, $tenantId);

            return [
                'success' => true,
                'message' => 'Import terminé avec succès.',
                'log' => array_merge($log, ['Profils médecins : ' . $backfill . ' fiche(s) mise(s) à jour.']),
                'stats' => $stats,
            ];
        } catch (Throwable $e) {
            error_log('TenantDataImportWeb: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function backfillMedecinTypeProfilForTenant(PDO $pdo, int $tenantId): int
    {
        if (!self::columnExists($pdo, 'medecins', 'type_profil')) {
            return 0;
        }

        $hasUserCol = self::columnExists($pdo, 'medecins', 'utilisateur_id');
        $sql = 'SELECT m.id, m.specialite, m.type_profil';
        if ($hasUserCol) {
            $sql .= ', u.role AS user_role';
        }
        $sql .= ' FROM medecins m';
        if ($hasUserCol) {
            $sql .= ' LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id AND u.tenant_id = m.tenant_id';
        }
        $sql .= " WHERE m.tenant_id = ? AND (m.statut IS NULL OR m.statut <> 'supprime')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE medecins SET type_profil = ? WHERE id = ? AND tenant_id = ?');
        $updated = 0;

        foreach ($rows as $row) {
            $current = strtolower((string) ($row['type_profil'] ?? 'medecin'));
            if ($current === '') {
                $current = 'medecin';
            }
            $inferred = null;
            if (!empty($row['user_role']) && medecin_profil_is_valid((string) $row['user_role'])) {
                $inferred = (string) $row['user_role'];
            }
            if ($inferred === null && !empty($row['specialite'])) {
                $inferred = self::inferTypeFromText((string) $row['specialite']);
            }
            if ($inferred === null || !medecin_profil_is_valid($inferred) || $inferred === $current) {
                continue;
            }
            $update->execute([$inferred, (int) $row['id'], $tenantId]);
            $updated++;
        }

        return $updated;
    }

    private static function inferTypeFromText(string $text): ?string
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return null;
        }
        $rules = [
            'sage_femme' => ['sage-femme', 'sage femme', 'maïeut'],
            'infirmier'  => ['infirmier', 'infirmière', 'infirmiere', 'ide'],
            'laborantin' => ['laborantin', 'laborantine', 'biologiste'],
            'pharmacien' => ['pharmacien', 'pharmacienne'],
            'technicien' => ['technicien', 'technicienne'],
        ];
        foreach ($rules as $type => $needles) {
            foreach ($needles as $needle) {
                if (strpos($text, $needle) !== false) {
                    return $type;
                }
            }
        }
        return null;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private static function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Fichier trop volumineux pour le serveur.';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incomplet — réessayez.';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier sélectionné.';
            default:
                return 'Erreur lors de l\'upload (code ' . $code . ').';
        }
    }
}
