# 💰 GUIDE DE GESTION DES FRAIS DE SOINS

## 🎯 ACCÈS RAPIDE

**Page principale de gestion :**
```
http://localhost/efficasante/gestion_frais.html
```

---

## 📚 DEUX TYPES DE FRAIS À GÉRER

### 1️⃣ CATALOGUE DES SOINS (Actes médicaux)

**📍 Page de gestion :** `http://localhost/efficasante/parametres/soins.php`

**Qu'est-ce qu'un soin ?**
Les soins sont les actes médicaux que vous effectuez lors d'une consultation :
- Pansements
- Injections
- Sutures
- Perfusions
- Examens
- Petite chirurgie
- Etc.

---

### ➕ AJOUTER UN SOIN

**Étape par étape :**

1. **Ouvrir la page :** `parametres/soins.php`
2. **Cliquer sur :** "Ajouter un soin" (bouton vert en haut)
3. **Remplir le formulaire :**

   | Champ | Exemple | Obligatoire |
   |-------|---------|-------------|
   | **Nom du soin** | "Pansement simple" | ✅ Oui |
   | **Type de soin** | "Pansement" | ✅ Oui |
   | **Prix (FCFA)** | 2500 | ✅ Oui |
   | **Durée (minutes)** | 15 | Non (défaut: 30) |
   | **Description** | "Pansement standard pour plaie simple" | Non |
   | **Statut** | "Actif" ou "Inactif" | Non (défaut: Actif) |

4. **Cliquer sur :** "Enregistrer"

**✅ Résultat :** Le soin apparaît maintenant dans la liste et peut être ajouté aux consultations.

---

### ✏️ MODIFIER UN SOIN

1. **Trouver le soin** dans la liste
2. **Cliquer sur** l'icône ✏️ (Modifier)
3. **Changer** les informations nécessaires
4. **Enregistrer** les modifications

**💡 Astuce :** Pour changer le prix d'un soin, il suffit de modifier le champ "Prix".

---

### 🗑️ SUPPRIMER UN SOIN

**⚠️ ATTENTION :** La suppression est irréversible !

1. **Trouver le soin** dans la liste
2. **Cliquer sur** l'icône 🗑️ (Supprimer)
3. **Confirmer** la suppression

**💡 Conseil :** Préférez désactiver un soin plutôt que le supprimer (changer le statut à "Inactif").

---

## 2️⃣ TARIFS DE CONSULTATION

**📍 Page de gestion :** `http://localhost/efficasante/parametres/tarifs.php`

**Qu'est-ce qu'un tarif de consultation ?**
C'est le prix de base d'une consultation selon :
- Le **type de consultation** (normale, urgence, domicile, suivi...)
- La **spécialité** du médecin (généraliste, cardiologue, pédiatre...)

---

### ➕ AJOUTER UN TARIF

**Étape par étape :**

1. **Ouvrir la page :** `parametres/tarifs.php`
2. **Cliquer sur :** "Ajouter un tarif" (bouton vert en haut)
3. **Remplir le formulaire :**

   | Champ | Exemple | Obligatoire |
   |-------|---------|-------------|
   | **Type de consultation** | "Normale" | ✅ Oui |
   | **Spécialité** | "Cardiologie" | Non (laissez vide = toutes spécialités) |
   | **Prix (FCFA)** | 15000 | ✅ Oui |
   | **Description** | "Consultation standard" | Non |
   | **Statut** | "Actif" | Non (défaut: Actif) |

4. **Cliquer sur :** "Enregistrer"

---

### 📊 EXEMPLES DE TARIFS À CONFIGURER

| Type | Spécialité | Prix suggéré | Description |
|------|-----------|--------------|-------------|
| Normale | Généraliste | 5 000 FCFA | Consultation standard |
| Normale | Cardiologie | 15 000 FCFA | Consultation cardiologue |
| Normale | Pédiatrie | 8 000 FCFA | Consultation pédiatre |
| Urgence | (Toutes) | 10 000 FCFA | Consultation d'urgence |
| Domicile | (Toutes) | 20 000 FCFA | Visite à domicile |
| Suivi | (Toutes) | 3 000 FCFA | Consultation de contrôle |

---

### ✏️ MODIFIER UN TARIF

1. **Trouver le tarif** dans la liste
2. **Cliquer sur** l'icône ✏️ (Modifier)
3. **Changer** les informations (généralement le prix)
4. **Enregistrer**

**💡 Cas d'usage :** Augmentation des prix annuelle - modifier chaque tarif un par un.

---

### 🗑️ SUPPRIMER UN TARIF

1. **Trouver le tarif** dans la liste
2. **Cliquer sur** l'icône 🗑️ (Supprimer)
3. **Confirmer**

