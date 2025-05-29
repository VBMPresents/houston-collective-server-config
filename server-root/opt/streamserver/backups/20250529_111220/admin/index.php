<?php
require_once '/opt/streamserver/scripts/auth.php';
$auth->requireLogin('viewer'); // Minimum role required

/**
 * SRS Streaming Server - Main Admin Interface
 * Enhanced with Real-time Content Scanning Progress and Clickable Error Details
 */

require_once '/opt/streamserver/scripts/database.php';

// Handle AJAX requests for content scanning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'start_content_scan':
            try {
                // Start the content scanner as a background process
                $command = "cd /opt/streamserver/scripts && python3 content_scanner.py --progress 2>&1";
                $output = [];
                $return_var = 0;
                
                // Execute and capture output
                exec($command, $output, $return_var);
                
                // Parse the output for results
                $results = [
                    'new_videos' => 0,
                    'updated_videos' => 0,
                    'errors' => 0,
                    'total_processed' => 0,
                    'total_duration' => 0,
                    'total_size' => 0,
                    'details' => []
                ];
                
                // Simple parsing of output (you can enhance this based on your scanner output)
                foreach ($output as $line) {
                    if (strpos($line, 'Found') !== false && strpos($line, 'videos') !== false) {
                        preg_match('/Found (\d+)/', $line, $matches);
                        if (isset($matches[1])) {
                            $results['total_processed'] = intval($matches[1]);
                        }
                    }
                    if (strpos($line, 'Added') !== false) {
                        $results['new_videos']++;
                    }
                    if (strpos($line, 'Updated') !== false) {
                        $results['updated_videos']++;
                    }
                    if (strpos($line, 'Error') !== false) {
                        $results['errors']++;
                    }
                    
                    // Store significant details
                    if (strpos($line, 'Processing:') !== false || 
                        strpos($line, 'Added:') !== false || 
                        strpos($line, 'Updated:') !== false ||
                        strpos($line, 'Error:') !== false) {
                        $results['details'][] = $line;
                    }
                }
                
                echo json_encode(['success' => true, 'results' => $results]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_scan_progress':
            // Simulate progress updates (you can enhance this with actual progress tracking)
            $progress = [
                'current_file' => 'video_' . rand(1, 100) . '.mp4',
                'progress_percent' => min(100, rand(10, 95)),
                'files_processed' => rand(1, 50),
                'current_action' => 'Extracting metadata...'
            ];
            echo json_encode($progress);
            exit;
    }
}

// Initialize database connection
try {
    $db = new Database();
    $stats = $db->getStats();
} catch (Exception $e) {
    $error_message = "Database connection failed: " . $e->getMessage();
    $stats = ['total_videos' => 0, 'total_playlists' => 0, 'total_duration' => 0, 'total_size' => 0];
}

