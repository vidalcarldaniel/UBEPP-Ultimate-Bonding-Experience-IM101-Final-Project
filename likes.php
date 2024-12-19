<?php
// Start session
session_start();

// Redirect to login.php if session does not exist
if (empty($_SESSION['users'])) {
    header('Location: login.php');
    exit();
}

// Set user
$user = $_SESSION['users'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ubeapp');

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Function to sanitize output
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fetch posts created by the logged-in user (dashboard)
$dashboardQuery = "
    SELECT posts.*, 
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.post_id) AS comment_count,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = ?) AS user_liked
    FROM posts
    WHERE posts.user_id = ?
    ORDER BY posts.created_at DESC
";
$dashboardStmt = $conn->prepare($dashboardQuery);
$dashboardStmt->bind_param("ii", $user['user_id'], $user['user_id']);
$dashboardStmt->execute();
$dashboardResult = $dashboardStmt->get_result();


// Handle like functionality
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'like') {
            $postId = intval($_POST['postId'] ?? 0); // Ensure $postId is valid and cast it to an integer
            if ($postId > 0) {
                $stmt = $conn->prepare("SELECT * FROM likes WHERE post_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $postId, $user['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Already liked, so remove like
                    $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $postId, $user['user_id']);
                    $stmt->execute();
                } else {
                    // Not liked, so add like
                    $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $postId, $user['user_id']);
                    $stmt->execute();
                }

                // Get the updated like count
                $stmt = $conn->prepare("SELECT COUNT(*) AS like_count FROM likes WHERE post_id = ?");
                $stmt->bind_param("i", $postId);
                $stmt->execute();
                $likeCountResult = $stmt->get_result();
                $likeCount = $likeCountResult->fetch_assoc()['like_count'] ?? 0;

                // Return the like count as JSON response
                echo json_encode(['likeCount' => $likeCount]);
                exit();
            } else {
                echo json_encode(['error' => 'Invalid post ID']);
                exit();
            }
        }
    }

    // Fetch posts from the user and their friends
    $query = $conn->prepare("
    SELECT Posts.content, Posts.created_at, Users.username 
    FROM Posts 
    JOIN Users ON Posts.user_id = Users.user_id
    WHERE Posts.user_id = ? OR Posts.user_id IN (
        SELECT friend_id FROM Friendships WHERE user_id = ?
    )
    ORDER BY Posts.created_at DESC
    ");
    $query->bind_param("ii", $user['user_id'], $user['user_id']);
    $query->execute();
    $posts = $query->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comments']) && isset($_POST['postId'])) {
        $comments = $conn->real_escape_string($_POST['comments']);
        $postId = intval($_POST['postId']);
    
        if (!empty($comments) && $postId > 0) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comments) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $postId, $user['user_id'], $comments);
            $stmt->execute();
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid comment or post ID']);
        }
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['postId'])) {
        $postId = intval($_GET['postId']);
    
        $stmt = $conn->prepare("SELECT c.comments, u.username, c.created_at 
                                FROM comments c
                                JOIN users u ON c.user_id = u.user_id
                                WHERE c.post_id = ?
                                ORDER BY c.created_at ASC");
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'username' => $row['username'],
                'comments' => $row['comments'],
                'created_at' => $row['created_at'],
            ];
        }
    
        echo json_encode($comments);
        exit();
    }
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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

    <!-- Dashboard Section -->  
    <div class="card-body bg-transparent mt-3 mb-5">
        <?php if ($dashboardResult->num_rows > 0): ?>
            <?php while ($row = $dashboardResult->fetch_assoc()): ?>
                <div class="card mb-3 bg-transparent border-0">
                    <div class="card-body bg-transparent">
                        <p><strong><?php echo ucfirst($row['username'] ?? 'Me'); ?>:</strong></p>
                        <?php if (!empty($row['content'])): ?>
                            <p><?php echo htmlspecialchars($row['content']); ?></p>
                        <?php endif; ?>

                        <?php if ($row['type'] === 'image' && $row['file_path']): ?>
                            <img src="<?php echo htmlspecialchars($row['file_path']); ?>" alt="Uploaded Image" class="img-fluid">
                        <?php elseif ($row['type'] === 'video' && $row['file_path']): ?>
                            <video controls class="w-100">
                                <source src="<?php echo htmlspecialchars($row['file_path']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php endif; ?>
                        <p class="text-muted"><small>Posted on: <?php echo $row['created_at']; ?></small></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No posts yet. Create your first post!</p>
        <?php endif; ?>
    </div>

    <!-- Footer Navbar -->
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

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.0/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Like button click
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function () {
                const postId = this.getAttribute('data-post-id');
                const likeCountElement = this.querySelector('.like-count');

                fetch('dashboard.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'like',
                        postId: postId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    likeCountElement.innerText = data.likeCount;
                });
            });
        });

            // Fetch comments for a post
    document.querySelectorAll('.comment-btn').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-post-id');
            document.getElementById('postId').value = postId;

            fetch(`dashboard.php?postId=${postId}`)
                .then(response => response.json())
                .then(comments => {
                    const commentsSection = document.getElementById('commentsSection');
                    commentsSection.innerHTML = '';
                    comments.forEach(comment => {
                        commentsSection.innerHTML += `
                            <div class="mb-2">
                                <strong>${comment.username}:</strong>
                                <p>${comment.comments}</p>
                                <small class="text-muted">${comment.created_at}</small>
                            </div>
                        `;
                    });
                });
        });
    });

    // Submit new comment
    document.getElementById('commentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const postId = document.getElementById('postId').value;
                    document.getElementById('newComment').value = '';
                    fetch(`dashboard.php?postId=${postId}`) // Reload comments
                        .then(response => response.json())
                        .then(comments => {
                            const commentsSection = document.getElementById('commentsSection');
                            commentsSection.innerHTML = '';
                            comments.forEach(comment => {
                                commentsSection.innerHTML += `
                                    <div class="mb-2">
                                        <strong>${comment.username}:</strong>
                                        <p>${comment.comments}</p>
                                        <small class="text-muted">${comment.created_at}</small>
                                    </div>
                                `;
                            });
                        });
                }
            });
    });

    </script>
</body>
</html>