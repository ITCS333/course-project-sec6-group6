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

$db      = getDBConnection();
$method  = $_SERVER['REQUEST_METHOD'];
$raw     = file_get_contents('php://input');
$data    = json_decode($raw, true) ?? [];
$id      = isset($_GET['id'])       ? (int) $_GET['id']    : null;
$action  = isset($_GET['action'])   ? trim($_GET['action']) : null;
$search  = isset($_GET['search'])   ? trim($_GET['search']) : null;
$topicId = isset($_GET['topic_id']) ? (int) $_GET['topic_id'] : null;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo $statusCode < 400
        ? json_encode(['success' => true,  'data'    => $data])
        : json_encode(['success' => false, 'message' => $data]);
    exit;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function getAllTopics($db) {
    global $search;
    $sql    = 'SELECT id, subject, message, author, created_at FROM topics';
    $params = [];

    if (!empty($search)) {
        $sql .= ' WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowed = ['subject', 'author', 'created_at'];
    $sort    = isset($_GET['sort']) && in_array($_GET['sort'], $allowed, true) ? $_GET['sort'] : 'created_at';
    $order   = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 200);
}

function getTopicById($db, $id) {
    if (!$id || !is_numeric($id)) sendResponse('id is required.', 400);

    $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$topic) sendResponse('Topic not found.', 404);
    sendResponse($topic, 200);
}

function createTopic($db, $data) {
    if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) {
        sendResponse('subject, message and author are required.', 400);
    }

    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $author  = sanitizeInput($data['author']);

    $stmt = $db->prepare('INSERT INTO topics (subject, message, author) VALUES (:subject, :message, :author)');
    $stmt->execute([':subject' => $subject, ':message' => $message, ':author' => $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $newId, 'data' => ['id' => $newId]]);
        exit;
    }
    sendResponse('Failed to create topic.', 500);
}

function updateTopic($db, $data) {
    if (empty($data['id'])) sendResponse('id is required.', 400);
    $id = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM topics WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse('Topic not found.', 404);

    $fields = [];
    $params = [];

    if (isset($data['subject'])) {
        $fields[]           = 'subject = :subject';
        $params[':subject'] = sanitizeInput($data['subject']);
    }
    if (isset($data['message'])) {
        $fields[]           = 'message = :message';
        $params[':message'] = sanitizeInput($data['message']);
    }

    if (empty($fields)) sendResponse('Nothing to update.', 400);

    $params[':id'] = $id;
    $stmt = $db->prepare('UPDATE topics SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $ok   = $stmt->execute($params);

    if ($ok) sendResponse('Topic updated successfully.', 200);
    sendResponse('Failed to update topic.', 500);
}

function deleteTopic($db, $id) {
    if (!$id || !is_numeric($id)) sendResponse('id is required.', 400);

    $check = $db->prepare('SELECT id FROM topics WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse('Topic not found.', 404);

    $stmt = $db->prepare('DELETE FROM topics WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) sendResponse('Topic deleted successfully.', 200);
    sendResponse('Failed to delete topic.', 500);
}

function getRepliesByTopicId($db, $topicId) {
    if (!$topicId || !is_numeric($topicId)) sendResponse('topic_id is required.', 400);

    $stmt = $db->prepare(
        'SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = :topic_id ORDER BY created_at ASC'
    );
    $stmt->execute([':topic_id' => $topicId]);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 200);
}

function createReply($db, $data) {
    if (empty($data['topic_id'])) sendResponse('topic_id is required.', 400);
    if (empty($data['text']))     sendResponse('text is required.', 400);
    if (empty($data['author']))   sendResponse('author is required.', 400);

    $topicId = (int) $data['topic_id'];
    $text    = sanitizeInput($data['text']);
    $author  = sanitizeInput($data['author']);

    $check = $db->prepare('SELECT id FROM topics WHERE id = :id');
    $check->execute([':id' => $topicId]);
    if (!$check->fetch()) sendResponse('Topic not found.', 404);

    $stmt = $db->prepare('INSERT INTO replies (topic_id, text, author) VALUES (:topic_id, :text, :author)');
    $stmt->execute([':topic_id' => $topicId, ':text' => $text, ':author' => $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();
        $rep   = $db->prepare('SELECT id, topic_id, text, author, created_at FROM replies WHERE id = :id');
        $rep->execute([':id' => $newId]);
        $reply = $rep->fetch(PDO::FETCH_ASSOC);
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $newId, 'data' => $reply]);
        exit;
    }
    sendResponse('Failed to create reply.', 500);
}

function deleteReply($db, $id) {
    if (!$id || !is_numeric($id)) sendResponse('id is required.', 400);

    $check = $db->prepare('SELECT id FROM replies WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse('Reply not found.', 404);

    $stmt = $db->prepare('DELETE FROM replies WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) sendResponse('Reply deleted successfully.', 200);
    sendResponse('Failed to delete reply.', 500);
}

try {

    if ($method === 'GET') {
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif (!empty($id)) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }

    } elseif ($method === 'POST') {
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }

    } elseif ($method === 'PUT') {
        updateTopic($db, $data);

    } elseif ($method === 'DELETE') {
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }

    } else {
        sendResponse('Method not allowed.', 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse('A server error occurred. Please try again.', 500);
}
?>
