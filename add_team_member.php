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
$memberEmail = $_POST['member_email'] ?? '';
$adminId = $_POST['admin_id'] ?? '';

if (empty($teamId) || empty($memberEmail) || empty($adminId)) {
    echo json_encode(['status' => 'error', 'message' => 'Team ID, member email, and admin ID are required']);
    exit;
}

$teamId = $db->prepareData($teamId);
$sql = "SELECT id FROM teams WHERE id = '$teamId'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid team']);
    exit;
}

$adminId = $db->prepareData($adminId);
$sql = "SELECT id FROM users WHERE id = '$adminId'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Admin ID does not exist']);
    exit;
}

$memberEmail = $db->prepareData($memberEmail);
$sql = "SELECT id FROM users WHERE email = '$memberEmail'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Member email does not exist']);
    exit;
}

$member = mysqli_fetch_assoc($result);
$memberId = $member['id'];

// Check if member is already in team
$sql = "SELECT id FROM team_members WHERE team_id = '$teamId' AND user_id = '$memberId'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Member already in team']);
    exit;
}

$sql = "INSERT INTO team_members (team_id, user_id, role, status) VALUES ('$teamId', '$memberId', 'member', 'pending')";
if (mysqli_query($conn, $sql)) {
    $memberId = mysqli_insert_id($conn);
    echo json_encode(['status' => 'success', 'message' => 'Member added successfully', 'data' => ['member_id' => $memberId]]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add member: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
exit;
?>