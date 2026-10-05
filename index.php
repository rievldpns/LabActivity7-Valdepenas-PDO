<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'db.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Form Submissions via Transactions & Prepared Statements + CSRF Check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security validation failed (CSRF token mismatch).");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    } elseif ($action === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        if (!empty($content)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $content]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($action === 'add_comment') {
        $post_id = $_POST['post_id'] ?? null;
        $content = trim($_POST['comment_content'] ?? '');
        if ($post_id && !empty($content)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$post_id, $_SESSION['user_id'], $content]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($action === 'update_post') {
        $post_id = $_POST['post_id'] ?? null;
        $content = trim($_POST['content'] ?? '');
        if ($post_id && !empty($content)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE posts SET content = ?, is_edited = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $post_id, $_SESSION['user_id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($action === 'delete_post') {
        $post_id = $_POST['post_id'] ?? null;
        if ($post_id) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
                $stmt->execute([$post_id, $_SESSION['user_id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($action === 'update_comment') {
        $comment_id = $_POST['comment_id'] ?? null;
        $content = trim($_POST['content'] ?? '');
        if ($comment_id && !empty($content)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE comments SET content = ?, is_edited = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $comment_id, $_SESSION['user_id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($action === 'delete_comment') {
        $comment_id = $_POST['comment_id'] ?? null;
        if ($comment_id) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $_SESSION['user_id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
    header("Location: index.php");
    exit();
}

$edit_post = null;
$edit_comment = null;
if (isset($_GET['edit_post'])) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit_post'], $_SESSION['user_id']]);
    $edit_post = $stmt->fetch();
}
if (isset($_GET['edit_comment'])) {
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit_comment'], $_SESSION['user_id']]);
    $edit_comment = $stmt->fetch();
}

$posts = $pdo->query("SELECT posts.*, users.first_name, users.last_name FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feed - Blog Site</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 650px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        header h2 { margin: 0; font-size: 18px; color: #444; }
        .logout-btn { background: none; border: none; color: #d9534f; cursor: pointer; font-weight: 600; font-size: 14px; padding: 0; }
        .logout-btn:hover { text-decoration: underline; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; font-size: 16px; color: #007bff; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; resize: vertical; }
        input[type="text"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 600; margin-top: 8px; }
        button:hover { background: #0056b3; }
        .post-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .author-name { font-weight: 600; color: #222; }
        .post-time { font-size: 12px; color: #777; }
        .edited-tag { font-size: 12px; color: #888; font-style: italic; margin-left: 4px; }
        .post-content { font-size: 15px; line-height: 1.5; color: #444; margin-bottom: 12px; }
        
        /* Action buttons alignment */
        .post-actions { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
        .action-link { font-size: 13px; color: #007bff; text-decoration: none; }
        .action-link:hover { text-decoration: underline; }
        .delete-btn { background: none; border: none; color: #d9534f; cursor: pointer; padding: 0; font-size: 13px; font-weight: 600; margin: 0; }
        .delete-btn:hover { text-decoration: underline; }
        
        .comments-section { margin-top: 15px; background: #fafbfc; padding: 12px; border-radius: 6px; border: 1px solid #eaeaea; }
        .comments-section h4 { margin: 0 0 10px 0; font-size: 14px; color: #555; }
        .comment-item { font-size: 13px; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #eee; display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .comment-content-area { flex-grow: 1; word-break: break-word; }
        .comment-actions { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
        
        .comment-form { display: flex; gap: 8px; margin-top: 10px; }
        .comment-form input[type="text"] { flex-grow: 1; }
        .comment-form button { margin-top: 0; padding: 6px 12px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h2>Welcome, <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></h2>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </header>

        <!-- Inline Form for Editing Post -->
        <?php if ($edit_post): ?>
            <div class="card">
                <h3>Edit Post</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_post">
                    <input type="hidden" name="post_id" value="<?= $edit_post['id'] ?>">
                    <textarea name="content" rows="4" required><?= htmlspecialchars($edit_post['content']) ?></textarea><br>
                    <button type="submit">Update Post</button> 
                    <a href="index.php" class="action-link" style="margin-left: 10px;">Cancel</a>
                </form>
            </div>
        <!-- Inline Form for Editing Comment -->
        <?php elseif ($edit_comment): ?>
            <div class="card">
                <h3>Edit Comment</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_comment">
                    <input type="hidden" name="comment_id" value="<?= $edit_comment['id'] ?>">
                    <input type="text" name="content" value="<?= htmlspecialchars($edit_comment['content']) ?>" required style="width: 100%; margin-bottom: 10px;"><br>
                    <button type="submit">Update Comment</button> 
                    <a href="index.php" class="action-link" style="margin-left: 10px;">Cancel</a>
                </form>
            </div>
        <!-- Default Form: Create Post -->
        <?php else: ?>
            <div class="card">
                <h3>Create a Post</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="create_post">
                    <textarea name="content" rows="3" required placeholder="What's on your mind?"></textarea><br>
                    <button type="submit">Post</button>
                </form>
            </div>
        <?php endif; ?>

        <h3>Feed</h3>
        <?php foreach ($posts as $post): ?>
            <div class="card">
                <div class="post-header">
                    <div>
                        <span class="author-name"><?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?></span>
                        <?php if ($post['is_edited']): ?><span class="edited-tag">(Edited)</span><?php endif; ?>
                    </div>
                    <span class="post-time"><?= $post['created_at'] ?></span>
                </div>
                
                <div class="post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                
                <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                    <div class="post-actions">
                        <a href="index.php?edit_post=<?= $post['id'] ?>" class="action-link">Edit Post</a>
                        <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="delete-btn">Delete Post</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="comments-section">
                    <h4>Comments</h4>
                    <?php
                    $c_stmt = $pdo->prepare("SELECT comments.*, users.first_name, users.last_name FROM comments JOIN users ON comments.user_id = users.id WHERE comments.post_id = ? ORDER BY comments.created_at ASC");
                    $c_stmt->execute([$post['id']]);
                    $comments = $c_stmt->fetchAll();
                    ?>
                    
                    <?php if (count($comments) > 0): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-content-area">
                                    <strong><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?>:</strong> 
                                    <?= htmlspecialchars($comment['content']) ?>
                                    <?php if ($comment['is_edited']): ?><span class="edited-tag">(Edited)</span><?php endif; ?>
                                </div>
                                
                                <?php if ($comment['user_id'] == $_SESSION['user_id']): ?>
                                    <div class="comment-actions">
                                        <a href="index.php?edit_comment=<?= $comment['id'] ?>" class="action-link" style="font-size: 12px;">Edit</a>
                                        <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Delete this comment?');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <button type="submit" class="delete-btn" style="font-size: 12px;">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #777; margin: 5px 0;">No comments yet.</p>
                    <?php endif; ?>

                    <form method="POST" class="comment-form">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="text" name="comment_content" required placeholder="Write a comment...">
                        <button type="submit">Comment</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>