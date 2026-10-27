<?php
/**
 * TeamForge Authentication API
 * Handles login, registration, session status, and logout
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $email = sanitize($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            jsonError('Please enter both email and password.');
        }

        $dbOk = isDbAvailable();
        if ($dbOk) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                jsonResponse([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'department' => $user['department']
                    ],
                    'csrf_token' => csrf_token(),
                    'message' => 'Signed in successfully.'
                ]);
            }
        }

        // Demo fallback if MySQL is offline or password is demo
        $mockUsers = getMockUsers();
        foreach ($mockUsers as $mUser) {
            if (strtolower($mUser['email']) === strtolower($email) && ($password === 'Password123!' || !$dbOk)) {
                $_SESSION['user_id'] = $mUser['id'];
                $_SESSION['user_email'] = $mUser['email'];
                $_SESSION['user_name'] = $mUser['full_name'];
                $_SESSION['user_role'] = $mUser['role'];
                $_SESSION['logged_in_time'] = time();

                jsonResponse([
                    'success' => true,
                    'user' => $mUser,
                    'csrf_token' => csrf_token(),
                    'message' => 'Signed in successfully (Demo Mode).'
                ]);
            }
        }

        jsonError('Invalid college email or password.', 401);
        break;

    case 'register':
        $fullName = sanitize($input['full_name'] ?? '');
        $email = sanitize($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $department = sanitize($input['department'] ?? 'Computer Science & Engineering');
        $role = sanitize($input['role'] ?? ROLE_STUDENT);
        $bio = sanitize($input['bio'] ?? '');

        if (empty($fullName) || empty($email) || empty($password)) {
            jsonError('Please complete all required fields.');
        }

        if (strlen($password) < 6) {
            jsonError('Password must contain at least 6 characters.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                jsonError('An account with this email already exists.');
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO users (full_name, email, password_hash, role, department, bio, availability_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'available')
            ");
            $stmt->execute([$fullName, $email, $hash, $role, $department, $bio]);
            $userId = (int)$db->lastInsertId();

            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();
            loginUser($newUser);

            jsonResponse([
                'success' => true,
                'user' => $newUser,
                'csrf_token' => csrf_token(),
                'message' => 'Account registered successfully.'
            ]);
        } else {
            // Mock registration session
            $_SESSION['user_id'] = 999;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = $role;
            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => 999,
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role,
                    'department' => $department
                ],
                'csrf_token' => csrf_token(),
                'message' => 'Account registered successfully (Demo Mode).'
            ]);
        }
        break;

    case 'logout':
        logoutUser();
        jsonResponse(['success' => true, 'message' => 'Signed out successfully.']);
        break;

    case 'me':
    default:
        if (isLoggedIn()) {
            $user = currentUser();
            if (!$user && isset($_SESSION['user_id'])) {
                $user = [
                    'id' => $_SESSION['user_id'],
                    'full_name' => $_SESSION['user_name'] ?? 'User',
                    'email' => $_SESSION['user_email'] ?? '',
                    'role' => $_SESSION['user_role'] ?? 'student'
                ];
            }
            jsonResponse([
                'logged_in' => true,
                'user' => $user,
                'csrf_token' => csrf_token()
            ]);
        } else {
            jsonResponse([
                'logged_in' => false,
                'csrf_token' => csrf_token()
            ]);
        }
        break;
}
