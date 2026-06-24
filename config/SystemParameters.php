<?php
/**
 * Classe SystemParameters
 * Gestion centralisée des paramètres du système pour les exports PDF
 */

class SystemParameters {
    /** Paramètres d'identité visuelle : jamais hérités du global (tenant_id NULL) par un abonné. */
    private const TENANT_BRANDING_KEYS = [
        'logo_clinique',
        'logo_alt_text',
        'nom_etablissement',
        'couleur_principale',
        'couleur_secondaire',
    ];

    private static $instance = null;
    private $pdo;
    private $parametres = [];
    /** @var int|null */
    private $tenantId = null;
    /** @var int|null */
    private $loadedTenantId = null;
    private $defaults = [
        // Identité de l'établissement
        'nom_etablissement' => 'Clinique et Hôpital',
        'adresse' => '123 Avenue de la Santé',
        'ville' => 'Votre Ville',
        'code_postal' => '00000',
        'pays' => 'Mali',
        'telephone' => '+223 XX XX XX XX',
        'email' => 'contact@clinique.com',
        'site_web' => 'www.clinique.com',
        
        // Devise et finances (stockage en FCFA, affichage paramétrable)
        'devise_code' => 'XOF',
        'devise_symbole' => 'FCFA',
        'devise_decimaux' => '0',
        'devise_conversion_actif' => '0',
        
        // Informations légales
        'numero_agrement' => 'N/A',
        'numero_fiscal' => 'N/A',
        'registre_commerce' => 'N/A',
        
        // Logo et identité visuelle
        'logo_clinique' => '', // Chemin vers le fichier logo
        'logo_alt_text' => 'Logo de la clinique', // Texte alternatif pour le logo
        'couleur_principale' => '#dc3545',
        'couleur_secondaire' => '#007bff',
        
        // Paramètres système
        'langue' => 'fr',
        'theme' => 'default',
        'timezone' => 'Africa/Bamako',
        
        // Informations de contact supplémentaires
        'fax' => '',
        'mobile' => '',
        'horaires_ouverture' => 'Lun-Ven: 8h-18h, Sam: 8h-12h',
        'urgence_telephone' => '+223 XX XX XX XX',

        // Imprimante thermique tickets (Xprinter XP-80TS — ESC/POS, 80 mm, réseau port 9100)
        'thermal_printer_actif' => '1',
        'thermal_printer_ip' => '',
        'thermal_printer_port' => '9100',
        'thermal_printer_width_mm' => '80',
        'thermal_printer_model' => 'Xprinter XP-80TS',

        // Module patients — suppression autorisée (1) ou interdite (0)
        'patients_suppression_actif' => '1',

        // Module médecins — le secrétaire peut ajouter des professionnels (1) ou non (0)
        'secretaire_medecins_ajout_actif' => '0',

        // Module laboratoire — le secrétaire peut supprimer des analyses (1) ou non (0)
        'secretaire_labo_suppression_actif' => '0',

        // Alertes sonores — assignation patient & messages communication
        'notifications_sonores_actif' => '1',
    ];

    private function __construct(?int $tenantId = null) {
        $this->tenantId = $tenantId;
        $this->loadedTenantId = $tenantId;
        $this->pdo = $this->getConnection();
        $this->loadParameters();
    }

