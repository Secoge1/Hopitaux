<?php
require_once __DIR__ . '/../includes/init.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// Inclure la configuration de la devise et le modèle
require_once '../config/currency.php';
require_once '../models/Paiement.php';

$paiementModel = new Paiement();

// Récupérer les filtres depuis l'URL
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$type_paiement = $_GET['type_paiement'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Appliquer les filtres
$filters = [];
if ($search) $filters['search'] = $search;
if ($statut) $filters['statut'] = $statut;
if ($type_paiement) $filters['type_paiement'] = $type_paiement;
if ($date_debut) $filters['date_debut'] = $date_debut;
if ($date_fin) $filters['date_fin'] = $date_fin;

// Récupérer tous les paiements avec les filtres (pas de pagination pour l'export)
$paiements = $paiementModel->getAll(1, 10000, $filters); // Limite élevée pour récupérer tous les résultats
$stats = $paiementModel->getStats();

// Debug : afficher les informations pour vérifier
if (empty($paiements)) {
    echo "<h2>⚠️ Aucun paiement trouvé</h2>";
    echo "<p>Filtres appliqués : " . json_encode($filters) . "</p>";
    echo "<p>Nombre de paiements récupérés : " . count($paiements) . "</p>";
    echo "<p><a href='index.php'>Retour à la liste</a></p>";
    exit;
}

// Générer le PDF
generatePDF($paiements, $stats, $filters);

function generatePDF($paiements, $stats, $filters) {
    // En-têtes pour forcer le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rapport_paiements_' . date('Y-m-d') . '.pdf"');
    
    // Créer le contenu HTML du PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Rapport des Paiements</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 16px; color: #666; margin-top: 10px; }
            .info { margin-bottom: 20px; }
            .filters { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .stats { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .stat-item { text-align: center; padding: 10px; background-color: #e9ecef; border-radius: 5px; flex: 1; margin: 0 5px; }
            .stat-value { font-size: 18px; font-weight: bold; color: #007bff; }
            .stat-label { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .total { font-weight: bold; background-color: #e9ecef; }
            .status-paye { color: #28a745; }
            .status-en_attente { color: #ffc107; }
            .status-partiel { color: #17a2b8; }
            .status-annule { color: #dc3545; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
            .logo { text-align: center; margin-bottom: 20px; }
            .logo-text { font-size: 20px; font-weight: bold; color: #007bff; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">
                <div class="logo-text">🏥</div>
            </div>
            <div class="title">Clinique et Hôpital</div>
            <div class="subtitle">Rapport des Paiements</div>
        </div>
        
        <div class="info">
            <strong>Date de génération :</strong> ' . date('d/m/Y H:i') . '<br>
            <strong>Devise :</strong> ' . CURRENCY_NAME . ' (' . CURRENCY_SYMBOL . ')<br>
            <strong>Total des paiements :</strong> ' . count($paiements) . '
        </div>
        
        <div class="filters">
            <strong>Filtres appliqués :</strong><br>
            ' . ($filters['search'] ? 'Recherche : ' . htmlspecialchars($filters['search']) . '<br>' : '') . '
            ' . ($filters['statut'] ? 'Statut : ' . htmlspecialchars($filters['statut']) . '<br>' : '') . '
            ' . ($filters['type_paiement'] ? 'Type : ' . htmlspecialchars($filters['type_paiement']) . '<br>' : '') . '
            ' . ($filters['date_debut'] ? 'Date début : ' . htmlspecialchars($filters['date_debut']) . '<br>' : '') . '
            ' . ($filters['date_fin'] ? 'Date fin : ' . htmlspecialchars($filters['date_fin']) . '<br>' : '') . '
            ' . (empty($filters) ? 'Aucun filtre appliqué' : '') . '
        </div>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value">' . formatCurrency($stats['total_encaisse'] ?? 0) . '</div>
                <div class="stat-label">Total encaissé</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">' . formatCurrency($stats['en_attente'] ?? 0) . '</div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">' . formatCurrency($stats['partiel'] ?? 0) . '</div>
                <div class="stat-label">Partiel</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">' . ($stats['total'] ?? 0) . '</div>
                <div class="stat-label">Transactions</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Patient</th>
                    <th>Montant</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';
    
    $totalMontant = 0;
    foreach ($paiements as $paiement) {
        $totalMontant += $paiement['montant'];
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($paiement['numero_facture']) . '</td>
                    <td>' . htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']) . '</td>
                    <td>' . formatCurrency($paiement['montant']) . '</td>
                    <td>' . htmlspecialchars(getTypePaiementLabel($paiement['type_paiement'])) . '</td>
                    <td class="status-' . $paiement['statut'] . '">' . htmlspecialchars(getStatutLabel($paiement['statut'])) . '</td>
                    <td>' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
            <tfoot>
                <tr class="total">
                    <td colspan="2"><strong>Total</strong></td>
                    <td><strong>' . formatCurrency($totalMontant) . '</strong></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer">
            <p><strong>Clinique et Hôpital</strong></p>
            <p>Ce rapport a été généré automatiquement le ' . date('d/m/Y à H:i') . '</p>
            <p>Total des transactions : ' . count($paiements) . ' | Montant total : ' . formatCurrency($totalMontant) . '</p>
        </div>
    </body>
    </html>';
    
    // Convertir le HTML en PDF (utiliser une bibliothèque comme TCPDF ou mPDF)
    // Pour l'instant, on affiche le HTML (vous devrez installer une bibliothèque PDF)
    echo $html;
}

function getTypePaiementLabel($type) {
    $types = [
        'carte' => 'Carte bancaire',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'securite_sociale' => 'Sécurité sociale',
        'mutuelle' => 'Mutuelle',
        'mobile_money' => 'Mobile Money',
        'autre' => 'Autre'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

function getStatutLabel($statut) {
    $statuts = [
        'en_attente' => 'En attente',
        'partiel' => 'Paiement partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
        'rembourse' => 'Remboursé'
    ];
    
    return $statuts[$statut] ?? ucfirst($statut);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export PDF - Paiements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-file-pdf me-2 text-danger"></i>Export PDF des Paiements</h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
                <a href="?export=pdf" class="btn btn-danger btn-sm">
                    <i class="fas fa-download me-1"></i>Télécharger PDF
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Génération de Rapport PDF</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Fonctionnalité d'export :</strong> Cette page permet de générer des rapports PDF des paiements.
                        </div>

                        <h6>Fonctionnalités disponibles :</h6>
                        <ul>
                            <li><i class="fas fa-check text-success me-2"></i>Export des paiements en PDF</li>
                            <li><i class="fas fa-check text-success me-2"></i>Rapport détaillé avec totaux</li>
                            <li><i class="fas fa-check text-success me-2"></i>Formatage automatique en FCFA</li>
                            <li><i class="fas fa-check text-success me-2"></i>Mise en page professionnelle</li>
                        </ul>

                        <div class="mt-4">
                            <a href="?export=pdf" class="btn btn-danger btn-lg">
                                <i class="fas fa-download me-2"></i>Générer et Télécharger le PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Options d'Export</h6>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Période</label>
                                <select class="form-select">
                                    <option>Ce mois</option>
                                    <option>Ce trimestre</option>
                                    <option>Cette année</option>
                                    <option>Personnalisé</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select">
                                    <option>Tous</option>
                                    <option>Payé</option>
                                    <option>En attente</option>
                                    <option>Partiel</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Format</label>
                                <select class="form-select">
                                    <option>PDF</option>
                                    <option>Excel (à venir)</option>
                                    <option>CSV (à venir)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" disabled>
                                <i class="fas fa-cog me-2"></i>Configurer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aperçu du rapport -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Aperçu du Rapport</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Voici un aperçu de ce qui sera inclus dans votre rapport PDF :</p>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Jean Dupont</td>
                                <td>Consultation</td>
                                <td><?php echo formatCurrency(15000); ?></td>
                                <td><span class="badge bg-success">Payé</span></td>
                                <td>2024-01-15</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Marie Martin</td>
                                <td>Analyse</td>
                                <td><?php echo formatCurrency(25000); ?></td>
                                <td><span class="badge bg-warning">En attente</span></td>
                                <td>2024-01-16</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Pierre Durand</td>
                                <td>Rendez-vous</td>
                                <td><?php echo formatCurrency(8000); ?></td>
                                <td><span class="badge bg-success">Payé</span></td>
                                <td>2024-01-17</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3"><strong>TOTAL</strong></td>
                                <td><strong><?php echo formatCurrency(48000); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/auto-responsive.js"></script>
</body>
</html>


