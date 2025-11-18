<?php

require_once 'posts.php';
require_once 'functions.php';



// ==== LOGOUT ====
if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

function safeAvatar($file) {
    // Разрешаем только буквы, цифры, дефис, подчёркивание, точку
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
        return 'default.png';
    }

    // Проверяем, существует ли файл
    if (!file_exists(__DIR__ . '/avatars/' . $file)) {
        return 'default.png';
    }

    return $file;
}

function renderComments($items, $level = 0) {
    $maxLevel = 6;
    $indent = min($level, $maxLevel) * 25;
    foreach ($items as $c) {
        $avatar = htmlspecialchars(safeAvatar($c['avatar']));
        $cid = (int)$c['id'];
        $parentId = (int)$c['id'];

        echo '<div class="comment" data-id="'.$cid.'" style="margin-left:'.$indent.'px">';

        echo '<img src="avatars/'.$avatar.'" class="comment-avatar">';
        echo '<div>';
        echo '<b>'.htmlspecialchars($c['username']).'</b>: '.htmlspecialchars($c['content']);
        echo '<div class="comment-date" data-time="' . str_replace(' ', 'T', $c['created_at']) . 'Z" style="color:#777; font-size:12px;"></div>';
        echo '<div><a href="#" class="reply-link" data-id="'.$c['id'].'">Ответить</a></div>';
        // лайки/дизлайки
        $likeCount = $c['likes'] ?? 0;
        $dislikeCount = $c['dislikes'] ?? 0;
        $userReact = $c['user_reaction'] ?? null; // like / dislike / null

        $likeOpacity = ($userReact === 'like') ? 1 : 0.5;
        $dislikeOpacity = ($userReact === 'dislike') ? 1 : 0.5;

        echo "
        <div class='comment-reactions' style='margin-top:5px;'>
            <span style='cursor:pointer;' onclick='likeComment({$c['id']}, this)'>
                <img src=\"assets/like.svg\" class=\"comment-like-icon\" style=\"width:16px; opacity:$likeOpacity;\">
                <span class='comment-like-count'>$likeCount</span>
            </span>

            <span style='cursor:pointer; margin-left:10px;' onclick='dislikeComment({$c['id']}, this)'>
                <img src=\"assets/dislike.svg\" class=\"comment-dislike-icon\" style=\"width:16px; opacity:$dislikeOpacity;\">
                <span class='comment-dislike-count'>$dislikeCount</span>
            </span>
        </div>";


        // === КНОПКА "ПОКАЗАТЬ ОТВЕТЫ" ===
        if (!empty($c['children'])) {
            echo '<div class="show-replies" data-id="'.$c['id'].'" style="cursor:pointer; margin-top:5px;">
                    Показать ответы ('.count($c["children"]).')
                  </div>';
        }

        echo '</div>';
        echo '</div>';

        // === БЛОК ДЕТЕЙ (изначально скрыт) ===
        if (!empty($c['children'])) {
            echo '<div class="replies-block" data-parent="'.$parentId.'" style="display:none;">';
            renderComments($c['children'], $level + 1);
            echo '</div>';
        }
    }
}

?>

<!-- ====================== HTML ====================== -->
<!DOCTYPE html>
<html lang="ru">
<link rel="stylesheet" href="style.css">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
</head>
<body >

<?php include 'header.php'; ?>
<?php include 'filters.php'; ?>

<?php if (!$currentUser): ?>
    <div style="
        background: #ffe8e8;
        border: 1px solid #ffb3b3;
        padding: 15px;
        margin: 15px;
        border-radius: 10px;
        color: rgba(56, 53, 53, 1);
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
    ">
        <img src="assets/lock.svg" style="width:20px; height:20px; vertical-align:middle;">
        <span>
            Чтобы участвовать в обсуждениях, ставить лайки и создавать посты — 
            <a href="login.php" style="color:#a00;">войдите</a> или 
            <a href="register.php" style="color:#a00;">зарегистрируйтесь</a>.
        </span>
    </div>
<?php endif; ?>

<!-- ====================== POSTS ====================== -->
<div class="posts">
    <?php foreach ($posts as $p): ?>
        <?php include 'post_item.php'; ?>
    <?php endforeach; ?>
</div>

<!-- ====================== PAGINATION ====================== -->
<div class="pagination">
    <?php if ($page > 1): ?>
        <a class="page-btn" href="?page=<?= $page - 1 ?>">« Назад</a>
    <?php endif; ?>

    <span>Страница <?= $page ?></span>

    <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="?page=<?= $page + 1 ?>">Вперед »</a>
    <?php endif; ?>

</div>

<script src="main.js"></script>

</body>
</html>
