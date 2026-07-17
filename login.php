<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Utilisateur.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/includes/header_logo.php';
require_once __DIR__ . '/includes/saas/SubscriptionService.php';
require_once __DIR__ . '/includes/saas/TenantSchema.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';
require_once __DIR__ . '/includes/saas/PharmaCommercial.php';
require_once __DIR__ . '/includes/saas/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/includes/pharma_erp/bootstrap.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', efficasante_web_base_path());
}
require_once __DIR__ . '/includes/platform_brand.php';

$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
if ($redirect && preg_match('#^https?://#i', $redirect)) {
    $redirect = '';
} elseif ($redirect) {
    $redirect = ltrim($redirect, '/');
}

        if (isset($_SESSION['user_id'], $_SESSION['user_connected']) && $_SESSION['user_connected'] === true) {
    header('Location: ' . pharma_erp_post_login_url($redirect ?: null));
    exit();
}

$error = '';
$appName = platform_name();
$appTagline = platform_tagline();
$appYear = date('Y');
$documentationUrl = BASE_PATH . '/documentation.php';

$demoAccount = null;
$demoTry = isset($_GET['demo_try']);
$demoProduct = PharmaCommercial::normalizeProductLine($_GET['product'] ?? '');
if ($demoTry && $demoProduct === 'clinical' && function_exists('app_is_pharma_production_host') && app_is_pharma_production_host()) {
    $demoProduct = PharmaSubscriptionPlan::PRODUCT_LINE;
    if ($redirect === '') {
        $redirect = 'pharma_erp/';
    }
}
if ($demoTry) {
    try {
        TenantSchema::ensure();
        if ($demoProduct === PharmaSubscriptionPlan::PRODUCT_LINE) {
            $demoAccount = PharmaCommercial::ensurePharmaDemoTenant();
            if ($redirect === '') {
                $redirect = 'pharma_erp/';
            }
        } else {
            $demoAccount = SubscriptionService::getInstance()->ensureDemoTenant();
        }
    } catch (Exception $e) {
        error_log('Demo tenant: ' . $e->getMessage());
        $error = 'Impossible de préparer l\'environnement de démonstration. Réessayez dans quelques instants.';
    }
}

