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
        INSERT INTO posts (user_id, title, content, likes, dislikes, category_id)
        VALUES
        (1, 'Добро пожаловать!', 'Первый пост на форуме!', 10, 1, 1),
        (2, 'PHP + SQLite', 'Полностью рабочий форум!', 5, 0, 3),
        (1, 'Новости проекта', 'Скоро будет больше функций!', 2, 0, 1);
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

    <style>
        /* Комментарии */
        .comments {
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .comment-list .comment {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f9f9f9;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .comment-list .comment b {
            margin-right: 5px;
        }

        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .comment-input {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
            transition: all 0.2s;
        }

        .comment-input:focus {
            border-color: #333;
            box-shadow: 0 0 4px rgba(0,0,0,0.15);
            outline: none;
        }

        .comment-send {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            background: #333;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .comment-send:hover {
            background: #555;
        }

        /* Слегка увеличим отступ у поста, чтобы комментарии были читаемы */
        .post {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Можно сделать аватар комментатора чуть меньше, если добавлять */
        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Для мобильных: инпут и кнопка подстраиваются */
        @media (max-width: 600px) {
            .comment-form {
                flex-direction: column;
            }
            .comment-input {
                width: 100%;
            }
            .comment-send {
                width: 100%;
            }
        }
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

                <div class="post-date"
                    data-time="<?= str_replace(' ', 'T', $p['created_at']) ?>Z">
                </div>


                <?php if ($p['edited_at']): ?>
                    <div style="color:#777;">(изменено)</div>
                <?php endif; ?>
                </div>

            </div>

            <div>
                <a class="title" href="post.php?id=<?= $p['id'] ?>">
                    <?= htmlspecialchars($p['title']) ?>
                </a>
            </div>

            <div class="post-info">
                <span>Категория: <?= htmlspecialchars($p['category_name']) ?></span>

                <?php 
                    $liked = false;
                    $disliked = false;
                    if ($currentUser) {
                        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
                        $stmt->execute([$currentUser['id'], $p['id']]);
                        $liked = $stmt->fetch();
                    }
                                        
                    if ($currentUser) {
                        $stmt = $pdo->prepare("SELECT id FROM dislikes WHERE user_id = ? AND post_id = ?");
                        $stmt->execute([$currentUser['id'], $p['id']]);
                        $disliked = $stmt->fetch();
                    }
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
                    <span class="count-number"><?= $p['comments'] ?></span>
                </span>


            </div>

        <div class="comments">
            <?php
            // Загружаем комментарии для этого поста
            $stmt = $pdo->prepare("
                SELECT c.*, u.username, COALESCE(u.avatar, 'default.png') AS avatar 
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ? 
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$p['id']]);
            $comments = $stmt->fetchAll();
            ?>

            <div class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <div class="comment">
                        <img src="avatars/<?= htmlspecialchars($c['avatar']) ?>" class="comment-avatar">
                        <div>
                            <b><?= htmlspecialchars($c['username']) ?></b>: <?= htmlspecialchars($c['content']) ?>
                            <div class="comment-date" data-time="<?= str_replace(' ', 'T', $c['created_at']) ?>Z" style="color:#777; font-size:12px;">
                                <!-- сюда JS подставит "только что / X мин." -->
                            </div>
                        </div>
                    </div>


                <?php endforeach; ?>
            </div>

            <?php if ($currentUser): ?>
                <form method="POST" class="comment-form" onsubmit="return addComment(event, <?= $p['id'] ?>)">
                    <input type="text" class="comment-input" name="comment" placeholder="Написать комментарий..." required style="width:80%;">
                    <button type="submit" class="comment-send">Отправить</button>
                </form>
            <?php else: ?>
                <div style="color:#999;">Чтобы комментировать, войдите в аккаунт.</div>
            <?php endif; ?>
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

<script>


function timeAgo(date) {
    const now = new Date();
    const diff = (now - date) / 1000; // в секундах

    // секунды
    if (diff < 60) return "только что";

    // минуты
    const minutes = diff / 60;
    if (minutes < 60) {
        const m = Math.floor(minutes);
        return m + " " + ("мин.");
    }

    // часы
    const hours = minutes / 60;
    if (hours < 24) {
        const h = Math.floor(hours);
        return h + " " + ("ч.");
    }

    // дни
    const days = hours / 24;
    if (days < 2) return "вчера";

    if (days < 7) {
        const d = Math.floor(days);
        return d + " " + ("дн.");
    }

    // если давно — выводим дату
    return String(date.getDate()).padStart(2, '0') + "." +
        String((date.getMonth() + 1)).padStart(2, '0') + "." +
        String(date.getFullYear()).slice(-2);

}

document.querySelectorAll(".post-date").forEach(el => {
    const ts = el.dataset.time;

    // "2025-02-07T12:10:00" — нормальный формат
    const date = new Date(el.dataset.time); // будет уже с учётом UTC → локально


    el.textContent = timeAgo(date);
});

document.querySelectorAll(".comment-date").forEach(el => {
    const date = new Date(el.dataset.time);
    el.textContent = timeAgo(date);
});


function likePost(id, el) {
    fetch("like.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let counter = el.querySelector(".like-count");
            let icon = el.querySelector(".like-icon");

            if (t === "LIKED") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";
            } 
            else if (t === "UNLIKED") {
                counter.textContent = parseInt(counter.textContent) - 1;
                icon.style.opacity = "0.5";
            } 
            else if (t === "NOT_LOGGED_IN") {
                alert("Чтобы ставить лайки, войдите в аккаунт.");
            }
            if (t === "LIKED_REMOVED_DISLIKE") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";

                // Сбросить дизлайк
                let dSpan = el.parentNode.querySelector(".dislike-count");
                let dIcon = el.parentNode.querySelector(".dislike-icon");
                dIcon.style.opacity = "0.5";
                dSpan.textContent = parseInt(dSpan.textContent) - 1;
            }

        });
}   

