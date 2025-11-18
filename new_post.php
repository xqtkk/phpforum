<?php
session_start();
require_once 'functions.php';
require_once 'posts.php'; // если посты хранятся там

if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Получаем список категорий из БД
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);

function addPost($userId, $title, $content, $categoryId = 0) {
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, category_id, created_at) 
                           VALUES (:user_id, :title, :content, :category_id, :created_at)");

    $stmt->execute([
        ':user_id'    => $userId,
        ':title'      => $title,
        ':content'    => $content,
        ':category_id'=> $categoryId,
        ':created_at' => date('Y-m-d H:i:s')
    ]);

    return $pdo->lastInsertId();
}



// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category'] ?? 0);

    if ($title && $content) {
        addPost($currentUser['id'], $title, $content, $categoryId);
        header("Location: index.php");
        exit;
    } else {
        $error = "Заполните все поля!";
    }
}

?>

<!DOCTYPE html>
<html lang="ru">

<style>
/* dark-theme.css */

body {
    background-color: #121212;
    color: #e0e0e0;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

.create-post-form {
    max-width: 600px;
    margin: 50px auto;
    padding: 30px;
    background-color: #1e1e1e;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
}

.create-post-form h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #ffffff;
}

.create-post-form input[type="text"],
.create-post-form textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    background-color: #2c2c2c;
    border: 1px solid #444;
    border-radius: 5px;
    color: #ffffff;
    font-size: 14px;
}

.create-post-form input[type="text"]::placeholder,
.create-post-form textarea::placeholder {
    color: #999;
}

.create-post-form button {
    display: block;
    width: 100%;
    padding: 12px;
    background-color: #007bff;
    border: none;
    border-radius: 5px;
    color: #ffffff;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s;
}

.create-post-form button:hover {
    background-color: #0056b3;
}

/* Ошибки */
.create-post-form p {
    color: #ff6b6b;
    text-align: center;
}

/* Ссылки */
a {
    color: #1e90ff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

</style>
<head>
    <meta charset="UTF-8">
    <title>Создать пост</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="create-post-form">
    <h2>Создать новый пост</h2>
    <?php if (!empty($error)) echo '<p style="color:red;">'.$error.'</p>'; ?>
    <form method="post">
        <input type="text" name="title" placeholder="Заголовок" required><br><br>
        <textarea name="content" placeholder="Содержание поста" rows="5" required></textarea><br><br>

        <label for="category">Категория:</label>
        <select name="category" id="category" required>
            <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit">Создать пост</button>
    </form>

</div>

</body>
</html>
