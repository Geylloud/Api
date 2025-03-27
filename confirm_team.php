<?php
header('Content-Type: application/json');
require_once "database.php";

$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$teamId = $_POST['team_id'] ?? '';
$adminId = $_POST['admin_id'] ?? '';

if (empty($teamId) || empty($adminId)) {
    echo json_encode(['status' => 'error', 'message' => 'Team ID and admin ID are required']);
    exit;
}

$adminId = $db->prepareData($adminId);
$teamId = $db->prepareData($teamId);
$sql = "SELECT admin_id FROM teams WHERE id = '$teamId'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid team']);
    exit;
}

$team = mysqli_fetch_assoc($result);
$teamAdminId = $team['admin_id'];

$sql = "SELECT id FROM users WHERE id = '$adminId' AND id = '$teamAdminId'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Team confirmed successfully', 'data' => ['team_id' => $teamId]]);

mysqli_close($conn);
exit;
?>