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
$teamId = $_POST['team_id'] ?? '';
$name = $_POST['name'] ?? '';
$dueDate = $_POST['due_date'] ?? null; // Add due_date parameter

if (empty($teamId) || empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Team ID and list name are required']);
    exit;
}

// Validate team_id exists
$teamId = $db->prepareData($teamId);
$sql = "SELECT id FROM teams WHERE id = '$teamId'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid team ID']);
    exit;
}

// Prepare the due_date for database insertion
$dueDate = !empty($dueDate) ? $db->prepareData($dueDate) : null;
$dueDateValue = $dueDate ? "'$dueDate'" : 'NULL'; // Handle NULL for database

// Insert new list into the lists table
$name = $db->prepareData($name);
$sql = "INSERT INTO lists (team_id, name, due_date) VALUES ('$teamId', '$name', $dueDateValue)";
if (mysqli_query($conn, $sql)) {
    $listId = mysqli_insert_id($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'List created successfully',
        'data' => [
            'list_id' => $listId,
            'team_id' => $teamId,
            'name' => $name,
            'due_date' => $dueDate // Return due_date in response
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create list: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
exit;
?>