<?php
header('Content-Type: application/json');
require_once "database.php";

$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$userId = $_GET['admin_id'] ?? ''; // Rename to user_id in future for clarity
if (empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit;
}

// Fetch teams where user is admin or member
$stmt = $conn->prepare("SELECT t.id, t.name, t.admin_id 
                        FROM teams t 
                        LEFT JOIN team_members tm ON t.id = tm.team_id 
                        WHERE t.admin_id = ? OR tm.member_id = ?");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$teams = [];
while ($row = $result->fetch_assoc()) {
    $teamId = $row['id'];
    $teams[] = [
        'team_id' => $teamId,
        'name' => $row['name'],
        'admin_id' => $row['admin_id']
    ];
}

echo json_encode(['status' => 'success', 'message' => 'Teams retrieved successfully', 'data' => $teams]);
$stmt->close();
mysqli_close($conn);
exit;
?>