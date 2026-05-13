<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

function sendJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

try {
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'comments') {
            $weekId = $_GET['week_id'] ?? null;
            $stmt = $db->prepare("SELECT * FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
            $stmt->execute([$weekId]);
            sendJson(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if (isset($_GET['id'])) {
            $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $week = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$week) {
                sendJson(["success" => false, "message" => "Week not found"], 404);
            }

            $week['links'] = json_decode($week['links'] ?? '[]', true) ?: [];
            sendJson(["success" => true, "data" => $week]);
        }

        $sql = "SELECT * FROM weeks";
        $params = [];

        if (!empty($_GET['search'])) {
            $sql .= " WHERE title LIKE ? OR description LIKE ?";
            $term = "%" . $_GET['search'] . "%";
            $params[] = $term;
            $params[] = $term;
        }

        $sort = $_GET['sort'] ?? 'start_date';
        if (!in_array($sort, ['title', 'start_date'])) {
            $sort = 'start_date';
        }

        $order = strtolower($_GET['order'] ?? 'asc');
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        $sql .= " ORDER BY $sort $order";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($weeks as &$week) {
            $week['links'] = json_decode($week['links'] ?? '[]', true) ?: [];
        }

        sendJson(["success" => true, "data" => $weeks]);
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true) ?: [];

        if (($_GET['action'] ?? '') === 'comment') {
            $weekId = $data['week_id'] ?? null;
            $author = trim($data['author'] ?? '');
            $text = trim($data['text'] ?? '');

            if (!$weekId || $author === '' || $text === '') {
                sendJson(["success" => false, "message" => "Missing fields"], 400);
            }

            $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
            $check->execute([$weekId]);
            if (!$check->fetch()) {
                sendJson(["success" => false, "message" => "Week not found"], 404);
            }

            $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
            $stmt->execute([$weekId, $author, $text]);

            $id = (int)$db->lastInsertId();
            sendJson([
                "success" => true,
                "id" => $id,
                "data" => [
                    "id" => $id,
                    "week_id" => (int)$weekId,
                    "author" => $author,
                    "text" => $text
                ]
            ], 201);
        }

        if (empty($data['title']) || empty($data['start_date'])) {
            sendJson(["success" => false, "message" => "Title and start_date required"], 400);
        }

        if (!validDate($data['start_date'])) {
            sendJson(["success" => false, "message" => "Invalid date"], 400);
        }

        $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($data['title']),
            $data['start_date'],
            $data['description'] ?? '',
            json_encode($data['links'] ?? [])
        ]);

        sendJson(["success" => true, "id" => (int)$db->lastInsertId()], 201);
    }

    if ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true) ?: [];

        if (empty($data['id'])) {
            sendJson(["success" => false, "message" => "Missing id"], 400);
        }

        $check = $db->prepare("SELECT * FROM weeks WHERE id = ?");
        $check->execute([$data['id']]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendJson(["success" => false, "message" => "Week not found"], 404);
        }

        if (isset($data['start_date']) && !validDate($data['start_date'])) {
            sendJson(["success" => false, "message" => "Invalid date"], 400);
        }

        $title = $data['title'] ?? $existing['title'];
        $startDate = $data['start_date'] ?? $existing['start_date'];
        $description = $data['description'] ?? $existing['description'];
        $links = isset($data['links']) ? json_encode($data['links']) : $existing['links'];

        $stmt = $db->prepare("UPDATE weeks SET title = ?, start_date = ?, description = ?, links = ? WHERE id = ?");
        $stmt->execute([$title, $startDate, $description, $links, $data['id']]);

        sendJson(["success" => true]);
    }

    if ($method === 'DELETE') {
        if (($_GET['action'] ?? '') === 'delete_comment') {
            $commentId = $_GET['comment_id'] ?? null;

            $check = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
            $check->execute([$commentId]);
            if (!$check->fetch()) {
                sendJson(["success" => false, "message" => "Comment not found"], 404);
            }

            $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
            $stmt->execute([$commentId]);
            sendJson(["success" => true]);
        }

        $id = $_GET['id'] ?? null;

        $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            sendJson(["success" => false, "message" => "Week not found"], 404);
        }

        $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
        $stmt->execute([$id]);
        sendJson(["success" => true]);
    }

    sendJson(["success" => false, "message" => "Method not allowed"], 405);

} catch (Exception $e) {
    sendJson(["success" => false, "message" => $e->getMessage()], 500);
}
