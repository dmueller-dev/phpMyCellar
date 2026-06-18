<?php
// Define a constant to protect included files from direct access
define('INCLUDED_VIA_APP', true);

// Include initialization (handles session and db)
require_once __DIR__ . '/includes/init.php';

global $mysqli, $conn;

// Set response header to JSON
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['status' => 'error', 'message' => 'Access Denied: You must be logged in.']);
  exit;
}

$user_id = $_SESSION['user_id'];

// Accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST is allowed.']);
  exit;
}

// CSRF Validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
  echo json_encode(['status' => 'error', 'message' => 'Security check failed. Please refresh and try again.']);
  exit;
}

$item_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$item_type = $_POST['type'] ?? '';

// Validate parameters
if ($item_id <= 0 || ($item_type !== 'wine' && $item_type !== 'tnote' && $item_type !== 'blog')) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid parameters provided.']);
  exit;
}

try {
  $action = toggleSubscription($conn, $user_id, $item_id, $item_type);
  echo json_encode(['status' => 'success', 'action' => $action]);
} catch (Exception $e) {
  echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
