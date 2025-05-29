<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('admin'); // Only admins can control streaming

// Rest of your existing code stays the same...
require_once '/opt/streamserver/scripts/database.php';

$db = new Database();
$videos = $db->getVideos(1, 100);

// Get SRS status
$srsUrl = 'http://127.0.0.1:1985/api/v1/summaries';
$srsResponse = @file_get_contents($srsUrl);
$srsStatus = $srsResponse ? json_decode($srsResponse, true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Control - The Houston Collective</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
            margin: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #ff6b6b, #ffd93d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .nav {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .nav a.active {
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
        }
        .panel {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid rgba(255, 107, 107, 0.3);
        }
        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffd93d;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .status-online {
            color: #6bcf7f;
            font-weight: bold;
        }
        .status-offline {
            color: #ff6b6b;
            font-weight: bold;
        }
        .video-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .video-item:hover {
            background: rgba(255, 107, 107, 0.2);
        }
        .video-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .video-meta {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }
        .btn {
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            margin: 0.5rem;
            font-size: 1rem;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="logo">🎛️ The Houston Collective Stream Control</h1>
    </div>

    <nav class="nav">
        <a href="index.php">📊 Dashboard</a>
        <a href="media.php">📁 Media Library</a>
        <a href="playlists.php">📋 Playlists</a>
        <a href="stream_control.php" class="active">🎛️ Stream Control</a>
        <a href="player.html" target="_blank">📺 Live Player</a>
    </nav>

    <!-- SRS Status Panel -->
    <div class="panel">
        <h2 class="panel-title">📡 System Status</h2>
        <?php if ($srsStatus): ?>
            <p class="status-online">✅ SRS Server Online</p>
            <p>CPU: <?= round($srsStatus['data']['self']['cpu_percent'], 1) ?>% | 
               Memory: <?= round($srsStatus['data']['self']['mem_kbyte'] / 1024, 1) ?>MB | 
               Uptime: <?= gmdate('H:i:s', $srsStatus['data']['self']['srs_uptime']) ?></p>
        <?php else: ?>
            <p class="status-offline">❌ SRS Server Offline</p>
        <?php endif; ?>
        
        <button class="btn" onclick="window.open('player.html', '_blank')">📺 Open Live Player</button>
        <button class="btn" onclick="location.reload()">🔄 Refresh Status</button>
    </div>

    <!-- Video Library Panel -->
    <div class="panel">
        <h2 class="panel-title">🎵 Stream from Library (<?= count($videos) ?> videos shown)</h2>
        <p style="text-align: center; margin-bottom: 1rem; color: rgba(255,255,255,0.8);">
            Click any video to get the streaming command
        </p>
        
        <?php foreach ($videos as $video): ?>
        <div class="video-item" onclick="showStreamCommand('<?= htmlspecialchars($video['file_path']) ?>', '<?= htmlspecialchars($video['display_name'] ?: $video['filename']) ?>')">
            <div class="video-title"><?= htmlspecialchars($video['display_name'] ?: $video['filename']) ?></div>
            <div class="video-meta">
                Duration: <?= $video['duration'] ? gmdate('H:i:s', $video['duration']) : 'Unknown' ?> | 
                Resolution: <?= $video['resolution'] ?: 'Unknown' ?> | 
                Size: <?= round($video['file_size'] / (1024*1024), 1) ?>MB
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        function showStreamCommand(filepath, title) {
            alert(`To stream: ${title}\n\nRun this command on your server:\n\nffmpeg -re -i "${filepath}" -c:v libx264 -preset fast -c:a aac -f flv rtmp://127.0.0.1:1935/live/test\n\nThen refresh the Live Player to watch!`);
        }
    </script>
</body>
</html>
