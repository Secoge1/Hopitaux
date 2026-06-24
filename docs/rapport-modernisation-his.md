# Rapport d'impact — Modernisation HIS/ERP Efficasante

**Date :** 24/06/2025  
**Périmètre :** 12 axes de modernisation demandés  
**Note globale actuelle :** **6,5 / 10**  
**Principe directeur :** toute évolution majeure passe par **Admin plateforme → Fonctionnalités** (`platform_tenant_features`)

---

## 0. Règle d'activation (confirmée)

| Élément | État |
|---------|------|
| Table `platform_tenant_features` | Existe |
| UI Admin plateforme | `admin_platform/fonctionnalites.php` |
| Helper générique | `tenant_feature_enabled('clé')` |
| Feature opérationnelle | `payment_finance_sync` uniquement |
| Catalogue roadmap | 10 clés enregistrées (live / beta / planned) |

**Aucune nouvelle fonctionnalité ne sera visible ou active pour un établissement tant que l'admin principal ne l'active pas.**

---

## 1. Architecture actuelle

### Modules existants (20+)

```
patients/ consultations/ rendez-vous/ laboratoire/ medecins/
paiements/ finances/ assurances/ pharmacie/ personnel/
maintenance/ communication/ dossiers/ parametres/ utilisateurs/
admin_platform/ api/rest/ mobile/
```

### Dépendances transverses

```
includes/init.php
  → TenantSchema::ensure()
  → Auth, SubscriptionService, notifications

includes/app_module_layout.php
  → module_guard, TenantScope

models/*.php
  → PDO + TenantScope (isolation tenant_id)
```

### SaaS multi-tenant (§10)

**Déjà en place et mature :**
- Tables `tenants`, `subscription_orders`, `subscription_invoices`
- `TenantScope` sur ~30 tables métier
- Provisioning, garde abonnement, admin plateforme
- Permissions par rôle tenant (`tenant_role_modules`)

**Conclusion §10 :** faisabilité confirmée — **déjà implémenté à ~80 %**.

---

## 2. État par fonctionnalité demandée

| # | Fonctionnalité | Existant | Manquant | Maturité | Clé feature |
|---|----------------|----------|----------|----------|-------------|
| 1 | Dashboard exécutif | KPIs basiques (`dashboard.php`, `DashboardStats`) | Graphiques, CA J/M/A, top services/médecins, impayés | 35 % | `executive_dashboard` |
| 2 | DME centralisé | `patients/dossier_medical.php`, PDF | Recherche globale, imagerie, vaccins structurés | 55 % | `emr_centralized` |
| 3 | PDF & WhatsApp | PDF TCPDF (ordonnances, factures, labo) | WhatsApp, liens sécurisés | 40 % | `pdf_whatsapp_share` |
| 4 | Portail patient | — | Login patient, espace, RDV en ligne | 0 % | `patient_portal` |
| 5 | Assurances/mutuelles | Module `assurances/`, remboursements | Tiers payant auto, CANAM/AMO, créances | 45 % | `insurance_tpa` |
| 6 | Pharmacie avancée | Stock, mouvements, alertes basiques | Lots, inventaires, valorisation | 35 % | `pharmacy_advanced` |
| 7 | Notifications | In-app (`NotificationSystem`) | Centre unifié, SMS/email/WhatsApp | 40 % | `notification_center` |
| 8 | API REST mobile | `api/rest/` (lecture staff) | CRUD complet, doc OpenAPI, patient/caisse | 30 % | `rest_api_v2` |
| 9 | IA médicale | Mistral (consultations + labo) | Résumés, recherche dossier, assistant | 25 % | `ai_medical_suite` |
| 10 | SaaS multi-cliniques | Complet | Durcissement API, backups cloud auto | 80 % | *(core — pas de flag)* |
| 11 | Audit sécurité | Auth, CSRF partiel, logs | CSRF systématique, 2FA, chiffrement | 50 % | *(transversal)* |
| 12 | Sync paiements/compta | Contre-passation, verrouillage | Couche facture AR | 70 % | `payment_finance_sync` ✅ |

---

## 3. Forces du système

1. **Base métier complète** — tous les modules cliniques/financiers présents
2. **SaaS multi-tenant opérationnel** — isolation, abonnements, admin plateforme
3. **Pattern feature flags** — déploiement progressif sans casser l'existant
4. **PDF et tickets** — infrastructure d'impression existante
5. **IA Mistral** — première intégration consultations/laboratoire
6. **Apps mobiles staff** — Flutter + React consommant API REST
7. **Audit paiements récent** — contre-passation ERP, verrouillage encaissement

---

## 4. Faiblesses du système

