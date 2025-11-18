<?php
session_start();
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'Не авторизован']);
    exit;
}

$postId = (int)$_POST['post_id'];
$content = trim($_POST['content']);
$parent_id = (int)($_POST['parent_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($content === '') {
    echo json_encode(['success'=>false, 'error'=>'Комментарий пустой']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO comments (post_id, user_id, parent_id, content)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$postId, $userId, $parent_id, $content]);

$commentId = $pdo->lastInsertId();

$pdo->prepare("UPDATE posts SET comments = comments + 1 WHERE id = ?")
    ->execute([$postId]);

$stmt = $pdo->prepare("
    SELECT username, COALESCE(avatar,'default.png') AS avatar 
    FROM users WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

echo json_encode([
    'success' => true,
    'id' => $commentId,
    'parent_id' => $parent_id,
    'username' => $user['username'],
    'avatar' => $user['avatar'],
    'content' => htmlspecialchars($content),
    'created_at' => date("Y-m-d H:i:s")
]);
