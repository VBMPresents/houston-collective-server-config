<?php
/**
 * Authentication System for Houston Collective Streaming Server
 * Handles login, sessions, and access control
 */

session_start();

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new PDO('sqlite:/opt/streamserver/database/streaming.db');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    // Login user
    public function login($username, $password, $remember = false) {
        try {
            // Check for account lockout
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logActivity(null, 'login_failed', "Failed login attempt for username: $username");
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
                return ['success' => false, 'message' => 'Account temporarily locked. Try again later.'];
            }
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Reset login attempts
                $this->db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = CURRENT_TIMESTAMP WHERE id = ?")
                         ->execute([$user['id']]);
                
                // Create session
                $session_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . ($remember ? '30 days' : '24 hours')));
                
                $stmt = $this->db->prepare("
                    INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['id'], 
                    $session_token, 
                    $_SERVER['REMOTE_ADDR'] ?? '', 
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $expires_at
                ]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_token'] = $session_token;
                
                // Set cookie if remember me
                if ($remember) {
                    setcookie('remember_token', $session_token, strtotime('+30 days'), '/', '', false, true);
                }
                
                $this->logActivity($user['id'], 'login_success', "Successful login");
                return ['success' => true, 'user' => $user];
                
            } else {
                // Increment login attempts
                $attempts = $user['login_attempts'] + 1;
                $locked_until = null;
                
                if ($attempts >= 5) {
                    $locked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                }
                
                $this->db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?")
                         ->execute([$attempts, $locked_until, $user['id']]);
                
                $this->logActivity($user['id'], 'login_failed', "Failed password attempt");
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login system error'];
        }
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            return $this->validateSession($_SESSION['session_token']);
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateSession($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    // Validate session token
    private function validateSession($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, s.user_id 
                FROM user_sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > CURRENT_TIMESTAMP AND u.is_active = 1
            ");
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                // Update last activity
                $this->db->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_token = ?")
                         ->execute([$token]);
                
                // Update session variables
                $_SESSION['user_id'] = $session['id'];
                $_SESSION['username'] = $session['username'];
                $_SESSION['role'] = $session['role'];
                $_SESSION['session_token'] = $token;
                
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
        }
        
        return false;
    }
    
    // Logout user
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Deactivate session
            $this->db->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?")
                     ->execute([$_SESSION['session_token']]);
            
            $this->logActivity($_SESSION['user_id'] ?? null, 'logout', "User logged out");
        }
        
        // Clear session
        session_destroy();
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }
    
    // Check user role/permission
    public function hasRole($required_role) {
        if (!$this->isLoggedIn()) return false;
        
        $role_hierarchy = ['admin' => 4, 'editor' => 3, 'viewer' => 2, 'guest' => 1];
        $user_level = $role_hierarchy[$_SESSION['role']] ?? 0;
        $required_level = $role_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    // Get current user info
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Log user activity
    public function logActivity($user_id, $action, $details = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity (user_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    
    // Require login (redirect if not logged in)
    public function requireLogin($required_role = 'viewer') {
        if (!$this->isLoggedIn()) {
            header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        if (!$this->hasRole($required_role)) {
            header('Location: /admin/access_denied.php');
            exit;
        }
    }
}

// Global auth instance
$auth = new Auth();
?>
