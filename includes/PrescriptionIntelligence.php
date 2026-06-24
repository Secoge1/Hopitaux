<?php
/**
 * Service de prescription intelligente pour les analyses médicales
 * Fournit des suggestions automatiques basées sur le type d'analyse sélectionné
 */
class PrescriptionIntelligence {
    
    /**
     * Base de connaissances médicales pour les suggestions d'analyses
     */
    private static $medicalKnowledge = [
        'sang' => [
            'title' => 'Analyse Sanguine Complète',
            'suggestions' => [
                'NFS (Numération Formule Sanguine)',
                'Glycémie à jeun',
                'Cholestérol total et HDL/LDL',
                'Triglycérides',
                'Fonction rénale (urée, créatinine)',
                'Fonction hépatique (transaminases, bilirubine)',
                'Ionogramme sanguin (Na, K, Cl)',
                'Vitamine D',
                'TSH (fonction thyroïdienne)',
                'Ferritine'
            ],
            'preparation' => 'Patient à jeun depuis 12h, pas d\'activité physique intense 24h avant',
            'delai' => '2-3 jours ouvrables',
            'indications' => [
                'Bilan de santé annuel',
                'Surveillance de traitement',
                'Symptômes non spécifiques',
                'Dépistage préventif'
            ],
            'contraindications' => [
                'Patient sous anticoagulants (précautions)',
                'Allergie aux antiseptiques'
            ]
        ],
        
        'urine' => [
            'title' => 'Analyse d\'Urine',
            'suggestions' => [
                'ECBU (Examen Cytobactériologique des Urines)',
                'Protéinurie 24h',
                'Glycosurie',
                'Hématurie',
                'Cultures urinaires',
                'Recherche de cristaux',
                'pH urinaire',
                'Densité urinaire'
            ],
            'preparation' => 'Première miction du matin, toilette intime préalable',
            'delai' => '1-2 jours ouvrables',
            'indications' => [
                'Infection urinaire suspectée',
                'Surveillance rénale',
                'Diabète',
                'Hypertension artérielle'
            ],
            'contraindications' => [
                'Menstruations en cours',
                'Traitement antibiotique récent'
            ]
        ],
        
        'imagerie' => [
            'title' => 'Imagerie Médicale',
            'suggestions' => [
                'Radiographie thoracique',
                'Échographie abdominale',
                'IRM cérébrale',
                'Scanner thoraco-abdomino-pelvien',
                'Échographie cardiaque',
                'Mammographie',
                'Densitométrie osseuse',
                'Coloscopie virtuelle'
            ],
            'preparation' => 'Variable selon l\'examen, jeûne souvent requis',
            'delai' => '3-7 jours ouvrables',
            'indications' => [
                'Diagnostic de pathologie',
                'Surveillance d\'évolution',
                'Dépistage préventif',
                'Contrôle post-traitement'
            ],
            'contraindications' => [
                'Grossesse (pour certains examens)',
                'Claustrophobie (IRM)',
                'Allergie aux produits de contraste'
            ]
        ],
        
        'specialisee' => [
            'title' => 'Tests Spécialisés',
            'suggestions' => [
                'Test de tolérance au glucose',
                'Épreuve d\'effort cardiaque',
                'Test de fonction respiratoire',
                'Électrocardiogramme 24h (Holter)',
                'Endoscopie digestive',
                'Biopsie',
                'Tests allergologiques',
                'Marqueurs tumoraux'
            ],
            'preparation' => 'Préparation spécifique selon le test',
            'delai' => '5-10 jours ouvrables',
            'indications' => [
                'Diagnostic spécialisé',
                'Surveillance de pathologie chronique',
                'Évaluation fonctionnelle',
                'Dépistage ciblé'
            ],
            'contraindications' => [
                'État clinique instable',
                'Allergies connues',
                'Contre-indications spécifiques au test'
            ]
        ],
        
        'microbiologie' => [
            'title' => 'Microbiologie',
            'suggestions' => [
                'Hémocultures',
                'Cultures de prélèvements divers',
                'Antibiogramme',
                'Recherche de parasites',
                'Tests sérologiques',
                'PCR virale',
                'Examen direct',
                'Tests de sensibilité'
            ],
            'preparation' => 'Prélèvement aseptique strict',
            'delai' => '3-5 jours ouvrables',
            'indications' => [
                'Infection suspectée',
                'Identification de germe',
                'Test de sensibilité',
                'Surveillance épidémiologique'
            ],
            'contraindications' => [
                'Antibiotiques récents',
                'Prélèvement contaminé'
            ]
        ],
        
        'biochimie' => [
            'title' => 'Biochimie Clinique',
            'suggestions' => [
                'Bilan lipidique complet',
                'Fonction pancréatique (amylase, lipase)',
                'Marqueurs cardiaques (troponine, BNP)',
                'Hormones thyroïdiennes',
                'Cortisol',
                'Insuline et peptide C',
                'Acide urique',
                'Phosphatases alcalines'
            ],
            'preparation' => 'Patient à jeun, horaire spécifique selon l\'analyse',
            'delai' => '2-4 jours ouvrables',
            'indications' => [
                'Évaluation métabolique',
                'Surveillance thérapeutique',
                'Dépistage endocrinien',
                'Bilan pré-opératoire'
            ],
            'contraindications' => [
                'Traitement hormonal récent',
                'Stress aigu'
            ]
        ],
        
        'hematologie' => [
            'title' => 'Hématologie',
            'suggestions' => [
                'NFS avec formule leucocytaire',
                'Vitesse de sédimentation',
                'CRP (Protéine C Réactive)',
                'Fibrinogène',
                'Temps de coagulation',
                'Plaquettes',
                'Hémoglobine glyquée (HbA1c)',
                'Frottis sanguin'
            ],
            'preparation' => 'Pas de préparation particulière',
            'delai' => '1-2 jours ouvrables',
            'indications' => [
                'Anémie suspectée',
                'Surveillance de traitement',
                'Infection ou inflammation',
                'Troubles de la coagulation'
            ],
            'contraindications' => [
                'Patient sous anticoagulants',
                'Hématome récent au site de ponction'
            ]
        ],
        
        'immunologie' => [
            'title' => 'Immunologie',
            'suggestions' => [
                'Immunoglobulines (IgG, IgM, IgA)',
                'Complément (C3, C4)',
                'Facteur rhumatoïde',
                'Anticorps antinucléaires',
                'Allergènes spécifiques',
                'Marqueurs d\'auto-immunité',
                'Tests de compatibilité',
                'Immunité vaccinale'
            ],
            'preparation' => 'Pas de préparation particulière',
            'delai' => '3-7 jours ouvrables',
            'indications' => [
                'Maladies auto-immunes',
                'Déficits immunitaires',
                'Allergies',
                'Surveillance post-transplantation'
            ],
            'contraindications' => [
                'Traitement immunosuppresseur',
                'Vaccination récente'
            ]
        ]
    ];
    
