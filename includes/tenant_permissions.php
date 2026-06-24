<?php
/**
 * Permissions d'accès aux modules — personnalisables par établissement (tenant).
 */

require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/../config/db.php';

class TenantPermissions
{
    /** Modules que seul l'administrateur peut avoir ; verrouillés pour le rôle admin. */
    private const ADMIN_LOCKED_MODULES = ['parametres'];

    /** @var array<string, array<string, list<string>>> */
    private static array $cache = [];

    public static function ensureTables(): void
    {
        $pdo = getDB();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_role_modules (
                id INT NOT NULL AUTO_INCREMENT,
                tenant_id INT NOT NULL,
                role VARCHAR(32) NOT NULL,
                module_key VARCHAR(64) NOT NULL,
                granted TINYINT(1) NOT NULL DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_tenant_role_module (tenant_id, role, module_key),
                KEY idx_tenant_role (tenant_id, role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /** @return list<string> */
    public static function configurableModuleKeys(): array
    {
        return array_keys(MODULE_ROLES);
    }

    /** @return array<string, list<string>> */
    public static function moduleGroups(): array
    {
        return [
            'Soins' => ['patients', 'medecins', 'rdv', 'consultations', 'laboratoire', 'dossiers'],
            'Administration' => ['paiements', 'personnel', 'finances', 'assurances'],
            'Logistique' => ['pharmacie', 'maintenance'],
            'Communication' => ['communication'],
            'Système' => ['parametres'],
        ];
    }

    public static function hasTenantOverrides(int $tenantId, string $role): bool
    {
        if ($tenantId < 1 || !app_role_is_valid($role)) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM tenant_role_modules WHERE tenant_id = ? AND role = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $role]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<string> */
    public static function getModulesForRole(int $tenantId, string $role): array
    {
        if (!app_role_is_valid($role)) {
            return [];
        }

        $cacheKey = $tenantId . ':' . $role;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        if ($tenantId < 1 || !self::hasTenantOverrides($tenantId, $role)) {
            $modules = app_modules_for_role_default($role);
            self::$cache[$cacheKey] = $modules;
            return $modules;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT module_key FROM tenant_role_modules
             WHERE tenant_id = ? AND role = ? AND granted = 1
             ORDER BY module_key'
        );
        $stmt->execute([$tenantId, $role]);
        $modules = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $modules = self::enforceAdminLocks($role, $modules);
        self::$cache[$cacheKey] = $modules;
        return $modules;
    }

    public static function roleHasModule(int $tenantId, string $role, string $moduleKey): bool
    {
        return in_array($moduleKey, self::getModulesForRole($tenantId, $role), true);
    }

    /**
     * Matrice complète pour l'UI : role => module => bool
     *
     * @return array<string, array<string, bool>>
     */
    public static function getMatrixForTenant(int $tenantId): array
    {
        $matrix = [];
        foreach (app_role_keys() as $role) {
            $granted = array_fill_keys(self::configurableModuleKeys(), false);
            foreach (self::getModulesForRole($tenantId, $role) as $mod) {
                $granted[$mod] = true;
            }
            $matrix[$role] = $granted;
        }
        return $matrix;
    }

    /**
     * @param list<string> $moduleKeys
     */
    public static function saveRoleModules(int $tenantId, string $role, array $moduleKeys): bool
    {
        if ($tenantId < 1 || !app_role_is_valid($role)) {
            return false;
        }

        self::ensureTables();

        $allowed = array_flip(self::configurableModuleKeys());
        $moduleKeys = array_values(array_unique(array_filter(
            $moduleKeys,
            static fn ($k) => is_string($k) && isset($allowed[$k])
        )));

        $moduleKeys = self::enforceAdminLocks($role, $moduleKeys);

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM tenant_role_modules WHERE tenant_id = ? AND role = ?');
            $del->execute([$tenantId, $role]);

            $ins = $pdo->prepare(
                'INSERT INTO tenant_role_modules (tenant_id, role, module_key, granted) VALUES (?, ?, ?, ?)'
            );
            foreach (self::configurableModuleKeys() as $mod) {
                $granted = in_array($mod, $moduleKeys, true) ? 1 : 0;
                $ins->execute([$tenantId, $role, $mod, $granted]);
            }

            $pdo->commit();
            self::clearCache($tenantId, $role);
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('TenantPermissions::saveRoleModules: ' . $e->getMessage());
            return false;
        }
    }

    public static function resetRoleToDefaults(int $tenantId, string $role): bool
    {
        if ($tenantId < 1 || !app_role_is_valid($role)) {
            return false;
        }
        self::ensureTables();
        $pdo = getDB();
        $stmt = $pdo->prepare('DELETE FROM tenant_role_modules WHERE tenant_id = ? AND role = ?');
        $ok = $stmt->execute([$tenantId, $role]);
        self::clearCache($tenantId, $role);
        return $ok;
    }

    public static function resetTenantToDefaults(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        self::ensureTables();
        $pdo = getDB();
        $stmt = $pdo->prepare('DELETE FROM tenant_role_modules WHERE tenant_id = ?');
        $ok = $stmt->execute([$tenantId]);
        self::clearCache($tenantId);
        return $ok;
    }

    public static function tenantHasCustomizations(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        self::ensureTables();
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT 1 FROM tenant_role_modules WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    private static function enforceAdminLocks(string $role, array $modules): array
    {
        if ($role !== 'admin') {
            return array_values(array_filter(
                $modules,
                static fn ($m) => !in_array($m, self::ADMIN_LOCKED_MODULES, true)
            ));
        }
        foreach (self::ADMIN_LOCKED_MODULES as $locked) {
            if (!in_array($locked, $modules, true)) {
                $modules[] = $locked;
            }
        }
        return array_values(array_unique($modules));
    }

    private static function clearCache(int $tenantId, ?string $role = null): void
    {
        if ($role !== null) {
            unset(self::$cache[$tenantId . ':' . $role]);
            return;
        }
        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, $tenantId . ':')) {
                unset(self::$cache[$key]);
            }
        }
    }
}

if (!function_exists('app_modules_for_role_default')) {
    /** Matrice globale par défaut (sans personnalisation tenant). */
    function app_modules_for_role_default(string $role): array
    {
        $modules = [];
        foreach (MODULE_ROLES as $key => $roles) {
            if (in_array($role, $roles, true)) {
                $modules[] = $key;
            }
        }
        return $modules;
    }
}
