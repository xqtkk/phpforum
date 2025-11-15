<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "NOT_LOGGED_IN";
    exit;
}

$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$post_id = (int)$_GET["id"];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM dislikes WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user_id, $post_id]);
$disliked = $stmt->fetch();

$stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user_id, $post_id]);
$liked = $stmt->fetch();

if ($disliked) {
    $pdo->prepare("DELETE FROM dislikes WHERE user_id = ? AND post_id = ?")
        ->execute([$user_id, $post_id]);

    $pdo->prepare("UPDATE posts SET dislikes = dislikes - 1 WHERE id = ?")
        ->execute([$post_id]);

    echo "UNDISLIKED";
    exit;
} else {
    $pdo->prepare("INSERT INTO dislikes (user_id, post_id) VALUES (?, ?)")
        ->execute([$user_id, $post_id]);

    $pdo->prepare("UPDATE posts SET dislikes = dislikes + 1 WHERE id = ?")
        ->execute([$post_id]);

    if ($liked) {
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")
            ->execute([$user_id, $post_id]);

        $pdo->prepare("UPDATE posts SET likes = likes - 1 WHERE id = ?")
            ->execute([$post_id]);

        echo "DISLIKED_REMOVED_LIKE";
        exit;
    }

    echo "DISLIKED";
    exit;
}
