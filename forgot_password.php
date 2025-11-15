<?php
session_start();
require 'vendor/autoload.php'; // если ты установил PHPMailer через Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    // Проверка на пустой email
    if (empty($email)) {
        $errors[] = "Введите email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email.";
    }

    if (empty($errors)) {
        // Проверка, существует ли пользователь с таким email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Генерация уникального токена
            $token = bin2hex(random_bytes(16));

            // Сохраняем токен и время его жизни в БД (например, токен действителен 1 час)
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, datetime('now'))");
            $stmt->execute([$user['id'], $token]);

            // Формируем ссылку для сброса пароля
            $resetLink = "http://yourdomain.com/reset_password.php?token=$token";

            // Отправляем письмо с ссылкой на сброс пароля
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'xqtkk1437@gmail.com';
                $mail->Password   = 'smjo gowx disx spzh';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('no-reply@yourdomain.com', 'Forum');
                $mail->addAddress($email);

                $mail->Subject = 'Сброс пароля';
                $mail->Body    = "Вы запросили сброс пароля. Перейдите по следующей ссылке, чтобы изменить пароль: $resetLink";

                $mail->send();
                $success = "Ссылка для сброса пароля была отправлена на ваш email.";
            } catch (Exception $e) {
                $errors[] = "Ошибка отправки письма: " . $mail->ErrorInfo;
            }
        } else {
            $errors[] = "Пользователь с таким email не найден.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля</title>
</head>
<body>
    <h2>Восстановление пароля</h2>
    <?php foreach ($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Введите ваш email" required>
        <button type="submit">Отправить ссылку для сброса пароля</button>
    </form>
</body>
</html>
