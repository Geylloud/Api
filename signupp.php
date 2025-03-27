<?php
header('Content-Type: application/json');
require_once 'database.php';

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

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $response["message"] = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format";
    } elseif ($db->emailExists("users", $email)) {
        $response["message"] = "Email already exists";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id; // Get the newly created user's ID
            // Fetch the user data including created_at
            $sql = "SELECT id, first_name, last_name, email, created_at FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $response["status"] = "success";
            $response["message"] = "Sign up successful";
            $response["data"] = [
                "id" => (int)$user['id'], // Ensure id is an integer
                "first_name" => $user['first_name'],
                "last_name" => $user['last_name'],
                "email" => $user['email'],
                "created_at" => $user['created_at']
            ];
        } else {
            $response["message"] = "Failed to sign up: " . $conn->error;
        }
        $stmt->close();
    }
} else {
    $response["message"] = "Invalid request method";
}

$conn->close();
echo json_encode($response);
?>