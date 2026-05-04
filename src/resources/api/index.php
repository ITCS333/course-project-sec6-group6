<?php
/**
 * Course Resources API
 * 
 * This is a RESTful API that handles all CRUD operations for course resources 
 * and their associated comments/discussions.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: resources
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(255), NOT NULL)
 *   - description (TEXT, nullable)
 *   - link (VARCHAR(500), NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * Table: comments_resource
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - resource_id (INT UNSIGNED, FOREIGN KEY references resources.id, CASCADE DELETE)
 *   - author (VARCHAR(100), NOT NULL)
 *   - text (TEXT, NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET:    Retrieve resource(s) or comment(s)
 *   - POST:   Create a new resource or comment
 *   - PUT:    Update an existing resource
 *   - DELETE: Delete a resource (associated comments in comments_resource are
 *             removed automatically by the ON DELETE CASCADE constraint)
 * 
 * Response Format: JSON
 * All responses follow the structure:
 *   { "success": true,  "data": ...    }  (on success)
 *   { "success": false, "message": ... }  (on error)
 * 
 * API Endpoints:
 * 
 *   Resources:
 *     GET    /resources/api/index.php                         - Get all resources
 *     GET    /resources/api/index.php?id={id}                 - Get single resource by ID
 *     POST   /resources/api/index.php                         - Create new resource
 *     PUT    /resources/api/index.php                         - Update resource
 *     DELETE /resources/api/index.php?id={id}                 - Delete resource
 * 
 *   Comments:
 *     GET    /resources/api/index.php?resource_id={id}&action=comments
 *                                                             - Get all comments for a resource
 *     POST   /resources/api/index.php?action=comment          - Create a new comment
 *     DELETE /resources/api/index.php?comment_id={id}&action=delete_comment
 *                                                             - Delete a single comment
 * 
 * Query Parameters for GET all resources:
 *   - search: Optional. Filter resources by title or description using LIKE.
 *   - sort:   Optional. Sort field — allowed values: title, created_at (default: created_at).
 *   - order:  Optional. Sort direction — allowed values: asc, desc (default: desc).
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS
// Set Content-Type to application/json
// Allow cross-origin requests (CORS) if needed
// Allow specific HTTP methods (GET, POST, PUT, DELETE, OPTIONS)
// Allow specific headers (Content-Type, Authorization)

header("Content-Type: application/json");

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// TODO: Handle preflight OPTIONS request
// If the request method is OPTIONS, return 200 status and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// TODO: Include the database connection file
// The Database class lives at src/resources/api/config/Database.php
// require_once './config/Database.php';
require_once './config/Database.php';

// TODO: Get the PDO database connection
// $database = new Database();
// $db = $database->getConnection();
$database = new Database();
$db = $database->getConnection();


// TODO: Get the HTTP request method
// $method = $_SERVER['REQUEST_METHOD'];
$method = $_SERVER['REQUEST_METHOD'];


// TODO: Get the request body for POST and PUT requests
// $rawData = file_get_contents('php://input');
// $data = json_decode($rawData, true);
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);


// TODO: Parse query parameters from $_GET
// Get 'action', 'id', 'resource_id', 'comment_id'
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;



// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Function: Get all resources
 * Method: GET (no id or action parameter)
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by title or description
 *   - sort:   Optional field to sort by — allowed values: title, created_at
 *   - order:  Optional sort direction — allowed values: asc, desc (default: desc)
 * 
 * Response:
 *   { "success": true, "data": [ ...resource objects ] }
 */
