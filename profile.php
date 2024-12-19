<?php
session_start();

// Redirect to login.php if session does not exist
if (empty($_SESSION['users']) || !isset($_SESSION['users']['user_id'])) {
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

// Fetch updated user details securely
$stmt = $conn->prepare("SELECT * FROM Users WHERE user_id = ?");
if ($stmt === false) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user['user_id']);
$stmt->execute();

$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc(); // Update user data
    $_SESSION['users'] = $user;    // Update session data
} else {
    // User not found, redirect to login
    header('Location: login.php');
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body id="bg_main">
    <!-- logo -->
    <div class="container bg-purple border-bottom border-secondary p-2 sticky-top">
        <a href="dashboard.php"><img src="images/logo.png" alt="logo" width="60" height="60" class="mx-auto d-block"></a>
    </div>

    <div class="container mt-4">
        <!-- Profile Section -->
        <h1 class="text-center"><?php echo ucfirst(htmlspecialchars($user['username'])); ?></h1>
        <img class="rounded-circle mx-auto d-block" 
        src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'images/user_profile.png'; ?>" 
        alt="Profile Picture" width="150" height="150">

        <!-- Upload Profile Picture Form -->
        <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="mt-3">
            <div class="mb-3">
                <label for="profile_picture" class="form-label">Upload Profile Picture</label>
                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>

        <!-- Logout -->
        <div class="d-flex flex-column mt-20 justify-content-end">
            <a href="logout.php" class="text-decoration-none">
                <h3 class="text-center text-dark">Logout</h3>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="navbar navbar-expand bg-violet fixed-bottom">
        <div class="container-fluid">
            <ul class="navbar-nav d-flex flex-row justify-content-around w-100 text-center">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bx-home-alt fs-3'></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="search.php" class="nav-link">
                        <i class="fa-solid fa-magnifying-glass fs-3"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bx-plus-circle fs-3'></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="likes.php" class="nav-link">
                        <i class='fa-regular fa-heart fs-3'></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class='fa-regular fa-user fs-3'></i>
                    </a>
                </li>
            </ul>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
