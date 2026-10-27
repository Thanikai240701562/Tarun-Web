<?php
/**
 * TeamForge Notifications API
 */

require_once __DIR__ . '/bootstrap.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'mark_read':
        $notifId = (int)($input['id'] ?? 0);
        if (isDbAvailable()) {
            $db = getDB();
            if ($notifId > 0) {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notifId, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
        }
        jsonResponse(['success' => true, 'message' => 'Notification marked as read.']);
        break;

    case 'list':
    default:
        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$userId]);
            $notifs = $stmt->fetchAll();
            jsonResponse(['success' => true, 'notifications' => $notifs]);
        } else {
            $notifs = [
                [
                    'id' => 1,
                    'title' => 'Team Invitation Received',
                    'message' => 'Priya Patel invited you to join AlgoKnights as Backend Optimization Lead.',
                    'link' => 'teams.html',
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ],
                [
                    'id' => 2,
                    'title' => 'New Event Published',
                    'message' => 'Dr. Sarah Mitchell published Inter-College AI & Web Hackathon 2026.',
                    'link' => 'events.html',
                    'is_read' => 1,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ]
            ];
            jsonResponse(['success' => true, 'is_mock' => true, 'notifications' => $notifs]);
        }
        break;
}