function getAllResources($db) {
    // TODO: Initialize the base SQL query
    // SELECT id, title, description, link, created_at FROM resources
    // base query
    $sql = "SELECT id, title, description, link, created_at FROM resources";

    $params = [];

    // TODO: Check if search parameter exists in $_GET
    // If yes, add WHERE clause using LIKE to search title and description
    // Use OR to search both fields
   // search
    if (!empty($_GET['search'])) {
        $search = $_GET['search'];
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    // TODO: Validate the sort parameter
    // Allowed values: title, created_at
    // Default to 'created_at' if not provided or invalid

      // sort validation
    $allowedSort = ['title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';

    if (!in_array($sort, $allowedSort)) {
        $sort = 'created_at';
    }

    // TODO: Validate the order parameter
    // Allowed values: asc, desc
    // Default to 'desc' if not provided or invalid
       // order validation
    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'desc');

    if (!in_array($order, $allowedOrder)) {
        $order = 'desc';
    }

    // TODO: Add ORDER BY clause to the query
       // ORDER BY
    $sql .= " ORDER BY $sort $order";

    // TODO: Prepare the statement using PDO
       // prepare
    $stmt = $db->prepare($sql);

    // TODO: If a search parameter was used, bind it with % wildcards
    // e.g., $stmt->bindValue(':search', '%' . $search . '%')
        // bind (if search exists)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    // TODO: Execute the query
    $stmt->execute();

    // TODO: Fetch all results as an associative array
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TODO: Return JSON response using sendResponse()
    // e.g., sendResponse(['success' => true, 'data' => $resources]);
     sendResponse([
        'success' => true,
        'data' => $resources
    ]);
}


/**
 * Function: Get a single resource by ID
 * Method: GET with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   { "success": true, "data": { id, title, description, link, created_at } }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 */
function getResourceById($db, $resourceId) {
    // TODO: Validate that $resourceId is provided and is numeric
    // If not, return error response with HTTP 400
     if (!$resourceId || !is_numeric($resourceId)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ]);
        return;
    }

    // TODO: Prepare SQL query
    // SELECT id, title, description, link, created_at FROM resources WHERE id = ?

   $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");

    // TODO: Bind $resourceId and execute
     $stmt->execute([$resourceId]);

    // TODO: Fetch the result as an associative array
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: If found, return success response with resource data
    // If not found, return error response with HTTP 404
    if ($resource) {
        sendResponse([
            'success' => true,
            'data' => $resource
        ]);
    } else {
        http_response_code(404);
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ]);
    }
}


/**
 * Function: Create a new resource
 * Method: POST (no action parameter)
 * 
 * Required JSON Body:
 *   - title:       Resource title (required)
 *   - description: Resource description (optional, defaults to empty string)
 *   - link:        URL to the resource (required, must be a valid URL)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new resource id> }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createResource($db, $data) {
    // TODO: Validate required fields — title and link must not be empty
    // If missing, return error response with HTTP 400

    // TODO: Sanitize input — trim whitespace from all fields

    // TODO: Validate the link using filter_var with FILTER_VALIDATE_URL
    // If invalid, return error response with HTTP 400

    // TODO: Default description to empty string if not provided

    // TODO: Prepare INSERT query
    // INSERT INTO resources (title, description, link) VALUES (?, ?, ?)

    // TODO: Bind title, description, and link; then execute

    // TODO: If rowCount() > 0, return success response with HTTP 201
    //       and include the new id from $db->lastInsertId()
    // If failed, return error response with HTTP 500
        if (empty($data['title']) || empty($data['link'])) {
        http_response_code(400);
        sendResponse(['success' => false, 'message' => 'Title and link are required.']);
        return;
    }

    $title       = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : '';
    $link        = trim($data['link']);

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        sendResponse(['success' => false, 'message' => 'Invalid URL.']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    if ($stmt->rowCount() > 0) {
        http_response_code(201);
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id'      => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        sendResponse(['success' => false, 'message' => 'Failed to create resource.']);
    }

}


/**
 * Function: Update an existing resource
 * Method: PUT
 * 
 * Required JSON Body:
 *   - id:          The resource's database ID (required)
 *   - title:       Updated title (optional)
 *   - description: Updated description (optional)
 *   - link:        Updated URL (optional, must be a valid URL if provided)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource updated successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function updateResource($db, $data) {
    // TODO: Validate that id is provided in $data
    // If not, return error response with HTTP 400
    if (empty($data['title'])) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Title and link are required.'
        ]);
        return;
    }

    // TODO: Check if the resource exists — SELECT by id
    // If not found, return error response with HTTP 404
    $check = $db->prepare("SELECT id, title, description, link FROM resources WHERE id = ?");
    $check->execute([$data['id']]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        sendResponse(['success' => false, 'message' => 'Resource not found.']);
        return;
    }

    // استخدم القيم الموجودة كـ fallback
    $title       = isset($data['title'])       ? trim($data['title'])       : $existing['title'];
    $description = isset($data['description']) ? trim($data['description']) : $existing['description'];
    $link        = isset($data['link'])        ? trim($data['link'])        : $existing['link'];

    // TODO: Build UPDATE query dynamically for only the fields provided
    // (title, description, link — check which are present in $data)
    // If no fields to update, return error response with HTTP 400
     if (!filter_var($link, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid URL.'
        ]);
        return;
    }

    // TODO: If link is being updated, validate it with FILTER_VALIDATE_URL
    // If invalid, return error response with HTTP 400

    // TODO: Build the final SQL:
    // UPDATE resources SET field1 = ?, field2 = ?, ... WHERE id = ?
    // TODO: Prepare, bind all update values then bind id, and execute
    // TODO: Return success response with HTTP 200
    // If execution failed, return error response with HTTP 500
      $stmt = $db->prepare("UPDATE resources SET title = ?, description = ?, link = ? WHERE id = ?");
    $stmt->execute([$title, $description, $link, $data['id']]);
   if ($stmt->rowCount() > 0) {
    http_response_code(200);
    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
} else {
    http_response_code(500);
    sendResponse(['success' => false, 'message' => 'Failed to update resource.']);
}


/**
 * Function: Delete a resource
 * Method: DELETE with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * 
 * Note: All associated comments in comments_resource are deleted automatically
 *       by the ON DELETE CASCADE foreign key constraint — no manual deletion
 *       of comments is needed.
 */
