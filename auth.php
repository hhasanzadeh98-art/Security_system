<?php
session_start();
require_once 'classes.php';

$error = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $userModel = new User();
    $user = $userModel->login($username, $password);

    if ($user) {
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['name'] = $user->getName();

        // ریدایرکت به پنل مناسب
        if ($user->getRole() === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: guard.php");
        }
        exit();
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است';
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
        }
    </style>
</head>

<body>
    <div class="card login-container">
        <h1 style="text-align: center;">🔐 ورود به سیستم</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div class="form-group">
                <label>نام کاربری:</label>
                <input type="text" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label>رمز عبور:</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" name="login" class="btn btn-start" style="width: 100%;">
                ورود
            </button>
        </form>
    </div>
</body>

</html>