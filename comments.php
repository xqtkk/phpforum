<?php
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$post_id = (int)($_GET['post_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 5;
$offset = ($page - 1) * $per_page;

$currentUserId = $_SESSION["user_id"] ?? 0;

/* === 1) Загружаем корневые комментарии === */
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.username,
        COALESCE(u.avatar,'default.png') AS avatar,

        (SELECT COUNT(*) FROM comment_reactions r 
         WHERE r.comment_id = c.id AND r.reaction = 'like') AS likes,

        (SELECT COUNT(*) FROM comment_reactions r 
         WHERE r.comment_id = c.id AND r.reaction = 'dislike') AS dislikes,

        (SELECT reaction FROM comment_reactions r
         WHERE r.comment_id = c.id AND r.user_id = ?
         LIMIT 1
        ) AS user_reaction

    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.post_id = ? AND c.parent_id = 0
    ORDER BY c.created_at ASC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $currentUserId, PDO::PARAM_INT);
$stmt->bindValue(2, $post_id, PDO::PARAM_INT);
$stmt->bindValue(3, $per_page, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$roots = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* === 2) Загружаем ответы на эти корни === */
$root_ids = array_column($roots, 'id');

$replies = [];
if ($root_ids) {
    $in  = implode(',', array_fill(0, count($root_ids), '?'));

    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            COALESCE(u.avatar,'default.png') AS avatar,

            (SELECT COUNT(*) FROM comment_reactions r 
             WHERE r.comment_id = c.id AND r.reaction = 'like') AS likes,

            (SELECT COUNT(*) FROM comment_reactions r 
             WHERE r.comment_id = c.id AND r.reaction = 'dislike') AS dislikes,

            (SELECT reaction FROM comment_reactions r
             WHERE r.comment_id = c.id AND r.user_id = ?
             LIMIT 1
            ) AS user_reaction

        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.parent_id IN ($in)
        ORDER BY c.created_at ASC
    ");

    // user_id + ids
    $params = array_merge([$currentUserId], $root_ids);
    $stmt->execute($params);

    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* === 3) Формируем дерево === */
$tree = [];
$refs = [];

foreach (array_merge($roots, $replies) as $c) {
    $c['children'] = [];
    $refs[$c['id']] = $c;

    if ($c['parent_id'] == 0) {
        $tree[$c['id']] = &$refs[$c['id']];
    } else if (isset($refs[$c['parent_id']])) {
        $refs[$c['parent_id']]['children'][$c['id']] = &$refs[$c['id']];
    }
}

/* === 4) Подсчёт страниц === */
$total = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id = 0");
$total->execute([$post_id]);
$total_roots = (int)$total->fetchColumn();
$total_pages = ceil($total_roots / $per_page);

echo json_encode([
    "comments" => $tree,
    "total_pages" => $total_pages,
    "current_page" => $page
]);
