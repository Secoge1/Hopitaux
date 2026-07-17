<?php
/**
 * Plans de licence Efficasante SaaS — trois formules commerciales.
 * - Abonnement Essentiel : 70 000 FCFA / an — 5 utilisateurs
 * - Abonnement Pro       : 100 000 FCFA / an — 15 utilisateurs
 * - Licence à vie        : 550 000 FCFA (paiement unique)
 */

class SubscriptionPlan
{
    public const STARTER = 'starter';
    public const ANNUAL = 'annual';
    public const LIFETIME = 'lifetime';

    /** @var array<string, array> */
    private static array $plans = [
        self::STARTER => [
            'slug' => self::STARTER,
            'name' => 'Abonnement Essentiel',
            'name_full' => 'Abonnement Essentiel Se.Santé',
            'tagline' => 'Pour les petites structures — jusqu\'à 5 utilisateurs',
            'price_xof' => 70000,
            'renewal_price_xof' => 70000,
            'billing_type' => 'annual',
            'max_users' => 5,
            'popular' => false,
            'cta' => 'Souscrire',
            'modules' => ['*'],
        ],
        self::ANNUAL => [
            'slug' => self::ANNUAL,
            'name' => 'Abonnement Pro',
            'name_full' => 'Abonnement Pro Se.Santé',
            'tagline' => 'Idéal pour grandir — jusqu\'à 15 utilisateurs',
            'price_xof' => 100000,
            'renewal_price_xof' => 100000,
            'billing_type' => 'annual',
            'max_users' => 15,
            'popular' => true,
            'popular_badge' => 'LE PLUS POPULAIRE',
            'cta' => 'Souscrire',
            'modules' => ['*'],
        ],
        self::LIFETIME => [
            'slug' => self::LIFETIME,
            'name' => 'Licence à vie',
            'name_full' => 'Licence à vie Se.Santé',
            'tagline' => 'Paiement unique — accès permanent sans renouvellement',
            'price_xof' => 550000,
            'renewal_price_xof' => null,
            'billing_type' => 'lifetime',
            'max_users' => 50,
            'popular' => false,
            'cta' => 'Acheter à vie',
            'modules' => ['*'],
        ],
    ];

    public const MODULE_LABELS = [
        'patients' => 'Gestion des patients',
        'consultations' => 'Consultations médicales',
        'rendez_vous' => 'Prise de rendez-vous',
        'laboratoire' => 'Laboratoire & analyses',
        'pharmacie' => 'Pharmacie & stocks',
        'paiements' => 'Facturation & paiements',
        'finances' => 'Gestion financière',
        'personnel' => 'Gestion du personnel',
        'rapports' => 'Rapports & statistiques',
        'api_rest' => 'API REST & intégrations',
    ];

    public static function normalizeSlug(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return self::ANNUAL;
        }
        $slug = strtolower(trim($slug));
        $aliases = [
            'essentiel' => self::STARTER,
            'starter' => self::STARTER,
            'debutant' => self::STARTER,
            'demarrage' => self::STARTER,
            'annuel' => self::ANNUAL,
            'abonnement' => self::ANNUAL,
            'abonnement_annuel' => self::ANNUAL,
            'pro' => self::ANNUAL,
            'vie' => self::LIFETIME,
            'a_vie' => self::LIFETIME,
            'perpetual' => self::LIFETIME,
            'perpetuelle' => self::LIFETIME,
        ];
        if (isset($aliases[$slug])) {
            return $aliases[$slug];
        }
        return isset(self::$plans[$slug]) ? $slug : self::ANNUAL;
    }

    public static function get(string $slug): array
    {
        $plan = self::$plans[self::normalizeSlug($slug)] ?? self::$plans[self::ANNUAL];
        return self::withBrandName($plan);
    }

    public static function getAll(): array
    {
        return array_map([self::class, 'withBrandName'], self::$plans);
    }

    public static function getCommercialPlans(): array
    {
        return self::getAll();
    }

    private static function withBrandName(array $plan): array
    {
        $brand = function_exists('platform_name')
            ? platform_name()
            : (defined('PLATFORM_NAME') ? (string) PLATFORM_NAME : 'Se.Santé');
        $plan['name_full'] = ($plan['name'] ?? 'Plan') . ' ' . $brand;
        return $plan;
    }

    public static function planRank(string $slug): int
    {
        $order = [self::STARTER => 1, self::ANNUAL => 2, self::LIFETIME => 3];
        return $order[self::normalizeSlug($slug)] ?? 1;
    }

    public static function hasModule(string $planSlug, string $moduleKey): bool
    {
        $plan = self::get($planSlug);
        $modules = $plan['modules'] ?? [];
        return in_array('*', $modules, true) || in_array($moduleKey, $modules, true);
    }

    public static function getModuleLabel(string $moduleKey): string
    {
        return self::MODULE_LABELS[$moduleKey] ?? ucfirst(str_replace('_', ' ', $moduleKey));
    }

    public static function isAnnual(string $slug): bool
    {
        $plan = self::get($slug);
        return ($plan['billing_type'] ?? '') === 'annual';
    }

    public static function isLifetime(string $slug): bool
    {
        return self::normalizeSlug($slug) === self::LIFETIME;
    }

    public static function formatPrice(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    /**
     * @return array<int, array{text: string, ok: bool}>
     */
    public static function getPlanMarketingFeatures(string $slug): array
    {
        $common = [
            ['text' => 'Gestion complète des patients & dossiers', 'ok' => true],
            ['text' => 'Consultations, RDV & laboratoire', 'ok' => true],
            ['text' => 'Sync automatique paiements ↔ comptabilité', 'ok' => true],
            ['text' => 'Encaissement unifié consultations & analyses labo', 'ok' => true],
            ['text' => 'Personnel, rapports & statistiques', 'ok' => true],
            ['text' => 'Application mobile & PWA', 'ok' => true],
            ['text' => 'PharmaPro ERP — ERP officine autonome (tarifs dédiés)', 'ok' => true],
        ];

        $normalized = self::normalizeSlug($slug);
        $plan = self::get($normalized);

        if ($normalized === self::LIFETIME) {
            return array_merge($common, [
                ['text' => 'Accès permanent sans renouvellement', 'ok' => true],
                ['text' => 'Jusqu\'à ' . (int) $plan['max_users'] . ' utilisateurs', 'ok' => true],
                ['text' => 'Support prioritaire inclus', 'ok' => true],
            ]);
        }

        $renewal = (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
        return array_merge($common, [
            ['text' => 'Renouvellement annuel à ' . self::formatPrice($renewal), 'ok' => true],
            ['text' => 'Jusqu\'à ' . (int) $plan['max_users'] . ' utilisateurs', 'ok' => true],
            ['text' => 'Passage vers une formule supérieure possible', 'ok' => true],
        ]);
    }

    /**
     * Mapping page PHP → clé module (garde automatique).
     * @return array<string, string>
     */
    public static function getPageModuleMap(): array
    {
        return [
            'laboratoire' => 'laboratoire',
            'pharmacie' => 'pharmacie',
            'finances' => 'finances',
            'personnel' => 'personnel',
            'rapports' => 'rapports',
        ];
    }
}