    /**
     * Obtenir les suggestions pour un type d'analyse donné
     */
    public static function getSuggestions($typeAnalyse) {
        if (!isset(self::$medicalKnowledge[$typeAnalyse])) {
            return [
                'error' => 'Type d\'analyse non reconnu',
                'suggestions' => []
            ];
        }
        
        $data = self::$medicalKnowledge[$typeAnalyse];
        
        return [
            'title' => $data['title'],
            'suggestions' => $data['suggestions'],
            'preparation' => $data['preparation'],
            'delai' => $data['delai'],
            'indications' => $data['indications'],
            'contraindications' => $data['contraindications'],
            'type' => $typeAnalyse
        ];
    }
    
    /**
     * Obtenir tous les types d'analyses disponibles
     */
    public static function getAllTypes() {
        return array_keys(self::$medicalKnowledge);
    }
    
    /**
     * Générer une description intelligente basée sur le type d'analyse
     */
    public static function generateDescription($typeAnalyse, $suggestions = []) {
        $data = self::getSuggestions($typeAnalyse);
        
        if (isset($data['error'])) {
            return "Analyse de type : " . ucfirst($typeAnalyse);
        }
        
        $description = "Analyse de type : " . $data['title'] . "\n\n";
        $description .= "Analyses suggérées :\n";
        
        foreach ($data['suggestions'] as $index => $suggestion) {
            $description .= "• " . $suggestion . "\n";
        }
        
        $description .= "\nPréparation requise : " . $data['preparation'] . "\n";
        $description .= "Délai de rendu : " . $data['delai'] . "\n";
        
        if (!empty($data['indications'])) {
            $description .= "\nIndications courantes :\n";
            foreach ($data['indications'] as $indication) {
                $description .= "• " . $indication . "\n";
            }
        }
        
        if (!empty($data['contraindications'])) {
            $description .= "\nContre-indications/Précautions :\n";
            foreach ($data['contraindications'] as $contraindication) {
                $description .= "• " . $contraindication . "\n";
            }
        }
        
        return $description;
    }
    
    /**
     * Générer des instructions intelligentes basées sur le type d'analyse
     */
    public static function generateInstructions($typeAnalyse) {
        $data = self::getSuggestions($typeAnalyse);
        
        if (isset($data['error'])) {
            return "Consulter le médecin pour les instructions spécifiques.";
        }
        
        $instructions = "INSTRUCTIONS POUR LE PATIENT :\n\n";
        $instructions .= "Préparation : " . $data['preparation'] . "\n\n";
        $instructions .= "Délai de rendu : " . $data['delai'] . "\n\n";
        
        if (!empty($data['contraindications'])) {
            $instructions .= "ATTENTION - Précautions à prendre :\n";
            foreach ($data['contraindications'] as $contraindication) {
                $instructions .= "⚠️ " . $contraindication . "\n";
            }
            $instructions .= "\n";
        }
        
        $instructions .= "IMPORTANT : Informer le laboratoire de tout traitement en cours ou d'antécédents médicaux pertinents.";
        
        return $instructions;
    }
    
    /**
     * Obtenir des suggestions personnalisées selon l'âge et le sexe du patient
     */
    public static function getPersonalizedSuggestions($typeAnalyse, $age = null, $sexe = null) {
        $baseSuggestions = self::getSuggestions($typeAnalyse);
        
        if (isset($baseSuggestions['error'])) {
            return $baseSuggestions;
        }
        
        $personalized = $baseSuggestions;
        
        // Suggestions personnalisées selon l'âge
        if ($age !== null) {
            if ($age >= 50) {
                $personalized['suggestions'][] = 'Dépistage du cancer colorectal (si applicable)';
                $personalized['suggestions'][] = 'Évaluation cardiovasculaire';
            }
            
            if ($age >= 65) {
                $personalized['suggestions'][] = 'Dépistage cognitif';
                $personalized['suggestions'][] = 'Évaluation de la fragilité';
            }
        }
        
        // Suggestions personnalisées selon le sexe
        if ($sexe === 'F') {
            $personalized['suggestions'][] = 'Dépistage gynécologique (si applicable)';
            $personalized['suggestions'][] = 'Évaluation hormonale';
        } elseif ($sexe === 'M') {
            $personalized['suggestions'][] = 'Dépistage prostatique (si applicable)';
            $personalized['suggestions'][] = 'Testostérone (si indiqué)';
        }
        
        return $personalized;
    }
}
?>



