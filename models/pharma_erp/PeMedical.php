<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeMedical
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getPrescriptions(int $page = 1, int $limit = 20, string $status = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_prescriptions', $where, $params, 'pr');

        $sql = "
            SELECT pr.*, pp.first_name, pp.last_name, pp.phone
            FROM pe_prescriptions pr
            LEFT JOIN pe_patients pp ON pp.id = pr.pe_patient_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function linkHisPatient(int $hisPatientId)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM pe_patients WHERE tenant_id = ? AND external_patient_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $hisPatientId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $stmt = $pdo->prepare('SELECT nom, prenom, telephone, date_naissance FROM patients WHERE id = ? LIMIT 1');
        $stmt->execute([$hisPatientId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO pe_patients (tenant_id, external_patient_id, first_name, last_name, phone, birth_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId,
            $hisPatientId,
            $p['prenom'] ?? '',
            $p['nom'] ?? '',
            $p['telephone'] ?? null,
            $p['date_naissance'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createPrescription(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pdo = getDB();
        $number = 'RX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_prescriptions (tenant_id, pharmacy_id, pe_patient_id, external_consultation_id, prescription_number, prescriber_name, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $tenantId,
                (int) $data['pharmacy_id'],
                !empty($data['pe_patient_id']) ? (int) $data['pe_patient_id'] : null,
                !empty($data['external_consultation_id']) ? (int) $data['external_consultation_id'] : null,
                $number,
                $data['prescriber_name'] ?? null,
                $data['notes'] ?? null,
            ]);
            $rxId = (int) $pdo->lastInsertId();
            foreach ($data['lines'] ?? [] as $line) {
                $pdo->prepare("
                    INSERT INTO pe_prescription_lines (tenant_id, prescription_id, product_id, product_name, quantity_prescribed, dosage_notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $tenantId,
                    $rxId,
                    !empty($line['product_id']) ? (int) $line['product_id'] : null,
                    $line['product_name'] ?? 'Médicament',
                    max(1, (int) ($line['quantity'] ?? 1)),
                    $line['dosage_notes'] ?? null,
                ]);
            }
            $pdo->commit();
            return $rxId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function searchHisPatients(string $search, int $limit = 15): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }
        $pdo = getDB();
        $term = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT id, nom, prenom, telephone, date_naissance
            FROM patients
            WHERE (nom LIKE ? OR prenom LIKE ? OR telephone LIKE ?)
            AND statut = 'actif'
            ORDER BY nom ASC
            LIMIT {$limit}
        ");
        $stmt->execute([$term, $term, $term]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
