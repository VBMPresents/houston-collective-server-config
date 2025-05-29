            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 206, 97, 0.1), 
                transparent);
            transition: left 0.6s ease;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 229, 138, 0.8);
            box-shadow: 0 10px 30px rgba(255, 206, 97, 0.3);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 8px;
            color: #ffffff;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2.2rem;
            }

            .nav-tabs {
                flex-direction: column;
            }

            .playlist-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                flex-direction: column;
            }

            .priority-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include "nav.php"; ?>
    <div class="container">
        <div class="header">
            <h1>Playlist Management</h1>
            <p>Create and manage professional streaming playlists for The Houston Collective</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview')">📊 Overview</button>
            <button class="nav-tab" onclick="showTab('create')">➕ Create Playlist</button>
            <button class="nav-tab" onclick="showTab('manage')">⚙️ Manage Playlists</button>
            <button class="nav-tab" onclick="showTab('templates')">📋 Templates</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($playlists); ?></div>
                    <div class="stat-label">Total Playlists</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($playlists, function($p) { return $p['is_active']; })); ?></div>
                    <div class="stat-label">Active Playlists</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($playlistStats, 'video_count')); ?></div>
                    <div class="stat-label">Total Videos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo gmdate("H:i", array_sum(array_column($playlistStats, 'total_duration'))); ?></div>
                    <div class="stat-label">Total Duration</div>
                </div>
            </div>
        </div>

        <!-- Create Playlist Tab -->
        <div id="create" class="tab-content">
            <div class="card">
                <h2>Create New Playlist</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_playlist">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Playlist Name *</label>
                            <input type="text" id="name" name="name" required placeholder="Enter playlist name">
                        </div>

                        <div class="form-group">
                            <label for="type">Playlist Type</label>
                            <select id="type" name="type">
                                <option value="standard">Standard Playlist</option>
                                <option value="shuffle">Shuffle Playlist</option>
                                <option value="priority">Priority Playlist</option>
                                <option value="smart">Smart Playlist</option>
                                <option value="scheduled">Scheduled Playlist</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe this playlist's purpose and content"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Playback Options</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="shuffle_enabled" name="shuffle_enabled">
                                <label for="shuffle_enabled">Enable Shuffle</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="loop_enabled" name="loop_enabled">
                                <label for="loop_enabled">Enable Loop</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Priority Level</label>
                        <div class="priority-selector">
                            <div class="priority-option">
                                <input type="radio" id="priority_low" name="priority" value="1">
                                <label for="priority_low">Low<br><small>Background</small></label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" id="priority_medium" name="priority" value="5" checked>
                                <label for="priority_medium">Medium<br><small>Standard</small></label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" id="priority_high" name="priority" value="10">
                                <label for="priority_high">High<br><small>Featured</small></label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Playlist</button>
                </form>
            </div>
        </div>

        <!-- Manage Playlists Tab -->
        <div id="manage" class="tab-content">
            <div class="playlist-grid">
                <?php foreach ($playlists as $playlist): ?>
                    <?php
                    $stats = array_filter($playlistStats, function($s) use ($playlist) {
                        return $s['playlist_id'] == $playlist['id'];
                    });
                    $playlistStat = reset($stats) ?: ['video_count' => 0, 'total_duration' => 0];
                    ?>
                    <div class="playlist-card">
                        <h3><?php echo htmlspecialchars($playlist['name']); ?></h3>

                        <?php if ($playlist['description']): ?>
                            <p>
                                <?php echo htmlspecialchars($playlist['description']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="playlist-meta">
                            <span class="meta-badge type"><?php echo ucfirst($playlist['playlist_type']); ?></span>
                            <span class="meta-badge priority">Priority <?php echo $playlist['priority']; ?></span>
                            <span class="meta-badge status"><?php echo $playlist['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </div>

                        <div style="margin: 20px 0; font-weight: 600; color: rgba(255, 255, 255, 0.9);">
                            <strong><?php echo $playlistStat['video_count']; ?></strong> videos •
                            <strong><?php echo gmdate("H:i:s", $playlistStat['total_duration']); ?></strong> duration
                        </div>

                        <div class="playlist-actions">
                            <a href="playlist_editor.php?id=<?php echo $playlist['id']; ?>" class="btn btn-primary">Edit Content</a>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_playlist">
                                <input type="hidden" name="playlist_id" value="<?php echo $playlist['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $playlist['is_active'] ? 0 : 1; ?>">
                                <button type="submit" class="btn btn-secondary">
                                    <?php echo $playlist['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>

                            <?php if ($playlist['id'] > 1): // Don't allow deleting General Rotation ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this playlist?');">
                                    <input type="hidden" name="action" value="delete_playlist">
                                    <input type="hidden" name="playlist_id" value="<?php echo $playlist['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Templates Tab -->
        <div id="templates" class="tab-content">
            <div class="card">
                <h2>Playlist Templates</h2>
                <p style="opacity: 0.9; margin-bottom: 30px; font-size: 1.1rem;">Quick-start templates for common playlist types</p>

                <div class="playlist-grid">
                    <div class="playlist-card" style="cursor: pointer;" onclick="createFromTemplate('music_mix')">
                        <h3>🎵 Music Mix</h3>
                        <p>General music rotation with shuffle enabled</p>
                        <div class="meta-badge type">Standard + Shuffle</div>
                    </div>

                    <div class="playlist-card" style="cursor: pointer;" onclick="createFromTemplate('prime_time')">
                        <h3>⭐ Prime Time</h3>
                        <p>High-priority content for peak hours</p>
                        <div class="meta-badge priority">High Priority</div>
                    </div>

                    <div class="playlist-card" style="cursor: pointer;" onclick="createFromTemplate('late_night')">
                        <h3>🌙 Late Night</h3>
                        <p>Chill content for overnight streaming</p>
                        <div class="meta-badge type">Low Priority + Loop</div>
                    </div>

                    <div class="playlist-card" style="cursor: pointer;" onclick="createFromTemplate('event_special')">
                        <h3>🎉 Event Special</h3>
                        <p>Scheduled playlist for special events</p>
                        <div class="meta-badge type">Scheduled</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all nav tabs
            const navTabs = document.querySelectorAll('.nav-tab');
            navTabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function createFromTemplate(templateType) {
            // Switch to create tab
            showTab('create');

            // Fill form based on template
            const form = document.querySelector('form[action=""]');
            const nameInput = form.querySelector('#name');
            const typeSelect = form.querySelector('#type');
            const descInput = form.querySelector('#description');
            const shuffleCheck = form.querySelector('#shuffle_enabled');
            const loopCheck = form.querySelector('#loop_enabled');
            const priorityRadios = form.querySelectorAll('input[name="priority"]');

            switch(templateType) {
                case 'music_mix':
                    nameInput.value = 'Music Mix';
                    typeSelect.value = 'shuffle';
                    descInput.value = 'General music rotation with shuffle enabled for variety';
                    shuffleCheck.checked = true;
                    loopCheck.checked = false;
                    priorityRadios[1].checked = true; // Medium
                    break;

                case 'prime_time':
                    nameInput.value = 'Prime Time';
                    typeSelect.value = 'priority';
                    descInput.value = 'High-priority content for peak viewing hours';
                    shuffleCheck.checked = false;
                    loopCheck.checked = false;
                    priorityRadios[2].checked = true; // High
                    break;

                case 'late_night':
                    nameInput.value = 'Late Night';
                    typeSelect.value = 'standard';
                    descInput.value = 'Chill content for overnight streaming hours';
                    shuffleCheck.checked = false;
                    loopCheck.checked = true;
                    priorityRadios[0].checked = true; // Low
                    break;

                case 'event_special':
                    nameInput.value = 'Event Special';
                    typeSelect.value = 'scheduled';
                    descInput.value = 'Special event content with scheduled playback';
                    shuffleCheck.checked = false;
                    loopCheck.checked = false;
                    priorityRadios[2].checked = true; // High
                    break;
            }

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth' });
        }

        // Auto-save form data as user types (basic localStorage fallback)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[action=""]');
            if (form) {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // Simple validation feedback
                        if (this.hasAttribute('required') && !this.value) {
                            this.style.borderColor = '#ff4757';
                        } else {
                            this.style.borderColor = 'rgba(255, 206, 97, 0.4)';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
EOF

cat /opt/streamserver/web/admin/playlist_editor.php
cat > /opt/streamserver/web/admin/playlist_editor.php << 'EOF'
<?php
require_once '../../scripts/database.php';

$db = new Database();
$message = '';
$messageType = '';

// Get playlist ID from URL
$playlistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$playlistId) {
    header('Location: playlists.php');
    exit;
}

// Get playlist details
$playlist = $db->getPlaylistById($playlistId);
if (!$playlist) {
    header('Location: playlists.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_video':
            $videoId = (int)$_POST['video_id'];
            $result = $db->addVideoToPlaylist($playlistId, $videoId);
            echo json_encode(['success' => $result]);
            exit;

        case 'remove_video':
            $videoId = (int)$_POST['video_id'];
            $result = $db->removeVideoFromPlaylist($playlistId, $videoId);
            echo json_encode(['success' => $result]);
            exit;

        case 'reorder_videos':
            $videoIds = $_POST['video_ids'];
            $result = $db->reorderPlaylistVideos($playlistId, $videoIds);
            echo json_encode(['success' => $result]);
            exit;

        case 'search_videos':
            $search = trim($_POST['search']);
            $videos = $db->searchVideos($search);
            echo json_encode($videos);
            exit;
    }
}

// Get all videos and playlist videos
$allVideos = $db->getAllVideos();
$playlistVideos = $db->getPlaylistVideos($playlistId);
$playlistVideoIds = array_column($playlistVideos, 'video_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Playlist: <?php echo htmlspecialchars($playlist['name']); ?> - The Houston Collective</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
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
            background-attachment: fixed;
            min-height: 100vh;
            color: #ffffff;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
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
            z-index: -1;
        }

        .header {
            background: linear-gradient(135deg, 
                rgba(253, 94, 83, 0.15) 0%,
                rgba(255, 109, 98, 0.12) 50%,
                rgba(238, 79, 68, 0.15) 100%);
            padding: 25px;
            backdrop-filter: blur(20px);
            border-bottom: 3px solid rgba(255, 206, 97, 0.6);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                transparent 0%,
                rgba(255, 229, 138, 0.1) 25%,
                rgba(255, 206, 97, 0.1) 50%,
                rgba(255, 229, 138, 0.1) 75%,
                transparent 100%);
            pointer-events: none;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .header h1 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.2rem;
            font-weight: 900;
            margin-bottom: 8px;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
        }

        .header .playlist-info {
            opacity: 0.9;
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.2), 
                transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            color: #ffffff;
            border: 2px solid rgba(255, 229, 138, 0.6);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 30px rgba(253, 94, 83, 0.5),
                0 0 20px rgba(255, 206, 97, 0.4);
            border-color: rgba(255, 229, 138, 1);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border: 3px solid rgba(255, 206, 97, 0.6);
        }

        .btn-secondary:hover {
            background: rgba(255, 206, 97, 0.2);
            transform: translateY(-3px);
            border-color: rgba(255, 229, 138, 1);
            box-shadow: 0 8px 25px rgba(255, 206, 97, 0.3);
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            min-height: calc(100vh - 200px);
        }

        .panel {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 206, 97, 0.1), 
                transparent);
            transition: left 0.6s ease;
        }

        .panel:hover::before {
            left: 100%;
        }

        .panel h2 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .search-box {
            position: relative;
            margin-bottom: 25px;
            z-index: 1;
        }

        .search-box input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 3px solid rgba(255, 206, 97, 0.4);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.4s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 20px rgba(255, 206, 97, 0.3);
        }

        .search-box::after {
            content: "🔍";
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.4rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .video-list {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            position: relative;
            z-index: 1;
        }

        .video-item {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.5) 0%,
                rgba(31, 33, 77, 0.3) 50%,
                rgba(0, 0, 0, 0.5) 100%);
            border-radius: 15px;
            padding: 18px;
            margin-bottom: 12px;
            cursor: move;
            transition: all 0.4s ease;
            border: 2px solid rgba(255, 206, 97, 0.3);
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
            overflow: hidden;
        }

        .video-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 206, 97, 0.1), 
                transparent);
            transition: left 0.6s ease;
        }

        .video-item:hover::before {
            left: 100%;
        }

        .video-item:hover {
            background: linear-gradient(135deg, 
                rgba(253, 94, 83, 0.3) 0%,
                rgba(255, 109, 98, 0.25) 50%,
                rgba(238, 79, 68, 0.3) 100%);
            border-color: rgba(255, 229, 138, 0.8);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(253, 94, 83, 0.3);
        }

        .video-item.sortable-ghost {
            opacity: 0.4;
            transform: scale(0.95);
        }

        .video-item.sortable-chosen {
            background: linear-gradient(135deg, 
                rgba(255, 217, 61, 0.4) 0%,
                rgba(255, 229, 138, 0.3) 50%,
                rgba(255, 217, 61, 0.4) 100%);
            border-color: #FFCE61;
            box-shadow: 0 0 25px rgba(255, 206, 97, 0.5);
        }

        .video-item.sortable-drag {
            background: linear-gradient(135deg, 
                rgba(255, 107, 107, 0.4) 0%,
                rgba(253, 94, 83, 0.3) 50%,
                rgba(255, 107, 107, 0.4) 100%);
            border-color: #ff6b6b;
            transform: rotate(3deg) scale(1.05);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.5);
        }

        .video-thumbnail {
            width: 70px;
            height: 50px;
            background: linear-gradient(135deg, 
                rgba(253, 94, 83, 0.6) 0%,
                rgba(255, 109, 98, 0.5) 50%,
                rgba(238, 79, 68, 0.6) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            color: rgba(255, 255, 255, 0.9);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
            position: relative;
            z-index: 1;
        }

        .video-info {
            flex: 1;
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .video-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .video-meta {
            display: flex;
            gap: 18px;
            font-size: 0.9rem;
            opacity: 0.8;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .video-meta span {
            padding: 4px 8px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
            border: 1px solid rgba(255, 206, 97, 0.3);
            font-weight: 600;
        }

        .video-actions {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 700;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.3), 
                transparent);
            transition: left 0.5s ease;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .add-btn {
            background: linear-gradient(135deg, #6bcf7f, #4caf50);
            color: #fff;
            border: 2px solid rgba(107, 207, 127, 0.6);
        }

        .add-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 8px 20px rgba(107, 207, 127, 0.5);
            border-color: rgba(107, 207, 127, 1);
        }

        .remove-btn {
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: #fff;
            border: 2px solid rgba(255, 71, 87, 0.6);
        }

        .remove-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 8px 20px rgba(255, 71, 87, 0.5);
            border-color: rgba(255, 71, 87, 1);
        }

        .drag-handle {
            color: #FFCE61;
            font-size: 1.8rem;
            cursor: grab;
            padding: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .drag-handle:hover {
            color: #FFE58A;
            transform: scale(1.2);
            text-shadow: 0 0 15px rgba(255, 206, 97, 0.6);
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .drop-zone {
            border: 3px dashed rgba(255, 206, 97, 0.4);
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            margin-bottom: 25px;
            transition: all 0.4s ease;
            background: rgba(0, 0, 0, 0.2);
        }

        .drop-zone.drag-over {
            border-color: #FFCE61;
            background: rgba(255, 206, 97, 0.1);
            box-shadow: 0 0 30px rgba(255, 206, 97, 0.3);
        }

        .playlist-stats {
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.5) 0%,
                rgba(31, 33, 77, 0.3) 50%,
                rgba(0, 0, 0, 0.5) 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px;
            text-align: center;
            border: 2px solid rgba(255, 206, 97, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(255, 206, 97, 0.6);
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 5px;
            color: #ffffff;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            opacity: 0.7;
        }

        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 25px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .loading {
            text-align: center;
            padding: 30px;
            opacity: 0.7;
        }

        .spinner {
            display: inline-block;
            width: 35px;
            height: 35px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #FFCE61;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 20px;
                min-height: auto;
            }

            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .header-actions {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .video-item {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 15px;
            }

            .video-meta {
                justify-content: center;
            }

            .playlist-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<?php include "nav.php"; ?>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>Edit: <?php echo htmlspecialchars($playlist['name']); ?></h1>
                <div class="playlist-info">
                    <?php echo ucfirst($playlist['playlist_type']); ?> Playlist • Priority <?php echo $playlist['priority']; ?>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="savePlaylist()">💾 Save Changes</button>
                <a href="playlists.php" class="btn btn-primary">← Back to Playlists</a>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Video Library Panel -->
        <div class="panel">
            <h2>📚 Video Library</h2>

            <div class="search-box">
                <input type="text" id="search-videos" placeholder="Search videos..." onkeyup="searchVideos()">
            </div>

            <div class="video-list" id="video-library">
                <?php foreach ($allVideos as $video): ?>
                    <?php if (!in_array($video['id'], $playlistVideoIds)): ?>
                        <div class="video-item" data-video-id="<?php echo $video['id']; ?>">
                            <div class="video-thumbnail">🎬</div>
                            <div class="video-info">
                                <div class="video-name"><?php echo htmlspecialchars($video['display_name']); ?></div>
                                <div class="video-meta">
                                    <span><?php echo gmdate("H:i:s", $video['duration']); ?></span>
                                    <span><?php echo strtoupper($video['format']); ?></span>
                                    <span><?php echo $video['resolution']; ?></span>
                                </div>
                            </div>
                            <div class="video-actions">
                                <button class="action-btn add-btn" onclick="addToPlaylist(<?php echo $video['id']; ?>)" title="Add to playlist">+</button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Playlist Content Panel -->
        <div class="panel">
            <h2>🎵 Playlist Content</h2>

            <div class="playlist-stats">
                <div class="stat">
                    <div class="stat-number" id="video-count"><?php echo count($playlistVideos); ?></div>
                    <div class="stat-label">Videos</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="total-duration"><?php echo gmdate("H:i:s", array_sum(array_column($playlistVideos, 'duration'))); ?></div>
                    <div class="stat-label">Duration</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="file-size"><?php echo number_format(array_sum(array_column($playlistVideos, 'file_size')) / 1024 / 1024, 1); ?>MB</div>
                    <div class="stat-label">Size</div>
                </div>
            </div>

            <?php if (empty($playlistVideos)): ?>
                <div class="drop-zone" id="drop-zone">
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No videos in this playlist</h3>
                        <p>Drag videos from the library or click the + button to add videos</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="video-list" id="playlist-content">
                <?php foreach ($playlistVideos as $index => $video): ?>
                    <div class="video-item" data-video-id="<?php echo $video['video_id']; ?>">
                        <div class="drag-handle">⋮⋮</div>
                        <div class="video-thumbnail">🎬</div>
                        <div class="video-info">
                            <div class="video-name"><?php echo htmlspecialchars($video['display_name']); ?></div>
                            <div class="video-meta">
                                <span>#<?php echo $index + 1; ?></span>
                                <span><?php echo gmdate("H:i:s", $video['duration']); ?></span>
                                <span><?php echo strtoupper($video['format']); ?></span>
                            </div>
                        </div>
                        <div class="video-actions">
                            <button class="action-btn remove-btn" onclick="removeFromPlaylist(<?php echo $video['video_id']; ?>)" title="Remove from playlist">×</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let playlistId = <?php echo $playlistId; ?>;
        let playlistSortable;
        let hasChanges = false;

        // Initialize sortable playlist
        document.addEventListener('DOMContentLoaded', function() {
            const playlistContent = document.getElementById('playlist-content');

            playlistSortable = Sortable.create(playlistContent, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    hasChanges = true;
                    updatePlaylistOrder();
                    updateVideoNumbers();
                }
            });

            // Make library videos draggable to playlist
            const videoLibrary = document.getElementById('video-library');
            Sortable.create(videoLibrary, {
                group: {
                    name: 'videos',
                    pull: 'clone',
                    put: false
                },
                sort: false,
                onEnd: function(evt) {
                    if (evt.to === playlistContent) {
                        const videoId = evt.item.dataset.videoId;
                        addToPlaylist(videoId);
                        evt.item.remove(); // Remove the clone
                    }
                }
            });

            // Also make playlist accept drops
            playlistSortable.option('group', {
                name: 'videos',
                pull: false,
                put: true
            });
        });

        function addToPlaylist(videoId) {
            fetch('playlist_editor.php?id=' + playlistId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=add_video&video_id=' + videoId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from library and add to playlist
                    const libraryItem = document.querySelector('#video-library [data-video-id="' + videoId + '"]');
                    const playlistContent = document.getElementById('playlist-content');

                    if (libraryItem) {
                        // Create playlist item
                        const playlistItem = createPlaylistItem(libraryItem, videoId);
                        playlistContent.appendChild(playlistItem);

                        // Remove from library
                        libraryItem.remove();

                        // Update stats
                        updateStats();
                        updateVideoNumbers();
                        hasChanges = true;

                        // Hide empty state if visible
                        const dropZone = document.getElementById('drop-zone');
                        if (dropZone) dropZone.style.display = 'none';
                    }
                } else {
                    alert('Failed to add video to playlist');
                }
            });
        }

        function removeFromPlaylist(videoId) {
            fetch('playlist_editor.php?id=' + playlistId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=remove_video&video_id=' + videoId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from playlist and add back to library
                    const playlistItem = document.querySelector('#playlist-content [data-video-id="' + videoId + '"]');
                    const videoLibrary = document.getElementById('video-library');

                    if (playlistItem) {
                        // Create library item
                        const libraryItem = createLibraryItem(playlistItem, videoId);
                        videoLibrary.appendChild(libraryItem);

                        // Remove from playlist
                        playlistItem.remove();

                        // Update stats
                        updateStats();
                        updateVideoNumbers();
                        hasChanges = true;

                        // Show empty state if no videos left
                        const playlistContent = document.getElementById('playlist-content');
                        if (playlistContent.children.length === 0) {
                            showEmptyState();
                        }
                    }
                } else {
                    alert('Failed to remove video from playlist');
                }
            });
        }

        function createPlaylistItem(libraryItem, videoId) {
            const item = document.createElement('div');
            item.className = 'video-item';
            item.dataset.videoId = videoId;

            const videoName = libraryItem.querySelector('.video-name').textContent;
            const videoMeta = libraryItem.querySelector('.video-meta').innerHTML;

            item.innerHTML = `
                <div class="drag-handle">⋮⋮</div>
                <div class="video-thumbnail">🎬</div>
                <div class="video-info">
                    <div class="video-name">${videoName}</div>
                    <div class="video-meta">${videoMeta}</div>
                </div>
                <div class="video-actions">
                    <button class="action-btn remove-btn" onclick="removeFromPlaylist(${videoId})" title="Remove from playlist">×</button>
                </div>
            `;

            return item;
        }

        function createLibraryItem(playlistItem, videoId) {
            const item = document.createElement('div');
            item.className = 'video-item';
            item.dataset.videoId = videoId;

            const videoName = playlistItem.querySelector('.video-name').textContent;
            const videoMeta = playlistItem.querySelector('.video-meta').innerHTML;

            item.innerHTML = `
                <div class="video-thumbnail">🎬</div>
                <div class="video-info">
                    <div class="video-name">${videoName}</div>
                    <div class="video-meta">${videoMeta}</div>
                </div>
                <div class="video-actions">
                    <button class="action-btn add-btn" onclick="addToPlaylist(${videoId})" title="Add to playlist">+</button>
                </div>
            `;

            return item;
        }

        function updatePlaylistOrder() {
            const playlistItems = document.querySelectorAll('#playlist-content .video-item');
            const videoIds = Array.from(playlistItems).map(item => item.dataset.videoId);

            fetch('playlist_editor.php?id=' + playlistId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=reorder_videos&video_ids=' + JSON.stringify(videoIds)
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to save playlist order');
                }
            });
        }

        function updateVideoNumbers() {
            const playlistItems = document.querySelectorAll('#playlist-content .video-item');
            playlistItems.forEach((item, index) => {
                const metaElement = item.querySelector('.video-meta');
                const metaText = metaElement.innerHTML;
                // Replace the number (first span)
                metaElement.innerHTML = metaText.replace(/^<span>#\d+<\/span>/, `<span>#${index + 1}</span>`);
            });
        }

        function updateStats() {
            const playlistItems = document.querySelectorAll('#playlist-content .video-item');
            document.getElementById('video-count').textContent = playlistItems.length;

            // Note: For accurate duration/size updates, we'd need to fetch video data
            // This is a simplified version that just updates the count
        }

        function showEmptyState() {
            const playlistContent = document.getElementById('playlist-content');
            playlistContent.innerHTML = `
                <div class="drop-zone" id="drop-zone">
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No videos in this playlist</h3>
                        <p>Drag videos from the library or click the + button to add videos</p>
                    </div>
                </div>
            `;
        }

        function searchVideos() {
            const search = document.getElementById('search-videos').value;
            const videoItems = document.querySelectorAll('#video-library .video-item');

            videoItems.forEach(item => {
                const videoName = item.querySelector('.video-name').textContent.toLowerCase();
                if (videoName.includes(search.toLowerCase())) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function savePlaylist() {
            if (hasChanges) {
                // Auto-save is already happening via AJAX
                alert('All changes have been saved automatically!');
                hasChanges = false;
            } else {
                alert('No changes to save.');
            }
        }

        // Warn user about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>
EOF

python3 /opt/streamserver/scripts/content_scanner.py
ls -la /opt/streamserver/database/streaming.db
# Make the database writable by both streamadmin and www-data
sudo chown streamadmin:www-data /opt/streamserver/database/streaming.db
sudo chmod 664 /opt/streamserver/database/streaming.db
# Also fix the database directory permissions
sudo chown streamadmin:www-data /opt/streamserver/database/
sudo chmod 775 /opt/streamserver/database/
python3 /opt/streamserver/scripts/content_scanner.py
find /opt/streamserver/content/ -name "*.mp4" -o -name "*.mkv" -o -name "*.avi" -o -name "*.mov" | head -5
ffmpeg -re -i "/opt/streamserver/content/singles/KPFT Sessions/Tucker Hart hounds of hell.mp4" -c:v libx264 -preset fast -c:a aac -f flv rtmp://127.0.0.1:1935/live/test
# Check if SRS is listening on port 1935
sudo netstat -tlnp | grep 1935
tail -20 /opt/streamserver/srs/logs/srs.log
ss -tlnp | grep 1935
sudo tee /opt/streamserver/srs/conf/srs.conf > /dev/null << 'EOF'
# SRS 5.0-r2 Configuration - Testing without HTTP hooks
listen              1935;
max_connections     2000;
srs_log_tank        file;
srs_log_file        /opt/streamserver/srs/logs/srs.log;
srs_log_level       info;

# HTTP API
http_api {
    enabled         on;
    listen          1985;
    crossdomain     on;
}

# HTTP server for HLS
http_server {
    enabled         on;
    listen          8080;
    dir             /opt/streamserver/srs/hls;
    crossdomain     on;
}

# Main vhost - simplified for testing
vhost __defaultVhost__ {
    hls {
        enabled         on;
        hls_path        /opt/streamserver/srs/hls;
        hls_fragment    2;
        hls_window      30;
        hls_cleanup     on;
        hls_dispose     15;
        hls_m3u8_file   [app]/[stream].m3u8;
        hls_ts_file     [app]/[stream]-[seq].ts;
    }

    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }
}

rtc_server {
    enabled on;
    listen 8000;
    candidate $CANDIDATE;
}

stats {
    network         0;
    disk            sda sdb xvda xvdb;
}
EOF

sudo systemctl restart srs && sudo systemctl status srs
ffmpeg -re -i "/opt/streamserver/content/singles/KPFT Sessions/Tucker Hart hounds of hell.mp4" -c:v libx264 -preset fast -c:a aac -f flv rtmp://127.0.0.1:1935/live/test
ffmpeg -re -i "/opt/streamserver/content/singles/KPFT Sessions/opti.mp4" -c:v libx264 -preset fast -c:a aac -f flv rtmp://127.0.0.1:1935/live/test
sudo nano /opt/streamserver/web/admin/schedule.php
sudo nano /opt/streamserver/web/admin/nav.php
sudo nano /opt/streamserver/web/admin/schedule.php
ls -la /opt/streamserver/scripts/database.php
sqlite3 /opt/streamserver/database/streaming.db ".schema schedule"
sudo tail -f /var/log/nginx/error.log
sudo nano /opt/streamserver/web/admin/schedule.php
sudo nano /opt/streamserver/scripts/basic_scheduler.py
sudo chmod +x /opt/streamserver/scripts/basic_scheduler.py
sudo chown streamadmin:streamadmin /opt/streamserver/scripts/basic_scheduler.py
sqlite3 /opt/streamserver/database/streaming.db "SELECT id, name, priority FROM playlists WHERE is_active = 1;"
cd /opt/streamserver/scripts
python3 basic_scheduler.py
ls -la /opt/streamserver/content/
sqlite3 /opt/streamserver/database/streaming.db "SELECT filename, file_path FROM videos LIMIT 5;"
nano /opt/streamserver/scripts/basic_scheduler.py
python3 basic_scheduler.py
sudo nano /etc/systemd/system/stream-scheduler.service
sudo systemctl daemon-reload
sudo systemctl enable stream-scheduler.service
sudo systemctl start stream-scheduler.service
sudo systemctl status stream-scheduler.service
sudo nano /opt/streamserver/scripts/smart_scheduler.py
sudo nano /etc/systemd/system/stream-scheduler.service
sudo systemctl daemon-reload
sudo systemctl stop stream-scheduler.service
sudo systemctl start stream-scheduler.service
sudo systemctl status stream-scheduler.service
sudo journalctl -f -u stream-scheduler.service --no-pager
sudo nano /opt/streamserver/web/admin/smart_dashboard.php
sudo nano /opt/streamserver/web/admin/nav.php
sudo tail -f /var/log/nginx/error.log
ls -la /opt/streamserver/scripts/database.php
systemctl is-active stream-scheduler.service
sudo nano /opt/streamserver/web/admin/smart_dashboard.php
sudo nano /opt/streamserver/scripts/create_user_tables.php
sudo -u streamserver php /opt/streamserver/scripts/create_user_tables.php
sudo -u streamadmin php /opt/streamserver/scripts/create_user_tables.php
sudo nano /opt/streamserver/scripts/auth.php
sudo chown streamadmin:streamadmin /opt/streamserver/scripts/auth.php
sudo chmod 644 /opt/streamserver/scripts/auth.php
sudo nano /opt/streamserver/web/admin/login.php
sudo nano /opt/streamserver/web/admin/access_denied.php
sudo nano /opt/streamserver/web/admin/index.php
sudo nano /opt/streamserver/web/admin/media.php
sudo nano /opt/streamserver/web/admin/playlists.php
sudo nano /opt/streamserver/web/admin/playlist_editor.php
sudo nano /opt/streamserver/web/admin/schedule.php
sudo nano /opt/streamserver/web/admin/stream_control.php
sudo nano /opt/streamserver/web/admin/smart_dashboard.php
sudo nano /opt/streamserver/web/admin/users.php
sudo tail -20 /var/log/nginx/error.log
sudo tail -20 /var/log/php8.1-fpm.log
sudo journalctl -u php8.1-fpm -n 20
sudo nano /opt/streamserver/web/admin/login.php
sudo nano /opt/streamserver/web/admin/edit_user.php
cat /opt/streamserver/web/admin/media.php
sudo nano /opt/streamserver/web/admin/media.php
cat /opt/streamserver/web/admin/playlists.php
sudo nano /opt/streamserver/web/admin/playlists.php
cat /opt/streamserver/web/admin/index.php
sudo nano /opt/streamserver/web/admin/index.php
cat /opt/streamserver/web/admin/player.html
sudo nano /opt/streamserver/web/admin/player.html
# Check if SRS service is running
sudo systemctl status srs
# Check if SRS is listening on the correct ports
sudo netstat -tlnp | grep :8080
sudo netstat -tlnp | grep :1935
# Check SRS configuration
cat /opt/streamserver/srs/conf/srs.conf
# Check what's in the HLS directory
ls -la /opt/streamserver/srs/hls/
# Check ports with ss instead of netstat
ss -tlnp | grep :8080
ss -tlnp | grep :1935
# Check if there are any current SRS streams via API
curl http://localhost:1985/api/v1/streams/
# Check if the smart scheduler service is running
sudo systemctl status stream-scheduler.service
# Check the smart scheduler logs to see what it's doing
tail -f /opt/streamserver/logs/smart_scheduler.log
# Also check if there are any files in the live directory
ls -la /opt/streamserver/srs/hls/live/
# Check the full FFmpeg command that's running
ps aux | grep ffmpeg
# Or check your smart scheduler script to see what stream name it uses
grep -n "rtmp://" /opt/streamserver/scripts/smart_scheduler.py
# Check if SRS can see the active stream now
curl http://localhost:1985/api/v1/streams/
# Check if HLS files are being generated in real-time
ls -la /opt/streamserver/srs/hls/live/
# Check SRS logs for any errors
tail -20 /opt/streamserver/srs/logs/srs.log
# Let's also force check the HLS directory while streaming is active
watch -n 2 "ls -la /opt/streamserver/srs/hls/live/"
# Check if there are any RTMP connections to SRS
ss -tnp | grep :1935
# Let's look at SRS logs specifically for RTMP connections 
grep -i rtmp /opt/streamserver/srs/logs/srs.log | tail -10
# Let's also check if the FFmpeg process is actually connecting
# Kill the current FFmpeg and restart it manually to see errors
sudo pkill ffmpeg
# Wait a moment, then check if scheduler restarts it
sleep 5
ps aux | grep ffmpeg
# First, let's backup the current config
cp /opt/streamserver/srs/conf/srs.conf /opt/streamserver/srs/conf/srs.conf.backup
# Now let's edit the config to add timeout settings
sudo nano /opt/streamserver/srs/conf/srs.conf
sudo systemctl restart srs
# Check if it restarted successfully  
sudo systemctl status srs
# Check the detailed error from SRS
journalctl -xeu srs.service --no-pager
# Let's also check the config file to see what might be wrong
cat /opt/streamserver/srs/conf/srs.conf
# Let's create a corrected configuration file
sudo tee /opt/streamserver/srs/conf/srs.conf > /dev/null << 'EOF'
# SRS 5.0-r2 Configuration - Testing without HTTP hooks
listen              1935;
max_connections     2000;
# Publishing and streaming timeouts
publish_1stpkt_timeout    20000;
publish_normal_timeout    30000;
srs_log_tank        file;
srs_log_file        /opt/streamserver/srs/logs/srs.log;
srs_log_level       info;

# HTTP API
http_api {
    enabled         on;
    listen          1985;
    crossdomain     on;
}

# HTTP server for HLS
http_server {
    enabled         on;
    listen          8080;
    dir             /opt/streamserver/srs/hls;
    crossdomain     on;
}

# Main vhost - simplified for testing
vhost __defaultVhost__ {
    hls {
        enabled         on;
        hls_path        /opt/streamserver/srs/hls;
        hls_fragment    2;
        hls_window      30;
        hls_cleanup     on;
        hls_dispose     15;
        hls_m3u8_file   [app]/[stream].m3u8;
        hls_ts_file     [app]/[stream]-[seq].ts;
    }
    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }
}

rtc_server {
    enabled on;
    listen 8000;
    candidate $CANDIDATE;
}

stats {
    network         0;
    disk            sda sdb xvda xvdb;
}
EOF

# Now restart SRS
sudo systemctl restart srs
# Check if it started successfully
sudo systemctl status srs
# Check what's currently in the config file
cat /opt/streamserver/srs/conf/srs.conf
# Let's manually edit the file to fix the vhost name
sudo sed -i 's/\*\*defaultVhost\*\*/__defaultVhost__/g' /opt/streamserver/srs/conf/srs.conf
# Verify the change was made
grep "vhost" /opt/streamserver/srs/conf/srs.conf
# Now try to restart SRS
sudo systemctl restart srs
# Check the status
sudo systemctl status srs
# Create the clean config (as in my previous message)
sudo cat > /opt/streamserver/srs/conf/srs.conf << 'EOF'
# SRS 5.0-r2 Configuration - Testing without HTTP hooks
listen              1935;
max_connections     2000;
publish_1stpkt_timeout    20000;
publish_normal_timeout    30000;
srs_log_tank        file;
srs_log_file        /opt/streamserver/srs/logs/srs.log;
srs_log_level       info;

http_api {
    enabled         on;
    listen          1985;
    crossdomain     on;
}

http_server {
    enabled         on;
    listen          8080;
    dir             /opt/streamserver/srs/hls;
    crossdomain     on;
}

vhost __defaultVhost__ {
    hls {
        enabled         on;
        hls_path        /opt/streamserver/srs/hls;
        hls_fragment    2;
        hls_window      30;
        hls_cleanup     on;
        hls_dispose     15;
        hls_m3u8_file   [app]/[stream].m3u8;
        hls_ts_file     [app]/[stream]-[seq].ts;
    }
    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }
}

rtc_server {
    enabled on;
    listen 8000;
    candidate $CANDIDATE;
}

stats {
    network         0;
    disk            sda sdb xvda xvdb;
}
EOF

# Try to start SRS manually to see the actual error
sudo /usr/local/bin/srs -c /opt/streamserver/srs/conf/srs.conf
# Stop the manual SRS process (Ctrl+C if it's still running)
sudo pkill srs
# Start the SRS service
sudo systemctl start srs
# Check if it's running
sudo systemctl status srs
# Now check if SRS can see active streams
curl http://localhost:1985/api/v1/streams/
# Check if HLS files are being generated
ls -la /opt/streamserver/srs/hls/live/
# Let's also check for any new files being created
watch -n 2 "ls -la /opt/streamserver/srs/hls/live/"
# First, press Ctrl+C to exit the watch command
# Check if SRS is actually running
sudo systemctl status srs
# Check if there are any active streams
curl http://localhost:1985/api/v1/streams/
# The FFmpeg process probably needs to reconnect to SRS
# Let's restart the smart scheduler to get a fresh connection
sudo systemctl restart stream-scheduler.service
# Wait a moment for it to reconnect
sleep 5
# Check if FFmpeg is connecting again
ps aux | grep ffmpeg
# Check if SRS now sees the stream
curl http://localhost:1985/api/v1/streams/
# Check SRS logs for any new connections or errors
tail -10 /opt/streamserver/srs/logs/srs.log
# Check if HLS files are being created now
ls -la /opt/streamserver/srs/hls/live/
# Create a working SRS configuration without invalid timeout settings
sudo cat > /opt/streamserver/srs/conf/srs.conf << 'EOF'
# SRS 5.0-r2 Configuration - Working version
listen              1935;
max_connections     2000;
srs_log_tank        file;
srs_log_file        /opt/streamserver/srs/logs/srs.log;
srs_log_level       info;

http_api {
    enabled         on;
    listen          1985;
    crossdomain     on;
}

http_server {
    enabled         on;
    listen          8080;
    dir             /opt/streamserver/srs/hls;
    crossdomain     on;
}

vhost __defaultVhost__ {
    tcp_nodelay     on;
    min_latency     on;
    
    publish {
        parse_sps   off;
    }
    
    hls {
        enabled         on;
        hls_path        /opt/streamserver/srs/hls;
        hls_fragment    2;
        hls_window      30;
        hls_cleanup     on;
        hls_dispose     15;
        hls_m3u8_file   [app]/[stream].m3u8;
        hls_ts_file     [app]/[stream]-[seq].ts;
    }
    
    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }
}

