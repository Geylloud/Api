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
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $response["message"] = "Email and password are required";
    } else {
        $sql = "SELECT id, first_name, last_name, email, password, created_at FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $response["status"] = "success";
                $response["message"] = "Login successful";
                $response["data"] = [
                    "id" => (int)$row['id'], // Ensure id is an integer
                    "first_name" => $row['first_name'],
                    "last_name" => $row['last_name'],
                    "email" => $row['email'],
                    "created_at" => $row['created_at']
                ];
            } else {
                $response["message"] = "Your Email or Password is wrong";
            }
        } else {
            $response["message"] = "User not found";
        }
        $stmt->close();
    }
} else {
    $response["message"] = "Invalid request method";
}

$conn->close();
echo json_encode($response);
?>