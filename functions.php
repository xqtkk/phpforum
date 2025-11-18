<?php

function currentUser($pdo) {
    if (empty($_SESSION['user_id'])) return null;

    $stmt = $pdo->prepare("
        SELECT id, username, COALESCE(avatar,'default.png') AS avatar
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCategories($pdo) {
    return $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
}

function getPosts($pdo, $page, $perPage, $order, $category, $userId) {
    $offset = ($page - 1) * $perPage;
    $params = [':uid' => $userId];

    $where = "";
    if ($category) {
        $where = "WHERE p.category_id = :cat";
        $params[':cat'] = $category;
    }

    $sql = "
        SELECT p.*, u.username, u.display_name,
               COALESCE(u.avatar,'default.png') AS avatar,
               c.name AS category_name,
               CASE WHEN l.user_id IS NOT NULL THEN 'like' END AS user_like,
               CASE WHEN d.user_id IS NOT NULL THEN 'dislike' END AS user_dislike
        FROM posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN likes l ON l.post_id = p.id AND l.user_id = :uid
        LEFT JOIN dislikes d ON d.post_id = p.id AND d.user_id = :uid
        $where
        ORDER BY $order
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildCommentTree($comments) {
    $refs = [];
    $tree = [];

    foreach ($comments as $c) {
        $c['children'] = [];
        $refs[$c['id']] = $c;
    }

    foreach ($refs as $id => &$c) {
        if ($c['parent_id'] == 0) {
            $tree[$id] = &$c;
        } else {
            $refs[$c['parent_id']]['children'][$id] = &$c;
        }
    }

    return $tree;
}

