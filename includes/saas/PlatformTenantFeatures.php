<?php
/**
 * Fonctionnalités activables par l'admin plateforme, établissement par établissement.
 * Toute évolution majeure doit être enregistrée ici et consommée via tenant_feature_enabled().
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/TenantContext.php';

class PlatformTenantFeatures
{
    /** Fonctionnalités opérationnelles (code métier branché). */
    public const PAYMENT_FINANCE_SYNC = 'payment_finance_sync';

    /** Roadmap — activation admin sans effet tant que le code n'est pas branché. */
    public const EXECUTIVE_DASHBOARD = 'executive_dashboard';
    public const EMR_CENTRALIZED = 'emr_centralized';
    public const PDF_WHATSAPP_SHARE = 'pdf_whatsapp_share';
    public const PATIENT_PORTAL = 'patient_portal';
    public const INSURANCE_TPA = 'insurance_tpa';
    public const PHARMACY_ADVANCED = 'pharmacy_advanced';
    public const NOTIFICATION_CENTER = 'notification_center';
    public const REST_API_V2 = 'rest_api_v2';
    public const AI_MEDICAL_SUITE = 'ai_medical_suite';

    /** live = code branché | beta = partiel | planned = registre uniquement */
    private const STATUSES = [
        self::PAYMENT_FINANCE_SYNC => 'live',
        self::EXECUTIVE_DASHBOARD => 'planned',
        self::EMR_CENTRALIZED => 'planned',
        self::PDF_WHATSAPP_SHARE => 'planned',
        self::PATIENT_PORTAL => 'planned',
        self::INSURANCE_TPA => 'planned',
        self::PHARMACY_ADVANCED => 'planned',
        self::NOTIFICATION_CENTER => 'planned',
        self::REST_API_V2 => 'beta',
        self::AI_MEDICAL_SUITE => 'beta',
    ];

    /** @var array<string, string> */
    private static array $labels = [
        self::PAYMENT_FINANCE_SYNC => 'Synchronisation Paiements · Finances · Analyses',
        self::EXECUTIVE_DASHBOARD => 'Tableau de bord exécutif intelligent',
        self::EMR_CENTRALIZED => 'Dossier médical électronique centralisé',
        self::PDF_WHATSAPP_SHARE => 'Partage PDF & WhatsApp',
        self::PATIENT_PORTAL => 'Portail patient sécurisé',
        self::INSURANCE_TPA => 'Assurances & mutuelles (tiers payant)',
        self::PHARMACY_ADVANCED => 'Pharmacie avancée (lots, inventaires)',
        self::NOTIFICATION_CENTER => 'Centre de notifications unifié',
        self::REST_API_V2 => 'API REST mobile (v2 étendue)',
        self::AI_MEDICAL_SUITE => 'Suite IA médicale (Mistral)',
    ];

    /** @var array<string, string> */
    private static array $descriptions = [
        self::PAYMENT_FINANCE_SYNC => 'Lie consultations et analyses aux paiements, écritures comptables automatiques et encaissement unifié.',
        self::EXECUTIVE_DASHBOARD => 'KPIs financiers, graphiques interactifs, top services et médecins, évolution CA/consultations.',
        self::EMR_CENTRALIZED => 'Vue patient unique : consultations, ordonnances, labo, hospitalisations, allergies, antécédents, paiements.',
        self::PDF_WHATSAPP_SHARE => 'Boutons PDF, impression et partage WhatsApp sur ordonnances, résultats, factures, certificats.',
        self::PATIENT_PORTAL => 'Espace patient : historique, téléchargements, RDV, paiements.',
        self::INSURANCE_TPA => 'CANAM, AMO, mutuelles : taux, plafonds, reste à charge, créances et remboursements.',
        self::PHARMACY_ADVANCED => 'Lots, péremption, alertes rupture, inventaires, valorisation stock, mouvements.',
        self::NOTIFICATION_CENTER => 'Alertes RDV, résultats labo, stocks, factures impayées, créances assurance.',
        self::REST_API_V2 => 'API REST étendue pour apps médecin, patient, caisse et laboratoire.',
        self::AI_MEDICAL_SUITE => 'Suggestions diagnostics, enrichissement analyses, résumés consultation (sans diagnostic auto).',
    ];

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_keys(self::$labels);
    }

    /** @return array<string, array{key: string, label: string, description: string, status: string}> */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::$labels as $key => $label) {
            $out[$key] = [
                'key' => $key,
                'label' => $label,
                'description' => self::$descriptions[$key] ?? '',
                'status' => self::featureStatus($key),
            ];
        }
        return $out;
    }

    public static function featureStatus(string $key): string
    {
        return self::STATUSES[$key] ?? 'planned';
    }

    public static function isLiveFeature(string $key): bool
    {
        return self::featureStatus($key) === 'live';
    }

    /** @return array<string, string> */
    public static function featureLabels(): array
    {
        return self::$labels;
    }

    public static function featureDescription(string $key): string
    {
        return self::$descriptions[$key] ?? '';
    }

    public static function ensureTable(): void
    {
        $pdo = getDB();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS platform_tenant_features (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                feature_key VARCHAR(64) NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                enabled_at DATETIME DEFAULT NULL,
                enabled_by INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_tenant_feature (tenant_id, feature_key),
                KEY idx_feature (feature_key),
                KEY idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function isEnabled(string $featureKey, ?int $tenantId = null): bool
    {
        return self::getFeatureRow($featureKey, $tenantId) !== null;
    }

    public static function getEnabledStamp(string $featureKey, ?int $tenantId = null): ?string
    {
        $row = self::getFeatureRow($featureKey, $tenantId);
        if ($row === null) {
            return null;
        }

        $tenantId = (int) $row['tenant_id'];
        $marker = $row['enabled_at'] ?? $row['updated_at'] ?? '1';

        return $tenantId . '_' . $featureKey . '_' . $marker;
    }

    /** @return array{tenant_id: int, enabled_at: ?string, updated_at: ?string}|null */
    private static function getFeatureRow(string $featureKey, ?int $tenantId): ?array
    {
        self::ensureTable();

        if ($tenantId === null) {
            TenantContext::bindFromSession();
            $tenantId = TenantContext::getTenantId();
        }
        if (!$tenantId) {
            return null;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT tenant_id, enabled, enabled_at, updated_at
             FROM platform_tenant_features
             WHERE tenant_id = ? AND feature_key = ? AND enabled = 1
             LIMIT 1'
        );
        $stmt->execute([(int) $tenantId, $featureKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function setEnabled(int $tenantId, string $featureKey, bool $enabled, ?int $enabledBy = null, ?string $notes = null): bool
    {
        if (!isset(self::$labels[$featureKey])) {
            return false;
        }

        self::ensureTable();

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO platform_tenant_features (tenant_id, feature_key, enabled, enabled_at, enabled_by, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                enabled_at = VALUES(enabled_at),
                enabled_by = VALUES(enabled_by),
                notes = COALESCE(VALUES(notes), notes)
        ");

        return $stmt->execute([
            $tenantId,
            $featureKey,
            $enabled ? 1 : 0,
            $enabled ? date('Y-m-d H:i:s') : null,
            $enabled ? $enabledBy : null,
            $notes,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listTenantsStatus(string $featureKey): array
    {
        self::ensureTable();
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT t.id, t.company_name, t.tenant_key, t.status,
                   COALESCE(f.enabled, 0) AS feature_enabled,
                   f.enabled_at, f.notes
            FROM tenants t
            LEFT JOIN platform_tenant_features f
                ON f.tenant_id = t.id AND f.feature_key = ?
            ORDER BY t.company_name ASC
        ");
        $stmt->execute([$featureKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countEnabled(string $featureKey): int
    {
        self::ensureTable();
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM platform_tenant_features WHERE feature_key = ? AND enabled = 1'
        );
        $stmt->execute([$featureKey]);
        return (int) $stmt->fetchColumn();
    }
}