if ($demoTry && $demoAccount) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? AND statut = ? LIMIT 1'
        );
        $stmt->execute([$demoAccount['username'], $demoAccount['tenant_id'], 'actif']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($demoAccount['password'], $user['mot_de_passe'])) {
            $auth = Auth::getInstance();
            $auth->connecter($user);
            header('Location: ' . pharma_erp_post_login_url($redirect ?: null));
            exit();
        }
        $error = 'Connexion démo impossible (compte introuvable ou mot de passe obsolète). Contactez le support.';
    } catch (Exception $e) {
        error_log('Demo login: ' . $e->getMessage());
        $error = 'Erreur lors de la connexion démo. Veuillez réessayer.';
    }
} elseif ($demoTry && $error === '') {
    $error = 'Environnement de démonstration indisponible. Vérifiez que la base de données est accessible.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $postRedirect = trim($_POST['redirect'] ?? '');

    if ($postRedirect && !preg_match('#^https?://#i', $postRedirect)) {
        $redirect = ltrim($postRedirect, '/');
    }

    if ($identifiant === '' || $mot_de_passe === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $database = new Database();
            $utilisateurModel = new Utilisateur($database->getConnection());
            $utilisateur = $utilisateurModel->authentifierIdentifiant($identifiant, $mot_de_passe);

            if ($utilisateur) {
                Auth::getInstance()->connecter($utilisateur);
                header('Location: ' . pharma_erp_post_login_url($redirect ?: null));
                exit();
            }
            $error = 'Identifiants incorrects ou compte inactif.';
        } catch (Exception $e) {
            error_log('Login: ' . $e->getMessage());
            $error = 'Erreur de connexion. Veuillez réessayer.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= htmlspecialchars($appName) ?></title>
    <?php
        require_once __DIR__ . '/includes/pwa.php';
        pwa_render_head_tags();
    ?>
    <link rel="icon" href="<?= htmlspecialchars(platform_logo_url()) ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary-dark:   #1b4f9b;
            --primary-mid:    #1b8fad;
            --primary-main:   #00b5ad;
            --primary-light:  #5dcecb;
            --primary-pale:   #e8f7f9;
            --cream:          #f8fcfd;
            --text-dark:      #0f2942;
            --text-mid:       #1b4f9b;
            --text-light:     #64748b;
            --border:         #e2e8f0;
            --error:          #dc2626;
            --error-bg:       #fef2f2;
            --shadow-card:    0 32px 80px rgba(27,79,155,.22), 0 8px 24px rgba(27,143,173,.12);
            --shadow-btn:     0 8px 24px rgba(27,143,173,.4);
        }

        html, body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--primary-dark);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .login-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .login-aside {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            background: linear-gradient(155deg, #0f2942 0%, var(--primary-dark) 42%, var(--primary-mid) 100%);
            overflow: hidden;
        }
        .login-aside::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 25%, rgba(167,139,250,.2) 0%, transparent 45%),
                radial-gradient(circle at 85% 75%, rgba(102,126,234,.15) 0%, transparent 40%);
            pointer-events: none;
        }
        .aside-content { position: relative; z-index: 1; }

        .aside-logo {
            margin: 0 0 3rem -3rem;
            padding: 1rem 1.5rem 1rem 3rem;
            background: #fff;
            border-radius: 0 14px 14px 0;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
            display: inline-block;
            max-width: calc(100% + 3rem);
        }
        .platform-logo-login {
            max-width: 240px;
            width: 100%;
            height: auto;
            display: block;
        }
        .platform-logo-compact {
            max-height: 48px;
            width: auto;
            display: block;
        }

        .aside-carousel { position: relative; min-height: 320px; max-width: 420px; }
        .aside-slides { position: relative; min-height: 300px; }
        .aside-slide {
            position: absolute; inset: 0;
            opacity: 0; visibility: hidden;
            transform: translateY(12px);
            transition: opacity .55s ease, transform .55s ease, visibility .55s;
        }
        .aside-slide.is-active {
            opacity: 1; visibility: visible; transform: translateY(0); z-index: 1;
        }
        .aside-slide h2 {
            font-size: clamp(1.65rem, 2.8vw, 2.2rem);
            font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 1rem;
        }
        .aside-slide h2 em { font-style: normal; color: var(--primary-light); }
        .aside-slide .slide-lead {
            color: rgba(255,255,255,.65); font-size: .94rem; line-height: 1.75;
            max-width: 380px; margin-bottom: 1.75rem;
        }
        .aside-features { list-style: none; display: flex; flex-direction: column; gap: .8rem; }
        .aside-features li {
            display: flex; align-items: center; gap: 12px;
            color: rgba(255,255,255,.82); font-size: .88rem;
        }
        .aside-features .feat-dot {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(167,139,250,.3);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .aside-features .feat-dot i { font-size: 12px; color: var(--primary-light); }

        .aside-carousel-nav { display: flex; align-items: center; gap: 1rem; margin-top: 2rem; }
        .aside-dots { display: flex; gap: 6px; flex: 1; }
        .aside-dot {
            width: 8px; height: 8px; border-radius: 50%; border: none; padding: 0;
            background: rgba(255,255,255,.25); cursor: pointer;
            transition: background .25s, transform .25s;
        }
        .aside-dot.is-active { background: var(--primary-light); transform: scale(1.15); }
        .aside-progress {
            flex: 1; max-width: 120px; height: 3px;
            background: rgba(255,255,255,.15); border-radius: 3px; overflow: hidden;
        }
        .aside-progress-bar {
            display: block; height: 100%; width: 0%;
            background: linear-gradient(90deg, var(--primary-mid), var(--primary-light));
            border-radius: 3px;
        }
        .aside-progress-bar.is-running { animation: asideProgress 6s linear forwards; }
        @keyframes asideProgress { from { width: 0%; } to { width: 100%; } }

        .aside-footer {
            position: relative; z-index: 1;
            display: flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,.4); font-size: .75rem;
        }
        .aside-footer i { color: var(--primary-light); opacity: .8; }

        .login-main {
            display: flex; align-items: center; justify-content: center;
            padding: 2.5rem 2rem; background: var(--cream); position: relative;
        }
        .login-main::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--primary-light), var(--primary-mid), transparent);
            opacity: .5;
        }

        .login-panel { width: 100%; max-width: 420px; animation: fadeUp .5s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-mobile-brand { display: none; align-items: center; gap: 12px; margin-bottom: 2rem; }
        .login-mobile-brand .aside-logo-name { font-size: 1.15rem; color: var(--text-dark); }
        .login-mobile-brand .aside-logo-tag { color: var(--text-light); }

        .login-form-header { margin-bottom: 1.75rem; }
        .login-form-header .eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            color: var(--primary-mid); background: rgba(102,126,234,.1);
            border: 1px solid rgba(102,126,234,.2); padding: 5px 12px; border-radius: 20px; margin-bottom: .85rem;
        }
        .login-form-header h1 { font-size: 1.85rem; font-weight: 900; color: var(--text-dark); margin-bottom: .4rem; }
        .login-form-header p { color: var(--text-light); font-size: .92rem; }

        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 13px 16px; border-radius: 12px; margin-bottom: 1.35rem;
            font-size: 13.5px; animation: shake .45s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        .alert-error { background: var(--error-bg); border: 1px solid #fca5a5; color: var(--error); }

        .field { margin-bottom: 1.2rem; }
        .field label { display: block; font-size: .82rem; font-weight: 700; color: var(--text-mid); margin-bottom: .5rem; }
        .field-wrap { position: relative; }
        .field-wrap .field-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: var(--text-light); font-size: 15px; pointer-events: none;
        }
        .field-wrap input {
            width: 100%; padding: 13px 14px 13px 44px;
            border: 1.5px solid var(--border); border-radius: 12px; font-size: 15px;
            background: #fff; color: var(--text-dark);
            transition: border-color .2s, box-shadow .2s;
        }
        .field-wrap input:focus {
            outline: none; border-color: var(--primary-mid);
            box-shadow: 0 0 0 4px rgba(102,126,234,.12);
        }
        .field-wrap:focus-within .field-icon { color: var(--primary-mid); }
        .field-wrap.has-toggle input { padding-right: 44px; }
        .toggle-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--text-light); cursor: pointer; font-size: 15px; padding: 5px;
        }

        .btn-login {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            background: linear-gradient(135deg, var(--primary-mid) 0%, var(--primary-main) 100%);
            color: #fff; box-shadow: var(--shadow-btn);
            display: flex; align-items: center; justify-content: center; gap: 9px;
            margin-top: .5rem; transition: transform .18s;
        }
        .btn-login:hover { transform: translateY(-2px); }
        .btn-login.loading .btn-text { display: none; }
        .btn-login .spinner { display: none; }
        .btn-login.loading .spinner { display: inline-flex; align-items: center; gap: 8px; }

        .form-footer {
            margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border);
            text-align: center; color: var(--text-light); font-size: .78rem; line-height: 1.7;
        }
        .form-footer a { color: var(--primary-mid); font-weight: 600; text-decoration: none; }

        @media (max-width: 900px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-aside { display: none; }
            .login-main { min-height: 100vh; }
            .login-mobile-brand { display: flex; }
        }
    </style>
