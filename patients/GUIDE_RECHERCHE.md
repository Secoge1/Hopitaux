# Guide de Dépannage - Recherche de Patients

## 🎉 Nouvelle Fonctionnalité : Autocomplétion Intelligente

**L'autocomplétion en temps réel est maintenant disponible !**

Dès que vous commencez à taper dans le champ de recherche, des suggestions de patients apparaissent automatiquement avec :
- 👤 Nom complet
- 📁 Numéro de dossier
- 📞 Téléphone
- 🎂 Âge
- ✅ Statut

**Navigation :**
- Utilisez les flèches ⬆️⬇️ pour naviguer
- Appuyez sur Entrée ↩️ pour sélectionner
- Cliquez directement sur une suggestion

📖 **Voir le guide complet :** [GUIDE_AUTOCOMPLETION.md](GUIDE_AUTOCOMPLETION.md)

---

## Problème
La recherche dans le module patients (`/patients/?search=`) ne fonctionne pas.

## Solutions Appliquées

### 1. Améliorations du Code
- ✅ Ajout de la gestion des erreurs avec try-catch
- ✅ Activation de l'affichage des erreurs PHP
- ✅ Ajout d'un indicateur visuel de recherche active
- ✅ Amélioration des messages d'erreur
- ✅ Ajout de logs JavaScript pour le débogage

### 2. Tests à Effectuer

#### Test 1: Vérifier la base de données
Ouvrez `http://localhost/efficasante/patients/test_search.php` dans votre navigateur.
Ce script va:
- Afficher tous les paramètres GET reçus
- Compter le nombre total de patients
- Tester la recherche avec différents termes
- Afficher un formulaire de test

#### Test 2: Utiliser la recherche normale
1. Allez sur `http://localhost/efficasante/patients/`
2. Entrez un terme de recherche (nom, prénom, ou numéro de dossier)
3. Cliquez sur "Rechercher"
4. Vérifiez si un message d'alerte bleue apparaît indiquant "Recherche active"

#### Test 3: Vérifier la console du navigateur
1. Ouvrez la console du navigateur (F12)
2. Allez sur la page des patients
3. Vérifiez les logs qui affichent:
   - Les paramètres de recherche actuels
   - Le nombre de patients trouvés
   - Les valeurs du formulaire lors de la soumission

### 3. Cas d'Utilisation de la Recherche

#### Rechercher par nom:
```
http://localhost/efficasante/patients/?search=Dupont
```

#### Rechercher par prénom:
```
http://localhost/efficasante/patients/?search=Jean
```

#### Rechercher par prénom et nom ensemble:
```
http://localhost/efficasante/patients/?search=Jean Dupont
```
ou inversement:
```
http://localhost/efficasante/patients/?search=Dupont Jean
```

#### Rechercher par numéro de dossier:
```
http://localhost/efficasante/patients/?search=P2025
```

#### Filtrer par statut:
```
http://localhost/efficasante/patients/?statut=actif
```

#### Combiner recherche et filtre:
```
http://localhost/efficasante/patients/?search=Dupont&statut=actif
```

### 4. Problèmes Possibles et Solutions

#### Problème: La page se charge mais n'affiche rien
**Cause possible:** Erreur PHP
**Solution:** 
1. Vérifiez le fichier de log PHP (généralement dans `C:\wamp64\logs\php_error.log`)
2. Assurez-vous que l'affichage des erreurs est activé (déjà fait dans le code)

#### Problème: La recherche retourne 0 résultats alors que des patients existent
**Cause possible:** Les patients ont le statut "supprime"
**Solution:**
1. Ouvrez phpMyAdmin
2. Exécutez cette requête:
```sql
SELECT statut, COUNT(*) as count 
FROM patients 
GROUP BY statut;
```
3. Si tous les patients ont le statut "supprime", exécutez:
```sql
UPDATE patients 
SET statut = 'actif' 
WHERE statut = 'supprime';
```

