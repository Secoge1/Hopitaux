<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tenant_permissions.php';
require_once __DIR__ . '/config/SystemLogs.php';

try {
    echo "Starting saveRoleModules...\n";
    $tenantId = 1;
    $role = 'secretaire';
    $modules = ['patients', 'rdv'];
    
    $start = microtime(true);
    $res = TenantPermissions::saveRoleModules($tenantId, $role, $modules);
    $end = microtime(true);
    
    echo "Result: " . var_export($res, true) . "\n";
    echo "Time taken: " . ($end - $start) . " seconds\n";
    
    echo "Adding SystemLogs...\n";
    $start = microtime(true);
    (new SystemLogs())->addLog('permissions_update', 'Mise à jour droits module — rôle ' . $role, 1);
    $end = microtime(true);
    echo "Time taken for SystemLogs: " . ($end - $start) . " seconds\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
