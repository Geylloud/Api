<?php
header('Content-Type: application/json');
require_once 'database.php';

$db = new Database();
$conn = $db->dbConnect();

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $response["message"] = "Email is required";
    } else {
        $email = $db->prepareData($email); // Use your existing prepareData method for sanitization
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $response["status"] = "success";
            $response["message"] = "Email exists";
        } else {
            $response["message"] = "Email does not exist";
        }
        $stmt->close();
    }
} else {
    $response["message"] = "Invalid request method";
}

$conn->close();
echo json_encode($response);
?>