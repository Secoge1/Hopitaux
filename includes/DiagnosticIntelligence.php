<?php
/**
 * Service d'Intelligence Médicale pour les Diagnostics
 * Fournit des suggestions automatiques de diagnostic, traitement et ordonnance basées sur les symptômes
 */
class DiagnosticIntelligence {
    
    /**
     * Base de connaissances médicales pour les diagnostics
     */
    private static $medicalKnowledge = [
        // Symptômes respiratoires
        'toux' => [
            'diagnostics' => [
                'Infection virale des voies respiratoires supérieures',
                'Bronchite aiguë',
                'Asthme',
                'Pneumonie',
                'Reflux gastro-œsophagien'
            ],
            'traitements' => [
                'Repos et hydratation',
                'Antitussifs si toux sèche',
                'Expectorants si toux grasse',
                'Antibiotiques si infection bactérienne suspectée'
            ],
            'medicaments' => [
                'Paracétamol 1g x3/jour pendant 5 jours',
                'Dextrométhorphane si toux sèche',
                'Amoxicilline 1g x2/jour si infection bactérienne',
                'Inhalateur de salbutamol si asthme'
            ],
            'examens' => ['Radiographie thoracique', 'Examens sanguins', 'Test de spirométrie']
        ],
        
        'fievre' => [
            'diagnostics' => [
                'Infection virale',
                'Infection bactérienne',
                'Grippe',
                'COVID-19',
                'Infection urinaire'
            ],
            'traitements' => [
                'Antipyrétiques',
                'Hydratation abondante',
                'Repos',
                'Antibiotiques si infection bactérienne'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Ibuprofène 400mg x3/jour',
                'Amoxicilline 1g x2/jour si infection bactérienne'
            ],
            'examens' => ['Examen sanguin', 'ECBU', 'Test COVID-19', 'Hémogramme']
        ],
        
        'maux_de_tete' => [
            'diagnostics' => [
                'Céphalée de tension',
                'Migraine',
                'Sinusite',
                'Hypertension artérielle',
                'Céphalée secondaire'
            ],
            'traitements' => [
                'Antalgiques',
                'Repos dans le calme',
                'Hydratation',
                'Traitement de la cause sous-jacente'
            ],
            'medicaments' => [
                'Paracétamol 1g x3/jour',
                'Ibuprofène 400mg x3/jour',
                'Sumatriptan si migraine'
            ],
            'examens' => ['Tension artérielle', 'Examen neurologique', 'Scanner si nécessaire']
        ],
        
        'douleurs_abdominales' => [
            'diagnostics' => [
                'Gastrite',
                'Syndrome du côlon irritable',
                'Appendicite',
                'Calculs biliaires',
                'Infection urinaire'
            ],
            'traitements' => [
                'Antispasmodiques',
                'Anti-acides',
                'Régime alimentaire adapté',
                'Antibiotiques si infection'
            ],
            'medicaments' => [
                'Spasfon 2 comprimés x3/jour',
                'Gaviscon après les repas',
                'Oméprazole 20mg le matin'
            ],
            'examens' => ['Échographie abdominale', 'ECBU', 'Examens sanguins']
        ],
        
        'fatigue' => [
            'diagnostics' => [
                'Anémie ferriprive',
                'Hypothyroïdie',
                'Syndrome de fatigue chronique',
                'Dépression',
                'Infection chronique'
            ],
            'traitements' => [
                'Supplémentation en fer',
                'Traitement hormonal si hypothyroïdie',
                'Thérapie cognitive-comportementale',
                'Antidépresseurs si dépression'
            ],
            'medicaments' => [
                'Sulfate ferreux 200mg/jour',
                'Lévothyroxine si hypothyroïdie',
                'Sertraline 50mg/jour si dépression'
            ],
            'examens' => ['Hémogramme', 'TSH', 'Ferritine', 'Vitamine B12']
        ],
        
        'nausees_vomissements' => [
            'diagnostics' => [
                'Gastro-entérite',
                'Migraine',
                'Grossesse',
                'Médicaments',
                'Anxiété'
            ],
            'traitements' => [
                'Antiémétiques',
                'Réhydratation',
                'Repos',
                'Éviter les déclencheurs'
            ],
            'medicaments' => [
                'Dompéridone 10mg x3/jour',
                'Métoclopramide 10mg x3/jour',
                'Solutions de réhydratation'
            ],
            'examens' => ['Test de grossesse', 'Examens sanguins', 'Échographie abdominale']
        ],
        
        'douleurs_articulaires' => [
            'diagnostics' => [
                'Arthrose',
                'Polyarthrite rhumatoïde',
                'Goutte',
                'Lupus',
                'Fibromyalgie'
            ],
            'traitements' => [
                'Anti-inflammatoires',
                'Physiothérapie',
                'Corticostéroïdes',
                'Traitements de fond'
            ],
            'medicaments' => [
                'Ibuprofène 400mg x3/jour',
                'Prednisolone 20mg/jour',
                'Méthotrexate si polyarthrite'
            ],
            'examens' => ['Radiographies', 'Examens sanguins', 'VS, CRP', 'Facteur rhumatoïde']
        ],
        
        'troubles_sommeil' => [
            'diagnostics' => [
                'Insomnie',
                'Apnée du sommeil',
                'Syndrome des jambes sans repos',
                'Anxiété',
                'Dépression'
            ],
            'traitements' => [
                'Hygiène du sommeil',
                'CPAP si apnée',
                'Anxiolytiques',
                'Antidépresseurs'
            ],
            'medicaments' => [
                'Zolpidem 10mg au coucher',
                'Lorazépam 1mg si anxiété',
                'Mélatonine 3mg'
            ],
            'examens' => ['Polysomnographie', 'Tests psychologiques']
        ],
        
        'douleurs_thoraciques' => [
            'diagnostics' => [
                'Angine de poitrine',
                'Infarctus du myocarde',
                'Pneumonie',
                'Anxiété',
                'Reflux gastro-œsophagien'
            ],
            'traitements' => [
                'Urgence médicale',
                'Oxygénothérapie',
                'Anticoagulants',
                'Intervention coronarienne'
            ],
            'medicaments' => [
                'Aspirine 75mg/jour',
                'Clopidogrel 75mg/jour',
                'Atorvastatine 20mg/jour'
            ],
            'examens' => ['ECG', 'Troponine', 'Radiographie thoracique', 'Échocardiographie']
        ],
        
        'vertiges' => [
            'diagnostics' => [
                'Vertige positionnel paroxystique bénin',
                'Maladie de Ménière',
                'Névrite vestibulaire',
                'Migraine vestibulaire',
                'Hypotension orthostatique'
            ],
            'traitements' => [
                'Manoeuvres de repositionnement',
                'Antivertigineux',
                'Rééducation vestibulaire',
                'Traitement de la cause'
            ],
            'medicaments' => [
                'Bétahistine 16mg x3/jour',
                'Dimenhydrinate 50mg x3/jour'
            ],
            'examens' => ['Examen neurologique', 'Audiométrie', 'IRM cérébrale']
        ],
        
        // SYMPTÔMES DU DOS ET COLONNE VERTÉBRALE
        'douleurs_dos' => [
            'diagnostics' => [
                'Lombalgie aiguë',
                'Hernie discale',
                'Sciatique',
                'Scoliose',
                'Arthrose vertébrale',
                'Contracture musculaire',
                'Spondylarthrite ankylosante'
            ],
            'traitements' => [
                'Repos relatif',
                'Anti-inflammatoires',
                'Physiothérapie',
                'Kinésithérapie',
                'Infiltration si nécessaire'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Ibuprofène 400mg x3/jour',
                'Diclofénac gel en application locale',
                'Relaxants musculaires (Tétrazépam)'
            ],
            'examens' => ['Radiographie du rachis', 'IRM lombaire', 'Scanner si nécessaire']
        ],
        
        'lombalgie' => [
            'diagnostics' => [
                'Lombalgie commune',
                'Hernie discale L4-L5 ou L5-S1',
                'Sciatique',
                'Contracture musculaire',
                'Arthrose lombaire'
            ],
            'traitements' => [
                'Repos relatif 48h',
                'Anti-inflammatoires',
                'Kinésithérapie',
                'École du dos'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Ibuprofène 400mg x3/jour',
                'Diclofénac gel local'
            ],
            'examens' => ['Radiographie lombaire', 'IRM si signes neurologiques']
        ],
        
        'cervicalgie' => [
            'diagnostics' => [
                'Torticolis',
                'Cervicalgie commune',
                'Hernie discale cervicale',
                'Arthrose cervicale',
                'Contracture des muscles du cou'
            ],
            'traitements' => [
                'Repos du cou',
                'Anti-inflammatoires',
                'Collier cervical si nécessaire',
                'Physiothérapie'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Ibuprofène 400mg x3/jour',
                'Diclofénac gel en application'
            ],
            'examens' => ['Radiographie cervicale', 'IRM cervicale si signes neurologiques']
        ],
        
        // SYMPTÔMES OCULAIRES
        'douleurs_yeux' => [
            'diagnostics' => [
                'Conjonctivite',
                'Uvéite',
                'Kératite',
                'Glaucome aigu',
                'Migraine ophtalmique',
                'Sécheresse oculaire',
                'Orgelet'
            ],
            'traitements' => [
                'Larmes artificielles',
                'Anti-inflammatoires locaux',
                'Antibiotiques si infection',
                'Consultation ophtalmologique urgente'
            ],
            'medicaments' => [
                'Larmes artificielles',
                'Collyre anti-inflammatoire',
                'Collyre antibiotique si infection',
                'Paracétamol si douleur'
            ],
            'examens' => ['Examen ophtalmologique', 'Mesure de la pression intra-oculaire', 'Examen à la lampe à fente']
        ],
        
        'vision_floue' => [
            'diagnostics' => [
                'Presbytie',
                'Myopie',
                'Astigmatisme',
                'Cataracte',
                'DMLA (Dégénérescence Maculaire Liée à l\'Âge)',
                'Rétinopathie diabétique',
                'Glaucome chronique'
            ],
            'traitements' => [
                'Correction optique',
                'Chirurgie si indiquée',
                'Traitement médical selon la cause',
                'Surveillance régulière'
            ],
            'medicaments' => [
                'Larmes artificielles si sécheresse',
                'Collyres si glaucome',
                'Compléments alimentaires pour DMLA'
            ],
            'examens' => ['Examen ophtalmologique complet', 'Champ visuel', 'OCT rétinien']
        ],
        
        'yeux_rouges' => [
            'diagnostics' => [
                'Conjonctivite virale',
                'Conjonctivite bactérienne',
                'Conjonctivite allergique',
                'Uvéite',
                'Kératite',
                'Glaucome aigu',
                'Sécheresse oculaire'
            ],
            'traitements' => [
                'Larmes artificielles',
                'Collyres anti-inflammatoires',
                'Antibiotiques si infection bactérienne',
                'Consultation ophtalmologique'
            ],
            'medicaments' => [
                'Larmes artificielles',
                'Collyre décongestionnant',
                'Collyre antibiotique si infection',
                'Antihistaminiques si allergique'
            ],
            'examens' => ['Examen ophtalmologique', 'Prélèvement si infection suspectée']
        ],
        
        // SYMPTÔMES GASTRO-INTESTINAUX AVANCÉS
        'ulcere' => [
            'diagnostics' => [
                'Ulcère gastrique',
                'Ulcère duodénal',
                'Gastrite érosive',
                'Reflux gastro-œsophagien sévère',
                'Cancer gastrique'
            ],
            'traitements' => [
                'Inhibiteurs de la pompe à protons',
                'Anti-H2',
                'Antibiotiques si Helicobacter pylori',
                'Arrêt des AINS',
                'Régime alimentaire adapté'
            ],
            'medicaments' => [
                'Oméprazole 40mg/jour',
                'Ranitidine 300mg/jour',
                'Amoxicilline + Clarithromycine si H. pylori',
                'Sucralfate 1g x4/jour'
            ],
            'examens' => ['Fibroscopie gastrique', 'Test Helicobacter pylori', 'Biopsie si nécessaire']
        ],
        
        'brulures_estomac' => [
            'diagnostics' => [
                'Reflux gastro-œsophagien',
                'Gastrite',
                'Ulcère gastrique',
                'Œsophagite',
                'Hernie hiatale'
            ],
            'traitements' => [
                'Inhibiteurs de la pompe à protons',
                'Anti-acides',
                'Régime alimentaire',
                'Élévation de la tête du lit'
            ],
            'medicaments' => [
                'Oméprazole 20mg/jour',
                'Gaviscon après les repas',
                'Ranitidine 150mg x2/jour'
            ],
            'examens' => ['Fibroscopie œsogastroduodénale', 'pH-métrie si nécessaire']
        ],
        
        'diarrhee' => [
            'diagnostics' => [
                'Gastro-entérite virale',
                'Gastro-entérite bactérienne',
                'Intoxication alimentaire',
                'Syndrome du côlon irritable',
                'Maladie de Crohn',
                'Rectocolite hémorragique',
                'Intolérance alimentaire'
            ],
            'traitements' => [
                'Réhydratation orale',
                'Régime sans résidus',
                'Antidiarrhéiques',
                'Probiotiques',
                'Antibiotiques si bactérien'
            ],
            'medicaments' => [
                'Sels de réhydratation orale',
                'Lopéramide 2mg si nécessaire',
                'Smecta 3 sachets/jour',
                'Probiotiques'
            ],
            'examens' => ['Coproculture', 'Parasitologie des selles', 'Coloscopie si chronique']
        ],
        
        'constipation' => [
            'diagnostics' => [
                'Constipation fonctionnelle',
                'Hypothyroïdie',
                'Médicaments constipants',
                'Diverticulose',
                'Cancer colorectal',
                'Syndrome du côlon irritable'
            ],
            'traitements' => [
                'Régime riche en fibres',
                'Hydratation abondante',
                'Exercice physique',
                'Laxatifs si nécessaire'
            ],
            'medicaments' => [
                'Lactulose 10-20ml/jour',
                'Macrogol 1-3 sachets/jour',
                'Bisacodyl si occasionnel',
                'Suppositoires glycérine'
            ],
            'examens' => ['Coloscopie si > 50 ans', 'Bilan thyroïdien', 'Transit du côlon']
        ],
        
        // MALADIES CHRONIQUES - DIABÈTE
        'diabete' => [
            'diagnostics' => [
                'Diabète de type 1',
                'Diabète de type 2',
                'Diabète gestationnel',
                'Pré-diabète',
                'Résistance à l\'insuline',
                'Hypoglycémie',
                'Hyperglycémie'
            ],
            'traitements' => [
                'Insulinothérapie (type 1)',
                'Antidiabétiques oraux (type 2)',
                'Régime diabétique',
                'Exercice physique régulier',
                'Surveillance glycémique',
                'Éducation thérapeutique'
            ],
            'medicaments' => [
                'Insuline (rapide, lente)',
                'Métformine 500-1000mg x2/jour',
                'Glibenclamide 5mg/jour',
                'Gliclazide 80mg/jour',
                'Sitagliptine 100mg/jour'
            ],
            'examens' => ['Glycémie à jeun', 'HbA1c', 'Test de tolérance au glucose', 'Glycémie post-prandiale']
        ],
        
        'polyurie' => [
            'diagnostics' => [
                'Diabète non contrôlé',
                'Diabète insipide',
                'Infection urinaire',
                'Insuffisance rénale',
                'Polydipsie psychogène',
                'Hypercalcémie'
            ],
            'traitements' => [
                'Contrôle glycémique si diabète',
                'Hydratation adaptée',
                'Traitement de la cause',
                'Surveillance rénale'
            ],
            'medicaments' => [
                'Insuline si diabète',
                'Desmopressine si diabète insipide',
                'Antibiotiques si infection urinaire'
            ],
            'examens' => ['Glycémie', 'Ionogramme sanguin', 'ECBU', 'Créatinine', 'Osmolarité urinaire']
        ],
        
        'polydipsie' => [
            'diagnostics' => [
                'Diabète non contrôlé',
                'Diabète insipide',
                'Hypercalcémie',
                'Polydipsie psychogène',
                'Insuffisance surrénale'
            ],
            'traitements' => [
                'Contrôle glycémique',
                'Hydratation contrôlée',
                'Traitement de la cause',
                'Surveillance hydro-électrolytique'
            ],
            'medicaments' => [
                'Insuline si diabète',
                'Desmopressine si diabète insipide',
                'Hydrocortisone si insuffisance surrénale'
            ],
            'examens' => ['Glycémie', 'Ionogramme', 'Cortisol', 'ADH', 'Osmolarité']
        ],
        
        // MALADIES CARDIOVASCULAIRES
        'hypertension' => [
            'diagnostics' => [
                'Hypertension artérielle essentielle',
                'Hypertension secondaire',
                'Hypertension rénovasculaire',
                'Phéochromocytome',
                'Syndrome de Cushing',
                'Hyperaldostéronisme primaire'
            ],
            'traitements' => [
                'Mesures hygiéno-diététiques',
                'Antihypertenseurs',
                'Traitement de la cause si secondaire',
                'Surveillance tensionnelle'
            ],
            'medicaments' => [
                'Amlodipine 5-10mg/jour',
                'Lisinopril 10-20mg/jour',
                'Hydrochlorothiazide 12.5-25mg/jour',
                'Aténolol 50-100mg/jour'
            ],
            'examens' => ['Mesure tensionnelle', 'ECG', 'Échographie cardiaque', 'Bilan rénal', 'Dosage rénine-aldostérone']
        ],
        
        'angine_poitrine' => [
            'diagnostics' => [
                'Angine de poitrine stable',
                'Angine de poitrine instable',
                'Infarctus du myocarde',
                'Spasme coronaire',
                'Cardiomyopathie',
                'Péricardite'
            ],
            'traitements' => [
                'Urgence médicale',
                'Nitroglycérine',
                'Anticoagulants',
                'Intervention coronarienne',
                'Réadaptation cardiaque'
            ],
            'medicaments' => [
                'Nitroglycérine sublinguale',
                'Aspirine 75mg/jour',
                'Clopidogrel 75mg/jour',
                'Atorvastatine 20-40mg/jour',
                'Bisoprolol 2.5-10mg/jour'
            ],
            'examens' => ['ECG', 'Troponine', 'Échographie cardiaque', 'Coronarographie']
        ],
        
        'insuffisance_cardiaque' => [
            'diagnostics' => [
                'Insuffisance cardiaque systolique',
                'Insuffisance cardiaque diastolique',
                'Cardiomyopathie dilatée',
                'Cardiomyopathie hypertrophique',
                'Valvulopathie',
                'Cardiopathie ischémique'
            ],
            'traitements' => [
                'Diurétiques',
                'Inhibiteurs de l\'enzyme de conversion',
                'Bêta-bloquants',
                'Antagonistes des récepteurs de l\'aldostérone',
                'Régime hyposodé'
            ],
            'medicaments' => [
                'Furosémide 20-80mg/jour',
                'Énalapril 5-20mg/jour',
                'Carvedilol 3.125-25mg x2/jour',
                'Spironolactone 25mg/jour'
            ],
            'examens' => ['Échographie cardiaque', 'BNP', 'ECG', 'Radiographie thoracique', 'Cathétérisme cardiaque']
        ],
        
        // MALADIES HÉPATIQUES
        'hepatite' => [
            'diagnostics' => [
                'Hépatite virale A',
                'Hépatite virale B',
                'Hépatite virale C',
                'Hépatite alcoolique',
                'Hépatite auto-immune',
                'Hépatite médicamenteuse',
                'Stéatose hépatique'
            ],
            'traitements' => [
                'Repos et hydratation',
                'Arrêt de l\'alcool',
                'Antiviraux si hépatite B/C',
                'Corticoïdes si auto-immune',
                'Arrêt du médicament responsable'
            ],
            'medicaments' => [
                'Ténofovir si hépatite B',
                'Sofosbuvir + Ledipasvir si hépatite C',
                'Prednisolone si auto-immune',
                'Ursodéoxycholique acide'
            ],
            'examens' => ['Transaminases', 'Bilirubine', 'Sérologies virales', 'Échographie hépatique', 'Biopsie hépatique']
        ],
        
        'cirrhose' => [
            'diagnostics' => [
                'Cirrhose alcoolique',
                'Cirrhose post-hépatite',
                'Cirrhose biliaire primitive',
                'Cirrhose auto-immune',
                'Hémochromatose',
                'Maladie de Wilson'
            ],
            'traitements' => [
                'Arrêt de l\'alcool',
                'Traitement de la cause',
                'Diurétiques si ascite',
                'Bêta-bloquants si varices',
                'Transplantation hépatique'
            ],
            'medicaments' => [
                'Spironolactone 100-200mg/jour',
                'Furosémide 20-40mg/jour',
                'Propranolol 40-160mg/jour',
                'Lactulose 15-30ml x2/jour'
            ],
            'examens' => ['Transaminases', 'Albumine', 'TP', 'Échographie hépatique', 'Fibroscan', 'Endoscopie digestive']
        ],
        
        'ictere' => [
            'diagnostics' => [
                'Ictère obstructif',
                'Hépatite virale',
                'Cirrhose hépatique',
                'Calculs biliaires',
                'Cancer du pancréas',
                'Hémolyse',
                'Syndrome de Gilbert'
            ],
            'traitements' => [
                'Traitement de la cause',
                'Désobstruction si obstructive',
                'Transfusion si hémolyse',
                'Photothérapie si nouveau-né'
            ],
            'medicaments' => [
                'Ursodéoxycholique acide si calculs',
                'Antiviraux si hépatite',
                'Chimiothérapie si cancer'
            ],
            'examens' => ['Bilirubine totale/conjuguée', 'Transaminases', 'Échographie abdominale', 'IRM/MRCP']
        ],
        
        // MALADIES RÉNALES
        'insuffisance_renale' => [
            'diagnostics' => [
                'Insuffisance rénale aiguë',
                'Insuffisance rénale chronique',
                'Glomérulonéphrite',
                'Néphropathie diabétique',
                'Néphropathie hypertensive',
                'Polykystose rénale'
            ],
            'traitements' => [
                'Traitement de la cause',
                'Dialyse si nécessaire',
                'Transplantation rénale',
                'Régime hyposodé et hypoprotidique',
                'Contrôle tensionnel'
            ],
            'medicaments' => [
                'Énalapril 5-20mg/jour',
                'Furosémide 20-80mg/jour',
                'Érythropoïétine si anémie',
                'Calcitriol si carence vitamine D'
            ],
            'examens' => ['Créatinine', 'Urée', 'Clairance créatinine', 'Protéinurie', 'Échographie rénale', 'Biopsie rénale']
        ],
        
        'colique_nephretique' => [
            'diagnostics' => [
                'Calcul urétéral',
                'Calcul rénal',
                'Calcul vésical',
                'Sténose urétérale',
                'Tumeur obstructive',
                'Caillot sanguin'
            ],
            'traitements' => [
                'Antalgiques puissants',
                'Antispasmodiques',
                'Lithotritie extracorporelle',
                'Urétéroscopie',
                'Chirurgie si nécessaire'
            ],
            'medicaments' => [
                'Morphine si douleur intense',
                'Spasfon 2 comprimés x3/jour',
                'Paracétamol 1g x4/jour',
                'Tamsulosine 0.4mg/jour'
            ],
            'examens' => ['Échographie rénale', 'Uroscanner', 'Urographie intraveineuse', 'ECBU']
        ],
        
        // MALADIES TROPICALES ET ENDÉMIQUES AU MALI
        'paludisme' => [
            'diagnostics' => [
                'Paludisme à Plasmodium falciparum',
                'Paludisme à Plasmodium vivax',
                'Paludisme à Plasmodium malariae',
                'Paludisme à Plasmodium ovale',
                'Paludisme grave',
                'Paludisme cérébral'
            ],
            'traitements' => [
                'Traitement antipaludique',
                'Artémisinine combinée',
                'Quinine si grave',
                'Prévention des complications',
                'Surveillance clinique'
            ],
            'medicaments' => [
                'Artéméther + Luméfantrine',
                'Artésunate + Amodiaquine',
                'Quinine 600mg x3/jour si grave',
                'Primaquine si vivax'
            ],
            'examens' => ['Frottis sanguin', 'Test rapide paludisme', 'Goutte épaisse', 'Hémogramme']
        ],
        
        'dengue' => [
            'diagnostics' => [
                'Dengue classique',
                'Dengue hémorragique',
                'Syndrome de choc de la dengue',
                'Fièvre dengue',
                'Dengue sévère'
            ],
            'traitements' => [
                'Hydratation abondante',
                'Antalgiques (éviter aspirine)',
                'Surveillance clinique',
                'Traitement symptomatique'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Sels de réhydratation orale',
                'Éviter aspirine et AINS'
            ],
            'examens' => ['Test rapide dengue', 'Antigène NS1', 'Sérologie dengue', 'Hémogramme']
        ],
        
        'chikungunya' => [
            'diagnostics' => [
                'Chikungunya aigu',
                'Chikungunya chronique',
                'Arthrite post-chikungunya',
                'Fièvre chikungunya'
            ],
            'traitements' => [
                'Traitement symptomatique',
                'Antalgiques',
                'Anti-inflammatoires',
                'Repos et hydratation'
            ],
            'medicaments' => [
                'Paracétamol 1g x4/jour',
                'Ibuprofène 400mg x3/jour',
                'Colchicine si arthrite persistante'
            ],
            'examens' => ['Test rapide chikungunya', 'Sérologie chikungunya', 'Hémogramme']
        ],
        
        'meningite' => [
            'diagnostics' => [
                'Méningite bactérienne',
                'Méningite virale',
                'Méningite tuberculeuse',
                'Méningite cryptococcique',
                'Méningite à méningocoque',
                'Méningite à pneumocoque'
            ],
            'traitements' => [
                'Antibiotiques IV si bactérienne',
                'Antiviraux si virale',
                'Antituberculeux si tuberculeuse',
                'Traitement de l\'hypertension intracrânienne'
            ],
            'medicaments' => [
                'Ceftriaxone 2g x2/jour',
                'Vancomycine si résistance',
                'Aciclovir si virale',
                'Rifampicine + Isoniazide si tuberculeuse'
            ],
            'examens' => ['Ponction lombaire', 'Examen du LCR', 'Hémocultures', 'Scanner cérébral']
        ],
        
        'tuberculose' => [
            'diagnostics' => [
                'Tuberculose pulmonaire',
                'Tuberculose extrapulmonaire',
                'Tuberculose ganglionnaire',
                'Tuberculose osseuse',
                'Tuberculose méningée',
                'Tuberculose multirésistante'
            ],
            'traitements' => [
                'Traitement antituberculeux',
                'Rifampicine + Isoniazide + Éthambutol + Pyrazinamide',
                'Traitement de 6 mois minimum',
                'Surveillance de la compliance'
            ],
            'medicaments' => [
                'Rifampicine 600mg/jour',
                'Isoniazide 300mg/jour',
                'Éthambutol 1200mg/jour',
                'Pyrazinamide 2000mg/jour'
            ],
            'examens' => ['Examen direct des crachats', 'Culture de BK', 'Radiographie thoracique', 'Test tuberculinique']
        ],
        
        'leishmaniose' => [
            'diagnostics' => [
                'Leishmaniose cutanée',
                'Leishmaniose viscérale (kala-azar)',
                'Leishmaniose muco-cutanée',
                'Leishmaniose diffuse'
            ],
            'traitements' => [
                'Antimoniate de méglumine',
                'Amphotéricine B liposomale',
                'Miltefosine',
                'Traitement local si cutanée'
            ],
            'medicaments' => [
                'Antimoniate de méglumine 20mg/kg/jour',
                'Amphotéricine B liposomale 3-5mg/kg',
                'Miltefosine 50-100mg/jour'
            ],
            'examens' => ['Frottis de lésion', 'Culture de Leishmania', 'Sérologie', 'PCR']
        ],
        
        // MALADIES INFECTIEUSES COURANTES
        'typhoide' => [
            'diagnostics' => [
                'Fièvre typhoïde',
                'Paratyphoïde A, B, C',
                'Fièvre entérique',
                'Salmonellose invasive'
            ],
            'traitements' => [
                'Antibiotiques adaptés',
                'Hydratation',
                'Antipyrétiques',
                'Surveillance des complications'
            ],
            'medicaments' => [
                'Ciprofloxacine 500mg x2/jour',
                'Azithromycine 500mg/jour',
                'Ceftriaxone 2g/jour si résistance'
            ],
            'examens' => ['Hémocultures', 'Coproculture', 'Sérologie de Widal', 'Hémogramme']
        ],
        
        'cholera' => [
            'diagnostics' => [
                'Choléra classique',
                'Choléra grave',
                'Diarrhée cholériforme',
                'Choléra infantile'
            ],
            'traitements' => [
                'Réhydratation orale/IV',
                'Antibiotiques si grave',
                'Isolation du patient',
                'Prévention des contacts'
            ],
            'medicaments' => [
                'Sels de réhydratation orale',
                'Doxycycline 300mg en dose unique',
                'Azithromycine 1g en dose unique'
            ],
            'examens' => ['Examen direct des selles', 'Culture de Vibrio cholerae', 'Ionogramme']
        ],
        
        'onchocercose' => [
            'diagnostics' => [
                'Onchocercose cutanée',
                'Onchocercose oculaire',
                'Cécité des rivières',
                'Dermatite onchocerquienne'
            ],
            'traitements' => [
                'Ivermectine',
                'Doxycycline si nécessaire',
                'Traitement des complications oculaires',
                'Prévention des réinfections'
            ],
            'medicaments' => [
                'Ivermectine 150μg/kg en dose unique',
                'Doxycycline 100mg/jour pendant 6 semaines'
            ],
            'examens' => ['Biopsie cutanée', 'Examen ophtalmologique', 'Test de Mazzotti']
        ],
        
        'trypanosomiase' => [
            'diagnostics' => [
                'Trypanosomiase africaine (maladie du sommeil)',
                'Trypanosomiase gambiense',
                'Trypanosomiase rhodésiense',
                'Trypanosomiase américaine (maladie de Chagas)'
            ],
            'traitements' => [
                'Suramine si stade précoce',
                'Mélarsoprol si atteinte neurologique',
                'Éflornithine si gambiense',
                'Nifurtimox si américaine'
            ],
            'medicaments' => [
                'Suramine 20mg/kg',
                'Mélarsoprol 2.2mg/kg/jour',
                'Éflornithine 400mg/kg/jour',
                'Nifurtimox 8-10mg/kg/jour'
            ],
            'examens' => ['Examen du sang', 'Ponction ganglionnaire', 'Ponction lombaire', 'Examen du LCR']
        ]
    ];
    
