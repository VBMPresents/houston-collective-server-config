<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('editor'); // Editors and admins can manage schedules

// Rest of your existing code stays the same...
require_once '../../scripts/database.php';

$db = new Database();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $db->pdo->prepare("
                    INSERT INTO schedule (playlist_id, day_of_week, start_time, end_time, repeat_type, is_active) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $_POST['playlist_id'],
                    $_POST['day_of_week'], 
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['repeat_type']
                ]);
                $success = "Schedule created successfully!";
                break;
                
            case 'delete':
                $stmt = $db->pdo->prepare("DELETE FROM schedule WHERE id = ?");
                $stmt->execute([$_POST['schedule_id']]);
                $success = "Schedule deleted successfully!";
                break;
                
            case 'toggle':
                $stmt = $db->pdo->prepare("UPDATE schedule SET is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['is_active'], $_POST['schedule_id']]);
                $success = "Schedule updated successfully!";
                break;
        }
    }
}

// Get all playlists for dropdown
$playlists = $db->pdo->query("
    SELECT id, name, playlist_type, priority 
    FROM playlists 
    WHERE is_active = 1 
    ORDER BY name
")->fetchAll();

// Get all schedules with playlist names
$schedules = $db->pdo->query("
    SELECT s.*, p.name as playlist_name, p.priority 
    FROM schedule s 
    LEFT JOIN playlists p ON s.playlist_id = p.id 
    ORDER BY s.day_of_week, s.start_time
")->fetchAll();

$days = [
    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - The Houston Collective</title>
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
            color: #ffffff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            font-size: 3.2rem;
            font-weight: 900;
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
            margin-bottom: 30px;
            text-align: center;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
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
            margin-bottom: 30px;
            transition: all 0.4s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(253, 94, 83, 0.4);
            border-color: rgba(255, 229, 138, 0.6);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
        }

        select, input[type="time"] {
            background: rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 206, 97, 0.4);
            border-radius: 12px;
            padding: 15px 20px;
            color: #ffffff;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        select:focus, input[type="time"]:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 20px rgba(255, 206, 97, 0.3);
        }

        .btn {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            color: #ffffff;
            border: 2px solid rgba(255, 229, 138, 0.6);
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(253, 94, 83, 0.5);
            border-color: rgba(255, 229, 138, 0.8);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff4757, #ff3838);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.6);
            border: 3px solid rgba(255, 206, 97, 0.6);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid rgba(255, 206, 97, 0.3);
        }

        .schedule-table th {
            background: rgba(0, 0, 0, 0.4);
            font-weight: 700;
            color: #FFCE61;
        }

        .schedule-table tr:hover {
            background: rgba(255, 206, 97, 0.1);
        }

        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-high { background: #ff4757; }
        .priority-medium { background: #ffa502; }
        .priority-low { background: #2ed573; }

        .status-active { color: #2ed573; }
        .status-inactive { color: #ff4757; }

        .success-message {
            background: rgba(46, 213, 115, 0.2);
            border: 2px solid #2ed573;
            color: #2ed573;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .nav-link {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            border: 3px solid rgba(255, 206, 97, 0.6);
            transition: all 0.4s ease;
            font-weight: 600;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(253, 94, 83, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Schedule Management</h1>
        
        <div class="nav-links">
            <a href="index.php" class="nav-link">📊 Dashboard</a>
            <a href="media.php" class="nav-link">🎬 Media Library</a>
            <a href="playlists.php" class="nav-link">📋 Playlists</a>
            <a href="playlist_editor.php" class="nav-link">✏️ Playlist Editor</a>
            <a href="stream_control.php" class="nav-link">🎛️ Stream Control</a>
            <a href="player.html" class="nav-link">📺 Live Player</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Create New Schedule -->
        <div class="card">
            <h2>➕ Create New Schedule</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="playlist_id">📋 Playlist</label>
                        <select name="playlist_id" id="playlist_id" required>
                            <option value="">Select a playlist...</option>
                            <?php foreach ($playlists as $playlist): ?>
                                <option value="<?= $playlist['id'] ?>">
                                    <?= htmlspecialchars($playlist['name']) ?> 
                                    (<?= ucfirst($playlist['priority']) ?> Priority)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="day_of_week">📅 Day of Week</label>
                        <select name="day_of_week" id="day_of_week" required>
                            <option value="">Select day...</option>
                            <?php foreach ($days as $num => $day): ?>
                                <option value="<?= $num ?>"><?= $day ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">⏰ Start Time</label>
                        <input type="time" name="start_time" id="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">⏰ End Time</label>
                        <input type="time" name="end_time" id="end_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="repeat_type">🔄 Repeat</label>
                        <select name="repeat_type" id="repeat_type" required>
                            <option value="weekly">Weekly</option>
                            <option value="daily">Daily</option>
                            <option value="once">One Time Only</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn">➕ Create Schedule</button>
            </form>
        </div>

        <!-- Current Schedules -->
        <div class="card">
            <h2>📋 Current Schedules</h2>
            
            <?php if (empty($schedules)): ?>
                <p style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                    📭 No schedules created yet. Create your first schedule above!
                </p>
            <?php else: ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>📅 Day</th>
                            <th>⏰ Time</th>
                            <th>📋 Playlist</th>
                            <th>🎯 Priority</th>
                            <th>🔄 Repeat</th>
                            <th>📊 Status</th>
                            <th>⚙️ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?= $days[$schedule['day_of_week']] ?></td>
                                <td>
                                    <?= date('g:i A', strtotime($schedule['start_time'])) ?> - 
                                    <?= date('g:i A', strtotime($schedule['end_time'])) ?>
                                </td>
                                <td><?= htmlspecialchars($schedule['playlist_name']) ?></td>
                                <td>
                                    <span class="priority-badge priority-<?= $schedule['priority'] ?>">
                                        <?= ucfirst($schedule['priority']) ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($schedule['repeat_type']) ?></td>
                                <td>
                                    <span class="<?= $schedule['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $schedule['is_active'] ? '✅ Active' : '❌ Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $schedule['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;">
                                            <?= $schedule['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 8px 16px; font-size: 12px;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
