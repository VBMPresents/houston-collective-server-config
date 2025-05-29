<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('admin'); // Only admins can view advanced monitoring

// Rest of your existing code stays the same...
// Smart Scheduler Dashboard for The Houston Collective
require_once '../../scripts/database.php';

$db = new Database();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'status':
            echo json_encode(getSchedulerStatus());
            break;
        case 'analytics':
            echo json_encode(getAnalytics());
            break;
        case 'logs':
            echo json_encode(getRecentLogs());
            break;
        case 'emergency_override':
            echo json_encode(triggerEmergencyOverride());
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

function getSchedulerStatus() {
    $status = [];
    
    // Check systemd service status
    $output = shell_exec('systemctl is-active stream-scheduler.service 2>/dev/null');
    $status['service_active'] = trim($output) === 'active';
    
    // Get service details
    $service_info = shell_exec('systemctl status stream-scheduler.service --no-pager -l 2>/dev/null');
    $status['service_info'] = $service_info;
    
    // Check if FFmpeg is running
    $ffmpeg_count = (int)shell_exec('pgrep -c ffmpeg 2>/dev/null || echo 0');
    $status['ffmpeg_running'] = $ffmpeg_count > 0;
    $status['ffmpeg_processes'] = $ffmpeg_count;
    
    // Get memory and CPU usage
    $memory_info = shell_exec("systemctl show stream-scheduler.service --property=MemoryCurrent 2>/dev/null");
    if (preg_match('/MemoryCurrent=(\d+)/', $memory_info, $matches)) {
        $status['memory_mb'] = round($matches[1] / 1024 / 1024, 1);
    } else {
        $status['memory_mb'] = 'Unknown';
    }
    
    return $status;
}

function getAnalytics() {
    $analytics_file = '/opt/streamserver/logs/analytics_summary.json';
    
    if (file_exists($analytics_file)) {
        $content = file_get_contents($analytics_file);
        $analytics = json_decode($content, true);
        
        if ($analytics) {
            // Calculate uptime
            if (isset($analytics['session_start'])) {
                $start = new DateTime($analytics['session_start']);
                $now = new DateTime();
                $uptime = $now->diff($start);
                $analytics['uptime_formatted'] = $uptime->format('%d days, %h hours, %i minutes');
            }
            
            return $analytics;
        }
    }
    
    return [
        'streams_started' => 0,
        'schedule_switches' => 0,
        'emergency_overrides' => 0,
        'errors' => 0,
        'uptime_formatted' => 'Not available'
    ];
}

function getRecentLogs() {
    $logs = [];
    
    // Get recent smart scheduler logs
    $log_output = shell_exec('journalctl -u stream-scheduler.service --since "1 hour ago" --no-pager -o cat 2>/dev/null | tail -20');
    
    if ($log_output) {
        $lines = explode("\n", trim($log_output));
        foreach ($lines as $line) {
            if (!empty($line)) {
                $logs[] = [
                    'timestamp' => date('H:i:s'),
                    'message' => $line,
                    'type' => getLogType($line)
                ];
            }
        }
    }
    
    return array_reverse($logs); // Most recent first
}

function getLogType($message) {
    if (strpos($message, '❌') !== false || strpos($message, 'ERROR') !== false) {
        return 'error';
    } elseif (strpos($message, '⚠️') !== false || strpos($message, 'WARNING') !== false) {
        return 'warning';
    } elseif (strpos($message, '🚨') !== false) {
        return 'emergency';
    } elseif (strpos($message, '▶️') !== false || strpos($message, '🔄') !== false) {
        return 'action';
    } else {
        return 'info';
    }
}

function triggerEmergencyOverride() {
    $output = shell_exec('sudo systemctl kill --signal=SIGUSR1 stream-scheduler.service 2>&1');
    return [
        'success' => true,
        'message' => 'Emergency override signal sent',
        'output' => $output
    ];
}

// Get current data for initial page load
$current_status = getSchedulerStatus();
$current_analytics = getAnalytics();
$recent_logs = getRecentLogs();