    /**
     * @return int|null
     */
    private static function resolveTenantId() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['tenant_id'])) {
            return (int) $_SESSION['tenant_id'];
        }
        return null;
    }

    public static function getInstance() {
        $tenantId = self::resolveTenantId();
        if (self::$instance === null || self::$instance->loadedTenantId !== $tenantId) {
            self::$instance = new self($tenantId);
        }
        return self::$instance;
    }

    /** Instance forcée pour un établissement (PDF / impressions). */
    public static function forTenant(?int $tenantId): self
    {
        self::$instance = new self($tenantId);
        return self::$instance;
    }

    public static function resetInstance() {
        self::$instance = null;
    }

    /**
     * Obtenir la connexion à la base de données (même source que le reste de l'application)
     */
    private function getConnection() {
        try {
            if (!function_exists('getDB')) {
                require_once __DIR__ . '/db.php';
            }
            $pdo = getDB();
            if ($pdo instanceof PDO) {
                return $pdo;
            }
        } catch (Throwable $e) {
            error_log('SystemParameters::getConnection: ' . $e->getMessage());
        }
        return null;
    }

    private function hasTenantColumn() {
        if (!$this->pdo) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parametres_systeme' AND COLUMN_NAME = 'tenant_id'"
            );
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Charger les paramètres depuis la base de données (global + surcharge tenant).
     */
    private function loadParameters() {
        if (!$this->pdo) {
            return;
        }

        try {
            $this->createTableIfNotExists();
            $this->parametres = [];

            if ($this->hasTenantColumn()) {
                $stmt = $this->pdo->query(
                    "SELECT cle, valeur FROM parametres_systeme WHERE tenant_id IS NULL"
                );
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($this->tenantId && in_array($row['cle'], self::TENANT_BRANDING_KEYS, true)) {
                        continue;
                    }
                    $this->parametres[$row['cle']] = $row['valeur'];
                }
                if ($this->tenantId) {
                    $stmt = $this->pdo->prepare(
                        "SELECT cle, valeur FROM parametres_systeme WHERE tenant_id = ?"
                    );
                    $stmt->execute([$this->tenantId]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $this->parametres[$row['cle']] = $row['valeur'];
                    }
                }
            } else {
                $stmt = $this->pdo->query("SELECT cle, valeur FROM parametres_systeme");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $this->parametres[$row['cle']] = $row['valeur'];
                }
            }
        } catch (Exception $e) {
            // En cas d'erreur, utiliser les valeurs par défaut
        }
    }

    /**
     * Créer la table des paramètres si elle n'existe pas
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS parametres_systeme (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cle VARCHAR(100) UNIQUE NOT NULL,
            valeur TEXT,
            description TEXT,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }

    /**
     * Obtenir un paramètre
     */
    public function get($key, $default = null) {
        if (isset($this->parametres[$key])) {
            return $this->parametres[$key];
        }
        
        if (isset($this->defaults[$key])) {
            return $this->defaults[$key];
        }
        
        return $default;
    }

    /**
     * Obtenir tous les paramètres
     */
    public function getAll() {
        $all = [];
        foreach ($this->defaults as $key => $default) {
            $all[$key] = $this->get($key);
        }
        return $all;
    }

    /**
     * Obtenir les paramètres d'identité pour les PDF
     */
    public function getIdentityForPDF() {
        return [
            'nom_etablissement' => $this->get('nom_etablissement'),
            'adresse' => $this->get('adresse'),
            'ville' => $this->get('ville'),
            'code_postal' => $this->get('code_postal'),
            'pays' => $this->get('pays'),
            'telephone' => $this->get('telephone'),
            'email' => $this->get('email'),
            'site_web' => $this->get('site_web'),
            'logo_clinique' => $this->get('logo_clinique'),
            'numero_agrement' => $this->get('numero_agrement'),
            'numero_fiscal' => $this->get('numero_fiscal'),
            'registre_commerce' => $this->get('registre_commerce'),
            'horaires_ouverture' => $this->get('horaires_ouverture'),
            'urgence_telephone' => $this->get('urgence_telephone')
        ];
    }

    /**
     * Obtenir les paramètres de devise pour les PDF
     */
    public function getCurrencyForPDF() {
        $s = $this->getCurrencySettings();
        return [
            'devise_code' => $s['code'],
            'devise_symbole' => $s['symbol'],
            'devise_decimales' => $s['decimals'],
            'devise_conversion_actif' => $s['conversion'] ? '1' : '0',
        ];
    }

    /**
     * Formater une adresse complète
     */
    public function getFullAddress() {
        $parts = [];
        
        if ($this->get('adresse')) {
            $parts[] = $this->get('adresse');
        }
        
        if ($this->get('code_postal') || $this->get('ville')) {
            $cityPart = '';
            if ($this->get('code_postal')) {
                $cityPart .= $this->get('code_postal') . ' ';
            }
            if ($this->get('ville')) {
                $cityPart .= $this->get('ville');
            }
            $parts[] = trim($cityPart);
        }
        
        if ($this->get('pays')) {
            $parts[] = $this->get('pays');
        }
        
        return implode(', ', $parts);
    }

    /**
     * Formater les informations de contact
     */
    public function getContactInfo() {
        $contact = [];
        
        if ($this->get('telephone')) {
            $contact[] = 'Tél: ' . $this->get('telephone');
        }
        
        if ($this->get('fax')) {
            $contact[] = 'Fax: ' . $this->get('fax');
        }
        
        if ($this->get('mobile')) {
            $contact[] = 'Mobile: ' . $this->get('mobile');
        }
        
        if ($this->get('email')) {
            $contact[] = 'Email: ' . $this->get('email');
        }
        
        if ($this->get('site_web')) {
            $contact[] = 'Web: ' . $this->get('site_web');
        }
        
        return implode(' | ', $contact);
    }

    /**
     * Générer l'en-tête HTML pour les PDF
     */
    public function generatePDFHeader($title = '', $subtitle = '') {
        $html = '
        <div class="header" style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px;">';

        $html .= $this->getPdfLogoBlockHtml(['align' => 'center', 'max_height' => 65, 'max_width' => 180]);
        
        if ($title) {
            $html .= '<div class="title" style="font-size: 20px; font-weight: bold; color: #333; margin: 0;">' . htmlspecialchars($title) . '</div>';
        }
        
        if ($subtitle) {
            $html .= '<div class="subtitle" style="font-size: 14px; color: #666; margin: 5px 0 0 0;">' . htmlspecialchars($subtitle) . '</div>';
        }
        
        $html .= '
            <div style="font-size: 10px; color: #666; margin-top: 5px;">
                ' . htmlspecialchars($this->getFullAddress()) . '<br>
                ' . htmlspecialchars($this->getContactInfo()) . '
            </div>
        </div>';
        
        return $html;
    }

    /**
     * Générer le pied de page HTML pour les PDF
     */
    public function generatePDFFooter() {
        $identity = $this->getIdentityForPDF();
        
        $html = '
        <div class="footer" style="margin-top: 25px; text-align: center; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 12px;">
            <p style="margin: 3px 0;"><strong>' . htmlspecialchars($identity['nom_etablissement']) . '</strong></p>
            <p style="margin: 3px 0;">' . htmlspecialchars($this->getFullAddress()) . '</p>
            <p style="margin: 3px 0;">' . htmlspecialchars($this->getContactInfo()) . '</p>';
        
        if ($identity['numero_agrement'] && $identity['numero_agrement'] !== 'N/A') {
            $html .= '<p style="margin: 3px 0;">N° Agrément: ' . htmlspecialchars($identity['numero_agrement']) . '</p>';
        }
        
        $html .= '
            <p style="margin: 3px 0;">Ce document a été généré automatiquement le ' . date('d/m/Y H:i') . '</p>
            <p style="margin: 3px 0;">Pour toute question, contactez notre service</p>
        </div>';
        
        return $html;
    }

    /**
     * Paramètres devise de l'établissement (montants stockés en FCFA).
     *
     * @return array{code: string, symbol: string, decimals: int, conversion: bool, base: string, rateFromBase: float, name: string}
     */
    public function getCurrencySettings(): array
    {
        if (!class_exists('CurrencyConfig')) {
            require_once __DIR__ . '/CurrencyConfig.php';
        }

        $code = CurrencyConfig::normalizeCode((string) $this->get('devise_code', 'XOF'));
        $symbol = trim((string) $this->get('devise_symbole', 'FCFA'));
        $decimals = (int) $this->get('devise_decimaux', 0);
        $conversion = $this->get('devise_conversion_actif', '0') === '1';

        if ($symbol === '') {
            $symbol = CurrencyConfig::CURRENCY_PRESETS[$code]['symbol'] ?? 'FCFA';
        }
        if (!array_key_exists($code, CurrencyConfig::CURRENCY_PRESETS) && $decimals < 0) {
            $decimals = 0;
        }

        return [
            'code' => $code,
            'symbol' => $symbol,
            'decimals' => max(0, $decimals),
            'conversion' => $conversion && !CurrencyConfig::isFCFA($code),
            'base' => CurrencyConfig::BASE_CURRENCY,
            'rateFromBase' => CurrencyConfig::getRateFromFCFA($code),
            'name' => CurrencyConfig::getCurrencyName($code),
        ];
    }

    /**
     * Formater un montant selon la devise configurée (montant stocké en FCFA).
     */
    public function formatCurrency($amount, bool $showSymbol = true): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            $amount = 0;
        }
        if (!class_exists('CurrencyConfig')) {
            require_once __DIR__ . '/CurrencyConfig.php';
        }
        return CurrencyConfig::formatForTenant((float) $amount, $this->getCurrencySettings(), $showSymbol);
    }

    /**
     * Alias — montant stocké en FCFA, affichage selon paramètres tenant.
     */
    public function formatFCFA($amount): string
    {
        return $this->formatCurrency($amount);
    }

    /**
     * Formater un montant en FCFA brut (sans conversion).
     */
    public function formatStorageFCFA($amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            $amount = 0;
        }
        if (!class_exists('CurrencyConfig')) {
            require_once __DIR__ . '/CurrencyConfig.php';
        }
        return CurrencyConfig::formatForTenant(
            (float) $amount,
            ['code' => 'XOF', 'symbol' => 'FCFA', 'decimals' => 0, 'conversion' => false],
            true
        );
    }

    /**
     * Devise d'affichage de l'établissement.
     */
    public function getCurrency(): array
    {
        $s = $this->getCurrencySettings();
        return [
            'code' => $s['code'],
            'symbol' => $s['symbol'],
            'decimals' => $s['decimals'],
            'name' => $s['name'],
            'base' => $s['base'],
            'conversion' => $s['conversion'],
        ];
    }

    /**
     * Nom et logo de l'établissement : réservés au rôle administrateur.
     */
    private function assertBrandingAdmin(string $key): void
    {
        if (!in_array($key, self::TENANT_BRANDING_KEYS, true)) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!class_exists('Auth')) {
            require_once __DIR__ . '/Auth.php';
        }
        $auth = Auth::getInstance();
        if (!$auth->estConnecte() || !$auth->estAdmin()) {
            throw new RuntimeException('Seuls les administrateurs peuvent modifier le nom et le logo de l\'établissement.');
        }
    }

    /**
     * Mettre à jour un paramètre
     */
    public function update($key, $value, $description = '') {
        if (!$this->pdo) {
            return false;
        }

        try {
            $this->assertBrandingAdmin($key);
            if ($this->hasTenantColumn()) {
                $sql = "INSERT INTO parametres_systeme (cle, valeur, description, tenant_id)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE valeur = ?, description = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$key, $value, $description, $this->tenantId, $value, $description]);
            } else {
                $sql = "INSERT INTO parametres_systeme (cle, valeur, description)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE valeur = ?, description = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$key, $value, $description, $value, $description]);
            }

            $this->parametres[$key] = $value;

            return true;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Initialiser les paramètres par défaut
     */
    public function initializeDefaults() {
        if (!$this->pdo) {
            return false;
        }

        try {
            foreach ($this->defaults as $key => $value) {
                $this->update($key, $value, 'Paramètre par défaut');
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gérer l'upload d'un logo
     */
    public function uploadLogo($file) {
        try {
            $this->assertBrandingAdmin('logo_clinique');
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier'];
        }

        // Vérifier le type de fichier - uniquement PNG et JPEG
        $allowedTypes = [
            'image/jpeg' => 'JPEG',
            'image/jpg' => 'JPEG', 
            'image/png' => 'PNG'
        ];
        
        // Vérifier le type MIME déclaré
        if (!in_array($file['type'], array_keys($allowedTypes))) {
            return ['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez uniquement JPG ou PNG.'];
        }
        
        // Vérification supplémentaire avec finfo pour plus de sécurité
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detectedMime, array_keys($allowedTypes))) {
            return ['success' => false, 'message' => 'Type de fichier détecté non autorisé. Utilisez uniquement JPG ou PNG.'];
        }

        // Vérifier la taille (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Fichier trop volumineux. Taille maximum : 2MB.'];
        }

        // Répertoire logos avec chemin absolu (__DIR__ = config/, donc ../ = racine)
        $logoDir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($logoDir)) {
            mkdir($logoDir, 0755, true);
        }

        // Supprimer l'ancien logo s'il existe (via getLogoPath() pour chemin absolu fiable)
        $oldLogoAbsPath = $this->getLogoPath();
        if ($oldLogoAbsPath && file_exists($oldLogoAbsPath)) {
            unlink($oldLogoAbsPath);
        }

        // Générer un nom unique pour le fichier avec extension correcte
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tenantPart = $this->tenantId ? ('t' . (int) $this->tenantId . '_') : 'global_';
        $filename = 'logo_' . $tenantPart . date('Y-m-d_H-i-s') . '.' . $extension;
        $absolutePath = $logoDir . $filename;

        // Déplacer le fichier uploadé
        if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
            // Stocker le chemin relatif à la racine du projet (portable, sans ../)
            $storedPath = 'uploads/logos/' . $filename;
            $this->update('logo_clinique', $storedPath);
            
            // Mettre à jour le texte alternatif avec le type d'image
            $imageType = $allowedTypes[$detectedMime];
            $this->update('logo_alt_text', "Logo de la clinique ($imageType)");
            
            return [
                'success' => true, 
                'message' => "Logo $imageType mis à jour avec succès", 
                'path' => $storedPath,
                'type' => $imageType,
                'size' => round($file['size'] / 1024, 2)
            ];
        } else {
            return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
        }
    }

    /**
     * Supprimer le logo actuel
     */
    public function removeLogo() {
        $this->assertBrandingAdmin('logo_clinique');

        // Utiliser getLogoPath() pour obtenir le chemin absolu fiable (indépendant du CWD)
        $currentLogoAbsPath = $this->getLogoPath();
        if ($currentLogoAbsPath && file_exists($currentLogoAbsPath)) {
            unlink($currentLogoAbsPath);
        }
        $this->update('logo_clinique', '');
        return true;
    }

    /**
     * Obtenir le chemin du logo
     */
    public function getLogoPath() {
        $logoPath = $this->get('logo_clinique');
        if (!$logoPath) {
            return null;
        }
        
        // Normaliser les séparateurs de chemin
        $logoPath = str_replace('\\', '/', $logoPath);
        
        // Si le chemin existe déjà tel quel et est absolu, le normaliser avec realpath
        if (file_exists($logoPath)) {
            $realPath = realpath($logoPath);
            return $realPath ?: $logoPath;
        }
        
        // Liste des tentatives de résolution de chemin
        $attempts = [];
        
        // Si le chemin est relatif (commence par ../), essayer depuis le répertoire config/
        if (strpos($logoPath, '../') === 0) {
            $attempts[] = __DIR__ . '/' . $logoPath;
            $attempts[] = __DIR__ . '/../' . str_replace('../', '', $logoPath);
        }
        
        // Si le chemin contient uploads/
        if (strpos($logoPath, 'uploads/') !== false) {
            // Extraire la partie après uploads/
            $uploadsIndex = strpos($logoPath, 'uploads/');
            $relativeFromUploads = substr($logoPath, $uploadsIndex);
            $attempts[] = __DIR__ . '/../' . $relativeFromUploads;
        }
        
        // Essayer aussi depuis la racine directement
        $attempts[] = __DIR__ . '/../' . ltrim($logoPath, './');
        
        // Essayer chaque tentative
        foreach ($attempts as $attempt) {
            $attempt = str_replace('\\', '/', $attempt);
            if (file_exists($attempt)) {
                $realPath = realpath($attempt);
                return $realPath ?: $attempt;
            }
        }
        
        // Si aucune tentative ne fonctionne, retourner le chemin original
        return $logoPath;
    }

    /**
     * Générer l'en-tête HTML avec logo pour les PDF (version compacte)
     */
    public function generatePDFHeaderWithLogo($title = '', $subtitle = '') {
        $html = '
        <div class="header" style="margin-bottom: 15px; padding: 10px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 6px; border: 1px solid #dee2e6;">
            <div style="display: flex; align-items: center; justify-content: space-between;">';

        $html .= '
                    <div class="logo-section" style="flex: 0 0 auto; margin-right: 15px;">'
            . $this->getPdfLogoHtml(['max_height' => 90, 'max_width' => 320, 'extra_style' => 'border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'])
            . '</div>';

        $html .= '
                <div class="info-section" style="flex: 1; text-align: right;">';
        
        if ($title) {
            $html .= '<div class="title" style="font-size: 16px; font-weight: bold; color: #333; margin: 0;">' . htmlspecialchars($title) . '</div>';
        }
        
        if ($subtitle) {
            $html .= '<div class="subtitle" style="font-size: 11px; color: #666; margin: 3px 0 0 0;">' . htmlspecialchars($subtitle) . '</div>';
        }
        
        $html .= '
                    <div style="font-size: 8px; color: #666; margin-top: 3px;">
                        ' . htmlspecialchars($this->getFullAddress()) . '<br>
                        ' . htmlspecialchars($this->getContactInfo()) . '
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }

    /**
     * Convertir une image en base64 pour l'affichage dans les PDF.
     * Redimensionne l'image via GD avant l'encodage pour obtenir un résultat
     * net et léger quelle que soit la taille originale du logo.
     *
     * @param string $logoPath   Chemin absolu vers le fichier image
     * @param int    $maxWidth   Largeur maximale en pixels (défaut : 400 = 2× affichage 200px)
     * @param int    $maxHeight  Hauteur maximale en pixels (défaut : 130 = 2× affichage 65px)
     */
    public function getLogoAsBase64($logoPath, $maxWidth = 400, $maxHeight = 130) {
        if (!$logoPath || !file_exists($logoPath)) {
            return null;
        }

        try {
            // Déterminer le type MIME
            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $allowedMimes = ['image/jpeg' => 'JPEG', 'image/jpg' => 'JPEG', 'image/png' => 'PNG'];
            $extensionMap  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $logoPath);
            finfo_close($finfo);

            if (!isset($allowedMimes[$mimeType])) {
                $mimeType = $extensionMap[$extension] ?? null;
                if (!$mimeType) return null;
            }

            // Tenter un redimensionnement GD pour un rendu net et un base64 compact
            if (function_exists('imagecopyresampled') && function_exists('imagecreatefrompng')) {
                $imgInfo = @getimagesize($logoPath);
                if ($imgInfo) {
                    list($origW, $origH) = $imgInfo;
                    $ratio = min($maxWidth / $origW, $maxHeight / $origH, 1.0);

                    if ($ratio < 1.0) {
                        // Downscale nécessaire
                        $newW = (int)round($origW * $ratio);
                        $newH = (int)round($origH * $ratio);

                        $src = null;
                        if ($mimeType === 'image/png')  $src = @imagecreatefrompng($logoPath);
                        elseif (function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($logoPath);

                        if ($src) {
                            $dst = imagecreatetruecolor($newW, $newH);
                            if ($mimeType === 'image/png') {
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                                imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
                                imagealphablending($dst, true);
                            }
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                            imagedestroy($src);

                            ob_start();
                            if ($mimeType === 'image/png') imagepng($dst, null, 7);
                            else                           imagejpeg($dst, null, 92);
                            $imageData = ob_get_clean();
                            imagedestroy($dst);

                            if ($imageData) {
                                $base64 = base64_encode($imageData);
                                return [
                                    'data'        => $base64,
                                    'mime'        => $mimeType,
                                    'type'        => $allowedMimes[$mimeType],
                                    'size'        => strlen($imageData),
                                    'base64_size' => strlen($base64),
                                ];
                            }
                        }
                    }
                }
            }

            // Fallback : encoder le fichier original
            $imageData = file_get_contents($logoPath);
            if (!$imageData || strlen($imageData) < 100) return null;

            $base64 = base64_encode($imageData);
            return [
                'data'        => $base64,
                'mime'        => $mimeType,
                'type'        => $allowedMimes[$mimeType],
                'size'        => strlen($imageData),
                'base64_size' => strlen($base64),
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Logo générique établissement (sans marque plateforme) pour PDF.
     * @return array{data: string, mime: string, type: string}|null
     */
    public function getGenericClinicLogoAsBase64(int $width = 360, int $height = 120): ?array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $teal = imagecolorallocate($image, 23, 161, 184);
        $tealDark = imagecolorallocate($image, 15, 122, 138);
        $white = imagecolorallocate($image, 255, 255, 255);

        $cx = (int) ($width * 0.22);
        $cy = (int) ($height / 2);
        $r = (int) min($height * 0.38, 56);
        imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $teal);
        imageellipse($image, $cx, $cy, $r * 2, $r * 2, $tealDark);
        imagefilledrectangle($image, $cx - (int) ($r * 0.35), $cy - (int) ($r * 0.55), $cx + (int) ($r * 0.35), $cy + (int) ($r * 0.55), $white);
        imagefilledrectangle($image, $cx - (int) ($r * 0.55), $cy - (int) ($r * 0.18), $cx + (int) ($r * 0.55), $cy + (int) ($r * 0.18), $white);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        if (!$imageData) {
            return null;
        }

        return [
            'data' => base64_encode($imageData),
            'mime' => 'image/png',
            'type' => 'PNG',
        ];
    }

    /**
     * Logo de l'établissement en base64 (upload ou icône générique).
     * @return array{data: string, mime: string, type?: string}|null
     */
    public function resolvePdfLogoBase64(int $maxWidth = 640, int $maxHeight = 200): ?array
    {
        $logoPath = $this->getLogoPath();
        if ($logoPath && file_exists($logoPath)) {
            $base64 = $this->getLogoAsBase64($logoPath, $maxWidth, $maxHeight);
            if ($base64 && !empty($base64['data'])) {
                return $base64;
            }
        }

        return $this->getGenericClinicLogoAsBase64($maxWidth, (int) max(80, $maxHeight));
    }

    /**
     * Balise &lt;img&gt; pour PDF / impression (logo agence uniquement).
     * @param array{max_height?: int, max_width?: int, extra_style?: string, margin?: string} $opts
     */
    public function getPdfLogoHtml(array $opts = []): string
    {
        $maxH = (int) ($opts['max_height'] ?? 90);
        $maxW = (int) ($opts['max_width'] ?? 320);
        $extra = (string) ($opts['extra_style'] ?? '');
        $margin = (string) ($opts['margin'] ?? '0 auto');

        $base64 = $this->resolvePdfLogoBase64($maxW * 2, $maxH * 2);
        if (!$base64) {
            return '';
        }

        return '<img src="data:' . $base64['mime'] . ';base64,' . $base64['data'] . '"'
            . ' alt="' . htmlspecialchars($this->get('logo_alt_text', 'Logo établissement')) . '"'
            . ' style="max-height: ' . $maxH . 'px; max-width: ' . $maxW . 'px; object-fit: contain; display: block; margin: ' . $margin . ';' . $extra . '">';
    }

    /**
     * Bloc logo centré pour en-têtes PDF.
     * @param array{align?: string, max_height?: int, max_width?: int, margin_bottom?: string} $opts
     */
    public function getPdfLogoBlockHtml(array $opts = []): string
    {
        $align = (string) ($opts['align'] ?? 'center');
        $marginBottom = (string) ($opts['margin_bottom'] ?? '10px');
        $img = $this->getPdfLogoHtml($opts);
        if ($img === '') {
            return '';
        }

        return '<div class="pdf-logo-block" style="text-align: ' . htmlspecialchars($align) . '; margin-bottom: ' . htmlspecialchars($marginBottom) . ';">' . $img . '</div>';
    }
}

