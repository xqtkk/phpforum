<?php
session_start();

// Подключение к БД
$pdo = new PDO("sqlite:" . __DIR__ . "/database.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login    = trim($_POST["login"] ?? "");     // username или email
    $password = trim($_POST["password"] ?? "");
    $login = strtolower($login);
    if ($login === "") {
        $errors[] = "Введите логин или email.";
    }
    if ($password === "") {
        $errors[] = "Введите пароль.";
    }

    if (empty($errors)) {
        // Ищем по username ИЛИ email
        $stmt = $pdo->prepare("
            SELECT id, username, email, password 
            FROM users 
            WHERE username = ? OR email = ?
            LIMIT 1
        ");

        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "Пользователь не найден.";
        } elseif (!password_verify($password, $user["password"])) {
            $errors[] = "Неверный пароль.";
        } else {
            // Успех — авторизуем
            $_SESSION["user_id"] = $user["id"];
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
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

        input[type="password"] {
            margin-bottom: 5px;
        }

        input[type="text"]:focus,
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
        .forgot a {
            font-size: 13px;
            color: #3032bdff;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }
        .forgot {
            display: flex;
            align-items: center;
        }
        .forgot a:hover {
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
    <h2>Вход</h2>

    <?php foreach ($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">

        <input type="text" name="login" placeholder="Логин или Email" required>

        <div style="position: relative;">
            <input id="password" type="password" name="password" placeholder="Пароль" required style="padding-right: 45px;">
            
            <!-- Кнопка-глаз -->
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

        <!-- Ссылка "Забыли пароль?" под полем -->
        <div style="text-align: right; margin-bottom: 5px;">
            <a href="forgot_password.php" style="
                font-size: 13px;
                font-weight: bold;
                color: #3032bdff;
                text-decoration: none;
            ">Забыли пароль?</a>
        </div>

        <button type="submit">Войти</button>
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
        Нет аккаунта?
        <a href="register.php">Регистрация</a>
    </p>
</div>


</body>
</html>
