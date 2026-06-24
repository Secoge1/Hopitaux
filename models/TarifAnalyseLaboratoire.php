<?php
/**
 * Tarifs / types d'analyses laboratoire (configurables par établissement).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';

class TarifAnalyseLaboratoire
{
    /** Valeurs par défaut (première initialisation tenant). */
    public const DEFAULT_TYPES = [
        'sang'          => ['libelle' => 'Analyse sanguine',   'prix' => 5000.00,  'ordre' => 10],
        'urine'         => ['libelle' => 'Analyse d\'urine',   'prix' => 3000.00,  'ordre' => 20],
        'imagerie'      => ['libelle' => 'Imagerie médicale',  'prix' => 15000.00, 'ordre' => 30],
        'specialisee'   => ['libelle' => 'Test spécialisé',    'prix' => 25000.00, 'ordre' => 40],
        'microbiologie' => ['libelle' => 'Microbiologie',      'prix' => 8000.00,  'ordre' => 50],
        'biochimie'     => ['libelle' => 'Biochimie',          'prix' => 6000.00,  'ordre' => 60],
        'hematologie'   => ['libelle' => 'Hématologie',        'prix' => 4000.00,  'ordre' => 70],
        'immunologie'   => ['libelle' => 'Immunologie',        'prix' => 12000.00, 'ordre' => 80],
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDB();
        self::ensureTable($this->pdo);
        self::ensureDefaultsForCurrentTenant($this->pdo);
    }

    public static function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tarifs_analyses_laboratoire (
                id INT NOT NULL AUTO_INCREMENT,
                tenant_id INT DEFAULT NULL,
                code VARCHAR(64) NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                prix DECIMAL(12,2) NOT NULL DEFAULT 0,
                description TEXT DEFAULT NULL,
                ordre INT NOT NULL DEFAULT 0,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                date_modification DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_tenant (tenant_id),
                KEY idx_statut (statut),
                UNIQUE KEY uk_tarif_analyse_code_tenant (code, tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';
        return trim($code, '_');
    }

    private function scopeTenant(array &$where, array &$params): void
    {
        TenantScope::appendWhere($this->pdo, 'tarifs_analyses_laboratoire', $where, $params);
    }

    public static function ensureDefaultsForCurrentTenant(PDO $pdo): void
    {
        TenantContext::bindFromSession();
        $tenantId = TenantScope::currentTenantId();
        if (!$tenantId) {
            return;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tarifs_analyses_laboratoire WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO tarifs_analyses_laboratoire (tenant_id, code, libelle, prix, ordre, statut)
             VALUES (?, ?, ?, ?, ?, \'actif\')'
        );
        foreach (self::DEFAULT_TYPES as $code => $row) {
            $ins->execute([$tenantId, $code, $row['libelle'], $row['prix'], (int) $row['ordre']]);
        }
    }

    /** @return array<string, string> code => libellé (actifs) */
    public static function getTypesMapForTenant(bool $actifsOnly = true): array
    {
        $pdo = getDB();
        self::ensureTable($pdo);
        self::ensureDefaultsForCurrentTenant($pdo);

        $where = [];
        $params = [];
        if ($actifsOnly) {
            $where[] = "statut = 'actif'";
        }
        TenantScope::appendWhere($pdo, 'tarifs_analyses_laboratoire', $where, $params);
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare(
            "SELECT code, libelle FROM tarifs_analyses_laboratoire $wc ORDER BY ordre ASC, libelle ASC"
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['code']] = $row['libelle'];
        }
        if ($map !== []) {
            return $map;
        }
        foreach (self::DEFAULT_TYPES as $code => $row) {
            $map[$code] = $row['libelle'];
        }
        return $map;
    }

    /** @return array<string, float> code => prix (actifs) */
    public static function getPrixMapForTenant(): array
    {
        $pdo = getDB();
        self::ensureTable($pdo);
        self::ensureDefaultsForCurrentTenant($pdo);

        $where = ["statut = 'actif'"];
        $params = [];
        TenantScope::appendWhere($pdo, 'tarifs_analyses_laboratoire', $where, $params);
        $stmt = $pdo->prepare(
            'SELECT code, prix FROM tarifs_analyses_laboratoire WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ordre ASC'
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['code']] = (float) $row['prix'];
        }
        if ($map !== []) {
            return $map;
        }
        foreach (self::DEFAULT_TYPES as $code => $row) {
            $map[$code] = (float) $row['prix'];
        }
        return $map;
    }

    public static function getPrixForCode(string $code): float
    {
        $code = self::normalizeCode($code);
        $map = self::getPrixMapForTenant();
        if (isset($map[$code])) {
            return (float) $map[$code];
        }
        return (float) (self::DEFAULT_TYPES[$code]['prix'] ?? 5000.00);
    }

    public function getAll(?string $statut = null): array
    {
        $where = [];
        $params = [];
        if ($statut) {
            $where[] = 'statut = ?';
            $params[] = $statut;
        }
        $this->scopeTenant($where, $params);
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tarifs_analyses_laboratoire $wc ORDER BY ordre ASC, libelle ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $sql = 'SELECT * FROM tarifs_analyses_laboratoire WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_analyses_laboratoire');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(TenantScope::paramsForId($this->pdo, 'tarifs_analyses_laboratoire', $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByCode(string $code): ?array
    {
        $code = self::normalizeCode($code);
        $where = ['code = ?'];
        $params = [$code];
        $this->scopeTenant($where, $params);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tarifs_analyses_laboratoire WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $code = self::normalizeCode($data['code'] ?? '');
        if ($code === '') {
            return false;
        }
        if ($this->getByCode($code)) {
            return false;
        }

        $columns = ['code', 'libelle', 'prix', 'description', 'ordre', 'statut'];
        $placeholders = ['?', '?', '?', '?', '?', '?'];
        $values = [
            $code,
            trim($data['libelle'] ?? ''),
            (float) ($data['prix'] ?? 0),
            $data['description'] ?? null,
            (int) ($data['ordre'] ?? 0),
            $data['statut'] ?? 'actif',
        ];
        TenantScope::bindInsert($this->pdo, 'tarifs_analyses_laboratoire', $columns, $placeholders, $values);
        $sql = 'INSERT INTO tarifs_analyses_laboratoire (' . implode(', ', $columns) . ', date_creation)
                VALUES (' . implode(', ', $placeholders) . ', NOW())';
        return $this->pdo->prepare($sql)->execute($values);
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->getById($id)) {
            return false;
        }
        $sql = 'UPDATE tarifs_analyses_laboratoire SET
                libelle = ?, prix = ?, description = ?, ordre = ?, statut = ?, date_modification = NOW()
                WHERE id = ?' . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_analyses_laboratoire');
        return $this->pdo->prepare($sql)->execute(TenantScope::appendOwned(
            $this->pdo,
            'tarifs_analyses_laboratoire',
            [
                trim($data['libelle'] ?? ''),
                (float) ($data['prix'] ?? 0),
                $data['description'] ?? null,
                (int) ($data['ordre'] ?? 0),
                $data['statut'] ?? 'actif',
                $id,
            ]
        ));
    }

    public function delete(int $id): bool
    {
        $row = $this->getById($id);
        if (!$row) {
            return false;
        }
        if ($this->countUsages($row['code']) > 0) {
            return false;
        }
        $del = $this->pdo->prepare(
            'DELETE FROM tarifs_analyses_laboratoire WHERE id = ?'
            . TenantScope::andOwnedByTenant($this->pdo, 'tarifs_analyses_laboratoire')
        );
        return $del->execute(TenantScope::paramsForId($this->pdo, 'tarifs_analyses_laboratoire', $id));
    }

    public function countUsages(string $code): int
    {
        $where = ['type_analyse = ?'];
        $params = [$code];
        TenantScope::appendWhere($this->pdo, 'analyses', $where, $params);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM analyses WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Base de suggestions IA pour un type configuré (hors catalogue codé en dur).
     *
     * @return array<string, mixed>
     */
    public static function buildSuggestionsBase(array $tarif): array
    {
        $libelle = trim((string) ($tarif['libelle'] ?? $tarif['code']));
        $base = [
            'title' => $libelle,
            'suggestions' => [
                'Analyses standard pour « ' . $libelle . ' »',
                'Bilan complémentaire selon le contexte clinique',
            ],
            'preparation' => 'À préciser selon le protocole du laboratoire',
            'delai' => 'Variable selon l\'analyse',
            'indications' => [
                'Selon le motif de consultation et les signes cliniques',
            ],
            'contraindications' => [
                'Informer l\'équipe de tout traitement ou antécédent pertinent',
            ],
            'custom_type' => true,
        ];
        $desc = trim((string) ($tarif['description'] ?? ''));
        if ($desc !== '') {
            $base['type_description'] = $desc;
        }
        return $base;
    }
}
