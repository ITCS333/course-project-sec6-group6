<?php
/**
 * Assignment Management API
 * 
 * This is a RESTful API that handles all CRUD operations for course assignments
 * and their associated discussion comments.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: assignments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(200))
 *   - description (TEXT)
 *   - due_date (DATE)
 *   - files (TEXT)
 *   - created_at (TIMESTAMP)
 *   - updated_at (TIMESTAMP)
 * 
 * Table: comments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - assignment_id (VARCHAR(50), FOREIGN KEY)
 *   - author (VARCHAR(100))
 *   - text (TEXT)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET: Retrieve assignment(s) or comment(s)
 *   - POST: Create a new assignment or comment
 *   - PUT: Update an existing assignment
 *   - DELETE: Delete an assignment or comment
 * 
 * Response Format: JSON
 */

// Start session for authentication
session_start();

// Store session data for user tracking
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = null;
}

// ============================================================================
// HEADERS AND CORS CONFIGURATION
// ============================================================================

// TODO: Set Content-Type header to application/json
header('Content-Type: application/json');

// TODO: Set CORS headers to allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

// TODO: Include the database connection class
require_once '../../common/DBConfig.php';
require_once '../../common/DatabaseHelper.php';

// TODO: Create database connection
$database = new DatabaseHelper($config["host"], $config["dbname"], $config["username"], $config["password"]);
$db = $database->getConnection();

// TODO: Set PDO to throw exceptions on errors
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ============================================================================
// REQUEST PARSING
// ============================================================================

// TODO: Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
$data = [];
if ($method === 'POST' || $method === 'PUT') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true) ?? [];
}

// TODO: Parse query parameters
$resource = $_GET['resource'] ?? '';
$id = $_GET['id'] ?? '';
$assignment_id = $_GET['assignment_id'] ?? '';

// ============================================================================
// ASSIGNMENT CRUD FUNCTIONS
// ============================================================================

/**
 * Function: Get all assignments
 * Method: GET
 * Endpoint: ?resource=assignments
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by title or description
 *   - sort: Optional field to sort by (title, due_date, created_at)
 *   - order: Optional sort order (asc or desc, default: asc)
 * 
 * Response: JSON array of assignment objects
 */
function getAllAssignments($db) {
    // TODO: Start building the SQL query
    $sql = "SELECT * FROM assignments WHERE 1=1";
    $params = [];
    
    // TODO: Check if 'search' query parameter exists in $_GET
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $sql .= " AND (title LIKE :search OR description LIKE :search)";
        $params[':search'] = $search;
    }
    
    // TODO: Check if 'sort' and 'order' query parameters exist
    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort = isset($_GET['sort']) && validateAllowedValue($_GET['sort'], $allowedSort) ? $_GET['sort'] : 'created_at';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
    $sql .= " ORDER BY {$sort} {$order}";
    
    // TODO: Prepare the SQL statement using $db->prepare()
    $stmt = $db->prepare($sql);
    
    // TODO: Bind parameters if search is used
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // TODO: Execute the prepared statement
    $stmt->execute();
    
    // TODO: Fetch all results as associative array
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // TODO: For each assignment, decode the 'files' field from JSON to array
    foreach ($assignments as &$assignment) {
        $assignment['files'] = json_decode($assignment['files'] ?? '[]', true);
    }
    
    // TODO: Return JSON response
    sendResponse(['success' => true, 'data' => $assignments]);
}

/**
 * Function: Get a single assignment by ID
 * Method: GET
 * Endpoint: ?resource=assignments&id={assignment_id}
 * 
 * Query Parameters:
 *   - id: The assignment ID (required)
 * 
 * Response: JSON object with assignment details
 */
function getAssignmentById($db, $assignmentId) {
    // TODO: Validate that $assignmentId is provided and not empty
    if (empty($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Assignment ID is required'], 400);
    }
    
    // TODO: Prepare SQL query to select assignment by id
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = :id");
    
    // TODO: Bind the :id parameter
    $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
    
    // TODO: Execute the statement
    $stmt->execute();
    
    // TODO: Fetch the result as associative array
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // TODO: Check if assignment was found
    if (!$assignment) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }
    
    // TODO: Decode the 'files' field from JSON to array
    $assignment['files'] = json_decode($assignment['files'] ?? '[]', true);
    
    // TODO: Return success response with assignment data
    sendResponse(['success' => true, 'data' => $assignment]);
}

