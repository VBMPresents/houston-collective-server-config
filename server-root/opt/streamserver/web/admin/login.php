<?php
/**
 * Login Page for Houston Collective Streaming Server
 * Beautiful sunset-themed login interface
 */

require_once '../../scripts/auth.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_POST['action'] ?? '' === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $result = $auth->login($username, $password, $remember);
        
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '/admin/index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Houston Collective</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, 
                #1a1a2e 0%, 
                #2d1b37 15%,
                #4a2c4a 30%,
                #6b3e5d 45%,
                #8e4f6f 60%,
                #b16082 75%,
                #d17194 90%,
                #f082a6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, 
                rgba(223, 64, 53, 0.1) 0%,
                rgba(253, 94, 83, 0.08) 25%,
                rgba(255, 109, 98, 0.06) 50%,
                rgba(238, 79, 68, 0.04) 75%,
                rgba(0, 0, 0, 0.3) 100%);
            pointer-events: none;
        }

        .login-container {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 50px 40px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            box-shadow: 0 20px 50px rgba(253, 94, 83, 0.4);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-title {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 206, 97, 0.4);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 20px rgba(255, 206, 97, 0.3);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .checkbox-input {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .checkbox-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }

        .login-button {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            color: #ffffff;
            border: 2px solid rgba(255, 229, 138, 0.6);
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(253, 94, 83, 0.5);
            border-color: rgba(255, 229, 138, 0.8);
        }

        .login-button:active {
            transform: translateY(-1px);
        }

        .error-message {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid rgba(255, 71, 87, 0.4);
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }

        .success-message {
            background: rgba(107, 207, 127, 0.2);
            color: #6bcf7f;
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid rgba(107, 207, 127, 0.4);
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .login-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">🌅 Admin Login</h1>
            <p class="login-subtitle">The Houston Collective Streaming Server</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username" class="form-label">👤 Username</label>
                <input type="text" id="username" name="username" class="form-input" 
                       placeholder="Enter your username" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">🔐 Password</label>
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="Enter your password" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                <label for="remember" class="checkbox-label">Remember me for 30 days</label>
            </div>

            <button type="submit" class="login-button">
                🚀 Access Control Room
            </button>
        </form>

        <div class="footer-text">
            Default login: <strong>admin</strong> / <strong>admin123</strong><br>
            Please change password after first login
        </div>
    </div>
</body>
</html>
