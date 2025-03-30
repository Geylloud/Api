<?php
header('Content-Type: application/json');
require_once "database.php";

$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$teamId = $_GET['team_id'] ?? '';
$userId = $_GET['user_id'] ?? '';

if (empty($teamId) || empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'Team ID and User ID are required']);
    exit;
}

// Check if the user is a member of the team
$stmt = $conn->prepare("SELECT * FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'accepted'");
$stmt->bind_param("ii", $teamId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User is not a member of this team']);
    $stmt->close();
    mysqli_close($conn);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT id, name, due_date FROM lists WHERE team_id = ?");
$stmt->bind_param("i", $teamId);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'due_date' => $row['due_date']
    ];
}

if (empty($projects)) {
    echo json_encode(['status' => 'success', 'message' => 'No projects found for this team', 'data' => []]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Projects retrieved successfully', 'data' => $projects]);
}

$stmt->close();
mysqli_close($conn);
exit;
?>