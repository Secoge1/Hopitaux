<?php
/**
 * Marque plateforme (nom + logo) — réservée à l'administrateur principal.
 * Stockage global : parametres_systeme.tenant_id IS NULL.
 */

class PlatformBranding
{
    private const KEYS = ['platform_name', 'platform_logo'];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    private static function pdo(): ?PDO
    {
        if (!function_exists('getDBSoft')) {
            require_once dirname(__DIR__) . '/config/db.php';
        }
        return function_exists('getDBSoft') ? getDBSoft() : null;
    }

    private static function hasTenantColumn(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parametres_systeme' AND COLUMN_NAME = 'tenant_id'"
            );
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /** @return array<string, string> */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        $pdo = self::pdo();
        if (!$pdo) {
            return self::$cache;
        }

        try {
            $placeholders = implode(',', array_fill(0, count(self::KEYS), '?'));
            if (self::hasTenantColumn($pdo)) {
                $stmt = $pdo->prepare(
                    "SELECT cle, valeur FROM parametres_systeme
                     WHERE tenant_id IS NULL AND cle IN ($placeholders)"
                );
            } else {
                $stmt = $pdo->prepare(
                    "SELECT cle, valeur FROM parametres_systeme WHERE cle IN ($placeholders)"
                );
            }
            $stmt->execute(self::KEYS);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['cle']] = (string) ($row['valeur'] ?? '');
            }
        } catch (Throwable $e) {
            error_log('PlatformBranding::load: ' . $e->getMessage());
        }

        return self::$cache;
    }

    public static function getName(): string
    {
        $value = trim(self::load()['platform_name'] ?? '');
        if ($value !== '') {
            return $value;
        }
        return defined('PLATFORM_NAME') ? (string) PLATFORM_NAME : 'Se.Santé';
    }

    public static function getLogoPath(): string
    {
        $value = trim(self::load()['platform_logo'] ?? '');
        if ($value !== '' && self::fileExists($value)) {
            return $value;
        }
        return defined('PLATFORM_LOGO') ? (string) PLATFORM_LOGO : 'assets/images/brand/sesante-logo.png';
    }

    public static function getCustomLogoPath(): ?string
    {
        $value = trim(self::load()['platform_logo'] ?? '');
        return $value !== '' ? $value : null;
    }

    public static function fileExists(string $relativePath): bool
    {
        $root = dirname(__DIR__);
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        return is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    }

    private static function assertPlatformAdmin(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!function_exists('saas_is_platform_admin')) {
            require_once __DIR__ . '/saas/saas_helpers.php';
        }
        if (!saas_is_platform_admin()) {
            throw new RuntimeException('Accès réservé à l\'administrateur principal de la plateforme.');
        }
    }

    private static function save(string $key, string $value, string $description): bool
    {
        if (!in_array($key, self::KEYS, true)) {
            return false;
        }

        self::assertPlatformAdmin();
        $pdo = self::pdo();
        if (!$pdo) {
            return false;
        }

        try {
            if (self::hasTenantColumn($pdo)) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM parametres_systeme WHERE cle = ? AND tenant_id IS NULL LIMIT 1"
                );
                $stmt->execute([$key]);
                $id = $stmt->fetchColumn();
                if ($id) {
                    $upd = $pdo->prepare(
                        'UPDATE parametres_systeme SET valeur = ?, description = ? WHERE id = ?'
                    );
                    $upd->execute([$value, $description, $id]);
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO parametres_systeme (cle, valeur, description, tenant_id) VALUES (?, ?, ?, NULL)'
                    );
                    $ins->execute([$key, $value, $description]);
                }
            } else {
                $sql = "INSERT INTO parametres_systeme (cle, valeur, description)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE valeur = ?, description = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value, $description, $value, $description]);
            }

            self::resetCache();
            return true;
        } catch (Throwable $e) {
            error_log('PlatformBranding::save: ' . $e->getMessage());
            return false;
        }
    }

    public static function updateName(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Le nom de la plateforme est obligatoire.');
        }
        $nameLen = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($nameLen > 120) {
            throw new RuntimeException('Le nom ne peut pas dépasser 120 caractères.');
        }
        return self::save('platform_name', $name, 'Nom de la plateforme (site public)');
    }

    /**
     * @param array<string, mixed> $file
     * @return array{success: bool, message: string, path?: string}
     */
    public static function uploadLogo(array $file): array
    {
        try {
            self::assertPlatformAdmin();
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier.'];
        }

        $allowedTypes = ['image/jpeg' => 'JPEG', 'image/jpg' => 'JPEG', 'image/png' => 'PNG'];
        if (!in_array($file['type'] ?? '', array_keys($allowedTypes), true)) {
            return ['success' => false, 'message' => 'Format non autorisé. Utilisez JPG ou PNG.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detectedMime, array_keys($allowedTypes), true)) {
            return ['success' => false, 'message' => 'Type de fichier détecté non autorisé.'];
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Fichier trop volumineux (max 2 Mo).'];
        }

        $logoDir = dirname(__DIR__) . '/uploads/platform/';
        if (!is_dir($logoDir)) {
            mkdir($logoDir, 0755, true);
        }

        $custom = self::getCustomLogoPath();
        if ($custom && self::fileExists($custom)) {
            $abs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $custom), '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'platform_logo_' . date('Y-m-d_H-i-s') . '.' . $extension;
        $absolutePath = $logoDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.'];
        }

        $storedPath = 'uploads/platform/' . $filename;
        if (!self::save('platform_logo', $storedPath, 'Logo de la plateforme (site public)')) {
            @unlink($absolutePath);
            return ['success' => false, 'message' => 'Impossible d\'enregistrer le logo en base.'];
        }

        $type = $allowedTypes[$detectedMime];
        return [
            'success' => true,
            'message' => "Logo $type mis à jour avec succès.",
            'path' => $storedPath,
        ];
    }

    public static function removeLogo(): bool
    {
        self::assertPlatformAdmin();
        $custom = self::getCustomLogoPath();
        if ($custom && self::fileExists($custom)) {
            $abs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $custom), '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        return self::save('platform_logo', '', 'Logo de la plateforme (site public)');
    }
}
