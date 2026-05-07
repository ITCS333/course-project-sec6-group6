<?php
/**
 * Assignment Management API
 *
 * RESTful API for CRUD operations on course assignments and their
 * discussion comments. Uses PDO to interact with the MySQL database
 * defined in schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: assignments
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   description TEXT
 *   due_date    DATE          NOT NULL
 *   files       TEXT          — JSON-encoded array of file URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP     — updated automatically by MySQL ON UPDATE
 *
 * Table: comments_assignment
 *   id            INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   assignment_id INT UNSIGNED  NOT NULL — FK → assignments.id (ON DELETE CASCADE)
 *   author        VARCHAR(100)  NOT NULL
 *   text          TEXT          NOT NULL
 *   created_at    TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve assignment(s) or comments
 *   POST   — Create a new assignment or comment
 *   PUT    — Update an existing assignment
 *   DELETE — Delete an assignment (cascade removes its comments) or a comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Assignments:
 *     GET    ./api/index.php                  — list all assignments
 *     GET    ./api/index.php?id={id}           — get one assignment by integer id
 *     POST   ./api/index.php                  — create a new assignment
 *     PUT    ./api/index.php                  — update an assignment (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete an assignment
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&assignment_id={id}
 *                                             — list comments for an assignment
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all assignments:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, due_date, created_at
 *            (default: due_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */
 
// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================
 
// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow headers: Content-Type, Authorization.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
 
// TODO: Include the shared database connection file.
require_once __DIR__ . '/../common/db.php';
 
// TODO: Get the PDO database connection.
$db = getDBConnection();
 
// TODO: Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];
 
// TODO: Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];
 
// TODO: Read query parameters.
$action       = $_GET['action']        ?? null;  // 'comments', 'comment', 'delete_comment'
$id           = $_GET['id']            ?? null;  // integer assignment id
$assignmentId = $_GET['assignment_id'] ?? null;  // integer assignment id for comments queries
$commentId    = $_GET['comment_id']    ?? null;  // integer comment id
 
 
// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================
 
/**
 * Get all assignments (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 */
function getAllAssignments(PDO $db): void
{
    // TODO: Build the base SELECT query.
    $sql    = 'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments';
    $params = [];
 
    // TODO: If $_GET['search'] is provided and non-empty, append WHERE clause.
    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $sql               .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search']  = '%' . $search . '%';
    }
 
    // TODO: Validate $_GET['sort'] against the whitelist [title, due_date, created_at].
    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort        = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'due_date';
 
    // TODO: Validate $_GET['order'] against [asc, desc].
    $allowedOrder = ['asc', 'desc'];
    $order        = in_array($_GET['order'] ?? '', $allowedOrder) ? $_GET['order'] : 'asc';
 
    // TODO: Append ORDER BY {sort} {order} to the query.
    $sql .= " ORDER BY {$sort} {$order}";
 
    // TODO: Prepare, bind (if searching), and execute the statement.
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
 
    // TODO: Fetch all rows as an associative array.
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    // TODO: For each row, decode the files column.
    foreach ($assignments as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }
 
    // TODO: Call sendResponse.
    sendResponse(['success' => true, 'data' => $assignments]);
}
 
 
/**
 * Get a single assignment by its integer primary key.
 * Method: GET with ?id={id}.
 */
function getAssignmentById(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment id.'], 400);
    }
 
    // TODO: SELECT from assignments WHERE id = ?
    $stmt = $db->prepare(
        'SELECT id, title, description, due_date, files, created_at, updated_at
         FROM assignments WHERE id = ?'
    );
    $stmt->execute([(int)$id]);
 
    // TODO: Fetch one row and decode files JSON.
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];
    }
 
    // TODO: If found, sendResponse success. If not found, HTTP 404.
    if ($assignment) {
        sendResponse(['success' => true, 'data' => $assignment]);
    } else {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }
}
 
 
/**
 * Create a new assignment.
 * Method: POST (no ?action parameter).
 */
