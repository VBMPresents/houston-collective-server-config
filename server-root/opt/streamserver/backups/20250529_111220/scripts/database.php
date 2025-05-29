<?php
/**
 * SRS Streaming Server - Database Helper Class
 * Complete database operations for admin interface
 */

class Database {
    private $db;
    private $dbPath;
    public $pdo; // For compatibility with media.php
    
    public function __construct() {
        $this->dbPath = dirname(__FILE__) . '/../database/streaming.db';
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->exec('PRAGMA journal_mode=WAL');
            $this->db->exec('PRAGMA foreign_keys=ON');
            $this->pdo = $this->db; // For compatibility
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    // Dashboard stats method
    public function getStats() {
        $videoCount = $this->db->query("SELECT COUNT(*) FROM videos WHERE is_active = 1")->fetchColumn();
        $playlistCount = $this->db->query("SELECT COUNT(*) FROM playlists WHERE is_active = 1")->fetchColumn();
        $totalDuration = $this->db->query("SELECT COALESCE(SUM(duration), 0) FROM videos WHERE is_active = 1")->fetchColumn();
        $totalSize = $this->db->query("SELECT COALESCE(SUM(file_size), 0) FROM videos WHERE is_active = 1")->fetchColumn();
        
        return [
            'total_videos' => $videoCount,
            'total_playlists' => $playlistCount,
            'total_duration' => $totalDuration,
            'total_size' => $totalSize
        ];
    }
    
    // Video management methods for media.php
    public function getVideos($page = 1, $limit = 20, $search = '', $format_filter = '') {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM videos WHERE is_active = 1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (display_name LIKE ? OR filename LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($format_filter)) {
            $sql .= " AND format = ?";
            $params[] = $format_filter;
        }
        
        $sql .= " ORDER BY display_name ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM videos WHERE is_active = 1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (display_name LIKE ? OR filename LIKE ?)";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
        }
        
        if (!empty($format_filter)) {
            $countSql .= " AND format = ?";
            $countParams[] = $format_filter;
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetchColumn();
        
        return [
            'videos' => $videos,
            'total' => $totalCount,
            'pages' => ceil($totalCount / $limit),
            'current_page' => $page
        ];
    }
    
    public function updateVideo($id, $data) {
        $sql = "UPDATE videos SET ";
        $params = [];
        $updates = [];
        
        if (isset($data['display_name'])) {
            $updates[] = "display_name = ?";
            $params[] = $data['display_name'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        $sql .= implode(", ", $updates) . ", date_modified = datetime('now') WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteVideo($id) {
        $this->db->beginTransaction();
        try {
            // Remove from playlists first
            $stmt = $this->db->prepare("DELETE FROM video_playlists WHERE video_id = ?");
            $stmt->execute([$id]);
            
            // Delete video record
            $stmt = $this->db->prepare("DELETE FROM videos WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    // Basic video operations
    public function getAllVideos() {
        $stmt = $this->db->query("SELECT * FROM videos WHERE is_active = 1 ORDER BY display_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getVideoById($id) {
        $stmt = $this->db->prepare("SELECT * FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Playlist management methods
    public function getAllPlaylists() {
        $stmt = $this->db->query("SELECT * FROM playlists ORDER BY priority DESC, name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPlaylistById($id) {
        $stmt = $this->db->prepare("SELECT * FROM playlists WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPlaylistByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM playlists WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createPlaylist($name, $description, $type, $shuffle, $loop, $priority) {
        $stmt = $this->db->prepare("
            INSERT INTO playlists (name, description, playlist_type, shuffle_enabled, loop_enabled, priority, is_active, date_created, date_modified) 
            VALUES (?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
        ");
        return $stmt->execute([$name, $description, $type, $shuffle, $loop, $priority]);
    }
    
    public function deletePlaylist($id) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM video_playlists WHERE playlist_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->db->prepare("DELETE FROM playlists WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function togglePlaylist($id, $active) {
        $stmt = $this->db->prepare("UPDATE playlists SET is_active = ?, date_modified = datetime('now') WHERE id = ?");
        return $stmt->execute([$active, $id]);
    }
    
    public function getPlaylistStats() {
        $stmt = $this->db->query("
            SELECT 
                p.id as playlist_id,
                COUNT(vp.video_id) as video_count,
                COALESCE(SUM(v.duration), 0) as total_duration,
                COALESCE(SUM(v.file_size), 0) as total_size
            FROM playlists p
            LEFT JOIN video_playlists vp ON p.id = vp.playlist_id
            LEFT JOIN videos v ON vp.video_id = v.id
            GROUP BY p.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPlaylistVideos($playlistId) {
        $stmt = $this->db->prepare("
            SELECT v.*, vp.sort_order, vp.date_added
            FROM videos v
            JOIN video_playlists vp ON v.id = vp.video_id
            WHERE vp.playlist_id = ?
            ORDER BY vp.sort_order ASC
        ");
        $stmt->execute([$playlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addVideoToPlaylist($playlistId, $videoId) {
        $stmt = $this->db->prepare("SELECT id FROM video_playlists WHERE playlist_id = ? AND video_id = ?");
        $stmt->execute([$playlistId, $videoId]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM video_playlists WHERE playlist_id = ?");
        $stmt->execute([$playlistId]);
        $nextOrder = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("
            INSERT INTO video_playlists (playlist_id, video_id, sort_order, date_added) 
            VALUES (?, ?, ?, datetime('now'))
        ");
        return $stmt->execute([$playlistId, $videoId, $nextOrder]);
    }
    
    public function removeVideoFromPlaylist($playlistId, $videoId) {
        $stmt = $this->db->prepare("DELETE FROM video_playlists WHERE playlist_id = ? AND video_id = ?");
        return $stmt->execute([$playlistId, $videoId]);
    }
    
    public function reorderPlaylistVideos($playlistId, $videoIds) {
        $this->db->beginTransaction();
        try {
            foreach ($videoIds as $index => $videoId) {
                $stmt = $this->db->prepare("
                    UPDATE video_playlists 
                    SET sort_order = ? 
                    WHERE playlist_id = ? AND video_id = ?
                ");
                $stmt->execute([$index + 1, $playlistId, $videoId]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function searchVideos($search) {
        if (empty($search)) {
            return $this->getAllVideos();
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM videos 
            WHERE display_name LIKE ? OR filename LIKE ? 
            ORDER BY display_name ASC
            LIMIT 100
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
