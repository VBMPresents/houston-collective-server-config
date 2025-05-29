#!/usr/bin/env python3
"""
Smooth Smart Scheduler for The Houston Collective Streaming Server
Enhanced with gapless video transitions using FFmpeg concat
"""

import sqlite3
import subprocess
import time
import logging
import signal
import sys
import os
import random
import json
import threading
from datetime import datetime, timedelta
from pathlib import Path

class SmoothSmartScheduler:
    def __init__(self):
        # Core settings
        self.base_dir = Path("/opt/streamserver")
        self.rtmp_url = 'rtmp://127.0.0.1:1935/live/test'
        self.db_path = self.base_dir / "database" / "streaming.db"
        self.concat_file = self.base_dir / "temp" / "playlist.txt"
        self.logs_dir = self.base_dir / "logs"
        
        # Ensure temp directory exists
        (self.base_dir / "temp").mkdir(exist_ok=True)
        
        # Streaming state
        self.current_process = None
        self.current_playlist_id = None
        self.playlist_videos = []
        self.current_video_index = 0
        self.is_running = False
        self.concat_queue = []  # Queue of videos for concat file
        self.queue_size = 5  # Number of videos to queue ahead
        
        # Setup logging
        self.setup_logging()
        
        # Analytics
        self.analytics = {
            'streams_started': 0,
            'schedule_switches': 0,
            'emergency_overrides': 0,
            'errors': 0,
            'uptime_start': datetime.now().isoformat()
        }
        
        # Signal handlers
        signal.signal(signal.SIGTERM, self.signal_handler)
        signal.signal(signal.SIGINT, self.signal_handler)

    def setup_logging(self):
        """Enhanced logging setup"""
        self.logs_dir.mkdir(exist_ok=True)
        
        # Main logger
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(self.logs_dir / 'smooth_scheduler.log'),
                logging.StreamHandler()
            ]
        )
        self.logger = logging.getLogger('SmoothScheduler')
        
        # Analytics logger
        analytics_handler = logging.FileHandler(self.logs_dir / 'smooth_analytics.log')
        analytics_handler.setFormatter(logging.Formatter('%(asctime)s - %(message)s'))
        self.analytics_logger = logging.getLogger('Analytics')
        self.analytics_logger.addHandler(analytics_handler)
        self.analytics_logger.setLevel(logging.INFO)

    def get_db_connection(self):
        """Get database connection with error handling"""
        try:
            conn = sqlite3.connect(str(self.db_path))
            conn.row_factory = sqlite3.Row
            return conn
        except Exception as e:
            self.logger.error(f"❌ Database connection failed: {e}")
            return None

    def get_playlist_videos(self, playlist_id, shuffle=False):
        """Get videos from playlist with smart ordering"""
        conn = self.get_db_connection()
        if not conn:
            return []

        try:
            cursor = conn.cursor()
            cursor.execute("""
                SELECT v.id, v.filename, v.file_path, v.display_name, v.duration,
                       v.resolution, v.file_size, vp.sort_order
                FROM videos v
                JOIN video_playlists vp ON v.id = vp.video_id
                WHERE vp.playlist_id = ? AND v.is_active = 1
                ORDER BY vp.sort_order, v.display_name
            """, (playlist_id,))

            videos = cursor.fetchall()
            conn.close()

            video_list = [dict(video) for video in videos]

            if shuffle and len(video_list) > 1:
                random.shuffle(video_list)
                self.logger.info(f"🎲 Shuffled {len(video_list)} videos in playlist {playlist_id}")

            return video_list

        except Exception as e:
            self.logger.error(f"❌ Error getting playlist videos: {e}")
            self.analytics['errors'] += 1
            if conn:
                conn.close()
            return []

    def create_concat_file(self):
        """Create FFmpeg concat file with queued videos"""
        try:
            with open(self.concat_file, 'w') as f:
                for video_path in self.concat_queue:
                    # Escape single quotes and backslashes for FFmpeg
                    safe_path = str(video_path).replace("'", "'\\''")
                    f.write(f"file '{safe_path}'\n")
            
            self.logger.info(f"📝 Created concat file with {len(self.concat_queue)} videos")
            return True
            
        except Exception as e:
            self.logger.error(f"❌ Error creating concat file: {e}")
            return False

    def update_video_queue(self, playlist_id, shuffle=False, loop=True):
        """Update the video queue for smooth playback"""
        # Reload playlist if changed
        if playlist_id != self.current_playlist_id:
            self.playlist_videos = self.get_playlist_videos(playlist_id, shuffle)
            self.current_playlist_id = playlist_id
            self.current_video_index = 0
            self.logger.info(f"📋 Loaded playlist {playlist_id} with {len(self.playlist_videos)} videos")

        if not self.playlist_videos:
            self.logger.warning(f"⚠️ No videos found in playlist {playlist_id}")
            return False

        # Clear and rebuild queue
        self.concat_queue = []
        
        # Add next videos to queue
        for i in range(self.queue_size):
            video_index = (self.current_video_index + i) % len(self.playlist_videos)
            video = self.playlist_videos[video_index]
            video_path = Path(video['file_path'])
            
            if video_path.exists():
                self.concat_queue.append(video_path)
            else:
                self.logger.warning(f"⚠️ Video file not found: {video_path}")

        # Advance index for next update
        self.current_video_index = (self.current_video_index + 1) % len(self.playlist_videos)
        
        return len(self.concat_queue) > 0

    def start_smooth_stream(self, playlist_id):
        """Start smooth concatenated streaming"""
        if not self.update_video_queue(playlist_id):
            self.logger.error("❌ No videos available for streaming")
            return False

        if not self.create_concat_file():
            self.logger.error("❌ Failed to create concat file")
            return False

        # Enhanced FFmpeg command for smooth concat streaming
        ffmpeg_cmd = [
            'ffmpeg',
            '-f', 'concat',
            '-safe', '0',
            '-i', str(self.concat_file),
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-tune', 'zerolatency',
            '-crf', '23',
            '-maxrate', '2000k',
            '-bufsize', '4000k',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-f', 'flv',
            '-y',
            self.rtmp_url
        ]

        try:
            self.logger.info("🎬 Starting smooth concatenated stream")
            self.current_process = subprocess.Popen(
                ffmpeg_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                preexec_fn=os.setsid
            )

            self.analytics['streams_started'] += 1
            self.analytics_logger.info(f"Smooth stream started with {len(self.concat_queue)} videos")
            return True

        except Exception as e:
            self.logger.error(f"❌ Failed to start smooth stream: {e}")
            self.analytics['errors'] += 1
            return False

    def monitor_and_update_stream(self, playlist_id):
        """Monitor FFmpeg and update concat file as needed"""
        if not self.current_process:
            return

        # Check if process is still running
        if self.current_process.poll() is not None:
            self.logger.warning("📺 Stream process ended, restarting...")
            self.start_smooth_stream(playlist_id)
            return

        # Periodically update the queue (every few minutes)
        # This ensures new videos are added to the concat file
        if random.random() < 0.1:  # 10% chance each cycle to refresh
            self.logger.info("🔄 Refreshing video queue for variety")
            self.update_video_queue(playlist_id, shuffle=True)
            self.create_concat_file()

    def stop_current_stream(self):
        """Stop current stream gracefully"""
        if self.current_process:
            try:
                self.logger.info("⏹️ Stopping smooth stream...")
                
                # Send SIGTERM for graceful shutdown
                self.current_process.terminate()

                # Wait for graceful termination
                try:
                    self.current_process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    self.logger.warning("🔥 Force killing stream process...")
                    self.current_process.kill()
                    self.current_process.wait()

                self.current_process = None
                self.logger.info("✅ Smooth stream stopped")

            except Exception as e:
                self.logger.error(f"❌ Error stopping stream: {e}")
                self.analytics['errors'] += 1

    def get_intelligent_fallback(self):
        """Smart fallback playlist selection based on time of day"""
        conn = self.get_db_connection()
        if not conn:
            return 1  # Default fallback

        try:
            current_hour = datetime.now().hour
            
            # Time-based intelligent selection
            if 6 <= current_hour < 12:
                time_context = "morning"
                priority_preference = "medium"
            elif 12 <= current_hour < 18:
                time_context = "afternoon"  
                priority_preference = "high"
            elif 18 <= current_hour < 23:
                time_context = "evening"
                priority_preference = "medium"
            else:
                time_context = "late night"
                priority_preference = "low"

            # Get best playlist for time period
            cursor = conn.cursor()
            cursor.execute("""
                SELECT id, name, priority FROM playlists 
                WHERE is_active = 1 
                ORDER BY 
                    CASE 
                        WHEN ? = 'high' AND priority >= 8 THEN 1
                        WHEN ? = 'medium' AND priority BETWEEN 4 AND 7 THEN 1
                        WHEN ? = 'low' AND priority <= 4 THEN 1
                        ELSE 2
                    END,
                    priority DESC,
                    RANDOM()
                LIMIT 1
            """, (priority_preference, priority_preference, priority_preference))
            
            result = cursor.fetchone()
            conn.close()

            if result:
                playlist_id, name, priority = result
                self.logger.info(f"🧠 Smart fallback for {time_context}: '{name}' (Priority: {priority})")
                return playlist_id
            else:
                self.logger.warning("⚠️ No active playlists found, using default")
                return 1

        except Exception as e:
            self.logger.error(f"❌ Error getting intelligent fallback: {e}")
            self.analytics['errors'] += 1
            if conn:
                conn.close()
            return 1

    def run_smooth_cycle(self):
        """Main smooth streaming cycle"""
        # Get target playlist (could add schedule logic here)
        target_playlist_id = self.get_intelligent_fallback()
        
        # Start stream if not running
        if not self.current_process:
            self.start_smooth_stream(target_playlist_id)
        else:
            # Monitor and update existing stream
            self.monitor_and_update_stream(target_playlist_id)

    def run(self):
        """Main smooth scheduler loop"""
        self.logger.info("🎬 Starting Smooth Smart Scheduler...")
        self.is_running = True
        cycle_count = 0

        while self.is_running:
            try:
                cycle_count += 1
                self.run_smooth_cycle()

                # Save analytics periodically
                if cycle_count % 60 == 0:
                    self.save_analytics()

                # Sleep between cycles
                time.sleep(60)  # Check every minute

            except Exception as e:
                self.logger.error(f"❌ Scheduler cycle error: {e}")
                self.analytics['errors'] += 1
                time.sleep(30)  # Wait before retrying

    def save_analytics(self):
        """Save analytics to JSON file"""
        try:
            analytics_file = self.logs_dir / 'smooth_analytics_summary.json'
            self.analytics['last_updated'] = datetime.now().isoformat()
            
            with open(analytics_file, 'w') as f:
                json.dump(self.analytics, f, indent=2)
                
        except Exception as e:
            self.logger.error(f"❌ Error saving analytics: {e}")

    def signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        self.logger.info(f"🛑 Received signal {signum}, shutting down smoothly...")
        self.is_running = False
        self.stop_current_stream()
        self.save_analytics()
        sys.exit(0)

if __name__ == "__main__":
    scheduler = SmoothSmartScheduler()
    scheduler.run()
