<?php
/**
 * Journalisation des opérations paiements / sync comptable.
 */

class PaymentAudit
{
    public static function log(string $action, array $context = []): void
    {
        if (!function_exists('logAction')) {
            return;
        }

        $payload = array_merge([
            'module' => 'paiements',
            'ts' => date('c'),
        ], $context);

        $details = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($details === false) {
            $details = $action;
        }

        logAction($action, $details);
    }
}
