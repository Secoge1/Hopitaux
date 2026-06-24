<?php
/**
 * Service d'Intelligence Artificielle pour l'Analyse Prédictive des Risques Patients
 * Évalue les risques de santé et recommande des actions préventives
 */
class PatientRiskIntelligence {
    
    /**
     * Base de connaissances des facteurs de risque
     */
    private static $riskFactors = [
        'age_risk' => [
            'infant' => ['age_range' => [0, 2], 'risk_multiplier' => 1.5, 'conditions' => ['infections', 'dehydration', 'fever']],
            'child' => ['age_range' => [2, 12], 'risk_multiplier' => 1.2, 'conditions' => ['accidents', 'infections', 'allergies']],
            'adolescent' => ['age_range' => [12, 18], 'risk_multiplier' => 1.1, 'conditions' => ['mental_health', 'substance_abuse', 'eating_disorders']],
            'adult' => ['age_range' => [18, 65], 'risk_multiplier' => 1.0, 'conditions' => ['chronic_diseases', 'lifestyle_diseases', 'work_stress']],
            'senior' => ['age_range' => [65, 120], 'risk_multiplier' => 2.0, 'conditions' => ['cardiovascular', 'dementia', 'falls', 'medication_interactions']]
        ],
        'medical_history' => [
            'diabetes' => ['risk_score' => 3, 'related_conditions' => ['cardiovascular', 'kidney_disease', 'neuropathy']],
            'hypertension' => ['risk_score' => 2.5, 'related_conditions' => ['stroke', 'heart_disease', 'kidney_disease']],
            'heart_disease' => ['risk_score' => 4, 'related_conditions' => ['heart_failure', 'arrhythmia', 'sudden_death']],
            'cancer' => ['risk_score' => 3.5, 'related_conditions' => ['metastasis', 'treatment_complications', 'recurrence']],
            'kidney_disease' => ['risk_score' => 3, 'related_conditions' => ['dialysis', 'transplant_rejection', 'cardiovascular']],
            'liver_disease' => ['risk_score' => 3.5, 'related_conditions' => ['cirrhosis', 'liver_failure', 'bleeding']],
            'respiratory_disease' => ['risk_score' => 2.5, 'related_conditions' => ['respiratory_failure', 'infections', 'exacerbations']],
            'mental_health' => ['risk_score' => 2, 'related_conditions' => ['suicide_risk', 'substance_abuse', 'medication_adherence']]
        ],
        'lifestyle_factors' => [
            'smoking' => ['risk_score' => 3, 'impact' => ['lung_cancer', 'heart_disease', 'stroke']],
            'alcohol' => ['risk_score' => 2.5, 'impact' => ['liver_disease', 'cancer', 'mental_health']],
            'obesity' => ['risk_score' => 2.5, 'impact' => ['diabetes', 'heart_disease', 'joint_problems']],
            'sedentary' => ['risk_score' => 1.5, 'impact' => ['cardiovascular', 'diabetes', 'mental_health']],
            'poor_diet' => ['risk_score' => 2, 'impact' => ['cardiovascular', 'diabetes', 'cancer']],
            'stress' => ['risk_score' => 2, 'impact' => ['mental_health', 'cardiovascular', 'immune_system']]
        ],
        'environmental_factors' => [
            'malaria_zone' => ['risk_score' => 3, 'conditions' => ['malaria', 'dengue', 'yellow_fever']],
            'urban_area' => ['risk_score' => 1.5, 'conditions' => ['pollution', 'stress', 'lifestyle_diseases']],
            'rural_area' => ['risk_score' => 2, 'conditions' => ['infections', 'accidents', 'limited_access']],
            'tropical_climate' => ['risk_score' => 2.5, 'conditions' => ['vector_diseases', 'dehydration', 'skin_cancers']]
        ]
    ];
    
