<?php
/**
 * Service d'Intelligence Artificielle Dermatologique
 * Basé sur les catégories HAM10000/ISIC pour la classification des lésions cutanées
 * 
 * Catégories diagnostiques :
 * - nv    : Naevus mélanocytaire (grain de beauté)
 * - mel   : Mélanome
 * - bkl   : Kératose séborrhéique / lésions bénignes kératosiques
 * - bcc   : Carcinome basocellulaire
 * - akiec : Kératose actinique / Maladie de Bowen
 * - vasc  : Lésions vasculaires (angiomes, etc.)
 * - df    : Dermatofibrome
 */

class DermatologyAI {
    
    /**
     * Base de connaissances des lésions cutanées (HAM10000)
     */
    private static $lesionTypes = [
        'nv' => [
            'code' => 'nv',
            'nom' => 'Naevus mélanocytaire',
            'nom_commun' => 'Grain de beauté',
            'description' => 'Lésion pigmentée bénigne très fréquente, constituée de cellules mélanocytaires.',
            'caracteristiques' => [
                'Couleur uniforme (brun, noir ou couleur chair)',
                'Bords réguliers et bien définis',
                'Forme symétrique généralement ronde ou ovale',
                'Taille stable dans le temps',
                'Surface lisse ou légèrement surélevée'
            ],
            'signes_alerte' => [
                'Changement de couleur rapide',
                'Augmentation de taille soudaine',
                'Bords devenant irréguliers',
                'Saignement spontané'
            ],
            'gravite' => 'benin',
            'urgence' => 'faible',
            'recommandations' => [
                'Surveillance régulière (auto-examen mensuel)',
                'Photo de référence pour comparaison',
                'Consultation annuelle chez le dermatologue si nombreux naevi',
                'Protection solaire recommandée'
            ],
            'couleurs_typiques' => ['brun', 'noir', 'beige', 'rose'],
            'prevalence' => 'très fréquent'
        ],
        
        'mel' => [
            'code' => 'mel',
            'nom' => 'Mélanome',
            'nom_commun' => 'Cancer de la peau (mélanome)',
            'description' => 'Tumeur maligne des mélanocytes. Cancer de la peau le plus dangereux nécessitant une prise en charge urgente.',
            'caracteristiques' => [
                'Asymétrie de la lésion',
                'Bords irréguliers, dentelés ou mal définis',
                'Couleur hétérogène (plusieurs teintes)',
                'Diamètre > 6mm (mais peut être plus petit)',
                'Évolution rapide (taille, forme, couleur)'
            ],
            'signes_alerte' => [
                'Règle ABCDE positive',
                'Lésion différente des autres (signe du vilain petit canard)',
                'Saignement ou ulcération',
                'Prurit ou douleur',
                'Apparition récente chez adulte > 30 ans'
            ],
            'gravite' => 'grave',
            'urgence' => 'haute',
            'recommandations' => [
                'CONSULTATION DERMATOLOGIQUE URGENTE (< 2 semaines)',
                'Ne pas manipuler ou traumatiser la lésion',
                'Biopsie nécessaire pour confirmation',
                'Bilan d\'extension si confirmé',
                'Traitement chirurgical rapide essentiel'
            ],
            'couleurs_typiques' => ['noir', 'brun foncé', 'bleu', 'rouge', 'blanc', 'polychrome'],
            'prevalence' => 'rare mais grave'
        ],
        
        'bkl' => [
            'code' => 'bkl',
            'nom' => 'Kératose séborrhéique',
            'nom_commun' => 'Verrue séborrhéique',
            'description' => 'Lésion bénigne très fréquente chez les personnes âgées, d\'aspect "collé" sur la peau.',
            'caracteristiques' => [
                'Aspect "collé" ou "posé" sur la peau',
                'Surface verruqueuse, rugueuse',
                'Couleur variable (beige à brun-noir)',
                'Bords bien délimités',
                'Présence fréquente de pseudo-kystes cornés'
            ],
            'signes_alerte' => [
                'Croissance très rapide',
                'Inflammation ou irritation persistante',
                'Apparition de multiples lésions soudainement (signe de Leser-Trélat)'
            ],
            'gravite' => 'benin',
            'urgence' => 'faible',
            'recommandations' => [
                'Surveillance simple habituellement suffisante',
                'Ablation possible si gêne esthétique ou frottement',
                'Consultation si doute diagnostique',
                'Cryothérapie ou curetage si traitement souhaité'
            ],
            'couleurs_typiques' => ['beige', 'brun', 'brun foncé', 'noir'],
            'prevalence' => 'très fréquent après 50 ans'
        ],
        
        'bcc' => [
            'code' => 'bcc',
            'nom' => 'Carcinome basocellulaire',
            'nom_commun' => 'Cancer de la peau (basocellulaire)',
            'description' => 'Cancer cutané le plus fréquent, d\'évolution lente et locale. Rarement métastatique mais destruction locale possible.',
            'caracteristiques' => [
                'Papule perlée translucide',
                'Télangiectasies (petits vaisseaux) à la surface',
                'Bords surélevés, perlés',
                'Ulcération centrale possible',
                'Croissance lente sur mois/années'
            ],
            'signes_alerte' => [
                'Lésion qui ne cicatrise pas',
                'Saignement au moindre traumatisme',
                'Croûte récidivante',
                'Extension progressive'
            ],
            'gravite' => 'modere',
            'urgence' => 'moyenne',
            'recommandations' => [
                'Consultation dermatologique dans le mois',
                'Biopsie pour confirmation diagnostique',
                'Traitement chirurgical le plus souvent',
                'Surveillance des récidives',
                'Protection solaire stricte'
            ],
            'couleurs_typiques' => ['rose', 'rouge pâle', 'translucide', 'pigmenté'],
            'prevalence' => 'fréquent (zones photo-exposées)'
        ],
        
        'akiec' => [
            'code' => 'akiec',
            'nom' => 'Kératose actinique',
            'nom_commun' => 'Kératose solaire / Maladie de Bowen',
            'description' => 'Lésion précancéreuse due à l\'exposition solaire chronique. Peut évoluer vers un carcinome épidermoïde.',
            'caracteristiques' => [
                'Surface rugueuse, squameuse',
                'Plaque érythémateuse',
                'Localisation zones photo-exposées',
                'Sensation de papier de verre au toucher',
                'Croûtes adhérentes récidivantes'
            ],
            'signes_alerte' => [
                'Épaississement de la lésion',
                'Induration à la palpation',
                'Saignement',
                'Douleur ou sensibilité'
            ],
            'gravite' => 'precancereux',
            'urgence' => 'moyenne',
            'recommandations' => [
                'Consultation dermatologique recommandée',
                'Traitement pour prévenir l\'évolution',
                'Cryothérapie, 5-FU topique, ou photothérapie dynamique',
                'Protection solaire indispensable',
                'Surveillance régulière des autres zones exposées'
            ],
            'couleurs_typiques' => ['rose', 'rouge', 'brun clair'],
            'prevalence' => 'fréquent chez phototypes clairs'
        ],
        
        'vasc' => [
            'code' => 'vasc',
            'nom' => 'Lésion vasculaire',
            'nom_commun' => 'Angiome / Hémangiome',
            'description' => 'Lésion bénigne constituée de vaisseaux sanguins. Inclut les angiomes, hémangiomes et malformations vasculaires.',
            'caracteristiques' => [
                'Couleur rouge à violacée',
                'S\'efface partiellement à la vitropression',
                'Bords nets ou diffus selon le type',
                'Surface plane ou surélevée',
                'Chaleur locale possible'
            ],
            'signes_alerte' => [
                'Croissance rapide inhabituelle',
                'Saignement spontané',
                'Ulcération',
                'Localisation gênante (paupière, lèvre)'
            ],
            'gravite' => 'benin',
            'urgence' => 'faible',
            'recommandations' => [
                'Surveillance simple pour la plupart',
                'Traitement possible si gêne esthétique',
                'Laser vasculaire si indication',
                'Consultation si localisation problématique'
            ],
            'couleurs_typiques' => ['rouge', 'violet', 'bleu', 'rose'],
            'prevalence' => 'fréquent'
        ],
        
        'df' => [
            'code' => 'df',
            'nom' => 'Dermatofibrome',
            'nom_commun' => 'Histiocytofibrome',
            'description' => 'Nodule fibreux bénin, souvent secondaire à un traumatisme ou piqûre d\'insecte.',
            'caracteristiques' => [
                'Nodule ferme à la palpation',
                'Signe de la fossette positif (dépression à la compression latérale)',
                'Couleur brun-rosé',
                'Diamètre généralement < 1 cm',
                'Localisation fréquente sur les jambes'
            ],
            'signes_alerte' => [
                'Croissance rapide',
                'Taille > 2 cm',
                'Récidive après exérèse'
            ],
            'gravite' => 'benin',
            'urgence' => 'faible',
            'recommandations' => [
                'Abstention thérapeutique le plus souvent',
                'Exérèse si gêne ou doute diagnostique',
                'Surveillance simple',
                'Rassurance du patient'
            ],
            'couleurs_typiques' => ['brun', 'rose', 'rouge-brun'],
            'prevalence' => 'fréquent'
        ]
    ];
    
