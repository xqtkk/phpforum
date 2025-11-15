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
