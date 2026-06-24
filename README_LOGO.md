# 🖼️ Système de gestion du logo - Documentation complète

## 📋 Vue d'ensemble

Ce système gère l'affichage du logo de la clinique sur toutes les pages de l'application **EfficaSanté**. Le logo est stocké dans la base de données et affiché dynamiquement via plusieurs méthodes.

## 🚀 Démarrage rapide

### Le logo ne s'affiche pas ? Suivez ces étapes :

#### 1. **Script de réparation automatique** (RECOMMANDÉ)
Ouvrez votre navigateur et accédez à :
```
http://localhost/efficasante/fix_logo.php
```

Ce script va automatiquement :
- ✅ Diagnostiquer les problèmes
- ✅ Trouver les logos disponibles
- ✅ Réparer la configuration
- ✅ Tester le fonctionnement

#### 2. **Page de test visuelle**
Vérifiez que tout fonctionne :
```
http://localhost/efficasante/test_logo.php
```

Cette page affiche 8 tests différents du logo pour vérifier chaque aspect du système.

#### 3. **Vider le cache du navigateur**
Après toute réparation, videz le cache :
- **Windows** : `Ctrl + F5`
- **Mac** : `Cmd + Shift + R`
- **Linux** : `Ctrl + Shift + R`

## 📁 Fichiers du système

### Fichiers principaux

| Fichier | Description | Rôle |
|---------|-------------|------|
| `display_logo.php` | Affiche le logo depuis la DB | Point d'entrée pour l'affichage du logo |
| `includes/header_logo.php` | Fonctions PHP d'affichage | Fonctions réutilisables : `getSystemLogo()`, etc. |
| `config/SystemParameters.php` | Gestion des paramètres système | Récupération du chemin du logo depuis la DB |
| `assets/css/system_logo.css` | Styles CSS du logo | Styles responsive et adaptatifs |
| `assets/js/logo-handler.js` | Gestionnaire JavaScript | Gestion des erreurs et fallback automatique |

### Fichiers de diagnostic et réparation

| Fichier | Description | Usage |
|---------|-------------|-------|
| `fix_logo.php` | **Script de réparation automatique** | `http://localhost/efficasante/fix_logo.php` |
| `test_logo.php` | **Page de test visuelle** | `http://localhost/efficasante/test_logo.php` |
| `debug_logo.php` | Page de debug technique | `http://localhost/efficasante/debug_logo.php` |
| `fix_logo_database.sql` | Script SQL de réparation | Exécuter dans PHPMyAdmin ou MySQL |
| `GUIDE_DEPANNAGE_LOGO.md` | Guide complet de dépannage | Documentation détaillée |
| `CORRECTIONS_LOGO.md` | Historique des corrections | Détail des modifications apportées |

## 🔧 Utilisation

### Dans vos pages PHP

#### 1. Inclure les fichiers nécessaires

Dans la section `<head>` :
```html
<link href="assets/css/system_logo.css" rel="stylesheet">
```

Avant la fermeture de `</body>` :
```html
<script src="assets/js/logo-handler.js"></script>
```

#### 2. Afficher le logo

**Méthode 1 : Logo simple**
```php
<?php
require_once 'includes/header_logo.php';
echo getSystemLogo('medium'); // Tailles : small, medium, large, header, navbar
?>
```

**Méthode 2 : Logo avec texte**
```php
<?php
require_once 'includes/header_logo.php';
echo getSystemLogoWithText('medium', true);
?>
```

**Méthode 3 : Logo pour en-tête**
```php
<?php
require_once 'includes/header_logo.php';
echo getSystemLogoHeader('default'); // Variantes : default, compact, logo_only, sidebar
?>
```

**Méthode 4 : URL directe**
```html
<img src="display_logo.php" alt="Logo" class="system-logo">
```

### Depuis un sous-répertoire

Si votre page est dans un sous-répertoire (ex: `patients/ajouter.php`), ajustez les chemins :

```php
<?php
require_once '../includes/header_logo.php';
echo getSystemLogo('medium');
?>
```

```html
<link href="../assets/css/system_logo.css" rel="stylesheet">
<script src="../assets/js/logo-handler.js"></script>
```

## 📐 Tailles disponibles

| Taille | Dimensions | Usage recommandé |
|--------|-----------|------------------|
| `small` | 32×32px | Icônes, boutons |
| `medium` | 64×64px | Usage général |
| `large` | 128×128px | En-têtes principaux |
| `header` | 200×60px | Barre de navigation |
| `navbar` | 202×85px | Menu principal |

## 🎨 Classes CSS disponibles

```css
.system-logo          /* Logo de base */
.logo-header          /* Logo pour en-tête */
.logo-sidebar         /* Logo pour sidebar */
.logo-compact         /* Logo compact */
.logo-small           /* Petit logo */
.logo-container       /* Conteneur du logo */
.logo-text            /* Texte accompagnant le logo */
.logo-image-container /* Conteneur de l'image */
```

## 🔍 Diagnostic

### Problème courant : Logo ne s'affiche pas

**Solution rapide :**
1. Ouvrez `http://localhost/efficasante/fix_logo.php`
2. Le script va diagnostiquer et réparer automatiquement
3. Videz le cache : `Ctrl + F5`

### Problème : Logo déformé

**Cause :** Styles CSS conflictuels

**Solution :**
- Vérifiez que `assets/css/system_logo.css` est chargé
- Ajoutez `!important` si nécessaire :
```css
.system-logo {
    max-width: 100% !important;
    height: auto !important;
}
```