    /**
     * Analyser le risque global d'un patient
     */
    public static function analyzePatientRisk($patientId) {
        try {
            require_once __DIR__ . '/../models/Patient.php';
            $patientModel = new Patient();
            
            $patient = $patientModel->getById($patientId);
            if (!$patient) {
                return ['success' => false, 'error' => 'Patient non trouvé'];
            }
            
            // Calculer l'âge
            $age = $patientModel->calculateAge($patient['date_naissance']);
            
            // Analyser les différents types de risques
            $riskAnalysis = [
                'patient_info' => [
                    'id' => $patient['id'],
                    'nom' => $patient['nom'],
                    'prenom' => $patient['prenom'],
                    'age' => $age,
                    'sexe' => $patient['sexe']
                ],
                'risk_assessment' => [
                    'age_risk' => self::assessAgeRisk($age),
                    'medical_history_risk' => self::assessMedicalHistoryRisk($patient),
                    'lifestyle_risk' => self::assessLifestyleRisk($patient),
                    'environmental_risk' => self::assessEnvironmentalRisk($patient),
                    'allergy_risk' => self::assessAllergyRisk($patient)
                ],
                'overall_risk_score' => 0,
                'risk_level' => 'low',
                'recommendations' => [],
                'monitoring_schedule' => [],
                'preventive_actions' => []
            ];
            
            // Calculer le score de risque global
            $riskAnalysis['overall_risk_score'] = self::calculateOverallRiskScore($riskAnalysis['risk_assessment']);
            $riskAnalysis['risk_level'] = self::determineRiskLevel($riskAnalysis['overall_risk_score']);
            
            // Générer des recommandations
            $riskAnalysis['recommendations'] = self::generateRecommendations($riskAnalysis);
            $riskAnalysis['monitoring_schedule'] = self::generateMonitoringSchedule($riskAnalysis);
            $riskAnalysis['preventive_actions'] = self::generatePreventiveActions($riskAnalysis);
            
            return [
                'success' => true,
                'data' => $riskAnalysis
            ];
            
        } catch (Exception $e) {
            error_log("Erreur PatientRiskIntelligence: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'analyse des risques'
            ];
        }
    }
    
    /**
     * Prédire les risques futurs basés sur les tendances
     */
    public static function predictFutureRisks($patientId, $timeframe = 12) {
        try {
            require_once __DIR__ . '/../models/Patient.php';
            require_once __DIR__ . '/../models/Consultation.php';
            
            $patientModel = new Patient();
            $consultationModel = new Consultation();
            
            $patient = $patientModel->getById($patientId);
            if (!$patient) {
                return ['success' => false, 'error' => 'Patient non trouvé'];
            }
            
            // Analyser l'historique médical
            $consultations = $consultationModel->getByPatient($patientId);
            $age = $patientModel->calculateAge($patient['date_naissance']);
            
            // Prédictions basées sur l'âge et l'historique
            $predictions = [
                'patient_info' => [
                    'id' => $patient['id'],
                    'age' => $age,
                    'consultations_count' => count($consultations)
                ],
                'risk_predictions' => [
                    'short_term' => self::predictShortTermRisks($patient, $age, $consultations),
                    'medium_term' => self::predictMediumTermRisks($patient, $age, $consultations),
                    'long_term' => self::predictLongTermRisks($patient, $age, $consultations)
                ],
                'intervention_recommendations' => [],
                'monitoring_priorities' => []
            ];
            
            // Générer des recommandations d'intervention
            $predictions['intervention_recommendations'] = self::generateInterventionRecommendations($predictions);
            $predictions['monitoring_priorities'] = self::generateMonitoringPriorities($predictions);
            
            return [
                'success' => true,
                'data' => $predictions
            ];
            
        } catch (Exception $e) {
            error_log("Erreur prédiction risques: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la prédiction des risques'
            ];
        }
    }
    
