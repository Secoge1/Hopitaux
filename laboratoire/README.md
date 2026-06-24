# Module Laboratoire - Système de Gestion Hospitalière

## Vue d'ensemble

Le module Laboratoire permet de gérer complètement le cycle de vie des analyses médicales, depuis la demande jusqu'aux résultats. Il offre une interface intuitive pour les techniciens de laboratoire et les médecins.

## Fonctionnalités

### ✅ Fonctionnalités Disponibles

- **Gestion complète des analyses** : CRUD complet (Créer, Lire, Modifier, Supprimer)
- **Suivi des statuts** : En attente, En cours, Terminée, Annulée
- **Gestion des priorités** : Normale, Urgente, Critique
- **Types d'analyses** : Sanguines, Urine, Imagerie, Tests spécialisés
- **Rapports et statistiques** : Graphiques et export PDF
- **Interface responsive** : Compatible mobile et desktop

### 🔄 Workflow des Analyses

1. **Demande d'analyse** : Le médecin crée une demande d'analyse
2. **Validation** : L'analyse est validée et mise en attente
3. **Exécution** : Le laboratoire commence l'analyse
4. **Résultats** : Saisie des résultats et validation
5. **Archivage** : L'analyse est terminée et archivée

## Structure des Fichiers

```
laboratoire/
├── index.php          # Liste des analyses avec filtres
├── ajouter.php        # Création d'une nouvelle analyse
├── voir.php           # Visualisation détaillée d'une analyse
├── modifier.php       # Modification d'une analyse
├── supprimer.php      # Suppression sécurisée d'une analyse
├── rapport.php        # Rapports et statistiques
├── README.md          # Documentation (ce fichier)
└── models/
    └── Analyse.php    # Modèle de données
```

## Base de Données

### Table `analyses`

```sql
CREATE TABLE analyses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medecin_id INT NOT NULL,
    type_analyse VARCHAR(50) NOT NULL,
    priorite ENUM('normale', 'urgente', 'critique') DEFAULT 'normale',
    description TEXT,
    instructions TEXT,
    statut ENUM('en_attente', 'en_cours', 'termine', 'annule') DEFAULT 'en_attente',
    resultats TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_analyse DATETIME,
    date_resultats DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);
```

## Installation

### 1. Exécuter le script d'installation

```bash
# Accéder au répertoire du projet
cd clinique-hopital

# Exécuter le script d'installation
php install_laboratoire.php
```

### 2. Vérifier la création de la table

Le script créera automatiquement :
- La table `analyses` avec la structure appropriée
- Les index pour optimiser les performances
- Les contraintes de clés étrangères
- Des données d'exemple pour les tests

## Utilisation

### Navigation

- **Accueil** : Retour au tableau de bord principal
- **Liste** : Vue d'ensemble de toutes les analyses
- **Nouvelle Analyse** : Créer une nouvelle demande d'analyse
- **Rapport** : Statistiques et export des données

### Création d'une Analyse

1. Cliquer sur "Nouvelle Analyse"
2. Sélectionner le patient et le médecin
3. Choisir le type d'analyse et la priorité
4. Ajouter description et instructions
5. Valider la création

### Suivi d'une Analyse

- **Voir** : Consulter tous les détails
- **Modifier** : Mettre à jour les informations
- **Actions rapides** : Changer le statut rapidement
- **Supprimer** : Suppression sécurisée avec confirmation

### Rapports et Statistiques

- **Filtres** : Par date, statut, type, médecin
- **Graphiques** : Répartition par statut et type
- **Export** : PDF et impression
- **Statistiques** : Compteurs en temps réel

## Types d'Analyses Supportés

- **Analyses sanguines** : NFS, Glycémie, Cholestérol, etc.
- **Analyses d'urine** : ECBU, Protéinurie, etc.
- **Imagerie médicale** : Radiographie, Échographie, Scanner
- **Tests spécialisés** : Tests allergiques, Tests génétiques
- **Analyses personnalisées** : Selon les besoins spécifiques

## Statuts et Priorités

### Statuts
- **En attente** : Analyse demandée, en attente de traitement
- **En cours** : Analyse en cours de réalisation
- **Terminée** : Analyse terminée avec résultats
- **Annulée** : Analyse annulée

### Priorités
- **Normale** : Traitement standard
- **Urgente** : Traitement prioritaire
- **Critique** : Traitement immédiat

## Sécurité

- **Validation des données** : Toutes les entrées sont validées
- **Confirmation de suppression** : Double confirmation requise
- **Gestion des erreurs** : Messages d'erreur informatifs
- **Sessions** : Gestion des sessions utilisateur

## Personnalisation

### Ajouter un nouveau type d'analyse

1. Modifier le modèle `Analyse.php`
2. Ajouter le type dans la méthode `getTypesAnalyses()`
3. Mettre à jour l'interface utilisateur

### Modifier les statuts

1. Éditer l'énumération dans la base de données
2. Mettre à jour le modèle
3. Adapter l'interface

## Support et Maintenance

### Vérification de l'état

```bash
# Vérifier la structure de la table
php install_laboratoire.php

# Consulter les logs d'erreur
tail -f /var/log/apache2/error.log
```

### Sauvegarde

```bash
# Exporter la table analyses
mysqldump -u username -p database_name analyses > analyses_backup.sql

# Restaurer
mysql -u username -p database_name < analyses_backup.sql
```

## Développement Futur

### Fonctionnalités prévues

- [ ] Interface pour techniciens de laboratoire
- [ ] Gestion des échantillons
- [ ] Intégration avec équipements de laboratoire
- [ ] Notifications automatiques
- [ ] Historique des modifications
- [ ] Gestion des normes et références

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





