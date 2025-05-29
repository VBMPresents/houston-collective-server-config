<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('viewer'); // Minimum role required

/**
 * SRS Streaming Server - Media Library Browser with Folder Navigation
 * Enhanced with Playlist Management Features and Folder Operations
 */

require_once '/opt/streamserver/scripts/database.php';

// Initialize database connection
try {
    $db = new Database();
} catch (Exception $e) {
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'update_video':
            try {
                $result = $db->updateVideo($_POST['video_id'], [
                    'display_name' => $_POST['display_name'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);
                echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'delete_video':
            try {
                $result = $db->deleteVideo($_POST['video_id']);
                echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'add_to_playlist':
            try {
                $video_id = $_POST['video_id'];
                $playlist_id = $_POST['playlist_id'];

                // Check if already in playlist
                $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM video_playlists WHERE video_id = ? AND playlist_id = ?");
                $stmt->execute([$video_id, $playlist_id]);

                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Video is already in this playlist']);
                    exit;
                }

                // Get the next sort order
                $stmt = $db->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM video_playlists WHERE playlist_id = ?");
                $stmt->execute([$playlist_id]);
                $sort_order = $stmt->fetchColumn();

                // Add to playlist
                $stmt = $db->pdo->prepare("INSERT INTO video_playlists (video_id, playlist_id, sort_order, date_added) VALUES (?, ?, ?, datetime('now'))");
                $stmt->execute([$video_id, $playlist_id, $sort_order]);

                // Get playlist name for response
                $stmt = $db->pdo->prepare("SELECT name FROM playlists WHERE id = ?");
                $stmt->execute([$playlist_id]);
                $playlist_name = $stmt->fetchColumn();

                echo json_encode(['success' => true, 'message' => "Added to playlist: {$playlist_name}"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'remove_from_playlist':
            try {
                $video_id = $_POST['video_id'];
                $playlist_id = $_POST['playlist_id'];

                $stmt = $db->pdo->prepare("DELETE FROM video_playlists WHERE video_id = ? AND playlist_id = ?");
                $stmt->execute([$video_id, $playlist_id]);

                // Get playlist name for response
                $stmt = $db->pdo->prepare("SELECT name FROM playlists WHERE id = ?");
                $stmt->execute([$playlist_id]);
                $playlist_name = $stmt->fetchColumn();

                echo json_encode(['success' => true, 'message' => "Removed from playlist: {$playlist_name}"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'add_folder_to_playlist':
            try {
                $folder_path = $_POST['folder_path'];
                $playlist_id = $_POST['playlist_id'];

                // Get all videos in this folder
                $stmt = $db->pdo->prepare("SELECT id FROM videos WHERE file_path LIKE ? AND is_active = 1");
                $stmt->execute(["/opt/streamserver/content/{$folder_path}/%"]);
                $videos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($videos)) {
                    echo json_encode(['success' => false, 'message' => 'No videos found in this folder']);
                    exit;
                }

                // Get next sort order
                $stmt = $db->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM video_playlists WHERE playlist_id = ?");
                $stmt->execute([$playlist_id]);
                $start_order = $stmt->fetchColumn();

                $added_count = 0;
                foreach ($videos as $video_id) {
                    // Check if already in playlist
                    $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM video_playlists WHERE video_id = ? AND playlist_id = ?");
                    $stmt->execute([$video_id, $playlist_id]);

                    if ($stmt->fetchColumn() == 0) {
                        $start_order++;
                        $stmt = $db->pdo->prepare("INSERT INTO video_playlists (video_id, playlist_id, sort_order, date_added) VALUES (?, ?, ?, datetime('now'))");
                        $stmt->execute([$video_id, $playlist_id, $start_order]);
                        $added_count++;
                    }
                }

                // Get playlist name
                $stmt = $db->pdo->prepare("SELECT name FROM playlists WHERE id = ?");
                $stmt->execute([$playlist_id]);
                $playlist_name = $stmt->fetchColumn();

                echo json_encode(['success' => true, 'message' => "Added {$added_count} videos from folder to playlist: {$playlist_name}"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'create_playlist_from_folder':
            try {
                $folder_path = $_POST['folder_path'];
                $folder_name = basename($folder_path);
                $playlist_name = "📁 " . $folder_name;

                // Check if playlist already exists
                $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM playlists WHERE name = ?");
                $stmt->execute([$playlist_name]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Playlist with this name already exists']);
                    exit;
                }

                // Create playlist
                $stmt = $db->pdo->prepare("INSERT INTO playlists (name, description, playlist_type, is_active, date_created) VALUES (?, ?, 'standard', 1, datetime('now'))");
                $description = "Auto-generated playlist from folder: {$folder_path}";
                $stmt->execute([$playlist_name, $description]);
                $playlist_id = $db->pdo->lastInsertId();

                // Get all videos in folder
                $stmt = $db->pdo->prepare("SELECT id FROM videos WHERE file_path LIKE ? AND is_active = 1 ORDER BY display_name");
                $stmt->execute(["/opt/streamserver/content/{$folder_path}/%"]);
                $videos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($videos)) {
                    echo json_encode(['success' => false, 'message' => 'No videos found in this folder']);
                    exit;
                }

                // Add all videos to playlist
                $sort_order = 0;
                foreach ($videos as $video_id) {
                    $sort_order++;
                    $stmt = $db->pdo->prepare("INSERT INTO video_playlists (video_id, playlist_id, sort_order, date_added) VALUES (?, ?, ?, datetime('now'))");
                    $stmt->execute([$video_id, $playlist_id, $sort_order]);
                }

                echo json_encode(['success' => true, 'message' => "Created playlist '{$playlist_name}' with " . count($videos) . " videos"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get current folder from URL parameter
$current_folder = $_GET['folder'] ?? '';
$current_folder = trim($current_folder, '/');

// Build breadcrumb path
$breadcrumbs = [];
if (!empty($current_folder)) {
    $parts = explode('/', $current_folder);
    $path = '';
    foreach ($parts as $part) {
        $path .= ($path ? '/' : '') . $part;
        $breadcrumbs[] = ['name' => $part, 'path' => $path];
    }
}

// Get pagination and filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 25)));
$search = $_GET['search'] ?? '';

if (empty($current_folder)) {
    // Show folders
    try {
        $where_conditions = ["(parent_folder IS NULL OR parent_folder = '')"];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "folder_name LIKE ?";
            $params[] = "%{$search}%";
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM folders WHERE {$where_clause}");
        $stmt->execute($params);
        $total_items = $stmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $db->pdo->prepare("SELECT * FROM folders WHERE {$where_clause} ORDER BY folder_name LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = ceil($total_items / $limit);
        $items = $folders;
        $items_type = 'folders';
    } catch (Exception $e) {
        $folders = [];
        $total_items = 0;
        $total_pages = 0;
        $error_message = $e->getMessage();
        $items = [];
        $items_type = 'folders';
    }
} else {
    // Show files in current folder
    try {
        $folder_pattern = "/opt/streamserver/content/{$current_folder}/%";
        
        $where_conditions = ["file_path LIKE ? AND file_path NOT LIKE ?"];
        $params = [$folder_pattern, $folder_pattern . "/%"];

        if (!empty($search)) {
            $where_conditions[] = "display_name LIKE ?";
            $params[] = "%{$search}%";
        }

        $where_clause = implode(' AND ', $where_conditions);

        $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM videos WHERE {$where_clause}");
        $stmt->execute($params);
        $total_items = $stmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $db->pdo->prepare("SELECT * FROM videos WHERE {$where_clause} ORDER BY display_name LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = ceil($total_items / $limit);
        $items = $videos;
        $items_type = 'videos';

        // Get playlist memberships for videos
        $video_playlists = [];
        if (!empty($videos)) {
            $video_ids = array_column($videos, 'id');
            $placeholders = str_repeat('?,', count($video_ids) - 1) . '?';
            $stmt = $db->pdo->prepare("
                SELECT vp.video_id, p.id, p.name, p.playlist_type
                FROM video_playlists vp
                JOIN playlists p ON vp.playlist_id = p.id
                WHERE vp.video_id IN ($placeholders) AND p.is_active = 1
                ORDER BY p.name
            ");
            $stmt->execute($video_ids);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $video_playlists[$row['video_id']][] = $row;
            }
        }
    } catch (Exception $e) {
        $videos = [];
        $total_items = 0;
        $total_pages = 0;
        $error_message = $e->getMessage();
        $items = [];
        $items_type = 'videos';
        $video_playlists = [];
    }

    // Also get subfolders in current folder
    try {
        $stmt = $db->pdo->prepare("SELECT * FROM folders WHERE parent_folder = ? ORDER BY folder_name");
        $stmt->execute([$current_folder]);
        $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $subfolders = [];
    }
}

// Get all active playlists for dropdowns
$all_playlists = [];
try {
    $stmt = $db->pdo->query("SELECT id, name, playlist_type FROM playlists WHERE is_active = 1 ORDER BY name");
    $all_playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore playlist loading errors
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Library - The Houston Collective</title>
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
            background: linear-gradient(135deg,
                rgba(253, 94, 83, 0.15) 0%,
                rgba(255, 109, 98, 0.12) 50%,
                rgba(238, 79, 68, 0.15) 100%);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 206, 97, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
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
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #FFCE61, #FFE58A, #FFCE61);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(255, 206, 97, 0.6);
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        /* Breadcrumbs */
        .breadcrumbs {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 206, 97, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }

        .breadcrumb-item {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .breadcrumb-item:hover {
            background: rgba(255, 206, 97, 0.2);
            color: #ffffff;
        }

        .breadcrumb-separator {
            color: rgba(255, 206, 97, 0.6);
            font-weight: bold;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        /* Filters and Search */
        .filters {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 1rem;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .filter-input {
            padding: 12px 16px;
            border: 2px solid rgba(255, 206, 97, 0.4);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 15px rgba(255, 206, 97, 0.3);
        }

        /* Folder Actions */
        .folder-actions {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 206, 97, 0.4);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Grid Layouts */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Folder Cards */
        .folder-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
            cursor: pointer;
        }

        .folder-card::before {
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

        .folder-card:hover::before {
            left: 100%;
        }

        .folder-card:hover {
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

        .folder-thumbnail {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg,
                rgba(255, 206, 97, 0.6) 0%,
                rgba(255, 229, 138, 0.5) 50%,
                rgba(255, 206, 97, 0.6) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: rgba(0, 0, 0, 0.8);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .folder-info {
            padding: 25px;
            position: relative;
            z-index: 1;
        }

        .folder-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .folder-meta {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .folder-actions-card {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Video Cards - Keep existing styles */
        .video-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
        }

        .video-card::before {
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

        .video-card:hover::before {
            left: 100%;
        }

        .video-card:hover {
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

        .video-thumbnail {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg,
                rgba(253, 94, 83, 0.6) 0%,
                rgba(255, 109, 98, 0.5) 50%,
                rgba(238, 79, 68, 0.6) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.9);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .video-info {
            padding: 25px;
            position: relative;
            z-index: 1;
        }

        .video-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        /* Playlist Badges */
        .playlist-badges {
            margin-bottom: 15px;
        }

        .playlist-badge {
            display: inline-block;
            padding: 6px 12px;
            margin: 3px;
            background: linear-gradient(135deg, #6bcf7f, #52c272);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 2px solid rgba(107, 207, 127, 0.6);
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .playlist-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(107, 207, 127, 0.4);
            border-color: rgba(107, 207, 127, 1);
        }

        .playlist-badge .remove-btn {
            margin-left: 8px;
            font-weight: bold;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }

        .playlist-badge .remove-btn:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(1.2);
        }

        .no-playlists {
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .video-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border: 1px solid rgba(255, 206, 97, 0.3);
        }

        .meta-item span:first-child {
            font-weight: 600;
            color: rgba(255, 206, 97, 0.9);
        }

        .video-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-edit {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border: 2px solid rgba(255, 206, 97, 0.6);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(243, 156, 18, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: 2px solid rgba(231, 76, 60, 0.6);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
        }

        /* Playlist Dropdown */
        .playlist-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: 2px solid rgba(52, 152, 219, 0.6);
        }

        .dropdown-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.95) 0%,
                rgba(31, 33, 77, 0.9) 50%,
                rgba(0, 0, 0, 0.95) 100%);
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.6);
            backdrop-filter: blur(10px);
            bottom: 100%;
            left: 0;
            margin-bottom: 5px;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            color: #ffffff;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255, 206, 97, 0.2);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: rgba(255, 206, 97, 0.2);
            color: #ffffff;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid rgba(255, 206, 97, 0.4);
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .pagination a:hover {
            background: rgba(255, 206, 97, 0.2);
            border-color: rgba(255, 229, 138, 0.8);
            transform: translateY(-2px);
        }

        .pagination .current {
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62);
            border-color: rgba(255, 229, 138, 0.8);
            box-shadow: 0 4px 15px rgba(253, 94, 83, 0.4);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.9) 0%,
                rgba(31, 33, 77, 0.8) 50%,
                rgba(0, 0, 0, 0.9) 100%);
            padding: 35px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            backdrop-filter: blur(20px);
            border: 3px solid rgba(255, 206, 97, 0.6);
        }

        .modal h3 {
            margin-bottom: 25px;
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            font-size: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255, 206, 97, 0.4);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(255, 229, 138, 0.8);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 15px rgba(255, 206, 97, 0.3);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .error-message {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.9), rgba(192, 57, 43, 0.9));
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 2px solid rgba(231, 76, 60, 0.6);
            font-weight: 600;
        }

        .success-message {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.9), rgba(46, 125, 50, 0.9));
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 2px solid rgba(39, 174, 96, 0.6);
            font-weight: 600;
        }

        .results-summary {
            margin-bottom: 25px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-weight: 500;
            text-align: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            border: 1px solid rgba(255, 206, 97, 0.3);
        }

        /* Message */
        .message {
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

        .message.show {
            transform: translateX(0);
        }

        .message.success {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.9), rgba(46, 125, 50, 0.9));
            border: 2px solid rgba(39, 174, 96, 0.6);
        }

        .message.error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.9), rgba(192, 57, 43, 0.9));
            border: 2px solid rgba(231, 76, 60, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                font-size: 2rem;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .video-meta {
                grid-template-columns: 1fr;
            }

            .video-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php include "nav.php"; ?>
    <div class="container">
        <div class="header">
            <h1>Media Library</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">← Dashboard</a>
                <button class="btn btn-primary" onclick="scanContent()">🔍 Scan Content</button>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
            <a href="media.php" class="breadcrumb-item">📁 Content Root</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="breadcrumb-separator">→</span>
                <a href="media.php?folder=<?= urlencode($crumb['path']) ?>" class="breadcrumb-item">
                    📁 <?= htmlspecialchars($crumb['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Folder Actions (only show when viewing a folder with videos) -->
        <?php if (!empty($current_folder) && $items_type === 'videos' && !empty($items)): ?>
            <div class="folder-actions">
                <strong style="color: rgba(255, 206, 97, 0.9);">Folder Actions:</strong>
                
                <div class="playlist-dropdown">
                    <button class="btn btn-small dropdown-btn" onclick="toggleFolderDropdown()">
                        📋 Add Folder to Playlist
                    </button>
                    <div class="dropdown-content" id="folder-dropdown">
                        <?php foreach ($all_playlists as $playlist): ?>
                            <div class="dropdown-item" onclick="addFolderToPlaylist('<?= htmlspecialchars($current_folder) ?>', <?= $playlist['id'] ?>, '<?= htmlspecialchars($playlist['name']) ?>')">
                                📋 <?= htmlspecialchars($playlist['name']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($all_playlists)): ?>
                            <div class="dropdown-item" onclick="alert('No playlists available. Create a playlist first.')">
                                No playlists available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-small btn-primary" onclick="createPlaylistFromFolder('<?= htmlspecialchars($current_folder) ?>')">
                    ➕ Create Playlist from Folder
                </button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <form class="filters" method="GET">
            <?php if (!empty($current_folder)): ?>
                <input type="hidden" name="folder" value="<?= htmlspecialchars($current_folder) ?>">
            <?php endif; ?>
            
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Search <?= $items_type ?>..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label>Per Page</label>
                <select name="limit" class="filter-input">
                    <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>

            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>

        <!-- Results Summary -->
        <div class="results-summary">
            <?php if ($items_type === 'folders'): ?>
                Showing <?= count($items) ?> of <?= number_format($total_items) ?> folders
                (Page <?= $page ?> of <?= $total_pages ?>)
            <?php else: ?>
                Showing <?= count($items) ?> of <?= number_format($total_items) ?> videos in folder
                (Page <?= $page ?> of <?= $total_pages ?>)
                <?php if (!empty($subfolders)): ?>
                    <br>📁 <?= count($subfolders) ?> subfolders also available
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Show subfolders first when viewing files -->
            <?php if ($items_type === 'videos' && !empty($subfolders)): ?>
                <?php foreach ($subfolders as $folder): ?>
                    <div class="folder-card" onclick="navigateToFolder('<?= htmlspecialchars($folder['folder_path']) ?>')">
                        <div class="folder-thumbnail">
                            📁
                        </div>
                        <div class="folder-info">
                            <div class="folder-title"><?= htmlspecialchars($folder['folder_name']) ?></div>
                            <div class="folder-meta">
                                📊 <?= $folder['video_count'] ?> videos
                            </div>
                            <div class="folder-actions-card">
                                <button class="btn btn-small btn-secondary" onclick="event.stopPropagation(); navigateToFolder('<?= htmlspecialchars($folder['folder_path']) ?>')">
                                    Open Folder
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Show main content (folders or videos) -->
            <?php if ($items_type === 'folders'): ?>
                <!-- Show folders -->
                <?php foreach ($items as $folder): ?>
                    <div class="folder-card" onclick="navigateToFolder('<?= htmlspecialchars($folder['folder_path']) ?>')">
                        <div class="folder-thumbnail">
                            📁
                        </div>
                        <div class="folder-info">
                            <div class="folder-title"><?= htmlspecialchars($folder['folder_name']) ?></div>
                            <div class="folder-meta">
                                📊 <?= $folder['video_count'] ?> videos
                            </div>
                            <div class="folder-actions-card">
                                <button class="btn btn-small btn-secondary" onclick="event.stopPropagation(); navigateToFolder('<?= htmlspecialchars($folder['folder_path']) ?>')">
                                    Open Folder
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Show videos -->
                <?php foreach ($items as $video): ?>
                    <div class="video-card" data-video-id="<?= $video['id'] ?>">
                        <div class="video-thumbnail">
                            🎬
                        </div>

                        <div class="video-info">
                            <div class="video-title"><?= htmlspecialchars($video['display_name']) ?></div>

                            <!-- Playlist Badges -->
                            <div class="playlist-badges">
                                <?php if (isset($video_playlists[$video['id']]) && !empty($video_playlists[$video['id']])): ?>
                                    <?php foreach ($video_playlists[$video['id']] as $playlist): ?>
                                        <span class="playlist-badge" title="<?= htmlspecialchars($playlist['playlist_type']) ?>">
                                            📋 <?= htmlspecialchars($playlist['name']) ?>
                                            <span class="remove-btn" onclick="removeFromPlaylist(<?= $video['id'] ?>, <?= $playlist['id'] ?>, '<?= htmlspecialchars($playlist['name']) ?>')" title="Remove from playlist">×</span>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-playlists">Not in any playlists</div>
                                <?php endif; ?>
                            </div>

                            <div class="video-meta">
                                <div class="meta-item">
                                    <span>Duration:</span>
                                    <span><?= formatDuration($video['duration'] ?? 0) ?></span>
                                </div>
                                <div class="meta-item">
                                    <span>Size:</span>
                                    <span><?= formatFileSize($video['file_size'] ?? 0) ?></span>
                                </div>
                                <div class="meta-item">
                                    <span>Format:</span>
                                    <span><?= htmlspecialchars($video['format'] ?? 'Unknown') ?></span>
                                </div>
                                <div class="meta-item">
                                    <span>Resolution:</span>
                                    <span><?= formatResolution($video['resolution'] ?? 'Unknown') ?></span>
                                </div>
                            </div>

                            <div class="video-actions">
                                <button class="btn btn-small btn-edit" onclick="editVideo(<?= $video['id'] ?>, '<?= htmlspecialchars($video['display_name']) ?>', <?= $video['is_active'] ?>)">
                                    Edit
                                </button>

                                <div class="playlist-dropdown">
                                    <button class="btn btn-small dropdown-btn" onclick="toggleDropdown(<?= $video['id'] ?>)">
                                        + Playlist
                                    </button>
                                    <div class="dropdown-content" id="dropdown-<?= $video['id'] ?>">
                                        <?php foreach ($all_playlists as $playlist): ?>
                                            <div class="dropdown-item" onclick="addToPlaylist(<?= $video['id'] ?>, <?= $playlist['id'] ?>, '<?= htmlspecialchars($playlist['name']) ?>')">
                                                📋 <?= htmlspecialchars($playlist['name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($all_playlists)): ?>
                                            <div class="dropdown-item" onclick="alert('No playlists available. Create a playlist first.')">
                                                No playlists available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <button class="btn btn-small btn-delete" onclick="deleteVideo(<?= $video['id'] ?>, '<?= htmlspecialchars($video['display_name']) ?>')">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Video Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Video</h3>
            <form id="editForm">
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" id="editDisplayName" name="display_name" required>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="editIsActive" name="is_active" value="1">
                        <label for="editIsActive">Active</label>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentVideoId = null;

        function navigateToFolder(folderPath) {
            window.location.href = `media.php?folder=${encodeURIComponent(folderPath)}`;
        }

        function toggleFolderDropdown() {
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-content:not(#folder-dropdown)').forEach(dropdown => {
                dropdown.classList.remove('show');
            });

            // Toggle folder dropdown
            const dropdown = document.getElementById('folder-dropdown');
            dropdown.classList.toggle('show');
        }

        function addFolderToPlaylist(folderPath, playlistId, playlistName) {
            if (confirm(`Add all videos from this folder to "${playlistName}"?`)) {
                fetch('media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add_folder_to_playlist&folder_path=${encodeURIComponent(folderPath)}&playlist_id=${playlistId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                    // Close dropdown
                    document.getElementById('folder-dropdown').classList.remove('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while adding folder to playlist.', 'error');
                    document.getElementById('folder-dropdown').classList.remove('show');
                });
            }
        }

        function createPlaylistFromFolder(folderPath) {
            const folderName = folderPath.split('/').pop();
            if (confirm(`Create a new playlist from all videos in "${folderName}" folder?`)) {
                fetch('media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=create_playlist_from_folder&folder_path=${encodeURIComponent(folderPath)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while creating playlist.', 'error');
                });
            }
        }

        function editVideo(videoId, displayName, isActive) {
            currentVideoId = videoId;
            document.getElementById('editDisplayName').value = displayName;
            document.getElementById('editIsActive').checked = isActive == 1;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
            currentVideoId = null;
        }

        function deleteVideo(videoId, displayName) {
            if (confirm(`Are you sure you want to delete "${displayName}"? This action cannot be undone.`)) {
                fetch('media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_video&video_id=${videoId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        location.reload();
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while deleting the video.', 'error');
                });
            }
        }

        function toggleDropdown(videoId) {
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-content:not(#folder-dropdown)').forEach(dropdown => {
                if (dropdown.id !== `dropdown-${videoId}`) {
                    dropdown.classList.remove('show');
                }
            });

            // Toggle this dropdown
            const dropdown = document.getElementById(`dropdown-${videoId}`);
            dropdown.classList.toggle('show');
        }

        function addToPlaylist(videoId, playlistId, playlistName) {
            fetch('media.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_playlist&video_id=${videoId}&playlist_id=${playlistId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
                // Close dropdown
                document.getElementById(`dropdown-${videoId}`).classList.remove('show');
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while adding to playlist.', 'error');
                document.getElementById(`dropdown-${videoId}`).classList.remove('show');
            });
        }

        function removeFromPlaylist(videoId, playlistId, playlistName) {
            if (confirm(`Remove this video from "${playlistName}"?`)) {
                fetch('media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove_from_playlist&video_id=${videoId}&playlist_id=${playlistId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while removing from playlist.', 'error');
                });
            }
        }

        function showMessage(message, type) {
            // Remove any existing messages
            const existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);

            // Show message
            setTimeout(() => messageDiv.classList.add('show'), 100);

            // Hide message after 3 seconds
            setTimeout(() => {
                messageDiv.classList.remove('show');
                setTimeout(() => messageDiv.remove(), 300);
            }, 3000);
        }

        function scanContent() {
            if (confirm('Scan for new content? This may take several minutes for large libraries.')) {
                showMessage('Content scanning started. This page will refresh when complete.', 'success');

                fetch('/opt/streamserver/scripts/content_scanner.py', {
                    method: 'POST',
                })
                .then(response => {
                    showMessage('Content scan completed. Refreshing page...', 'success');
                    setTimeout(() => location.reload(), 2000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Content scan may have completed. Refreshing page...', 'success');
                    setTimeout(() => location.reload(), 2000);
                });
            }
        }

        // Handle edit form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', 'update_video');
            formData.append('video_id', currentVideoId);
            formData.append('display_name', document.getElementById('editDisplayName').value);
            if (document.getElementById('editIsActive').checked) {
                formData.append('is_active', '1');
            }

            fetch('media.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while updating the video.', 'error');
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('editModal');
            if (e.target === modal) {
                closeModal();
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.matches('.dropdown-btn')) {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions for display formatting
function formatDuration($seconds) {
    if ($seconds <= 0) return '00:00';
    return gmdate($seconds >= 3600 ? "H:i:s" : "i:s", $seconds);
}

function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}

function formatResolution($resolution) {
    if (empty($resolution) || $resolution === 'Unknown') return 'Unknown';
    return $resolution;
}
?>