    /**
     * Recommander un plan de suivi personnalisé
     */
    public static function recommendFollowUpPlan($patientId) {
        try {
            $riskAnalysis = self::analyzePatientRisk($patientId);
            if (!$riskAnalysis['success']) {
                return $riskAnalysis;
            }
            
            $patient = $riskAnalysis['data'];
            $riskLevel = $patient['risk_level'];
            $age = $patient['patient_info']['age'];
            
            $followUpPlan = [
                'patient_info' => $patient['patient_info'],
                'risk_level' => $riskLevel,
                'follow_up_schedule' => self::generateFollowUpSchedule($riskLevel, $age),
                'specialist_referrals' => self::generateSpecialistReferrals($patient),
                'screening_recommendations' => self::generateScreeningRecommendations($age, $patient),
                'lifestyle_modifications' => self::generateLifestyleModifications($patient),
                'medication_review' => self::generateMedicationReview($patient)
            ];
            
            return [
                'success' => true,
                'data' => $followUpPlan
            ];
            
        } catch (Exception $e) {
            error_log("Erreur plan de suivi: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la génération du plan de suivi'
            ];
        }
    }
    
    /**
     * Évaluer le risque lié à l'âge
     */
    private static function assessAgeRisk($age) {
        foreach (self::$riskFactors['age_risk'] as $category => $data) {
            if ($age >= $data['age_range'][0] && $age <= $data['age_range'][1]) {
                return [
                    'category' => $category,
                    'risk_score' => $data['risk_multiplier'],
                    'conditions_at_risk' => $data['conditions'],
                    'recommendations' => self::getAgeSpecificRecommendations($category, $age)
                ];
            }
        }
        
        return [
            'category' => 'unknown',
            'risk_score' => 1.0,
            'conditions_at_risk' => [],
            'recommendations' => []
        ];
    }
    
