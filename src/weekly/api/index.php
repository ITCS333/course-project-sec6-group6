 <?php
require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  
exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();


$method = $_SERVER['REQUEST_METHOD'];

function sendJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($method === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'comments') {
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

            $week['links'] = json_decode($week['links'] ?? '[]', true);
            sendJson(["success" => true, "data" => $week]);
        }

        $stmt = $db->query("SELECT * FROM weeks ORDER BY start_date ASC");
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($weeks as &$week) {
            $week['links'] = json_decode($week['links'] ?? '[]', true);
        }

        sendJson(["success" => true, "data" => $weeks]);
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (isset($_GET['action']) && $_GET['action'] === 'comment') {
            $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['week_id'],
                $data['author'],
                $data['text']
            ]);

            $id = $db->lastInsertId();

            sendJson([
                "success" => true,
                "id" => $id,
                "data" => [
                    "id" => (int)$id,
                    "week_id" => (int)$data['week_id'],
                    "author" => $data['author'],
                    "text" => $data['text']
                ]
            ]);
        }

        $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['start_date'],
            $data['description'] ?? '',
            json_encode($data['links'] ?? [])
        ]);

        sendJson([
            "success" => true,
            "id" => (int)$db->lastInsertId()
        ]);
    }

    if ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $db->prepare("UPDATE weeks SET title = ?, start_date = ?, description = ?, links = ? WHERE id = ?");
        $stmt->execute([
            $data['title'],
            $data['start_date'],
            $data['description'] ?? '',
            json_encode($data['links'] ?? []),
            $data['id']
        ]);

        sendJson(["success" => true]);
    }

    if ($method === 'DELETE') {
        if (isset($_GET['action']) && $_GET['action'] === 'delete_comment') {
            $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
            $stmt->execute([$_GET['comment_id']]);

            sendJson(["success" => true]);
        }

        $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        sendJson(["success" => true]);
    

    sendJson(["success" => false, "message" => "Method not allowed"], 405);

} catch (Exception $e) {
    sendJson(["success" => false, "message" => $e->getMessage()], 500);
}
