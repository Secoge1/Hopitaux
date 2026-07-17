<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeAccounting.php';
require_once __DIR__ . '/PeAccountingEngine.php';
require_once __DIR__ . '/PeHisFinanceBridge.php';

class PeEmployee
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 30, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ["status != 'terminated'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ? OR job_title LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        $this->peScope($pdo, 'pe_employees', $where, $params);
        $sql = 'SELECT * FROM pe_employees WHERE ' . implode(' AND ', $where) . ' ORDER BY last_name, first_name LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $search = ''): int
    {
        $pdo = getDB();
        $where = ["status != 'terminated'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        $this->peScope($pdo, 'pe_employees', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_employees WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }

        $code = trim((string) ($data['employee_code'] ?? ''));
        if ($code === '') {
            $code = 'EMP-' . strtoupper(substr(uniqid(), -6));
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_employees (
                tenant_id, pharmacy_id, personnel_id, employee_code, first_name, last_name,
                job_title, department, phone, email, hire_date, salary_base, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $ok = $stmt->execute([
            $tenantId,
            (int) $data['pharmacy_id'],
            !empty($data['personnel_id']) ? (int) $data['personnel_id'] : null,
            $code,
            $data['first_name'],
            $data['last_name'],
            $data['job_title'] ?? null,
            $data['department'] ?? 'Pharmacie',
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['hire_date'] ?? date('Y-m-d'),
            (float) ($data['salary_base'] ?? 0),
        ]);

        if (!$ok) {
            return false;
        }
        $id = (int) $pdo->lastInsertId();
        $this->peAudit('employee_created', 'pe_employees', $id);
        return $id;
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_employees', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_employees WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (!$this->findById($id)) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE pe_employees SET
                first_name = ?, last_name = ?, job_title = ?, department = ?,
                phone = ?, email = ?, salary_base = ?, status = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $ok = $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['job_title'] ?? null,
            $data['department'] ?? 'Pharmacie',
            $data['phone'] ?? null,
            $data['email'] ?? null,
            (float) ($data['salary_base'] ?? 0),
            $data['status'] ?? 'active',
            $id,
            $this->peTenantId(),
        ]);
        if ($ok) {
            $this->peAudit('employee_updated', 'pe_employees', $id);
        }
        return $ok;
    }

    /** Import depuis personnel HIS. */
    public function importFromPersonnel(int $personnelId, int $pharmacyId)
    {
        require_once __DIR__ . '/../Personnel.php';
        $person = (new Personnel())->getById($personnelId);
        if (!$person) {
            return false;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM pe_employees WHERE personnel_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$personnelId, $this->peTenantId()]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        return $this->create([
            'pharmacy_id' => $pharmacyId,
            'personnel_id' => $personnelId,
            'employee_code' => $person['numero_employe'] ?? ('P-' . $personnelId),
            'first_name' => $person['prenom'] ?? '',
            'last_name' => $person['nom'] ?? '',
            'job_title' => $person['poste'] ?? null,
            'department' => $person['departement'] ?? 'Pharmacie',
            'phone' => $person['telephone'] ?? null,
            'email' => $person['email'] ?? null,
            'hire_date' => $person['date_embauche'] ?? date('Y-m-d'),
            'salary_base' => (float) ($person['salaire'] ?? 0),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function getActiveForPayroll(): array
    {
        $pdo = getDB();
        $where = ["status = 'active'"];
        $params = [];
        $this->peScope($pdo, 'pe_employees', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_employees WHERE ' . implode(' AND ', $where) . ' ORDER BY last_name ASC');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

class PePayroll
{
    use PeModelTrait;

    public function generateRun(int $pharmacyId, string $periodLabel, string $periodStart, string $periodEnd): array
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            throw new RuntimeException('Tenant invalide.');
        }

        $employeeModel = new PeEmployee();
        $employees = $employeeModel->getActiveForPayroll();
        if (empty($employees)) {
            throw new RuntimeException('Aucun employé actif.');
        }

        $pdo = getDB();
        $runNumber = 'PAIE-' . date('Ym') . '-' . strtoupper(substr(uniqid(), -4));

        $pdo->beginTransaction();
        try {
            $totalGross = 0.0;
            $totalNet = 0.0;
            $totalDed = 0.0;

            $stmt = $pdo->prepare("
                INSERT INTO pe_payroll_runs (
                    tenant_id, pharmacy_id, run_number, period_label, period_start, period_end, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $tenantId, $pharmacyId, $runNumber, $periodLabel, $periodStart, $periodEnd,
                $_SESSION['user_id'] ?? null,
            ]);
            $runId = (int) $pdo->lastInsertId();

            foreach ($employees as $emp) {
                $base = (float) $emp['salary_base'];
                $bonus = 0.0;
                $deductions = round($base * 0.05, 2);
                $net = round($base + $bonus - $deductions, 2);
                $totalGross += $base + $bonus;
                $totalDed += $deductions;
                $totalNet += $net;

                $name = trim($emp['first_name'] . ' ' . $emp['last_name']);
                $stmt = $pdo->prepare("
                    INSERT INTO pe_payroll_lines (tenant_id, payroll_run_id, employee_id, employee_name, base_salary, bonus, deductions, net_salary)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tenantId, $runId, $emp['id'], $name, $base, $bonus, $deductions, $net]);
            }

            $pdo->prepare('UPDATE pe_payroll_runs SET total_gross = ?, total_deductions = ?, total_net = ? WHERE id = ?')
                ->execute([$totalGross, $totalDed, $totalNet, $runId]);

            $pdo->commit();
            return ['id' => $runId, 'run_number' => $runNumber, 'total_net' => $totalNet];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function validateRun(int $runId): bool
    {
        $run = $this->findRun($runId);
        if (!$run || $run['status'] !== 'draft') {
            return false;
        }

        PeAccountingEngine::ensureSeed((int) $run['tenant_id']);
        self::ensurePayrollAccounts((int) $run['tenant_id']);

        $accounting = new PeAccounting();
        $accounting->createEntry(
            'OD',
            'Paie ' . $run['period_label'] . ' — charges',
            [
                ['account_number' => '641000', 'debit' => (float) $run['total_gross'], 'label' => 'Salaires bruts'],
                ['account_number' => '421000', 'credit' => (float) $run['total_net'], 'label' => 'Net à payer'],
                ['account_number' => '431000', 'credit' => (float) $run['total_deductions'], 'label' => 'Retenues sociales'],
            ],
            'payroll',
            $runId,
            (int) $run['pharmacy_id']
        );

        $pdo = getDB();
        $pdo->prepare("UPDATE pe_payroll_runs SET status = 'validated', validated_at = NOW() WHERE id = ?")
            ->execute([$runId]);

        return true;
    }

    public function findRun(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_payroll_runs', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_payroll_runs WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function getRuns(int $limit = 20): array
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_payroll_runs', $where, $params, 'r');
        $sql = 'SELECT r.* FROM pe_payroll_runs r WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getRunLines(int $runId): array
    {
        $pdo = getDB();
        $where = ['payroll_run_id = ?'];
        $params = [$runId];
        $this->peScope($pdo, 'pe_payroll_lines', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_payroll_lines WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function ensurePayrollAccounts(int $tenantId): void
    {
        $pdo = getDB();
        $extra = [
            ['641000', 'Salaires et appointements', 6, 'expense'],
            ['421000', 'Personnel — rémunérations dues', 4, 'liability'],
            ['431000', 'Organismes sociaux', 4, 'liability'],
        ];
        foreach ($extra as [$num, $label, $class, $type]) {
            $stmt = $pdo->prepare('SELECT 1 FROM pe_accounts WHERE tenant_id = ? AND account_number = ?');
            $stmt->execute([$tenantId, $num]);
            if (!$stmt->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO pe_accounts (tenant_id, account_number, account_label, account_class, account_type, is_system)
                    VALUES (?, ?, ?, ?, ?, 1)
                ")->execute([$tenantId, $num, $label, $class, $type]);
            }
        }
    }
}

class PeLeave
{
    use PeModelTrait;

    /** @param array<string, mixed> $data */
    public function create(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }

        $start = $data['start_date'];
        $end = $data['end_date'];
        $days = max(1, (int) ((strtotime($end) - strtotime($start)) / 86400) + 1);

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_leave_requests (tenant_id, employee_id, leave_type, start_date, end_date, days_count, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $ok = $stmt->execute([
            $tenantId,
            (int) $data['employee_id'],
            $data['leave_type'] ?? 'annual',
            $start,
            $end,
            $days,
            $data['reason'] ?? null,
        ]);
        return $ok ? (int) $pdo->lastInsertId() : false;
    }

    public function review(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_leave_requests', $where, $params);
        $stmt = $pdo->prepare("
            UPDATE pe_leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE " . implode(' AND ', $where)
        );
        return $stmt->execute(array_merge([$status, $_SESSION['user_id'] ?? null], $params));
    }

    /** @return list<array<string, mixed>> */
    public function getAll(string $status = ''): array
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_leave_requests', $where, $params, 'l');

        $sql = "
            SELECT l.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
            FROM pe_leave_requests l
            INNER JOIN pe_employees e ON e.id = l.employee_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        $pdo = getDB();
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return [];
        }
        $stats = [];
        foreach (['pending', 'approved'] as $st) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_leave_requests WHERE tenant_id = ? AND status = ?');
            $stmt->execute([$tenantId, $st]);
            $stats[$st] = (int) $stmt->fetchColumn();
        }
        return $stats;
    }
}
