<?php
require_once '../../scripts/database.php';

try {
    $db = new Database();
    $playlists = $db->getAllPlaylists();
    echo "Database connection: SUCCESS\n";
    echo "Found " . count($playlists) . " playlists\n";
    
    if (!empty($playlists)) {
        echo "First playlist: " . $playlists[0]['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Database connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
?>
