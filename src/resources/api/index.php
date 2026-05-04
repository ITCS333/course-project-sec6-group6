<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db          = getDBConnection();
$method      = $_SERVER['REQUEST_METHOD'];
$rawData     = file_get_contents('php://input');
$data        = json_decode($rawData, true) ?? [];
$action      = $_GET['action']      ?? null;
$id          = $_GET['id']          ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id  = $_GET['comment_id']  ?? null;


function getAllResources($db) {
    $sql    = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort  = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort) ? $_GET['sort'] : 'created_at';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}


function getResourceById($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(['success' => false, 'message' => 'Title and link are required.'], 400);
        return;
    }

    $title       = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : '';
    $link        = trim($data['link']);

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
        return;
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    sendResponse([
        'success' => true,
        'message' => 'Resource created successfully.',
        'id'      => (int) $db->lastInsertId()
    ], 201);
}


function updateResource($db, $data) {
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Resource ID is required.'], 400);
        return;
    }

    $check = $db->prepare("SELECT id, title, description, link FROM resources WHERE id = ?");
    $check->execute([$data['id']]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    $title       = isset($data['title'])       ? trim($data['title'])       : $existing['title'];
    $description = isset($data['description']) ? trim($data['description']) : $existing['description'];
    $link        = isset($data['link'])        ? trim($data['link'])        : $existing['link'];

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
        return;
    }

    $stmt = $db->prepare("UPDATE resources SET title = ?, description = ?, link = ? WHERE id = ?");
    $stmt->execute([$title, $description, $link, $data['id']]);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}


function deleteResource($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);

    sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
}


function getCommentsByResourceId($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }

    $stmt = $db->prepare(
        "SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([$resourceId]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
}


function createComment($db, $data) {
    if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
        sendResponse(['success' => false, 'message' => 'All fields are required.'], 400);
        return;
    }

    $resourceId = $data['resource_id'];

    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    $author = trim($data['author']);
    $text   = trim($data['text']);

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$resourceId, $author, $text]);

    sendResponse([
        'success' => true,
        'message' => 'Comment added successfully.',
        'id'      => (int) $db->lastInsertId()
    ], 201);
}


function deleteComment($db, $commentId) {
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID.'], 400);
        return;
    }

    $check = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $check->execute([$commentId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
        return;
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
}


try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error.'], 500);
}


function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return ['valid' => count($missing) === 0, 'missing' => $missing];
}
?>