<?php
/**
 * Modèle Utilisateur
 * Gestion de l'authentification et des rôles
 */

require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';

class Utilisateur {
    private $conn;
    private $table_name = "utilisateurs";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Authentifier par email ou nom d'utilisateur (connexion SaaS multi-tenant).
     */
    public function authentifierIdentifiant($identifiant, $mot_de_passe) {
        require_once __DIR__ . '/../includes/saas/TenantSchema.php';
        TenantSchema::ensure();

        $identifiant = trim($identifiant);
        if ($identifiant === '') {
            return false;
        }

        $hasTenant = $this->columnExists('tenant_id');
        $hasPlatformAdmin = $this->columnExists('is_platform_admin');

        $cols = 'u.id, u.nom_utilisateur, u.email, u.mot_de_passe, u.role, u.statut';
        if ($hasTenant) {
            $cols .= ', u.tenant_id';
        }
        if ($hasPlatformAdmin) {
            $cols .= ', u.is_platform_admin';
        }

        if ($hasTenant) {
            $query = "SELECT {$cols}, t.status AS tenant_status, t.expires_at AS tenant_expires_at, t.license_type
                      FROM {$this->table_name} u
                      LEFT JOIN tenants t ON t.id = u.tenant_id
                      WHERE u.statut = 'actif' AND (u.email = :identifiant OR u.nom_utilisateur = :identifiant2)";
        } else {
            $query = "SELECT {$cols} FROM {$this->table_name} u
                      WHERE u.statut = 'actif' AND (u.email = :identifiant OR u.nom_utilisateur = :identifiant2)";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifiant', $identifiant);
        $stmt->bindParam(':identifiant2', $identifiant);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->resolveAuthenticatedRow($rows, $mot_de_passe, $hasTenant, $hasPlatformAdmin);
    }

    /**
     * Authentifier un utilisateur (email uniquement — legacy)
     */
    public function authentifier($email, $mot_de_passe) {
        require_once __DIR__ . '/../includes/saas/TenantSchema.php';
        TenantSchema::ensure();

        $hasTenant = $this->columnExists('tenant_id');
        $hasPlatformAdmin = $this->columnExists('is_platform_admin');

        $cols = 'u.id, u.nom_utilisateur, u.email, u.mot_de_passe, u.role, u.statut';
        if ($hasTenant) {
            $cols .= ', u.tenant_id';
        }
        if ($hasPlatformAdmin) {
            $cols .= ', u.is_platform_admin';
        }

        if ($hasTenant) {
            $query = "SELECT {$cols}, t.status AS tenant_status, t.expires_at AS tenant_expires_at, t.license_type
                      FROM {$this->table_name} u
                      LEFT JOIN tenants t ON t.id = u.tenant_id
                      WHERE u.email = :email AND u.statut = 'actif'";
        } else {
            $query = "SELECT {$cols} FROM {$this->table_name} u WHERE u.email = :email AND u.statut = 'actif'";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->resolveAuthenticatedRow($rows, $mot_de_passe, $hasTenant, $hasPlatformAdmin);
    }

    /**
     * Parmi plusieurs comptes partageant le même identifiant, ne retient que celui
     * dont le mot de passe correspond (évite le blocage multi-tenant sur « admin », etc.).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|false
     */
    private function resolveAuthenticatedRow(array $rows, string $mot_de_passe, bool $hasTenant, bool $hasPlatformAdmin)
    {
        if (count($rows) === 0) {
            return false;
        }

        $matches = [];
        foreach ($rows as $row) {
            if (!password_verify($mot_de_passe, $row['mot_de_passe'] ?? '')) {
                continue;
            }
            if ($hasTenant && empty($row['is_platform_admin']) && !empty($row['tenant_id'])) {
                if (($row['tenant_status'] ?? '') === 'suspended') {
                    continue;
                }
            }
            $matches[] = $row;
        }

        if (count($matches) !== 1) {
            return false;
        }

        return $matches[0];
    }

    private function columnExists(string $column): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$this->table_name, $column]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /** @return array{sql: string, tenant_id: ?int, deny: bool} */
    private function tenantFilter(): array
    {
        if (!$this->columnExists('tenant_id')) {
            return ['sql' => '', 'tenant_id' => null, 'deny' => false];
        }
        TenantContext::bindFromSession();
        $tenantId = TenantContext::getTenantId();
        if ($tenantId) {
            return ['sql' => ' AND tenant_id = :tenant_id', 'tenant_id' => (int) $tenantId, 'deny' => false];
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $enforce = !empty($_SESSION['user_connected']) && empty($_SESSION['is_platform_admin']);
        if ($enforce) {
            return ['sql' => ' AND 1 = 0', 'tenant_id' => null, 'deny' => true];
        }
        return ['sql' => '', 'tenant_id' => null, 'deny' => false];
    }

    private function bindTenantFilter(PDOStatement $stmt, array $tf): void
    {
        if ($tf['tenant_id'] !== null) {
            $stmt->bindValue(':tenant_id', $tf['tenant_id'], PDO::PARAM_INT);
        }
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function creer($nom_utilisateur, $email, $mot_de_passe, $role, $statut = 'actif', $tenant_id = null) {
        require_once __DIR__ . '/../includes/roles.php';
        if (!app_role_is_valid($role)) {
            return false;
        }
        require_once __DIR__ . '/../includes/saas/SubscriptionService.php';
        $subSvc = SubscriptionService::getInstance();
        $subSvc->loadForSession();

        if (!$subSvc->checkUserLimit()) {
            return false;
        }

        $hasTenant = $this->columnExists('tenant_id');
        if ($hasTenant && $tenant_id === null) {
            require_once __DIR__ . '/../includes/saas/TenantContext.php';
            TenantContext::bindFromSession();
            $tenant_id = TenantContext::getTenantId();
        }

        if ($hasTenant && empty($tenant_id)) {
            error_log('Utilisateur::creer — tenant_id manquant, création refusée.');
            return false;
        }

        if ($hasTenant) {
            $query = "INSERT INTO {$this->table_name} 
                      (tenant_id, nom_utilisateur, email, mot_de_passe, role, statut) 
                      VALUES (:tenant_id, :nom_utilisateur, :email, :mot_de_passe, :role, :statut)";
        } else {
            $query = "INSERT INTO {$this->table_name} 
                      (nom_utilisateur, email, mot_de_passe, role, statut) 
                      VALUES (:nom_utilisateur, :email, :mot_de_passe, :role, :statut)";
        }

        $stmt = $this->conn->prepare($query);
        $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        if ($hasTenant) {
            $stmt->bindParam(':tenant_id', $tenant_id);
        }
        $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':mot_de_passe', $mot_de_passe_hash);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':statut', $statut);

        return $stmt->execute() ? (int) $this->conn->lastInsertId() : false;
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function modifier($id, $nom_utilisateur, $email, $role, $statut) {
        require_once __DIR__ . '/../includes/roles.php';
        if (!app_role_is_valid($role)) {
            return false;
        }
        $tf = $this->tenantFilter();
        $query = "UPDATE " . $this->table_name . " 
                  SET nom_utilisateur = :nom_utilisateur, 
                      email = :email, 
                      role = :role, 
                      statut = :statut 
                  WHERE id = :id" . $tf['sql'];
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':statut', $statut);
        $this->bindTenantFilter($stmt, $tf);

        return $stmt->execute();
    }

    /**
     * Changer le mot de passe
     */
    public function changerMotDePasse($id, $nouveau_mot_de_passe) {
        $tf = $this->tenantFilter();
        $query = "UPDATE " . $this->table_name . " 
                  SET mot_de_passe = :mot_de_passe 
                  WHERE id = :id" . $tf['sql'];
        
        $stmt = $this->conn->prepare($query);
        
        $mot_de_passe_hash = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':mot_de_passe', $mot_de_passe_hash);
        $this->bindTenantFilter($stmt, $tf);

        return $stmt->execute();
    }