function deleteResource($db, $resourceId) {
    // TODO: Validate that $resourceId is provided and is numeric
    // If not, return error response with HTTP 400
if (!$resourceId || !is_numeric($resourceId)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ]);
        return;
    }
    // TODO: Check if the resource exists — SELECT by id
    // If not found, return error response with HTTP 404
 $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);

    if ($check->rowCount() === 0) {
        http_response_code(404);
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ]);
        return;
    }
    // TODO: Prepare DELETE query
    // DELETE FROM resources WHERE id = ?
 $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");

    // TODO: Bind $resourceId and execute
     $stmt->execute([$resourceId]);


    // TODO: If rowCount() > 0, return success response with HTTP 200
    // If failed, return error response with HTTP 500
      if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ]);
    } else {
        http_response_code(500);
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete resource.'
        ]);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific resource
 * Method: GET with ?resource_id={id}&action=comments
 * 
 * Query Parameters:
 *   - resource_id: The resource's database ID (required)
 * 
 * Response:
 *   { "success": true, "data": [ ...comment objects ] }
 *   Returns an empty data array if no comments exist (not an error).
 *
 * Each comment object: { id, resource_id, author, text, created_at }
 */
function getCommentsByResourceId($db, $resourceId) {
    // TODO: Validate that $resourceId is provided and is numeric
    // If not, return error response with HTTP 400
if (!$resourceId || !is_numeric($resourceId)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ]);
        return;
    }
    // TODO: Prepare SQL query
    // SELECT id, resource_id, author, text, created_at
    // FROM comments_resource
    // WHERE resource_id = ?
    // ORDER BY created_at ASC
   $stmt = $db->prepare(
        "SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC"
    );

    // TODO: Bind $resourceId and execute
      $stmt->execute([$resourceId]);

    // TODO: Fetch all results as an associative array
       $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TODO: Return success response — always return an array,
    //       even if empty (no comments is not an error)
    sendResponse([
        'success' => true,
        'data' => $comments ?: []  
    ]);
}


/**
 * Function: Create a new comment
 * Method: POST with ?action=comment
 * 
 * Required JSON Body:
 *   - resource_id: The resource's database ID (required, must be numeric)
 *   - author:      Name of the comment author (required)
 *   - text:        Comment text content (required)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new comment id> }
 * Response (resource not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createComment($db, $data) {
    // TODO: Validate required fields — resource_id, author, and text
    // must all be present and not empty
    // If any are missing, return error response with HTTP 400
  if (
        empty($data['resource_id']) ||
        empty($data['author']) ||
        empty($data['text'])
    ) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'All fields are required.'
        ]);
        return;
    }

    $resourceId = $data['resource_id'];

    // TODO: Validate that resource_id is numeric
    // If not, return error response with HTTP 400
     if (!is_numeric($resourceId)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ]);
        return;
    }

    // TODO: Check that the resource exists in the resources table
    // If not found, return error response with HTTP 404
   $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);

    if ($check->rowCount() === 0) {
        http_response_code(404);
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ]);
        return;
    }

    // TODO: Sanitize author and text — trim whitespace
      $author = trim($data['author']);
    $text = trim($data['text']);

    // TODO: Prepare INSERT query
    // INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)
     $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");

    // TODO: Bind resource_id, author, and text; then execute
    $stmt->execute([$resourceId, $author, $text]);

    // TODO: If rowCount() > 0, return success response with HTTP 201
    //       and include the new id from $db->lastInsertId()
    // If failed, return error response with HTTP 500
    if ($stmt->rowCount() > 0) {

        http_response_code(201);

        sendResponse([
            'success' => true,
            'message' => 'Comment added successfully.',
            'id' => $db->lastInsertId()
        ]);

    } else {
        http_response_code(500);
        sendResponse([
            'success' => false,
            'message' => 'Failed to add comment.'
        ]);
    }
}


/**
 * Function: Delete a comment
 * Method: DELETE with ?comment_id={id}&action=delete_comment
 * 
 * Query Parameters:
 *   - comment_id: The comment's database ID (required)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Comment deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Comment not found." }
 */
