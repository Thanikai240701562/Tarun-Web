<?php
/**
 * TeamForge College Events API
 * Handles event listings, registration, and creation
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'register':
        $eventId = (int)($input['event_id'] ?? 0);
        if ($eventId <= 0) jsonError('Invalid event ID.');

        if (isDbAvailable()) {
            $db = getDB();
            $checkStmt = $db->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $checkStmt->execute([$eventId, $userId]);
            if ($checkStmt->fetch()) {
                jsonError('You are already registered for this event.');
            }

            $stmt = $db->prepare("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, 'registered')");
            $stmt->execute([$eventId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Successfully registered for this college event!']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Successfully registered (Demo Mode)!']);
        }
        break;

    case 'unregister':
        $eventId = (int)($input['event_id'] ?? 0);
        if ($eventId <= 0) jsonError('Invalid event ID.');

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Registration cancelled.']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Registration cancelled (Demo Mode).']);
        }
        break;

    case 'create':
        $title = sanitize($input['title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $department = sanitize($input['department'] ?? 'Computer Science & Engineering');
        $eventDate = sanitize($input['event_date'] ?? '');
        $deadline = sanitize($input['registration_deadline'] ?? '');
        $minSize = (int)($input['min_team_size'] ?? 3);
        $maxSize = (int)($input['max_team_size'] ?? 5);

        if (empty($title) || empty($eventDate)) {
            jsonError('Event title and date are required.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO events (title, description, department, event_date, registration_deadline, min_team_size, max_team_size, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$title, $description, $department, $eventDate, $deadline, $minSize, $maxSize, $userId]);
            jsonResponse(['success' => true, 'message' => 'College event created successfully!']);
        } else {
            jsonResponse(['success' => true, 'message' => 'College event created successfully (Demo Mode)!']);
        }
        break;

    case 'view':
    case 'details':
        $eventId = (int)($_GET['id'] ?? 0);
        if ($eventId <= 0) jsonError('Invalid event ID.');

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            if (!$event) jsonError('Event not found.', 404);

            // Check if current user is registered
            $regStmt = $db->prepare("SELECT status FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $regStmt->execute([$eventId, $userId]);
            $reg = $regStmt->fetch();
            $event['is_registered'] = !empty($reg);

            // Get registered participants
            $partStmt = $db->prepare("
                SELECT u.id, u.full_name, u.email, u.department, er.created_at as registered_at
                FROM event_registrations er
                JOIN users u ON er.user_id = u.id
                WHERE er.event_id = ? AND er.status != 'cancelled'
            ");
            $partStmt->execute([$eventId]);
            $event['participants'] = $partStmt->fetchAll();

            jsonResponse(['success' => true, 'event' => $event]);
        } else {
            $mockEvents = getMockEvents();
            $event = null;
            foreach ($mockEvents as $e) {
                if ($e['id'] === $eventId) {
                    $event = $e;
                    break;
                }
            }
            if (!$event) $event = $mockEvents[0];
            $event['is_registered'] = true;
            $event['participants'] = getMockUsers();
            jsonResponse(['success' => true, 'is_mock' => true, 'event' => $event]);
        }
        break;

    case 'list':
    default:
        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->query("
                SELECT e.*, 
                    (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as participant_count,
                    (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = " . (int)$userId . " AND status != 'cancelled') as is_registered
                FROM events e
                ORDER BY e.event_date ASC
            ");
            $events = $stmt->fetchAll();
            jsonResponse(['success' => true, 'events' => $events]);
        } else {
            $mock = getMockEvents();
            foreach ($mock as &$ev) {
                $ev['is_registered'] = ($ev['id'] === 1);
            }
            jsonResponse(['success' => true, 'is_mock' => true, 'events' => $mock]);
        }
        break;
}
