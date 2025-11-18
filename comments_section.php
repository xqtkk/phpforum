<div class="comments">
<?php
$perPage = 5;
$currentUserId = $_SESSION["user_id"] ?? 0;

/* === 1) Загружаем ВСЕ комментарии с лайками/дизлайками === */
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
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");

$stmt->execute([$currentUserId, $p['id']]);
$allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* === 2) Считаем корневые комментарии === */
$totalRootsStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM comments 
    WHERE post_id = ? AND parent_id = 0
");
$totalRootsStmt->execute([$p['id']]);
$totalRootsCount = (int)$totalRootsStmt->fetchColumn();
$totalPages = (int)ceil($totalRootsCount / $perPage);

/* === 3) Формируем refs === */
$refs = [];
foreach ($allComments as $c) {
    $c['children'] = [];
    $refs[$c['id']] = $c;
}

/* === 4) Формируем дерево === */
$treeAll = [];
foreach ($refs as $id => &$c) {
    if ((int)$c['parent_id'] === 0) {
        $treeAll[$id] = &$c;
    } else if (isset($refs[$c['parent_id']])) {
        $refs[$c['parent_id']]['children'][$id] = &$c;
    }
}

/* === 5) Берём первые 5 корневых === */
$rootIds = array_keys($treeAll);
$visibleRootIds = array_slice($rootIds, 0, $perPage, true);

$treePage = [];
foreach ($visibleRootIds as $rid) {
    $treePage[$rid] = $treeAll[$rid];
}
?>

<div class="comment-list">
    <?php renderComments($treePage); ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="comment-pagination"
        data-post="<?= $p['id'] ?>" 
        data-page="1" 
        data-total-pages="<?= $totalPages ?>">
        <button class="load-more" onclick="loadMoreComments(<?= $p['id'] ?>, this)">
            Показать ещё
        </button>
    </div>
<?php endif; ?>


<?php if ($currentUser): ?>
    <form method="POST" class="comment-form" onsubmit="return addComment(event, <?= $p['id'] ?>)">
        <input type="hidden" name="parent_id" value="0">
        <input type="text" class="comment-input" name="comment" placeholder="Написать комментарий..." required style="width:80%;">
        <button type="submit" class="comment-send">Отправить</button>
    </form>
<?php else: ?>
    <div style="color:#999;">Чтобы комментировать, войдите в аккаунт.</div>
<?php endif; ?>
</div>