---

## 🎓 UTILISATION DANS LES CONSULTATIONS

### Comment les frais sont appliqués ?

#### 1. **Tarif de consultation** (automatique)
Lorsque vous créez une consultation, le système suggère automatiquement le tarif selon :
- Le type de consultation choisi
- La spécialité du médecin

#### 2. **Soins** (manuel)
Pendant ou après la consultation, vous pouvez ajouter des soins :
1. Aller dans la consultation
2. Cliquer sur "Gérer les soins"
3. Sélectionner les soins effectués
4. Le prix est calculé automatiquement (prix unitaire × quantité)

**✅ Total de la consultation = Tarif consultation + Total des soins**

---

## 💡 CONSEILS PRATIQUES

### ✅ POUR LES SOINS

1. **Nomenclature claire**
   - ✅ BON : "Pansement simple (plaie légère)"
   - ❌ MAUVAIS : "Pansement"

2. **Organisation par type**
   - Créez des catégories logiques (pansement, injection, etc.)
   - Facilitera la recherche lors des consultations

3. **Prix réalistes**
   - Basez-vous sur les tarifs du marché
   - Ajustez selon votre structure

4. **Durée estimée**
   - Utile pour la planification des consultations
   - Aide à gérer le planning médical

---

### ✅ POUR LES TARIFS

1. **Couverture complète**
   - Configurez au moins un tarif pour chaque type de consultation
   - Pensez à toutes les spécialités de votre établissement

2. **Spécialités optionnelles**
   - Laissez la spécialité vide pour un tarif "par défaut"
   - Créez des tarifs spécifiques uniquement si nécessaire

3. **Révision régulière**
   - Révisez vos tarifs au moins 1 fois par an
   - Ajustez selon l'inflation et les coûts

4. **Statut "Inactif"**
   - Utilisez pour temporairement désactiver un tarif
   - Mieux que supprimer (vous gardez l'historique)

---

## ❓ QUESTIONS FRÉQUENTES

### Q1 : Comment changer le prix d'un soin ?
**R :** Allez dans `parametres/soins.php`, cliquez sur ✏️ à côté du soin, changez le prix, enregistrez.

### Q2 : Puis-je avoir plusieurs tarifs pour la même spécialité ?
**R :** Oui ! Créez un tarif pour chaque type de consultation (normale, urgence, domicile...).

### Q3 : Que se passe-t-il si je supprime un soin utilisé dans des consultations passées ?
**R :** Les consultations passées conservent leurs données. Seul le catalogue est mis à jour.

### Q4 : Comment facturer un acte non listé ?
**R :** 2 options :
- Ajoutez le soin au catalogue (recommandé)
- Créez un soin "Autre" avec un prix modifiable

### Q5 : Puis-je importer des tarifs en masse ?
**R :** Actuellement non. Utilisez l'interface pour ajouter les tarifs un par un.

### Q6 : Les prix incluent-ils la TVA ?
**R :** Configurez selon votre comptabilité. Généralement, les services médicaux sont exonérés de TVA.

---

## 🆘 EN CAS DE PROBLÈME

### Erreur "Soin non trouvé" lors d'une consultation
**Cause :** Le soin a été supprimé du catalogue  
**Solution :** Recréez le soin ou choisissez un soin similaire

### Les tarifs ne s'affichent pas
**Cause :** Tarifs configurés avec statut "Inactif"  
**Solution :** Activez les tarifs dans `parametres/tarifs.php`

### Je ne vois pas mes modifications
**Cause :** Cache du navigateur  
**Solution :** Rafraîchir la page (Ctrl+F5 ou Cmd+Shift+R)

---

## 📞 SUPPORT

Pour toute question :
1. Consultez ce guide
2. Contactez l'administrateur système
3. Consultez la documentation complète

---

**📅 Dernière mise à jour :** Mars 2026  
**📝 Version du système :** EfficaSanté v2.0  

---

## 🔗 LIENS RAPIDES

| Page | URL |
|------|-----|
| **Centre de gestion** | `gestion_frais.html` |
| **Gérer les soins** | `parametres/soins.php` |
| **Gérer les tarifs** | `parametres/tarifs.php` |
| **Consultations** | `consultations/index.php` |
| **Dashboard** | `dashboard.php` |

---

**✅ CHECKLIST DE DÉMARRAGE**

- [ ] Ouvrir `gestion_frais.html`
- [ ] Configurer au moins 3 types de soins courants
- [ ] Configurer les tarifs pour chaque type de consultation
- [ ] Tester en créant une consultation
- [ ] Ajouter des soins à la consultation test
- [ ] Vérifier que les prix sont corrects