function createAssignment(PDO $db, array $data): void
{
    // TODO: Validate required fields: title, description, due_date.
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'title, description, and due_date are required.'], 400);
    }
 
    // TODO: Trim fields.
    $title       = trim($data['title']);
    $description = trim($data['description']);
    $due_date    = trim($data['due_date']);
 
    // TODO: Validate due_date format.
    if (!validateDate($due_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid due_date format. Expected YYYY-MM-DD.'], 400);
    }
 
    // TODO: Handle files array.
    $files = (isset($data['files']) && is_array($data['files']))
        ? json_encode($data['files'])
        : json_encode([]);
 
    // TODO: INSERT INTO assignments.
    $stmt = $db->prepare(
        'INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$title, $description, $due_date, $files]);
 
    // TODO: Return HTTP 201 with new id, or HTTP 500 on failure.
    if ($stmt->rowCount() > 0) {
        sendResponse(
            ['success' => true, 'message' => 'Assignment created.', 'id' => (int)$db->lastInsertId()],
            201
        );
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create assignment.'], 500);
    }
}
 
 
/**
 * Update an existing assignment.
 * Method: PUT.
 */
function updateAssignment(PDO $db, array $data): void
{
    // TODO: Validate that $data['id'] is present.
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'id is required and must be numeric.'], 400);
    }
 
    $id = (int)$data['id'];
 
    // TODO: Check the assignment exists.
    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }
 
    // TODO: Dynamically build the SET clause.
    $setClauses = [];
    $params     = [];
 
    if (isset($data['title'])) {
        $setClauses[] = 'title = ?';
        $params[]     = trim($data['title']);
    }
    if (isset($data['description'])) {
        $setClauses[] = 'description = ?';
        $params[]     = trim($data['description']);
    }
    if (isset($data['due_date'])) {
        $due_date = trim($data['due_date']);
        if (!validateDate($due_date)) {
            sendResponse(['success' => false, 'message' => 'Invalid due_date format. Expected YYYY-MM-DD.'], 400);
        }
        $setClauses[] = 'due_date = ?';
        $params[]     = $due_date;
    }
    if (isset($data['files'])) {
        $setClauses[] = 'files = ?';
        $params[]     = json_encode(is_array($data['files']) ? $data['files'] : []);
    }
 
    // TODO: If no updatable fields, HTTP 400.
    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided.'], 400);
    }
 
    // TODO: updated_at refreshed automatically by MySQL ON UPDATE CURRENT_TIMESTAMP.
 
    // TODO: BUILD and execute UPDATE query.
    $params[] = $id;
    $sql      = 'UPDATE assignments SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->execute($params);
 
    // TODO: HTTP 200 on success (rowCount 0 when values unchanged is still success).
    sendResponse(['success' => true, 'message' => 'Assignment updated.']);
}
 
 
/**
 * Delete an assignment by integer id.
 * Method: DELETE with ?id={id}.
 */
function deleteAssignment(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    if (!isset($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment id.'], 400);
    }
 
    $id = (int)$id;
 
    // TODO: Check the assignment exists.
    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }
 
    // TODO: DELETE FROM assignments WHERE id = ? (CASCADE removes comments automatically).
    $stmt = $db->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
 
    // TODO: HTTP 200 on success, HTTP 500 on failure.
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Assignment deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete assignment.'], 500);
    }
}
 
 
// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================
 
/**
 * Get all comments for a specific assignment.
 * Method: GET with ?action=comments&assignment_id={id}.
 */
