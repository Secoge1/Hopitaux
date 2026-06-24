<?php
/**
 * Helpers PWA partagés (manifest, meta, service worker).
 */

if (!function_exists('pwa_app_short_name')) {
    /** Nom affiché sous l'icône (installation PWA, iOS). */
    function pwa_app_short_name(): string
    {
        if (!function_exists('platform_name')) {
            require_once __DIR__ . '/platform_brand.php';
        }
        return platform_name();
    }

    /** Nom complet dans le manifest et les notifications. */
    function pwa_app_name(): string
    {
        return pwa_app_short_name() . ' - Gestion clinique';
    }
}

if (!function_exists('pwa_app_base')) {
    function pwa_app_base(): string
    {
        if (!function_exists('efficasante_web_base_path')) {
            require_once __DIR__ . '/header_logo.php';
        }
        $base = efficasante_web_base_path();
        return $base === '' ? '' : rtrim($base, '/');
    }
}

if (!function_exists('pwa_url')) {
    function pwa_url(string $path = ''): string
    {
        $base = pwa_app_base();
        if ($path === '') {
            return $base === '' ? '/' : $base;
        }
        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }
}

if (!function_exists('pwa_manifest_data')) {
    function pwa_manifest_data(): array
    {
        $base = pwa_app_base();
        $scope = ($base === '' ? '/' : $base . '/');
        $start = pwa_url('login.php');

        return [
            'id'                          => $scope,
            'name'                        => pwa_app_name(),
            'short_name'                  => pwa_app_short_name(),
            'description'                 => 'Patients, consultations, labo, sync paiements-comptabilité — PWA installable',
            'start_url'                   => $start,
            'scope'                       => $scope,
            'display'                     => 'standalone',
            'background_color'            => '#ffffff',
            'theme_color'                 => '#1976D2',
            'orientation'                 => 'any',
            'lang'                        => 'fr',
            'dir'                         => 'ltr',
            'categories'                  => ['medical', 'health', 'productivity'],
            'icons'                       => [
                [
                    'src'     => pwa_url('assets/pwa/icon-192x192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => pwa_url('assets/pwa/icon-512x512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => pwa_url('assets/pwa/icon-192x192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src'     => pwa_url('assets/pwa/icon-512x512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts'                   => [
                [
                    'name'        => 'Version mobile',
                    'short_name'  => 'Mobile',
                    'description' => 'Interface tactile smartphone',
                    'url'         => pwa_url('mobile/'),
                    'icons'       => [
                        ['src' => pwa_url('assets/pwa/icon-96x96.png'), 'sizes' => '96x96', 'type' => 'image/png'],
                    ],
                ],
                [
                    'name'        => 'Dashboard',
                    'short_name'  => 'Dashboard',
                    'description' => 'Tableau de bord',
                    'url'         => pwa_url('dashboard.php'),
                    'icons'       => [
                        ['src' => pwa_url('assets/pwa/icon-96x96.png'), 'sizes' => '96x96', 'type' => 'image/png'],
                    ],
                ],
                [
                    'name'        => 'Diagnostic IA',
                    'short_name'  => 'IA',
                    'description' => 'Diagnostic médical assisté',
                    'url'         => pwa_url('mobile_web_app_real_ai.html'),
                    'icons'       => [
                        ['src' => pwa_url('assets/pwa/icon-96x96.png'), 'sizes' => '96x96', 'type' => 'image/png'],
                    ],
                ],
            ],
            'related_applications'      => [],
            'prefer_related_applications' => false,
        ];
    }
}

if (!function_exists('pwa_render_head_tags')) {
    function pwa_render_head_tags(): void
    {
        $manifest = htmlspecialchars(pwa_url('manifest.php'));
        $apple    = htmlspecialchars(pwa_url('assets/pwa/apple-touch-icon.png'));
        ?>
    <meta name="theme-color" content="#1976D2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(pwa_app_short_name()) ?>">
    <link rel="manifest" href="<?= $manifest ?>">
    <link rel="apple-touch-icon" href="<?= $apple ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $apple ?>">
        <?php
    }
}

if (!function_exists('pwa_is_mobile_device')) {
    /** Détecte un smartphone ou une tablette via l'en-tête User-Agent. */
    function pwa_is_mobile_device(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            return false;
        }
        return (bool) preg_match(
            '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i',
            $ua
        );
    }
}

if (!function_exists('pwa_install_banner_html')) {
    function pwa_install_banner_html(): string
    {
        $app = htmlspecialchars(pwa_app_short_name());
        $icon = htmlspecialchars(pwa_url('assets/pwa/icon-96x96.png'));

        return '<div id="pwaInstallToast" role="dialog" aria-live="polite" aria-label="Installer l\'application">'
            . '<img src="' . $icon . '" alt="" class="pwa-toast-icon" width="40" height="40">'
            . '<div class="pwa-toast-body">'
            . '<p class="pwa-toast-title">Installer ' . $app . '</p>'
            . '<p class="pwa-toast-text" id="pwaInstallText">'
            . 'Ajoutez l\'application sur votre écran d\'accueil pour un accès rapide.'
            . '</p>'
            . '<div class="pwa-toast-actions">'
            . '<button type="button" class="pwa-btn-install" id="pwaBtnInstall" style="display:none">Installer</button>'
            . '<button type="button" class="pwa-btn-ios" id="pwaBtnIos" style="display:none">Ajouter à l\'écran d\'accueil</button>'
            . '<button type="button" class="pwa-btn-dismiss" id="pwaBtnDismiss">Plus tard</button>'
            . '</div>'
            . '</div>'
            . '<button type="button" class="pwa-btn-close" id="pwaBtnClose" aria-label="Fermer">&times;</button>'
            . '</div>'
            . '<div id="pwaIosGuide" class="pwa-ios-guide" hidden aria-hidden="true">'
            . '<div class="pwa-ios-guide-backdrop" id="pwaIosGuideBackdrop"></div>'
            . '<div class="pwa-ios-guide-sheet" role="dialog" aria-labelledby="pwaIosGuideTitle">'
            . '<button type="button" class="pwa-ios-guide-close" id="pwaIosGuideClose" aria-label="Fermer">&times;</button>'
            . '<p class="pwa-ios-guide-title" id="pwaIosGuideTitle">Ajouter ' . $app . ' sur l\'écran d\'accueil</p>'
            . '<p class="pwa-ios-guide-sub">Sur iPhone, l\'installation se fait via <strong>Safari</strong> :</p>'
            . '<ol class="pwa-ios-steps">'
            . '<li><span class="pwa-step-icon">□↑</span> Touchez <strong>Partager</strong> en bas de l\'écran</li>'
            . '<li><span class="pwa-step-icon">＋</span> Choisissez <strong>Sur l\'écran d\'accueil</strong></li>'
            . '<li><span class="pwa-step-icon">✓</span> Touchez <strong>Ajouter</strong> en haut à droite</li>'
            . '</ol>'
            . '<p class="pwa-ios-note" id="pwaIosSafariNote" style="display:none">'
            . 'Vous n\'êtes pas dans Safari. Copiez l\'adresse du site, ouvrez <strong>Safari</strong>, collez-la puis suivez les étapes ci-dessus.'
            . '</p>'
            . '<button type="button" class="pwa-ios-guide-ok" id="pwaIosGuideOk">J\'ai compris</button>'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('pwa_install_script_html')) {
    function pwa_install_script_html(): string
    {
        $app = json_encode(pwa_app_short_name(), JSON_UNESCAPED_UNICODE);
        $swUrl = json_encode(pwa_url('sw.js'), JSON_UNESCAPED_SLASHES);

        return '<script>(function(){'
            . '"use strict";'
            . 'var APP=' . $app . ';'
            . 'var SW_URL=' . $swUrl . ';'
            . 'if("serviceWorker"in navigator&&!window.__PWA_SW_REGISTERED){'
            . 'window.__PWA_SW_REGISTERED=true;'
            . 'navigator.serviceWorker.register(SW_URL).catch(function(e){console.warn("PWA SW:",e);});'
            . '}'
            . 'function pwaIsStandalone(){'
            . 'return window.matchMedia("(display-mode: standalone)").matches||window.navigator.standalone===true;'
            . '}'
            . 'function pwaIsMobileClient(){'
            . 'return window.matchMedia("(max-width:768px)").matches'
            . '||/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);'
            . '}'
            . 'function pwaIsIos(){return /iPhone|iPad|iPod/i.test(navigator.userAgent)&&!window.MSStream;}'
            . 'function pwaIsSafariIos(){'
            . 'var ua=navigator.userAgent;'
            . 'return pwaIsIos()&&/Safari/i.test(ua)&&!/CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo/i.test(ua);'
            . '}'
            . 'function pwaOpenIosGuide(){'
            . 'var g=document.getElementById("pwaIosGuide");'
            . 'var note=document.getElementById("pwaIosSafariNote");'
            . 'if(!g)return;'
            . 'if(note)note.style.display=pwaIsSafariIos()?"none":"block";'
            . 'g.hidden=false;g.setAttribute("aria-hidden","false");'
            . 'document.body.classList.add("pwa-ios-guide-open");'
            . '}'
            . 'function pwaCloseIosGuide(){'
            . 'var g=document.getElementById("pwaIosGuide");'
            . 'if(!g)return;'
            . 'g.hidden=true;g.setAttribute("aria-hidden","true");'
            . 'document.body.classList.remove("pwa-ios-guide-open");'
            . '}'
            . 'var PWA_DISMISS_KEY="sesante_pwa_install_dismiss";'
            . 'var PWA_DISMISS_DAYS=30;'
            . 'var pwaShowTimer=null;'
            . 'function pwaDismissKey(){return PWA_DISMISS_KEY;}'
            . 'function pwaReadDismissTs(){'
            . 'try{var r=localStorage.getItem(pwaDismissKey());if(r)return parseInt(r,10);'
            . 'r=sessionStorage.getItem(pwaDismissKey());return r?parseInt(r,10):0;}catch(e){return 0;}'
            . '}'
            . 'function pwaWriteDismissTs(ts){'
            . 'try{localStorage.setItem(pwaDismissKey(),String(ts));}catch(e){}'
            . 'try{sessionStorage.setItem(pwaDismissKey(),String(ts));}catch(e){}'
            . '}'
            . 'function pwaIsDismissed(){'
            . 'var ts=pwaReadDismissTs();'
            . 'if(!ts)return false;'
            . 'return Date.now()-ts<PWA_DISMISS_DAYS*24*60*60*1000;'
            . '}'
            . 'function pwaHideToast(){'
            . 'var t=document.getElementById("pwaInstallToast");'
            . 'if(t)t.classList.remove("is-visible");'
            . '}'
            . 'function pwaDismiss(){'
            . 'if(pwaShowTimer){clearTimeout(pwaShowTimer);pwaShowTimer=null;}'
            . 'pwaWriteDismissTs(Date.now());'
            . 'pwaHideToast();'
            . 'pwaCloseIosGuide();'
            . '}'
            . 'function pwaShowToast(){'
            . 'var toast=document.getElementById("pwaInstallToast");'
            . 'var text=document.getElementById("pwaInstallText");'
            . 'var btn=document.getElementById("pwaBtnInstall");'
            . 'if(!toast||pwaIsStandalone()||pwaIsDismissed()||!pwaIsMobileClient())return;'
            . 'if(pwaShowTimer){clearTimeout(pwaShowTimer);}'
            . 'if(!window.isSecureContext&&location.hostname!=="localhost"){'
            . 'if(text)text.textContent="L\'installation nécessite une connexion sécurisée (HTTPS).";'
            . '}else if(pwaIsIos()){'
            . 'var iosBtn=document.getElementById("pwaBtnIos");'
            . 'if(iosBtn)iosBtn.style.display="inline-flex";'
            . 'if(text)text.textContent=pwaIsSafariIos()'
            . '?"Ajoutez "+APP+" sur l\'écran d\'accueil comme une application."'
            . ':"Ouvrez ce site dans Safari pour l\'ajouter à l\'écran d\'accueil.";'
            . '}else if(btn){btn.style.display="inline-flex";'
            . 'if(text)text.textContent="Installez "+APP+" comme une application native sur cet appareil.";'
            . '}'
            . 'pwaShowTimer=setTimeout(function(){'
            . 'pwaShowTimer=null;'
            . 'if(pwaIsDismissed()||pwaIsStandalone())return;'
            . 'toast.classList.add("is-visible");'
            . '},1500);'
            . '}'
            . 'var deferredPrompt=null;'
            . 'window.addEventListener("beforeinstallprompt",function(e){'
            . 'e.preventDefault();deferredPrompt=e;'
            . 'var btn=document.getElementById("pwaBtnInstall");'
            . 'if(btn)btn.style.display="inline-flex";'
            . 'pwaShowToast();'
            . '});'
            . 'document.addEventListener("DOMContentLoaded",function(){'
            . 'var btn=document.getElementById("pwaBtnInstall");'
            . 'var closeBtn=document.getElementById("pwaBtnClose");'
            . 'var dismissBtn=document.getElementById("pwaBtnDismiss");'
            . 'var legacyBox=document.getElementById("installBox");'
            . 'if(legacyBox)legacyBox.classList.add("hidden");'
            . 'var iosBtn=document.getElementById("pwaBtnIos");'
            . 'if(btn){btn.addEventListener("click",function(){'
            . 'if(deferredPrompt){deferredPrompt.prompt();'
            . 'deferredPrompt.userChoice.then(function(){deferredPrompt=null;pwaDismiss();});'
            . '}else if(pwaIsIos()){pwaOpenIosGuide();}'
            . 'else{alert("Chrome : menu ⋮ → Installer l\'application ou Ajouter à l\'écran d\'accueil");}'
            . '});}'
            . 'if(iosBtn){iosBtn.addEventListener("click",pwaOpenIosGuide);}'
            . 'var iosClose=document.getElementById("pwaIosGuideClose");'
            . 'var iosOk=document.getElementById("pwaIosGuideOk");'
            . 'var iosBackdrop=document.getElementById("pwaIosGuideBackdrop");'
            . 'if(iosClose)iosClose.addEventListener("click",pwaDismiss);'
            . 'if(iosOk)iosOk.addEventListener("click",pwaDismiss);'
            . 'if(iosBackdrop)iosBackdrop.addEventListener("click",pwaDismiss);'
            . 'if(closeBtn)closeBtn.addEventListener("click",pwaDismiss);'
            . 'if(dismissBtn)dismissBtn.addEventListener("click",pwaDismiss);'
            . 'if(!pwaIsStandalone()&&!pwaIsDismissed())pwaShowToast();'
            . '});'
            . '})();</script>';
    }
}

if (!function_exists('pwa_inject_install_banner')) {
    /** Injecte la bannière PWA dans le HTML final (smartphones uniquement). */
    function pwa_inject_install_banner(string $html): string
    {
        if (strpos($html, '<html') === false || stripos($html, 'id="pwaInstallToast"') !== false) {
            return $html;
        }
        if (!pwa_is_mobile_device()) {
            return $html;
        }

        $css = '<link rel="stylesheet" href="' . htmlspecialchars(pwa_url('assets/css/pwa_install.css')) . '" id="pwa-install-css">';
        $html = preg_replace('/(<head\b[^>]*>)/i', '$1' . $css, $html, 1);

        $snippet = pwa_install_banner_html() . pwa_install_script_html();
        return str_replace('</body>', $snippet . '</body>', $html);
    }
}

if (!function_exists('pwa_render_sw_script')) {
    function pwa_render_sw_script(): void
    {
        $swUrl = htmlspecialchars(pwa_url('sw.js'));
        ?>
    <script>
    (function () {
        if (!('serviceWorker' in navigator) || window.__PWA_SW_REGISTERED) return;
        window.__PWA_SW_REGISTERED = true;
        navigator.serviceWorker.register('<?= $swUrl ?>')
            .catch(function (err) { console.warn('PWA service worker:', err); });
    })();
    </script>
        <?php
    }
}
