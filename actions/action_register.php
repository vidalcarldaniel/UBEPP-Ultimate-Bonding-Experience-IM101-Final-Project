<?php
// Include the database connection file
include 'database.php';

session_start();

// Capture POST data
$name = $_POST['name'];
$username = $_POST['username'];
$password = $_POST['password'];
$date_now = date('Y-m-d');

// Validate inputs
if (empty($name) || empty($username) || empty($password)) {
    die("All fields are required!");
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

try {
    // Insert user data into the database
    $sql = "INSERT INTO users (name, username, password, date_joined) VALUES (:name, :username, :password, :date_joined)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':username' => $username,
        ':password' => $hashed_password,
        ':date_joined' => $date_now,
    ]);

    // Start user session and get the user ID
    $user_id = $conn->lastInsertId();

    // Retrieve user data
    $sql2 = "SELECT user_id, name, username, date_joined FROM users WHERE user_id = :user_id";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([':user_id' => $user_id]);
    $_SESSION['users'] = $stmt2->fetch(PDO::FETCH_ASSOC);

    // Redirect to login
    header('Location: ../login.php');
    exit();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
