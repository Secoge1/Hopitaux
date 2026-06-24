<?php
/**
 * Modèle Laboratoire — délègue au modèle Analyse (isolation tenant).
 */

require_once __DIR__ . '/Analyse.php';

class Laboratoire {
    private $conn;
    private Analyse $analyse;

    public function __construct($db) {
        $this->conn = $db;
        $this->analyse = new Analyse();
    }

    public function getAll() {
        return $this->analyse->getAll(1, 1000);
    }

    public function getById($id) {
        return $this->analyse->getById($id);
    }

    public function getByPatient($patientId) {
        return $this->analyse->getPatientAnalyses($patientId, 100);
    }

    public function create($data) {
        $id = $this->analyse->create([
            'patient_id' => $data['patient_id'],
            'medecin_id' => $data['medecin_id'] ?? null,
            'type_analyse' => $data['type_analyse'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'statut' => $data['statut'] ?? 'en_attente',
        ]);
        return $id !== false;
    }

    public function update($id, $data) {
        return $this->analyse->update($id, [
            'type_analyse' => $data['type_analyse'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'statut' => $data['statut'],
            'resultats' => $data['resultats'] ?? null,
        ]);
    }

    public function delete($id) {
        return $this->analyse->delete($id);
    }

    public function search($term) {
        return $this->analyse->getAll(1, 100, ['search' => $term]);
    }

    public function getCount() {
        return $this->analyse->count();
    }

    public function getRecent($limit = 5) {
        return $this->analyse->getAll(1, (int) $limit);
    }

    public function getByStatus($status) {
        return $this->analyse->getAll(1, 1000, ['statut' => $status]);
    }
}
