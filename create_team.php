<?php
header('Content-Type: application/json');
require_once "database.php";

$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get POST data (use $_POST for form data)
$name = $_POST['name'] ?? '';
$adminId = $_POST['admin_id'] ?? '';

if (empty($name) || empty($adminId)) {
    echo json_encode(['status' => 'error', 'message' => 'Team name and admin ID are required']);
    exit;
}

// Validate admin_id exists in users table
$adminId = $db->prepareData($adminId);
$sql = "SELECT id FROM users WHERE id = '$adminId'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Admin ID does not exist']);
    exit;
}

// Insert new team
$sql = "INSERT INTO teams (name, admin_id) VALUES ('" . $db->prepareData($name) . "', '$adminId')";
if (mysqli_query($conn, $sql)) {
    $teamId = mysqli_insert_id($conn);
    echo json_encode(['status' => 'success', 'message' => 'Team created successfully', 'data' => ['team_id' => $teamId]]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create team: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
exit;
?>