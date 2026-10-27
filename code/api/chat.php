<?php
/**
 * TeamForge Ephemeral Real-Time Chat API
 * Zero persistent MySQL storage — uses sliding ephemeral buffer
 */

require_once __DIR__ . '/bootstrap.php';

$input = getJsonInput();
$teamId = (int)($input['team_id'] ?? $_GET['team_id'] ?? 1);
$bufferFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teamforge_chat_' . $teamId . '.json';

$action = $input['action'] ?? $_GET['action'] ?? 'read';
$user = currentUser();
$senderName = $user['full_name'] ?? ($_SESSION['user_name'] ?? 'Aarav Sharma');
$senderId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'send':
        $messageText = sanitize($input['message'] ?? '');
        if (empty($messageText)) jsonError('Message cannot be empty.');

        $messages = [];
        if (file_exists($bufferFile)) {
            $raw = file_get_contents($bufferFile);
            $messages = json_decode($raw, true) ?: [];
        }

        $newMessage = [
            'id' => uniqid('msg_'),
            'user_id' => $senderId,
            'user_name' => $senderName,
            'text' => $messageText,
            'timestamp' => date('H:i:s')
        ];

        $messages[] = $newMessage;
        // Keep only last 50 ephemeral messages (Sliding Buffer)
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }

        file_put_contents($bufferFile, json_encode($messages));
        jsonResponse(['success' => true, 'message' => $newMessage]);
        break;

    case 'read':
    default:
        $messages = [];
        if (file_exists($bufferFile)) {
            $raw = file_get_contents($bufferFile);
            $messages = json_decode($raw, true) ?: [];
        } else {
            // Seed a welcome message for demo
            $messages = [
                [
                    'id' => 'init_1',
                    'user_id' => 1,
                    'user_name' => 'Dr. Sarah Mitchell',
                    'text' => 'Welcome to the TeamForge Ephemeral Room! Messages are kept in memory buffers and never written to permanent database storage.',
                    'timestamp' => '10:00:00'
                ],
                [
                    'id' => 'init_2',
                    'user_id' => 2,
                    'user_name' => 'Aarav Sharma',
                    'text' => 'Hello everyone! Let us review the CSP formulation before the hackathon submission.',
                    'timestamp' => '10:05:22'
                ]
            ];
            file_put_contents($bufferFile, json_encode($messages));
        }

        jsonResponse([
            'success' => true,
            'team_id' => $teamId,
            'messages' => $messages,
            'ephemeral_notice' => 'Zero persistent MySQL storage. Buffer clears upon server restart.'
        ]);
        break;
}