    /**
     * Mots-clés pour détecter les symptômes dans le texte
     */
    private static $symptomKeywords = [
        'toux' => ['toux', 'tousser', 'toussant', 'tousse'],
        'fievre' => ['fièvre', 'febrile', 'hyperthermie', 'chaud', 'température', 'fievre', 'fièvre', 'fievre', 'fèvre'],
        'maux_de_tete' => ['mal de tête', 'céphalée', 'migraine', 'tête', 'crâne', 'maux de tête', 'maux'],
        'douleurs_abdominales' => ['mal au ventre', 'douleur abdominale', 'crampes', 'estomac', 'abdomen'],
        'fatigue' => ['fatigue', 'fatigué', 'épuisé', 'lassitude', 'faiblesse'],
        'nausees_vomissements' => ['nausée', 'vomissement', 'nauséabond', 'vomit', 'mal au cœur'],
        'douleurs_articulaires' => ['articulation', 'rhumatisme', 'arthrite', 'arthrose', 'genou', 'épaule'],
        'troubles_sommeil' => ['insomnie', 'sommeil', 'dormir', 'réveil', 'fatigue matinale'],
        'douleurs_thoraciques' => ['douleur thoracique', 'poitrine', 'cœur', 'respiration'],
        'vertiges' => ['vertige', 'étourdissement', 'équilibre', 'tournis'],
        
        // NOUVEAUX SYMPTÔMES DU DOS
        'douleurs_dos' => ['mal au dos', 'douleur au dos', 'douleur dorsale', 'douleur de dos', 'dos', 'rachis', 'colonne vertébrale'],
        'lombalgie' => ['lombalgie', 'mal au bas du dos', 'douleur lombaire', 'bas du dos', 'lombes', 'lombaire'],
        'cervicalgie' => ['cervicalgie', 'mal au cou', 'douleur cervicale', 'torticolis', 'cou', 'nuque', 'cervical'],
        
        // NOUVEAUX SYMPTÔMES OCULAIRES
        'douleurs_yeux' => ['mal aux yeux', 'douleur oculaire', 'douleur aux yeux', 'œil', 'yeux', 'ophtalmique', 'oculaire'],
        'vision_floue' => ['vision floue', 'vue floue', 'flou', 'mal voir', 'voir flou', 'vision trouble', 'vue trouble'],
        'yeux_rouges' => ['yeux rouges', 'œil rouge', 'rougeur oculaire', 'conjonctivite', 'œil irrité'],
        
        // NOUVEAUX SYMPTÔMES GASTRO-INTESTINAUX
        'ulcere' => ['ulcère', 'ulcere', 'ulcération', 'ulceration', 'estomac ulcéré', 'gastrique'],
        'brulures_estomac' => ['brûlure estomac', 'brulure estomac', 'brûlure d\'estomac', 'acidité', 'brûlures', 'pyrosis'],
        'diarrhee' => ['diarrhée', 'diarrhee', 'selles liquides', 'évacuation fréquente', 'liquide'],
        'constipation' => ['constipation', 'constipé', 'difficulté défécation', 'selles dures', 'aller aux toilettes'],
        
        // MALADIES CHRONIQUES
        'diabete' => ['diabète', 'diabete', 'glycémie élevée', 'hyperglycémie', 'insuline', 'glucose'],
        'polyurie' => ['polyurie', 'uriner beaucoup', 'urines fréquentes', 'diurèse importante'],
        'polydipsie' => ['polydipsie', 'soif intense', 'boire beaucoup', 'hydratation excessive'],
        
        // MALADIES CARDIOVASCULAIRES
        'hypertension' => ['hypertension', 'tension élevée', 'pression artérielle', 'hta', 'tension artérielle'],
        'angine_poitrine' => ['angine', 'douleur thoracique', 'oppression thoracique', 'douleur poitrine', 'cœur'],
        'insuffisance_cardiaque' => ['insuffisance cardiaque', 'cœur faible', 'défaillance cardiaque', 'cardiopathie'],
        
        // MALADIES HÉPATIQUES
        'hepatite' => ['hépatite', 'hepatite', 'foie', 'transaminases', 'hépatique'],
        'cirrhose' => ['cirrhose', 'cirrhose hépatique', 'foie cirrhotique', 'ascite'],
        'ictere' => ['ictère', 'jaunisse', 'peau jaune', 'sclérotique jaune', 'bilirubine'],
        
        // MALADIES RÉNALES
        'insuffisance_renale' => ['insuffisance rénale', 'rein', 'créatinine élevée', 'urée', 'dialyse'],
        'colique_nephretique' => ['colique néphrétique', 'calcul rénal', 'calcul urinaire', 'lithiase'],
        
        // MALADIES TROPICALES AU MALI
        'paludisme' => ['paludisme', 'malaria', 'fièvre palustre', 'plasmodium', 'moustique', 'fièvre intermittente'],
        'dengue' => ['dengue', 'fièvre dengue', 'fièvre hémorragique', 'moustique tigre'],
        'chikungunya' => ['chikungunya', 'fièvre chikungunya', 'arthrite chikungunya'],
        'meningite' => ['méningite', 'meningite', 'raideur de nuque', 'céphalée intense', 'lcr'],
        'tuberculose' => ['tuberculose', 'tuberculose pulmonaire', 'bk', 'bactérie de koch', 'tbc'],
        'leishmaniose' => ['leishmaniose', 'kala-azar', 'bouton d\'orient', 'mouche du sable'],
        'typhoide' => ['typhoïde', 'typhoide', 'fièvre typhoïde', 'salmonelle', 'fièvre entérique'],
        'cholera' => ['choléra', 'cholera', 'vibrio cholerae', 'diarrhée aqueuse', 'déshydratation'],
        'onchocercose' => ['onchocercose', 'cécité des rivières', 'onchocerca', 'simulie'],
        'trypanosomiase' => ['trypanosomiase', 'maladie du sommeil', 'trypanosome', 'glossine', 'mouche tsé-tsé']
    ];
    