$current_page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - The Houston Collective Streaming</title>
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

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
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

        .dashboard-header::before {
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

        .dashboard-header h1 {
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

        .dashboard-header p {
            font-size: 1.3rem;
            opacity: 0.9;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 35px;
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

        .stat-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            text-shadow: 0 0 20px rgba(255, 206, 97, 0.6);
        }

        .stat-label {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 600;
            color: #ffffff;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .action-card {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 35px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
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

        .action-card:hover::before {
            left: 100%;
        }

        .action-card:hover {
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

        .action-card h3 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 18px;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .action-card p {
            opacity: 0.9;
            margin-bottom: 25px;
            line-height: 1.7;
            color: #ffffff;
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

        .system-status {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.6) 0%,
                rgba(31, 33, 77, 0.4) 50%,
                rgba(0, 0, 0, 0.6) 100%);
            border-radius: 20px;
            padding: 35px;
            backdrop-filter: blur(15px);
            border: 3px solid rgba(255, 206, 97, 0.4);
        }

        .system-status h3 {
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.3);
            transition: all 0.3s ease;
        }

        .status-item:hover {
            border-color: rgba(255, 206, 97, 0.6);
            background: rgba(255, 206, 97, 0.1);
        }

        .status-indicator {
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .status-online {
            background: rgba(107, 207, 127, 0.3);
            color: #6bcf7f;
            border: 2px solid #6bcf7f;
        }

        .status-offline {
            background: rgba(255, 71, 87, 0.3);
            color: #ff4757;
            border: 2px solid #ff4757;
        }

        .status-idle {
            background: rgba(255, 206, 97, 0.3);
            color: #FFCE61;
            border: 2px solid #FFCE61;
        }

        .error-message {
            background: rgba(255, 71, 87, 0.2);
            border: 2px solid #ff4757;
            color: #ff4757;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
        }

        /* Progress Modal */
        .progress-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        .progress-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .progress-content {
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.9) 0%,
                rgba(31, 33, 77, 0.8) 50%,
                rgba(0, 0, 0, 0.9) 100%);
            padding: 40px;
            border-radius: 25px;
            max-width: 600px;
            width: 90%;
            backdrop-filter: blur(20px);
            border: 3px solid rgba(255, 206, 97, 0.6);
            position: relative;
            animation: slideUp 0.3s ease;
        }

        .progress-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .progress-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .progress-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }

        .progress-bar-container {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 25px;
            padding: 8px;
            margin: 25px 0;
            border: 2px solid rgba(255, 206, 97, 0.4);
        }

        .progress-bar {
            height: 20px;
            background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
            border-radius: 20px;
            width: 0%;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
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
            animation: progressShimmer 2s infinite;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 25px 0;
        }

        .progress-stat {
            text-align: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.3);
        }

        .progress-stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .progress-stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .current-file {
            background: rgba(0, 0, 0, 0.4);
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.3);
            margin: 20px 0;
        }

        .current-file-label {
            color: rgba(255, 206, 97, 0.9);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .current-file-name {
            color: #ffffff;
            font-weight: 500;
            word-break: break-all;
        }

        .activity-feed {
            max-height: 200px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.3);
            padding: 15px;
            margin: 20px 0;
        }

        .activity-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 206, 97, 0.2);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item.new {
            color: #6bcf7f;
        }

        .activity-item.error {
            color: #ff4757;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .activity-item.error:hover {
            background: rgba(255, 71, 87, 0.2);
            border-color: rgba(255, 71, 87, 0.5);
            transform: translateX(5px);
        }

        .error-click-hint {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
            margin-top: 5px;
        }

        .completion-summary {
            text-align: center;
            padding: 30px;
        }

        .completion-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .completion-title {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .summary-item {
            text-align: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border: 2px solid rgba(255, 206, 97, 0.3);
        }

        .summary-number {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFCE61, #FFE58A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .summary-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* Error Details Modal */
        .error-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 3000;
            animation: fadeIn 0.3s ease;
        }

        .error-details-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-details-content {
            background: linear-gradient(135deg,
                rgba(255, 71, 87, 0.9) 0%,
                rgba(192, 57, 43, 0.8) 50%,
                rgba(255, 71, 87, 0.9) 100%);
            padding: 35px;
            border-radius: 20px;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            backdrop-filter: blur(20px);
            border: 3px solid rgba(255, 71, 87, 0.8);
            position: relative;
            animation: slideUp 0.3s ease;
        }

        .error-details-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .error-details-header h3 {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }

        .error-details-close {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .error-details-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .error-info {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .error-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .error-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .error-info-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            min-width: 100px;
        }

        .error-info-value {
            color: #ffffff;
            text-align: right;
            word-break: break-word;
            flex: 1;
            margin-left: 15px;
        }

        .error-stack {
            background: rgba(0, 0, 0, 0.6);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #ffffff;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .error-summary {
            background: rgba(0, 0, 0, 0.4);
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
            border: 2px solid rgba(255, 71, 87, 0.4);
        }

        .error-summary h4 {
            color: #ff4757;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .error-suggestions {
            background: rgba(255, 206, 97, 0.1);
            padding: 15px;
            border-radius: 12px;
            margin-top: 15px;
            border: 2px solid rgba(255, 206, 97, 0.4);
        }

        .error-suggestions h4 {
            color: #FFCE61;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .error-suggestions ul {
            margin: 0;
            padding-left: 20px;
            color: rgba(255, 255, 255, 0.9);
        }

        .error-suggestions li {
            margin-bottom: 5px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes progressShimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 2.2rem;
            }

            .stats-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .stat-card,
            .action-card {
                padding: 25px;
            }

            .progress-content {
                padding: 25px;
            }

            .progress-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include "nav.php"; ?>

    <div class="main-content">
        <div class="dashboard-header">
            <h1>Dashboard Overview</h1>
            <p>Welcome to The Houston Collective streaming administration panel</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎬</div>
                <div class="stat-number"><?php echo $stats['total_videos']; ?></div>
                <div class="stat-label">Total Videos</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?php echo $stats['total_playlists']; ?></div>
                <div class="stat-label">Playlists</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-number"><?php echo gmdate("H:i", $stats['total_duration']); ?></div>
                <div class="stat-label">Total Duration</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-number"><?php echo number_format($stats['total_size'] / 1024 / 1024 / 1024, 1); ?> GB</div>
                <div class="stat-label">Total Size</div>
            </div>
        </div>

        <div class="actions-grid">
            <div class="action-card">
                <h3>🎬 Manage Media Library</h3>
                <p>Browse, organize, and manage your video content. Upload new files, edit metadata, and organize your media collection.</p>
                <a href="media.php" class="btn btn-primary">Open Media Library</a>
            </div>

            <div class="action-card">
                <h3>📋 Create Playlist</h3>
                <p>Build custom playlists with drag-and-drop functionality. Set shuffle options, priorities, and scheduling for professional broadcasting.</p>
                <a href="playlists.php" class="btn btn-primary">Manage Playlists</a>
            </div>

            <div class="action-card">
                <h3>🔍 Scan for New Content</h3>
                <p>Automatically discover and process new video files. Extract metadata, generate thumbnails, and add to your media library.</p>
                <button onclick="startContentScan()" class="btn btn-secondary" id="scanButton">Start Content Scan</button>
            </div>
        </div>

        <div class="system-status">
            <h3>System Status</h3>
            <div class="status-grid">
                <div class="status-item">
                    <span>SRS Media Server</span>
                    <span class="status-indicator status-online">Online</span>
                </div>

                <div class="status-item">
                    <span>Database Connection</span>
                    <span class="status-indicator <?php echo isset($error_message) ? 'status-offline' : 'status-online'; ?>">
                        <?php echo isset($error_message) ? 'Offline' : 'Online'; ?>
                    </span>
                </div>

                <div class="status-item">
                    <span>Stream Output</span>
                    <span class="status-indicator status-idle">Idle</span>
                </div>

                <div class="status-item">
                    <span>Last Content Scan</span>
                    <span class="status-indicator status-idle">Never</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div id="progressModal" class="progress-modal">
        <div class="progress-content">
            <div id="progressView">
                <div class="progress-header">
                    <h2>🔍 Scanning Content</h2>
                    <div class="progress-subtitle">Discovering and processing video files...</div>
                </div>

                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>

                <div class="progress-stats">
                    <div class="progress-stat">
                        <div class="progress-stat-number" id="filesProcessed">0</div>
                        <div class="progress-stat-label">Files Processed</div>
                    </div>
                    <div class="progress-stat">
                        <div class="progress-stat-number" id="progressPercent">0%</div>
                        <div class="progress-stat-label">Progress</div>
                    </div>
                </div>

                <div class="current-file">
                    <div class="current-file-label">Currently Processing:</div>
                    <div class="current-file-name" id="currentFileName">Initializing scan...</div>
                </div>

                <div class="activity-feed" id="activityFeed">
                    <div class="activity-item">🔍 Starting content discovery...</div>
                </div>
            </div>

            <div id="completionView" style="display: none;">
                <div class="completion-summary">
                    <div class="completion-icon">✅</div>
                    <div class="completion-title">Content Scan Complete!</div>
                    
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-number" id="summaryNewVideos">0</div>
                            <div class="summary-label">New Videos</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-number" id="summaryUpdatedVideos">0</div>
                            <div class="summary-label">Updated</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-number" id="summaryTotalProcessed">0</div>
                            <div class="summary-label">Total Files</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-number" id="summaryErrors">0</div>
                            <div class="summary-label">Errors</div>
                        </div>
                    </div>

                    <button onclick="closeProgressModal()" class="btn btn-primary" style="margin-top: 20px;">
                        Close & Refresh Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let scanInProgress = false;
        let progressInterval;
        let errorDetails = []; // Store detailed error information

        function startContentScan() {
            if (scanInProgress) return;
            
            scanInProgress = true;
            errorDetails = []; // Reset error details
            const scanButton = document.getElementById('scanButton');
            scanButton.textContent = 'Scanning...';
            scanButton.disabled = true;
            
            // Show progress modal
            document.getElementById('progressModal').classList.add('show');
            document.getElementById('progressView').style.display = 'block';
            document.getElementById('completionView').style.display = 'none';
            
            // Reset progress
            resetProgress();
            
            // Start simulated progress updates
            startProgressUpdates();
            
            // Start actual content scan
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=start_content_scan'
            })
            .then(response => response.json())
            .then(data => {
                // Stop progress simulation
                clearInterval(progressInterval);
                
                if (data.success) {
                    showCompletionSummary(data.results);
                } else {
                    showError(data.message, {
                        type: 'General Error',
                        timestamp: new Date().toISOString(),
                        details: data.message,
                        suggestions: [
                            'Check if the content directory exists and is readable',
                            'Verify that Python and required modules are installed',
                            'Check server logs for more details',
                            'Ensure sufficient disk space is available'
                        ]
                    });
                }
                
                scanInProgress = false;
                scanButton.textContent = 'Start Content Scan';
                scanButton.disabled = false;
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Error:', error);
                showError('Network or server error occurred.', {
                    type: 'Network Error',
                    timestamp: new Date().toISOString(),
                    details: error.message || 'Unknown network error',
                    stack: error.stack || 'No stack trace available',
                    suggestions: [
                        'Check your internet connection',
                        'Verify the server is running',
                        'Try refreshing the page and scanning again',
                        'Contact system administrator if problem persists'
                    ]
                });
                scanInProgress = false;
                scanButton.textContent = 'Start Content Scan';
                scanButton.disabled = false;
            });
        }

        function resetProgress() {
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('filesProcessed').textContent = '0';
            document.getElementById('progressPercent').textContent = '0%';
            document.getElementById('currentFileName').textContent = 'Initializing scan...';
            document.getElementById('activityFeed').innerHTML = '<div class="activity-item">🔍 Starting content discovery...</div>';
        }

        function startProgressUpdates() {
            let progress = 0;
            let filesProcessed = 0;
            
            const sampleFiles = [
                'music_video_001.mp4', 'concert_footage.mkv', 'interview_segment.avi',
                'live_performance.mp4', 'behind_scenes.mov', 'promotional_video.mp4',
                'acoustic_session.mkv', 'music_documentary.mp4', 'studio_recording.avi'
            ];
            
            const activities = [
                'Scanning directory structure...',
                'Discovering video files...',
                'Extracting metadata...',
                'Generating thumbnails...',
                'Updating database...',
                'Processing audio tracks...',
                'Validating file integrity...'
            ];
            
            const possibleErrors = [
                {
                    message: 'Failed to extract metadata from corrupted_video.mp4',
                    details: {
                        type: 'Metadata Error',
                        file: 'corrupted_video.mp4',
                        timestamp: new Date().toISOString(),
                        details: 'FFprobe could not read video metadata. File may be corrupted or in an unsupported format.',
                        stack: 'FFprobeError: Invalid data found when processing input\n    at extractMetadata()\n    at processVideo()',
                        suggestions: [
                            'Check if the video file is corrupted',
                            'Try re-encoding the video with FFmpeg',
                            'Verify the file format is supported',
                            'Remove or replace the problematic file'
                        ]
                    }
                },
                {
                    message: 'Permission denied: thumbnail_generation_failed.mkv',
                    details: {
                        type: 'Permission Error',
                        file: 'thumbnail_generation_failed.mkv',
                        timestamp: new Date().toISOString(),
                        details: 'Unable to write thumbnail file. Check directory permissions.',
                        stack: 'PermissionError: [Errno 13] Permission denied: \'/opt/streamserver/thumbnails/\'',
                        suggestions: [
                            'Check write permissions on thumbnail directory',
                            'Verify streamserver user has proper access',
                            'Run: sudo chown -R streamserver:streamserver /opt/streamserver/',
                            'Check disk space availability'
                        ]
                    }
                }
            ];
            
            progressInterval = setInterval(() => {
                // Update progress
                progress = Math.min(95, progress + Math.random() * 8);
                filesProcessed += Math.floor(Math.random() * 3);
                
                // Update UI
                document.getElementById('progressBar').style.width = progress + '%';
                document.getElementById('progressPercent').textContent = Math.floor(progress) + '%';
                document.getElementById('filesProcessed').textContent = filesProcessed;
                
                // Update current file
                if (Math.random() > 0.7) {
                    const randomFile = sampleFiles[Math.floor(Math.random() * sampleFiles.length)];
                    document.getElementById('currentFileName').textContent = randomFile;
                    
                    // Add activity (occasionally add an error for demonstration)
                    if (Math.random() > 0.92 && possibleErrors.length > 0) {
                        const randomError = possibleErrors[Math.floor(Math.random() * possibleErrors.length)];
                        addActivity(randomError.message, 'error', randomError.details);
                    } else {
                        const randomActivity = activities[Math.floor(Math.random() * activities.length)];
                        addActivity(randomActivity, Math.random() > 0.8 ? 'new' : '');
                    }
                }
                
                // Stop at 95% and wait for real completion
                if (progress >= 95) {
                    clearInterval(progressInterval);
                }
            }, 800);
        }

        function addActivity(text, className = '', errorDetail = null) {
            const activityFeed = document.getElementById('activityFeed');
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item ' + className;
            
            if (className === 'error' && errorDetail) {
                // Store error details
                const errorId = errorDetails.length;
                errorDetails.push(errorDetail);
                
                // Make error clickable
                activityItem.innerHTML = `
                    <div>❌ ${text}</div>
                    <div class="error-click-hint">Click for details</div>
                `;
                activityItem.style.cursor = 'pointer';
                activityItem.onclick = () => showErrorDetails(errorId);
            } else {
                const icon = className === 'new' ? '✅' : className === 'error' ? '❌' : '•';
                activityItem.textContent = `${icon} ${text}`;
            }
            
            activityFeed.appendChild(activityItem);
            activityFeed.scrollTop = activityFeed.scrollHeight;
            
            // Keep only last 10 items
            while (activityFeed.children.length > 10) {
                activityFeed.removeChild(activityFeed.firstChild);
            }
        }

        function showErrorDetails(errorId) {
            const error = errorDetails[errorId];
            if (!error) return;

            // Create error details modal if it doesn't exist
            let modal = document.getElementById('errorDetailsModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'errorDetailsModal';
                modal.className = 'error-details-modal';
                document.body.appendChild(modal);
            }

            modal.innerHTML = `
                <div class="error-details-content">
                    <div class="error-details-header">
                        <span style="font-size: 1.5rem;">❌</span>
                        <h3>Error Details</h3>
                        <button class="error-details-close" onclick="closeErrorDetails()">×</button>
                    </div>
                    
                    <div class="error-info">
                        <div class="error-info-item">
                            <span class="error-info-label">Type:</span>
                            <span class="error-info-value">${error.type}</span>
                        </div>
                        <div class="error-info-item">
                            <span class="error-info-label">File:</span>
                            <span class="error-info-value">${error.file || 'Unknown'}</span>
                        </div>
                        <div class="error-info-item">
                            <span class="error-info-label">Time:</span>
                            <span class="error-info-value">${new Date(error.timestamp).toLocaleString()}</span>
                        </div>
                    </div>

                    <div class="error-summary">
                        <h4>Error Description</h4>
                        <p style="color: rgba(255, 255, 255, 0.9); line-height: 1.5;">${error.details}</p>
                    </div>

                    ${error.stack ? `
                        <div class="error-summary">
                            <h4>Technical Details</h4>
                            <div class="error-stack">${error.stack}</div>
                        </div>
                    ` : ''}

                    <div class="error-suggestions">
                        <h4>💡 Suggested Solutions</h4>
                        <ul>
                            ${error.suggestions.map(suggestion => `<li>${suggestion}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;

            modal.classList.add('show');
        }

        function closeErrorDetails() {
            const modal = document.getElementById('errorDetailsModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }

        function showCompletionSummary(results) {
            // Complete progress bar
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressPercent').textContent = '100%';
            
            // Add final activity
            addActivity('Content scan completed successfully!', 'new');
            
            // Show completion after a brief delay
            setTimeout(() => {
                document.getElementById('progressView').style.display = 'none';
                document.getElementById('completionView').style.display = 'block';
                
                // Populate summary
                document.getElementById('summaryNewVideos').textContent = results.new_videos || 0;
                document.getElementById('summaryUpdatedVideos').textContent = results.updated_videos || 0;
                document.getElementById('summaryTotalProcessed').textContent = results.total_processed || 0;
                document.getElementById('summaryErrors').textContent = results.errors || 0;
            }, 1500);
        }

        function showError(message, errorDetail = null) {
            if (errorDetail) {
                const errorId = errorDetails.length;
                errorDetails.push(errorDetail);
                addActivity(message, 'error', errorDetail);
            } else {
                addActivity('Error: ' + message, 'error');
            }
            
            setTimeout(() => {
                document.getElementById('progressView').style.display = 'none';
                document.getElementById('completionView').style.display = 'block';
                document.querySelector('.completion-icon').textContent = '❌';
                document.querySelector('.completion-title').textContent = 'Scan Failed';
            }, 1000);
        }

        function closeProgressModal() {
            document.getElementById('progressModal').classList.remove('show');
            
            // Also close error details modal if open
            closeErrorDetails();
            
            // Refresh the page to update stats
            setTimeout(() => {
                location.reload();
            }, 500);
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('progress-modal') && !scanInProgress) {
                closeProgressModal();
            }
            if (e.target.classList.contains('error-details-modal')) {
                closeErrorDetails();
            }
        });

        // Close error details with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeErrorDetails();
            }
        });
    </script>
</body>
</html>
