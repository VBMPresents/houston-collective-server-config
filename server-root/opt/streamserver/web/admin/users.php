<?php
/**
 * User Management Interface - Houston Collective Streaming Server
 * Complete user administration with role management
 */

require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('admin'); // Only admins can manage users

require_once '/opt/streamserver/scripts/database.php';

$db = new Database();
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST['action'] ?? '' === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    $full_name = trim($_POST['full_name'] ?? '');
    
    if (empty($username) || empty($password) || empty($email)) {
        $error_message = 'Username, password, and email are required.';
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->pdo->prepare("
                INSERT INTO users (username, password_hash, email, role, full_name) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $password_hash, $email, $role, $full_name]);
            
            $auth->logActivity($_SESSION['user_id'], 'user_created', "Created user: $username");
            $success_message = "User '$username' created successfully!";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                $error_message = 'Username or email already exists.';
            } else {
                $error_message = 'Error creating user: ' . $e->getMessage();
            }
        }
    }
}

if ($_POST['action'] ?? '' === 'update_user') {
    $user_id = $_POST['user_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    $full_name = trim($_POST['full_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        $stmt = $db->pdo->prepare("
            UPDATE users SET username = ?, email = ?, role = ?, full_name = ?, is_active = ? 
            WHERE id = ?
        ");
        $stmt->execute([$username, $email, $role, $full_name, $is_active, $user_id]);
        
        $auth->logActivity($_SESSION['user_id'], 'user_updated', "Updated user: $username");
        $success_message = "User updated successfully!";
    } catch (PDOException $e) {
        $error_message = 'Error updating user: ' . $e->getMessage();
    }
}

if ($_POST['action'] ?? '' === 'delete_user') {
    $user_id = $_POST['user_id'] ?? '';
    
    // Don't allow deleting self
    if ($user_id != $_SESSION['user_id']) {
        try {
            $stmt = $db->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $stmt = $db->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $auth->logActivity($_SESSION['user_id'], 'user_deleted', "Deleted user: " . $user['username']);
            $success_message = "User deleted successfully!";
        } catch (PDOException $e) {
            $error_message = 'Error deleting user: ' . $e->getMessage();
        }
    } else {
        $error_message = "You cannot delete your own account.";
    }
}

// Get all users
$stmt = $db->pdo->query("
    SELECT u.*, 
           COUNT(s.id) as active_sessions,
           MAX(s.last_activity) as last_session_activity
    FROM users u 
    LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 1 
    GROUP BY u.id 
    ORDER BY u.created_date DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user activity for display
$stmt = $db->pdo->query("
    SELECT ua.*, u.username 
    FROM user_activity ua 
    LEFT JOIN users u ON ua.user_id = u.id 
    ORDER BY ua.timestamp DESC 
    LIMIT 20
");
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - The Houston Collective</title>
    <?php include 'nav.php'; ?>
    <style>
        .users-container {
            padding: 30px;
            max-width: 1400px;
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

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
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

        .btn-danger {
            background: rgba(255, 71, 87, 0.3);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.6);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(253, 94, 83, 0.4);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 206, 97, 0.3);
        }

        .users-table th {
            background: rgba(0, 0, 0, 0.4);
            color: #FFCE61;
            font-weight: 700;
        }

        .users-table td {
            color: #ffffff;
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

        .status-active {
            color: #6bcf7f;
        }

        .status-inactive {
            color: #ff4757;
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

        .activity-list {
            max-height: 400px;
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
        }

        @media (max-width: 768px) {
            .users-container {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="users-container">
        <div class="page-header">
            <h1 class="page-title">👥 User Management</h1>
            <p class="page-subtitle">Manage system users and access control</p>
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

        <div class="content-grid">
            <!-- Create New User -->
            <div class="card">
                <h2 class="card-title">➕ Create New User</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="viewer">Viewer</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🚀 Create User</button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <h2 class="card-title">📊 Recent Activity</h2>
                <div class="activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-action">
                                <?php echo htmlspecialchars($activity['action']); ?>
                                <?php if ($activity['username']): ?>
                                    by <?php echo htmlspecialchars($activity['username']); ?>
                                <?php endif; ?>
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
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h2 class="card-title">👤 All Users</h2>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Active Sessions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['full_name']): ?>
                                    <br><small style="color: rgba(255, 255, 255, 0.7);">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    <span style="color: rgba(255, 255, 255, 0.5);">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['active_sessions'] > 0): ?>
                                    <span style="color: #6bcf7f;"><?php echo $user['active_sessions']; ?> active</span>
                                <?php else: ?>
                                    <span style="color: rgba(255, 255, 255, 0.5);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 12px;">
                                    ✏️ Edit
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" 
                                            class="btn btn-danger" style="font-size: 0.8rem; padding: 6px 12px;">
                                        🗑️ Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editUser(userId) {
            // Simple implementation - you can enhance this with a modal
            window.location.href = '/admin/edit_user.php?id=' + userId;
        }

        function deleteUser(userId, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
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
