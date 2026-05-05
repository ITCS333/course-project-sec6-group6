<?php
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
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the shared database connection file.
require_once __DIR__ . '/../common/db.php';

// Get the PDO database connection.
$db = getDBConnection();

// Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];

// Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Read query parameters.
$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;


// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 */
function getAllWeeks(PDO $db): void
{
    // Build the base SELECT query.
    $sql = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    // If search is provided, append WHERE clause.
    $search = $_GET['search'] ?? null;
    if ($search && trim($search) !== '') {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    // Validate sort against whitelist [title, start_date]. Default: start_date.
    $allowedSort = ['title', 'start_date'];
    $sort = $_GET['sort'] ?? 'start_date';
    if (!in_array($sort, $allowedSort)) {
        $sort = 'start_date';
    }

    // Validate order against [asc, desc]. Default: asc.
    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, $allowedOrder)) {
        $order = 'asc';
    }

    // Append ORDER BY clause.
    $sql .= " ORDER BY $sort $order";

    // Prepare, bind, and execute.
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // Fetch all rows as associative array.
    $weeks = $stmt->fetchAll();

    // For each row, decode links JSON.
    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}


/**
 * Get a single week by its integer primary key.
 */
function getWeekById(PDO $db, $id): void
{
    // Validate that $id is provided and numeric.
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    // SELECT week by id.
    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch();

    if ($week) {
        // Decode links JSON.
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }
}


/**
 * Create a new week.
 */
function createWeek(PDO $db, array $data): void
{
    // Validate required fields.
    if (empty($data['title']) || empty($data['start_date'])) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required.'], 400);
    }

    // Trim inputs.
    $title      = trim($data['title']);
    $start_date = trim($data['start_date']);
    $description = trim($data['description'] ?? '');

    // Validate start_date format.
    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
    }

    // Handle links.
    $links = (isset($data['links']) && is_array($data['links']))
        ? json_encode($data['links'])
        : json_encode([]);

    // INSERT into weeks.
    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $start_date, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully.',
            'id'      => (int) $db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week.'], 500);
    }
}


/**
 * Update an existing week.
 */
function updateWeek(PDO $db, array $data): void
{
    // Validate that id is present.
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Week id is required.'], 400);
    }

    $id = $data['id'];

    // Check that the week exists.
    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    // Dynamically build SET clause.
    $setClauses = [];
    $params = [];

    if (isset($data['title'])) {
        $setClauses[] = "title = ?";
        $params[] = trim($data['title']);
    }

    if (isset($data['start_date'])) {
        $start_date = trim($data['start_date']);
        if (!validateDate($start_date)) {
            sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        $setClauses[] = "start_date = ?";
        $params[] = $start_date;
    }

    if (isset($data['description'])) {
        $setClauses[] = "description = ?";
        $params[] = trim($data['description']);
    }

    if (isset($data['links'])) {
        $setClauses[] = "links = ?";
        $params[] = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    // If no updatable fields, return 400.
    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided.'], 400);
    }

    // Build and execute UPDATE query.
    $sql = "UPDATE weeks SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() >= 0) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update week.'], 500);
    }
}


/**
 * Delete a week by integer id.
 */
function deleteWeek(PDO $db, $id): void
{
    // Validate that $id is provided and numeric.
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    // Check that the week exists.
    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    // DELETE the week (comments cascade automatically).
    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week.'], 500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
    // Validate that $weekId is provided and numeric.
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week_id.'], 400);
    }

    // SELECT comments ordered by created_at ASC.
    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);

    $comments = $stmt->fetchAll();
    sendResponse(['success' => true, 'data' => $comments]);
}


/**
 * Create a new comment.
 */
function createComment(PDO $db, array $data): void
{
    // Validate required fields.
    $weekId = $data['week_id'] ?? null;
    $author = isset($data['author']) ? trim($data['author']) : '';
    $text   = isset($data['text'])   ? trim($data['text'])   : '';

    if (!$weekId || $author === '' || $text === '') {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required.'], 400);
    }

    // Validate that week_id is numeric.
    if (!is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'week_id must be numeric.'], 400);
    }

    // Check that the week exists.
    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$weekId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    // INSERT the comment.
    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => $newId,
            'data'    => [
                'id'         => $newId,
                'week_id'    => (int) $weekId,
                'author'     => $author,
                'text'       => $text,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


/**
 * Delete a single comment.
 */
function deleteComment(PDO $db, $commentId): void
{
    // Validate that $commentId is provided and numeric.
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing comment_id.'], 400);
    }

    // Check that the comment exists.
    $check = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    // DELETE the comment.
    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);

} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An internal error occurred.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate a date string against the "YYYY-MM-DD" format.
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Sanitize a string input.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