function deleteComment($db, $commentId) {
    // TODO: Validate that $commentId is provided and is numeric
    // If not, return error response with HTTP 400
    if (!$commentId || !is_numeric($commentId)) {
        http_response_code(400);
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID.'
        ]);
        return;
    }

    // TODO: Check if the comment exists in comments_resource — SELECT by id
    // If not found, return error response with HTTP 404
  $check = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $check->execute([$commentId]);

    if ($check->rowCount() === 0) {
        http_response_code(404);
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ]);
        return;
    }

    // TODO: Prepare DELETE query
    // DELETE FROM comments_resource WHERE id = ?
     $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt = $db->prepare($sql);


    // TODO: Bind $commentId and execute
       $stmt->execute([$commentId]);

    // TODO: If rowCount() > 0, return success response with HTTP 200
    // If failed, return error response with HTTP 500
     if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ]);
    } else {
        http_response_code(500);
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment.'
        ]);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // TODO: Route the request based on $method and $action

    if ($method === 'GET') {

        // If action === 'comments', return all comments for a resource
        // TODO: Get resource_id from $_GET and call getCommentsByResourceId()
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        
        }

        // If 'id' is present in $_GET, return a single resource
        // TODO: Call getResourceById() with $_GET['id']
          elseif ($id) {
            getResourceById($db, $id);
        }

        // Otherwise, return all resources (supports ?search=, ?sort=, ?order=)
        // TODO: Call getAllResources()
             else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {


        // If action === 'comment', create a new comment
        // TODO: Call createComment() with the decoded request body
         if ($action === 'comment') {
            createComment($db, $data);
        }
         // Otherwise, create a new resource
        // TODO: Call createResource() with the decoded request body

        else {
            createResource($db, $data);
        }

       

    } elseif ($method === 'PUT') {

        // Update an existing resource
        // TODO: Call updateResource() with the decoded request body
         updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        // If action === 'delete_comment', delete a single comment
        // TODO: Get comment_id from $_GET and call deleteComment()
 if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        }
        // Otherwise, delete a resource
        // TODO: Get id from $_GET and call deleteResource()
         else {
            deleteResource($db, $resourceId);
        }

    } else {
        // TODO: Return HTTP 405 Method Not Allowed for unsupported methods
           http_response_code(405);
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
    }

} catch (PDOException $e) {
    // TODO: Log the error with error_log()
    // Return a generic HTTP 500 error — do NOT expose $e->getMessage() to the client
    error_log($e->getMessage());

    http_response_code(500);
    sendResponse([
        'success' => false,
        'message' => 'Database error.'
    ]);

} catch (Exception $e) {
    // TODO: Log the error with error_log()
    // Return HTTP 500 error response using sendResponse()
     error_log($e->getMessage());

    http_response_code(500);
    sendResponse([
        'success' => false,
        'message' => 'Server error.'
    ]);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper: Send a JSON response and stop execution.
 * 
 * @param array $data        Response payload. Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default: 200).
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Set the HTTP status code using http_response_code()
    http_response_code($statusCode);
    // TODO: Ensure $data is an array; if not, wrap it
     if (!is_array($data)) {
        $data = ['data' => $data];
    }

    // TODO: Echo json_encode($data) and call exit
    echo json_encode($data);
    exit;
}


/**
 * Helper: Validate a URL string.
 * 
 * @param  string $url
 * @return bool  True if the URL passes FILTER_VALIDATE_URL, false otherwise.
 */
function validateUrl($url) {
    // TODO: Use filter_var($url, FILTER_VALIDATE_URL)
    // Return true if valid, false otherwise
     return filter_var($url, FILTER_VALIDATE_URL) !== false;
}


/**
 * Helper: Sanitize a single input string.
 * 
 * @param  string $data
 * @return string  Trimmed, tag-stripped, and HTML-encoded string.
 */
function sanitizeInput($data) {
    // TODO: trim() → strip_tags() → htmlspecialchars(ENT_QUOTES, 'UTF-8')
    // Return the sanitized string
      $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    return $data;
}


/**
 * Helper: Check that all required fields exist and are non-empty in $data.
 * 
 * @param  array $data            Associative array of input data.
 * @param  array $requiredFields  List of field names that must be present.
 * @return array  ['valid' => bool, 'missing' => string[]]
 */
function validateRequiredFields($data, $requiredFields) {
    // TODO: Loop through $requiredFields
    // Collect any that are absent or empty in $data into a $missing array
    // Return ['valid' => (count($missing) === 0), 'missing' => $missing]
     $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return [
        'valid' => count($missing) === 0,
        'missing' => $missing
    ];
}

?>