/**
 * Function: Create a new assignment
 * Method: POST
 * Endpoint: ?resource=assignments
 * 
 * Required JSON Body:
 *   - title: Assignment title (required)
 *   - description: Assignment description (required)
 *   - due_date: Due date in YYYY-MM-DD format (required)
 *   - files: Array of file URLs/paths (optional)
 * 
 * Response: JSON object with created assignment data
 */
function createAssignment($db, $data) {
    // TODO: Validate required fields
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'Title, description, and due_date are required'], 400);
    }
    
    // TODO: Sanitize input data
    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $due_date = $data['due_date'];
    
    // TODO: Validate due_date format
    if (!validateDate($due_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
    }
    
    // TODO: Generate a unique assignment ID
    // Note: Using AUTO_INCREMENT, so no need to generate manually
    
    // TODO: Handle the 'files' field
    $files = isset($data['files']) && is_array($data['files']) ? json_encode($data['files']) : '[]';
    
    // TODO: Prepare INSERT query
    $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (:title, :description, :due_date, :files)");
    
    // TODO: Bind all parameters
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':due_date', $due_date);
    $stmt->bindValue(':files', $files);
    
    // TODO: Execute the statement
    $success = $stmt->execute();
    
    // TODO: Check if insert was successful
    if ($success) {
        $newId = $db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Assignment created successfully',
            'data' => [
                'id' => $newId,
                'title' => $title,
                'description' => $description,
                'due_date' => $due_date,
                'files' => json_decode($files, true)
            ]
        ], 201);
    }
    
    // TODO: If insert failed, return 500 error
    sendResponse(['success' => false, 'message' => 'Failed to create assignment'], 500);
}

/**
 * Function: Update an existing assignment
 * Method: PUT
 * Endpoint: ?resource=assignments
 * 
 * Required JSON Body:
 *   - id: Assignment ID (required, to identify which assignment to update)
 *   - title: Updated title (optional)
 *   - description: Updated description (optional)
 *   - due_date: Updated due date (optional)
 *   - files: Updated files array (optional)
 * 
 * Response: JSON object with success status
 */
function updateAssignment($db, $data) {
    // TODO: Validate that 'id' is provided in $data
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Assignment ID is required'], 400);
    }
    
    // TODO: Store assignment ID in variable
    $id = $data['id'];
    
    // TODO: Check if assignment exists
    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }
    
    // TODO: Build UPDATE query dynamically based on provided fields
    $updates = [];
    $params = [':id' => $id];
    
    // TODO: Check which fields are provided and add to SET clause
    if (!empty($data['title'])) {
        $updates[] = "title = :title";
        $params[':title'] = sanitizeInput($data['title']);
    }
    if (!empty($data['description'])) {
        $updates[] = "description = :description";
        $params[':description'] = sanitizeInput($data['description']);
    }
    if (!empty($data['due_date'])) {
        if (!validateDate($data['due_date'])) {
            sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
        }
        $updates[] = "due_date = :due_date";
        $params[':due_date'] = $data['due_date'];
    }
    if (isset($data['files']) && is_array($data['files'])) {
        $updates[] = "files = :files";
        $params[':files'] = json_encode($data['files']);
    }
    
    // TODO: If no fields to update (besides updated_at), return 400 error
    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    // TODO: Complete the UPDATE query
    $sql = "UPDATE assignments SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    
    // TODO: Prepare the statement
    $stmt = $db->prepare($sql);
    
    // TODO: Bind all parameters dynamically
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // TODO: Execute the statement
    $success = $stmt->execute();
    
    // TODO: Check if update was successful
    if ($success) {
        sendResponse(['success' => true, 'message' => 'Assignment updated successfully']);
    }
    
    // TODO: If no rows affected, return appropriate message
    sendResponse(['success' => false, 'message' => 'Failed to update assignment'], 500);
}

/**
 * Function: Delete an assignment
 * Method: DELETE
 * Endpoint: ?resource=assignments&id={assignment_id}
 * 
 * Query Parameters:
 *   - id: Assignment ID (required)
 * 
 * Response: JSON object with success status
 */
