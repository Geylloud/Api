<?php
header('Content-Type: application/json');
require_once "database.php";

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize database connection
$db = new Database();
$conn = $db->dbConnect();

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$response = ['status' => 'error', 'message' => ''];

switch ($method) {
    // Create a new task (Admin only)
    case 'POST':
        if (!isset($_POST['action'])) {
            $response['message'] = 'Action is required';
            break;
        }

        if ($_POST['action'] === 'create') {
            $task_name = trim($_POST['task_name'] ?? '');
            $deadline = trim($_POST['deadline'] ?? '');
            $team_id = trim($_POST['team_id'] ?? '');
            $list_id = trim($_POST['list_id'] ?? '');
            $admin_id = trim($_POST['admin_id'] ?? '');

            if (empty($task_name) || empty($team_id) || empty($list_id) || empty($admin_id)) {
                $response['message'] = 'Task name, team ID, list ID, and admin ID are required';
                break;
            }

            // Log the input values for debugging
            error_log("Create Task - Input: task_name=$task_name, team_id=$team_id, list_id=$list_id, admin_id=$admin_id");

            // Admin authorization check for creating a task
            $isAdmin = $db->isAdmin($admin_id, $team_id);
            error_log("Admin Check - admin_id=$admin_id, team_id=$team_id, isAdmin=" . ($isAdmin ? 'true' : 'false'));
            if (!$isAdmin) {
                $response['message'] = 'Unauthorized: Only admins can create tasks';
                break;
            }

            // Validate deadline format (YYYY-MM-DD)
            if (!empty($deadline)) {
                $date = DateTime::createFromFormat('Y-m-d', $deadline);
                if (!$date || $date->format('Y-m-d') !== $deadline) {
                    $response['message'] = 'Invalid deadline format. Use YYYY-MM-DD (e.g., 2025-05-03)';
                    break;
                }
            } else {
                $deadline = null; // Set to null if empty
            }

            $task_name = $db->prepareData($task_name);
            $team_id = (int)$team_id; // Ensure integer
            $list_id = (int)$list_id; // Ensure integer
            $deadline = $deadline ? $db->prepareData($deadline) : null;
            $status = 'To Do'; // Default status

            $sql = "INSERT INTO tasks (task_name, list_id, status, deadline, team_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissi", $task_name, $list_id, $status, $deadline, $team_id);

            if ($stmt->execute()) {
                $task_id = $conn->insert_id;
                $response['status'] = 'success';
                $response['message'] = 'Task created successfully';
                $response['data'] = ['task_id' => $task_id];
            } else {
                $response['message'] = 'Failed to create task: ' . $stmt->error;
            }
            $stmt->close();
        }

        // Assign members to a task (Admin only)
        elseif ($_POST['action'] === 'assign') {
            $task_id = trim($_POST['task_id'] ?? '');
            $user_ids = isset($_POST['user_ids']) ? (is_array($_POST['user_ids']) ? $_POST['user_ids'] : [$_POST['user_ids']]) : [];
            $admin_id = trim($_POST['admin_id'] ?? '');

            // Validate inputs
            if (empty($task_id)) {
                $response['message'] = 'Task ID is required';
                break;
            }
            if (empty($admin_id)) {
                $response['message'] = 'Admin ID is required';
                break;
            }
            if (empty($user_ids)) {
                $response['message'] = 'User IDs are required';
                break;
            }

            // Check if task exists and get its team_id
            $task_id = (int)$task_id; // Ensure integer
            $sql = "SELECT team_id FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            if (!$stmt->execute()) {
                $response['message'] = 'Database error while fetching task: ' . $stmt->error;
                break;
            }
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                $response['message'] = 'Task not found';
                break;
            }
            $task = $result->fetch_assoc();
            $team_id = $task['team_id'];
            $stmt->close();

            // Admin authorization check for assigning members
            if (!$db->isAdmin($admin_id, $team_id)) {
                $response['message'] = 'Unauthorized: Only admins can assign members';
                break;
            }

            // Validate user IDs
            $valid_user_ids = [];
            foreach ($user_ids as $user_id) {
                $user_id = (int)$user_id; // Ensure integer
                $sql = "SELECT id FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                if (!$stmt->execute()) {
                    $response['message'] = 'Database error while validating user ID ' . $user_id . ': ' . $stmt->error;
                    break 2; // Break out of the switch
                }
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $valid_user_ids[] = $user_id;
                }
                $stmt->close();
            }

            if (empty($valid_user_ids)) {
                $response['message'] = 'No valid user IDs provided';
                break;
            }

            // Insert assignments
            $sql = "INSERT IGNORE INTO task_assignments (task_id, user_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $response['message'] = 'Failed to prepare statement: ' . $conn->error;
                break;
            }
            $stmt->bind_param("ii", $task_id, $user_id);

            $success = true;
            foreach ($valid_user_ids as $user_id) {
                if (!$stmt->execute()) {
                    $success = false;
                    $response['message'] = 'Failed to assign user ID ' . $user_id . ': ' . $stmt->error;
                    break;
                }
            }

            if ($success) {
                $response['status'] = 'success';
                $response['message'] = 'Members assigned successfully';
            }
            $stmt->close();
        }
        break;

    // Edit task (Admin can edit all, members can edit task_name and status only)
    case 'PUT':
        parse_str(file_get_contents("php://input"), $_PUT);
        if (!isset($_PUT['action'])) {
            $response['message'] = 'Action is required';
            break;
        }

        if ($_PUT['action'] === 'edit') {
            $task_id = trim($_PUT['task_id'] ?? '');
            $user_id = trim($_PUT['user_id'] ?? '');
            $task_name = trim($_PUT['task_name'] ?? '');
            $deadline = trim($_PUT['deadline'] ?? '');
            $status = trim($_PUT['status'] ?? '');
            $is_completed = isset($_PUT['is_completed']) ? filter_var($_PUT['is_completed'], FILTER_VALIDATE_BOOLEAN) : null;

            if (empty($task_id) || empty($user_id)) {
                $response['message'] = 'Task ID and user ID are required';
                break;
            }

            // Fetch task details
            $task_id = (int)$task_id; // Ensure integer
            $sql = "SELECT team_id FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            if (!$stmt->execute()) {
                $response['message'] = 'Database error while fetching task: ' . $stmt->error;
                break;
            }
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                $response['message'] = 'Task not found';
                break;
            }
            $task = $result->fetch_assoc();
            $team_id = $task['team_id'];
            $stmt->close();

            // Authorization check: Only admins or assigned members can edit
            $is_admin = $db->isAdmin($user_id, $team_id);
            $is_assigned = $db->isAssignedToTask($user_id, $task_id);

            if (!$is_admin && !$is_assigned) {
                $response['message'] = 'Unauthorized: You are not assigned to this task or an admin';
                break;
            }

            $updates = [];
            $params = [];
            $types = '';

            // Members can edit task_name; admins can edit all fields
            if (!empty($task_name)) {
                $updates[] = "task_name = ?";
                $params[] = $db->prepareData($task_name);
                $types .= 's';
            }

            // Only admins can edit the deadline
            if (!empty($deadline) && $is_admin) {
                $date = DateTime::createFromFormat('Y-m-d', $deadline);
                if (!$date || $date->format('Y-m-d') !== $deadline) {
                    $response['message'] = 'Invalid deadline format. Use YYYY-MM-DD (e.g., 2025-05-03)';
                    break;
                }
                $updates[] = "deadline = ?";
                $params[] = $db->prepareData($deadline);
                $types .= 's';
            }

            // Handle checkbox (is_completed): Only admins or assigned members can update status
            if (isset($is_completed) && ($is_admin || $is_assigned)) {
                if ($is_completed) {
                    $updates[] = "status = ?";
                    $params[] = 'Done';
                    $types .= 's';
                } else {
                    // If unchecked, status can be "To Do" or "In Progress" (default to "To Do" if not specified)
                    $new_status = !empty($status) && in_array($status, ['To Do', 'In Progress']) ? $status : 'To Do';
                    $updates[] = "status = ?";
                    $params[] = $new_status;
                    $types .= 's';
                }
            } elseif (!empty($status) && ($is_admin || $is_assigned)) {
                // Direct status update (without checkbox)
                if ($status === 'Done') {
                    $updates[] = "status = ?";
                    $params[] = 'Done';
                    $types .= 's';
                } else {
                    $updates[] = "status = ?";
                    $params[] = in_array($status, ['To Do', 'In Progress']) ? $status : 'To Do';
                    $types .= 's';
                }
            }

            if (empty($updates)) {
                $response['message'] = 'No fields to update';
                break;
            }

            $sql = "UPDATE tasks SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $task_id;
            $types .= 'i';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Task updated successfully';
            } else {
                $response['message'] = 'Failed to update task: ' . $stmt->error;
            }
            $stmt->close();
        }
        break;

    // Consolidated GET requests
    case 'GET':
        if (!isset($_GET['action'])) {
            $response['message'] = 'Action is required';
            break;
        }

        try {
            if ($_GET['action'] === 'get_members') {
                $task_id = trim($_GET['task_id'] ?? '');

                if (empty($task_id)) {
                    $response['message'] = 'Task ID is required';
                    break;
                }

                $members = $db->getTaskMembers($task_id);
                $response['status'] = 'success';
                $response['message'] = 'Members retrieved successfully';
                $response['data'] = $members;
            } elseif ($_GET['action'] === 'get_tasks') {
                $team_id = trim($_GET['team_id'] ?? '');

                if (empty($team_id)) {
                    $response['message'] = 'Team ID is required';
                    break;
                }

                $tasks = $db->getTasksByTeamId($team_id);
                $response['status'] = 'success';
                $response['message'] = 'Tasks retrieved successfully';
                $response['data'] = $tasks;
            } elseif ($_GET['action'] === 'get_team_members') {
                $team_id = trim($_GET['team_id'] ?? '');

                if (empty($team_id)) {
                    $response['message'] = 'Team ID is required';
                    break;
                }

                $members = $db->getTeamMembers($team_id);
                $response['status'] = 'success';
                $response['message'] = 'Team members retrieved successfully';
                $response['data'] = $members;
            } else {
                $response['message'] = 'Invalid action';
            }
        } catch (Exception $e) {
            $response['message'] = 'Error processing request: ' . $e->getMessage();
        }
        break;

    // Delete a task (Admin only)
    case 'DELETE':
        parse_str(file_get_contents("php://input"), $_DELETE);
        if (!isset($_DELETE['action'])) {
            $response['message'] = 'Action is required';
            break;
        }

        if ($_DELETE['action'] === 'delete') {
            $task_id = trim($_DELETE['task_id'] ?? '');
            $admin_id = trim($_DELETE['admin_id'] ?? '');

            if (empty($task_id) || empty($admin_id)) {
                $response['message'] = 'Task ID and admin ID are required';
                break;
            }

            $task_id = (int)$task_id; // Ensure integer
            $sql = "SELECT team_id FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);
            if (!$stmt->execute()) {
                $response['message'] = 'Database error while fetching task: ' . $stmt->error;
                break;
            }
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                $response['message'] = 'Task not found';
                break;
            }
            $task = $result->fetch_assoc();
            $team_id = $task['team_id'];
            $stmt->close();

            // Admin authorization check for deleting a task
            if (!$db->isAdmin($admin_id, $team_id)) {
                $response['message'] = 'Unauthorized: Only admins can delete tasks';
                break;
            }

            $sql = "DELETE FROM tasks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $task_id);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Task deleted successfully';
            } else {
                $response['message'] = 'Failed to delete task: ' . $stmt->error;
            }
            $stmt->close();
        }
        break;

    default:
        $response['message'] = 'Invalid request method';
        break;
}

echo json_encode($response);
mysqli_close($conn);
exit;
?>