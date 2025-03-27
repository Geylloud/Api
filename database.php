<?php
require "config.php";

class Database
{
    private $connect;
    private $sql;
    private $servername;
    private $username;
    private $password;
    private $databasename;

    public function __construct()
    {
        $config = new Config();
        $this->servername = $config->servername;
        $this->username = $config->username;
        $this->password = $config->password;
        $this->databasename = $config->databasename;
    }

    // Function to establish a database connection
    public function dbConnect()
    {
        $this->connect = mysqli_connect($this->servername, $this->username, $this->password, $this->databasename);

        if (!$this->connect) {
            error_log("Connection failed: " . mysqli_connect_error());
            die("Connection failed: " . mysqli_connect_error());
        }

        return $this->connect;
    }

    // Function to sanitize user input
    public function prepareData($data)
    {
        return mysqli_real_escape_string($this->connect, stripslashes(htmlspecialchars($data)));
    }

    public function emailExists($table, $email)
    {
        $email = $this->prepareData($email);
        $sql = "SELECT COUNT(*) FROM $table WHERE email = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return $count > 0;
    }

    // Function for user login
    public function logIn($table, $email, $password)
    {
        $email = $this->prepareData($email);
        $password = $this->prepareData($password);
        $this->sql = "SELECT * FROM $table WHERE email = '$email'";

        $result = mysqli_query($this->connect, $this->sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($password, $row['password'])) {
                return true; // Login successful
            }
        }

        return false; // Login failed
    }

    // Function for user signup
    public function signUp($table, $first_name, $last_name, $email, $password)
    {
        $first_name = $this->prepareData($first_name);
        $last_name = $this->prepareData($last_name);
        $email = $this->prepareData($email);
        $password = $this->prepareData($password);

        // Hash the password
        $password = password_hash($password, PASSWORD_DEFAULT);

        $this->sql = "INSERT INTO $table (first_name, last_name, email, password) VALUES ('$first_name', '$last_name', '$email', '$password')";

        if (mysqli_query($this->connect, $this->sql)) {
            return true; // Signup successful
        } else {
            return false; // Signup failed
        }
    }

    // Admin authorization method
    public function isAdmin($user_id, $team_id)
    {
        $user_id = (int)$user_id; // Ensure integer
        $team_id = (int)$team_id; // Ensure integer
        $sql = "SELECT * FROM teams WHERE id = ? AND admin_id = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param("ii", $team_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $isAdmin = $result->num_rows > 0;
        error_log("isAdmin Query - team_id=$team_id, user_id=$user_id, result_rows=" . $result->num_rows);
        $stmt->close();
        return $isAdmin;
    }

    public function isAssignedToTask($user_id, $task_id)
    {
        $user_id = (int)$user_id; // Ensure integer
        $task_id = (int)$task_id; // Ensure integer
        $sql = "SELECT * FROM task_assignments WHERE user_id = ? AND task_id = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param("ii", $user_id, $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    }

    public function getTaskMembers($taskId)
    {
        $taskId = (int)$taskId; // Ensure integer
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email 
                FROM users u 
                JOIN task_assignments ta ON u.id = ta.user_id 
                WHERE ta.task_id = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param("i", $taskId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => (int)$row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email']
            ];
        }
        $stmt->close();
        return $members;
    }

    public function getTasksByTeamId($teamId)
    {
        $teamId = (int)$teamId; // Ensure integer
        $sql = "SELECT t.id, t.list_id, t.task_name, t.status, t.deadline, t.created_at, t.team_id,
                       GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name, ' (', u.email, ')')) as assigned_members
                FROM tasks t
                LEFT JOIN task_assignments ta ON t.id = ta.task_id
                LEFT JOIN users u ON ta.user_id = u.id
                WHERE t.team_id = ?
                GROUP BY t.id";
        
        $stmt = $this->connect->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getTasksByTeamId: " . $this->connect->error);
            throw new Exception("Failed to prepare statement: " . $this->connect->error);
        }

        $stmt->bind_param("i", $teamId);
        if (!$stmt->execute()) {
            error_log("Execute failed in getTasksByTeamId: " . $stmt->error);
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            error_log("Get result failed in getTasksByTeamId: " . $stmt->error);
            throw new Exception("Failed to get result: " . $stmt->error);
        }

        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = [
                'id' => (int)$row['id'],
                'list_id' => (int)$row['list_id'],
                'task_name' => $row['task_name'],
                'status' => $row['status'],
                'deadline' => $row['deadline'],
                'created_at' => $row['created_at'],
                'team_id' => (int)$row['team_id'],
                'assigned_members' => $row['assigned_members'] ? explode(',', $row['assigned_members']) : []
            ];
        }
        $stmt->close();
        return $tasks;
    }

    public function getTeamMembers($teamId)
    {
        $teamId = (int)$teamId; // Ensure integer
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email 
                FROM users u 
                JOIN team_members tm ON u.id = tm.user_id 
                WHERE tm.team_id = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => (int)$row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email']
            ];
        }
        $stmt->close();
        return $members;
    }
}