function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    // TODO: Validate that $assignmentId is provided and numeric.
    if (!isset($assignmentId) || !is_numeric($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment_id.'], 400);
    }
 
    // TODO: SELECT from comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC.
    $stmt = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int)$assignmentId]);
 
    // TODO: Fetch all rows (empty array is valid).
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success' => true, 'data' => $comments]);
}
 
 
/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 */
function createComment(PDO $db, array $data): void
{
    // TODO: Validate assignment_id, author, and text are present and non-empty.
    $author = trim($data['author'] ?? '');
    $text   = trim($data['text']   ?? '');
 
    if (empty($data['assignment_id']) || $author === '' || $text === '') {
        sendResponse(['success' => false, 'message' => 'assignment_id, author, and text are required.'], 400);
    }
 
    // TODO: Validate that assignment_id is numeric.
    if (!is_numeric($data['assignment_id'])) {
        sendResponse(['success' => false, 'message' => 'assignment_id must be numeric.'], 400);
    }
 
    $assignmentId = (int)$data['assignment_id'];
 
    // TODO: Check that the parent assignment exists.
    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }
 
    // TODO: INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?).
    $stmt = $db->prepare(
        'INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)'
    );
    $stmt->execute([$assignmentId, $author, $text]);
 
    // TODO: HTTP 201 with new id and full comment object, or HTTP 500.
    if ($stmt->rowCount() > 0) {
        $newId     = (int)$db->lastInsertId();
        $fetchStmt = $db->prepare(
            'SELECT id, assignment_id, author, text, created_at
             FROM comments_assignment WHERE id = ?'
        );
        $fetchStmt->execute([$newId]);
        $comment = $fetchStmt->fetch(PDO::FETCH_ASSOC);
 
        sendResponse(
            ['success' => true, 'message' => 'Comment created.', 'id' => $newId, 'data' => $comment],
            201
        );
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}
 
 
/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 */
function deleteComment(PDO $db, $commentId): void
{
    // TODO: Validate that $commentId is provided and numeric.
    if (!isset($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment_id.'], 400);
    }
 
    $commentId = (int)$commentId;
 
    // TODO: Check the comment exists.
    $check = $db->prepare('SELECT id FROM comments_assignment WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }
 
    // TODO: DELETE FROM comments_assignment WHERE id = ?
    $stmt = $db->prepare('DELETE FROM comments_assignment WHERE id = ?');
    $stmt->execute([$commentId]);
 
    // TODO: HTTP 200 on success, HTTP 500 on failure.
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}
 
 
// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================
 
try {
 
    if ($method === 'GET') {
 
        // ?action=comments&assignment_id={id} → list comments for an assignment
        // TODO: if $action === 'comments', call getCommentsByAssignment($db, $assignmentId)
        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
 
        // ?id={id} → single assignment
        // TODO: elseif $id is set, call getAssignmentById($db, $id)
        } elseif (isset($id)) {
            getAssignmentById($db, $id);
 
        // no parameters → all assignments (supports ?search, ?sort, ?order)
        // TODO: else call getAllAssignments($db)
        } else {
            getAllAssignments($db);
        }
 
    } elseif ($method === 'POST') {
 
        // ?action=comment → create a comment in comments_assignment
        // TODO: if $action === 'comment', call createComment($db, $data)
        if ($action === 'comment') {
            createComment($db, $data);
 
        // no action → create a new assignment
        // TODO: else call createAssignment($db, $data)
        } else {
            createAssignment($db, $data);
        }
 
    } elseif ($method === 'PUT') {
 
        // Update an assignment; id comes from the JSON body
        // TODO: call updateAssignment($db, $data)
        updateAssignment($db, $data);
 
    } elseif ($method === 'DELETE') {
 
        // ?action=delete_comment&comment_id={id} → delete one comment
        // TODO: if $action === 'delete_comment', call deleteComment($db, $commentId)
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
 
        // ?id={id} → delete an assignment (and its comments via CASCADE)
        // TODO: else call deleteAssignment($db, $id)
        } else {
            deleteAssignment($db, $id);
        }
 
    } else {
        // TODO: sendResponse HTTP 405 Method Not Allowed.
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
 
} catch (PDOException $e) {
    // TODO: Log the error with error_log().
    // Return a generic HTTP 500 — do NOT expose $e->getMessage() to clients.
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);
 
} catch (Exception $e) {
    // TODO: Log the error with error_log().
    // Return HTTP 500 using sendResponse().
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
 
 
// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
 
/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    // TODO: http_response_code($statusCode);
    http_response_code($statusCode);
    // TODO: echo json_encode($data, JSON_PRETTY_PRINT);
    echo json_encode($data, JSON_PRETTY_PRINT);
    // TODO: exit;
    exit;
}
 
 
/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
    // TODO: $d = DateTime::createFromFormat('Y-m-d', $date);
    $d = DateTime::createFromFormat('Y-m-d', $date);
    // TODO: return $d && $d->format('Y-m-d') === $date;
    return $d && $d->format('Y-m-d') === $date;
}
 
 
/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}