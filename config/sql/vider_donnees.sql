-- =============================================================================
-- EfficaSanté / SeSanté — Vider les données (phpMyAdmin ou mysql CLI)
-- =============================================================================
-- Erreur #1701 : TRUNCATE échoue si une autre table référence la table via FK.
-- Solution : désactiver temporairement les contrôles FK, TRUNCATE, puis réactiver.
--
-- ⚠️  SAUVEGARDE OBLIGATOIRE avant exécution (parametres/sauvegardes.php ou export).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES = 0;

-- ---------------------------------------------------------------------------
-- Option A — Données métier uniquement (conserve comptes, tenants, paramètres)
-- Décommentez ce bloc si vous voulez garder utilisateurs / tenants / réglages.
-- ---------------------------------------------------------------------------
/*
TRUNCATE TABLE `active_sessions`;
TRUNCATE TABLE `login_attempts`;
TRUNCATE TABLE `suspicious_activities`;
TRUNCATE TABLE `system_logs`;
TRUNCATE TABLE `maintenance_logs`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `messages_internes`;
TRUNCATE TABLE `annonces`;
TRUNCATE TABLE `ia_diagnostics_realtime`;

TRUNCATE TABLE `consultation_soins`;
TRUNCATE TABLE `consultation_hospitalisation`;
TRUNCATE TABLE `tickets_consultation`;
TRUNCATE TABLE `sejours_hospitalisation`;
TRUNCATE TABLE `consultations`;

TRUNCATE TABLE `lignes_commande_pharmacie`;
TRUNCATE TABLE `mouvements_stock_pharmacie`;
TRUNCATE TABLE `commandes_pharmacie`;
TRUNCATE TABLE `medicaments`;

TRUNCATE TABLE `ecritures_comptables`;
TRUNCATE TABLE `remboursements`;
TRUNCATE TABLE `paiements`;
TRUNCATE TABLE `budgets`;
TRUNCATE TABLE `comptes_comptables`;

TRUNCATE TABLE `interventions_maintenance`;
TRUNCATE TABLE `equipements`;
TRUNCATE TABLE `stocks_materiel`;

TRUNCATE TABLE `horaires_personnel`;
TRUNCATE TABLE `conges_personnel`;
TRUNCATE TABLE `personnel`;

TRUNCATE TABLE `contrats_assurance`;
TRUNCATE TABLE `assurances`;

TRUNCATE TABLE `documents_patients`;
TRUNCATE TABLE `dossiers`;
TRUNCATE TABLE `analyses`;
TRUNCATE TABLE `rendez_vous`;
TRUNCATE TABLE `patients`;
TRUNCATE TABLE `medecins`;

TRUNCATE TABLE `tarifs_consultation`;
TRUNCATE TABLE `soins_consultation`;
TRUNCATE TABLE `categories_hospitalisation`;

TRUNCATE TABLE `clients`;
*/

-- ---------------------------------------------------------------------------
-- Option B — TOUT vider (y compris utilisateurs, tenants, licences)
-- ⚠️  Vous devrez recréer un compte admin après.
-- ---------------------------------------------------------------------------
TRUNCATE TABLE `active_sessions`;
TRUNCATE TABLE `login_attempts`;
TRUNCATE TABLE `suspicious_activities`;
TRUNCATE TABLE `system_logs`;
TRUNCATE TABLE `maintenance_logs`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `messages_internes`;
TRUNCATE TABLE `annonces`;
TRUNCATE TABLE `ia_diagnostics_realtime`;

TRUNCATE TABLE `consultation_soins`;
TRUNCATE TABLE `consultation_hospitalisation`;
TRUNCATE TABLE `tickets_consultation`;
TRUNCATE TABLE `sejours_hospitalisation`;
TRUNCATE TABLE `consultations`;

TRUNCATE TABLE `lignes_commande_pharmacie`;
TRUNCATE TABLE `mouvements_stock_pharmacie`;
TRUNCATE TABLE `commandes_pharmacie`;
TRUNCATE TABLE `medicaments`;

TRUNCATE TABLE `ecritures_comptables`;
TRUNCATE TABLE `remboursements`;
TRUNCATE TABLE `paiements`;
TRUNCATE TABLE `budgets`;
TRUNCATE TABLE `comptes_comptables`;

TRUNCATE TABLE `interventions_maintenance`;
TRUNCATE TABLE `equipements`;
TRUNCATE TABLE `stocks_materiel`;

TRUNCATE TABLE `horaires_personnel`;
TRUNCATE TABLE `conges_personnel`;
TRUNCATE TABLE `personnel`;

TRUNCATE TABLE `contrats_assurance`;
TRUNCATE TABLE `assurances`;

TRUNCATE TABLE `documents_patients`;
TRUNCATE TABLE `dossiers`;
TRUNCATE TABLE `analyses`;
TRUNCATE TABLE `rendez_vous`;
TRUNCATE TABLE `patients`;
TRUNCATE TABLE `medecins`;

TRUNCATE TABLE `tarifs_consultation`;
TRUNCATE TABLE `soins_consultation`;
TRUNCATE TABLE `categories_hospitalisation`;

TRUNCATE TABLE `clients`;

TRUNCATE TABLE `subscription_orders`;
TRUNCATE TABLE `renouvellements_licences`;
TRUNCATE TABLE `modules_licences`;
TRUNCATE TABLE `licences`;
TRUNCATE TABLE `prix_licences`;
TRUNCATE TABLE `system_licenses`;
TRUNCATE TABLE `utilisateurs`;
TRUNCATE TABLE `tenants`;
TRUNCATE TABLE `roles`;
TRUNCATE TABLE `parametres_systeme`;
TRUNCATE TABLE `v_stats_licences`;

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_SAFE_UPDATES = 1;

-- ⚠️  Vider aussi le cache PHP (sinon Accueil/Dashboard affichent d'anciens compteurs) :
--     php config/vider_cache.php
--     ou supprimez les fichiers dans le dossier cache/

-- Fin — vérifiez avec : SELECT COUNT(*) FROM patients;