    /**
     * Règle ABCDE pour l'évaluation des lésions pigmentées
     */
    private static $regleABCDE = [
        'A' => [
            'critere' => 'Asymétrie',
            'description' => 'La lésion n\'est pas symétrique selon ses axes',
            'score_melanome' => 1
        ],
        'B' => [
            'critere' => 'Bords',
            'description' => 'Bords irréguliers, dentelés ou mal définis',
            'score_melanome' => 1
        ],
        'C' => [
            'critere' => 'Couleur',
            'description' => 'Couleur hétérogène, plusieurs teintes',
            'score_melanome' => 1
        ],
        'D' => [
            'critere' => 'Diamètre',
            'description' => 'Diamètre supérieur à 6 mm',
            'score_melanome' => 1
        ],
        'E' => [
            'critere' => 'Évolution',
            'description' => 'Modification récente de taille, forme ou couleur',
            'score_melanome' => 2
        ]
    ];
    
    /**
     * Analyser les caractéristiques visuelles extraites d'une image
     * 
     * @param array $features Caractéristiques extraites de l'image
     * @return array Résultat de l'analyse avec probabilités
     */
    public static function analyzeFeatures(array $features): array {
        $scores = [];
        
        // Calculer un score pour chaque type de lésion basé sur les caractéristiques
        foreach (self::$lesionTypes as $code => $lesion) {
            $score = self::calculateLesionScore($features, $lesion);
            $scores[$code] = $score;
        }
        
        // Normaliser les scores en probabilités
        $total = array_sum($scores);
        $probabilities = [];
        
        if ($total > 0) {
            foreach ($scores as $code => $score) {
                $probabilities[$code] = round($score / $total, 4);
            }
        }
        
        // Trier par probabilité décroissante
        arsort($probabilities);
        
        // Construire le résultat
        $results = [];
        foreach ($probabilities as $code => $prob) {
            $lesion = self::$lesionTypes[$code];
            $results[] = [
                'code' => $code,
                'nom' => $lesion['nom'],
                'nom_commun' => $lesion['nom_commun'],
                'probabilite' => $prob,
                'pourcentage' => round($prob * 100, 1),
                'gravite' => $lesion['gravite'],
                'urgence' => $lesion['urgence']
            ];
        }
        
        return $results;
    }
    
