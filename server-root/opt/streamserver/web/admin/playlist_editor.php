<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('editor'); // Editors and admins can edit playlists

// Rest of your existing code stays the same...
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
