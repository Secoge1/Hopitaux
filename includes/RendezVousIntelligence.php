<?php
/**
 * Service d'Intelligence Artificielle pour la Planification des Rendez-vous
 * Optimise automatiquement la planification et suggère les meilleurs créneaux
 */
class RendezVousIntelligence {
    
    /**
     * Base de connaissances pour les durées de consultations
     */
    private static $consultationDurations = [
        'consultation_generale' => 30,
        'consultation_specialisee' => 45,
        'consultation_urgence' => 20,
        'consultation_followup' => 15,
        'consultation_preventive' => 40,
        'consultation_chirurgie' => 60,
        'consultation_psychiatrie' => 50,
        'consultation_pediatrie' => 25,
        'consultation_dermatologie' => 20,
        'consultation_cardiologie' => 45
    ];
    
    /**
     * Préférences de créneaux par type de consultation
     */
    private static $preferredSlots = [
        'consultation_generale' => ['09:00-12:00', '14:00-17:00'],
        'consultation_specialisee' => ['09:00-11:00', '15:00-17:00'],
        'consultation_urgence' => ['08:00-20:00'], // Toute la journée
        'consultation_followup' => ['09:00-12:00', '14:00-16:00'],
        'consultation_preventive' => ['09:00-11:00', '14:00-16:00'],
        'consultation_chirurgie' => ['08:00-12:00'], // Matin seulement
        'consultation_psychiatrie' => ['09:00-12:00', '14:00-17:00'],
        'consultation_pediatrie' => ['09:00-12:00', '14:00-16:00'],
        'consultation_dermatologie' => ['09:00-12:00', '14:00-17:00'],
        'consultation_cardiologie' => ['08:00-12:00', '14:00-17:00']
    ];
    
    /**
     * Analyser et optimiser la planification des rendez-vous
     */
    public static function analyzeAndOptimize($medecinId, $dateRange = 7) {
        try {
            require_once __DIR__ . '/../models/RendezVous.php';
            $rendezVousModel = new RendezVous();
            
            // Récupérer les rendez-vous existants
            $existingAppointments = $rendezVousModel->getByMedecinAndDateRange($medecinId, $dateRange);
            
            // Analyser les patterns
            $analysis = self::analyzeAppointmentPatterns($existingAppointments);
            
            // Optimiser la planification
            $optimization = self::optimizeSchedule($medecinId, $analysis);
            
            // Générer des recommandations
            $recommendations = self::generateRecommendations($analysis, $optimization);
            
            return [
                'success' => true,
                'analysis' => $analysis,
                'optimization' => $optimization,
                'recommendations' => $recommendations,
                'date_range' => $dateRange
            ];
            
        } catch (Exception $e) {
            error_log("Erreur RendezVousIntelligence: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'analyse de planification'
            ];
        }
    }
    
