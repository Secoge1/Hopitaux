<?php
/**
 * Filtrage des données par membre du personnel connecté (au sein du tenant).
 * Admin / secrétaire / comptable : vue établissement (tenant) inchangée.
 */

require_once __DIR__ . '/saas/TenantScope.php';
require_once __DIR__ . '/saas/TenantContext.php';
require_once __DIR__ . '/roles.php';

class StaffScope
{
    /** Rôles limités à « leurs » patients / actes. */
    private const SCOPED_ROLES = ['medecin', 'sage_femme', 'infirmier', 'laborantin', 'technicien'];

    private static function roleUsesMedecinScope(?string $role): bool
    {
        return $role !== null && app_role_has_medecin_scope($role);
    }

    private static ?array $ctx = null;

    public static function reset(): void
    {
        self::$ctx = null;
    }

    /**
     * @return array{
     *   active: bool,
     *   role: ?string,
     *   user_id: ?int,
     *   medecin_id: ?int,
     *   personnel_id: ?int,
     *   linked: bool
     * }
     */
    public static function context(): array
    {
        if (self::$ctx !== null) {
            return self::$ctx;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_connected']) || empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
            self::$ctx = ['active' => false, 'role' => null, 'user_id' => null, 'medecin_id' => null, 'personnel_id' => null, 'linked' => false];
            return self::$ctx;
        }

        if (!empty($_SESSION['is_platform_admin'])) {
            self::$ctx = ['active' => false, 'role' => null, 'user_id' => null, 'medecin_id' => null, 'personnel_id' => null, 'linked' => false];
            return self::$ctx;
        }

        $role = (string) $_SESSION['user_role'];
        $userId = (int) $_SESSION['user_id'];
        $active = in_array($role, self::SCOPED_ROLES, true);

        $medecinId = null;
        $personnelId = null;

        if ($active) {
            $pdo = getDB();
            TenantContext::bindFromSession();
            $medecinId = self::resolveMedecinId($pdo, $userId);
            if (!self::roleUsesMedecinScope($role)) {
                $personnelId = self::resolvePersonnelId($pdo, $userId);
            }
        }

        $linked = !$active
            || (self::roleUsesMedecinScope($role) && $medecinId > 0)
            || (!self::roleUsesMedecinScope($role) && ($personnelId > 0 || $medecinId > 0));

        self::$ctx = [
            'active'   => $active,
            'role'     => $role,
            'user_id'    => $userId,
            'medecin_id' => $medecinId,
            'personnel_id' => $personnelId,
            'linked'   => $linked,
        ];

        return self::$ctx;
    }

    public static function isActive(): bool
    {
        return self::context()['active'];
    }

    public static function isLinked(): bool
    {
        return self::context()['linked'];
    }

