<?php
/**
 * Modèle RendezVous - Gestion des rendez-vous
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/staff_scope.php';

class RendezVous {
    private $pdo;

    private function scopeTenant(array &$where, array &$params, string $alias = 'rv'): void
    {
        TenantScope::appendWhere($this->pdo, 'rendez_vous', $where, $params, $alias);
    }

    private function scopeStaff(array &$where, array &$params, string $alias = 'rv'): void
    {
        StaffScope::appendRdvFilter($where, $params, $alias);
    }

    /** @return list<mixed> */
    private function tenantWhereParams(int $id): array
    {
        return array_merge([$id], TenantScope::ownedParam($this->pdo, 'rendez_vous'));
    }

    private function tenantAndClause(string $alias = ''): string
    {
        return TenantScope::andOwnedByTenant($this->pdo, 'rendez_vous', $alias);
    }
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Récupérer tous les rendez-vous avec pagination
     */
    public function getAll($page = 1, $limit = 10, $search = '', $statut = '', $date = '') {
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR m.nom LIKE ? OR m.prenom LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($statut)) {
            $where[] = "rv.statut = ?";
            $params[] = $statut;
        } else {
            // Masquer les rendez-vous supprimés (soft delete) sauf filtre explicite
            $where[] = "rv.statut != 'supprime'";
        }
        
        if (!empty($date)) {
            $where[] = "DATE(rv.date_rdv) = ?";
            $params[] = $date;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
                // Utiliser LIMIT avec offset intégré pour éviter les problèmes de paramètres
        $sql = "SELECT rv.*,
                                   p.nom as patient_nom, p.prenom as patient_prenom,
                                   m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                             FROM rendez_vous rv
                             LEFT JOIN patients p ON rv.patient_id = p.id
                             LEFT JOIN medecins m ON rv.medecin_id = m.id
                             $whereClause
                             ORDER BY rv.date_rdv DESC
                             LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Compter le nombre total de rendez-vous
     */
    public function getCount($search = '', $statut = '', $date = '') {
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR m.nom LIKE ? OR m.prenom LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($statut)) {
            $where[] = "rv.statut = ?";
            $params[] = $statut;
        } else {
            $where[] = "rv.statut != 'supprime'";
        }
        
        if (!empty($date)) {
            $where[] = "DATE(rv.date_rdv) = ?";
            $params[] = $date;
        }

        $this->scopeTenant($where, $params);
        $this->scopeStaff($where, $params);
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total 
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Récupérer un rendez-vous par ID
     */
    public function getById($id) {
        $sql = "SELECT rv.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE rv.id = ?" . TenantScope::andOwnedByTenant($this->pdo, 'rendez_vous', 'rv');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$id], TenantScope::ownedParam($this->pdo, 'rendez_vous')));
        $row = $stmt->fetch();
        if ($row && !StaffScope::canAccessRdv($row)) {
            return false;
        }
        return $row;
    }
    
    /**
     * Créer un nouveau rendez-vous
     */
    public function create($data) {
        $columns = [
            'patient_id', 'medecin_id', 'date_rdv', 'heure_rdv', 'motif', 'notes', 'statut',
            'recurrence', 'date_fin_recurrence', 'notification_sms', 'notification_email', 'date_creation',
        ];
        $placeholders = array_fill(0, count($columns) - 1, '?');
        $placeholders[] = 'NOW()';
        $values = [
            $data['patient_id'],
            $data['medecin_id'],
            $data['date_rdv'],
            $data['heure_rdv'],
            $data['motif'] ?? null,
            $data['notes'] ?? null,
            $data['statut'] ?? 'planifie',
            $data['recurrence'] ?? 'aucune',
            $data['date_fin_recurrence'] ?? null,
            $data['notification_sms'] ?? 0,
            $data['notification_email'] ?? 0,
        ];
        TenantScope::bindInsert($this->pdo, 'rendez_vous', $columns, $placeholders, $values);
        $sql = 'INSERT INTO rendez_vous (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Récupérer les rendez-vous par plage de dates (pour le calendrier)
     */
    public function getByDateRange($start, $end) {
        $where = [
            'DATE(rv.date_rdv) BETWEEN ? AND ?',
            "rv.statut != 'supprime'",
        ];
        $params = [$start, $end];
        $this->scopeTenant($where, $params);
        $sql = "SELECT rv.*,
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY rv.date_rdv, rv.heure_rdv';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les rendez-vous d'un mois
     */
    public function getByMonth($month, $year) {
        $where = [
            'MONTH(rv.date_rdv) = ?',
            'YEAR(rv.date_rdv) = ?',
            "rv.statut != 'supprime'",
        ];
        $params = [$month, $year];
        $this->scopeTenant($where, $params);
        $sql = "SELECT rv.*,
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY rv.date_rdv, rv.heure_rdv';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Envoyer les notifications de rappel
     */
    public function sendReminders() {
        // Rendez-vous dans les 24 prochaines heures
        $sql = "SELECT rv.*, p.nom as patient_nom, p.prenom as patient_prenom, p.telephone, p.email,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE rv.statut IN ('planifie', 'confirme')
                AND DATE(rv.date_rdv) = CURDATE()
                AND rv.notification_sms = 1 OR rv.notification_email = 1";
        
        $stmt = $this->pdo->query($sql);
        $rdvs = $stmt->fetchAll();
        
        $sent = 0;
        foreach ($rdvs as $rdv) {
            if ($rdv['notification_sms'] && $rdv['telephone']) {
                // TODO: Implémenter l'envoi SMS (Twilio, Orange SMS, etc.)
                $sent++;
            }
            
            if ($rdv['notification_email'] && $rdv['email']) {
                // TODO: Implémenter l'envoi Email
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Mettre à jour un rendez-vous
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        // Construire dynamiquement la requête SQL selon les champs fournis
        if (isset($data['patient_id'])) {
            $fields[] = "patient_id = ?";
            $values[] = $data['patient_id'];
        }
        if (isset($data['medecin_id'])) {
            $fields[] = "medecin_id = ?";
            $values[] = $data['medecin_id'];
        }
        if (isset($data['date_rdv'])) {
            $fields[] = "date_rdv = ?";
            $values[] = $data['date_rdv'];
        }
        if (isset($data['heure_rdv'])) {
            $fields[] = "heure_rdv = ?";
            $values[] = $data['heure_rdv'];
        }
        if (isset($data['motif'])) {
            $fields[] = "motif = ?";
            $values[] = $data['motif'];
        }
        if (isset($data['notes'])) {
            $fields[] = "notes = ?";
            $values[] = $data['notes'];
        }
        if (isset($data['statut'])) {
            $fields[] = "statut = ?";
            $values[] = $data['statut'];
        }
        
        // Date de modification supprimée car colonne inexistante
        
        // Ajouter l'ID à la fin pour la clause WHERE
        $values[] = $id;
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = 'UPDATE rendez_vous SET ' . implode(', ', $fields) . ' WHERE id = ?' . $this->tenantAndClause();
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge($values, TenantScope::ownedParam($this->pdo, 'rendez_vous')));
    }
    
    /**
     * Supprimer un rendez-vous (suppression logique)
     * Marque le rendez-vous comme supprimé au lieu de le supprimer physiquement
     */
    public function delete($id) {
        try {
            // Marquer le rendez-vous comme supprimé (soft delete)
            $stmt = $this->pdo->prepare(
                "UPDATE rendez_vous SET statut = 'supprime' WHERE id = ?" . $this->tenantAndClause()
            );
            $result = $stmt->execute($this->tenantWhereParams($id));
            
            // Invalider le cache du dashboard pour mettre à jour les compteurs
            if ($result) {
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) {
                    // Ignorer les erreurs de cache, la suppression a réussi
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du rendez-vous ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suppression physique d'un rendez-vous (hard delete)
     * ATTENTION: Cette méthode est irréversible !
     */
    public function hardDelete($id) {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM rendez_vous WHERE id = ?' . $this->tenantAndClause()
            );
            $result = $stmt->execute($this->tenantWhereParams($id));
            
            // Invalider le cache
            if ($result) {
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) {
                    // Ignorer
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression physique du rendez-vous ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupérer les rendez-vous du jour
     */
    public function getToday() {
        $where = [
            'DATE(rv.date_rdv) = CURDATE()',
            "rv.statut != 'supprime'",
        ];
        $params = [];
        $this->scopeTenant($where, $params);

        $sql = "SELECT rv.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY rv.heure_rdv ASC';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les rendez-vous de la semaine
     */
    public function getWeek() {
        $where = [
            'rv.date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            "rv.statut != 'supprime'",
        ];
        $params = [];
        $this->scopeTenant($where, $params);

        $sql = "SELECT rv.*, 
                       p.nom as patient_nom, p.prenom as patient_prenom,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN patients p ON rv.patient_id = p.id
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY rv.date_rdv ASC, rv.heure_rdv ASC';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les statistiques des rendez-vous
     */
    public function getStats() {
        $pdo = $this->pdo;
        $stats = [];

        $stats['total'] = TenantScope::count($pdo, 'rendez_vous', ["statut != 'supprime'"]);

        $where = ["statut != 'supprime'"];
        $params = [];
        TenantScope::appendWhere($pdo, 'rendez_vous', $where, $params);
        $stmt = $pdo->prepare('SELECT statut, COUNT(*) as count FROM rendez_vous WHERE ' . implode(' AND ', $where) . ' GROUP BY statut');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }

        $stats['supprime'] = TenantScope::count($pdo, 'rendez_vous', ["statut = 'supprime'"]);
        $stats['aujourd_hui'] = TenantScope::count($pdo, 'rendez_vous', ["DATE(date_rdv) = CURDATE()", "statut != 'supprime'"]);
        $stats['cette_semaine'] = TenantScope::count($pdo, 'rendez_vous', [
            "date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            "statut != 'supprime'",
        ]);

        return $stats;
    }
    
    /**
     * Vérifier la disponibilité d'un créneau
     */
    public function checkAvailability($medecin_id, $date, $heure, $exclude_id = null) {
        $where = [
            'rv.medecin_id = ?',
            'rv.date_rdv = ?',
            'rv.heure_rdv = ?',
            "rv.statut NOT IN ('annule', 'supprime')",
        ];
        $params = [$medecin_id, $date, $heure];

        if ($exclude_id) {
            $where[] = 'rv.id != ?';
            $params[] = $exclude_id;
        }

        $this->scopeTenant($where, $params);

        $sql = 'SELECT COUNT(*) as count FROM rendez_vous rv WHERE ' . implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0) === 0;
    }
    
    /**
     * Confirmer un rendez-vous
     */
    public function confirmer($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE rendez_vous SET statut = 'confirme' WHERE id = ?" . $this->tenantAndClause()
        );
        return $stmt->execute($this->tenantWhereParams($id));
    }
    
    /**
     * Annuler un rendez-vous
     */
    public function annuler($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE rendez_vous SET statut = 'annule' WHERE id = ?" . $this->tenantAndClause()
        );
        return $stmt->execute($this->tenantWhereParams($id));
    }
    
    /**
     * Terminer un rendez-vous
     */
    public function terminer($id) {
        $stmt = $this->pdo->prepare(
            "UPDATE rendez_vous SET statut = 'termine' WHERE id = ?" . $this->tenantAndClause()
        );
        return $stmt->execute($this->tenantWhereParams($id));
    }
    
    /**
     * Récupérer tous les rendez-vous d'un patient
     */
    public function getPatientRendezVous($patient_id, $limit = null) {
        $where = [
            'rv.patient_id = ?',
            "rv.statut != 'supprime'",
        ];
        $params = [$patient_id];
        $this->scopeTenant($where, $params);

        $sql = "SELECT rv.*, 
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite as medecin_specialite, m.type_profil as medecin_type_profil
                FROM rendez_vous rv
                LEFT JOIN medecins m ON rv.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY rv.date_rdv DESC, rv.heure_rdv DESC';
        
        if ($limit) {
            $limit = (int) $limit;
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>