### Problème : Fallback SVG s'affiche au lieu du logo

**Cause :** Le fichier logo n'est pas trouvé

**Solution :**
1. Ouvrez `http://localhost/efficasante/debug_logo.php`
2. Vérifiez que le chemin du logo est correct
3. Vérifiez que le fichier existe dans `uploads/logos/`
4. Exécutez `fix_logo.php` pour réparer

## 🔒 Permissions

### Windows (WAMP)
Les permissions sont généralement correctes par défaut.

### Linux/Mac
Assurez-vous que le dossier `uploads/logos/` est accessible :
```bash
chmod -R 755 uploads/logos/
chown -R www-data:www-data uploads/logos/
```

## 📤 Upload d'un nouveau logo

### Via l'interface web
1. Allez dans **Paramètres > Système**
2. Section "Logo de l'établissement"
3. Cliquez sur "Parcourir"
4. Sélectionnez votre image (JPG ou PNG, max 2MB)
5. Cliquez sur "Télécharger le logo"

### Manuellement
1. Placez votre logo dans `uploads/logos/`
2. Renommez-le : `logo_clinique_YYYY-MM-DD_HH-MM-SS.png`
3. Exécutez `fix_logo.php` pour mettre à jour la configuration

### Via SQL
```sql
UPDATE parametres_systeme 
SET valeur = '../uploads/logos/votre_logo.png',
    date_modification = NOW()
WHERE cle = 'logo_clinique';
```

## 🧪 Tests

### Test automatique
```
http://localhost/efficasante/test_logo.php
```

### Test en ligne de commande
```bash
php fix_logo.php
```

### Test SQL
```bash
mysql -u root -p efficasante < fix_logo_database.sql
```

## 📊 Spécifications techniques

### Formats acceptés
- ✅ PNG (recommandé)
- ✅ JPG/JPEG
- ❌ GIF, SVG, WebP (non supportés)

### Taille maximale
- 2 MB (2 097 152 octets)

### Dimensions recommandées
- **Minimum** : 64×64px
- **Recommandé** : 200×200px ou 400×100px
- **Maximum** : 2000×2000px

### Types MIME acceptés
- `image/jpeg`
- `image/jpg`
- `image/png`

## 🔄 Processus de chargement

1. La page appelle `getSystemLogo()` ou charge `display_logo.php`
2. `SystemParameters::getLogoPath()` récupère le chemin depuis la DB
3. Le chemin est normalisé et résolu
4. Le fichier est lu et affiché
5. En cas d'erreur, le fallback SVG est affiché

## 🛠️ Dépannage avancé

### Vérifier le chemin en DB
```bash
php -r "require_once 'config/SystemParameters.php'; \$sp = SystemParameters::getInstance(); echo \$sp->getLogoPath();"
```

### Vérifier que le fichier existe
```bash
# Windows
dir uploads\logos\*.png

# Linux/Mac
ls -la uploads/logos/*.png
```

### Vérifier les logs PHP
- **WAMP** : `C:\wamp64\logs\php_error.log`
- **XAMPP** : `C:\xampp\apache\logs\error.log`
- **Linux** : `/var/log/apache2/error.log`

### Console du navigateur
1. Appuyez sur `F12`
2. Allez dans l'onglet **Console**
3. Cherchez les erreurs liées au logo
4. Allez dans l'onglet **Network**
5. Cherchez `display_logo.php` et vérifiez le code de statut

## 📝 Modifications récentes

**18 novembre 2025** :
- ✅ Correction de la résolution des chemins dans `display_logo.php`
- ✅ Amélioration de `SystemParameters::getLogoPath()`
- ✅ Ajout du fallback SVG par défaut
- ✅ Amélioration des styles CSS responsive
- ✅ Création du gestionnaire JavaScript `logo-handler.js`
- ✅ Création des outils de diagnostic et réparation

Voir `CORRECTIONS_LOGO.md` pour plus de détails.

## 📞 Support

Si vous rencontrez des problèmes après avoir suivi ce guide :

1. **Collectez les informations** :
   - Captures d'écran de `test_logo.php`
   - Captures d'écran de `debug_logo.php`
   - Console du navigateur (F12 > Console)
   - Network tab (F12 > Network)

2. **Vérifiez** :
   - Logs PHP
   - Permissions des dossiers
   - Version de PHP (minimum 7.4)
   - Configuration MySQL

3. **Consultez** :
   - `GUIDE_DEPANNAGE_LOGO.md` - Guide détaillé
   - `CORRECTIONS_LOGO.md` - Historique des corrections

## 📚 Documentation complète

- **Guide de dépannage** : `GUIDE_DEPANNAGE_LOGO.md`
- **Corrections apportées** : `CORRECTIONS_LOGO.md`
- **Guide des images** : `GUIDE_IMAGES_BANNIERES.md`

## ✅ Checklist avant de contacter le support

- [ ] J'ai exécuté `fix_logo.php`
- [ ] J'ai vérifié `test_logo.php`
- [ ] J'ai vidé le cache du navigateur (Ctrl+F5)
- [ ] J'ai vérifié que le fichier existe dans `uploads/logos/`
- [ ] J'ai vérifié les permissions du dossier
- [ ] J'ai consulté `GUIDE_DEPANNAGE_LOGO.md`
- [ ] J'ai vérifié la console du navigateur (F12)
- [ ] J'ai vérifié les logs PHP

---

**Version** : 1.0  
**Date** : 18 novembre 2025  
**Auteur** : Système EfficaSanté  
**Licence** : Propriétaire

