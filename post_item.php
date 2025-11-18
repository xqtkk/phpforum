<div class="post">
    <div class="post-header">
        <?php
        $avatar = !empty($p['avatar']) ? $p['avatar'] : "default.png";
        $displayName = !empty($p['display_name']) ? $p['display_name'] : $p['username'];
        ?>
        <a href="profile.php?id=<?= $p['user_id'] ?>">
            <img src="avatars/<?= htmlspecialchars($avatar) ?>" class="avatar" style="margin-right:10px;">
        </a>

        <div style="display:inline-block; vertical-align:top;">
            <div>
                <a href="profile.php?id=<?= $p['user_id'] ?>" style="text-decoration:none; font-weight:bold;">
                    <?= htmlspecialchars($displayName) ?>
                </a>
            </div>

            <div class="post-date" data-time="<?= str_replace(' ', 'T', $p['created_at']) ?>Z" style="color:#777; font-size:12px;"></div>

            <?php if (!empty($p['edited_at'])): ?>
                <div style="color:#777; font-size:12px;">(изменено)</div>
            <?php endif; ?>
        </div>
    </div>


    <div>
        <a class="title" href="post.php?id=<?= $p['id'] ?>">
            <?= htmlspecialchars($p['title']) ?>
        </a>
        
    </div>

    <div class="post-content">
     <?= nl2br(htmlspecialchars($p['content'])) ?>
    </div>

    <div class="post-info">
        <span>Категория: <?= htmlspecialchars($p['category_name']) ?></span>

        <?php 
            $liked = $p['user_like'] === 'like';
            $disliked = $p['user_dislike'] === 'dislike';
        ?>

        <span style="cursor:pointer;" onclick="likePost(<?= $p['id'] ?>, this)">
            <img src="assets/like.svg"
            class="like-icon"
            style="width:18px; vertical-align:middle; opacity: <?= $liked ? '1' : '0.5' ?>;">

            <span class="like-count"><?= $p['likes'] ?></span>
        </span>

        <span style="cursor:pointer;" onclick="dislikePost(<?= $p['id'] ?>, this)">
            <img src="assets/dislike.svg"
            class="dislike-icon"
            style="width:18px; vertical-align:middle; opacity: <?= $disliked ? '1' : '0.5' ?>;">

            <span class="dislike-count"><?= $p['dislikes'] ?></span>
        </span>

        <span class="comment-count">
            <img src="assets/comment.svg" style="width:18px; vertical-align:middle;">
            <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
                $stmt->execute([$p['id']]);
                $commentCount = $stmt->fetchColumn();
            ?>
            <span class="count-number"><?= $commentCount ?></span>
        </span>
    </div>

    <?php include 'comments_section.php'; ?>


</div>