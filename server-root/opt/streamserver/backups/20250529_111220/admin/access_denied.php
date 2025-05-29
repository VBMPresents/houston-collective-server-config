<?php
/**
 * Access Denied Page for Houston Collective Streaming Server
 */

require_once '/opt/streamserver/scripts/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - The Houston Collective</title>
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

        .access-denied-container {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 50px 40px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 71, 87, 0.6);
            box-shadow: 0 20px 50px rgba(255, 71, 87, 0.4);
            width: 100%;
            max-width: 500px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(255, 71, 87, 0.6));
        }

        .error-title {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #ff4757, #ff3838);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 71, 87, 0.6);
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .error-message {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .user-info {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.4);
            margin-bottom: 30px;
        }

        .user-role {
            color: #FFCE61;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.4s ease;
            border: 2px solid;
            cursor: pointer;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            color: #ffffff;
            border-color: rgba(255, 229, 138, 0.6);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-color: rgba(255, 206, 97, 0.6);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(253, 94, 83, 0.5);
        }

        @media (max-width: 480px) {
            .access-denied-container {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="access-denied-container">
        <div class="error-icon">🚫</div>
        
        <h1 class="error-title">Access Denied</h1>
        
        <p class="error-message">
            You don't have permission to access this resource.<br>
            Contact an administrator if you believe this is an error.
        </p>

        <?php if ($auth->isLoggedIn()): ?>
            <?php $user = $auth->getCurrentUser(); ?>
            <div class="user-info">
                <div class="user-role">Logged in as: <strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
                <div style="color: rgba(255, 255, 255, 0.8);">
                    Role: <strong><?php echo ucfirst($user['role']); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <?php if ($auth->isLoggedIn()): ?>
                <a href="/admin/index.php" class="btn btn-primary">🏠 Return to Dashboard</a>
                <a href="/admin/login.php?action=logout" class="btn btn-secondary">🚪 Logout</a>
            <?php else: ?>
                <a href="/admin/login.php" class="btn btn-primary">🔐 Login</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