// Get current playlist info
$current_playlist = null;
if (isset($current_analytics['current_playlist']) && $current_analytics['current_playlist']) {
    $stmt = $db->pdo->prepare("SELECT name, priority FROM playlists WHERE id = ?");
    $stmt->execute([$current_analytics['current_playlist']]);
    $current_playlist = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Scheduler Dashboard - The Houston Collective</title>
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
            max-width: 1400px;
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 25px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(253, 94, 83, 0.4);
            border-color: rgba(255, 229, 138, 0.6);
        }

        .card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        .status-online { background: #2ed573; }
        .status-offline { background: #ff4757; }
        .status-warning { background: #ffa502; }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(46, 213, 115, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0); }
        }

        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 206, 97, 0.2);
        }

        .metric:last-child {
            border-bottom: none;
        }

        .metric-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: #FFCE61;
        }

        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 15px;
        }

        .log-entry {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border-left: 4px solid transparent;
        }

        .log-info { 
            background: rgba(255, 255, 255, 0.05);
            border-left-color: #3498db;
        }
        .log-error { 
            background: rgba(255, 71, 87, 0.1);
            border-left-color: #ff4757;
        }
        .log-warning { 
            background: rgba(255, 165, 2, 0.1);
            border-left-color: #ffa502;
        }
        .log-action { 
            background: rgba(46, 213, 115, 0.1);
            border-left-color: #2ed573;
        }
        .log-emergency { 
            background: rgba(255, 71, 87, 0.2);
            border-left-color: #ff3838;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            50% { opacity: 0.7; }
        }

        .btn {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            color: #ffffff;
            border: 2px solid rgba(255, 229, 138, 0.6);
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(253, 94, 83, 0.5);
        }

        .btn-emergency {
            background: linear-gradient(135deg, #ff4757, #ff3838);
            animation: emergency-pulse 2s infinite;
        }

        @keyframes emergency-pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #FFCE61, #FFE58A);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .wide-card {
            grid-column: 1 / -1;
        }

        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: #FFCE61;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 12px;
            border: 2px solid rgba(255, 206, 97, 0.4);
        }

        .current-stream {
            background: linear-gradient(45deg, rgba(46, 213, 115, 0.1), rgba(52, 152, 219, 0.1));
            border: 2px solid rgba(46, 213, 115, 0.3);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auto-refresh" id="autoRefresh">🔄 Auto-refresh: ON</div>

    <div class="container">
        <h1>🧠 Smart Scheduler Dashboard</h1>
        
        <?php include 'nav.php'; ?>

        <div class="dashboard-grid">
            <!-- System Status Card -->
            <div class="card">
                <h3>📊 System Status</h3>
                <div class="metric">
                    <span>Scheduler Service</span>
                    <span class="metric-value">
                        <span class="status-indicator <?= $current_status['service_active'] ? 'status-online' : 'status-offline' ?>"></span>
                        <?= $current_status['service_active'] ? 'ONLINE' : 'OFFLINE' ?>
                    </span>
                </div>
                <div class="metric">
                    <span>FFmpeg Processes</span>
                    <span class="metric-value"><?= $current_status['ffmpeg_processes'] ?></span>
                </div>
                <div class="metric">
                    <span>Memory Usage</span>
                    <span class="metric-value"><?= $current_status['memory_mb'] ?> MB</span>
                </div>
                <div class="metric">
                    <span>Stream Status</span>
                    <span class="metric-value">
                        <span class="status-indicator <?= $current_status['ffmpeg_running'] ? 'status-online' : 'status-offline' ?>"></span>
                        <?= $current_status['ffmpeg_running'] ? 'STREAMING' : 'STOPPED' ?>
                    </span>
                </div>
            </div>

            <!-- Current Stream Card -->
            <div class="card">
                <h3>📺 Current Stream</h3>
                <?php if ($current_playlist): ?>
                    <div class="current-stream">
                        <div class="metric">
                            <span>Active Playlist</span>
                            <span class="metric-value"><?= htmlspecialchars($current_playlist['name']) ?></span>
                        </div>
                        <div class="metric">
                            <span>Priority</span>
                            <span class="metric-value"><?= ucfirst($current_playlist['priority']) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: rgba(255, 255, 255, 0.7);">No active stream detected</p>
                <?php endif; ?>
                <button class="btn" onclick="refreshDashboard()">🔄 Refresh Now</button>
            </div>

            <!-- Analytics Card -->
            <div class="card">
                <h3>📈 Session Analytics</h3>
                <div class="metric">
                    <span>Streams Started</span>
                    <span class="metric-value"><?= $current_analytics['streams_started'] ?></span>
                </div>
                <div class="metric">
                    <span>Schedule Switches</span>
                    <span class="metric-value"><?= $current_analytics['schedule_switches'] ?></span>
                </div>
                <div class="metric">
                    <span>Emergency Overrides</span>
                    <span class="metric-value"><?= $current_analytics['emergency_overrides'] ?></span>
                </div>
                <div class="metric">
                    <span>Errors</span>
                    <span class="metric-value"><?= $current_analytics['errors'] ?></span>
                </div>
                <div class="metric">
                    <span>Uptime</span>
                    <span class="metric-value" style="font-size: 0.9rem;"><?= $current_analytics['uptime_formatted'] ?></span>
                </div>
            </div>

            <!-- Emergency Controls Card -->
            <div class="card">
                <h3>🚨 Emergency Controls</h3>
                <p style="margin-bottom: 15px; color: rgba(255, 255, 255, 0.8);">
                    Emergency override will log the action and can be used for manual control situations.
                </p>
                <button class="btn btn-emergency" onclick="triggerEmergencyOverride()">
                    🚨 Emergency Override
                </button>
                <button class="btn" onclick="restartScheduler()">
                    🔄 Restart Scheduler
                </button>
            </div>
        </div>

        <!-- Live Logs Card (Full Width) -->
        <div class="card wide-card">
            <h3>📋 Live Activity Feed</h3>
            <div class="log-container" id="logContainer">
                <?php foreach ($recent_logs as $log): ?>
                    <div class="log-entry log-<?= $log['type'] ?>">
                        <span style="color: #FFCE61;">[<?= $log['timestamp'] ?>]</span> <?= htmlspecialchars($log['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let autoRefreshEnabled = true;
        let refreshInterval;

        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                if (autoRefreshEnabled) {
                    refreshDashboard();
                }
            }, 10000); // Refresh every 10 seconds
        }

        function toggleAutoRefresh() {
            autoRefreshEnabled = !autoRefreshEnabled;
            document.getElementById('autoRefresh').textContent = 
                `🔄 Auto-refresh: ${autoRefreshEnabled ? 'ON' : 'OFF'}`;
            
            if (autoRefreshEnabled) {
                startAutoRefresh();
            } else {
                clearInterval(refreshInterval);
            }
        }

        function refreshDashboard() {
            // Refresh logs
            fetch('?action=logs')
                .then(response => response.json())
                .then(logs => {
                    const container = document.getElementById('logContainer');
                    container.innerHTML = '';
                    logs.forEach(log => {
                        const div = document.createElement('div');
                        div.className = `log-entry log-${log.type}`;
                        div.innerHTML = `<span style="color: #FFCE61;">[${log.timestamp}]</span> ${log.message}`;
                        container.appendChild(div);
                    });
                    container.scrollTop = container.scrollHeight;
                })
                .catch(error => console.error('Error refreshing logs:', error));

            // Refresh analytics
            fetch('?action=analytics')
                .then(response => response.json())
                .then(analytics => {
                    // Update analytics display (you can add more specific updates here)
                    console.log('Analytics updated:', analytics);
                })
                .catch(error => console.error('Error refreshing analytics:', error));
        }

        function triggerEmergencyOverride() {
            if (confirm('Are you sure you want to trigger an emergency override? This will be logged.')) {
                fetch('?action=emergency_override')
                    .then(response => response.json())
                    .then(result => {
                        alert(result.message || 'Emergency override triggered');
                        refreshDashboard();
                    })
                    .catch(error => {
                        console.error('Error triggering override:', error);
                        alert('Error triggering emergency override');
                    });
            }
        }

        function restartScheduler() {
            if (confirm('Are you sure you want to restart the scheduler service?')) {
                // This would require additional server-side endpoint
                alert('Restart functionality would require additional implementation for security');
            }
        }

        // Click auto-refresh indicator to toggle
        document.getElementById('autoRefresh').addEventListener('click', toggleAutoRefresh);

        // Start auto-refresh on page load
        startAutoRefresh();

        // Initial scroll to bottom of logs
        document.addEventListener('DOMContentLoaded', function() {
            const logContainer = document.getElementById('logContainer');
            logContainer.scrollTop = logContainer.scrollHeight;
        });
    </script>
</body>
</html>
