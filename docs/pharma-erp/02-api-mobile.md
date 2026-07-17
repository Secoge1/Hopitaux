# API REST PharmaPro ERP

Base URL : `/api/rest/pharma/index.php?path=`

Authentification : header `Authorization: Bearer {token}`

## Endpoints

| Méthode | path | Description |
|---------|------|-------------|
| POST | `login` | `{ "email", "password" }` → token 30 jours |
| GET | `dashboard` | KPI ventes, stock, alertes |
| GET | `products` | Liste (`q`, `page`, `limit`) |
| GET | `products/detail` | Fiche produit + codes-barres (`id=`) |
| POST | `products/barcode/primary` | Définir code principal `{ product_id, barcode }` |
| POST | `products/barcode/add` | Ajouter code alternatif |
| POST | `products/barcode/remove` | Supprimer un code |
| GET | `products/barcode` | Produit par code (`code=`) |
| GET | `reports/grand_livre` | Grand livre groupé |
| GET | `reports/bilan/pdf` | Export PDF Bilan |
| GET | `reports/grand_livre/pdf` | Export PDF Grand Livre |
| GET | `sales` | Ventes récentes |
| POST | `sales` | Créer vente POS |
| GET | `sales/recent` | 15 dernières ventes |
| GET | `stock/alerts` | Ruptures + péremptions |
| GET | `features` | Flags tenant |

## Exemple vente POST

```json
{
  "lines": [
    { "product_id": 1, "quantity": 2, "unit_price": 1500 }
  ],
  "payment": { "method": "cash", "amount": 3000 },
  "customer_name": "Client comptoir"
}
```

## Prérequis

- Feature `pharma_erp_suite` activée (Admin plateforme)
- Rôle : `admin`, `pharmacien` ou `comptable`

## Rapports PDF

- Grand Livre : `/pharma_erp/accounting/grand_livre.php`
- Bilan SYSCOHADA : `/pharma_erp/accounting/bilan.php`
- Export PDF direct via `export_grand_livre_pdf.php` / `export_bilan_pdf.php`