rtc_server {
    enabled on;
    listen 8000;
    candidate $CANDIDATE;
}

stats {
    network         0;
    disk            sda sdb xvda xvdb;
}
EOF

# Now restart SRS
sudo systemctl restart srs
# Check if it starts properly
sudo systemctl status srs
# If SRS is running, restart the scheduler
sudo systemctl restart stream-scheduler.service
# Check for active streams
curl http://localhost:1985/api/v1/streams/
# Check what's actually in the config file
cat /opt/streamserver/srs/conf/srs.conf
# Try to start SRS manually to see the real error message
sudo /usr/local/bin/srs -c /opt/streamserver/srs/conf/srs.conf
# If that doesn't work, let's check if there are any syntax issues
sudo /usr/local/bin/srs -t -c /opt/streamserver/srs/conf/srs.conf
# Remove the invalid timeout lines
sudo sed -i '/publish_1stpkt_timeout/d' /opt/streamserver/srs/conf/srs.conf
sudo sed -i '/publish_normal_timeout/d' /opt/streamserver/srs/conf/srs.conf
# Fix the vhost name (replace ** with __)
sudo sed -i 's/\*\*defaultVhost\*\*/__defaultVhost__/g' /opt/streamserver/srs/conf/srs.conf
# Check that both fixes were applied
cat /opt/streamserver/srs/conf/srs.conf | grep -E "(publish_|vhost)"
# Test the config
sudo /usr/local/bin/srs -t -c /opt/streamserver/srs/conf/srs.conf
# If the test passes, start SRS
sudo systemctl restart srs
sudo systemctl status srs
# Restart the smart scheduler to connect to the fresh SRS instance
sudo systemctl restart stream-scheduler.service
# Check if the scheduler started successfully
sudo systemctl status stream-scheduler.service
# Wait a moment for FFmpeg to connect
sleep 5
# Check if SRS now sees active streams
curl http://localhost:1985/api/v1/streams/
# Check if HLS files are being generated
ls -la /opt/streamserver/srs/hls/live/
# Check if new files are being created
watch -n 2 "ls -la /opt/streamserver/srs/hls/live/ && echo '---' && curl -s http://localhost:1985/api/v1/streams/"
sudo nano /opt/streamserver/web/admin/nav.php
cat /opt/streamserver/web/admin/nav.php
sudo nano /opt/streamserver/web/admin/nav.php
# Let's look at your smart scheduler script to see how it handles video changes
grep -A 10 -B 5 "ffmpeg\|subprocess\|Popen" /opt/streamserver/scripts/smart_scheduler.py
# Also check the scheduler logs to see the transition behavior
tail -f /opt/streamserver/logs/smart_scheduler.log
# First, let's backup your current smart scheduler
cp /opt/streamserver/scripts/smart_scheduler.py /opt/streamserver/scripts/smart_scheduler.py.backup
# Now let's look at the current video selection logic
grep -A 20 -B 5 "def.*video\|get.*video" /opt/streamserver/scripts/smart_scheduler.py
# Let's create the enhanced smart scheduler
sudo nano /opt/streamserver/scripts/smooth_scheduler.py
# The smooth_scheduler.py file we created is useless - let's remove it
rm /opt/streamserver/scripts/smooth_scheduler.py
# Now let's properly modify your EXISTING smart_scheduler.py
# We'll enhance the video transition logic in the current file
sudo nano /opt/streamserver/scripts/smart_scheduler.py
# Press Ctrl+X to exit nano without changes
# Let's try the simpler fix first - optimize SRS for smoother transitions
sudo sed -i 's/hls_fragment    2;/hls_fragment    1;/' /opt/streamserver/srs/conf/srs.conf
sudo sed -i 's/hls_window      30;/hls_window      10;/' /opt/streamserver/srs/conf/srs.conf
# Check the changes
grep -E "hls_fragment|hls_window" /opt/streamserver/srs/conf/srs.conf
# Restart SRS to apply the changes
sudo systemctl restart srs
# Restart your scheduler to get a fresh connection
sudo systemctl restart stream-scheduler.service
# Check if both are running
sudo systemctl status srs
sudo systemctl status stream-scheduler.service
sudo find /opt/streamserver/content -type d -name "*" | head -20
sudo find /opt/streamserver/content -type d -exec sh -c 'echo "$(find "$1" -maxdepth 1 -type f \( -name "*.mp4" -o -name "*.mkv" -o -name "*.avi" -o -name "*.mov" \) | wc -l) files in: $1"' _ {} \;
sudo sqlite3 /opt/streamserver/database/streaming.db "CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    folder_path TEXT UNIQUE NOT NULL,
    folder_name TEXT NOT NULL,
    parent_folder TEXT,
    video_count INTEGER DEFAULT 0,
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modified DATETIME DEFAULT CURRENT_TIMESTAMP
);"
sudo nano /opt/streamserver/scripts/content_scanner.py
cat /opt/streamserver/scripts/content_scanner.py
sudo nano /opt/streamserver/scripts/content_scanner.py
sudo python3 /opt/streamserver/scripts/content_scanner.py
head -50 /opt/streamserver/web/admin/media.php
cat /opt/streamserver/web/admin/media.php
sudo nano /opt/streamserver/web/admin/media.php
sudo systemctl status srs stream-scheduler
ls -la /opt/streamserver/srs/hls/
ls -la /opt/streamserver/srs/hls/live/
curl -s http://localhost:1985/api/v1/streams/ | head -20
sudo journalctl -u stream-scheduler --since "5 minutes ago" | tail -20
sudo sqlite3 /opt/streamserver/database/streaming.db "SELECT p.name, COUNT(vp.video_id) as video_count FROM playlists p LEFT JOIN video_playlists vp ON p.id = vp.playlist_id WHERE p.name = 'General Rotation' GROUP BY p.id;"
sudo netstat -tlnp | grep :1935
sudo ss -tlnp | grep :1935
sudo ps aux | grep ffmpeg
sudo tail -30 /opt/streamserver/srs/logs/srs.log
sudo journalctl -u stream-scheduler --since "20:50" | grep -A5 -B5 -i "error\|fail\|exception"
sudo journalctl -u stream-scheduler --since "20:45" --until "20:55" | tail -20
sudo ps -o pid,etime,cmd -p 70053
ls -la /opt/streamserver/database/
sudo sqlite3 /opt/streamserver/database/streaming.db "SELECT v.display_name, v.file_path FROM videos v JOIN video_playlists vp ON v.id = vp.video_id JOIN playlists p ON vp.playlist_id = p.id WHERE p.name = 'General Rotation' AND v.is_active = 1 LIMIT 3;"
sudo systemctl restart stream-scheduler
cat /opt/streamserver/scripts/content_scanner.py
sudo nano /opt/streamserver/scripts/content_scanner.py
cat /opt/streamserver/web/admin/playlists_editor.php
cat /opt/streamserver/web/admin/playlist_editor.php
grep -A10 "removeVideoFromPlaylist" /opt/streamserver/scripts/database.php
grep -A5 -B5 "class Database" /opt/streamserver/scripts/database.php
grep -A10 "__construct" /opt/streamserver/scripts/database.php
grep -A15 "connect()" /opt/streamserver/scripts/database.php
sudo sqlite3 /opt/streamserver/database/streaming.db "SELECT p.name, v.display_name FROM playlists p JOIN video_playlists vp ON p.id = vp.playlist_id JOIN videos v ON vp.video_id = v.id WHERE p.name = 'General Rotation' LIMIT 1;"
curl -X POST "http://144.126.156.143/admin/playlist_editor.php?id=1"   -d "ajax=1&action=remove_video&video_id=1"   -H "Content-Type: application/x-www-form-urlencoded"
sudo nano /opt/streamserver/srs/conf/srs.conf
sudo systemctl restart stream-scheduler
curl -s http://localhost:1985/api/v1/streams/ | head -20
sudo nano /opt/streamserver/srs/conf/srs.conf
sudo systemctl restart srs
sudo systemctl status srs.service
sudo journalctl -xeu srs.service --no-pager | tail -20
sudo /usr/local/bin/srs -c /opt/streamserver/srs/conf/srs.conf -t
sudo nano /opt/streamserver/srs/conf/srs.conf
cat << 'EOF' > /tmp/audit_files.sh
#!/bin/bash

