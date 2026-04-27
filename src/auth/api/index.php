<?php

session_start();


header('Content-Type: application/json');


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}


$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$email    = trim($data['email']);
$password = $data['password'];


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}


require_once __DIR__ . '/db.php';
$pdo = getDBConnection();


try {

    
    $sql = 'SELECT id, name, email, password, is_admin FROM users WHERE email = ?';

  
    $stmt = $pdo->prepare($sql);

    
    $stmt->execute([$email]);

    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

   
    if (!$user || !password_verify($password, $user['password'])) {

        
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

   

    
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin']   = $user['is_admin'];
    $_SESSION['logged_in']  = true;

    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user'    => [
            'id'       => (int) $user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'is_admin' => (int) $user['is_admin'],
        ],
    ]);
    exit;

} catch (PDOException $e) {

   
    error_log($e->getMessage());


    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
?>
