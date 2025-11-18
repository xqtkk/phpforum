<?php
session_start();
require_once 'db.php'; // подключение к SQLite через PDO

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$viewUserId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;

// Получаем данные пользователя, чей профиль открываем
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$viewUserId]);
$user = $stmt->fetch();
if (!$user) {
    die("Пользователь не найден.");
}

$isOwner = ($currentUserId === $viewUserId);

$errors = [];
$success = '';

if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Редактирование своего профиля
    $displayName = trim($_POST['display_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if ($displayName && $username) {
        // Проверка уникальности username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $currentUserId]);
        if ($stmt->fetch()) {
            $errors[] = "Имя пользователя уже занято.";
        } else {
            // Загрузка аватарки
            $avatar = $user['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = "avatar_{$currentUserId}." . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . "/avatars/$filename");
                $avatar = $filename;
            }

            $stmt = $pdo->prepare("UPDATE users SET display_name = ?, username = ?, bio = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$displayName, strtolower($username), $bio, $avatar, $currentUserId]);
            $success = "Профиль обновлён!";
            header("Refresh:0");
        }
    } else {
        $errors[] = "Заполните все обязательные поля!";
    }
}

// Получаем посты пользователя
$myPosts = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$myPosts->execute([$viewUserId]);
$myPosts = $myPosts->fetchAll();

// Получаем лайкнутые посты
$likedPosts = $pdo->prepare("
    SELECT p.* FROM posts p
    JOIN post_likes pl ON p.id = pl.post_id
    WHERE pl.user_id = ? AND pl.reaction = 'like'
    ORDER BY p.created_at DESC
");
$likedPosts->execute([$viewUserId]);
$likedPosts = $likedPosts->fetchAll();

// Получаем комментарии пользователя
$comments = $pdo->prepare("SELECT c.*, p.title as post_title FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$comments->execute([$viewUserId]);
$comments = $comments->fetchAll();
?>



<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Профиль</title>
<style>
body { background:#121212; color:#e0e0e0; font-family: Arial, sans-serif; margin:0; padding:0;}
.container { max-width:900px; margin:50px auto; padding:20px; background:#1e1e1e; border-radius:10px; }
h2 { color:#fff; }
input, textarea { width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #444; background:#2c2c2c; color:#fff; }
button { padding:10px 20px; background:#007bff; border:none; border-radius:5px; color:#fff; cursor:pointer; }
button:hover { background:#0056b3; }
.avatar-preview { width:100px; height:100px; border-radius:50%; object-fit:cover; margin-bottom:10px; }
.section { margin-top:30px; }
.post, .comment { background:#2a2a2a; padding:10px; border-radius:5px; margin-bottom:10px; }
</style>
</head>
<body>
<div class="container">
<h2>Профиль пользователя: <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h2>

<?php if ($isOwner): ?>
    <?php foreach($errors as $e): ?>
        <div style="color:#ff6b6b; margin-bottom:10px;"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if($success): ?>
        <div style="color:#4caf50; margin-bottom:10px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <img src="avatars/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-preview"><br>
        <label>Аватарка</label>
        <input type="file" name="avatar" accept="image/*"><br>
        <label>Отображаемое имя</label>
        <input type="text" name="display_name" value="<?= htmlspecialchars($user['display_name']) ?>" required><br>
        <label>Имя для входа</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required><br>
        <label>Bio</label>
        <textarea name="bio"><?= htmlspecialchars($user['bio']) ?></textarea><br>
        <button type="submit">Сохранить</button>
    </form>
<?php else: ?>
    <img src="avatars/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-preview"><br>
    <p><b>Имя:</b> <?= htmlspecialchars($user['display_name']) ?></p>
    <p><b>Bio:</b> <?= nl2br(htmlspecialchars($user['bio'])) ?></p>
<?php endif; ?>

<div class="section">
<h3>Посты пользователя</h3>
<?php foreach($myPosts as $p): ?>
    <div class="post"><?= htmlspecialchars($p['title']) ?></div>
<?php endforeach; ?>
</div>

<div class="section">
<h3>Понравившиеся посты</h3>
<?php foreach($likedPosts as $p): ?>
    <div class="post"><?= htmlspecialchars($p['title']) ?></div>
<?php endforeach; ?>
</div>

<div class="section">
<h3>История комментариев</h3>
<?php foreach($comments as $c): ?>
    <div class="comment"><b><?= htmlspecialchars($c['post_title']) ?>:</b> <?= htmlspecialchars($c['content']) ?></div>
<?php endforeach; ?>
</div>

</div>
</body>
</html>