    /**
     * Calculer le score d'une lésion basé sur les caractéristiques
     */
    private static function calculateLesionScore(array $features, array $lesion): float {
        $score = 1.0; // Score de base
        
        // Analyse des couleurs
        if (isset($features['dominant_colors'])) {
            $colorMatch = self::matchColors($features['dominant_colors'], $lesion['couleurs_typiques']);
            $score += $colorMatch * 2;
        }
        
        // Analyse de la forme
        if (isset($features['symmetry'])) {
            if ($lesion['code'] === 'mel' && $features['symmetry'] < 0.5) {
                $score += 3; // Asymétrie favorise mélanome
            } elseif ($lesion['code'] === 'nv' && $features['symmetry'] > 0.7) {
                $score += 2; // Symétrie favorise naevus bénin
            }
        }
        
        // Analyse des bords
        if (isset($features['border_regularity'])) {
            if ($lesion['code'] === 'mel' && $features['border_regularity'] < 0.5) {
                $score += 3; // Bords irréguliers favorisent mélanome
            } elseif (in_array($lesion['code'], ['nv', 'df']) && $features['border_regularity'] > 0.7) {
                $score += 2;
            }
        }
        
        // Analyse de la texture
        if (isset($features['texture'])) {
            if ($lesion['code'] === 'bkl' && $features['texture'] === 'rugueuse') {
                $score += 3;
            } elseif ($lesion['code'] === 'akiec' && $features['texture'] === 'squameuse') {
                $score += 3;
            } elseif ($lesion['code'] === 'bcc' && $features['texture'] === 'perlee') {
                $score += 4;
            }
        }
        
        // Analyse de la taille
        if (isset($features['size_mm'])) {
            if ($lesion['code'] === 'mel' && $features['size_mm'] > 6) {
                $score += 2;
            } elseif ($lesion['code'] === 'df' && $features['size_mm'] < 10) {
                $score += 1;
            }
        }
        
        // Analyse de l'hétérogénéité des couleurs
        if (isset($features['color_heterogeneity'])) {
            if ($lesion['code'] === 'mel' && $features['color_heterogeneity'] > 0.6) {
                $score += 4; // Forte hétérogénéité favorise mélanome
            } elseif ($lesion['code'] === 'nv' && $features['color_heterogeneity'] < 0.3) {
                $score += 2;
            }
        }
        
        return max($score, 0.1);
    }
    