function deleteAssignment($db, $assignmentId) {
    // TODO: Validate that $assignmentId is provided and not empty
    if (empty($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Assignment ID is required'], 400);
    }
    
    // TODO: Check if assignment exists
    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }
    
    // TODO: Delete associated comments first (due to foreign key constraint)
    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE assignment_id = :id");
    $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
    $stmt->execute();
    
    // TODO: Prepare DELETE query for assignment
    $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id");
    
    // TODO: Bind the :id parameter
    $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
    
    // TODO: Execute the statement
    $success = $stmt->execute();
    
    // TODO: Check if delete was successful
    if ($success) {
        sendResponse(['success' => true, 'message' => 'Assignment deleted successfully']);
    }
    
    // TODO: If delete failed, return 500 error
    sendResponse(['success' => false, 'message' => 'Failed to delete assignment'], 500);
}

// ============================================================================
// COMMENT CRUD FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific assignment
 * Method: GET
 * Endpoint: ?resource=comments&assignment_id={assignment_id}
 * 
 * Query Parameters:
 *   - assignment_id: The assignment ID (required)
 * 
 * Response: JSON array of comment objects
 */
function getCommentsByAssignment($db, $assignmentId) {
    // TODO: Validate that $assignmentId is provided and not empty
    if (empty($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Assignment ID is required'], 400);
    }
    
    // TODO: Prepare SQL query to select all comments for the assignment
    $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id = :assignment_id ORDER BY created_at DESC");
    
    // TODO: Bind the :assignment_id parameter
    $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
    
    // TODO: Execute the statement
    $stmt->execute();
    
    // TODO: Fetch all results as associative array
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // TODO: Return success response with comments data
    sendResponse(['success' => true, 'data' => $comments]);
}

/**
 * Function: Create a new comment
 * Method: POST
 * Endpoint: ?resource=comments
 * 
 * Required JSON Body:
 *   - assignment_id: Assignment ID (required)
 *   - author: Comment author name (required)
 *   - text: Comment content (required)
 * 
 * Response: JSON object with created comment data
 */
function createComment($db, $data) {
    // TODO: Validate required fields
    if (empty($data['assignment_id']) || empty($data['author']) || empty($data['text'])) {
        sendResponse(['success' => false, 'message' => 'Assignment ID, author, and text are required'], 400);
    }
    
    // TODO: Sanitize input data
    $assignment_id = $data['assignment_id'];
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);
    
    // TODO: Validate that text is not empty after trimming
    if (empty(trim($text))) {
        sendResponse(['success' => false, 'message' => 'Comment text cannot be empty'], 400);
    }
    
    // TODO: Verify that the assignment exists
    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $assignment_id, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }
    
    // TODO: Prepare INSERT query for comment
    $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (:assignment_id, :author, :text)");
    
    // TODO: Bind all parameters
    $stmt->bindValue(':assignment_id', $assignment_id, PDO::PARAM_INT);
    $stmt->bindValue(':author', $author);
    $stmt->bindValue(':text', $text);
    
    // TODO: Execute the statement
    $success = $stmt->execute();
    
    // TODO: Get the ID of the inserted comment
    if ($success) {
        $newId = $db->lastInsertId();
        
        // TODO: Return success response with created comment data
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully',
            'data' => [
                'id' => $newId,
                'assignment_id' => $assignment_id,
                'author' => $author,
                'text' => $text,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    }
    
    sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
}

/**
 * Function: Delete a comment
 * Method: DELETE
 * Endpoint: ?resource=comments&id={comment_id}
 * 
 * Query Parameters:
 *   - id: Comment ID (required)
 * 
 * Response: JSON object with success status
 */
