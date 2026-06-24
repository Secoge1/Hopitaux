<?php
/**
 * Classe Auth
 * Gestion de l'authentification et des sessions
 */

class Auth {
    private static $instance = null;
    private $utilisateur = null;

    private function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->hydrateFromSession();
    }

    /**
     * Normalise le slug de rôle (évite les écarts BDD / session).
     */
    private function normalizeRole($role): string
    {
        $role = trim(strtolower((string) $role));
        $aliases = [
            'médecin' => 'medecin',
            'docteur' => 'medecin',
            'doctor'  => 'medecin',
        ];
        return $aliases[$role] ?? $role;
    }

    /**
     * Recharge le profil depuis la BDD si le rôle en session est vide ou invalide.
     */
    private function refreshUtilisateurFromDb(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }
        try {
            require_once __DIR__ . '/db.php';
            $pdo = getDB();
            $stmt = $pdo->prepare(
                'SELECT id, nom_utilisateur, email, role, statut, tenant_id
                 FROM utilisateurs WHERE id = ? LIMIT 1'
            );
            $stmt->execute([(int) $_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['role'] = $this->normalizeRole($row['role'] ?? '');
                $this->connecter($row);
            }
        } catch (Exception $e) {
            // Ignorer — session inchangée
        }
    }

    private function hydrateFromSession(): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $role = $this->normalizeRole($_SESSION['user_role'] ?? '');
        if ($role === '') {
            $this->refreshUtilisateurFromDb();
            return;
        }

        $_SESSION['user_role'] = $role;
        $this->utilisateur = [
            'id' => (int) $_SESSION['user_id'],
            'nom_utilisateur' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $role,
            'statut' => $_SESSION['user_statut'] ?? 'actif',
        ];
        if (isset($_SESSION['tenant_id'])) {
            $this->utilisateur['tenant_id'] = (int) $_SESSION['tenant_id'];
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        } elseif (self::$instance->utilisateur === null) {
            self::$instance->hydrateFromSession();
        }
        return self::$instance;
    }

    /**
     * Connecter un utilisateur
     */
    public function connecter($utilisateur_data) {
        $role = $this->normalizeRole($utilisateur_data['role'] ?? '');
        $_SESSION['user_id'] = $utilisateur_data['id'];
        $_SESSION['user_name'] = $utilisateur_data['nom_utilisateur'];
        $_SESSION['user_email'] = $utilisateur_data['email'];
        $_SESSION['user_role'] = $role;
        $_SESSION['user_statut'] = $utilisateur_data['statut'];
        $_SESSION['user_connected'] = true;
        $_SESSION['last_activity'] = time();

        if (isset($utilisateur_data['tenant_id'])) {
            $_SESSION['tenant_id'] = (int) $utilisateur_data['tenant_id'];
        }
        if (!empty($utilisateur_data['is_platform_admin'])) {
            $_SESSION['is_platform_admin'] = true;
        } else {
            unset($_SESSION['is_platform_admin']);
        }

        $this->utilisateur = $utilisateur_data;
        $this->utilisateur['role'] = $role;

        return true;
    }

    /**
     * ID du tenant (établissement) courant — multi-tenant SaaS.
     */
    public function getTenantId() {
        return isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
    }

    /**
     * Vérifie que le tenant est actif et non expiré.
     */
    public function ensureActiveTenant() {
        if (!$this->estConnecte()) {
            return;
        }
        require_once __DIR__ . '/../includes/saas/saas_helpers.php';
        if (saas_is_platform_admin()) {
            return;
        }
        require_once __DIR__ . '/../includes/saas/SubscriptionService.php';
        SubscriptionService::getInstance()->loadForSession();
        SubscriptionService::getInstance()->requireActiveTenant();
    }

    /**
     * Administrateur plateforme (gestion SaaS globale).
     */
    public function estAdminPlateforme() {
        require_once __DIR__ . '/../includes/saas/saas_helpers.php';
        return saas_is_platform_admin();
    }

    public function requireAdminPlateforme($redirect_url = null) {
        $this->requireAuth();
        if (!$this->estAdminPlateforme()) {
            if ($redirect_url === null) {
                $redirect_url = (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard.php';
            }
            header('Location: ' . $redirect_url);
            exit();
        }
    }

    /**
     * Déconnecter l'utilisateur
     */
    public function deconnecter() {
        session_unset();
        session_destroy();
        $this->utilisateur = null;
        
        return true;
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function estConnecte() {
        if (!$this->utilisateur) {
            return false;
        }

        // Vérifier l'expiration de la session (8 heures)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 28800)) {
            $this->deconnecter();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function getUtilisateur() {
        return $this->utilisateur;
    }

    /**
     * Obtenir l'ID de l'utilisateur connecté
     */
    public function getUserId() {
        return $this->utilisateur ? $this->utilisateur['id'] : null;
    }

    /**
     * Obtenir le rôle de l'utilisateur connecté
     */
    public function getUserRole() {
        return $this->utilisateur ? $this->utilisateur['role'] : null;
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public function aRole($role) {
        return $this->estConnecte() && $this->utilisateur['role'] === $role;
    }

    /**
     * Vérifier si l'utilisateur a un des rôles spécifiés
     */
    public function aUnRole($roles) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return $this->estConnecte() && in_array($this->utilisateur['role'], $roles);
    }

    /**
     * Accès au module selon le rôle et les droits personnalisés de l'établissement.
     */
    public function aAccesModule(string $moduleKey): bool
    {
        if (!$this->estConnecte()) {
            return false;
        }
        if (!function_exists('saas_is_platform_admin')) {
            require_once __DIR__ . '/../includes/saas/saas_helpers.php';
        }
        if (saas_is_platform_admin()) {
            return true;
        }
        if (!function_exists('app_role_has_module')) {
            require_once __DIR__ . '/../includes/roles.php';
        }
        $tenantId = $this->getTenantId();
        return app_role_has_module((string) $this->getUserRole(), $moduleKey, $tenantId > 0 ? $tenantId : null);
    }

    /**
     * Vérifier si l'utilisateur est administrateur
     */
    public function estAdmin() {
        return $this->aRole('admin');
    }

    /**
     * Vérifier si l'utilisateur est médecin
     */
    public function estMedecin() {
        return $this->aRole('medecin');
    }

    /**
     * Médecin ou sage-femme (fiche medecins, filtrage clinique identique).
     */
    public function estClinicienScope() {
        if (!function_exists('app_role_medecin_scope_roles')) {
            require_once __DIR__ . '/../includes/roles.php';
        }
        return $this->aUnRole(app_role_medecin_scope_roles());
    }

    /**
     * Vérifier si l'utilisateur est secrétaire
     */
    public function estSecretaire() {
        return $this->aRole('secretaire');
    }

    /**
     * Vérifier si l'utilisateur est infirmier
     */
    public function estInfirmier() {
        return $this->aRole('infirmier');
    }

    public function estComptable() {
        return $this->aRole('comptable');
    }

    public function peutEcrireFinances(): bool {
        if (!function_exists('app_role_finance_write_roles')) {
            require_once __DIR__ . '/../includes/roles.php';
        }
        return $this->aUnRole(app_role_finance_write_roles());
    }

    public function peutEcrirePaiements(): bool {
        if (!function_exists('app_role_paiements_write_roles')) {
            require_once __DIR__ . '/../includes/roles.php';
        }
        return $this->aUnRole(app_role_paiements_write_roles());
    }

    /**
     * Rediriger si non connecté ($redirect_url null = URL absolue depuis la racine web de l'app)
     */
    public function requireAuth($redirect_url = null) {
        if (!$this->estConnecte()) {
            if ($redirect_url === null) {
                $redirect_url = function_exists('efficasante_login_url') ? efficasante_login_url() : '/login.php';
            }
            header('Location: ' . $redirect_url);
            exit();
        }
        $this->ensureActiveTenant();
    }

    /**
     * Rediriger si n'a pas le rôle requis
     */
    public function requireRole($role, $redirect_url = null) {
        $this->requireAuth();

        if (!$this->aRole($role)) {
            if ($redirect_url === null) {
                $redirect_url = function_exists('efficasante_access_denied_url') ? efficasante_access_denied_url() : '/access_denied.php';
            }
            header('Location: ' . $redirect_url);
            exit();
        }
    }

    /**
     * Rediriger si n'a pas un des rôles requis
     */
    public function requireUnRole($roles, $redirect_url = null) {
        $this->requireAuth();

        if (!$this->aUnRole($roles)) {
            if ($redirect_url === null) {
                $redirect_url = function_exists('efficasante_access_denied_url') ? efficasante_access_denied_url() : '/access_denied.php';
            }
            header('Location: ' . $redirect_url);
            exit();
        }
    }

    /**
     * Générer un token CSRF
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifier un token CSRF
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Nettoyer les données de session
     */
    public function cleanup() {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 28800)) {
            $this->deconnecter();
        }
    }
}

// Initialiser l'instance d'authentification
$auth = Auth::getInstance();
