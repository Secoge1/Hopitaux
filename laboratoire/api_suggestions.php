<?php
/**
 * API suggestions laboratoire — authentifiée.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/TarifAnalyseLaboratoire.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../includes/MistralAIService.php';

module_api_guard('laboratoire');

header('Content-Type: application/json');

$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($searchQuery !== '') {
    try {
        $analyseModel = new Analyse();
        $rows = $analyseModel->searchAutocomplete($searchQuery, 10);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'type_analyse' => $row['type_analyse'] ?? '',
                'statut' => $row['statut'] ?? '',
                'numero_ticket' => $row['numero_ticket'] ?? '',
                'patient_nom_complet' => trim(($row['patient_prenom'] ?? '') . ' ' . ($row['patient_nom'] ?? '')),
                'medecin_nom_complet' => medecin_profil_format_joined($row),
            ];
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Récupérer les paramètres
$typeAnalyse = isset($_GET['type_analyse']) ? trim($_GET['type_analyse']) : '';
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

// Vérifier que le type d'analyse est fourni
if (empty($typeAnalyse)) {
    echo json_encode([
        'success' => false,
        'error' => 'Type d\'analyse non spécifié'
    ]);
    exit();
}

if ($patientId) {
    $patientModel = new Patient();
    if (!$patientModel->getById($patientId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Patient introuvable']);
        exit();
    }
}

try {
    // Définir les suggestions par type d'analyse
    $analysesSuggestions = [
        'sang' => [
            'title' => 'Analyse de Sang (Hématologie)',
            'suggestions' => [
                'Numération Formule Sanguine (NFS)',
                'Hémogramme complet',
                'Taux d\'hémoglobine',
                'Numération des globules blancs',
                'Numération des globules rouges',
                'Numération plaquettaire',
                'Vitesse de sédimentation (VS)',
                'CRP (Protéine C-Réactive)'
            ],
            'preparation' => 'Jeûne de 8-12 heures recommandé',
            'delai' => '24-48 heures',
            'indications' => [
                'Anémie ou fatigue chronique',
                'Infections récurrentes',
                'Bilan de santé général',
                'Suivi de traitement'
            ],
            'contraindications' => [
                'Informer si traitement anticoagulant en cours',
                'Signaler tout trouble de la coagulation connu'
            ]
        ],
        'urine' => [
            'title' => 'Analyse d\'Urine',
            'suggestions' => [
                'ECBU (Examen Cytobactériologique des Urines)',
                'Bandelette urinaire',
                'Protéinurie des 24h',
                'Créatinurie',
                'Microalbuminurie',
                'pH urinaire',
                'Densité urinaire',
                'Recherche de glucose'
            ],
            'preparation' => 'Première urine du matin de préférence, toilette intime soigneuse',
            'delai' => '24-72 heures',
            'indications' => [
                'Infection urinaire suspectée',
                'Douleurs à la miction',
                'Diabète (surveillance)',
                'Maladie rénale'
            ],
            'contraindications' => [
                'Éviter pendant les règles',
                'Ne pas contaminer l\'échantillon'
            ]
        ],
        'imagerie' => [
            'title' => 'Imagerie Médicale',
            'suggestions' => [
                'Radiographie (RX)',
                'Échographie abdominale',
                'Échographie pelvienne',
                'Échographie obstétricale',
                'Scanner (TDM)',
                'IRM (Imagerie par Résonance Magnétique)',
                'Mammographie',
                'Doppler vasculaire'
            ],
            'preparation' => 'Variable selon le type d\'examen (voir instructions spécifiques)',
            'delai' => '1-7 jours selon urgence',
            'indications' => [
                'Diagnostic de fractures',
                'Surveillance de grossesse',
                'Investigation abdominale',
                'Douleurs chroniques'
            ],
            'contraindications' => [
                'IRM : prothèses métalliques, pacemaker',
                'Scanner/RX : grossesse (sauf urgence)',
                'Produits de contraste : allergie, insuffisance rénale'
            ]
        ],
        'specialisee' => [
            'title' => 'Analyse Spécialisée',
            'suggestions' => [
                'Dosage hormonal (TSH, T3, T4)',
                'Bilan thyroïdien complet',
                'Dosage vitamine D',
                'Dosage vitamine B12',
                'Ferritine',
                'Électrophorèse des protéines',
                'Marqueurs tumoraux',
                'Sérologie virale (HIV, Hépatites)'
            ],
            'preparation' => 'Jeûne de 12 heures, prélèvement le matin',
            'delai' => '3-7 jours',
            'indications' => [
                'Troubles thyroïdiens',
                'Fatigue chronique',
                'Carences suspectées',
                'Dépistage cancer'
            ],
            'contraindications' => [
                'Arrêt de certains médicaments 48h avant',
                'Consulter le médecin avant l\'examen'
            ]
        ],
        'microbiologie' => [
            'title' => 'Microbiologie',
            'suggestions' => [
                'Hémoculture',
                'Culture de selles (coproculture)',
                'Prélèvement de gorge',
                'Prélèvement nasal',
                'Antibiogramme',
                'Recherche de parasites',
                'Test COVID-19 (PCR)',
                'Test rapide streptocoque'
            ],
            'preparation' => 'Aucune préparation spéciale, éviter antibiotiques récents',
            'delai' => '2-5 jours (cultures), 24-48h (tests rapides)',
            'indications' => [
                'Infection bactérienne suspectée',
                'Fièvre d\'origine inconnue',
                'Diarrhée persistante',
                'Angine'
            ],
            'contraindications' => [
                'Informer de tout traitement antibiotique récent'
            ]
        ],
        'biochimie' => [
            'title' => 'Biochimie Sanguine',
            'suggestions' => [
                'Glycémie à jeun',
                'Bilan lipidique (cholestérol, triglycérides)',
                'Bilan hépatique (transaminases)',
                'Bilan rénal (créatinine, urée)',
                'Ionogramme sanguin',
                'Calcémie',
                'Acide urique',
                'HbA1c (hémoglobine glyquée)'
            ],
            'preparation' => 'Jeûne strict de 12 heures',
            'delai' => '24-48 heures',
            'indications' => [
                'Diabète (diagnostic ou suivi)',
                'Hypercholestérolémie',
                'Bilan hépatique ou rénal',
                'Déséquilibre électrolytique'
            ],
            'contraindications' => [
                'Respecter le jeûne pour résultats fiables',
                'Hydratation normale autorisée (eau)'
            ]
        ],
        'hematologie' => [
            'title' => 'Hématologie Spécialisée',
            'suggestions' => [
                'Bilan de coagulation (TP, TCA)',
                'INR (patients sous anticoagulants)',
                'Fibrinogène',
                'D-dimères',
                'Groupage sanguin',
                'Recherche d\'agglutinines irrégulières (RAI)',
                'Électrophorèse de l\'hémoglobine',
                'Myélogramme'
            ],
            'preparation' => 'Variable selon l\'examen',
            'delai' => '1-3 jours',
            'indications' => [
                'Troubles de la coagulation',
                'Avant chirurgie',
                'Suivi traitement anticoagulant',
                'Transfusion sanguine'
            ],
            'contraindications' => [
                'Informer de tout traitement anticoagulant',
                'Signaler antécédents hémorragiques'
            ]
        ],
        'immunologie' => [
            'title' => 'Immunologie',
            'suggestions' => [
                'Sérologie toxoplasmose',
                'Sérologie rubéole',
                'Sérologie CMV',
                'Recherche anticorps antinucléaires',
                'Dosage des immunoglobulines',
                'Complément (C3, C4)',
                'Allergologie (IgE spécifiques)',
                'Bilan auto-immun'
            ],
            'preparation' => 'Aucune préparation spéciale',
            'delai' => '3-7 jours',
            'indications' => [
                'Grossesse (bilan TORCH)',
                'Maladies auto-immunes',
                'Allergies',
                'Déficits immunitaires'
            ],
            'contraindications' => [
                'Informer de tout traitement immunosuppresseur'
            ]
        ]
    ];
    
    // Type codé en dur OU type configuré dans Paramètres → Tarifs labo
    if (isset($analysesSuggestions[$typeAnalyse])) {
        $data = $analysesSuggestions[$typeAnalyse];
    } else {
        $tarifModel = new TarifAnalyseLaboratoire();
        $tarif = $tarifModel->getByCode($typeAnalyse);
        if (!$tarif || ($tarif['statut'] ?? '') !== 'actif') {
            echo json_encode([
                'success' => false,
                'error' => 'Type d\'analyse non reconnu ou inactif',
            ]);
            exit();
        }
        $data = TarifAnalyseLaboratoire::buildSuggestionsBase($tarif);
    }
    $patientAge = null;
    $patientSexe = null;
    
    // Ajouter des informations personnalisées si un patient est sélectionné
    if ($patientId) {
        try {
            $patientModel = new Patient();
            $patient = $patientModel->getById($patientId);
            
            if ($patient) {
                // Calculer l'âge
                if (!empty($patient['date_naissance'])) {
                    $dateNaissance = new DateTime($patient['date_naissance']);
                    $aujourdhui = new DateTime();
                    $patientAge = $dateNaissance->diff($aujourdhui)->y;
                }
                $patientSexe = $patient['sexe'];
                
                // Personnaliser selon l'âge et le sexe
                $data['patient_info'] = [
                    'age' => $patientAge,
                    'sexe' => $patientSexe,
                    'personalized' => true
                ];
                
                // Ajouter des suggestions spécifiques selon l'âge et le sexe
                if ($typeAnalyse === 'sang' || $typeAnalyse === 'biochimie') {
                    if ($patientAge > 50) {
                        $data['suggestions'][] = 'PSA (Antigène Prostatique Spécifique)' . ($patientSexe === 'M' ? ' - Recommandé' : '');
                        $data['indications'][] = 'Dépistage lié à l\'âge (>50 ans)';
                    }
                    
                    if ($patientSexe === 'F' && $patientAge >= 18 && $patientAge <= 50) {
                        $data['indications'][] = 'Bilan en âge de procréer';
                    }
                }
                
                if ($typeAnalyse === 'imagerie' && $patientSexe === 'F' && $patientAge >= 40) {
                    $data['suggestions'][] = 'Mammographie de dépistage (recommandée à partir de 40-50 ans)';
                }
            }
        } catch (Exception $e) {
            // Si erreur lors de la récupération du patient, continuer sans personnalisation
            error_log('Erreur récupération patient: ' . $e->getMessage());
        }
    }

    $mistral = MistralAIService::getInstance();
    $mistralMeta = ['enriched' => false];
    if ($mistral->isEnabledForLaboratoire()) {
        $description = isset($_GET['description']) ? trim($_GET['description']) : '';
        if (!empty($data['type_description'])) {
            $description = trim($description . "\n" . $data['type_description']);
        }
        $description = $description !== '' ? $description : null;
        $mistralMeta = $mistral->enrichLaboratoireSuggestions(
            $data,
            $typeAnalyse,
            $data['title'] ?? $typeAnalyse,
            $patientAge,
            $patientSexe,
            $description
        );
        if (!empty($mistralMeta['error'])) {
            $mistralMeta['error'] = 'Complément Mistral indisponible';
        }
    }
    
    // Retourner la réponse
    echo json_encode([
        'success' => true,
        'data' => $data,
        'ia_config' => $mistral->getPublicConfig(),
        'mistral' => $mistralMeta,
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