    private static function resolveMedecinId(PDO $pdo, int $userId): ?int
    {
        $user = self::loadUser($pdo, $userId);
        if (!$user) {
            return null;
        }

        if (self::columnExists($pdo, 'medecins', 'utilisateur_id')) {
            $where = ['utilisateur_id = ?', "(statut IS NULL OR statut <> 'supprime')"];
            $params = [$userId];
            TenantScope::appendWhere($pdo, 'medecins', $where, $params);
            $stmt = $pdo->prepare('SELECT id FROM medecins WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        if (!empty($user['email'])) {
            $where = ['email = ?', "(statut IS NULL OR statut <> 'supprime')"];
            $params = [$user['email']];
            TenantScope::appendWhere($pdo, 'medecins', $where, $params);
            $stmt = $pdo->prepare('SELECT id FROM medecins WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private static function resolvePersonnelId(PDO $pdo, int $userId): ?int
    {
        $user = self::loadUser($pdo, $userId);
        if (!$user) {
            return null;
        }

        if (self::columnExists($pdo, 'personnel', 'utilisateur_id')) {
            $where = ['utilisateur_id = ?', "statut = 'actif'"];
            $params = [$userId];
            TenantScope::appendWhere($pdo, 'personnel', $where, $params);
            $stmt = $pdo->prepare('SELECT id FROM personnel WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        if (!empty($user['email'])) {
            $where = ['email = ?', "statut = 'actif'"];
            $params = [$user['email']];
            TenantScope::appendWhere($pdo, 'personnel', $where, $params);
            $stmt = $pdo->prepare('SELECT id FROM personnel WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private static function loadUser(PDO $pdo, int $userId): ?array
    {
        $where = ['id = ?'];
        $params = [$userId];
        TenantScope::appendWhere($pdo, 'utilisateurs', $where, $params);
        $stmt = $pdo->prepare('SELECT id, email, nom_utilisateur, role FROM utilisateurs WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = "$table.$column";
        if (!isset($cache[$key])) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            $cache[$key] = (bool) $stmt->fetchColumn();
        }
        return $cache[$key];
    }

    /** Aucune ligne si rôle scopé sans fiche liée. */
    public static function appendDenyIfUnlinked(array &$where): void
    {
        $ctx = self::context();
        if ($ctx['active'] && !$ctx['linked']) {
            $where[] = '1 = 0';
        }
    }

    private static function sqlCol(string $alias, string $column): string
    {
        return $alias !== '' ? rtrim($alias, '.') . '.' . $column : $column;
    }

    public static function appendConsultationFilter(array &$where, array &$params, string $alias = 'c'): void
    {
        self::appendDenyIfUnlinked($where);
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked']) {
            return;
        }

        if (self::roleUsesMedecinScope($ctx['role']) && $ctx['medecin_id']) {
            $where[] = self::sqlCol($alias, 'medecin_id') . ' = ?';
            $params[] = $ctx['medecin_id'];
            return;
        }

        if ($ctx['role'] === 'infirmier' && $ctx['personnel_id'] && self::columnExists(getDB(), 'consultation_soins', 'personnel_id')) {
            $where[] = self::sqlCol($alias, 'id') . ' IN (
                SELECT cs.consultation_id FROM consultation_soins cs
                WHERE cs.personnel_id = ?' . TenantScope::andOwnedByTenant(getDB(), 'consultation_soins', 'cs') . '
            )';
            $params[] = $ctx['personnel_id'];
            if ($owned = TenantScope::ownedParam(getDB(), 'consultation_soins')) {
                $params = array_merge($params, $owned);
            }
            return;
        }

        if (in_array($ctx['role'], ['laborantin', 'technicien'], true)) {
            $where[] = '1 = 0';
        }
    }

    public static function appendRdvFilter(array &$where, array &$params, string $alias = 'rv'): void
    {
        self::appendDenyIfUnlinked($where);
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked']) {
            return;
        }

        if (self::roleUsesMedecinScope($ctx['role']) && $ctx['medecin_id']) {
            $where[] = self::sqlCol($alias, 'medecin_id') . ' = ?';
            $params[] = $ctx['medecin_id'];
            return;
        }

        if (in_array($ctx['role'], ['laborantin', 'technicien', 'infirmier'], true)) {
            $where[] = '1 = 0';
        }
    }

    public static function appendAnalyseFilter(array &$where, array &$params, string $alias = 'a'): void
    {
        self::appendDenyIfUnlinked($where);
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked']) {
            return;
        }

        if (self::roleUsesMedecinScope($ctx['role']) && $ctx['medecin_id']) {
            $where[] = self::sqlCol($alias, 'medecin_id') . ' = ?';
            $params[] = $ctx['medecin_id'];
            return;
        }

        if (in_array($ctx['role'], ['laborantin', 'technicien'], true) && $ctx['personnel_id']
            && self::columnExists(getDB(), 'analyses', 'technicien_id')) {
            $where[] = self::sqlCol($alias, 'technicien_id') . ' = ?';
            $params[] = $ctx['personnel_id'];
            return;
        }

        if ($ctx['role'] === 'infirmier' && $ctx['personnel_id']
            && self::columnExists(getDB(), 'consultation_soins', 'personnel_id')) {
            $pdo = getDB();
            $where[] = self::sqlCol($alias, 'patient_id') . ' IN (
                SELECT DISTINCT c.patient_id FROM consultations c
                INNER JOIN consultation_soins cs ON cs.consultation_id = c.id
                WHERE cs.personnel_id = ?' . TenantScope::andOwnedByTenant($pdo, 'consultation_soins', 'cs') . '
            )';
            $params[] = $ctx['personnel_id'];
            if ($t = TenantScope::ownedParam($pdo, 'consultation_soins')) {
                $params = array_merge($params, $t);
            }
        }
    }

    public static function appendPatientFilter(array &$where, array &$params, string $alias = 'p'): void
    {
        self::appendDenyIfUnlinked($where);
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked']) {
            return;
        }

        $pdo = getDB();
        $col = $alias !== '' ? rtrim($alias, '.') . '.id' : 'id';

        if (self::roleUsesMedecinScope($ctx['role']) && $ctx['medecin_id']) {
            $parts = [
                $col . ' IN (
                SELECT DISTINCT c.patient_id FROM consultations c WHERE c.medecin_id = ?' . TenantScope::andOwnedByTenant($pdo, 'consultations', 'c') . '
            )',
                $col . ' IN (
                SELECT DISTINCT rv.patient_id FROM rendez_vous rv WHERE rv.medecin_id = ?' . TenantScope::andOwnedByTenant($pdo, 'rendez_vous', 'rv') . '
            )',
            ];
            $params[] = $ctx['medecin_id'];
            if ($t = TenantScope::ownedParam($pdo, 'consultations')) {
                $params = array_merge($params, $t);
            }
            $params[] = $ctx['medecin_id'];
            if ($t = TenantScope::ownedParam($pdo, 'rendez_vous')) {
                $params = array_merge($params, $t);
            }
            if (self::columnExists($pdo, 'patients', 'medecin_referent_id')) {
                $parts[] = self::sqlCol($alias, 'medecin_referent_id') . ' = ?';
                $params[] = $ctx['medecin_id'];
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
            return;
        }

        if ($ctx['role'] === 'infirmier' && $ctx['personnel_id']
            && self::columnExists($pdo, 'consultation_soins', 'personnel_id')) {
            $where[] = "$col IN (
                SELECT DISTINCT c.patient_id FROM consultations c
                INNER JOIN consultation_soins cs ON cs.consultation_id = c.id
                WHERE cs.personnel_id = ?" . TenantScope::andOwnedByTenant($pdo, 'consultation_soins', 'cs') . '
            )';
            $params[] = $ctx['personnel_id'];
            if ($t = TenantScope::ownedParam($pdo, 'consultation_soins')) {
                $params = array_merge($params, $t);
            }
            return;
        }

        if (in_array($ctx['role'], ['laborantin', 'technicien'], true) && $ctx['personnel_id']
            && self::columnExists($pdo, 'analyses', 'technicien_id')) {
            $where[] = "$col IN (
                SELECT DISTINCT a.patient_id FROM analyses a WHERE a.technicien_id = ?" . TenantScope::andOwnedByTenant($pdo, 'analyses', 'a') . '
            )';
            $params[] = $ctx['personnel_id'];
            if ($t = TenantScope::ownedParam($pdo, 'analyses')) {
                $params = array_merge($params, $t);
            }
            return;
        }