    /**
     * Correspondance des couleurs
     */
    private static function matchColors(array $detected, array $typical): float {
        $matches = 0;
        foreach ($detected as $color) {
            if (in_array(strtolower($color), $typical)) {
                $matches++;
            }
        }
        return $matches > 0 ? $matches / count($detected) : 0;
    }
    
    /**
     * Obtenir les informations détaillées d'un type de lésion
     */
    public static function getLesionInfo(string $code): ?array {
        return self::$lesionTypes[$code] ?? null;
    }
    
    /**
     * Obtenir tous les types de lésions
     */
    public static function getAllLesionTypes(): array {
        return self::$lesionTypes;
    }
    
    /**
     * Évaluer le risque selon la règle ABCDE
     */
    public static function evaluateABCDE(array $criteria): array {
        $score = 0;
        $details = [];
        
        foreach (self::$regleABCDE as $letter => $rule) {
            $present = $criteria[strtolower($letter)] ?? $criteria[$letter] ?? false;
            if ($present) {
                $score += $rule['score_melanome'];
                $details[] = [
                    'critere' => $letter . ' - ' . $rule['critere'],
                    'present' => true,
                    'description' => $rule['description']
                ];
            }
        }
        
        $riskLevel = 'faible';
        $recommendation = 'Surveillance régulière recommandée.';
        
        if ($score >= 4) {
            $riskLevel = 'eleve';
            $recommendation = 'CONSULTATION DERMATOLOGIQUE URGENTE recommandée.';
        } elseif ($score >= 2) {
            $riskLevel = 'modere';
            $recommendation = 'Consultation dermatologique conseillée dans le mois.';
        }
        
        return [
            'score' => $score,
            'score_max' => 6,
            'niveau_risque' => $riskLevel,
            'details' => $details,
            'recommandation' => $recommendation
        ];
    }
    
