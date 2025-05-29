<?php
require_once '/opt/streamserver/scripts/database.php';
$db = new Database();

echo "<h1>Video Debug</h1>";
echo "<p>Testing getVideos(1, 50):</p>";
$videos = $db->getVideos(1, 50);
echo "<p>Got " . count($videos) . " videos</p>";

echo "<p>Testing getVideos(1, 200):</p>";
$videos2 = $db->getVideos(1, 200);
echo "<p>Got " . count($videos2) . " videos</p>";

echo "<p>Direct database query:</p>";
$pdo = new PDO('sqlite:/opt/streamserver/database/streaming.db');
$stmt = $pdo->query("SELECT COUNT(*) as count FROM videos WHERE is_active = 1");
$result = $stmt->fetch();
echo "<p>Direct count: " . $result['count'] . "</p>";
?>