function deleteComment($db, $commentId) {
    // TODO: Validate that $commentId is provided and not empty
    if (empty($commentId)) {
        sendResponse(['success' => false, 'message' => 'Comment ID is required'], 400);
    }
    
    // TODO: Check if comment exists
    $stmt = $db->prepare("SELECT id FROM comments_assignment WHERE id = :id");
    $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }
    
    // TODO: Prepare DELETE query
    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = :id");
    
    // TODO: Bind the :id parameter
    $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
    
    // TODO: Execute the statement
    $success = $stmt->execute();
    
    // TODO: Check if delete was successful
    if ($success) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
    }
    
    // TODO: If delete failed, return 500 error
    sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // TODO: Get the 'resource' query parameter to determine which resource to access
    // Already parsed above in REQUEST PARSING section
    
    // TODO: Route based on HTTP method and resource type
    if ($method === 'GET') {
        // TODO: Handle GET requests
        if ($resource === 'assignments') {
            // TODO: Check if 'id' query parameter exists
            if (!empty($id)) {
                getAssignmentById($db, $id);
            } else {
                getAllAssignments($db);
            }
        } elseif ($resource === 'comments') {
            // TODO: Check if 'assignment_id' query parameter exists
            if (!empty($assignment_id)) {
                getCommentsByAssignment($db, $assignment_id);
            } else {
                // TODO: Invalid resource, return 400 error
                sendResponse(['success' => false, 'message' => 'Assignment ID is required for comments'], 400);
            }
        } else {
            // TODO: Invalid resource, return 400 error
            sendResponse(['success' => false, 'message' => 'Invalid resource'], 400);
        }
        
    } elseif ($method === 'POST') {
        // TODO: Handle POST requests (create operations)
        if ($resource === 'assignments') {
            // TODO: Call createAssignment($db, $data)
            createAssignment($db, $data);
        } elseif ($resource === 'comments') {
            // TODO: Call createComment($db, $data)
            createComment($db, $data);
        } else {
            // TODO: Invalid resource, return 400 error
            sendResponse(['success' => false, 'message' => 'Invalid resource'], 400);
        }
        
    } elseif ($method === 'PUT') {
        // TODO: Handle PUT requests (update operations)
        if ($resource === 'assignments') {
            // TODO: Call updateAssignment($db, $data)
            updateAssignment($db, $data);
        } else {
            // TODO: PUT not supported for other resources
            sendResponse(['success' => false, 'message' => 'PUT method not supported for this resource'], 405);
        }
        
    } elseif ($method === 'DELETE') {
        // TODO: Handle DELETE requests
        if ($resource === 'assignments') {
            // TODO: Get 'id' from query parameter or request body
            $deleteId = !empty($id) ? $id : ($data['id'] ?? '');
            deleteAssignment($db, $deleteId);
        } elseif ($resource === 'comments') {
            // TODO: Get comment 'id' from query parameter
            deleteComment($db, $id);
        } else {
            // TODO: Invalid resource, return 400 error
            sendResponse(['success' => false, 'message' => 'Invalid resource'], 400);
        }
        
    } else {
        // TODO: Method not supported
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (PDOException $e) {
    // TODO: Handle database errors
    sendResponse([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], 500);
} catch (Exception $e) {
    // TODO: Handle general errors
    sendResponse([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper function to send JSON response and exit
 * 
 * @param array $data - Data to send as JSON
 * @param int $statusCode - HTTP status code (default: 200)
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Set HTTP response code
    http_response_code($statusCode);
    
    // TODO: Ensure data is an array
    if (!is_array($data)) {
        $data = ['data' => $data];
    }
    
    // TODO: Echo JSON encoded data
    echo json_encode($data, JSON_PRETTY_PRINT);
    
    // TODO: Exit to prevent further execution
    exit();
}

/**
 * Helper function to sanitize string input
 * 
 * @param string $data - Input data to sanitize
 * @return string - Sanitized data
 */
function sanitizeInput($data) {
    // TODO: Trim whitespace from beginning and end
    $data = trim($data);
    
    // TODO: Remove HTML and PHP tags
    $data = strip_tags($data);
    
    // TODO: Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // TODO: Return the sanitized data
    return $data;
}

/**
 * Helper function to validate date format (YYYY-MM-DD)
 * 
 * @param string $date - Date string to validate
 * @return bool - True if valid, false otherwise
 */
function validateDate($date) {
    // TODO: Use DateTime::createFromFormat to validate
    $d = DateTime::createFromFormat('Y-m-d', $date);
    
    // TODO: Return true if valid, false otherwise
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Helper function to validate allowed values (for sort fields, order, etc.)
 * 
 * @param string $value - Value to validate
 * @param array $allowedValues - Array of allowed values
 * @return bool - True if valid, false otherwise
 */
function validateAllowedValue($value, $allowedValues) {
    // TODO: Check if $value exists in $allowedValues array
    $result = in_array($value, $allowedValues, true);
    
    // TODO: Return the result
    return $result;
}

?>