#### Problème: La requête SQL échoue
**Cause possible:** La table patients n'existe pas ou a des colonnes manquantes
**Solution:**
1. Vérifiez la structure de la table:
```sql
DESCRIBE patients;
```
2. Assurez-vous que les colonnes suivantes existent:
   - id
   - numero_dossier
   - nom
   - prenom
   - date_naissance
   - sexe
   - telephone
   - email
   - adresse
   - statut
   - date_creation

#### Problème: Le formulaire ne se soumet pas
**Cause possible:** Conflit JavaScript
**Solution:**
1. Ouvrez la console du navigateur (F12)
2. Regardez les erreurs JavaScript
3. Vérifiez que les fichiers suivants se chargent correctement:
   - bootstrap.bundle.min.js

### 5. Fonctionnalités Ajoutées

#### Recherche intelligente prénom + nom
- La recherche peut maintenant trouver un patient en tapant "Jean Dupont" ou "Dupont Jean"
- Fonctionne avec les combinaisons : prénom nom, nom prénom
- Plus besoin de chercher séparément par prénom ou nom

#### Recherche en temps réel
- Le formulaire se soumet automatiquement lors du changement de statut
- Les logs de débogage s'affichent dans la console

#### Indicateur visuel
- Une alerte bleue apparaît quand une recherche est active
- Elle affiche le terme recherché et le nombre de résultats

#### Messages améliorés
- Message différent si aucun patient n'est trouvé vs aucun résultat de recherche
- Bouton "Effacer les filtres" pour facilement réinitialiser la recherche

#### Comment fonctionne la recherche combinée ?
La recherche vérifie maintenant 5 critères :
1. Le **nom** contient le terme recherché
2. Le **prénom** contient le terme recherché
3. Le **numéro de dossier** contient le terme recherché
4. La combinaison **"prénom nom"** contient le terme recherché
5. La combinaison **"nom prénom"** contient le terme recherché

**Fonctionnalités avancées :**
- ✅ **Recherche insensible à la casse** : "jean dupont", "JEAN DUPONT" ou "Jean Dupont" donnent le même résultat
- ✅ **Gestion des espaces multiples** : "Jean  Dupont" (avec plusieurs espaces) fonctionne
- ✅ **Recherche partielle** : "Dup" trouve "Dupont"
- ✅ **Ordre flexible** : "Dupont Jean" ou "Jean Dupont" trouvent le même patient

**Exemples :**
- Recherche "Jean" → trouve tous les Jean (prénom)
- Recherche "Dupont" → trouve tous les Dupont (nom)
- Recherche "Jean Dupont" → trouve le patient Jean Dupont
- Recherche "jean dupont" → trouve aussi Jean Dupont (insensible à la casse)
- Recherche "DUPONT JEAN" → trouve aussi Jean Dupont
- Recherche "Dupont Jean" → trouve aussi le patient Jean Dupont (ordre inversé)
- Recherche "Dup" → trouve tous les patients dont le nom ou prénom contient "Dup"
- Recherche "P2025" → trouve le patient avec le numéro de dossier P2025

### 6. Logs de Débogage

Ouvrez la console du navigateur pour voir:
```javascript
Page chargée. Paramètres actuels: {
    search: "terme_recherché",
    statut: "actif",
    totalPatients: 10,
    patientsAffiches: 10
}
```

Lors de la soumission du formulaire:
```javascript
Formulaire soumis avec: {
    search: "nouveau_terme",
    statut: "actif"
}
```

## Contact
Si le problème persiste après avoir suivi ce guide, vérifiez:
1. Les logs Apache (`C:\wamp64\logs\apache_error.log`)
2. Les logs PHP (`C:\wamp64\logs\php_error.log`)
3. La console du navigateur (F12 > Console)

## Fichiers Modifiés
- `patients/index.php` - Ajout de la gestion des erreurs et du débogage
- `patients/test_search.php` - Nouveau fichier de test
- `patients/GUIDE_RECHERCHE.md` - Ce guide

