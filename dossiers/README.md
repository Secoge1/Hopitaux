# Module Dossiers Patients - Système de Gestion Hospitalière

## Vue d'overview

Le module Dossiers Patients permet de gérer complètement les dossiers médicaux des patients, incluant leurs antécédents, allergies, groupes sanguins et priorités. Il offre une interface intuitive pour les médecins et le personnel médical.

## Fonctionnalités

### ✅ Fonctionnalités Disponibles

- **Gestion complète des dossiers** : CRUD complet (Créer, Lire, Modifier, Supprimer)
- **Suivi des statuts** : Actif, Inactif, Archivé
- **Gestion des priorités** : Basse, Moyenne, Haute
- **Informations médicales** : Groupes sanguins, antécédents, allergies
- **Recherche avancée** : Filtres multiples et recherche textuelle
- **Interface responsive** : Compatible mobile et desktop

### 🔄 Workflow des Dossiers

1. **Création** : Création d'un dossier pour un patient existant
2. **Complétion** : Ajout des informations médicales et antécédents
3. **Suivi** : Mise à jour régulière des informations
4. **Archivage** : Changement de statut selon l'état du patient

## Structure des Fichiers

```
dossiers/
├── index.php          # Liste des dossiers avec filtres
├── nouveau_dossier.php # Création d'un nouveau dossier
├── voir.php           # Visualisation détaillée d'un dossier
├── modifier.php       # Modification d'un dossier
├── supprimer.php      # Suppression sécurisée d'un dossier
├── recherche.php      # Recherche avancée
├── README.md          # Documentation (ce fichier)
└── models/
    └── Dossier.php    # Modèle de données
```

## Base de Données

### Table `dossiers`

```sql
CREATE TABLE dossiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    groupe_sanguin ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL,
    priorite ENUM('basse', 'moyenne', 'haute') DEFAULT 'basse',
    antecedents TEXT NULL,
    allergies TEXT NULL,
    statut ENUM('actif', 'inactif', 'archive') DEFAULT 'actif',
    notes TEXT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
```

## Installation

### 1. Exécuter le script d'installation

```bash
# Accéder au répertoire du projet
cd clinique-hopital

# Exécuter le script d'installation
php install_dossiers.php
```

### 2. Vérifier la création de la table

Le script créera automatiquement :
- La table `dossiers` avec la structure appropriée
- Les index pour optimiser les performances
- Les contraintes de clés étrangères
- Des données d'exemple pour les tests

## Utilisation

### Navigation

- **Accueil** : Retour au tableau de bord principal
- **Liste** : Vue d'ensemble de tous les dossiers
- **Nouveau Dossier** : Créer un nouveau dossier patient
- **Recherche Avancée** : Recherche et filtres avancés

### Création d'un Dossier

1. Cliquer sur "Nouveau Dossier"
2. Sélectionner le patient dans la liste
3. Remplir les informations médicales
4. Définir la priorité et le statut
5. Valider la création

### Suivi d'un Dossier

- **Voir** : Consulter tous les détails
- **Modifier** : Mettre à jour les informations
- **Supprimer** : Suppression sécurisée avec confirmation

### Recherche et Filtres

- **Recherche textuelle** : Par nom, prénom, numéro de dossier
- **Filtres médicaux** : Par groupe sanguin, statut, priorité
- **Filtres temporels** : Par date de création, date de naissance
- **Statistiques** : Compteurs en temps réel

## Types de Dossiers Supportés

- **Dossiers actifs** : Patients en cours de suivi
- **Dossiers inactifs** : Patients temporairement non suivis
- **Dossiers archivés** : Patients dont le suivi est terminé

## Statuts et Priorités

### Statuts
- **Actif** : Dossier en cours d'utilisation
- **Inactif** : Dossier temporairement inactif
- **Archivé** : Dossier terminé et archivé

### Priorités
- **Basse** : Suivi de routine
- **Moyenne** : Suivi régulier
- **Haute** : Suivi intensif ou urgence

## Sécurité

- **Validation des données** : Toutes les entrées sont validées
- **Confirmation de suppression** : Double confirmation requise
- **Gestion des erreurs** : Messages d'erreur informatifs
- **Sessions** : Gestion des sessions utilisateur

## Personnalisation

### Ajouter un nouveau groupe sanguin

1. Modifier le modèle `Dossier.php`
2. Ajouter le groupe dans la méthode `getGroupesSanguins()`
3. Mettre à jour l'interface utilisateur

### Modifier les statuts

1. Éditer l'énumération dans la base de données
2. Mettre à jour le modèle
3. Adapter l'interface

## Support et Maintenance

### Vérification de l'état

```bash
# Vérifier la structure de la table
php install_dossiers.php

# Consulter les logs d'erreur
tail -f /var/log/apache2/error.log
```

### Sauvegarde

```bash
# Exporter la table dossiers
mysqldump -u username -p database_name dossiers > dossiers_backup.sql

# Restaurer
mysql -u username -p database_name < dossiers_backup.sql
```

## Développement Futur

### Fonctionnalités prévues

- [ ] Historique des modifications
- [ ] Export des dossiers en PDF
- [ ] Intégration avec les consultations
- [ ] Notifications automatiques
- [ ] Gestion des documents attachés
- [ ] Interface pour familles

### API

- [ ] Endpoints REST pour intégration
- [ ] Webhooks pour notifications
- [ ] Documentation OpenAPI/Swagger

## Contact

Pour toute question ou suggestion concernant ce module, contactez l'équipe de développement.

---

**Version** : 1.0.0  
**Dernière mise à jour** : <?php echo date('d/m/Y'); ?>  
**Statut** : ✅ Fonctionnel





