<?php
/**
 * Trait commun — modèles PharmaPro ERP (TenantScope).
 */

trait PeModelTrait
{
    private function peScope(PDO $pdo, string $table, array &$where, array &$params, string $alias = ''): void
    {
        require_once __DIR__ . '/../../includes/saas/TenantScope.php';
        TenantScope::appendWhere($pdo, $table, $where, $params, $alias);
    }

    private function peTenantId(): ?int
    {
        require_once __DIR__ . '/../../includes/saas/TenantScope.php';
        return TenantScope::currentTenantId();
    }

    private function peAudit(string $action, string $entityType, ?int $entityId, ?array $payload = null): void
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return;
        }
        $userId = $_SESSION['user_id'] ?? null;
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_audit_log (tenant_id, user_id, action, entity_type, entity_id, ip_address, payload_json)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
