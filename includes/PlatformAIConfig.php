<?php
/**
 * Configuration IA Mistral — globale plateforme (tenant_id IS NULL).
 * Réservée à l'administrateur principal ; partagée par tous les établissements.
 */
class PlatformAIConfig
{
    public const KEY_ACTIVE = 'ia_mistral_actif';
    public const KEY_API = 'ia_mistral_api_key';
    public const KEY_MODEL = 'ia_mistral_model';
    public const KEY_CONSULTATIONS = 'ia_mistral_consultations';
    public const KEY_LABORATOIRE = 'ia_mistral_laboratoire';
    public const KEY_TIMEOUT = 'ia_mistral_timeout';

    /** @var array<int, string> */
    private const KEYS = [
        self::KEY_ACTIVE,
        self::KEY_API,
        self::KEY_MODEL,
        self::KEY_CONSULTATIONS,
        self::KEY_LABORATOIRE,
        self::KEY_TIMEOUT,
    ];

    /** @var array<string, string> */
    private const DEFAULTS = [
        self::KEY_ACTIVE => '0',
        self::KEY_API => '',
        self::KEY_MODEL => 'mistral-small-latest',
        self::KEY_CONSULTATIONS => '1',
        self::KEY_LABORATOIRE => '1',
        self::KEY_TIMEOUT => '25',
    ];

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

        self::$cache = self::DEFAULTS;
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
                $cle = (string) ($row['cle'] ?? '');
                if ($cle !== '' && array_key_exists($cle, self::$cache)) {
                    self::$cache[$cle] = (string) ($row['valeur'] ?? '');
                }
            }
        } catch (Throwable $e) {
            error_log('PlatformAIConfig::load: ' . $e->getMessage());
        }

        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (!in_array($key, self::KEYS, true)) {
            return $default ?? '';
        }
        $values = self::load();
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        return $default ?? (self::DEFAULTS[$key] ?? '');
    }

    /** @return array<string, string> */
    public static function getAll(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function hasApiKey(): bool
    {
        return trim(self::get(self::KEY_API, '')) !== '';
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

    public static function save(string $key, string $value, string $description = ''): bool
    {
        if (!in_array($key, self::KEYS, true)) {
            return false;
        }

        self::assertPlatformAdmin();
        $pdo = self::pdo();
        if (!$pdo) {
            return false;
        }

        if ($description === '') {
            $description = 'Configuration IA Mistral (plateforme)';
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
            error_log('PlatformAIConfig::save: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string, string> $values
     */
    public static function saveMany(array $values): void
    {
        self::assertPlatformAdmin();
        foreach ($values as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                self::save($key, $value);
            }
        }
    }
}
