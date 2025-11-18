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
            $resetLink = "https://unoperatically-unactuated-tula.ngrok-free.dev/reset_password.php?token=$token";

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

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }

        * {
            box-sizing: border-box;
        }


        .container {
            background: white;
            padding: 30px 40px;
            border-radius: 12px;
            width: 50%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s ease;

        }

        h2 {
            margin-top: 0;
            text-align: center;
            color: #222;
        }

        input[type=email] {
            width: 100%;
            padding: 12px;
            border: 1px solid #bbb;
            border-radius: 8px;
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 15px;
            transition: 0.2s;
        }

        input[type=email]:focus {
            outline: none;
            border: 1px solid black;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }

        button {
            width: 100%;
            background: black;
            border: none;
            padding: 12px;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            background: #1e1e1fff;
        }

        .error, .success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .error {
            background: #ffd5d5;
            color: #a80000;
        }

        .success {
            background: #d7ffdf;
            color: #006e2e;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Восстановление пароля</h2>
        <?php foreach ($errors as $e): ?>
            <div class="error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Введите ваш email" required>
            <button type="submit">Отправить ссылку</button>
        </form>
    </div>
</body>
</html>