    /**
     * Récupérer tous les utilisateurs
     */
    public function getAll() {
        $tf = $this->tenantFilter();
        $query = "SELECT id, nom_utilisateur, email, role, statut, date_creation 
                  FROM " . $this->table_name;
        if ($tf['tenant_id'] !== null) {
            $query .= ' WHERE tenant_id = :tenant_id';
        }
        $query .= ' ORDER BY nom_utilisateur';
        
        $stmt = $this->conn->prepare($query);
        $this->bindTenantFilter($stmt, $tf);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function getById($id) {
        $tf = $this->tenantFilter();
        $query = "SELECT id, nom_utilisateur, email, role, statut, date_creation 
                  FROM " . $this->table_name . " 
                  WHERE id = :id" . $tf['sql'];
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $this->bindTenantFilter($stmt, $tf);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Supprimer un utilisateur (avec invalidation du cache)
     */
    public function supprimer($id) {
        try {
            $tf = $this->tenantFilter();
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id" . $tf['sql'];
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $this->bindTenantFilter($stmt, $tf);
            
            $result = $stmt->execute();

            if ($result) {
                require_once __DIR__ . '/../includes/staff_link.php';
                StaffLink::unlinkUser((int) $id);
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) {
                    // Ignorer les erreurs de cache, la suppression a réussi
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression de l'utilisateur ID $id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si un email existe déjà
     */
    public function emailExiste($email, $exclude_id = null) {
        $tf = $this->tenantFilter();
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email" . $tf['sql'];
        if ($exclude_id) {
            $query .= ' AND id != :exclude_id';
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $this->bindTenantFilter($stmt, $tf);
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifier si un nom d'utilisateur existe déjà
     */
    public function nomUtilisateurExiste($nom_utilisateur, $exclude_id = null) {
        $tf = $this->tenantFilter();
        $query = "SELECT id FROM " . $this->table_name . " WHERE nom_utilisateur = :nom_utilisateur" . $tf['sql'];
        if ($exclude_id) {
            $query .= ' AND id != :exclude_id';
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $this->bindTenantFilter($stmt, $tf);
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtenir les statistiques des utilisateurs
     */
    public function getStats() {
        $tf = $this->tenantFilter();
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs,
                    SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) as inactifs,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'medecin' THEN 1 ELSE 0 END) as medecins,
                    SUM(CASE WHEN role = 'secretaire' THEN 1 ELSE 0 END) as secretaires,
                    SUM(CASE WHEN role = 'infirmier' THEN 1 ELSE 0 END) as infirmiers,
                    SUM(CASE WHEN role = 'comptable' THEN 1 ELSE 0 END) as comptables,
                    SUM(CASE WHEN role = 'pharmacien' THEN 1 ELSE 0 END) as pharmaciens,
                    SUM(CASE WHEN role = 'laborantin' THEN 1 ELSE 0 END) as laborantins,
                    SUM(CASE WHEN role = 'technicien' THEN 1 ELSE 0 END) as techniciens
                  FROM " . $this->table_name;
        if ($tf['tenant_id'] !== null) {
            $query .= ' WHERE tenant_id = :tenant_id';
        }
        
        $stmt = $this->conn->prepare($query);
        $this->bindTenantFilter($stmt, $tf);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Utilisateurs actifs du tenant courant (liste destinataires, etc.).
     *
     * @return list<array<string, mixed>>
     */
    public function listActifsForTenant() {
        $tf = $this->tenantFilter();
        $sql = "SELECT id, nom_utilisateur, email, role
                FROM {$this->table_name}
                WHERE statut = 'actif'" . $tf['sql'] . '
                ORDER BY nom_utilisateur';
        $stmt = $this->conn->prepare($sql);
        $this->bindTenantFilter($stmt, $tf);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
?>

