<?php
session_start();
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: register.php");
    exit;
}

$userId = $_SESSION['pending_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $errors[] = "Введите код.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM email_verification WHERE user_id = ? AND code = ?");
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();

        if ($row) {
            // Код верный — удаляем запись и логиним пользователя
            $pdo->prepare("DELETE FROM email_verification WHERE id = ?")->execute([$row['id']]);
            $_SESSION['user_id'] = $userId;
            unset($_SESSION['pending_user_id']);
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Неверный код.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<style>
/* Общий фон и шрифт */
body {
    background-color: #121212;
    color: #e0e0e0;
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

/* Контейнер формы */
form, h2 {
    width: 100%;
    max-width: 400px;
    margin: 10px;
}

h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #ffffff;
}

/* Поля ввода */
input[type="text"] {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 5px;
    border: 1px solid #444;
    background-color: #1e1e1e;
    color: #ffffff;
    font-size: 14px;
}

input[type="text"]::placeholder {
    color: #888;
}

/* Кнопка */
button {
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

button:hover {
    background-color: #0056b3;
}

/* Ошибки */
div[style*="color:red"] {
    background-color: #330000;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 5px;
    text-align: center;
    color: #ff6b6b;
}
</style>

<head>
<meta charset="UTF-8">
<title>Подтверждение Email</title>
</head>
<body>
<h2>Введите код подтверждения из письма</h2>

<?php foreach ($errors as $e): ?>
    <div style="color:red"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="POST">
    <input type="text" name="code" placeholder="6-значный код" required>
    <button type="submit">Подтвердить</button>
</form>
</body>
</html>