    /**
     * Générer un rapport complet d'analyse
     */
    public static function generateReport(array $predictions, array $abcdeEvaluation = null): array {
        if (empty($predictions)) {
            return [
                'status' => 'error',
                'message' => 'Aucune prédiction disponible'
            ];
        }
        
        $topPrediction = $predictions[0];
        $lesionInfo = self::getLesionInfo($topPrediction['code']);
        
        $report = [
            'status' => 'success',
            'diagnostic_principal' => [
                'code' => $topPrediction['code'],
                'nom' => $topPrediction['nom'],
                'nom_commun' => $topPrediction['nom_commun'],
                'confiance' => $topPrediction['pourcentage'],
                'gravite' => $topPrediction['gravite'],
                'urgence' => $topPrediction['urgence']
            ],
            'description' => $lesionInfo['description'] ?? '',
            'caracteristiques_attendues' => $lesionInfo['caracteristiques'] ?? [],
            'signes_alerte' => $lesionInfo['signes_alerte'] ?? [],
            'recommandations' => $lesionInfo['recommandations'] ?? [],
            'diagnostics_differentiels' => array_slice($predictions, 1, 3),
            'avertissement_medical' => self::getMedicalDisclaimer($topPrediction['gravite'])
        ];
        
        if ($abcdeEvaluation) {
            $report['evaluation_abcde'] = $abcdeEvaluation;
        }
        
        // Niveau d'urgence global
        $report['niveau_urgence'] = self::getUrgencyLevel($topPrediction);
        
        return $report;
    }
    
    /**
     * Obtenir le niveau d'urgence
     */
    private static function getUrgencyLevel(array $prediction): array {
        $urgencyLevels = [
            'haute' => [
                'niveau' => 3,
                'couleur' => '#dc3545',
                'message' => 'Consultation dermatologique urgente recommandée (sous 2 semaines)',
                'icone' => 'exclamation-triangle'
            ],
            'moyenne' => [
                'niveau' => 2,
                'couleur' => '#ffc107',
                'message' => 'Consultation dermatologique conseillée (dans le mois)',
                'icone' => 'exclamation-circle'
            ],
            'faible' => [
                'niveau' => 1,
                'couleur' => '#28a745',
                'message' => 'Surveillance régulière, consultation si évolution',
                'icone' => 'check-circle'
            ]
        ];
        
        return $urgencyLevels[$prediction['urgence']] ?? $urgencyLevels['faible'];
    }
    
    /**
     * Obtenir l'avertissement médical approprié
     */
    private static function getMedicalDisclaimer(string $gravite): string {
        $disclaimers = [
            'grave' => "⚠️ ATTENTION : Cette analyse suggère une lésion potentiellement grave. " .
                      "Une consultation dermatologique URGENTE est fortement recommandée. " .
                      "Cette analyse IA ne remplace en aucun cas un examen médical professionnel.",
            
            'precancereux' => "⚠️ Cette analyse suggère une lésion précancéreuse potentielle. " .
                             "Une consultation dermatologique est recommandée pour évaluation et traitement préventif. " .
                             "Seul un médecin peut établir un diagnostic définitif.",
            
            'modere' => "ℹ️ Cette analyse suggère une lésion nécessitant une évaluation médicale. " .
                       "Une consultation dermatologique est conseillée. " .
                       "Cette analyse IA est fournie à titre indicatif uniquement.",
            
            'benin' => "ℹ️ Cette analyse suggère une lésion probablement bénigne. " .
                      "Cependant, seul un examen médical professionnel peut confirmer ce diagnostic. " .
                      "Consultez un dermatologue en cas de doute ou d'évolution."
        ];
        
        return $disclaimers[$gravite] ?? $disclaimers['benin'];
    }
    
