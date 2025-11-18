<!-- ====================== HEADER ====================== -->
<header class="topbar">
    <div class="logo"><a href="index.php">Форум</a></div>
    <div>
        <?php if ($currentUser): ?>
            <a href="new_post.php" class="create-post-btn" title="Создать новый пост">+</a>
            <a href="profile.php">
                <img src="avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" class="avatar-small">
                <span><?= htmlspecialchars($currentUser['username']) ?></span>
            </a>
            <a href="?logout=1">Выход</a>
        <?php else: ?>
            <a href="login.php">Вход</a>
            <a href="register.php">Регистрация</a>
        <?php endif; ?>
    </div>
</header>