    /**
     * Évaluer le risque basé sur l'historique médical
     */
    private static function assessMedicalHistoryRisk($patient) {
        $riskScore = 1.0;
        $conditions = [];
        $recommendations = [];
        
        $antecedents = strtolower($patient['antecedents_medicaux'] ?? '');
        
        foreach (self::$riskFactors['medical_history'] as $condition => $data) {
            if (strpos($antecedents, strtolower($condition)) !== false) {
                $riskScore *= $data['risk_score'];
                $conditions[] = $condition;
                $recommendations = array_merge($recommendations, $data['related_conditions']);
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'identified_conditions' => $conditions,
            'related_risks' => array_unique($recommendations),
            'monitoring_needs' => self::getMonitoringNeeds($conditions)
        ];
    }
    
    /**
     * Évaluer le risque lié au mode de vie
     */
    private static function assessLifestyleRisk($patient) {
        $riskScore = 1.0;
        $identified_factors = [];
        $recommendations = [];
        
        // Analyser les notes du patient pour des indices sur le mode de vie
        $notes = strtolower($patient['notes'] ?? '');
        $antecedents = strtolower($patient['antecedents_medicaux'] ?? '');
        $allText = $notes . ' ' . $antecedents;
        
        foreach (self::$riskFactors['lifestyle_factors'] as $factor => $data) {
            $keywords = self::getLifestyleKeywords($factor);
            foreach ($keywords as $keyword) {
                if (strpos($allText, $keyword) !== false) {
                    $riskScore *= $data['risk_score'];
                    $identified_factors[] = $factor;
                    $recommendations = array_merge($recommendations, $data['impact']);
                    break;
                }
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'identified_factors' => array_unique($identified_factors),
            'health_impacts' => array_unique($recommendations),
            'lifestyle_recommendations' => self::getLifestyleRecommendations($identified_factors)
        ];
    }
    
    /**
     * Évaluer le risque environnemental
     */
    private static function assessEnvironmentalRisk($patient) {
        $riskScore = 1.0;
        $environmental_factors = [];
        
        // Basé sur la localisation (Mali par défaut)
        $country = $patient['pays'] ?? 'Mali';
        
        if ($country === 'Mali') {
            $environmental_factors[] = 'malaria_zone';
            $environmental_factors[] = 'tropical_climate';
            $riskScore *= self::$riskFactors['environmental_factors']['malaria_zone']['risk_score'];
            $riskScore *= self::$riskFactors['environmental_factors']['tropical_climate']['risk_score'];
        }
        
        // Déterminer si urbain ou rural basé sur la ville
        $ville = $patient['ville'] ?? '';
        if (in_array(strtolower($ville), ['bamako', 'sikasso', 'ségou', 'mopti'])) {
            $environmental_factors[] = 'urban_area';
            $riskScore *= self::$riskFactors['environmental_factors']['urban_area']['risk_score'];
        } else {
            $environmental_factors[] = 'rural_area';
            $riskScore *= self::$riskFactors['environmental_factors']['rural_area']['risk_score'];
        }
        
        return [
            'risk_score' => $riskScore,
            'environmental_factors' => $environmental_factors,
            'preventive_measures' => self::getEnvironmentalPreventiveMeasures($environmental_factors)
        ];
    }
    
    /**
     * Évaluer le risque lié aux allergies
     */
    private static function assessAllergyRisk($patient) {
        $allergies = $patient['allergies'] ?? '';
        $riskScore = 1.0;
        
        if (!empty($allergies)) {
            $riskScore = 2.0; // Augmentation du risque si allergies connues
            
            // Risque plus élevé pour certaines allergies
            $severe_allergies = ['pénicilline', 'aspirine', 'sulfamide', 'latex', 'iode'];
            foreach ($severe_allergies as $allergy) {
                if (strpos(strtolower($allergies), strtolower($allergy)) !== false) {
                    $riskScore = 3.0;
                    break;
                }
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'allergies_identified' => !empty($allergies),
            'allergy_list' => $allergies,
            'precautions' => self::getAllergyPrecautions($allergies)
        ];
    }
    
    /**
     * Calculer le score de risque global
     */
    private static function calculateOverallRiskScore($riskAssessment) {
        $totalScore = 1.0;
        
        foreach ($riskAssessment as $riskType => $assessment) {
            if (isset($assessment['risk_score'])) {
                $totalScore *= $assessment['risk_score'];
            }
        }
        
        // Normaliser le score entre 1 et 10
        return min(10, max(1, round($totalScore, 1)));
    }
    
    /**
     * Déterminer le niveau de risque
     */
    private static function determineRiskLevel($riskScore) {
        if ($riskScore <= 2) return 'low';
        if ($riskScore <= 4) return 'medium';
        if ($riskScore <= 7) return 'high';
        return 'critical';
    }
    
    /**
     * Générer des recommandations personnalisées
     */
    private static function generateRecommendations($riskAnalysis) {
        $recommendations = [];
        $riskLevel = $riskAnalysis['risk_level'];
        $age = $riskAnalysis['patient_info']['age'];
        
        // Recommandations basées sur le niveau de risque
        switch ($riskLevel) {
            case 'critical':
                $recommendations[] = [
                    'type' => 'urgent',
                    'title' => 'Suivi médical urgent requis',
                    'description' => 'Consultation immédiate avec un spécialiste recommandée',
                    'priority' => 'high',
                    'timeframe' => 'immédiat'
                ];
                break;
                
            case 'high':
                $recommendations[] = [
                    'type' => 'specialist',
                    'title' => 'Consultation spécialisée',
                    'description' => 'Référence vers un spécialiste dans les 2-4 semaines',
                    'priority' => 'high',
                    'timeframe' => '2-4 semaines'
                ];
                break;
                
            case 'medium':
                $recommendations[] = [
                    'type' => 'monitoring',
                    'title' => 'Surveillance renforcée',
                    'description' => 'Contrôles médicaux plus fréquents recommandés',
                    'priority' => 'medium',
                    'timeframe' => '1-3 mois'
                ];
                break;
                
            case 'low':
                $recommendations[] = [
                    'type' => 'prevention',
                    'title' => 'Mesures préventives',
                    'description' => 'Maintenir un mode de vie sain et des contrôles réguliers',
                    'priority' => 'low',
                    'timeframe' => '6-12 mois'
                ];
                break;
        }
        
        // Recommandations spécifiques à l'âge
        if ($age < 5) {
            $recommendations[] = [
                'type' => 'pediatric',
                'title' => 'Suivi pédiatrique',
                'description' => 'Vaccinations et contrôles de croissance réguliers',
                'priority' => 'high',
                'timeframe' => 'mensuel'
            ];
        } elseif ($age > 65) {
            $recommendations[] = [
                'type' => 'geriatric',
                'title' => 'Évaluation gériatrique',
                'description' => 'Bilan complet incluant cognition, mobilité et médicaments',
                'priority' => 'medium',
                'timeframe' => '6 mois'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Générer un planning de surveillance
     */
    private static function generateMonitoringSchedule($riskAnalysis) {
        $riskLevel = $riskAnalysis['risk_level'];
        $age = $riskAnalysis['patient_info']['age'];
        
        $schedule = [];
        
        switch ($riskLevel) {
            case 'critical':
                $schedule = [
                    'consultations' => 'hebdomadaires',
                    'examens_labos' => 'bi-mensuels',
                    'specialist_review' => 'mensuel'
                ];
                break;
                
            case 'high':
                $schedule = [
                    'consultations' => 'mensuelles',
                    'examens_labos' => 'trimestriels',
                    'specialist_review' => 'trimestriel'
                ];
                break;
                
            case 'medium':
                $schedule = [
                    'consultations' => 'trimestrielles',
                    'examens_labos' => 'semestriels',
                    'specialist_review' => 'annuel'
                ];
                break;
                
            case 'low':
                $schedule = [
                    'consultations' => 'annuelles',
                    'examens_labos' => 'annuels',
                    'specialist_review' => 'si nécessaire'
                ];
                break;
        }
        
        return $schedule;
    }
    
    /**
     * Générer des actions préventives
     */
    private static function generatePreventiveActions($riskAnalysis) {
        $actions = [];
        $riskAssessment = $riskAnalysis['risk_assessment'];
        
        // Actions basées sur l'historique médical
        if (isset($riskAssessment['medical_history_risk']['identified_conditions'])) {
            foreach ($riskAssessment['medical_history_risk']['identified_conditions'] as $condition) {
                $actions[] = [
                    'category' => 'medical',
                    'action' => "Surveillance renforcée pour {$condition}",
                    'frequency' => 'régulier',
                    'priority' => 'high'
                ];
            }
        }
        
        // Actions basées sur le mode de vie
        if (isset($riskAssessment['lifestyle_risk']['identified_factors'])) {
            foreach ($riskAssessment['lifestyle_risk']['identified_factors'] as $factor) {
                $actions[] = [
                    'category' => 'lifestyle',
                    'action' => "Modification du mode de vie: {$factor}",
                    'frequency' => 'continu',
                    'priority' => 'medium'
                ];
            }
        }
        
        // Actions préventives générales
        $actions[] = [
            'category' => 'prevention',
            'action' => 'Vaccinations à jour',
            'frequency' => 'selon calendrier',
            'priority' => 'high'
        ];
        
        $actions[] = [
            'category' => 'prevention',
            'action' => 'Activité physique régulière',
            'frequency' => 'quotidien',
            'priority' => 'medium'
        ];
        
        return $actions;
    }
    
    /**
     * Prédire les risques à court terme
     */
    private static function predictShortTermRisks($patient, $age, $consultations) {
        $risks = [];
        
        // Risques basés sur l'âge
        if ($age < 2) {
            $risks[] = ['condition' => 'infections_respiratoires', 'probability' => 0.7, 'timeframe' => '1-3 mois'];
        }
        
        if ($age > 65) {
            $risks[] = ['condition' => 'chutes', 'probability' => 0.6, 'timeframe' => '3-6 mois'];
            $risks[] = ['condition' => 'infections', 'probability' => 0.5, 'timeframe' => '1-6 mois'];
        }
        
        // Risques basés sur l'historique médical
        $antecedents = strtolower($patient['antecedents_medicaux'] ?? '');
        if (strpos($antecedents, 'diabète') !== false) {
            $risks[] = ['condition' => 'hypoglycémie', 'probability' => 0.4, 'timeframe' => '1-3 mois'];
        }
        
        if (strpos($antecedents, 'hypertension') !== false) {
            $risks[] = ['condition' => 'crise_hypertensive', 'probability' => 0.3, 'timeframe' => '3-6 mois'];
        }
        
        return $risks;
    }
    
    /**
     * Prédire les risques à moyen terme
     */
    private static function predictMediumTermRisks($patient, $age, $consultations) {
        $risks = [];
        
        // Risques de progression des maladies chroniques
        $antecedents = strtolower($patient['antecedents_medicaux'] ?? '');
        
        if (strpos($antecedents, 'diabète') !== false) {
            $risks[] = ['condition' => 'complications_diabétiques', 'probability' => 0.6, 'timeframe' => '6-12 mois'];
        }
        
        if (strpos($antecedents, 'hypertension') !== false) {
            $risks[] = ['condition' => 'cardiopathie', 'probability' => 0.4, 'timeframe' => '6-18 mois'];
        }
        
        // Risques liés à l'âge
        if ($age > 50) {
            $risks[] = ['condition' => 'cancer_screening', 'probability' => 0.8, 'timeframe' => '6-12 mois'];
        }
        
        return $risks;
    }
    
    /**
     * Prédire les risques à long terme
     */
    private static function predictLongTermRisks($patient, $age, $consultations) {
        $risks = [];
        
        // Risques de vieillissement
        if ($age > 65) {
            $risks[] = ['condition' => 'démence', 'probability' => 0.3, 'timeframe' => '2-5 ans'];
            $risks[] = ['condition' => 'fragilité', 'probability' => 0.5, 'timeframe' => '2-3 ans'];
        }
        
        // Risques de maladies chroniques
        $antecedents = strtolower($patient['antecedents_medicaux'] ?? '');
        
        if (strpos($antecedents, 'diabète') !== false) {
            $risks[] = ['condition' => 'insuffisance_rénale', 'probability' => 0.4, 'timeframe' => '2-5 ans'];
        }
        
        return $risks;
    }
    
    // Méthodes utilitaires
    private static function getAgeSpecificRecommendations($category, $age) {
        $recommendations = [
            'infant' => ['vaccinations', 'surveillance_croissance', 'alimentation'],
            'child' => ['vaccinations', 'développement', 'sécurité'],
            'adolescent' => ['santé_mentale', 'sexualité', 'substances'],
            'adult' => ['dépistage', 'mode_vie', 'stress'],
            'senior' => ['médicaments', 'mobilité', 'cognition']
        ];
        
        return $recommendations[$category] ?? [];
    }
    
    private static function getMonitoringNeeds($conditions) {
        $monitoring = [];
        foreach ($conditions as $condition) {
            switch ($condition) {
                case 'diabetes':
                    $monitoring[] = 'glycémie_quotidienne';
                    break;
                case 'hypertension':
                    $monitoring[] = 'tension_hebdomadaire';
                    break;
                case 'heart_disease':
                    $monitoring[] = 'rythme_cardiaque';
                    break;
            }
        }
        return $monitoring;
    }
    
    private static function getLifestyleKeywords($factor) {
        $keywords = [
            'smoking' => ['fume', 'tabac', 'cigarette'],
            'alcohol' => ['alcool', 'boit', 'ivrogne'],
            'obesity' => ['obèse', 'surpoids', 'gros'],
            'sedentary' => ['sédentaire', 'inactif', 'pas sport'],
            'poor_diet' => ['mauvaise alimentation', 'junk food'],
            'stress' => ['stress', 'anxiété', 'dépression']
        ];
        
        return $keywords[$factor] ?? [];
    }
    
    private static function getLifestyleRecommendations($factors) {
        $recommendations = [];
        foreach ($factors as $factor) {
            switch ($factor) {
                case 'smoking':
                    $recommendations[] = 'Arrêt du tabac';
                    break;
                case 'alcohol':
                    $recommendations[] = 'Réduction de la consommation d\'alcool';
                    break;
                case 'obesity':
                    $recommendations[] = 'Perte de poids';
                    break;
                case 'sedentary':
                    $recommendations[] = 'Activité physique régulière';
                    break;
            }
        }
        return $recommendations;
    }
    
    private static function getEnvironmentalPreventiveMeasures($factors) {
        $measures = [];
        foreach ($factors as $factor) {
            switch ($factor) {
                case 'malaria_zone':
                    $measures[] = 'Moustiquaire imprégnée';
                    $measures[] = 'Répulsif anti-moustique';
                    break;
                case 'tropical_climate':
                    $measures[] = 'Protection solaire';
                    $measures[] = 'Hydratation suffisante';
                    break;
                case 'urban_area':
                    $measures[] = 'Gestion du stress';
                    break;
                case 'rural_area':
                    $measures[] = 'Accès aux soins';
                    break;
            }
        }
        return $measures;
    }
    
    private static function getAllergyPrecautions($allergies) {
        if (empty($allergies)) return [];
        
        return [
            'Éviter les allergènes identifiés',
            'Porter un bracelet d\'allergie',
            'Avoir un auto-injecteur d\'adrénaline si nécessaire'
        ];
    }
    
    private static function generateInterventionRecommendations($predictions) {
        return [
            'Surveillance renforcée des conditions à risque',
            'Interventions préventives précoces',
            'Éducation du patient et de la famille',
            'Coordination avec les spécialistes'
        ];
    }
    
    private static function generateMonitoringPriorities($predictions) {
        return [
            'Surveillance des signes précoces',
            'Contrôles réguliers selon le risque',
            'Alertes automatiques pour les changements',
            'Communication avec l\'équipe soignante'
        ];
    }
    
    private static function generateFollowUpSchedule($riskLevel, $age) {
        $schedules = [
            'critical' => ['médecin_généraliste' => 'hebdomadaire', 'spécialiste' => 'mensuel'],
            'high' => ['médecin_généraliste' => 'mensuel', 'spécialiste' => 'trimestriel'],
            'medium' => ['médecin_généraliste' => 'trimestriel', 'spécialiste' => 'annuel'],
            'low' => ['médecin_généraliste' => 'annuel', 'spécialiste' => 'si nécessaire']
        ];
        
        return $schedules[$riskLevel] ?? $schedules['low'];
    }
    
    private static function generateSpecialistReferrals($patient) {
        $referrals = [];
        $antecedents = strtolower($patient['risk_assessment']['medical_history_risk']['identified_conditions'] ?? []);
        
        foreach ($antecedents as $condition) {
            switch ($condition) {
                case 'diabetes':
                    $referrals[] = 'Endocrinologue';
                    break;
                case 'hypertension':
                    $referrals[] = 'Cardiologue';
                    break;
                case 'heart_disease':
                    $referrals[] = 'Cardiologue';
                    break;
                case 'cancer':
                    $referrals[] = 'Oncologue';
                    break;
            }
        }
        
        return array_unique($referrals);
    }
    
    private static function generateScreeningRecommendations($age, $patient) {
        $screenings = [];
        
        // Screenings basés sur l'âge
        if ($age >= 50) {
            $screenings[] = 'Dépistage cancer colorectal';
        }
        
        if ($age >= 40) {
            $screenings[] = 'Dépistage diabète';
        }
        
        // Screenings basés sur le sexe
        if ($patient['patient_info']['sexe'] === 'F') {
            if ($age >= 25) {
                $screenings[] = 'Dépistage cancer du col';
            }
            if ($age >= 50) {
                $screenings[] = 'Mammographie';
            }
        }
        
        return $screenings;
    }
    
    private static function generateLifestyleModifications($patient) {
        $modifications = [];
        $lifestyleRisk = $patient['risk_assessment']['lifestyle_risk']['identified_factors'] ?? [];
        
        foreach ($lifestyleRisk as $factor) {
            switch ($factor) {
                case 'smoking':
                    $modifications[] = 'Programme d\'arrêt du tabac';
                    break;
                case 'alcohol':
                    $modifications[] = 'Réduction de la consommation d\'alcool';
                    break;
                case 'obesity':
                    $modifications[] = 'Plan de perte de poids';
                    break;
                case 'sedentary':
                    $modifications[] = 'Programme d\'exercice physique';
                    break;
            }
        }
        
        return $modifications;
    }
    
    private static function generateMedicationReview($patient) {
        return [
            'Révision des médicaments actuels',
            'Vérification des interactions',
            'Optimisation des posologies',
            'Éducation sur l\'observance'
        ];
    }
}
?>



