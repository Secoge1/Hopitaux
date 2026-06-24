<?php

/**

 * Miroir personnel pour les fiches medecins (infirmier, laborantin, etc.).

 * Permet au laboratoire et aux soins de continuer à utiliser personnel_id / technicien_id.

 */



require_once __DIR__ . '/medecin_profil.php';

require_once __DIR__ . '/saas/TenantScope.php';

require_once __DIR__ . '/saas/TenantContext.php';



class StaffMirror

{

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



    /**

     * Crée ou met à jour la fiche personnel liée à une fiche medecins.

     *

     * @return ?int personnel.id

     */

    public static function syncPersonnelFromMedecin(int $medecinId): ?int

    {

        if ($medecinId <= 0) {

            return null;

        }



        $pdo = self::pdo();

        if (!self::hasColumn($pdo, 'medecins', 'personnel_id') || !self::hasColumn($pdo, 'medecins', 'type_profil')) {

            return null;

        }



        $where = ['m.id = ?', "(m.statut IS NULL OR m.statut <> 'supprime')"];

        $params = [$medecinId];

        TenantScope::appendWhere($pdo, 'medecins', $where, $params, 'm');

        $stmt = $pdo->prepare(

            'SELECT m.* FROM medecins m WHERE ' . implode(' AND ', $where) . ' LIMIT 1'

        );

        $stmt->execute($params);

        $med = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$med) {

            return null;

        }



        $type = strtolower((string) ($med['type_profil'] ?? 'medecin'));

        if (!medecin_profil_needs_personnel_mirror($type)) {

            return null;

        }



        require_once __DIR__ . '/../models/Personnel.php';

        $personnelModel = new Personnel();



        $personnelId = !empty($med['personnel_id']) ? (int) $med['personnel_id'] : 0;

        $personnelData = [

            'nom' => $med['nom'] ?? '',

            'prenom' => $med['prenom'] ?? '',

            'telephone' => $med['telephone'] ?? null,

            'email' => $med['email'] ?? null,

            'adresse' => $med['adresse'] ?? null,

            'ville' => $med['ville'] ?? null,

            'code_postal' => $med['code_postal'] ?? null,

            'pays' => $med['pays'] ?? 'Mali',

            'poste' => medecin_profil_poste_label($type),

            'departement' => medecin_profil_departement_default($type),

            'date_embauche' => $med['date_embauche'] ?? date('Y-m-d'),

            'statut' => self::mapPersonnelStatut($med['statut'] ?? 'actif'),

        ];



        if (!empty($med['utilisateur_id']) && self::hasColumn($pdo, 'personnel', 'utilisateur_id')) {

            $personnelData['utilisateur_id'] = (int) $med['utilisateur_id'];

        }



        if ($personnelId > 0) {

            $existing = $personnelModel->getById($personnelId);

            if ($existing) {

                $personnelModel->update($personnelId, $personnelData);

                self::syncUtilisateurOnPersonnel($pdo, $personnelId, (int) ($med['utilisateur_id'] ?? 0));

                return $personnelId;

            }

        }



        $newId = $personnelModel->create($personnelData);

        if (!$newId) {

            return null;

        }

        $personnelId = (int) $newId;



        $whereSet = ['id = ?'];

        $paramsSet = [$medecinId];

        TenantScope::appendWhere($pdo, 'medecins', $whereSet, $paramsSet);

        $pdo->prepare('UPDATE medecins SET personnel_id = ? WHERE ' . implode(' AND ', $whereSet))

            ->execute(array_merge([$personnelId], $paramsSet));



        self::syncUtilisateurOnPersonnel($pdo, $personnelId, (int) ($med['utilisateur_id'] ?? 0));



        return $personnelId;

    }



    public static function syncUtilisateurFromMedecinLink(int $medecinId, int $userId): void

    {

        $personnelId = self::syncPersonnelFromMedecin($medecinId);

        if ($personnelId && $userId > 0) {

            self::syncUtilisateurOnPersonnel(self::pdo(), $personnelId, $userId);

        }

    }



    private static function syncUtilisateurOnPersonnel(PDO $pdo, int $personnelId, int $userId): void

    {

        if ($personnelId <= 0 || !self::hasColumn($pdo, 'personnel', 'utilisateur_id')) {

            return;

        }

        $whereClear = ['utilisateur_id = ?', 'id <> ?'];

        $paramsClear = [$userId, $personnelId];

        TenantScope::updateWhere($pdo, 'personnel', 'utilisateur_id = NULL', $whereClear, $paramsClear);



        $whereSet = ['id = ?'];

        $paramsSet = [$personnelId];

        TenantScope::appendWhere($pdo, 'personnel', $whereSet, $paramsSet);

        $pdo->prepare('UPDATE personnel SET utilisateur_id = ? WHERE ' . implode(' AND ', $whereSet))

            ->execute(array_merge([$userId > 0 ? $userId : null], $paramsSet));

    }



    private static function mapPersonnelStatut(string $statut): string

    {

        $statut = strtolower($statut);

        if ($statut === 'conge') {

            return 'actif';

        }

        if (in_array($statut, ['actif', 'inactif', 'suspendu'], true)) {

            return $statut;

        }

        return 'actif';

    }

}

