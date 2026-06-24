<?php
/**
 * Filtrage tenant_id réutilisable pour les modèles métier.
 */

require_once __DIR__ . '/TenantContext.php';

class TenantScope
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function tableHasTenantColumn(PDO $pdo, string $table): bool
    {
        if (isset(self::$columnCache[$table])) {
            return self::$columnCache[$table];
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = \'tenant_id\''
        );
        $stmt->execute([$table]);
        self::$columnCache[$table] = (bool) $stmt->fetchColumn();
        return self::$columnCache[$table];
    }

    public static function currentTenantId(): ?int
    {
        TenantContext::bindFromSession();
        return TenantContext::getTenantId();
    }

    /**
     * Utilisateur connecté (hors admin plateforme) : tenant_id obligatoire.
     */
    private static function shouldEnforceTenant(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['is_platform_admin'])) {
            return false;
        }
        return !empty($_SESSION['user_connected']);
    }

    private static function denyAll(array &$where): void
    {
        $where[] = '1 = 0';
    }

    /**
     * Ajoute « alias.tenant_id = ? » aux conditions WHERE.
     */
    public static function appendWhere(PDO $pdo, string $table, array &$where, array &$params, string $alias = ''): void
    {
        if (!self::tableHasTenantColumn($pdo, $table)) {
            return;
        }
        $tenantId = self::currentTenantId();
        if (!$tenantId) {
            if (self::shouldEnforceTenant()) {
                self::denyAll($where);
            }
            return;
        }
        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . 'tenant_id';
        $where[] = "{$col} = ?";
        $params[] = $tenantId;
    }

    /**
     * Préfixe SQL « WHERE … » avec filtre tenant (sans paramètres de recherche).
     */
    public static function baseWhere(PDO $pdo, string $table, string $alias = ''): array
    {
        $where = [];
        $params = [];
        self::appendWhere($pdo, $table, $where, $params, $alias);
        return [$where, $params];
    }

    /**
     * Enrichit un INSERT dynamique avec tenant_id si la colonne existe.
     *
     * @param list<string> $columns
     * @param list<string> $placeholders
     * @param list<mixed>  $values
     */
    public static function bindInsert(PDO $pdo, string $table, array &$columns, array &$placeholders, array &$values): void
    {
        if (!self::tableHasTenantColumn($pdo, $table)) {
            return;
        }
        $tenantId = self::currentTenantId();
        if (!$tenantId) {
            if (self::shouldEnforceTenant()) {
                throw new RuntimeException('Contexte tenant manquant pour cette opération.');
            }
            return;
        }
        $columns[] = 'tenant_id';
        $placeholders[] = '?';
        $values[] = $tenantId;
    }

    public static function assertAccessible(?array $row): void
    {
        TenantContext::assertOwned($row);
    }

    /**
     * Clause AND tenant pour requêtes « WHERE id = ? » (alias optionnel si JOIN).
     */
    public static function andOwnedByTenant(PDO $pdo, string $table, string $alias = ''): string
    {
        if (!self::tableHasTenantColumn($pdo, $table)) {
            return '';
        }
        $tenantId = self::currentTenantId();
        if (!$tenantId) {
            return self::shouldEnforceTenant() ? ' AND 1 = 0' : '';
        }
        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . 'tenant_id';
        return " AND {$col} = ?";
    }

    public static function ownedParam(PDO $pdo, string $table): array
    {
        if (!self::tableHasTenantColumn($pdo, $table)) {
            return [];
        }
        $tenantId = self::currentTenantId();
        if ($tenantId) {
            return [$tenantId];
        }
        return self::shouldEnforceTenant() ? [] : [];
    }

    /** Paramètres pour « WHERE id = ? » (+ tenant si applicable). */
    public static function paramsForId(PDO $pdo, string $table, int $id): array
    {
        return array_merge([$id], self::ownedParam($pdo, $table));
    }

    /** Paramètres pour UPDATE/DELETE : valeurs finissant par id (+ tenant). */
    public static function appendOwned(PDO $pdo, string $table, array $valuesEndingWithId): array
    {
        return array_merge($valuesEndingWithId, self::ownedParam($pdo, $table));
    }

    /**
     * COUNT(*) avec filtre tenant automatique.
     *
     * @param list<string> $where
     * @param list<mixed>  $params
     */
    public static function count(PDO $pdo, string $table, array $where = [], array $params = [], string $alias = ''): int
    {
        self::appendWhere($pdo, $table, $where, $params, $alias);
        $from = $alias !== '' ? $table . ' AS ' . rtrim($alias, '.') : $table;
        $sql = 'SELECT COUNT(*) FROM ' . $from;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Requête agrégée avec filtre tenant sur une table principale.
     *
     * @param list<string> $where
     * @param list<mixed>  $params
     */
    public static function aggregate(PDO $pdo, string $table, string $selectSql, array $where = [], array $params = [], string $alias = ''): array
    {
        self::appendWhere($pdo, $table, $where, $params, $alias);
        $from = $alias !== '' ? $table . ' AS ' . rtrim($alias, '.') : $table;
        $sql = 'SELECT ' . $selectSql . ' FROM ' . $from;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : [];
    }

    /**
     * DELETE avec filtre tenant automatique.
     *
     * @param list<string> $where
     * @param list<mixed>  $params
     */
    public static function deleteWhere(PDO $pdo, string $table, array $where, array $params, string $alias = ''): int
    {
        self::appendWhere($pdo, $table, $where, $params, $alias);
        $from = $alias !== '' ? '`' . $table . '` AS ' . rtrim($alias, '.') : '`' . $table . '`';
        $sql = 'DELETE FROM ' . $from . ' WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * UPDATE avec filtre tenant en fin de clause WHERE.
     */
    public static function updateWhere(PDO $pdo, string $table, string $setSql, array $where, array $params, string $alias = ''): bool
    {
        self::appendWhere($pdo, $table, $where, $params, $alias);
        $from = $alias !== '' ? '`' . $table . '` AS ' . rtrim($alias, '.') : '`' . $table . '`';
        $sql = 'UPDATE ' . $from . ' SET ' . $setSql . ' WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
