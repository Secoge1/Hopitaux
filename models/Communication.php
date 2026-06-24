<?php
/**
 * Modèle Communication - Gestion de la communication interne
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

class Communication {

    private function scopeMessages(PDO $pdo, array &$where, array &$params, string $alias = 'm'): void
    {
        TenantScope::appendWhere($pdo, 'messages_internes', $where, $params, $alias);
    }

    private function scopeAnnonces(PDO $pdo, array &$where, array &$params, string $alias = 'a'): void
    {
        TenantScope::appendWhere($pdo, 'annonces', $where, $params, $alias);
    }
    
    public function getMessages($user_id, $page = 1, $limit = 20, $type = 'received') {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        
        if ($type === 'received') {
            $where = [
                '(m.destinataire_id = ? OR (m.destinataire_role IS NOT NULL AND m.destinataire_role = (SELECT role FROM utilisateurs WHERE id = ?)))',
                'm.archive = 0',
            ];
            $params = [$user_id, $user_id];
            $this->scopeMessages($pdo, $where, $params);
            $sql = "SELECT m.*, u.nom_utilisateur as expediteur_nom, u.email as expediteur_email
                    FROM messages_internes m
                    LEFT JOIN utilisateurs u ON m.expediteur_id = u.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY m.date_creation DESC
                    LIMIT $limit OFFSET $offset";
        } else {
            $where = ['m.expediteur_id = ?', 'm.archive = 0'];
            $params = [$user_id];
            $this->scopeMessages($pdo, $where, $params);
            $sql = "SELECT m.*, u.nom_utilisateur as destinataire_nom, u.email as destinataire_email
                    FROM messages_internes m
                    LEFT JOIN utilisateurs u ON m.destinataire_id = u.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY m.date_creation DESC
                    LIMIT $limit OFFSET $offset";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getMessageById($id, $user_id) {
        $pdo = getDB();
        $uWhere = ['id = ?'];
        $uParams = [$user_id];
        TenantScope::appendWhere($pdo, 'utilisateurs', $uWhere, $uParams);
        $stmt = $pdo->prepare('SELECT role FROM utilisateurs WHERE ' . implode(' AND ', $uWhere));
        $stmt->execute($uParams);
        $userRole = $stmt->fetchColumn();

        $where = [
            'm.id = ?',
            '(m.expediteur_id = ? OR m.destinataire_id = ? OR (m.destinataire_role IS NOT NULL AND m.destinataire_role = ?))',
        ];
        $params = [$id, $user_id, $user_id, $userRole];
        $this->scopeMessages($pdo, $where, $params);
        
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u1.nom_utilisateur as expediteur_nom, u1.email as expediteur_email,
                   u2.nom_utilisateur as destinataire_nom, u2.email as destinataire_email
            FROM messages_internes m
            LEFT JOIN utilisateurs u1 ON m.expediteur_id = u1.id
            LEFT JOIN utilisateurs u2 ON m.destinataire_id = u2.id
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function createMessage($data) {
        $pdo = getDB();

        $columns = ['expediteur_id', 'destinataire_id', 'destinataire_role', 'sujet', 'message', 'priorite'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['expediteur_id'],
            $data['destinataire_id'] ?? null,
            $data['destinataire_role'] ?? null,
            $data['sujet'],
            $data['message'],
            $data['priorite'] ?? 'normale',
        ];
        TenantScope::bindInsert($pdo, 'messages_internes', $columns, $placeholders, $values);
        $sql = 'INSERT INTO messages_internes (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $messageId = $pdo->lastInsertId();
            require_once __DIR__ . '/../includes/NotificationSystem.php';
            $notificationSystem = NotificationSystem::getInstance();
            $uWhere = ['id = ?'];
            $uParams = [$data['expediteur_id']];
            TenantScope::appendWhere($pdo, 'utilisateurs', $uWhere, $uParams);
            $stmt = $pdo->prepare('SELECT nom_utilisateur FROM utilisateurs WHERE ' . implode(' AND ', $uWhere));
            $stmt->execute($uParams);
            $expediteurNom = $stmt->fetchColumn() ?: 'Système';
            $typeNotification = ($data['priorite'] ?? 'normale') === 'urgente' ? 'urgent' : 'info';
            $lien = 'communication/voir_message.php?id=' . $messageId;
            
            if (!empty($data['destinataire_id'])) {
                $notificationSystem->createNotification(
                    $data['destinataire_id'],
                    $typeNotification,
                    'Nouveau message reçu',
                    "Vous avez reçu un message de {$expediteurNom}: " . substr($data['sujet'], 0, 50),
                    'communication',
                    $lien
                );
            } elseif (!empty($data['destinataire_role'])) {
                $notificationSystem->createNotificationForRole(
                    $data['destinataire_role'],
                    $typeNotification,
                    'Nouveau message reçu',
                    "Vous avez reçu un message de {$expediteurNom}: " . substr($data['sujet'], 0, 50),
                    'communication',
                    $lien
                );
            }
            
            return $messageId;
        }
        
        return false;
    }
    
    public function markAsRead($id, $user_id) {
        $pdo = getDB();
        $uWhere = ['id = ?'];
        $uParams = [$user_id];
        TenantScope::appendWhere($pdo, 'utilisateurs', $uWhere, $uParams);
        $stmt = $pdo->prepare('SELECT role FROM utilisateurs WHERE ' . implode(' AND ', $uWhere));
        $stmt->execute($uParams);
        $userRole = $stmt->fetchColumn();

        $where = [
            'id = ?',
            'lu = 0',
            '(destinataire_id = ? OR (destinataire_role IS NOT NULL AND destinataire_role = ?))',
        ];
        $params = [$id, $user_id, $userRole];
        $owned = TenantScope::ownedParam($pdo, 'messages_internes');
        if ($owned) {
            $where[] = 'tenant_id = ?';
            $params = array_merge($params, $owned);
        }
        $stmt = $pdo->prepare(
            'UPDATE messages_internes SET lu = 1, date_lecture = NOW() WHERE ' . implode(' AND ', $where)
        );
        return $stmt->execute($params);
    }
    
    public function getUnreadCount($user_id) {
        $pdo = getDB();
        $where = [
            '(destinataire_id = ? OR (destinataire_role IS NOT NULL AND destinataire_role = (SELECT role FROM utilisateurs WHERE id = ?)))',
            'lu = 0',
            'archive = 0',
        ];
        $params = [$user_id, $user_id];
        TenantScope::appendWhere($pdo, 'messages_internes', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages_internes WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? (int)$result['count'] : 0;
    }
    
    public function getAnnonces($role = null, $limit = 10) {
        $pdo = getDB();
        $where = ['a.actif = 1', '(a.date_fin IS NULL OR a.date_fin >= NOW())'];
        $params = [];
        if ($role) {
            $where[] = "(a.destinataires = 'tous' OR a.destinataires = ?)";
            $params[] = $role;
        }
        $this->scopeAnnonces($pdo, $where, $params);
        $sql = "SELECT a.*, u.nom_utilisateur as auteur
                FROM annonces a
                LEFT JOIN utilisateurs u ON a.cree_par = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.date_debut DESC
                LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createAnnonce($data) {
        $pdo = getDB();
        $columns = ['titre', 'contenu', 'type', 'destinataires', 'date_debut', 'date_fin', 'actif', 'cree_par'];
        $placeholders = array_fill(0, count($columns), '?');
        $values = [
            $data['titre'],
            $data['contenu'],
            $data['type'] ?? 'information',
            $data['destinataires'] ?? 'tous',
            $data['date_debut'],
            $data['date_fin'] ?? null,
            $data['actif'] ?? 1,
            $data['cree_par'],
        ];
        TenantScope::bindInsert($pdo, 'annonces', $columns, $placeholders, $values);
        $sql = 'INSERT INTO annonces (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        return $result ? $pdo->lastInsertId() : false;
    }
    
    public function getStats($user_id) {
        $pdo = getDB();
        $stats = [];

        $whereSent = ['expediteur_id = ?', 'archive = 0'];
        $paramsSent = [$user_id];
        TenantScope::appendWhere($pdo, 'messages_internes', $whereSent, $paramsSent);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages_internes WHERE ' . implode(' AND ', $whereSent));
        $stmt->execute($paramsSent);
        $stats['messages_envoyes'] = (int) ($stmt->fetch()['count'] ?? 0);

        $whereRecv = [
            '(destinataire_id = ? OR (destinataire_role IS NOT NULL AND destinataire_role = (SELECT role FROM utilisateurs WHERE id = ?)))',
            'archive = 0',
        ];
        $paramsRecv = [$user_id, $user_id];
        TenantScope::appendWhere($pdo, 'messages_internes', $whereRecv, $paramsRecv);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages_internes WHERE ' . implode(' AND ', $whereRecv));
        $stmt->execute($paramsRecv);
        $stats['messages_recus'] = (int) ($stmt->fetch()['count'] ?? 0);
        $stats['messages_non_lus'] = $this->getUnreadCount($user_id);

        $whereAnn = ['actif = 1', '(date_fin IS NULL OR date_fin >= NOW())'];
        $paramsAnn = [];
        TenantScope::appendWhere($pdo, 'annonces', $whereAnn, $paramsAnn);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM annonces WHERE ' . implode(' AND ', $whereAnn));
        $stmt->execute($paramsAnn);
        $stats['annonces_actives'] = (int) ($stmt->fetch()['count'] ?? 0);
        
        return $stats;
    }
    
    public function deleteMessage($id, $user_id) {
        $pdo = getDB();
        if (!$this->getMessageById($id, $user_id)) {
            return false;
        }
        $where = ['id = ?', 'archive = 0'];
        $params = [(int) $id];
        TenantScope::appendWhere($pdo, 'messages_internes', $where, $params);
        $stmt = $pdo->prepare('UPDATE messages_internes SET archive = 1 WHERE ' . implode(' AND ', $where));
        return $stmt->execute($params);
    }
}
