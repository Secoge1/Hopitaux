<?php
/**
 * Images de la page d'accueil — priorité : uploads custom, puis assets locaux, puis URL distante.
 */

if (!function_exists('home_image_root')) {
    function home_image_root(): string
    {
        return dirname(__DIR__);
    }

    function home_image_url(string $webPath): string
    {
        return function_exists('public_url') ? public_url($webPath) : $webPath;
    }

    /**
     * @param list<string> $relativePaths Chemins relatifs à la racine du projet
     */
    function home_first_existing_url(array $relativePaths): ?string
    {
        $root = home_image_root();
        foreach ($relativePaths as $rel) {
            $rel = ltrim(str_replace('\\', '/', $rel), '/');
            if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
                return home_image_url($rel);
            }
        }
        return null;
    }

    function home_banner_image(int $num, ?string $remoteFallback = null): string
    {
        $n = max(1, min(4, $num));
        $custom = home_first_existing_url([
            "uploads/banners/banner-{$n}.jpg",
            "uploads/banners/banner-{$n}.png",
            "uploads/banners/banner-{$n}.webp",
        ]);
        if ($custom !== null) {
            return $custom;
        }

        $local = home_first_existing_url([
            "assets/images/home/banner-{$n}.png",
            "assets/images/home/banner-{$n}.jpg",
            "assets/images/home/banner-{$n}.webp",
            "assets/images/home/banner-{$n}.svg",
        ]);
        if ($local !== null) {
            return $local;
        }

        if ($remoteFallback !== null && $remoteFallback !== '') {
            return $remoteFallback;
        }

        return home_image_url("assets/images/home/banner-{$n}.svg");
    }

    function home_about_image(?string $remoteFallback = null): string
    {
        $custom = home_first_existing_url([
            'uploads/images/about.jpg',
            'uploads/images/about.png',
            'uploads/banners/about.jpg',
            'uploads/banners/about.png',
            'uploads/about.jpg',
            'uploads/about.png',
        ]);
        if ($custom !== null) {
            return $custom;
        }

        $local = home_first_existing_url([
            'assets/images/home/about.png',
            'assets/images/home/about.jpg',
            'assets/images/home/about.webp',
            'assets/images/home/about.svg',
            'assets/images/home/banner-1.png',
            'assets/images/home/banner-1.svg',
        ]);
        if ($local !== null) {
            return $local;
        }

        if ($remoteFallback !== null && $remoteFallback !== '') {
            return $remoteFallback;
        }

        return home_image_url('assets/images/home/about.svg');
    }

    /** Secours navigateur : PNG/JPG puis SVG */
    function home_banner_fallback(int $num): string
    {
        $n = max(1, min(4, $num));
        return home_first_existing_url([
            "assets/images/home/banner-{$n}.png",
            "assets/images/home/banner-{$n}.jpg",
            "assets/images/home/banner-{$n}.svg",
        ]) ?? home_image_url("assets/images/home/banner-{$n}.svg");
    }

    function home_about_fallback(): string
    {
        return home_first_existing_url([
            'assets/images/home/about.png',
            'assets/images/home/about.jpg',
            'assets/images/home/about.svg',
        ]) ?? home_image_url('assets/images/home/about.svg');
    }
}
