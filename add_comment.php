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
$userId = $_SESSION['user_id'];

if ($content === '') {
    echo json_encode(['success'=>false, 'error'=>'Комментарий пустой']);
    exit;
}

// Добавляем комментарий
$stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, datetime('now'))");
$stmt->execute([$postId, $userId, $content]);

// Увеличиваем счетчик комментариев в посте
$pdo->prepare("UPDATE posts SET comments = comments + 1 WHERE id = ?")->execute([$postId]);

// Получаем данные пользователя для возврата
$stmt = $pdo->prepare("SELECT username, COALESCE(avatar,'default.png') AS avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

echo json_encode([
    'success' => true,
    'username' => $user['username'],
    'avatar' => $user['avatar'],
    'content' => htmlspecialchars($content)
]);
