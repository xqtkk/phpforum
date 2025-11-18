<?php
require_once "db.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    echo "NOT_LOGGED_IN";
    exit;
}

$user = $_SESSION["user_id"];
$comment = (int)$_GET["id"];

$stmt = $pdo->prepare("SELECT reaction FROM comment_reactions WHERE user_id=? AND comment_id=?");
$stmt->execute([$user, $comment]);
$existing = $stmt->fetchColumn();

if ($existing === false) {
    $pdo->prepare("INSERT INTO comment_reactions (user_id, comment_id, reaction) VALUES (?, ?, 'dislike')")
        ->execute([$user, $comment]);
    echo "DISLIKED";
}
elseif ($existing === "dislike") {
    $pdo->prepare("DELETE FROM comment_reactions WHERE user_id=? AND comment_id=?")
        ->execute([$user, $comment]);
    echo "UNDISLIKED";
}
elseif ($existing === "like") {
    $pdo->prepare("UPDATE comment_reactions SET reaction='dislike' WHERE user_id=? AND comment_id=?")
        ->execute([$user, $comment]);
    echo "DISLIKED_REMOVED_LIKE";
}
