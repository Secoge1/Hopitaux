<?php
/**
 * Contexte tenant courant — accès centralisé au tenant_id de session.
 */

require_once __DIR__ . '/TenantSchema.php';

class TenantContext
{
    private static ?int $tenantId = null;
    private static ?array $tenantRow = null;

    public static function ensureSchema(): void
    {
        TenantSchema::ensure();
    }

    public static function bindFromSession(): void
    {
        self::$tenantId = null;
        self::$tenantRow = null;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['tenant_id'])) {
            self::$tenantId = (int) $_SESSION['tenant_id'];
        }
    }

    public static function getTenantId(): ?int
    {
        if (self::$tenantId === null) {
            self::bindFromSession();
        }
        return self::$tenantId;
    }

    public static function setTenantId(int $tenantId): void
    {
        self::$tenantId = $tenantId;
        self::$tenantRow = null;
        $_SESSION['tenant_id'] = $tenantId;
    }

    public static function getTenantRow(): ?array
    {
        if (self::$tenantRow !== null) {
            return self::$tenantRow;
        }
        $id = self::getTenantId();
        if (!$id) {
            return null;
        }
        self::ensureSchema();
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ?');
        $stmt->execute([$id]);
        self::$tenantRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return self::$tenantRow;
    }

    /**
     * Filtre SQL tenant_id pour requêtes métier.
     */
    public static function sqlFilter(string $alias = ''): string
    {
        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . 'tenant_id';
        return "{$col} = ?";
    }

    public static function sqlParam(): int
    {
        $id = self::getTenantId();
        return $id ?? 0;
    }

    /**
     * Vérifie qu'une ressource appartient au tenant courant.
     */
    public static function assertOwned(?array $row, string $redirectUrl = '/access_denied.php'): void
    {
        if (!$row) {
            header('Location: ' . $redirectUrl);
            exit;
        }
        $tenantId = self::getTenantId();
        if ($tenantId && isset($row['tenant_id']) && (int) $row['tenant_id'] !== $tenantId) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}