    /**
     * Analyser une image et retourner les prédictions
     * Cette méthode simule l'analyse basée sur les caractéristiques extraites
     * En production, elle serait connectée à un vrai modèle ML
     */
    public static function analyzeImage(string $imageBase64, array $metadata = []): array {
        // Extraire les caractéristiques de l'image
        // En production, cela utiliserait TensorFlow ou un autre framework ML
        $features = self::extractImageFeatures($imageBase64, $metadata);
        
        // Analyser les caractéristiques
        $predictions = self::analyzeFeatures($features);
        
        // Générer le rapport
        $report = self::generateReport($predictions);
        
        return [
            'predictions' => $predictions,
            'features' => $features,
            'report' => $report
        ];
    }
    
    /**
     * Extraire les caractéristiques d'une image
     * Version simplifiée - en production, utiliserait un vrai extracteur de features
     */
    private static function extractImageFeatures(string $imageBase64, array $metadata = []): array {
        // Décoder l'image
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));
        
        if (!$imageData) {
            return ['error' => 'Image invalide'];
        }
        
        // Créer une image GD pour l'analyse basique
        $image = @imagecreatefromstring($imageData);
        
        if (!$image) {
            return ['error' => 'Impossible de traiter l\'image'];
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Analyse basique des couleurs
        $colors = self::analyzeImageColors($image, $width, $height);
        
        // Cleanup
        imagedestroy($image);
        
        // Combiner avec les métadonnées fournies par l'utilisateur
        $features = array_merge([
            'dominant_colors' => $colors['dominant'],
            'color_heterogeneity' => $colors['heterogeneity'],
            'image_width' => $width,
            'image_height' => $height
        ], $metadata);
        
        return $features;
    }
    
    /**
     * Analyser les couleurs d'une image
     */
    private static function analyzeImageColors($image, int $width, int $height): array {
        $colorCounts = [];
        $sampleSize = min(1000, $width * $height);
        $step = max(1, floor(($width * $height) / $sampleSize));
        
        $totalPixels = 0;
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                $colorName = self::classifyColor($r, $g, $b);
                $colorCounts[$colorName] = ($colorCounts[$colorName] ?? 0) + 1;
                $totalPixels++;
            }
        }
        
        // Trier par fréquence
        arsort($colorCounts);
        
        // Calculer l'hétérogénéité
        $dominantCount = reset($colorCounts);
        $heterogeneity = 1 - ($dominantCount / $totalPixels);
        
        return [
            'dominant' => array_keys(array_slice($colorCounts, 0, 3)),
            'distribution' => $colorCounts,
            'heterogeneity' => round($heterogeneity, 2)
        ];
    }
    
    /**
     * Classifier une couleur RGB en catégorie dermatologique
     */
    private static function classifyColor(int $r, int $g, int $b): string {
        // Convertir en HSL pour une meilleure classification
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2 / 255;
        
        if ($max === $min) {
            // Gris
            if ($l > 0.8) return 'blanc';
            if ($l < 0.2) return 'noir';
            return 'gris';
        }
        
        $d = ($max - $min) / 255;
        $s = $l > 0.5 ? $d / (2 - $max/255 - $min/255) : $d / ($max/255 + $min/255);
        
        // Calculer la teinte
        $h = 0;
        if ($max === $r) {
            $h = 60 * fmod((($g - $b) / ($max - $min)), 6);
        } elseif ($max === $g) {
            $h = 60 * ((($b - $r) / ($max - $min)) + 2);
        } else {
            $h = 60 * ((($r - $g) / ($max - $min)) + 4);
        }
        if ($h < 0) $h += 360;
        
        // Classification dermatologique
        if ($s < 0.15) {
            if ($l > 0.8) return 'blanc';
            if ($l < 0.2) return 'noir';
            return 'gris';
        }
        
        if ($h >= 0 && $h < 30) return 'rouge';
        if ($h >= 30 && $h < 60) return 'brun';
        if ($h >= 60 && $h < 90) return 'beige';
        if ($h >= 90 && $h < 150) return 'vert'; // rare en dermato
        if ($h >= 150 && $h < 210) return 'bleu';
        if ($h >= 210 && $h < 270) return 'violet';
        if ($h >= 270 && $h < 330) return 'rose';
        if ($h >= 330 && $h <= 360) return 'rouge';
        
        return 'autre';
    }
}
