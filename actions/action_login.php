<?php
// Include the database connection file
include 'database.php';

session_start();

// Capture POST data
$username = $_POST['username'];
$password = $_POST['password'];

try {
    // Retrieve user by username
    $sql = "SELECT user_id, username, password FROM users WHERE username = :username";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $username]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify the password
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['users'] = $user;
        header('Location: ../dashboard.php');
        exit();
    } else {
        header('Location: ../login.php?error=Invalid credentials');
        exit();
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
