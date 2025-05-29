<?php
// Get current page for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="nav-links">
    <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="media.php" class="nav-link <?= $current_page == 'media.php' ? 'active' : '' ?>">🎬 Media Library</a>
    <a href="playlists.php" class="nav-link <?= $current_page == 'playlists.php' ? 'active' : '' ?>">📋 Playlists</a>
    <a href="playlist_editor.php" class="nav-link <?= $current_page == 'playlist_editor.php' ? 'active' : '' ?>">✏️ Playlist Editor</a>
    <a href="schedule.php" class="nav-link <?= $current_page == 'schedule.php' ? 'active' : '' ?>">📅 Schedule</a>
    <a href="smart_dashboard.php" class="nav-link <?= $current_page == 'smart_dashboard.php' ? 'active' : '' ?>">🧠 Smart Dashboard</a>
    <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">👥 Users</a>
    <a href="stream_control.php" class="nav-link <?= $current_page == 'stream_control.php' ? 'active' : '' ?>">🎛️ Stream Control</a>
    <a href="player.html" class="nav-link <?= $current_page == 'player.html' ? 'active' : '' ?>">📺 Live Player</a>
</div>
<style>
.nav-links {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    justify-content: center;
    flex-wrap: wrap;
}
.nav-link {
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    text-decoration: none;
    padding: 12px 24px;
    border-radius: 12px;
    border: 3px solid rgba(255, 206, 97, 0.6);
    transition: all 0.4s ease;
    font-weight: 600;
}
.nav-link:hover,
.nav-link.active {
    background: linear-gradient(135deg, #DF4035, #FD5E53, #FF6D62, #EE4F44);
    border-color: rgba(255, 229, 138, 0.8);
    transform: translateY(-3px);
    box-shadow:
        0 12px 30px rgba(253, 94, 83, 0.5),
        0 0 20px rgba(255, 206, 97, 0.4);
}
@media (max-width: 768px) {
    .nav-links {
        flex-direction: column;
        align-items: center;
    }
    .nav-link {
        width: 100%;
        max-width: 300px;
        text-align: center;
    }
}
</style>