1. **Pas de portail patient** — gap majeur pour cliniques modernes
2. **Pas de WhatsApp** — canal dominant en Afrique de l'Ouest
3. **Assurances non lettrées** au paiement — tiers payant manuel
4. **Dashboard sans BI** — compteurs sans graphiques ni tendances
5. **CSRF non systématique** — risque sécurité moyen
6. **API REST incomplète** — lecture seule, pas de tenant sur tokens
7. **Pas de couche Facture AR** — paiement = facture fusionnés
8. **Pharmacie basique** — pas de lots/inventaires professionnels

---

## 5. Plan d'évolution 12 mois

### Trimestre 1 (M1–M3) — Fondations & revenus

| Priorité | Lot | Durée estimée |
|----------|-----|---------------|
| Critique | Dashboard exécutif (graphiques Chart.js + KPIs financiers) | 3 sem. |
| Critique | Partage PDF + WhatsApp + liens sécurisés | 3 sem. |
| Importante | DME centralisé v2 (recherche, allergies, antécédents) | 4 sem. |
| Importante | Durcissement sécurité (CSRF global, audit trail) | 2 sem. |

### Trimestre 2 (M4–M6) — Patient & assurance

| Priorité | Lot | Durée estimée |
|----------|-----|---------------|
| Critique | Portail patient (auth, historique, téléchargements) | 6 sem. |
| Critique | Tiers payant assurance/mutuelle (CANAM, AMO, mutuelles) | 5 sem. |
| Importante | Centre de notifications unifié | 3 sem. |

### Trimestre 3 (M7–M9) — Opérations

| Priorité | Lot | Durée estimée |
|----------|-----|---------------|
| Importante | Pharmacie avancée (lots, inventaires, valorisation) | 5 sem. |
| Importante | API REST v2 + documentation OpenAPI | 4 sem. |
| Confort | Table `factures` + avoirs (phase 2 ERP) | 4 sem. |

### Trimestre 4 (M10–M12) — Intelligence & scale

| Priorité | Lot | Durée estimée |
|----------|-----|---------------|
| Confort | Suite IA (résumés, recherche dossier) | 4 sem. |
| Confort | Apps mobile patient + caisse | 6 sem. |
| Futuriste | SMS/WhatsApp notifications transactionnelles | 3 sem. |
| Futuriste | Backups cloud automatiques | 2 sem. |

---

## 6. Classification des améliorations

### Critique (M1–M6)
- Dashboard exécutif intelligent
- Partage PDF & WhatsApp
- Portail patient
- Tiers payant assurances
- CSRF / audit sécurité

### Importante (M3–M9)
- DME centralisé v2
- Centre notifications
- Pharmacie avancée
- API REST v2
- Couche factures AR

### Confort (M7–M12)
- Suite IA médicale étendue
- Graphiques avancés / exports BI
- Historique paiements append-only

### Futuriste (M10+)
- Assistant médical conversationnel
- Diagnostic automatique (exclu par design)
- Intégration Mobile Money API directe
- FHIR / interopérabilité internationale

---

## 7. Fichiers modifiés (infrastructure feature flags — session actuelle)

| Fichier | Modification |
|---------|--------------|
| `includes/saas/PlatformTenantFeatures.php` | Catalogue 10 features + statuts live/beta/planned |
| `includes/saas/saas_helpers.php` | Helper `tenant_feature_enabled()` |
| `admin_platform/fonctionnalites.php` | UI catalogue multi-features |

**Aucun module métier modifié dans cette session** — rapport et registre uniquement.

---

## 8. Prochaine phase proposée (validation requise)

### Phase 1A — Dashboard exécutif intelligent

**Fichiers prévus (estimation) :**

```
includes/saas/ExecutiveDashboardStats.php          (nouveau)
includes/saas/ExecutiveDashboardCharts.php       (nouveau)
dashboard.php                                    (modifié)
assets/js/executive-dashboard.js                 (nouveau)
assets/css/executive-dashboard.css               (nouveau)
config/verify_executive_dashboard.php            (nouveau)
```

**Gate :** `tenant_feature_enabled('executive_dashboard')`  
**Si OFF :** dashboard actuel inchangé  
**Si ON :** KPIs étendus + graphiques Chart.js

**Estimation :** ~400 lignes PHP + ~200 lignes JS, 0 migration BDD destructive.

---

## 9. Note globale détaillée

| Critère | Note /10 |
|---------|----------|
| Modules métier | 8 |
| SaaS multi-tenant | 8 |
| ERP / comptabilité | 6 |
| Expérience patient | 2 |
| Mobile / API | 5 |
| Sécurité | 6 |
| Assurances | 5 |
| Pharmacie | 5 |
| BI / dashboard | 4 |
| IA | 4 |
| **Moyenne pondérée** | **6,5 / 10** |

**Cible 12 mois :** 8,5 / 10 (clinique africaine professionnelle)

---

## 10. Compatibilité données existantes

- Toutes les évolutions prévues sont **additives** (nouvelles tables/colonnes via `TenantSchema::ensure()`)
- Feature flags **OFF par défaut** — comportement actuel préservé
- Pas de suppression de tables ni de migration destructive planifiée
