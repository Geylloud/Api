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
if (empty($teamId)) {
    echo json_encode(['status' => 'error', 'message' => 'Team ID is required']);
    exit;
}

$teamId = $db->prepareData($teamId);
$sql = "SELECT id, name, due_date FROM lists WHERE team_id = '$teamId'";
$result = mysqli_query($conn, $sql);

$lists = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lists[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'due_date' => $row['due_date'] // Include due_date in the response
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $lists]);

mysqli_close($conn);
exit;
?>