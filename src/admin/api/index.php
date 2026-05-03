<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];
$id     = isset($_GET['id'])     ? (int) $_GET['id']    : null;
$action = isset($_GET['action']) ? trim($_GET['action']) : null;

function getUsers($db) {
    $sql    = 'SELECT id, name, email, is_admin, created_at FROM users';
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= ' WHERE name LIKE :search OR email LIKE :search';
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $allowed = ['name', 'email', 'is_admin'];
    $sort    = isset($_GET['sort']) && in_array($_GET['sort'], $allowed, true) ? $_GET['sort'] : null;
    $order   = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

    if ($sort) {
        $sql .= " ORDER BY {$sort} {$order}";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 200);
}

function getUserById($db, $id) {
    $stmt = $db->prepare('SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) sendResponse('User not found.', 404);
    sendResponse($user, 200);
}

function createUser($db, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse('name, email and password are required.', 400);
    }

    $name     = sanitizeInput($data['name']);
    $email    = sanitizeInput($data['email']);
    $password = trim($data['password']);

    if (!validateEmail($email)) sendResponse('Invalid email format.', 400);
    if (strlen($password) < 8)  sendResponse('Password must be at least 8 characters.', 400);

    $dup = $db->prepare('SELECT id FROM users WHERE email = :email');
    $dup->execute([':email' => $email]);
    if ($dup->fetch()) sendResponse('Email already exists.', 409);

    $hash    = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = isset($data['is_admin']) && (int) $data['is_admin'] === 1 ? 1 : 0;

    $stmt = $db->prepare('INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)');
    $ok   = $stmt->execute([':name' => $name, ':email' => $email, ':password' => $hash, ':is_admin' => $isAdmin]);

    if ($ok) sendResponse(['id' => (int) $db->lastInsertId()], 201);
    sendResponse('Failed to create user.', 500);
}

function updateUser($db, $data) {
    if (empty($data['id'])) sendResponse('id is required.', 400);
    $id = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM users WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse('User not found.', 404);

    $fields = [];
    $params = [];

    if (isset($data['name'])) {
        $fields[]        = 'name = :name';
        $params[':name'] = sanitizeInput($data['name']);
    }
    if (isset($data['email'])) {
        $newEmail = sanitizeInput($data['email']);
        if (!validateEmail($newEmail)) sendResponse('Invalid email format.', 400);
        $dup = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $dup->execute([':email' => $newEmail, ':id' => $id]);
        if ($dup->fetch()) sendResponse('Email already in use.', 409);
        $fields[]         = 'email = :email';
        $params[':email'] = $newEmail;
    }
    if (isset($data['is_admin'])) {
        $fields[]            = 'is_admin = :is_admin';
        $params[':is_admin'] = (int) $data['is_admin'] === 1 ? 1 : 0;
    }

    if (empty($fields)) sendResponse('Nothing to update.', 400);

    $params[':id'] = $id;
    $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $ok   = $stmt->execute($params);

    if ($ok) sendResponse('User updated successfully.', 200);
    sendResponse('Failed to update user.', 500);
}

function deleteUser($db, $id) {
    if (!$id) sendResponse('id is required.', 400);

    $check = $db->prepare('SELECT id FROM users WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse('User not found.', 404);

    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $ok   = $stmt->execute([':id' => $id]);

    if ($ok) sendResponse('User deleted successfully.', 200);
    sendResponse('Failed to delete user.', 500);
}

function changePassword($db, $data) {
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse('id, current_password and new_password are required.', 400);
    }

    $id              = (int) $data['id'];
    $currentPassword = $data['current_password'];
    $newPassword     = $data['new_password'];

    if (strlen($newPassword) < 8) sendResponse('New password must be at least 8 characters.', 400);

    $stmt = $db->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) sendResponse('User not found.', 404);

    if (!password_verify($currentPassword, $user['password'])) {
        sendResponse('Current password is incorrect.', 401);
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
    $ok   = $stmt->execute([':password' => $hash, ':id' => $id]);

    if ($ok) sendResponse('Password changed successfully.', 200);
    sendResponse('Failed to change password.', 500);
}


try {
    if ($method === 'GET') {
        !empty($id) ? getUserById($db, $id) : getUsers($db);
    } elseif ($method === 'POST') {
        $action === 'change_password' ? changePassword($db, $data) : createUser($db, $data);
    } elseif ($method === 'PUT') {
        updateUser($db, $data);
    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);
    } else {
        sendResponse('Method not allowed.', 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo $statusCode < 400
        ? json_encode(['success' => true,  'data'    => $data])
        : json_encode(['success' => false, 'message' => $data]);
    exit;
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>
