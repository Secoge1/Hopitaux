<?php

/**

 * Garde d'accès par module (rôles + bootstrap API JSON).

 */



require_once __DIR__ . '/../config/Auth.php';

require_once __DIR__ . '/roles.php';



function module_roles_for(string $moduleKey): array

{

    return app_module_roles($moduleKey);

}



function module_access_denied_url(): string

{

    return function_exists('efficasante_access_denied_url')

        ? efficasante_access_denied_url()

        : '/access_denied.php';

}



/**

 * Auth + contrôle de rôle pour une page module.

 */

function module_require_roles(string $moduleKey, ?string $redirectUrl = null): void

{

    $auth = Auth::getInstance();

    $auth->requireAuth();

    if (!$auth->aAccesModule($moduleKey)) {

        header('Location: ' . ($redirectUrl ?? module_access_denied_url()));

        exit;

    }

}



/**

 * Bootstrap sécurisé pour endpoints JSON/AJAX.

 */

function module_api_guard(string $moduleKey, ?array $roles = null): Auth

{

    if (!headers_sent()) {

        header('Content-Type: application/json; charset=utf-8');

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    }



    $auth = Auth::getInstance();

    if (!$auth->estConnecte()) {

        http_response_code(401);

        echo json_encode(['success' => false, 'error' => 'Non autorisé'], JSON_UNESCAPED_UNICODE);

        exit;

    }



    if ($roles !== null) {

        if (!$auth->aUnRole($roles)) {

            http_response_code(403);

            echo json_encode(['success' => false, 'error' => 'Accès refusé'], JSON_UNESCAPED_UNICODE);

            exit;

        }

    } elseif (!$auth->aAccesModule($moduleKey)) {

        http_response_code(403);

        echo json_encode(['success' => false, 'error' => 'Accès refusé'], JSON_UNESCAPED_UNICODE);

        exit;

    }



    return $auth;

}



function module_api_json(array $payload, int $status = 200): void

{

    http_response_code($status);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);

    exit;

}



/** Rôles autorisés à créer / modifier / supprimer dans un module. */

function module_write_roles_for(string $moduleKey): array

{

    if ($moduleKey === 'finances') {

        return app_role_finance_write_roles();

    }

    if ($moduleKey === 'paiements') {

        return app_role_paiements_write_roles();

    }

    return app_module_roles($moduleKey);

}



function module_can_write(string $moduleKey): bool

{

    $auth = Auth::getInstance();

    return $auth->aUnRole(module_write_roles_for($moduleKey));

}



/** Lecture module OK mais écriture refusée (ex. secrétaire sur finances). */

function module_require_write(string $moduleKey, ?string $redirectUrl = null): void

{

    module_require_roles($moduleKey);

    if (module_can_write($moduleKey)) {

        return;

    }

    $base = function_exists('app_url') ? app_url('') : '';

    $target = $redirectUrl ?? rtrim($base, '/') . '/' . $moduleKey . '/index.php?error=access_denied';

    header('Location: ' . $target);

    exit;

}

