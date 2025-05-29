<?php
/**
 * User Management Database Schema Creation
 * Houston Collective Streaming Server
 */

try {
    // Connect to existing database
    $db = new PDO('sqlite:/opt/streamserver/database/streaming.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating user management tables...\n";
    
    // Users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'viewer',
            full_name VARCHAR(100),
            is_active INTEGER DEFAULT 1,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            login_attempts INTEGER DEFAULT 0,
            locked_until DATETIME
        )
    ");
    
    // User sessions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_active INTEGER DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // User activity log table
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_activity (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_token ON user_sessions(session_token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_user ON user_sessions(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_user ON user_activity(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_timestamp ON user_activity(timestamp)");
    
    // Create default admin user (password: admin123 - change after first login)
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("
        INSERT OR IGNORE INTO users (username, password_hash, email, role, full_name) 
        VALUES ('admin', '$admin_password', 'admin@houstoncollective.com', 'admin', 'System Administrator')
    ");
    
    echo "✅ User management tables created successfully!\n";
    echo "✅ Default admin user created (username: admin, password: admin123)\n";
    echo "⚠️  Please change the default password after first login!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating user tables: " . $e->getMessage() . "\n";
    exit(1);
}
?>
