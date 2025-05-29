<?php
/**
 * Edit User Page - Houston Collective Streaming Server
 * Edit user details, change passwords, and manage user settings
 */

require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('admin'); // Only admins can edit users

require_once '/opt/streamserver/scripts/database.php';

$db = new Database();
$success_message = '';
$error_message = '';
$user_to_edit = null;

// Get user ID from URL
$user_id = $_GET['id'] ?? '';

if (empty($user_id)) {
    header('Location: /admin/users.php');
    exit;
}

// Get user details
try {
    $stmt = $db->pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_to_edit) {
        header('Location: /admin/users.php?error=User not found');
        exit;
    }
} catch (PDOException $e) {
    header('Location: /admin/users.php?error=Database error');
    exit;
}

// Handle form submissions
if ($_POST['action'] ?? '' === 'update_user') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    $full_name = trim($_POST['full_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($username) || empty($email)) {
        $error_message = 'Username and email are required.';
    } else {
        try {
            $stmt = $db->pdo->prepare("
                UPDATE users SET username = ?, email = ?, role = ?, full_name = ?, is_active = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $role, $full_name, $is_active, $user_id]);
            
            $auth->logActivity($_SESSION['user_id'], 'user_updated', "Updated user: $username");
            $success_message = "User details updated successfully!";
            
            // Refresh user data
            $stmt = $db->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                $error_message = 'Username or email already exists.';
            } else {
                $error_message = 'Error updating user: ' . $e->getMessage();
            }
        }
    }
}

if ($_POST['action'] ?? '' === 'change_password') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error_message = 'New password is required.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            $auth->logActivity($_SESSION['user_id'], 'password_changed', "Changed password for user: " . $user_to_edit['username']);
            $success_message = "Password changed successfully!";
            
        } catch (PDOException $e) {
            $error_message = 'Error changing password: ' . $e->getMessage();
        }
    }
}

// Get user sessions
$stmt = $db->pdo->prepare("
    SELECT * FROM user_sessions 
    WHERE user_id = ? AND is_active = 1 
    ORDER BY last_activity DESC
");
$stmt->execute([$user_id]);
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user activity
$stmt = $db->pdo->prepare("
    SELECT * FROM user_activity 
    WHERE user_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User: <?php echo htmlspecialchars($user_to_edit['username']); ?> - The Houston Collective</title>
    <?php include 'nav.php'; ?>
    <style>
        .edit-user-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 3.2rem;
            font-weight: 900;
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .card {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            box-shadow: 0 15px 40px rgba(253, 94, 83, 0.4);
        }

        .card-title {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 206, 97, 0.4);
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            box-shadow: 0 0 15px rgba(255, 206, 97, 0.3);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-input {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .checkbox-label {
            color: #ffffff;
            font-weight: 600;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
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

        .btn-danger {
            background: rgba(255, 71, 87, 0.3);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.6);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(253, 94, 83, 0.4);
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message-success {
            background: rgba(107, 207, 127, 0.2);
            color: #6bcf7f;
            border: 2px solid rgba(107, 207, 127, 0.4);
        }

        .message-error {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
            border: 2px solid rgba(255, 71, 87, 0.4);
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 3px solid rgba(255, 206, 97, 0.6);
        }

        .info-label {
            font-weight: 600;
            color: #FFCE61;
            font-size: 0.9rem;
        }

        .info-value {
            color: #ffffff;
            margin-top: 5px;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: linear-gradient(135deg, #DF4035, #FD5E53);
            color: white;
        }

        .role-editor {
            background: linear-gradient(135deg, #FFCE61, #FFE58A);
            color: #1a1a2e;
        }

        .role-viewer {
            background: rgba(107, 207, 127, 0.3);
            color: #6bcf7f;
        }

        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 10px 15px;
            margin-bottom: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 3px solid rgba(255, 206, 97, 0.6);
        }

        .activity-action {
            font-weight: 600;
            color: #FFCE61;
        }

        .activity-time {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .edit-user-container {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="edit-user-container">
        <div class="page-header">
            <h1 class="page-title">✏️ Edit User</h1>
            <p class="page-subtitle">Editing: <strong><?php echo htmlspecialchars($user_to_edit['username']); ?></strong></p>
        </div>

        <?php if ($success_message): ?>
            <div class="message message-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message message-error">
                ⚠️ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- User Information Overview -->
        <div class="card" style="margin-bottom: 30px;">
            <h2 class="card-title">👤 User Information</h2>
            <div class="user-info-grid">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?php echo htmlspecialchars($user_to_edit['username']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user_to_edit['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value">
                        <span class="role-badge role-<?php echo $user_to_edit['role']; ?>">
                            <?php echo ucfirst($user_to_edit['role']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php echo $user_to_edit['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">
                        <?php echo date('M j, Y g:i A', strtotime($user_to_edit['created_date'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value">
                        <?php if ($user_to_edit['last_login']): ?>
                            <?php echo date('M j, Y g:i A', strtotime($user_to_edit['last_login'])); ?>
                        <?php else: ?>
                            <span style="color: rgba(255, 255, 255, 0.5);">Never</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Edit User Details -->
            <div class="card">
                <h2 class="card-title">📝 Edit User Details</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" 
                               value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($user_to_edit['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="viewer" <?php echo $user_to_edit['role'] === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                            <option value="editor" <?php echo $user_to_edit['role'] === 'editor' ? 'selected' : ''; ?>>Editor</option>
                            <option value="admin" <?php echo $user_to_edit['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" class="checkbox-input" 
                               <?php echo $user_to_edit['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active" class="checkbox-label">Account is active</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 Update Details</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2 class="card-title">🔐 Change Password</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" 
                               placeholder="Enter new password" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-input" 
                               placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔑 Change Password</button>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: rgba(255, 206, 97, 0.1); border-radius: 8px; border: 1px solid rgba(255, 206, 97, 0.3);">
                    <p style="color: #FFCE61; font-size: 0.9rem; margin: 0;">
                        💡 Password must be at least 6 characters long.
                    </p>
                </div>
            </div>
        </div>

        <!-- Active Sessions and Activity -->
        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">🔗 Active Sessions (<?php echo count($active_sessions); ?>)</h2>
                <?php if (empty($active_sessions)): ?>
                    <p style="color: rgba(255, 255, 255, 0.7);">No active sessions</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($active_sessions as $session): ?>
                            <div class="activity-item">
                                <div class="activity-action">
                                    📍 <?php echo htmlspecialchars($session['ip_address']); ?>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                    Last activity: <?php echo date('M j, Y g:i A', strtotime($session['last_activity'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">📊 Recent Activity</h2>
                <?php if (empty($user_activity)): ?>
                    <p style="color: rgba(255, 255, 255, 0.7);">No recent activity</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($user_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-action">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </div>
                                <?php if ($activity['details']): ?>
                                    <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($activity['details']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="activity-time">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="text-align: center; margin-top: 40px;">
            <a href="/admin/users.php" class="btn btn-secondary">⬅️ Back to Users</a>
            <?php if ($user_to_edit['id'] != $_SESSION['user_id']): ?>
                <button onclick="deleteUser(<?php echo $user_to_edit['id']; ?>, '<?php echo htmlspecialchars($user_to_edit['username'], ENT_QUOTES); ?>')" 
                        class="btn btn-danger">
                    🗑️ Delete User
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteUser(userId, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/admin/users.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
