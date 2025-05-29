<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('editor'); // Editors and admins can manage playlists

require_once '../../scripts/database.php';

$db = new Database();
$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_playlist':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $type = $_POST['type'];
                $shuffle = isset($_POST['shuffle_enabled']) ? 1 : 0;
                $loop = isset($_POST['loop_enabled']) ? 1 : 0;
                $priority = (int)$_POST['priority'];

                if (empty($name)) {
                    $message = 'Playlist name is required';
                    $messageType = 'error';
                } else {
                    // Check for duplicate names
                    $existing = $db->getPlaylistByName($name);
                    if ($existing) {
                        $message = 'A playlist with this name already exists';
                        $messageType = 'error';
                    } else {
                        $result = $db->createPlaylist($name, $description, $type, $shuffle, $loop, $priority);
                        if ($result) {
                            $message = 'Playlist "' . htmlspecialchars($name) . '" created successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create playlist';
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'delete_playlist':
                $id = (int)$_POST['playlist_id'];
                if ($id > 1) { // Don't allow deleting the default General Rotation playlist
                    $result = $db->deletePlaylist($id);
                    if ($result) {
                        $message = 'Playlist deleted successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete playlist';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Cannot delete the default General Rotation playlist';
                    $messageType = 'error';
                }
                break;

            case 'toggle_playlist':
                $id = (int)$_POST['playlist_id'];
                $active = (int)$_POST['is_active'];
                $result = $db->togglePlaylist($id, $active);
                if ($result) {
                    $status = $active ? 'activated' : 'deactivated';
                    $message = 'Playlist ' . $status . ' successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update playlist status';
                    $messageType = 'error';
                }
                break;

            case 'toggle_shuffle':
                $id = (int)$_POST['playlist_id'];
                $shuffle = (int)$_POST['shuffle_enabled'];
                try {
                    $stmt = $db->pdo->prepare("UPDATE playlists SET shuffle_enabled = ?, date_modified = datetime('now') WHERE id = ?");
                    $result = $stmt->execute([$shuffle, $id]);
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Shuffle ' . ($shuffle ? 'enabled' : 'disabled')]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update shuffle setting']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;

            case 'toggle_loop':
                $id = (int)$_POST['playlist_id'];
                $loop = (int)$_POST['loop_enabled'];
                try {
                    $stmt = $db->pdo->prepare("UPDATE playlists SET loop_enabled = ?, date_modified = datetime('now') WHERE id = ?");
                    $result = $stmt->execute([$loop, $id]);
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Loop ' . ($loop ? 'enabled' : 'disabled')]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update loop setting']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
        }
    }
}

// Get all playlists
$playlists = $db->getAllPlaylists();
$playlistStats = $db->getPlaylistStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playlist Management - The Houston Collective Streaming</title>
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
            background: linear-gradient(135deg,
                rgba(253, 94, 83, 0.15) 0%,
                rgba(255, 109, 98, 0.12) 50%,
                rgba(238, 79, 68, 0.15) 100%);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 206, 97, 0.3);
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

        .header h1 {
            font-size: 3.2rem;
            font-weight: 900;
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.3rem;
            opacity: 0.9;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }

        .nav-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 15px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            flex-wrap: wrap;
        }

        .nav-tab {
            padding: 14px 28px;
            background: rgba(0, 0, 0, 0.6);
            border: 3px solid rgba(255, 206, 97, 0.4);
            color: #ffffff;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.4s ease;
            font-size: 1.1rem;
            font-weight: 700;
            position: relative;
            overflow: hidden;
        }

        .nav-tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                transparent,
                rgba(255, 206, 97, 0.2),
                transparent);
            transition: left 0.6s ease;
        }

        .nav-tab:hover::before {
            left: 100%;
        }

        .nav-tab.active,
        .nav-tab:hover {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            border-color: rgba(255, 229, 138, 0.8);
            color: #ffffff;
            transform: translateY(-3px);
            box-shadow:
                0 12px 30px rgba(253, 94, 83, 0.5),
                0 0 20px rgba(255, 206, 97, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            position: relative;
            overflow: hidden;
        }

        .card::before {
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

        .card:hover::before {
            left: 100%;
        }

        .card h2 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border: 3px solid rgba(255, 206, 97, 0.4);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.4s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 20px rgba(255, 206, 97, 0.3);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .checkbox-group {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            border: 2px solid rgba(255, 206, 97, 0.3);
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: rgba(255, 206, 97, 0.6);
            background: rgba(255, 206, 97, 0.1);
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            scale: 1.3;
        }

        .checkbox-item label {
            margin: 0;
            font-weight: 600;
            color: #ffffff;
        }

        .priority-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }

        .priority-option {
            position: relative;
        }

        .priority-option input[type="radio"] {
            opacity: 0;
            position: absolute;
        }

        .priority-option label {
            display: block;
            padding: 20px;
            border: 3px solid rgba(255, 206, 97, 0.4);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s ease;
            font-weight: 700;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
        }

        .priority-option label:hover {
            border-color: rgba(255, 206, 97, 0.8);
            background: rgba(255, 206, 97, 0.1);
        }

        .priority-option input[type="radio"]:checked + label {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62);
            border-color: rgba(255, 229, 138, 0.8);
            color: #ffffff;
            box-shadow: 0 8px 25px rgba(253, 94, 83, 0.4);
        }

        .btn {
            padding: 15px 30px;
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

        .btn-danger {
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: #fff;
            border: 2px solid rgba(255, 71, 87, 0.6);
        }

        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(255, 71, 87, 0.5);
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

        .playlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }

        .playlist-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .playlist-card::before {
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

        .playlist-card:hover::before {
            left: 100%;
        }

        .playlist-card:hover {
            transform: translateY(-8px);
            background: linear-gradient(135deg,
                rgba(253, 94, 83, 0.3) 0%,
                rgba(255, 109, 98, 0.25) 50%,
                rgba(238, 79, 68, 0.3) 100%);
            border-color: rgba(255, 229, 138, 0.8);
            box-shadow:
                0 15px 40px rgba(253, 94, 83, 0.4),
                0 0 25px rgba(255, 206, 97, 0.3);
        }

        .playlist-card h3 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }

        .playlist-card p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .playlist-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
            position: relative;
            z-index: 1;
        }

        .meta-badge {
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            border: 2px solid;
        }

        .meta-badge.type {
            background: rgba(107, 207, 127, 0.3);
            color: #6bcf7f;
            border-color: #6bcf7f;
        }

        .meta-badge.priority {
            background: rgba(255, 206, 97, 0.3);
            color: #FFCE61;
            border-color: #FFCE61;
        }

        .meta-badge.status {
            background: rgba(255, 109, 98, 0.3);
            color: #FF6D62;
            border-color: #FF6D62;
        }

        /* Toggle Switches */
        .toggle-controls {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            position: relative;
            z-index: 1;
        }

        .toggle-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 25px;
            border: 2px solid rgba(255, 206, 97, 0.4);
            cursor: pointer;
            transition: all 0.4s ease;
        }

        .toggle-switch.active {
            background: linear-gradient(135deg, #6bcf7f, #52c272);
            border-color: rgba(107, 207, 127, 0.8);
            box-shadow: 0 0 15px rgba(107, 207, 127, 0.4);
        }

        .toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, #FFCE61, #FFE58A);
            border-radius: 50%;
            transition: all 0.4s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .toggle-switch.active .toggle-slider {
            transform: translateX(30px);
            background: linear-gradient(135deg, #ffffff, #f0f0f0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
        }

        .toggle-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }

        .playlist-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .message {
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid;
        }

        .message.success {
            background: rgba(107, 207, 127, 0.2);
            border-color: #6bcf7f;
            color: #6bcf7f;
        }

        .message.error {
            background: rgba(255, 71, 87, 0.2);
            border-color: #ff4757;
            color: #ff4757;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
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

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 2000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(135deg, rgba(107, 207, 127, 0.9), rgba(82, 194, 114, 0.9));
            border: 2px solid rgba(107, 207, 127, 0.6);
        }

        .notification.error {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.9), rgba(255, 56, 56, 0.9));
            border: 2px solid rgba(255, 71, 87, 0.6);
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

            .toggle-controls {
                flex-direction: column;
                gap: 15px;
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

                        <!-- Toggle Controls -->
                        <div class="toggle-controls">
                            <div class="toggle-group">
                                <span class="toggle-label">🔀 Shuffle</span>
                                <div class="toggle-switch <?php echo $playlist['shuffle_enabled'] ? 'active' : ''; ?>" 
                                     onclick="toggleShuffle(<?php echo $playlist['id']; ?>, <?php echo $playlist['shuffle_enabled'] ? 0 : 1; ?>)">
                                    <div class="toggle-slider"></div>
                                </div>
                            </div>
                            
                            <div class="toggle-group">
                                <span class="toggle-label">🔁 Loop</span>
                                <div class="toggle-switch <?php echo $playlist['loop_enabled'] ? 'active' : ''; ?>" 
                                     onclick="toggleLoop(<?php echo $playlist['id']; ?>, <?php echo $playlist['loop_enabled'] ? 0 : 1; ?>)">
                                    <div class="toggle-slider"></div>
                                </div>
                            </div>
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

        function toggleShuffle(playlistId, shuffleEnabled) {
            fetch('playlists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_shuffle&playlist_id=${playlistId}&shuffle_enabled=${shuffleEnabled}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update toggle visual state
                    const toggle = event.target.closest('.toggle-switch');
                    if (shuffleEnabled) {
                        toggle.classList.add('active');
                        toggle.setAttribute('onclick', `toggleShuffle(${playlistId}, 0)`);
                    } else {
                        toggle.classList.remove('active');
                        toggle.setAttribute('onclick', `toggleShuffle(${playlistId}, 1)`);
                    }
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating shuffle setting.', 'error');
            });
        }

        function toggleLoop(playlistId, loopEnabled) {
            fetch('playlists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_loop&playlist_id=${playlistId}&loop_enabled=${loopEnabled}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update toggle visual state
                    const toggle = event.target.closest('.toggle-switch');
                    if (loopEnabled) {
                        toggle.classList.add('active');
                        toggle.setAttribute('onclick', `toggleLoop(${playlistId}, 0)`);
                    } else {
                        toggle.classList.remove('active');
                        toggle.setAttribute('onclick', `toggleLoop(${playlistId}, 1)`);
                    }
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating loop setting.', 'error');
            });
        }

        function showNotification(message, type) {
            // Remove any existing notifications
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);

            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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
