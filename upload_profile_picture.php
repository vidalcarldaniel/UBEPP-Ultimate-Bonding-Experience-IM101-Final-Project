<?php
session_start();

// Redirect to login.php if session does not exist
if (empty($_SESSION['users'])) {
    header('Location: login.php');
    exit();
}

// Set user session data
$user = $_SESSION['users'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ubeapp');

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    // Directory to store profile pictures
    $uploadDir = __DIR__ . '/uploads/profile_pictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique file name
    $fileName = time() . '_' . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $targetFilePath = $uploadDir . $fileName;

    // Validate file type
    $fileType = mime_content_type($_FILES['profile_picture']['tmp_name']);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if (in_array($fileType, $allowedTypes)) {
        // Validate file size (max 5MB)
        if ($_FILES['profile_picture']['size'] <= 5 * 1024 * 1024) {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
                $relativePath = 'uploads/profile_pictures/' . $fileName;

                // Update database
                $stmt = $conn->prepare("UPDATE Users SET profile_picture = ? WHERE user_id = ?");
                $stmt->bind_param("si", $relativePath, $user['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['users']['profile_picture'] = $relativePath;
                    header('Location: profile.php?success=Profile picture updated successfully');
                    exit();
                } else {
                    echo "Database update failed.";
                }
                $stmt->close();
            } else {
                echo "Error uploading file.";
            }
        } else {
            echo "File size exceeds the 5MB limit.";
        }
    } else {
        echo "Invalid file type. Only JPG, PNG, and GIF are allowed.";
    }
} else {
    echo "No file uploaded or an error occurred.";
}

$conn->close();
?>
