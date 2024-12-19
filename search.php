<?php
// Start session
session_start();

if (empty($_SESSION['users'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['users'];
$conn = new mysqli('localhost', 'root', '', 'ubeapp');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search = $_POST['search'] ?? '';
    $searchTerm = '%' . $search . '%';

    $query = $conn->prepare("
        SELECT user_id, username 
        FROM Users 
        WHERE username LIKE ? AND user_id != ?
    ");
    $query->bind_param("si", $searchTerm, $user['user_id']);
    $query->execute();
    $result = $query->get_result();

    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
}

// Handle friend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
    $friend_id = intval($_POST['friend_id']);
    
    // Check if friendship already exists
    $checkFriendship = $conn->prepare("
        SELECT * FROM Friendships 
        WHERE (user_id = ? AND friend_id = ?) 
        OR (user_id = ? AND friend_id = ?)
    ");
    $checkFriendship->bind_param("iiii", $user['user_id'], $friend_id, $friend_id, $user['user_id']);
    $checkFriendship->execute();
    $existingFriendship = $checkFriendship->get_result()->fetch_assoc();

    if (!$existingFriendship) {
        // Add friendship to database
        $addFriendship = $conn->prepare("
            INSERT INTO Friendships (user_id, friend_id) VALUES (?, ?)
        ");
        $addFriendship->bind_param("ii", $user['user_id'], $friend_id);
        $addFriendship->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Users</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body id="bg_main">

    <!-- logo -->
    <div class="container bg-purple border-bottom border-secondary p-2 sticky-top">
        <a href="dashboard.php"><img src="images/logo.png" alt="logo" width="60" height="60" class="mx-auto d-block"></a>
    </div>
    <!-- Search Bar -->
    <div class="container-fluid mt-3 py-2 bg-none">
        <form method="POST" class="d-flex">
            <input type="text" name="search" placeholder="Search users by username" class="form-control me-2" required aria-label="Search">
            <button type="submit" class="btn btn-light text-violet fs-3"><i class='bx bx-send'></i></button>
        </form>
    </div>

    <div class="mb-4">
        <h4>Search Results</h4>
        <ul class="list-group">
            <?php if (!empty($searchResults)): ?>
                <?php foreach ($searchResults as $result): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($result['username']); ?>
                        <form method="POST" class="ms-2">
                            <input type="hidden" name="friend_id" value="<?php echo $result['user_id']; ?>">
                            <button type="submit" name="add_friend" class="btn btn-success btn-sm">Add Friend</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="list-group-item">No users found.</li>
            <?php endif; ?>
        </ul>
    </div>


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
                <!-- Updated "Create Post" button, now redirects to dashboard.php -->
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bx-plus-circle fs-3'></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="likes.php" class="nav-link">
                        <i class="fa-regular fa-heart fs-3"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fa-regular fa-user fs-3"></i>
                    </a>
                </li>
            </ul>
        </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>