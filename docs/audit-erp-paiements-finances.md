# Audit ERP/HIS — Consultations, Facturation, Paiements, Comptabilité

**Date :** 24/06/2025  
**Périmètre :** modules Consultations, Paiements, Finances (comptabilité), Laboratoire (analyses)  
**Note de robustesse (avant corrections) :** **4,5 / 10**  
**Note cible (après corrections appliquées) :** **7 / 10**

---

## 1. Architecture actuelle

### Chaîne métier observée

```
Consultation / Analyse
        ↓ (manuel — bouton « Générer le paiement »)
    Paiement (en_attente)
        ↓ (statut → payé)
    Écriture comptable (ecritures_comptables)
```

**Il n’existe pas de couche « Facture » distincte** entre la prestation et le paiement.  
Le champ `numero_facture` sur `paiements` fusionne facture et encaissement.

| Modèle ERP/HIS attendu | État actuel |
|------------------------|-------------|
| Consultation → Facture AR → Paiement(s) → Comptabilité | Consultation → Paiement → Comptabilité |
| Facture proforma / avoir | Absent |
| Paiements partiels liés à une facture | Statut `partiel` sans lien montant restant dû |
| Tiers payant (assurance/mutuelle) | Table `remboursements` assurance séparée, peu couplée au statut paiement |
| Crédit patient | Non modélisé |

### Impact

| Risque | Gravité |
|--------|---------|
| Paiements partiels sans solde dû traçable | Moyen |
| Assurance / mutuelle non lettrées au paiement | Élevé (long terme) |
| Impossible d’émettre un avoir sans toucher le paiement | Moyen |
| Montant consultation modifiable après génération paiement | Faible (recalcul manuel) |

**Recommandation (non appliquée — compatibilité) :** introduire une table `factures` en phase 2, sans casser `paiements` existants.

---

## 2. Annulation des paiements (critique)

### Comportement AVANT correction

| Événement | Comptabilité |
|-----------|--------------|
| Statut → `annule` | **Suppression physique** de l’écriture (`Finances::deleteEcriture`) |
| Statut → `en_attente` depuis `payé` | Idem |
| Suppression paiement `payé` | Suppression écriture + DELETE paiement |
| Statut → `rembourse` | Contre-passation (OK) |

**Risque comptable :** perte d’historique, soldes recalculés par suppression, non conforme aux normes ERP.

### Comportement APRÈ contrepassation

| Événement | Comptabilité |
|-----------|--------------|
| Statut → `annule` | Écriture `PAI-ANN-{id}` (contre-passation) |
| Statut → `rembourse` | Écriture `PAI-REM-{id}` |
| Réouverture statut | Écriture `PAI-REV-{id}` |
| Écriture originale | **Conservée** (`ecriture_comptable_id` inchangé) |

Fichiers modifiés : `models/Paiement.php`, `includes/PaymentAudit.php`

---

## 3. Génération automatique des paiements

| Question | Réponse |
|----------|---------|
| Paiement auto à la fin de consultation ? | **Non** — `Consultation::terminer()` ne crée rien |
| Création manuelle | Oui — `creer_depuis_consultation.php` / fiche consultation |
| Montant | `getPrixTotalComplet()` (consultation + soins + hospitalisation) |

### Fiabilité du workflow

| Scénario | Fiabilité |
|----------|-----------|
| Soin ajouté **après** génération paiement | **Risque** — montant paiement obsolète |
| Hospitalisation ajoutée après | **Risque** — idem |
| Analyse labo après consultation | **Risque** — paiement séparé (analyse) non consolidé |
| Facture complémentaire | **Non gérée** — nouvelle saisie manuelle |

**Verdict :** workflow **acceptable pour caisse manuelle**, **non fiable** pour facturation dynamique sans regénération ou ligne de facture.

---

## 4. Modification des paiements encaissés

### Comportement AVANT correction

- `modifier.php` : tous champs modifiables même si `payé`
- `supprimer.php` : suppression autorisée
- `updateFinancesSync` : réécriture comptable possible

### Comportement APRÈ verrouillage

| Statut | Règle |
|--------|-------|
| `paye` | Verrouillé — seuls `annule` / `rembourse` autorisés |
| `annule` / `rembourse` | Historique clos — plus de modification |
| Suppression | Interdite si `paye`, `annule` ou `rembourse` |

Corrections : `models/Paiement.php`, `paiements/modifier.php`, `paiements/supprimer.php`, `paiements/voir.php`

---

## 5. Audit et traçabilité

| Élément | Avant | Après |
|---------|-------|-------|
| Journal `system_logs` sur paiements | Absent | `PaymentAudit::log()` sur create/update/delete/sync/contrepassation |
| Historique paiements dédié | Absent | Via `system_logs` (JSON) |
| Historique écritures | `date_creation`, `cree_par` | Inchangé + références `PAI-*` |
| `date_modification` paiement | Si colonne existe | Inchangé |

**Manque restant :** table `paiements_historique` append-only (phase 2).

---

## 6. Cohérence des données

| Contrôle | État |
|----------|------|
| FK `patient_id`, `consultation_id` | OK (schéma backup) |
| FK `ecriture_comptable_id`, `analyse_id` | Colonnes sans FK — risque orphelin |
| Doublon écriture paiement | Protégé (idempotent `syncWithFinances`) |
| Doublon contrepassation | Protégé (référence unique `PAI-ANN-*`, etc.) |
| Transactions SQL imbriquées | Risque résiduel Paiement ↔ Finances |
| Sync feature flag tenant | OK (`payment_finance_sync_enabled`) |

---

## 7. Fichiers modifiés (corrections)

| Fichier | Modification |
|---------|--------------|
| `models/Paiement.php` | Contre-passation, verrouillage, audit, suppression protégée |
| `includes/PaymentAudit.php` | Journalisation |
| `includes/payment_sync_badge.php` | Badge une fois par utilisateur (sessionStorage) |
| `paiements/modifier.php` | UI verrouillage + chargement paiement avant POST + gestion erreurs |
| `paiements/supprimer.php` | Blocage paiements clos |
| `paiements/voir.php` | Actions conditionnelles |
| `config/verify_payment_finance_sync.php` | Tests contrepassation / verrouillage |

---

## 8. Note de robustesse

| Critère | Note /10 |
|---------|----------|
| Intégrité comptable | 7 (après contrepassation) |
| Séparation facture/paiement | 3 |
| Workflow consultation | 6 |
| Verrouillage encaissement | 8 |
| Audit trail | 6 |
| Intégrité référentielle BDD | 5 |
| **Moyenne pondérée** | **7 / 10** |

---

## 9. Roadmap recommandée (sans casser l’existant)

1. Table `factures` + lignes (consultation, analyse, soin) — migration progressive  
2. FK `ecriture_comptable_id` → `ecritures_comptables.id`  
3. Table `paiements_historique` append-only  
4. Lettrage assurance / mutuelle via `remboursements`  
5. Regénération automatique ou complément de facture si soins post-paiement  