    /**
     * Suggérer les meilleurs créneaux pour un nouveau rendez-vous
     */
    public static function suggestOptimalSlots($medecinId, $patientId, $typeConsultation, $preferredDate = null) {
        try {
            require_once __DIR__ . '/../models/RendezVous.php';
            require_once __DIR__ . '/../models/Patient.php';
            
            $rendezVousModel = new RendezVous();
            $patientModel = new Patient();
            
            // Récupérer les informations du patient
            $patient = $patientModel->getById($patientId);
            $patientAge = $patient ? $patientModel->calculateAge($patient['date_naissance']) : null;
            
            // Calculer la durée estimée
            $estimatedDuration = self::getEstimatedDuration($typeConsultation, $patientAge);
            
            // Récupérer les créneaux disponibles
            $availableSlots = self::getAvailableSlots($medecinId, $preferredDate, $estimatedDuration);
            
            // Filtrer selon les préférences
            $preferredSlots = self::filterByPreferences($availableSlots, $typeConsultation);
            
            // Optimiser selon l'historique du médecin
            $optimizedSlots = self::optimizeForMedecin($preferredSlots, $medecinId, $typeConsultation);
            
            // Prioriser les créneaux
            $prioritizedSlots = self::prioritizeSlots($optimizedSlots, $patient, $typeConsultation);
            
            return [
                'success' => true,
                'suggested_slots' => array_slice($prioritizedSlots, 0, 5), // Top 5
                'estimated_duration' => $estimatedDuration,
                'optimization_factors' => [
                    'patient_age' => $patientAge,
                    'consultation_type' => $typeConsultation,
                    'medecin_workload' => self::getMedecinWorkload($medecinId),
                    'patient_preferences' => self::getPatientPreferences($patient)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erreur suggestion créneaux: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la suggestion de créneaux'
            ];
        }
    }
    
    /**
     * Détecter les conflits et problèmes de planification
     */
    public static function detectConflicts($medecinId, $dateRange = 7) {
        try {
            require_once __DIR__ . '/../models/RendezVous.php';
            $rendezVousModel = new RendezVous();
            
            $appointments = $rendezVousModel->getByMedecinAndDateRange($medecinId, $dateRange);
            $conflicts = [];
            
            foreach ($appointments as $appointment) {
                // Détecter les chevauchements
                $overlaps = self::detectOverlaps($appointment, $appointments);
                if (!empty($overlaps)) {
                    $conflicts[] = [
                        'type' => 'overlap',
                        'appointment' => $appointment,
                        'conflicting_with' => $overlaps,
                        'severity' => 'high'
                    ];
                }
                
                // Détecter les créneaux trop courts
                $duration = self::getEstimatedDuration($appointment['type_consultation']);
                if ($duration < 15) {
                    $conflicts[] = [
                        'type' => 'short_duration',
                        'appointment' => $appointment,
                        'estimated_duration' => $duration,
                        'severity' => 'medium'
                    ];
                }
                
                // Détecter les surcharges
                $dailyLoad = self::calculateDailyLoad($appointments, $appointment['date_rendez_vous']);
                if ($dailyLoad > 8 * 60) { // Plus de 8 heures
                    $conflicts[] = [
                        'type' => 'overload',
                        'date' => $appointment['date_rendez_vous'],
                        'total_minutes' => $dailyLoad,
                        'severity' => 'high'
                    ];
                }
            }
            
            return [
                'success' => true,
                'conflicts' => $conflicts,
                'conflict_summary' => [
                    'total_conflicts' => count($conflicts),
                    'high_severity' => count(array_filter($conflicts, fn($c) => $c['severity'] === 'high')),
                    'medium_severity' => count(array_filter($conflicts, fn($c) => $c['severity'] === 'medium')),
                    'low_severity' => count(array_filter($conflicts, fn($c) => $c['severity'] === 'low'))
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erreur détection conflits: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la détection des conflits'
            ];
        }
    }
    
    /**
     * Analyser les patterns de rendez-vous
     */
    private static function analyzeAppointmentPatterns($appointments) {
        $patterns = [
            'peak_hours' => [],
            'most_common_types' => [],
            'average_duration' => 0,
            'patient_distribution' => [],
            'efficiency_metrics' => []
        ];
        
        if (empty($appointments)) {
            return $patterns;
        }
        
        // Analyser les heures de pointe
        $hourCounts = [];
        foreach ($appointments as $apt) {
            $hour = date('H', strtotime($apt['date_rendez_vous']));
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }
        arsort($hourCounts);
        $patterns['peak_hours'] = array_slice(array_keys($hourCounts), 0, 3);
        
        // Types de consultations les plus fréquents
        $typeCounts = [];
        foreach ($appointments as $apt) {
            $type = $apt['type_consultation'] ?? 'consultation_generale';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
        arsort($typeCounts);
        $patterns['most_common_types'] = array_slice(array_keys($typeCounts), 0, 5);
        
        // Durée moyenne
        $totalDuration = 0;
        foreach ($appointments as $apt) {
            $totalDuration += self::getEstimatedDuration($apt['type_consultation'] ?? 'consultation_generale');
        }
        $patterns['average_duration'] = count($appointments) > 0 ? round($totalDuration / count($appointments)) : 0;
        
        return $patterns;
    }
    
    /**
     * Optimiser l'emploi du temps
     */
    private static function optimizeSchedule($medecinId, $analysis) {
        $optimization = [
            'suggested_improvements' => [],
            'optimal_schedule' => [],
            'efficiency_gains' => []
        ];
        
        // Suggestions d'amélioration
        if (count($analysis['peak_hours']) > 0) {
            $optimization['suggested_improvements'][] = [
                'type' => 'schedule_distribution',
                'message' => 'Répartir les consultations sur plus de créneaux pour éviter les heures de pointe',
                'impact' => 'Réduction de l\'attente des patients'
            ];
        }
        
        if ($analysis['average_duration'] > 45) {
            $optimization['suggested_improvements'][] = [
                'type' => 'duration_optimization',
                'message' => 'Considérer des créneaux plus courts pour certaines consultations',
                'impact' => 'Augmentation du nombre de patients vus'
            ];
        }
        
        // Calculer les gains d'efficacité potentiels
        $optimization['efficiency_gains'] = [
            'potential_time_saved' => $analysis['average_duration'] * 0.1, // 10% d'optimisation
            'additional_patients_per_day' => round(480 / ($analysis['average_duration'] * 0.9)) - round(480 / $analysis['average_duration']),
            'revenue_potential' => 'Augmentation de 15-20% possible'
        ];
        
        return $optimization;
    }
    
    /**
     * Générer des recommandations personnalisées
     */
    private static function generateRecommendations($analysis, $optimization) {
        $recommendations = [];
        
        // Recommandations basées sur l'analyse
        if (!empty($analysis['peak_hours'])) {
            $recommendations[] = [
                'category' => 'planification',
                'title' => 'Optimiser les heures de pointe',
                'description' => 'Les heures ' . implode(', ', $analysis['peak_hours']) . ' sont les plus chargées',
                'action' => 'Répartir les consultations urgentes sur d\'autres créneaux',
                'priority' => 'high'
            ];
        }
        
        // Recommandations d'efficacité
        if (!empty($optimization['efficiency_gains'])) {
            $recommendations[] = [
                'category' => 'efficacité',
                'title' => 'Optimisation des durées',
                'description' => 'Gain potentiel de ' . $optimization['efficiency_gains']['potential_time_saved'] . ' minutes par consultation',
                'action' => 'Utiliser des créneaux adaptés selon le type de consultation',
                'priority' => 'medium'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Obtenir la durée estimée d'une consultation
     */
    private static function getEstimatedDuration($typeConsultation, $patientAge = null) {
        $baseDuration = self::$consultationDurations[$typeConsultation] ?? 30;
        
        // Ajuster selon l'âge du patient
        if ($patientAge !== null) {
            if ($patientAge < 5) {
                $baseDuration += 10; // Plus de temps pour les enfants
            } elseif ($patientAge > 65) {
                $baseDuration += 5; // Un peu plus de temps pour les seniors
            }
        }
        
        return $baseDuration;
    }
    
    /**
     * Obtenir les créneaux disponibles
     */
    private static function getAvailableSlots($medecinId, $preferredDate, $duration) {
        // Simulation des créneaux disponibles
        // En réalité, cela devrait interroger la base de données
        $availableSlots = [];
        $startDate = $preferredDate ? new DateTime($preferredDate) : new DateTime();
        
        for ($i = 0; $i < 7; $i++) {
            $currentDate = clone $startDate;
            $currentDate->add(new DateInterval('P' . $i . 'D'));
            
            // Créneaux de travail : 8h-12h et 14h-18h
            $morningSlots = self::generateTimeSlots($currentDate, '08:00', '12:00', $duration);
            $afternoonSlots = self::generateTimeSlots($currentDate, '14:00', '18:00', $duration);
            
            $availableSlots = array_merge($availableSlots, $morningSlots, $afternoonSlots);
        }
        
        return $availableSlots;
    }
    
    /**
     * Générer des créneaux horaires
     */
    private static function generateTimeSlots($date, $startTime, $endTime, $duration) {
        $slots = [];
        $start = new DateTime($date->format('Y-m-d') . ' ' . $startTime);
        $end = new DateTime($date->format('Y-m-d') . ' ' . $endTime);
        
        while ($start->add(new DateInterval('PT' . $duration . 'M')) <= $end) {
            $slotEnd = clone $start;
            $slotEnd->add(new DateInterval('PT' . $duration . 'M'));
            
            $slots[] = [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $slotEnd->format('Y-m-d H:i:s'),
                'duration' => $duration,
                'date' => $start->format('Y-m-d'),
                'time' => $start->format('H:i')
            ];
        }
        
        return $slots;
    }
    
    /**
     * Filtrer les créneaux selon les préférences
     */
    private static function filterByPreferences($slots, $typeConsultation) {
        $preferredTimes = self::$preferredSlots[$typeConsultation] ?? ['09:00-17:00'];
        
        $filteredSlots = [];
        foreach ($slots as $slot) {
            $slotTime = $slot['time'];
            foreach ($preferredTimes as $preferredRange) {
                if (self::timeInRange($slotTime, $preferredRange)) {
                    $filteredSlots[] = $slot;
                    break;
                }
            }
        }
        
        return $filteredSlots;
    }
    
    /**
     * Vérifier si une heure est dans une plage
     */
    private static function timeInRange($time, $range) {
        list($start, $end) = explode('-', $range);
        return $time >= $start && $time <= $end;
    }
    
    /**
     * Optimiser pour un médecin spécifique
     */
    private static function optimizeForMedecin($slots, $medecinId, $typeConsultation) {
        // Ajouter un score d'optimisation basé sur l'historique du médecin
        foreach ($slots as &$slot) {
            $slot['optimization_score'] = rand(70, 100); // Simulation
        }
        
        // Trier par score d'optimisation
        usort($slots, function($a, $b) {
            return $b['optimization_score'] - $a['optimization_score'];
        });
        
        return $slots;
    }
    
    /**
     * Prioriser les créneaux
     */
    private static function prioritizeSlots($slots, $patient, $typeConsultation) {
        foreach ($slots as &$slot) {
            $priority = 0;
            
            // Priorité selon le type de consultation
            if ($typeConsultation === 'consultation_urgence') {
                $priority += 50;
            }
            
            // Priorité selon l'âge du patient
            if ($patient && $patient['date_naissance']) {
                $age = (new DateTime())->diff(new DateTime($patient['date_naissance']))->y;
                if ($age < 5 || $age > 65) {
                    $priority += 20;
                }
            }
            
            // Priorité selon l'heure (éviter les heures de pointe)
            $hour = (int)date('H', strtotime($slot['start']));
            if ($hour >= 9 && $hour <= 11) {
                $priority += 10;
            } elseif ($hour >= 14 && $hour <= 16) {
                $priority += 10;
            }
            
            $slot['priority_score'] = $priority;
        }
        
        // Trier par score de priorité
        usort($slots, function($a, $b) {
            return $b['priority_score'] - $a['priority_score'];
        });
        
        return $slots;
    }
    
    /**
     * Obtenir la charge de travail d'un médecin
     */
    private static function getMedecinWorkload($medecinId) {
        // Simulation de la charge de travail
        return [
            'daily_average' => rand(8, 15),
            'weekly_total' => rand(40, 70),
            'efficiency_score' => rand(75, 95)
        ];
    }
    
    /**
     * Obtenir les préférences d'un patient
     */
    private static function getPatientPreferences($patient) {
        if (!$patient) return [];
        
        return [
            'preferred_hours' => ['09:00-12:00', '14:00-17:00'],
            'avoid_early_morning' => false,
            'prefer_afternoon' => false
        ];
    }
    
    /**
     * Détecter les chevauchements
     */
    private static function detectOverlaps($appointment, $allAppointments) {
        $overlaps = [];
        $appStart = new DateTime($appointment['date_rendez_vous']);
        $appEnd = clone $appStart;
        $appEnd->add(new DateInterval('PT' . self::getEstimatedDuration($appointment['type_consultation']) . 'M'));
        
        foreach ($allAppointments as $other) {
            if ($other['id'] === $appointment['id']) continue;
            
            $otherStart = new DateTime($other['date_rendez_vous']);
            $otherEnd = clone $otherStart;
            $otherEnd->add(new DateInterval('PT' . self::getEstimatedDuration($other['type_consultation']) . 'M'));
            
            if ($appStart < $otherEnd && $appEnd > $otherStart) {
                $overlaps[] = $other;
            }
        }
        
        return $overlaps;
    }
    
    /**
     * Calculer la charge quotidienne
     */
    private static function calculateDailyLoad($appointments, $date) {
        $totalMinutes = 0;
        foreach ($appointments as $apt) {
            if (date('Y-m-d', strtotime($apt['date_rendez_vous'])) === date('Y-m-d', strtotime($date))) {
                $totalMinutes += self::getEstimatedDuration($apt['type_consultation']);
            }
        }
        return $totalMinutes;
    }
}
?>