function dislikePost(id, el) {
    fetch("dislike.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let counter = el.querySelector(".dislike-count");
            let icon = el.querySelector(".dislike-icon");

            if (t === "DISLIKED") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";
            } 
            else if (t === "UNDISLIKED") {
                counter.textContent = parseInt(counter.textContent) - 1;
                icon.style.opacity = "0.5";
            } 
            else if (t === "NOT_LOGGED_IN") {
                alert("Чтобы ставить дизлайки, войдите в аккаунт.");
            }
            if (t === "DISLIKED_REMOVED_LIKE") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";

                let lSpan = el.parentNode.querySelector(".like-count");
                let lIcon = el.parentNode.querySelector(".like-icon");
                lIcon.style.opacity = "0.5";
                lSpan.textContent = parseInt(lSpan.textContent) - 1;
            }

        });
}   

function addComment(e, postId) {
    e.preventDefault();
    const form = e.target;
    const input = form.querySelector('input[name="comment"]');
    const content = input.value.trim();
    if (!content) return;

    fetch('add_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + postId + '&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const list = form.parentNode.querySelector('.comment-list');
            const div = document.createElement('div');
            div.className = 'comment';
            const now = new Date(); // текущее время
            const avatarSrc = (data.avatar && data.avatar !== "NULL") ? data.avatar : "default.png";

            div.innerHTML = `
                <img src="avatars/${avatarSrc}" class="comment-avatar">
                <div>
                    <b>${data.username}</b>: ${data.content}
                    <div class="comment-date" data-time="${now.toISOString()}Z" style="color:#777; font-size:12px;"></div>
                </div>
            `;

;
            list.appendChild(div);
            // Обновляем число комментариев
            const postDiv = form.closest('.post'); // сам пост
            // Обновляем число комментариев
            const commentCountSpan = postDiv.querySelector('.comment-count .count-number');
            let count = parseInt(commentCountSpan.textContent);
            commentCountSpan.textContent = count + 1;


            input.value = '';
            // сразу обновим текст времени
            const dateEl = div.querySelector(".comment-date");
            dateEl.textContent = timeAgo(now);
        } else {
            alert(data.error);
        }
    });


    return false;
}

</script>

</body>
</html>
