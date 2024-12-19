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

// Handle form submission for creating a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postType'])) {
    $postType = $conn->real_escape_string($_POST['postType']);
    $content = $conn->real_escape_string($_POST['content'] ?? '');
    $filePath = null;

    // Handle file upload for images or videos
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $fileName = time() . '_' . uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFilePath = $uploadDir . $fileName;

        // Check file type and extension
        $fileType = mime_content_type($_FILES['file']['tmp_name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allowed file types (images and videos)
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg'];

        $isValidImage = in_array($fileType, $allowedImageTypes);
        $isValidVideo = in_array($fileType, $allowedVideoTypes);

        if (($postType === 'image' && $isValidImage) || ($postType === 'video' && $isValidVideo)) {
            // Validate file size (5MB max)
            if ($_FILES['file']['size'] <= 5 * 1024 * 1024) {
                // Move file to the upload directory
                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
                    $filePath = $targetFilePath;
                } else {
                    echo "<p class='text-danger'>Error uploading file. Please try again.</p>";
                }
            } else {
                echo "<p class='text-danger'>File too large. Maximum size is 5MB.</p>";
            }
        } else {
            echo "<p class='text-danger'>Invalid file type. Only JPEG, PNG, GIF images, and MP4 videos are allowed.</p>";
        }
    }

    // Insert post into the database
    $stmt = $conn->prepare("INSERT INTO posts (user_id, type, content, file_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user['user_id'], $postType, $content, $filePath);
    $stmt->execute();
    $stmt->close();
}

// Fetch posts for timeline (user + friends)
// Fetch posts for timeline (user + friends)
$timelineQuery = "
    SELECT posts.*, users.username, users.profile_picture, 
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.post_id) AS comment_count,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = ?) AS user_liked
    FROM posts
    INNER JOIN users ON posts.user_id = users.user_id
    WHERE posts.user_id = ? 
       OR posts.user_id IN (
            SELECT friend_id FROM friendships WHERE user_id = ? 
            UNION
            SELECT user_id FROM friendships WHERE friend_id = ?
        )
    ORDER BY posts.created_at DESC
";
$timelineStmt = $conn->prepare($timelineQuery);
$timelineStmt->bind_param("iiii", $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id']);
$timelineStmt->execute();
$timelineResult = $timelineStmt->get_result();

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
    <!-- Timeline Section -->
    <div class="mt-5 mb-5 bg-transparent">
            <?php while ($row = $timelineResult->fetch_assoc()): ?>
                <div class="card mb-3 bg-transparent border-0">
                    <div class="card-body bg-transparent">
                        <div class="d-flex">
                            <img src="<?php echo htmlspecialchars($row['profile_picture']); ?>" alt="Profile Picture" class="rounded-circle me-3" width="50" height="50">
                            <p class="mt-2"><strong><?php echo ucfirst($row['username'] ?? 'Anonymous'); ?></strong></p>
                            <p class="text-muted mt-2 ms-5"><small>Posted on: <br><?php echo $row['created_at']; ?></small></p>
                        </div>

                        <?php if (!empty($row['content'])): ?>
                            <p><?php echo htmlspecialchars($row['content']); ?></p>
                        <?php endif; ?>

                        <?php if ($row['type'] === 'image' && !empty($row['file_path'])): ?>
                            <img src="<?php echo htmlspecialchars($row['file_path']); ?>" alt="Uploaded Image" class="img-fluid">
                        <?php elseif ($row['type'] === 'video' && !empty($row['file_path'])): ?>
                            <video controls class="w-100">
                                <source src="<?php echo htmlspecialchars($row['file_path']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <p class="text-muted">No additional content available.</p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mt-3">
                            <button class="btn btn-outline-danger like-btn" data-post-id="<?php echo $row['post_id']; ?>">
                                <i class='bx bx-heart'></i>(<span class="like-count"><?php echo $row['like_count'] ?? 0; ?></span>)
                            </button>
                            <button class="btn btn-outline-secondary comment-btn" data-bs-toggle="modal" data-bs-target="#commentModal" data-post-id="<?php echo $row['post_id']; ?>">
                                <i class='bx bx-comment'></i> Comments(<span class="comment-count"><?php echo $row['comment_count'] ?? 0; ?></span>)
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
    </div>
           
    <!-- Comment Modal -->
    <div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentModalLabel">Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="commentForm">
                        <div class="mb-3">
                            <label for="newComment" class="form-label">Write a comment</label>
                            <textarea class="form-control" id="newComment" name="comments" rows="3" required></textarea>
                        </div>
                        <input type="hidden" id="postId" name="postId">
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                    <hr>
                    <div id="commentsSection"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Creating a Post -->
    <div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="postModalLabel">Create a Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Post Type</label>
                            <div class="btn-group w-100" role="group" aria-label="Post Type">
                                <input type="radio" class="btn-check" name="postType" id="textPost" value="text" required>
                                <label class="btn btn-outline-primary" for="textPost">Text</label>

                                <input type="radio" class="btn-check" name="postType" id="imagePost" value="image" required>
                                <label class="btn btn-outline-success" for="imagePost">Image</label>

                                <input type="radio" class="btn-check" name="postType" id="videoPost" value="video" required>
                                <label class="btn btn-outline-danger" for="videoPost">Video</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="postContent" class="form-label">Content</label>
                            <textarea class="form-control" id="postContent" name="content" rows="3" placeholder="Write something..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="fileUpload" class="form-label">Upload File (Optional)</label>
                            <input type="file" class="form-control" id="fileUpload" name="file">
                        </div>

                        <button type="submit" class="btn btn-primary">Post</button>
                    </form>
                </div>
            </div>
        </div>
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
                    <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#postModal">
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