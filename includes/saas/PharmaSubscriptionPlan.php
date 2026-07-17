<?php
/**
 * Plans commerciaux PharmaPro ERP — officine autonome (alignés affiche type Pharma Smart).
 * - Essentiel : 70 000 FCFA / an — 5 utilisateurs
 * - Pro       : 100 000 FCFA / an — 15 utilisateurs
 * - À vie     : 350 000 FCFA — utilisateurs illimités
 */

class PharmaSubscriptionPlan
{
    public const PRODUCT_LINE = 'pharma_erp';

    public const STARTER = 'starter';
    public const ANNUAL = 'annual';
    public const LIFETIME = 'lifetime';

    /** @var array<string, array> */
    private static array $plans = [
        self::STARTER => [
            'slug' => self::STARTER,
            'name' => 'Abonnement Essentiel',
            'name_full' => 'PharmaPro ERP — Abonnement Essentiel',
            'tagline' => 'Petite officine — jusqu\'à 5 utilisateurs',
            'price_xof' => 70000,
            'renewal_price_xof' => 70000,
            'billing_type' => 'annual',
            'max_users' => 5,
            'popular' => false,
            'cta' => 'Choisir cette offre',
            'modules' => ['*'],
        ],
        self::ANNUAL => [
            'slug' => self::ANNUAL,
            'name' => 'Abonnement Pro',
            'name_full' => 'PharmaPro ERP — Abonnement Pro',
            'tagline' => 'Officine en croissance — jusqu\'à 15 utilisateurs',
            'price_xof' => 100000,
            'renewal_price_xof' => 100000,
            'billing_type' => 'annual',
            'max_users' => 15,
            'popular' => true,
            'popular_badge' => 'LE PLUS POPULAIRE',
            'cta' => 'Choisir cette offre',
            'modules' => ['*'],
        ],
        self::LIFETIME => [
            'slug' => self::LIFETIME,
            'name' => 'Achat à vie',
            'name_full' => 'PharmaPro ERP — Licence à vie',
            'tagline' => 'Paiement unique — utilisateurs illimités',
            'price_xof' => 350000,
            'renewal_price_xof' => null,
            'billing_type' => 'lifetime',
            'max_users' => 999,
            'popular' => false,
            'cta' => 'Choisir cette offre',
            'modules' => ['*'],
        ],
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
            'annuel' => self::ANNUAL,
            'abonnement' => self::ANNUAL,
            'pro' => self::ANNUAL,
            'vie' => self::LIFETIME,
            'a_vie' => self::LIFETIME,
            'lifetime' => self::LIFETIME,
        ];
        if (isset($aliases[$slug])) {
            return $aliases[$slug];
        }
        return isset(self::$plans[$slug]) ? $slug : self::ANNUAL;
    }

    public static function get(string $slug): array
    {
        return self::$plans[self::normalizeSlug($slug)] ?? self::$plans[self::ANNUAL];
    }

    /** @return array<string, array> */
    public static function getAll(): array
    {
        return self::$plans;
    }

    public static function getCommercialPlans(): array
    {
        return self::getAll();
    }

    public static function planRank(string $slug): int
    {
        $order = [self::STARTER => 1, self::ANNUAL => 2, self::LIFETIME => 3];
        return $order[self::normalizeSlug($slug)] ?? 1;
    }

    public static function isAnnual(string $slug): bool
    {
        return (self::get($slug)['billing_type'] ?? '') === 'annual';
    }

    public static function isLifetime(string $slug): bool
    {
        return self::normalizeSlug($slug) === self::LIFETIME;
    }

    public static function formatPrice(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    public static function calculateAmount(string $planSlug, string $orderType = 'new'): int
    {
        $plan = self::get($planSlug);
        if ($orderType === 'renewal') {
            return (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
        }
        return (int) $plan['price_xof'];
    }

    /**
     * @return array<int, array{text: string, ok: bool}>
     */
    public static function getPlanMarketingFeatures(string $slug): array
    {
        $normalized = self::normalizeSlug($slug);
        $plan = self::get($normalized);

        $common = [
            ['text' => 'POS caisse, scan codes-barres & tickets thermiques', 'ok' => true],
            ['text' => 'Produits, lots, péremption & inventaires', 'ok' => true],
            ['text' => 'Ventes, achats, fournisseurs & clients', 'ok' => true],
            ['text' => 'Comptabilité SYSCOHADA intégrée', 'ok' => true],
            ['text' => 'Multi-caisses, RH officine & rapports', 'ok' => true],
            ['text' => 'Application mobile & PWA', 'ok' => true],
            ['text' => 'Mises à jour, sauvegardes & support inclus', 'ok' => true],
        ];

        if ($normalized === self::LIFETIME) {
            return array_merge($common, [
                ['text' => 'Accès permanent sans renouvellement', 'ok' => true],
                ['text' => 'Utilisateurs illimités', 'ok' => true],
                ['text' => 'Support prioritaire', 'ok' => true],
            ]);
        }

        $renewal = (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
        return array_merge($common, [
            ['text' => 'Renouvellement annuel à ' . self::formatPrice($renewal), 'ok' => true],
            ['text' => 'Jusqu\'à ' . (int) $plan['max_users'] . ' utilisateurs', 'ok' => true],
            ['text' => 'Passage vers une formule supérieure possible', 'ok' => true],
        ]);
    }

    /** @return array<int, array{icon: string, label: string}> */
    public static function getModuleShowcase(): array
    {
        return [
            ['icon' => 'fa-chart-line', 'label' => 'Tableau de bord'],
            ['icon' => 'fa-pills', 'label' => 'Produits'],
            ['icon' => 'fa-shopping-cart', 'label' => 'Ventes'],
            ['icon' => 'fa-truck', 'label' => 'Achats'],
            ['icon' => 'fa-boxes-stacked', 'label' => 'Stocks'],
            ['icon' => 'fa-users', 'label' => 'Clients'],
            ['icon' => 'fa-industry', 'label' => 'Fournisseurs'],
            ['icon' => 'fa-calculator', 'label' => 'Comptabilité SYSCOHADA'],
            ['icon' => 'fa-cash-register', 'label' => 'Caisse POS'],
            ['icon' => 'fa-user-tie', 'label' => 'Ressources humaines'],
            ['icon' => 'fa-file-alt', 'label' => 'Rapports'],
            ['icon' => 'fa-cog', 'label' => 'Paramètres'],
        ];
    }
}