    /**
     * Analyser les symptômes et suggérer un diagnostic
     */
    public static function analyzeSymptoms($symptomes) {
        if (empty($symptomes)) {
            return [
                'error' => 'Aucun symptôme fourni',
                'suggestions' => []
            ];
        }
        
        $symptomes = mb_strtolower($symptomes, 'UTF-8');
        $detectedSymptoms = [];
        $confidence = [];
        
        // Détecter les symptômes dans le texte
        foreach (self::$symptomKeywords as $symptomKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($symptomes, $keyword, 0, 'UTF-8') !== false) {
                    $detectedSymptoms[] = $symptomKey;
                    $confidence[$symptomKey] = ($confidence[$symptomKey] ?? 0) + 1;
                    break;
                }
            }
        }
        
        if (empty($detectedSymptoms)) {
            return [
                'error' => 'Aucun symptôme reconnu',
                'suggestions' => []
            ];
        }
        
        // Combiner les diagnostics de tous les symptômes détectés
        $combinedDiagnostics = [];
        $combinedTreatments = [];
        $combinedMedications = [];
        $combinedExams = [];
        
        foreach ($detectedSymptoms as $symptom) {
            if (isset(self::$medicalKnowledge[$symptom])) {
                $knowledge = self::$medicalKnowledge[$symptom];
                
                // Pondérer les suggestions selon la confiance
                $weight = $confidence[$symptom];
                
                foreach ($knowledge['diagnostics'] as $diagnostic) {
                    $combinedDiagnostics[$diagnostic] = ($combinedDiagnostics[$diagnostic] ?? 0) + $weight;
                }
                
                foreach ($knowledge['traitements'] as $traitement) {
                    $combinedTreatments[$traitement] = ($combinedTreatments[$traitement] ?? 0) + $weight;
                }
                
                foreach ($knowledge['medicaments'] as $medicament) {
                    $combinedMedications[$medicament] = ($combinedMedications[$medicament] ?? 0) + $weight;
                }
                
                foreach ($knowledge['examens'] as $examen) {
                    $combinedExams[$examen] = ($combinedExams[$examen] ?? 0) + $weight;
                }
            }
        }
        
        // Trier par score de confiance
        arsort($combinedDiagnostics);
        arsort($combinedTreatments);
        arsort($combinedMedications);
        arsort($combinedExams);
        
        return [
            'symptomes_detectes' => $detectedSymptoms,
            'confiance' => $confidence,
            'diagnostics' => array_keys(array_slice($combinedDiagnostics, 0, 5, true)),
            'traitements' => array_keys(array_slice($combinedTreatments, 0, 5, true)),
            'medicaments' => array_keys(array_slice($combinedMedications, 0, 5, true)),
            'examens' => array_keys(array_slice($combinedExams, 0, 5, true)),
            'diagnostic_principal' => array_key_first($combinedDiagnostics),
            'confidence_score' => max($confidence)
        ];
    }
    
    /**
     * Générer un diagnostic complet basé sur les symptômes
     */
    public static function generateDiagnostic($symptomes, $patientAge = null, $patientSexe = null) {
        $analysis = self::analyzeSymptoms($symptomes);
        
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        $diagnostic = "DIAGNOSTIC PROBABLE :\n";
        $diagnostic .= "Symptômes rapportés : " . implode(', ', $analysis['symptomes_detectes']) . "\n\n";
        
        $diagnostic .= "Diagnostic principal : " . $analysis['diagnostic_principal'] . "\n";
        $diagnostic .= "Score de confiance : " . $analysis['confidence_score'] . "/5\n\n";
        
        $diagnostic .= "Diagnostics différentiels :\n";
        foreach ($analysis['diagnostics'] as $index => $diag) {
            $diagnostic .= ($index + 1) . ". " . $diag . "\n";
        }
        
        // Personnalisation selon l'âge et le sexe
        if ($patientAge || $patientSexe) {
            $diagnostic .= "\nCONSIDÉRATIONS SPÉCIFIQUES :\n";
            if ($patientAge >= 65) {
                $diagnostic .= "- Patient âgé : surveillance accrue recommandée\n";
            }
            if ($patientSexe === 'F' && $patientAge >= 15 && $patientAge <= 50) {
                $diagnostic .= "- Femme en âge de procréer : exclure grossesse si nécessaire\n";
            }
        }
        
        return [
            'diagnostic_complet' => $diagnostic,
            'analysis' => $analysis
        ];
    }
    
    /**
     * Générer un traitement basé sur l'analyse
     */
    public static function generateTreatment($analysis) {
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        $traitement = "PLAN DE TRAITEMENT :\n\n";
        
        $traitement .= "Traitement principal :\n";
        foreach (array_slice($analysis['traitements'], 0, 3) as $index => $trait) {
            $traitement .= ($index + 1) . ". " . $trait . "\n";
        }
        
        $traitement .= "\nSurveillance :\n";
        $traitement .= "- Évolution des symptômes\n";
        $traitement .= "- Tolérance au traitement\n";
        $traitement .= "- Signes d'aggravation\n";
        
        $traitement .= "\nConsultation de suivi :\n";
        $traitement .= "- Dans 48-72h si pas d'amélioration\n";
        $traitement .= "- Urgence si aggravation\n";
        
        return $traitement;
    }
    
    
    /**
     * Obtenir des suggestions contextuelles avec vérification des antécédents
     */
    public static function getContextualSuggestions($symptomes, $patientAge = null, $patientSexe = null, $patientAntecedents = null, $patientAllergies = null) {
        $analysis = self::analyzeSymptoms($symptomes);
        
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        // Vérifier les interactions et contre-indications
        $safetyChecks = self::performSafetyChecks($analysis, $patientAntecedents, $patientAllergies, $patientAge, $patientSexe);
        
        $suggestions = [
            'diagnostic' => self::generateDiagnostic($symptomes, $patientAge, $patientSexe),
            'traitement' => self::generateTreatment($analysis),
            'ordonnance' => self::generatePrescription($analysis, $patientAge),
            'safety_checks' => $safetyChecks
        ];
        
        return $suggestions;
    }

    /**
     * Recalcule les alertes de sécurité après enrichissement externe (ex. Mistral).
     */
    public static function refreshSafetyChecksForSuggestions(
        array &$suggestions,
        $patientAntecedents = null,
        $patientAllergies = null,
        $patientAge = null,
        $patientSexe = null
    ): void {
        $analysis = $suggestions['diagnostic']['analysis'] ?? null;
        if (!is_array($analysis) || isset($analysis['error'])) {
            return;
        }

        $safetyChecks = self::performSafetyChecks(
            $analysis,
            $patientAntecedents,
            $patientAllergies,
            $patientAge,
            $patientSexe
        );
        $suggestions['safety_checks'] = $safetyChecks;
        $suggestions['ordonnance'] = self::generatePrescription($analysis, $patientAge, $safetyChecks);
    }
    
    /**
     * Effectuer des vérifications de sécurité basées sur les antécédents
     */
    private static function performSafetyChecks($analysis, $antecedents = null, $allergies = null, $age = null, $sexe = null) {
        $warnings = [];
        $contraindications = [];
        $interactions = [];
        
        // Vérifier les allergies
        if (!empty($allergies)) {
            $allergiesList = array_map('trim', explode(',', $allergies));
            foreach ($allergiesList as $allergie) {
                $allergie = strtolower($allergie);
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    
                    // Vérifier les allergies connues
                    if (strpos($medicamentLower, 'pénicilline') !== false && strpos($allergie, 'pénicilline') !== false) {
                        $warnings[] = "⚠️ ALLERGIE: Le patient est allergique à la pénicilline - Éviter l'amoxicilline";
                        $contraindications[] = $medicament;
                    }
                    
                    if (strpos($medicamentLower, 'aspirine') !== false && strpos($allergie, 'aspirine') !== false) {
                        $warnings[] = "⚠️ ALLERGIE: Le patient est allergique à l'aspirine";
                        $contraindications[] = $medicament;
                    }
                    
                    if (strpos($medicamentLower, 'sulfamide') !== false && strpos($allergie, 'sulfamide') !== false) {
                        $warnings[] = "⚠️ ALLERGIE: Le patient est allergique aux sulfamides";
                        $contraindications[] = $medicament;
                    }
                }
            }
        }
        
        // Vérifier les antécédents médicaux
        if (!empty($antecedents)) {
            $antecedentsLower = strtolower($antecedents);
            
            // Diabète
            if (strpos($antecedentsLower, 'diabète') !== false || strpos($antecedentsLower, 'diabete') !== false) {
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    if (strpos($medicamentLower, 'corticoïde') !== false || strpos($medicamentLower, 'prednisolone') !== false) {
                        $warnings[] = "⚠️ DIABÈTE: Attention aux corticoïdes qui peuvent augmenter la glycémie";
                        $interactions[] = $medicament;
                    }
                }
            }
            
            // Hypertension
            if (strpos($antecedentsLower, 'hypertension') !== false || strpos($antecedentsLower, 'hta') !== false) {
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    if (strpos($medicamentLower, 'ibuprofène') !== false) {
                        $warnings[] = "⚠️ HYPERTENSION: L'ibuprofène peut augmenter la tension artérielle";
                        $interactions[] = $medicament;
                    }
                }
            }
            
            // Insuffisance rénale
            if (strpos($antecedentsLower, 'insuffisance rénale') !== false || strpos($antecedentsLower, 'renal') !== false) {
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    if (strpos($medicamentLower, 'amoxicilline') !== false || strpos($medicamentLower, 'paracétamol') !== false) {
                        $warnings[] = "⚠️ INSUFFISANCE RÉNALE: Ajuster les posologies des médicaments éliminés par les reins";
                        $interactions[] = $medicament;
                    }
                }
            }
            
            // Cardiopathie
            if (strpos($antecedentsLower, 'cardiopathie') !== false || strpos($antecedentsLower, 'cardiaque') !== false) {
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    if (strpos($medicamentLower, 'ibuprofène') !== false) {
                        $warnings[] = "⚠️ CARDIOPATHIE: L'ibuprofène peut aggraver les problèmes cardiaques";
                        $contraindications[] = $medicament;
                    }
                }
            }
            
            // Grossesse
            if ($sexe === 'F' && $age >= 15 && $age <= 50) {
                foreach ($analysis['medicaments'] as $medicament) {
                    $medicamentLower = strtolower($medicament);
                    if (strpos($medicamentLower, 'ibuprofène') !== false || strpos($medicamentLower, 'aspirine') !== false) {
                        $warnings[] = "⚠️ GROSSESSE POTENTIELLE: Éviter les AINS pendant la grossesse";
                        $contraindications[] = $medicament;
                    }
                }
            }
        }
        
        // Vérifications liées à l'âge
        if ($age >= 65) {
            foreach ($analysis['medicaments'] as $medicament) {
                $medicamentLower = strtolower($medicament);
                if (strpos($medicamentLower, 'morphine') !== false || strpos($medicamentLower, 'codéine') !== false) {
                    $warnings[] = "⚠️ PATIENT ÂGÉ: Surveillance accrue des opiacés chez les personnes âgées";
                    $interactions[] = $medicament;
                }
            }
        }
        
        return [
            'warnings' => $warnings,
            'contraindications' => array_unique($contraindications),
            'interactions' => array_unique($interactions),
            'has_warnings' => !empty($warnings),
            'has_contraindications' => !empty($contraindications),
            'has_interactions' => !empty($interactions)
        ];
    }
    
    /**
     * Générer une ordonnance sécurisée avec vérifications
     */
    public static function generatePrescription($analysis, $patientAge = null, $safetyChecks = null) {
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        $ordonnance = "ORDONNANCE MÉDICALE\n";
        $ordonnance .= "Date : " . date('d/m/Y') . "\n\n";
        
        // Filtrer les médicaments contre-indiqués
        $medicaments = $analysis['medicaments'];
        if ($safetyChecks && !empty($safetyChecks['contraindications'])) {
            $medicaments = array_filter($medicaments, function($medicament) use ($safetyChecks) {
                return !in_array($medicament, $safetyChecks['contraindications']);
            });
        }
        
        $ordonnance .= "MÉDICAMENTS PRESCRITS :\n";
        foreach (array_slice($medicaments, 0, 4) as $index => $medicament) {
            $ordonnance .= ($index + 1) . ". " . $medicament . "\n";
        }
        
        // Ajouter les avertissements de sécurité
        if ($safetyChecks && $safetyChecks['has_warnings']) {
            $ordonnance .= "\n⚠️ AVERTISSEMENTS DE SÉCURITÉ :\n";
            foreach ($safetyChecks['warnings'] as $warning) {
                $ordonnance .= $warning . "\n";
            }
        }
        
        // Ajustements selon l'âge
        if ($patientAge >= 65) {
            $ordonnance .= "\nATTENTION : Patient âgé - surveillance des effets secondaires\n";
        }
        
        $ordonnance .= "\nEXAMENS COMPLÉMENTAIRES RECOMMANDÉS :\n";
        foreach (array_slice($analysis['examens'], 0, 3) as $index => $examen) {
            $ordonnance .= ($index + 1) . ". " . $examen . "\n";
        }
        
        $ordonnance .= "\nINSTRUCTIONS :\n";
        $ordonnance .= "- Respecter les posologies\n";
        $ordonnance .= "- Ne pas arrêter le traitement sans avis médical\n";
        $ordonnance .= "- Consulter en cas d'effets indésirables\n";
        
        if ($safetyChecks && $safetyChecks['has_warnings']) {
            $ordonnance .= "- ⚠️ SURVEILLANCE RENFORCÉE REQUISE\n";
        }
        
        return $ordonnance;
    }
}
?>
