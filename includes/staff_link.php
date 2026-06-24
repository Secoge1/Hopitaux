<?php

/**

 * Liaison compte utilisateur ↔ fiche du registre unifié (table medecins).

 */



require_once __DIR__ . '/saas/TenantScope.php';

require_once __DIR__ . '/saas/TenantContext.php';

require_once __DIR__ . '/roles.php';

require_once __DIR__ . '/medecin_profil.php';

require_once __DIR__ . '/staff_mirror.php';



class StaffLink

{

    /** Rôles rattachés via le module Médecins (tous profils cliniques). */

    private const LINK_ROLES = ['medecin', 'sage_femme', 'infirmier', 'laborantin', 'technicien', 'pharmacien'];



    public static function linkTypeForRole(string $role): ?string

    {

        return in_array($role, self::LINK_ROLES, true) ? 'medecin' : null;

    }



    public static function roleNeedsLink(string $role): bool

    {

        return self::linkTypeForRole($role) !== null;

    }



    private static function pdo(): PDO

    {

        require_once __DIR__ . '/../config/db.php';

        TenantContext::bindFromSession();

        return getDB();

    }



    private static function hasColumn(PDO $pdo, string $table, string $column): bool

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



    private static function buildMedecinLabel(array $row): string

    {

        $type = strtolower((string) ($row['type_profil'] ?? 'medecin'));

        $label = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));

        if (self::hasColumn(self::pdo(), 'medecins', 'type_profil')) {

            $label .= ' — ' . medecin_profil_label($type);

        }

        if (!empty($row['specialite']) && !in_array($type, ['infirmier', 'laborantin', 'technicien', 'pharmacien'], true)) {

            $label .= ' (' . $row['specialite'] . ')';

        }

        return $label;

    }



    /**

     * @return list<array{id: int, label: string, linked_user_id: ?int, type_profil: string}>

     */

    public static function listMedecinsForSelect(?string $forRole = null): array

    {

        $pdo = self::pdo();

        if (!self::hasColumn($pdo, 'medecins', 'utilisateur_id')) {

            return [];

        }

        $where = ["(m.statut IS NULL OR m.statut <> 'supprime')"];

        $params = [];

        if ($forRole !== null && $forRole !== '' && self::hasColumn($pdo, 'medecins', 'type_profil')) {

            $types = medecin_profil_types_for_role($forRole);

            if ($types !== []) {

                $placeholders = implode(', ', array_fill(0, count($types), '?'));

                if (in_array('medecin', $types, true)) {
                    $where[] = "(m.type_profil IN ({$placeholders}) OR m.type_profil IS NULL OR m.type_profil = '')";
                } else {
                    $where[] = "m.type_profil IN ({$placeholders})";
                }

                $params = array_merge($params, $types);

            }

        }

        TenantScope::appendWhere($pdo, 'medecins', $where, $params, 'm');

        $cols = 'm.id, m.nom, m.prenom, m.specialite, m.utilisateur_id';

        if (self::hasColumn($pdo, 'medecins', 'type_profil')) {

            $cols .= ', m.type_profil';

        }

        $sql = "SELECT {$cols} FROM medecins m WHERE " . implode(' AND ', $where) . ' ORDER BY m.nom, m.prenom';

        $stmt = $pdo->prepare($sql);

        $stmt->execute($params);

        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $type = strtolower((string) ($row['type_profil'] ?? 'medecin'));

            $out[] = [

                'id' => (int) $row['id'],

                'label' => self::buildMedecinLabel($row),

                'linked_user_id' => !empty($row['utilisateur_id']) ? (int) $row['utilisateur_id'] : null,

                'type_profil' => $type,

            ];

        }

        return $out;

    }



    /** @deprecated Utiliser listMedecinsForSelect — compatibilité */

    public static function listPersonnelForSelect(): array

    {

        return self::listMedecinsForSelect();

    }



    /**

     * @return array{type: ?string, id: ?int, label: ?string}

     */

    public static function getLinkForUser(int $userId): array

    {

        $empty = ['type' => null, 'id' => null, 'label' => null];

        if ($userId <= 0) {

            return $empty;

        }

        $pdo = self::pdo();



        if (self::hasColumn($pdo, 'medecins', 'utilisateur_id')) {

            $where = ['m.utilisateur_id = ?', "(m.statut IS NULL OR m.statut <> 'supprime')"];

            $params = [$userId];

            TenantScope::appendWhere($pdo, 'medecins', $where, $params, 'm');

            $cols = 'm.id, m.nom, m.prenom, m.specialite';

            if (self::hasColumn($pdo, 'medecins', 'type_profil')) {

                $cols .= ', m.type_profil';

            }

            $stmt = $pdo->prepare(

                "SELECT {$cols} FROM medecins m WHERE " . implode(' AND ', $where) . ' LIMIT 1'

            );

            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {

                return ['type' => 'medecin', 'id' => (int) $row['id'], 'label' => self::buildMedecinLabel($row)];

            }

        }



        if (self::hasColumn($pdo, 'personnel', 'utilisateur_id')) {

            $where = ['p.utilisateur_id = ?', "(p.statut IS NULL OR p.statut <> 'inactif')"];

            $params = [$userId];

            TenantScope::appendWhere($pdo, 'personnel', $where, $params, 'p');

            $stmt = $pdo->prepare(

                'SELECT p.id, p.nom, p.prenom, p.poste FROM personnel p WHERE ' . implode(' AND ', $where) . ' LIMIT 1'

            );

            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {

                $label = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));

                if (!empty($row['poste'])) {

                    $label .= ' — ' . $row['poste'];

                }

                return ['type' => 'medecin', 'id' => (int) $row['id'], 'label' => $label . ' (ancien Personnel)'];

            }

        }



        return $empty;

    }



    /**

     * @param list<int> $userIds

     * @return array<int, array{type: ?string, id: ?int, label: ?string}>

     */

    public static function getLinksForUsers(array $userIds): array

    {

        $map = [];

        foreach ($userIds as $uid) {

            $map[(int) $uid] = ['type' => null, 'id' => null, 'label' => null];

        }

        foreach ($userIds as $uid) {

            $link = self::getLinkForUser((int) $uid);

            if ($link['id']) {

                $map[(int) $uid] = $link;

            }

        }

        return $map;

    }



    public static function unlinkUser(int $userId): void

    {

        if ($userId <= 0) {

            return;

        }

        $pdo = self::pdo();

        if (self::hasColumn($pdo, 'medecins', 'utilisateur_id')) {

            $where = ['utilisateur_id = ?'];

            $params = [$userId];

            TenantScope::updateWhere($pdo, 'medecins', 'utilisateur_id = NULL', $where, $params);

        }

        if (self::hasColumn($pdo, 'personnel', 'utilisateur_id')) {

            $where = ['utilisateur_id = ?'];

            $params = [$userId];

            TenantScope::updateWhere($pdo, 'personnel', 'utilisateur_id = NULL', $where, $params);

        }

    }



    /**

     * @return array{ok: bool, message: string}

     */

    public static function syncForUser(int $userId, string $role, ?int $recordId): array

    {

        if ($userId <= 0) {

            return ['ok' => false, 'message' => 'Utilisateur invalide.'];

        }



        self::unlinkUser($userId);



        if (self::linkTypeForRole($role) === null) {

            return ['ok' => true, 'message' => ''];

        }



        if (!$recordId || $recordId <= 0) {

            return ['ok' => true, 'message' => ''];

        }



        $available = self::listMedecinsForSelect($role);

        $allowedIds = array_map(static fn (array $row): int => (int) $row['id'], $available);

        if (!in_array($recordId, $allowedIds, true)) {

            return [

                'ok' => false,

                'message' => 'Fiche introuvable ou inactive. Créez une fiche dans le module Médecins (Profil : '

                    . medecin_profil_label(medecin_profil_default_for_role($role)) . '), puis réessayez.',

            ];

        }



        $pdo = self::pdo();

        if (!self::hasColumn($pdo, 'medecins', 'utilisateur_id')) {

            return ['ok' => false, 'message' => 'Colonne medecins.utilisateur_id absente.'];

        }



        $where = ['id = ?'];

        $params = [$recordId];

        TenantScope::appendWhere($pdo, 'medecins', $where, $params);

        $stmt = $pdo->prepare('SELECT id FROM medecins WHERE ' . implode(' AND ', $where) . ' LIMIT 1');

        $stmt->execute($params);

        if (!$stmt->fetchColumn()) {

            return ['ok' => false, 'message' => 'Fiche professionnelle introuvable.'];

        }



        $whereClear = ['utilisateur_id = ?', 'id <> ?'];

        $paramsClear = [$userId, $recordId];

        TenantScope::appendWhere($pdo, 'medecins', $whereClear, $paramsClear);

        $pdo->prepare('UPDATE medecins SET utilisateur_id = NULL WHERE ' . implode(' AND ', $whereClear))->execute($paramsClear);



        $whereSet = ['id = ?'];

        $paramsSet = [$recordId];

        TenantScope::appendWhere($pdo, 'medecins', $whereSet, $paramsSet);

        $pdo->prepare('UPDATE medecins SET utilisateur_id = ? WHERE ' . implode(' AND ', $whereSet))

            ->execute(array_merge([$userId], $paramsSet));



        StaffMirror::syncUtilisateurFromMedecinLink($recordId, $userId);



        return ['ok' => true, 'message' => ''];

    }

}



if (!function_exists('app_role_staff_link_type')) {

    function app_role_staff_link_type(string $role): ?string

    {

        return StaffLink::linkTypeForRole($role);

    }

}



if (!function_exists('app_role_staff_link_label')) {

    function app_role_staff_link_label(string $role): string

    {

        return StaffLink::roleNeedsLink($role) ? 'Fiche professionnelle (Médecins)' : 'Fiche métier';

    }

}

