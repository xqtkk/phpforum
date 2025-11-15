<?php
session_start();

// ==== LOGOUT ====
if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// 1) СНАЧАЛА подключаем БД
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

// 2) Потом получаем текущего пользователя
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, COALESCE(avatar, 'default.png') AS avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
}

// 3) Дальше — создание таблиц, выборка постов и всё остальное
// ====================== CREATE TABLES IF NOT EXISTS ======================
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    avatar TEXT
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    title TEXT,
    content TEXT,
    likes INTEGER DEFAULT 0,
    dislikes INTEGER DEFAULT 0,
    comments INTEGER DEFAULT 0,
    category_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    edited_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
");

// ====================== INSERT DEFAULT DATA IF EMPTY ======================
$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count == 0) {
    $pdo->exec("
        INSERT INTO users (username, avatar) VALUES
        ('Admin', 'default.png'),
        ('User1', 'default.png');
    ");

    $pdo->exec("
        INSERT INTO categories (name) VALUES
        ('Новости'),
        ('Обсуждение'),
        ('Программирование');
    ");

    $pdo->exec("
        INSERT INTO posts (user_id, title, content, likes, dislikes, comments, category_id)
        VALUES
        (1, 'Добро пожаловать!', 'Первый пост на форуме!', 10, 1, 3, 1),
        (2, 'PHP + SQLite', 'Полностью рабочий форум!', 5, 0, 0, 3),
        (1, 'Новости проекта', 'Скоро будет больше функций!', 2, 0, 1, 1);
    ");
}

// ====================== PAGINATION ======================
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// ====================== FILTERS ======================
$order = "p.created_at DESC";

if (!empty($_GET["sort"])) {
    switch ($_GET["sort"]) {
        case "likes":
            $order = "p.likes DESC";
            break;
        case "dislikes":
            $order = "p.dislikes DESC";
            break;
        case "comments":
            $order = "p.comments DESC";
            break;
        case "date_old":
            $order = "p.created_at ASC";
            break;
    }
}

$category = $_GET["category"] ?? "";

// ====================== BUILD QUERY ======================
$sql = "
SELECT 
    p.*, 
    u.username,
    COALESCE(u.avatar, 'default.png') AS avatar,
    c.name AS category_name
FROM posts p
JOIN users u ON p.user_id = u.id
LEFT JOIN categories c ON p.category_id = c.id
";

$where = [];
$params = [];

if ($category !== "") {
    $where[] = "p.category_id = :cat";
    $params[":cat"] = $category;
}

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY $order LIMIT :limit OFFSET :offset";

// ====================== EXECUTE ======================
$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_INT);
}

$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();

$posts = $stmt->fetchAll();

// Load categories for filter
$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<!-- ====================== HTML ====================== -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
    <style>
        body { font-family: sans-serif; background: #f5f5f5; margin: 0; }
        .topbar {
            background: #333; color: #fff; padding: 15px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .topbar a { color: #fff; text-decoration: none; margin-left: 15px; }
        .logo a { font-size: 22px; font-weight: bold; }
        .avatar-small { width: 32px; height: 32px; border-radius: 50%; vertical-align: middle; }

        .filters { background: #fff; padding: 15px; margin: 15px; border-radius: 10px; }
        .posts { padding: 15px; }
        .post { background: #fff; margin-bottom: 15px; padding: 15px; border-radius: 10px; }

        .post-header { display: flex; align-items: center; }
        .avatar { width: 48px; height: 48px; border-radius: 50%; margin-right: 10px; }

        .title { font-size: 20px; font-weight: bold; text-decoration: none; color: #333; }

        .post-info span { margin-right: 15px; color: #555; }

        .pagination { text-align: center; margin: 20px; }
        .page-btn { margin: 0 10px; text-decoration: none; }
    </style>
</head>
<body>

<!-- ====================== HEADER ====================== -->
<header class="topbar">
    <div class="logo"><a href="index.php">Форум</a></div>
    <div>
        <?php if ($currentUser): ?>
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

<!-- ====================== FILTERS ====================== -->
<div class="filters">
    <form method="GET" class="filter-form">
        <select name="category" class="filter-select">
            <option value="">Все категории</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($category == $c['id'] ? "selected" : "") ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="filter-select">
            <option value="">По умолчанию</option>
            <option value="likes">По лайкам</option>
            <option value="dislikes">По дизлайкам</option>
            <option value="comments">По комментариям</option>
            <option value="date_old">Старые сначала</option>
        </select>

        <button class="filter-btn">Применить</button>
    </form>
</div>

<style>
/* Стили фильтров */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.filter-select {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background: #fff;
    font-size: 14px;
    min-width: 150px;
    cursor: pointer;
    transition: border 0.2s, box-shadow 0.2s;
}

.filter-select:focus {
    border-color: #333;
    outline: none;
    box-shadow: 0 0 3px rgba(0,0,0,0.2);
}

.filter-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    background: #333;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
}

.filter-btn:hover {
    background: #555;
}
</style>


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
        <div class="post">
            <div class="post-header">
                <?php
                $avatar = (!empty($p['avatar'])) ? $p['avatar'] : "default.png";
                ?>
                <img src="avatars/<?= htmlspecialchars($avatar) ?>" class="avatar">

                <div>
                    <div><b><?= htmlspecialchars($p['username']) ?></b></div>
                    <div><?= $p['created_at'] ?> <?= $p['edited_at'] ? "(изменено)" : "" ?></div>
                </div>
            </div>

            <div>
                <a class="title" href="post.php?id=<?= $p['id'] ?>">
                    <?= htmlspecialchars($p['title']) ?>
                </a>
            </div>

            <div class="post-info">
                <span>Категория: <?= htmlspecialchars($p['category_name']) ?></span>

                <span>
                    <img src="assets/like.svg" style="width:18px; vertical-align:middle;">
                    <?= $p['likes'] ?>
                </span>

                <span>
                    <img src="assets/dislike.svg" style="width:18px; vertical-align:middle;">
                    <?= $p['dislikes'] ?>
                </span>

                <span>
                    <img src="assets/comment.svg" style="width:18px; vertical-align:middle;">
                    <?= $p['comments'] ?>
                </span>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<!-- ====================== PAGINATION ====================== -->
<div class="pagination">
    <?php if ($page > 1): ?>
        <a class="page-btn" href="?page=<?= $page - 1 ?>">« Назад</a>
    <?php endif; ?>

    <span>Страница <?= $page ?></span>

    <?php if (count($posts) == $perPage): ?>
        <a class="page-btn" href="?page=<?= $page + 1 ?>">Вперед »</a>
    <?php endif; ?>
</div>

</body>
</html>
