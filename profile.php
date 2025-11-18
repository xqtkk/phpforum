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
h2 { color:#fff; display:inline-block; margin-right:10px;}
.button-edit { padding:6px 12px; background:#007bff; border:none; border-radius:5px; color:#fff; cursor:pointer; text-decoration:none; }
.button-edit:hover { background:#0056b3; }
.avatar-preview { width:100px; height:100px; border-radius:50%; object-fit:cover; margin-bottom:10px; }
.section { margin-top:30px; }
.post, .comment { background:#2a2a2a; padding:10px; border-radius:5px; margin-bottom:10px; }
</style>
</head>
<body>
<div class="container">

<h2>Профиль пользователя: <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h2>

<?php if ($isOwner): ?>
    <a href="edit_profile.php" class="button-edit">Редактировать профиль</a>
<?php endif; ?>

<img src="avatars/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-preview"><br>
<p><b>Имя:</b> <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></p>
<p><b>Bio:</b> <?= nl2br(htmlspecialchars($user['bio'])) ?></p>

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
