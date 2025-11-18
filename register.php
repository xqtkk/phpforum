<?php
session_start();

// Подключение к БД
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // если установил через Composer

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    // ===== Проверки =====

    // Логин
    if ($username === "") {
        $errors[] = "Введите имя пользователя.";
    } elseif (!preg_match("/^[A-Za-z0-9]{3,20}$/", $username)) {
        $errors[] = "Логин может содержать только буквы и цифры (3–20 символов).";
    }
    $username = strtolower($username);
    // Email
    if ($email === "") {
        $errors[] = "Введите email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email.";
    }

    // Пароль
    if ($password === "") {
        $errors[] = "Введите пароль.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Пароль должен быть минимум 6 символов.";
    } elseif (!preg_match("/[A-Za-z]/", $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну букву.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну цифру.";
    }

    // Проверяем уникальность логина
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $errors[] = "Логин уже занят.";
    }

    // Проверяем уникальность email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email уже используется.";
    }

    // ===== Регистрация =====
    if (empty($errors)) {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, avatar) 
            VALUES (?, ?, ?, 'default.png')
        ");
        $stmt->execute([$username, $email, $hash]);

        $userId = $pdo->lastInsertId();

        // Генерация 6-значного кода
        $code = random_int(100000, 999999);

        // Сохраняем код в базе
        $stmt = $pdo->prepare("INSERT INTO email_verification (user_id, code) VALUES (?, ?)");
        $stmt->execute([$userId, $code]);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // SMTP сервер (Gmail, Mail.ru и т.д.)
            $mail->SMTPAuth   = true;
            $mail->Username   = 'xqtkk1437@gmail.com';
            $mail->Password   = 'smjo gowx disx spzh';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('aty@sosal.com', 'Forum');
            $mail->addAddress($email);

            
            $mail->Subject = 'Подтверждение Email';
            $mail->Body    = "Ваш код: $code";

            $mail->send();
            echo 'Письмо отправлено';

        } catch (Exception $e) {
            echo "Ошибка: {$mail->ErrorInfo}";
        }

        $_SESSION["pending_user_id"] = $userId;
        header("Location: verify_email.php");
        exit;
    }

}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <style>
        body { font-family: sans-serif; background: #f5f5f5; margin: 0; }

        .form-box {
            background: #fff; padding: 20px; width: 350px;
            margin: 60px auto; border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        input {
            width: 100%;
            margin-bottom:
            15px; padding: 10px;
            box-sizing: border-box; /* ← фикс */
        }

        button { padding: 10px; width: 100%; }
        .error {
            background: #ffdddd; padding: 10px;
            margin-bottom: 10px; border-left: 4px solid #d00;
        }

        .topbar {
            background: #333; color: #fff; padding: 15px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .topbar a { color: #fff; text-decoration: none; margin-left: 15px; }

        .logo a { font-size: 22px; font-weight: bold; }

        body {
            font-family: sans-serif;
            background: #f5f5f5;
            margin: 0;
        }

        /* ====== Header ====== */
        .topbar {
            background: #333;
            color: #fff;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
        }
        .logo a {
            font-size: 22px;
            font-weight: bold;
        }

        /* ====== Form Box ====== */
        .form-box {
            background: #fff;
            padding: 25px 30px;
            width: 360px;
            margin: 60px auto;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            transition: transform 0.2s;
        }

        .form-box:hover {
            transform: translateY(-2px);
        }

        .form-box h2 {
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        /* ====== Inputs ====== */
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #333232ff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.4);
            outline: none;
        }

        /* ====== Password toggle ====== */
        .password-container {
            position: relative;
        }

        .password-container button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            width: 28px;
            height: 28px;
            padding: 0;
        }

        .password-container button img {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* ====== Submit button ====== */
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #1b1b1bff;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.2s;
        }

        button[type="submit"]:hover {
            background: #727171ff;
        }

        /* ====== Error messages ====== */
        .error {
            background: #ffe5e5;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #d00;
            border-radius: 4px;
            color: #900;
            font-size: 13px;
        }

        /* ====== Links ====== */
        p {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }
        p a {
            color: #3032bdff;
            font-weight: bold;
            text-decoration: none;
            transition: color 0.2s;
        }
        p a:hover {
            color: #547ff5ff;
        }
        </style>
</head>
<body>

<!-- ====================== HEADER ====================== -->
<header class="topbar">
    <div class="logo"><a href="index.php">Форум</a></div>
</header>

<div class="form-box">
    <h2>Регистрация</h2>

    <?php foreach ($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">

        <input type="text" name="username" placeholder="Имя пользователя" required>

        <input type="email" name="email" placeholder="Email" required>

        <div style="position: relative;">
            <input id="password" type="password" name="password" placeholder="Пароль" required style="padding-right: 45px;">
            
        <button type="button"
                onclick="togglePassword()"
                id="passToggleBtn"
                style="
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(calc(-50% - 6px));
                    padding: 0;
                    margin: 0;
                    width: 28px;
                    height: 28px;
                    background: none;
                    border: none;
                    cursor: pointer;
                ">
            <img id="passIcon" src="assets/eye-closed.svg" style="width: 100%; height: 100%;">
        </button>

        </div>

        <button type="submit">Создать аккаунт</button>
    </form>

    <script>
        function togglePassword() {
            let input = document.getElementById("password");
            let icon = document.getElementById("passIcon");

            if (input.type === "password") {
                input.type = "text";
                icon.src = "assets/eye-open.svg";
            } else {
                input.type = "password";
                icon.src = "assets/eye-closed.svg";
            }
        }
    </script>

    <p>
        Уже есть аккаунт?
        <a href="login.php">Войти</a>
    </p>
</div>

</body>
</html>
