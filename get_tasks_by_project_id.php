<?php
header('Content-Type: application/json');
require_once "database.php";

$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$projectId = $_GET['project_id'] ?? '';
$userId = $_GET['user_id'] ?? '';

if (empty($projectId) || empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'Project ID and User ID are required']);
    exit;
}

// Validate project_id and get team_id
$stmt = $conn->prepare("SELECT team_id FROM lists WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid project ID']);
    $stmt->close();
    mysqli_close($conn);
    exit;
}

$project = $result->fetch_assoc();
$teamId = $project['team_id'];
$stmt->close();

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

// Fetch tasks
$stmt = $conn->prepare("SELECT t.id AS task_id, t.list_id, t.task_name, t.status, t.deadline, t.created_at, t.team_id
                        FROM tasks t 
                        WHERE t.list_id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $taskId = $row['task_id'];
    $task = [
        'id' => $taskId,
        'list_id' => $row['list_id'],
        'task_name' => $row['task_name'],
        'status' => $row['status'],
        'deadline' => $row['deadline'],
        'created_at' => $row['created_at'],
        'team_id' => $row['team_id'],
        'assigned_users' => []
    ];

    // Fetch assigned users for this task
    $userStmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role 
                                FROM task_assignments ta 
                                JOIN users u ON ta.user_id = u.id 
                                WHERE ta.task_id = ?");
    $userStmt->bind_param("i", $taskId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    $assignedUsers = [];
    while ($userRow = $userResult->fetch_assoc()) {
        $assignedUsers[] = [
            'id' => $userRow['id'],
            'first_name' => $userRow['first_name'],
            'last_name' => $userRow['last_name'],
            'email' => $userRow['email'],
            'role' => $userRow['role']
        ];
    }
    $task['assigned_users'] = $assignedUsers;

    $userStmt->close();
    $tasks[] = $task;
}

echo json_encode(['status' => 'success', 'data' => $tasks]);

$stmt->close();
mysqli_close($conn);
exit;
?>