files=(
    "/opt/streamserver/web/admin/media.php:MEDIA.PHP"
    "/opt/streamserver/web/admin/playlists.php:PLAYLISTS.PHP" 
    "/opt/streamserver/web/admin/index.php:INDEX.PHP"
    "/opt/streamserver/web/admin/player.html:PLAYER.HTML"
    "/opt/streamserver/srs/conf/srs.conf:SRS.CONF"
    "/opt/streamserver/scripts/smart_scheduler.py:SMART_SCHEDULER.PY"
    "/opt/streamserver/scripts/smart_scheduler.py.backup:SMART_SCHEDULER.PY.BACKUP"
)

for file_info in "${files[@]}"; do
    filepath="${file_info%:*}"
    filename="${file_info#*:}"
    echo "========================================"
    echo "=== $filename ==="
    echo "========================================"
    if [[ -f "$filepath" ]]; then
        cat "$filepath"
    else
        echo "FILE NOT FOUND: $filepath"
    fi
    echo -e "\n\n"
done
EOF

chmod +x /tmp/audit_files.sh && /tmp/audit_files.sh
echo "
╔══════════════════════════════════════════════════════════════╗
║            🎬 HOUSTON COLLECTIVE SYSTEM AUDIT 🎬              ║
╚══════════════════════════════════════════════════════════════╝
" && echo "📊 === DATABASE ANALYSIS ===" && echo "Database exists:" && ls -la /opt/streamserver/database/streaming.db 2>/dev/null || echo "❌ Database file not found" && echo -e "\nTable structure:" && sqlite3 /opt/streamserver/database/streaming.db ".tables" 2>/dev/null || echo "❌ Cannot read database" && echo -e "\nPlaylist schema:" && sqlite3 /opt/streamserver/database/streaming.db "PRAGMA table_info(playlists);" 2>/dev/null || echo "❌ No playlists table" && echo -e "\nSample playlists:" && sqlite3 /opt/streamserver/database/streaming.db "SELECT id, name, priority, shuffle_enabled, loop_enabled FROM playlists LIMIT 5;" 2>/dev/null || echo "❌ Cannot query playlists" && echo -e "\nSchedule table:" && sqlite3 /opt/streamserver/database/streaming.db "PRAGMA table_info(schedule);" 2>/dev/null || echo "❌ No schedule table found" && echo -e "\n⚙️  === SERVICE STATUS ===" && echo "SRS Server:" $(systemctl is-active srs.service 2>/dev/null || echo "inactive/missing") && echo "Stream Scheduler:" $(systemctl is-active stream-scheduler.service 2>/dev/null || echo "inactive/missing") && echo "Nginx:" $(systemctl is-active nginx.service 2>/dev/null || echo "inactive/missing") && echo "PHP-FPM:" $(systemctl is-active php8.1-fpm.service 2>/dev/null || echo "inactive/missing") && echo -e "\n🔄 === RUNNING PROCESSES ===" && echo "SRS processes:" && ps aux | grep -E "srs" | grep -v grep || echo "❌ No SRS processes" && echo "FFmpeg processes:" && ps aux | grep -E "ffmpeg.*1935" | grep -v grep || echo "❌ No active streams" && echo "Scheduler processes:" && ps aux | grep -E "python.*scheduler" | grep -v grep || echo "❌ No scheduler running" && echo -e "\n🌐 === NETWORK CONNECTIVITY ===" && echo "Port 1935 (RTMP):" && netstat -tln | grep ":1935" || echo "❌ Not listening" && echo "Port 1985 (SRS API):" && netstat -tln | grep ":1985" || echo "❌ Not listening" && echo "Port 8080 (HLS):" && netstat -tln | grep ":8080" || echo "❌ Not listening" && echo -e "\nHLS Stream Test:" && curl -s -I http://127.0.0.1:8080/live/test.m3u8 | head -1 2>/dev/null || echo "❌ HLS stream not accessible" && echo "SRS API Test:" && curl -s http://127.0.0.1:1985/api/v1/servers 2>/dev/null | head -c 50 | grep -o '"code":[0-9]*' || echo "❌ SRS API not responding" && echo -e "\n📁 === FILE SYSTEM CHECK ===" && echo "Main directories:" && ls -ld /opt/streamserver/ /opt/streamserver/content/ /opt/streamserver/database/ /opt/streamserver/srs/hls/ 2>/dev/null | awk '{print $1 " " $3 ":" $4 " " $9}' || echo "❌ Missing directories" && echo -e "\nContent files:" && find /opt/streamserver/content -name "*.mp4" -o -name "*.mkv" -o -name "*.avi" | wc -l | awk '{print $1 " video files found"}' && echo "HLS segments:" && ls -la /opt/streamserver/srs/hls/ 2>/dev/null | grep -E "\.(m3u8|ts)$" | wc -l | awk '{print $1 " HLS files found"}' && echo -e "\n🔧 === DEPENDENCIES CHECK ===" && echo "FFmpeg:" $(which ffmpeg 2>/dev/null || echo "❌ Not found") && echo "Python3:" $(which python3 2>/dev/null || echo "❌ Not found") && echo "SQLite3:" $(which sqlite3 2>/dev/null || echo "❌ Not found") && python3 -c "import sqlite3; print('✅ Python SQLite module OK')" 2>/dev/null || echo "❌ Python SQLite module missing" && ffmpeg -version 2>/dev/null | head -1 | grep -o "ffmpeg version [^ ]*" || echo "❌ FFmpeg version check failed" && echo -e "\n📋 === SRS CONFIG VALIDATION ===" && echo "Config file exists:" && ls -la /opt/streamserver/srs/conf/srs.conf | awk '{print $5 " bytes, " $6 " " $7 " " $8}' 2>/dev/null || echo "❌ Config not found" && echo "Config syntax test:" && /opt/streamserver/srs/objs/srs -t -c /opt/streamserver/srs/conf/srs.conf 2>&1 | grep -E "(success|error|failed)" || echo "❌ Cannot test config" && echo -e "\n📝 === RECENT LOG ANALYSIS ===" && echo "Smart Scheduler (last 5 lines):" && tail -5 /opt/streamserver/logs/smart_scheduler.log 2>/dev/null || echo "❌ No scheduler logs" && echo -e "\nSRS Server (last 5 lines):" && tail -5 /opt/streamserver/srs/logs/srs.log 2>/dev/null || echo "❌ No SRS logs" && echo -e "\nNginx errors (last 3 lines):" && tail -3 /var/log/nginx/error.log 2>/dev/null || echo "❌ No nginx error logs" && echo -e "\nSystemd service errors:" && journalctl -u stream-scheduler.service --no-pager -l -n 3 2>/dev/null || echo "❌ No systemd logs" && echo -e "\n🎯 === STREAM QUALITY TEST ===" && echo "Current HLS fragments:" && find /opt/streamserver/srs/hls -name "*.ts" -mmin -5 2>/dev/null | wc -l | awk '{print $1 " recent video segments"}' && echo "Fragment sizes:" && find /opt/streamserver/srs/hls -name "*.ts" -mmin -5 -exec ls -lh {} \; 2>/dev/null | awk '{print $5}' | head -3 | tr '\n' ' ' | awk '{print "Recent segments: " $0}' || echo "❌ No recent segments" && echo -e "\n
╔══════════════════════════════════════════════════════════════╗
║                    🎬 AUDIT COMPLETE 🎬                       ║
╚══════════════════════════════════════════════════════════════╝
"
echo "
╔══════════════════════════════════════════════════════════════╗
║                🌐 HOUSTON COLLECTIVE WEB AUDIT 🌐             ║
╚══════════════════════════════════════════════════════════════╝
" && echo "🏠 === MAIN ADMIN PAGES ===" && echo "Dashboard (index.php):" && curl -s -I -m 5 http://localhost/admin/ | head -1 && echo "Media Library:" && curl -s -I -m 5 http://localhost/admin/media.php | head -1 && echo "Playlists:" && curl -s -I -m 5 http://localhost/admin/playlists.php | head -1 && echo "Player:" && curl -s -I -m 5 http://localhost/admin/player.html | head -1 && echo -e "\n🎨 === PAGE CONTENT SAMPLES ===" && echo "Dashboard title:" && curl -s -m 5 http://localhost/admin/ | grep -o '<title>[^<]*</title>' 2>/dev/null || echo "❌ Could not extract title" && echo "Dashboard H1:" && curl -s -m 5 http://localhost/admin/ | grep -o '<h1[^>]*>[^<]*</h1>' | sed 's/<[^>]*>//g' 2>/dev/null || echo "❌ Could not extract H1" && echo -e "\nMedia Library title:" && curl -s -m 5 http://localhost/admin/media.php | grep -o '<title>[^<]*</title>' 2>/dev/null || echo "❌ Could not extract title" && echo "Media Library H1:" && curl -s -m 5 http://localhost/admin/media.php | grep -o '<h1[^>]*>[^<]*</h1>' | sed 's/<[^>]*>//g' 2>/dev/null || echo "❌ Could not extract H1" && echo -e "\nPlayer title:" && curl -s -m 5 http://localhost/admin/player.html | grep -o '<title>[^<]*</title>' 2>/dev/null || echo "❌ Could not extract title" && echo "Player H1:" && curl -s -m 5 http://localhost/admin/player.html | grep -o '<h1[^>]*>[^<]*</h1>' | sed 's/<[^>]*>//g' 2>/dev/null || echo "❌ Could not extract H1" && echo -e "\n🎬 === HOUSTON COLLECTIVE BRANDING CHECK ===" && echo "Dashboard Houston mentions:" && curl -s -m 5 http://localhost/admin/ | grep -i -c "houston" 2>/dev/null || echo "0" && echo "Media Houston mentions:" && curl -s -m 5 http://localhost/admin/media.php | grep -i -c "houston" 2>/dev/null || echo "0" && echo "Player Houston mentions:" && curl -s -m 5 http://localhost/admin/player.html | grep -i -c "houston" 2>/dev/null || echo "0" && echo -e "\n🔌 === API ENDPOINTS TEST ===" && echo "Admin root:" && curl -s -I -m 5 http://localhost/admin | head -1 && echo "Direct IP access:" && curl -s -I -m 5 http://144.126.156.143/admin/ | head -1 && echo "Player direct:" && curl -s -I -m 5 http://144.126.156.143/admin/player.html | head -1 && echo -e "\n📊 === DATABASE CONNECTIVITY TEST ===" && echo "Testing playlist AJAX (should show JSON or error):" && curl -s -m 5 -X POST -d "action=get_stats" http://localhost/admin/playlists.php | head -c 100 && echo "..." && echo -e "\nTesting media AJAX (should show JSON or error):" && curl -s -m 5 -X POST -d "action=get_folders" http://localhost/admin/media.php | head -c 100 && echo "..." && echo -e "\n🎨 === CSS/STYLING CHECK ===" && echo "Sunset gradient mentions (Dashboard):" && curl -s -m 5 http://localhost/admin/ | grep -c "gradient.*#" 2>/dev/null || echo "0" && echo "Houston Collective styling (Player):" && curl -s -m 5 http://localhost/admin/player.html | grep -c "linear-gradient" 2>/dev/null || echo "0" && echo -e "\n🔍 === JAVASCRIPT FUNCTIONALITY ===" && echo "Dashboard JS functions:" && curl -s -m 5 http://localhost/admin/ | grep -c "function.*(" 2>/dev/null || echo "0" && echo "Media JS functions:" && curl -s -m 5 http://localhost/admin/media.php | grep -c "function.*(" 2>/dev/null || echo "0" && echo "Player JS functions:" && curl -s -m 5 http://localhost/admin/player.html | grep -c "function.*(" 2>/dev/null || echo "0" && echo -e "\n📱 === RESPONSIVE DESIGN CHECK ===" && echo "Mobile viewport tags:" && curl -s -m 5 http://localhost/admin/ | grep -c "viewport.*width=device-width" 2>/dev/null || echo "0" && echo -e "\n⚡ === REAL-TIME FEATURES ===" && echo "Progress modal (Dashboard):" && curl -s -m 5 http://localhost/admin/ | grep -c "progress-modal" 2>/dev/null || echo "0" && echo "Toggle switches (Playlists):" && curl -s -m 5 http://localhost/admin/playlists.php | grep -c "toggle-switch" 2>/dev/null || echo "0" && echo "Playlist badges (Media):" && curl -s -m 5 http://localhost/admin/media.php | grep -c "playlist-badge" 2>/dev/null || echo "0" && echo -e "\n🚀 === PERFORMANCE CHECK ===" && echo "Total page sizes:" && echo "Dashboard:" $(curl -s -m 5 http://localhost/admin/ | wc -c 2>/dev/null || echo "0") "bytes" && echo "Media:" $(curl -s -m 5 http://localhost/admin/media.php | wc -c 2>/dev/null || echo "0") "bytes" && echo "Playlists:" $(curl -s -m 5 http://localhost/admin/playlists.php | wc -c 2>/dev/null || echo "0") "bytes" && echo "Player:" $(curl -s -m 5 http://localhost/admin/player.html | wc -c 2>/dev/null || echo "0") "bytes" && echo -e "\n
╔══════════════════════════════════════════════════════════════╗
║                   🌐 WEB AUDIT COMPLETE 🌐                   ║
╚══════════════════════════════════════════════════════════════╝
"
echo "
╔══════════════════════════════════════════════════════════════╗
║            🎨 HOUSTON COLLECTIVE CODE ANALYSIS 🎨            ║
╚══════════════════════════════════════════════════════════════╝
" && echo "📄 === DASHBOARD (index.php) STRUCTURE ===" && echo "HTML Title & Main Headers:" && curl -s -m 10 http://localhost/admin/ | grep -E '<title>|<h1|<h2|<h3' | head -5 && echo -e "\nButtons & Interactive Elements:" && curl -s -m 10 http://localhost/admin/ | grep -oE '<button[^>]*>[^<]*</button>|<a[^>]*class="btn[^>]*>[^<]*</a>' | head -10 && echo -e "\nJavaScript Functions:" && curl -s -m 10 http://localhost/admin/ | grep -oE 'function [a-zA-Z_][a-zA-Z0-9_]*\(' | head -10 && echo -e "\nCSS Classes (Houston Collective styling):" && curl -s -m 10 http://localhost/admin/ | grep -oE 'class="[^"]*' | grep -E "(btn|houston|collective|dashboard|card)" | head -8 && echo -e "\nColor Scheme (gradients/colors):" && curl -s -m 10 http://localhost/admin/ | grep -oE 'linear-gradient\([^)]*\)|#[0-9A-Fa-f]{6}|rgba?\([^)]*\)' | head -5 && echo -e "\n📋 === MEDIA LIBRARY (media.php) STRUCTURE ===" && echo "HTML Title & Main Headers:" && curl -s -m 10 http://localhost/admin/media.php | grep -E '<title>|<h1|<h2|<h3' | head -5 && echo -e "\nPlaylist Management Elements:" && curl -s -m 10 http://localhost/admin/media.php | grep -oE 'playlist[^"]*"|badge[^"]*"|dropdown[^"]*"' | head -8 && echo -e "\nAJAX Endpoints:" && curl -s -m 10 http://localhost/admin/media.php | grep -oE "action['\"][^'\"]*" | head -6 && echo -e "\nForm Actions:" && curl -s -m 10 http://localhost/admin/media.php | grep -oE '<form[^>]*>|<input[^>]*type="submit"[^>]*>' | head -5 && echo -e "\n🎵 === PLAYLISTS (playlists.php) STRUCTURE ===" && echo "HTML Title & Navigation:" && curl -s -m 10 http://localhost/admin/playlists.php | grep -E '<title>|nav-tab|tab-content' | head -5 && echo -e "\nToggle Controls:" && curl -s -m 10 http://localhost/admin/playlists.php | grep -oE 'toggle[^"]*"|switch[^"]*"' | head -6 && echo -e "\nTemplate System:" && curl -s -m 10 http://localhost/admin/playlists.php | grep -oE 'template[^"]*"|createFromTemplate[^(]*' | head -4 && echo -e "\n🎬 === PLAYER (player.html) STRUCTURE ===" && echo "HTML Title & Video Elements:" && curl -s -m 10 http://localhost/admin/player.html | grep -E '<title>|<video|<h1' | head -5 && echo -e "\nStreaming JavaScript:" && curl -s -m 10 http://localhost/admin/player.html | grep -oE 'function [a-zA-Z_][a-zA-Z0-9_]*\(' && echo -e "\nHLS Configuration:" && curl -s -m 10 http://localhost/admin/player.html | grep -oE 'streamUrl|Hls\.|m3u8' | head -5 && echo -e "\n🎨 === BRANDING CONSISTENCY ANALYSIS ===" && echo "Houston Collective mentions by page:" && echo "Dashboard:" $(curl -s -m 5 http://localhost/admin/ | grep -i -c "houston collective" 2>/dev/null || echo "0") && echo "Media:" $(curl -s -m 5 http://localhost/admin/media.php | grep -i -c "houston collective" 2>/dev/null || echo "0") && echo "Playlists:" $(curl -s -m 5 http://localhost/admin/playlists.php | grep -i -c "houston collective" 2>/dev/null || echo "0") && echo "Player:" $(curl -s -m 5 http://localhost/admin/player.html | grep -i -c "houston collective" 2>/dev/null || echo "0") && echo -e "\nSunset gradient usage:" && echo "Dashboard:" $(curl -s -m 5 http://localhost/admin/ | grep -c "rgba(253, 94, 83" 2>/dev/null || echo "0") && echo "Media:" $(curl -s -m 5 http://localhost/admin/media.php | grep -c "rgba(253, 94, 83" 2>/dev/null || echo "0") && echo "Playlists:" $(curl -s -m 5 http://localhost/admin/playlists.php | grep -c "rgba(253, 94, 83" 2>/dev/null || echo "0") && echo "Player:" $(curl -s -m 5 http://localhost/admin/player.html | grep -c "rgba(253, 94, 83" 2>/dev/null || echo "0") && echo -e "\n⚡ === INTERACTIVE FUNCTIONALITY CHECK ===" && echo "Dashboard buttons:" && curl -s -m 10 http://localhost/admin/ | grep -oE 'onclick="[^"]*"' | sed 's/onclick="//;s/"//' | head -5 && echo -e "\nMedia library AJAX calls:" && curl -s -m 10 http://localhost/admin/media.php | grep -oE 'fetch\([^)]*\)|\.then\(|\.catch\(' | head -5 && echo -e "\nPlaylist toggle functions:" && curl -s -m 10 http://localhost/admin/playlists.php | grep -oE 'toggle[A-Z][^(]*\(' | head -4 && echo -e "\n🔧 === POTENTIAL ISSUES & OPTIMIZATIONS ===" && echo "Inline styles (should use CSS classes):" && curl -s -m 10 http://localhost/admin/ | grep -c ' style=' 2>/dev/null || echo "0" && echo "External script dependencies:" && curl -s -m 10 http://localhost/admin/player.html | grep -oE 'src="[^"]*\.js"' && echo -e "\nError handling patterns:" && curl -s -m 10 http://localhost/admin/media.php | grep -c 'catch.*error\|\.error\|try.*{' 2>/dev/null || echo "0" && echo -e "\n📊 === CODE METRICS ===" && echo "Lines of JavaScript by page:" && echo "Dashboard:" $(curl -s -m 5 http://localhost/admin/ | grep -c 'function\|addEventListener\|onclick\|fetch' 2>/dev/null || echo "0") && echo "Media:" $(curl -s -m 5 http://localhost/admin/media.php | grep -c 'function\|addEventListener\|onclick\|fetch' 2>/dev/null || echo "0") && echo "Playlists:" $(curl -s -m 5 http://localhost/admin/playlists.php | grep -c 'function\|addEventListener\|onclick\|fetch' 2>/dev/null || echo "0") && echo "Player:" $(curl -s -m 5 http://localhost/admin/player.html | grep -c 'function\|addEventListener\|onclick\|Hls' 2>/dev/null || echo "0") && echo -e "\nCSS complexity (class count):" && echo "Dashboard:" $(curl -s -m 5 http://localhost/admin/ | grep -o 'class="[^"]*"' | wc -l 2>/dev/null || echo "0") && echo "Media:" $(curl -s -m 5 http://localhost/admin/media.php | grep -o 'class="[^"]*"' | wc -l 2>/dev/null || echo "0") && echo -e "\n🎯 === SPECIFIC FUNCTIONALITY TESTS ===" && echo "Content scan button (Dashboard):" && curl -s -m 10 http://localhost/admin/ | grep -oE 'scanContent|Start.*Scan' | head -2 && echo -e "\nPlaylist assignment (Media):" && curl -s -m 10 http://localhost/admin/media.php | grep -oE 'addToPlaylist|removeFromPlaylist' | head -2 && echo -e "\nShuffle/Loop toggles (Playlists):" && curl -s -m 10 http://localhost/admin/playlists.php | grep -oE 'toggleShuffle|toggleLoop' | head -2 && echo -e "\nStream controls (Player):" && curl -s -m 10 http://localhost/admin/player.html | grep -oE 'toggleMute|startStream' | head -2 && echo -e "\n
╔══════════════════════════════════════════════════════════════╗
║                🎨 CODE ANALYSIS COMPLETE 🎨                  ║
╚══════════════════════════════════════════════════════════════╝
"
sudo nano /opt/streamserver/srs/conf/srs.conf
/opt/streamserver/srs/objs/srs -t -c /opt/streamserver/srs/conf/srs.conf
which srs
/usr/local/bin/srs -t -c /opt/streamserver/srs/conf/srs.conf
sudo nano /opt/streamserver/srs/conf/srs.conf
/usr/local/bin/srs -t -c /opt/streamserver/srs/conf/srs.conf
sudo systemctl start srs.service
sudo systemctl status srs.service
cat /opt/streamserver/srs/conf/srs.conf
