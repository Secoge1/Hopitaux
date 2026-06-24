# Guide - Autocomplétion Intelligente

## 🎯 Fonctionnalité

L'autocomplétion intelligente vous permet de rechercher des patients en temps réel pendant que vous tapez. Dès que vous commencez à saisir un nom, prénom ou numéro de dossier, des suggestions apparaissent instantanément.

## ✨ Caractéristiques

### 1. **Recherche en temps réel**
- Les suggestions apparaissent **dès 2 caractères tapés**
- Délai de 300ms (debounce) pour optimiser les performances
- Indicateur de chargement pendant la recherche

### 2. **Affichage Riche**
Chaque suggestion affiche :
- 👤 **Nom complet** du patient (avec mise en surbrillance du texte recherché)
- 📁 **Numéro de dossier**
- 📞 **Téléphone** (si disponible)
- 🎂 **Âge** (si disponible)
- ✅ **Statut** (badge coloré : actif/inactif/archivé)

### 3. **Navigation au Clavier**
- ⬇️ **Flèche Bas** : Descendre dans les suggestions
- ⬆️ **Flèche Haut** : Remonter dans les suggestions
- ↩️ **Entrée** : Sélectionner la suggestion active et ouvrir la fiche patient
- ⎋ **Échap** : Fermer les suggestions

### 4. **Navigation à la Souris**
- **Survol** : Met en surbrillance la suggestion
- **Clic** : Ouvre directement la fiche détaillée du patient

### 5. **Comportement Intelligent**
- Se ferme automatiquement quand on clique ailleurs
- Se cache si on efface le texte (< 2 caractères)
- Affiche "Aucun patient trouvé" si aucun résultat
- Affiche une erreur en cas de problème de connexion

## 🎨 Interface

### Design
- **Dropdown élégant** avec ombres portées
- **Animations fluides** au survol
- **Mise en surbrillance** du texte recherché en jaune
- **Badges colorés** pour les statuts :
  - 🟢 Vert : Actif
  - 🟡 Orange : Inactif
  - ⚫ Gris : Archivé

### Responsive
- S'adapte automatiquement à la largeur du champ de recherche
- Défilement automatique si plus de 10 suggestions
- Hauteur maximale : 400px

## 🔧 Fichiers Techniques

### 1. `patients/api_suggestions.php`
API REST qui retourne les suggestions au format JSON.

**Paramètres:**
- `q` : terme de recherche (minimum 2 caractères)

**Réponse:**
```json
[
  {
    "id": 1,
    "numero_dossier": "P202500001",
    "nom": "Dupont",
    "prenom": "Jean",
    "nom_complet": "Jean Dupont",
    "telephone": "0612345678",
    "age": 45,
    "statut": "actif"
  }
]
```

### 2. `patients/index.php` (modifié)
Intégration de l'autocomplétion avec :
- CSS personnalisé pour le style
- JavaScript pour la gestion des événements
- Conteneur HTML pour les suggestions

### 3. `models/Patient.php`
Utilise la méthode `search()` pour récupérer les patients :
- Recherche insensible à la casse
- Recherche combinée nom + prénom
- Limite à 10 résultats pour les performances

## 📊 Performances

### Optimisations
1. **Debounce de 300ms** : Attend que l'utilisateur arrête de taper
2. **Limite à 10 résultats** : Temps de réponse rapide
3. **Recherche SQL optimisée** : Utilise des index
4. **Annulation des requêtes** : Annule les recherches précédentes

### Temps de réponse typique
- Base de données < 1000 patients : **< 100ms**
- Base de données < 10000 patients : **< 300ms**
- Base de données > 10000 patients : **< 500ms**

## 🎓 Utilisation

### Étape 1 : Commencer à taper
Tapez au moins **2 caractères** dans le champ de recherche.

### Étape 2 : Consulter les suggestions
Une liste de suggestions apparaît automatiquement en dessous.

### Étape 3 : Sélectionner un patient
**Option A - Souris :**
- Cliquez sur la suggestion souhaitée

**Option B - Clavier :**
1. Utilisez les flèches ⬆️⬇️ pour naviguer
2. Appuyez sur Entrée ↩️ pour sélectionner

### Étape 4 : Accès à la fiche
Vous êtes automatiquement redirigé vers la fiche détaillée du patient.

## 💡 Exemples d'Utilisation

### Recherche par prénom
```
Tapez : "Jean"
Résultat : Tous les patients prénommés Jean
```

### Recherche par nom
```
Tapez : "Dupont"
Résultat : Tous les patients nommés Dupont
```

### Recherche par nom complet
```
Tapez : "Jean Dupont"
Résultat : Le patient Jean Dupont
```

### Recherche par numéro de dossier
```
Tapez : "P2025"
Résultat : Tous les patients dont le dossier contient "P2025"
```

### Recherche partielle
```
Tapez : "Dup"
Résultat : Tous les patients dont le nom ou prénom contient "Dup"
```

## 🔒 Sécurité

### Protection XSS
- Échappement HTML de toutes les données affichées
- Utilisation de `escapeHtml()` pour sécuriser les suggestions

### Protection SQL Injection
- Requêtes préparées (prepared statements)
- Validation des paramètres côté serveur

### Limitation des Requêtes
- Minimum 2 caractères requis
- Debounce pour limiter les appels API
- Limite de 10 résultats maximum

## 🐛 Dépannage

### Problème : Les suggestions n'apparaissent pas
**Solutions :**
1. Vérifiez que vous tapez au moins 2 caractères
2. Ouvrez la console (F12) pour voir les erreurs
3. Vérifiez que le fichier `api_suggestions.php` est accessible

### Problème : Erreur lors de la recherche
**Solutions :**
1. Vérifiez la connexion à la base de données
2. Vérifiez les permissions du fichier `api_suggestions.php`
3. Consultez les logs PHP pour voir l'erreur exacte

### Problème : Les suggestions se ferment trop vite
**Solution :**
- C'est normal : les suggestions se ferment quand on clique ailleurs
- Utilisez les flèches du clavier pour naviguer sans perdre le focus

## 🚀 Améliorations Futures Possibles

1. **Cache côté client** : Stocker les recherches récentes
2. **Recherche floue** : Tolérance aux fautes de frappe
3. **Recherche par autres critères** : Ville, profession, etc.
4. **Raccourcis clavier** : Ctrl+K pour focus direct
5. **Historique des recherches** : Afficher les dernières recherches
6. **Tri intelligent** : Prioriser les patients récents ou actifs

## 📝 Notes Techniques

### Compatibilité
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

### Dépendances
- Bootstrap 5.3
- Font Awesome 6.0
- Fetch API (native JavaScript)

### API REST
L'endpoint `api_suggestions.php` peut être réutilisé dans d'autres parties de l'application qui nécessitent une recherche de patients.

**Exemple d'appel externe :**
```javascript
fetch('/patients/api_suggestions.php?q=Jean')
  .then(response => response.json())
  .then(data => console.log(data));
```

---

**Version:** 1.0  
**Date:** Octobre 2025  
**Auteur:** EfficaSanté Development Team