        if ($ctx['role'] === 'technicien') {
            $where[] = '1 = 0';
        }
    }

    public static function canAccessMedecin(?array $row): bool
    {
        if (!$row || !self::isActive()) {
            return (bool) $row;
        }
        if (!self::isLinked()) {
            return false;
        }
        $ctx = self::context();
        if ($ctx['medecin_id']) {
            return (int) ($row['id'] ?? 0) === (int) $ctx['medecin_id'];
        }
        return !self::isActive();
    }

    public static function appendMedecinFilter(array &$where, array &$params, string $alias = ''): void
    {
        self::appendDenyIfUnlinked($where);
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked'] || !$ctx['medecin_id']) {
            return;
        }
        $col = $alias !== '' ? rtrim($alias, '.') . '.id' : 'id';
        $where[] = "{$col} = ?";
        $params[] = (int) $ctx['medecin_id'];
    }

    public static function canAccessConsultation(?array $row): bool
    {
        if (!$row || !self::isActive()) {
            return true;
        }
        if (!self::isLinked()) {
            return false;
        }
        $ctx = self::context();
        if (self::roleUsesMedecinScope($ctx['role'])) {
            return (int) ($row['medecin_id'] ?? 0) === (int) $ctx['medecin_id'];
        }
        if ($ctx['role'] === 'infirmier' && self::columnExists(getDB(), 'consultation_soins', 'personnel_id')) {
            $pdo = getDB();
            $where = ['cs.consultation_id = ?', 'cs.personnel_id = ?'];
            $params = [(int) $row['id'], (int) $ctx['personnel_id']];
            TenantScope::appendWhere($pdo, 'consultation_soins', $where, $params, 'cs');
            $stmt = $pdo->prepare('SELECT 1 FROM consultation_soins cs WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            return (bool) $stmt->fetchColumn();
        }
        return true;
    }

    public static function canAccessRdv(?array $row): bool
    {
        if (!$row || !self::isActive()) {
            return (bool) $row;
        }
        if (!self::isLinked()) {
            return false;
        }
        $ctx = self::context();
        if (self::roleUsesMedecinScope($ctx['role'])) {
            return (int) ($row['medecin_id'] ?? 0) === (int) $ctx['medecin_id'];
        }
        return true;
    }

    public static function canAccessPatient(?array $row): bool
    {
        if (!$row) {
            return false;
        }
        if (!self::isActive()) {
            return true;
        }
        if (!self::isLinked()) {
            return false;
        }
        $where = ['1=1'];
        $params = [];
        self::appendPatientFilter($where, $params, 'p');
        $pdo = getDB();
        $where[] = 'p.id = ?';
        $params[] = (int) $row['id'];
        TenantScope::appendWhere($pdo, 'patients', $where, $params, 'p');
        $stmt = $pdo->prepare('SELECT 1 FROM patients p WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public static function canAccessAnalyse(?array $row): bool
    {
        if (!$row || !self::isActive()) {
            return true;
        }
        if (!self::isLinked()) {
            return false;
        }
        $ctx = self::context();
        if (self::roleUsesMedecinScope($ctx['role'])) {
            return (int) ($row['medecin_id'] ?? 0) === (int) $ctx['medecin_id'];
        }
        if (in_array($ctx['role'], ['laborantin', 'technicien'], true)) {
            return (int) ($row['technicien_id'] ?? 0) === (int) $ctx['personnel_id'];
        }
        if ($ctx['role'] === 'infirmier') {
            return self::canAccessPatient(['id' => (int) ($row['patient_id'] ?? 0)]);
        }
        return true;
    }

    /**
     * Médecin référent à enregistrer lors de la création d'un patient (médecin connecté lié).
     */
    public static function medecinReferentIdForPatientCreate(): ?int
    {
        $ctx = self::context();
        if (!$ctx['active'] || !$ctx['linked'] || !self::roleUsesMedecinScope($ctx['role']) || !$ctx['medecin_id']) {
            return null;
        }
        if (!self::columnExists(getDB(), 'patients', 'medecin_referent_id')) {
            return null;
        }
        return (int) $ctx['medecin_id'];
    }

    /** Admin, secrétaire, infirmier : choisir le médecin référent d'un patient. */
    public static function canAssignPatientMedecin(): bool
    {
        if (!self::columnExists(getDB(), 'patients', 'medecin_referent_id')) {
            return false;
        }
        $ctx = self::context();
        if (!empty($_SESSION['is_platform_admin'])) {
            return true;
        }
        return in_array($ctx['role'] ?? '', ['admin', 'secretaire', 'infirmier'], true);
    }

    /**
     * Valide et retourne l'ID médecin référent soumis (formulaire patient / accueil).
     */
    public static function resolveMedecinReferentIdForForm(?int $postedId): ?int
    {
        if (!self::canAssignPatientMedecin()) {
            return self::medecinReferentIdForPatientCreate();
        }
        if ($postedId === null || $postedId <= 0) {
            return null;
        }
        $pdo = getDB();
        $where = ['id = ?', "statut != 'supprime'"];
        $params = [$postedId];
        TenantScope::appendWhere($pdo, 'medecins', $where, $params);
        $stmt = $pdo->prepare('SELECT id FROM medecins WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Création consultation d'accueil + ticket depuis le module patients. */
    public static function canRegisterConsultationFromPatients(): bool
    {
        $ctx = self::context();
        if (!empty($_SESSION['is_platform_admin'])) {
            return true;
        }
        return in_array($ctx['role'] ?? '', ['admin', 'secretaire', 'sage_femme', 'infirmier', 'medecin'], true);
    }

    /** Création analyse d'accueil + ticket caisse depuis le module patients. */
    public static function canRegisterAnalyseFromPatients(): bool
    {
        return self::canRegisterConsultationFromPatients();
    }

    /** Message flash si compte non rattaché à une fiche médecin / personnel. */
    public static function flashIfUnlinked(): void
    {
        $ctx = self::context();
        if ($ctx['active'] && !$ctx['linked']) {
            $_SESSION['flash_message'] = 'Votre compte n\'est pas encore rattaché à une fiche médecin ou personnel. Contactez l\'administrateur pour voir vos dossiers.';
            $_SESSION['flash_type'] = 'warning';
        }
    }

    /** Admin / médecin peuvent choisir le technicien ; laborantin = auto-assignation. */
    public static function canPickTechnicienOnAnalyse(): bool
    {
        $ctx = self::context();
        if (!$ctx['active']) {
            return true;
        }
        return !in_array($ctx['role'], ['laborantin', 'technicien'], true);
    }

    /**
     * ID personnel (technicien) pour une analyse : auto pour laborantin, choix validé sinon.
     */
    public static function technicienIdForAnalyseForm(?int $postedId): ?int
    {
        $pdo = getDB();
        if (!self::columnExists($pdo, 'analyses', 'technicien_id')) {
            return null;
        }
        $ctx = self::context();
        if (in_array($ctx['role'], ['laborantin', 'technicien'], true)) {
            return ($ctx['personnel_id'] ?? 0) > 0 ? (int) $ctx['personnel_id'] : null;
        }
        if ($postedId <= 0) {
            return null;
        }
        $where = ['id = ?', "statut = 'actif'"];
        $params = [$postedId];
        TenantScope::appendWhere($pdo, 'personnel', $where, $params);
        $stmt = $pdo->prepare('SELECT id FROM personnel WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Assigne le technicien connecté si l'analyse n'en a pas encore. */
    public static function technicienIdForAnalyseClaim(?int $currentTechnicienId): ?int
    {
        if ($currentTechnicienId > 0) {
            return null;
        }
        $ctx = self::context();
        if (!in_array($ctx['role'], ['laborantin', 'technicien'], true) || !$ctx['personnel_id']) {
            return null;
        }
        return (int) $ctx['personnel_id'];
    }
}
