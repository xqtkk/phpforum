<?php
// posts.php
require_once 'db.php';
require_once 'functions.php';

session_start();

$currentUser = currentUser($pdo);
$currentUserId = $currentUser ? $currentUser['id'] : 0;

// Pagination
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Filters
$order = "p.created_at DESC";
if (!empty($_GET["sort"])) {
    switch ($_GET["sort"]) {
        case "likes": $order="p.likes DESC"; break;
        case "dislikes": $order="p.dislikes DESC"; break;
        case "comments": $order="p.comments DESC"; break;
        case "date_old": $order="p.created_at ASC"; break;
    }
}
$category = $_GET["category"] ?? "";

// Count posts
$where = [];
$params = [];
if ($category !== "") {
    $where[] = "p.category_id = :cat";
    $params[":cat"] = $category;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";
$totalPosts = $pdo->prepare("SELECT COUNT(*) FROM posts p $whereSQL");
$totalPosts->execute($params);
$totalPages = ceil($totalPosts->fetchColumn() / $perPage);

// Получаем посты
$posts = getPosts($pdo, $page, $perPage, $order, $category, $currentUserId);

// Получаем категории
$cats = getCategories($pdo);