</head>
<body>

<div class="login-shell">

    <aside class="login-aside">
        <div class="aside-content">
            <div class="aside-logo">
                <?= platform_brand_html('login') ?>
            </div>

            <div class="aside-carousel" id="asideCarousel">
                <div class="aside-slides">
                    <article class="aside-slide is-active">
                        <h2>Gérez vos <em>patients</em><br>en toute simplicité</h2>
                        <p class="slide-lead">Dossiers médicaux, historique des consultations et suivi complet de votre patientèle.</p>
                        <ul class="aside-features">
                            <li><span class="feat-dot"><i class="fas fa-user-injured"></i></span>Dossiers patients centralisés</li>
                            <li><span class="feat-dot"><i class="fas fa-file-medical"></i></span>Historique médical complet</li>
                            <li><span class="feat-dot"><i class="fas fa-search"></i></span>Recherche rapide multi-critères</li>
                        </ul>
                    </article>
                    <article class="aside-slide">
                        <h2>Consultations &amp; <em>rendez-vous</em></h2>
                        <p class="slide-lead">Planifiez, suivez et facturez les actes médicaux depuis une interface unique.</p>
                        <ul class="aside-features">
                            <li><span class="feat-dot"><i class="fas fa-calendar-check"></i></span>Agenda &amp; prise de RDV</li>
                            <li><span class="feat-dot"><i class="fas fa-stethoscope"></i></span>Consultations &amp; prescriptions</li>
                            <li><span class="feat-dot"><i class="fas fa-bell"></i></span>Rappels &amp; notifications</li>
                        </ul>
                    </article>
                    <article class="aside-slide">
                        <h2>Laboratoire &amp; <em>pharmacie</em></h2>
                        <p class="slide-lead">Analyses, résultats et gestion des stocks médicamenteux intégrés.</p>
                        <ul class="aside-features">
                            <li><span class="feat-dot"><i class="fas fa-flask"></i></span>Analyses &amp; résultats labo</li>
                            <li><span class="feat-dot"><i class="fas fa-pills"></i></span>Pharmacie &amp; stocks</li>
                            <li><span class="feat-dot"><i class="fas fa-vial"></i></span>Suivi des prélèvements</li>
                        </ul>
                    </article>
                    <article class="aside-slide">
                        <h2>Finances &amp; <em>facturation</em></h2>
                        <p class="slide-lead">Paiements, assurances et rapports financiers pour piloter votre établissement.</p>
                        <ul class="aside-features">
                            <li><span class="feat-dot"><i class="fas fa-money-bill-wave"></i></span>Facturation &amp; encaissements</li>
                            <li><span class="feat-dot"><i class="fas fa-sync-alt"></i></span>Sync automatique paiements ↔ comptabilité</li>
                            <li><span class="feat-dot"><i class="fas fa-chart-bar"></i></span>Rapports &amp; statistiques</li>
                            <li><span class="feat-dot"><i class="fas fa-shield-alt"></i></span>Assurances &amp; tiers payant</li>
                        </ul>
                    </article>
                    <article class="aside-slide">
                        <h2>SaaS <em>multi-établissements</em></h2>
                        <p class="slide-lead">Chaque clinique dispose de son espace isolé — Essentiel, Pro ou licence à vie.</p>
                        <ul class="aside-features">
                            <li><span class="feat-dot"><i class="fas fa-cloud"></i></span>Hébergement cloud sécurisé</li>
                            <li><span class="feat-dot"><i class="fas fa-mobile-alt"></i></span>App mobile &amp; PWA</li>
                            <li><span class="feat-dot"><i class="fas fa-infinity"></i></span>Licence à vie disponible</li>
                        </ul>
                    </article>
                </div>
                <div class="aside-carousel-nav">
                    <div class="aside-dots" id="asideDots"></div>
                    <div class="aside-progress"><span class="aside-progress-bar is-running" id="asideProgressBar"></span></div>
                </div>
            </div>
        </div>
        <div class="aside-footer">
            <i class="fas fa-globe-africa"></i>
            <span>&copy; <?= $appYear ?> <?= htmlspecialchars($appName) ?> — <?= htmlspecialchars(platform_company()) ?>, Mali</span>
        </div>
    </aside>

    <main class="login-main">
        <div class="login-panel">

            <div class="login-mobile-brand">
                <?= platform_brand_html('login-compact') ?>
            </div>

            <div class="login-form-header">
                <span class="eyebrow"><i class="fas fa-shield-alt"></i> Espace sécurisé</span>
                <h1>Bienvenue&nbsp;!</h1>
                <p>Connectez-vous à votre espace de gestion clinique.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" novalidate>
                <input type="hidden" name="login" value="1">
                <?php if ($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <?php endif; ?>

                <div class="field">
                    <label for="identifiant">Identifiant ou email</label>
                    <div class="field-wrap">
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" id="identifiant" name="identifiant"
                               placeholder="Votre identifiant ou adresse email"
                               required autofocus autocomplete="username"
                               value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="mot_de_passe">Mot de passe</label>
                    <div class="field-wrap has-toggle">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" id="mot_de_passe" name="mot_de_passe"
                               placeholder="Votre mot de passe" required autocomplete="current-password">
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Afficher le mot de passe">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Se connecter</span>
                    <span class="spinner"><i class="fas fa-spinner fa-spin"></i> Connexion…</span>
                </button>
            </form>

            <div class="form-footer">
                <p>
                    <i class="fas fa-lock"></i> Connexion sécurisée
                    &nbsp;&mdash;&nbsp;
                    <a href="mailto:contact@secogesarl.com">Support</a>
                    &nbsp;&middot;&nbsp;
                    <a href="<?= htmlspecialchars($documentationUrl) ?>">Documentation</a>
                    &nbsp;&middot;&nbsp;
                    <a href="<?= htmlspecialchars(BASE_PATH . '/home.php') ?>">Accueil</a>
                </p>
                <p style="margin-top:.4rem;">&copy; <?= $appYear ?> <?= htmlspecialchars($appName) ?></p>
            </div>
        </div>
    </main>
</div>

<script>
const togglePw = document.getElementById('togglePw');
const pwInput  = document.getElementById('mot_de_passe');
if (togglePw && pwInput) {
    togglePw.addEventListener('click', function () {
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        this.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
}
document.getElementById('loginForm').addEventListener('submit', function () {
    document.getElementById('btnLogin').classList.add('loading');
});

(function () {
    const slides = Array.from(document.querySelectorAll('.aside-slide'));
    const dotsWrap = document.getElementById('asideDots');
    const progressBar = document.getElementById('asideProgressBar');
    if (!slides.length || !dotsWrap) return;

    const INTERVAL = 6000;
    let current = 0, timer = null;

    slides.forEach(function (_, i) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'aside-dot' + (i === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Diapositive ' + (i + 1));
        dot.addEventListener('click', function () { goTo(i, true); });
        dotsWrap.appendChild(dot);
    });
    const dots = Array.from(dotsWrap.querySelectorAll('.aside-dot'));

    function resetProgress() {
        if (!progressBar) return;
        progressBar.classList.remove('is-running');
        void progressBar.offsetWidth;
        progressBar.classList.add('is-running');
    }
    function goTo(index, userClick) {
        current = (index + slides.length) % slides.length;
        slides.forEach(function (s, i) { s.classList.toggle('is-active', i === current); });
        dots.forEach(function (d, i) { d.classList.toggle('is-active', i === current); });
        resetProgress();
        if (userClick) restartTimer();
    }
    function next() { goTo(current + 1, false); }
    function restartTimer() { clearInterval(timer); timer = setInterval(next, INTERVAL); }
    resetProgress();
    restartTimer();
})();
</script>
<?php pwa_render_sw_script(); ?>
</body>
</html>
