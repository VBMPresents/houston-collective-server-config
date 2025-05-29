#!/usr/bin/env python3
"""
Basic Scheduler for The Houston Collective Streaming Server
Monitors schedule table and automatically streams appropriate playlists
"""

import sqlite3
import subprocess
import time
import logging
import signal
import sys
import os
import random
from datetime import datetime, timedelta
from pathlib import Path

class BasicScheduler:
    def __init__(self):
        # Configuration
        self.db_path = '/opt/streamserver/database/streaming.db'
        self.content_dir = '/opt/streamserver/content'
        self.log_dir = '/opt/streamserver/logs'
        self.rtmp_url = 'rtmp://127.0.0.1:1935/live/test'
        
        # Current streaming process
        self.current_process = None
        self.current_playlist_id = None
        self.current_video_index = 0
        self.playlist_videos = []
        self.is_running = False
        
        # Setup logging
        self.setup_logging()
        
        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
        self.logger.info("🎬 Basic Scheduler initialized")
    
    def setup_logging(self):
        """Setup logging configuration"""
        os.makedirs(self.log_dir, exist_ok=True)
        
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(f'{self.log_dir}/scheduler.log'),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)
    
    def signal_handler(self, signum, frame):
        """Handle shutdown signals gracefully"""
        self.logger.info(f"📡 Received signal {signum}, shutting down gracefully...")
        self.is_running = False
        self.stop_current_stream()
        sys.exit(0)
    
    def get_db_connection(self):
        """Get database connection"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.row_factory = sqlite3.Row
            return conn
        except Exception as e:
            self.logger.error(f"❌ Database connection failed: {e}")
            return None
    
    def get_current_schedule(self):
        """Get active schedule for current day and time"""
        now = datetime.now()
        current_day = now.weekday()  # 0=Monday, 6=Sunday
        # Convert to our format (0=Sunday, 1=Monday, etc.)
        current_day = 0 if current_day == 6 else current_day + 1
        current_time = now.strftime('%H:%M')
        
        conn = self.get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor()
            cursor.execute("""
                SELECT s.*, p.name as playlist_name, p.priority
                FROM schedule s
                JOIN playlists p ON s.playlist_id = p.id
                WHERE s.is_active = 1 
                AND s.day_of_week = ?
                AND s.start_time <= ?
                AND s.end_time > ?
                ORDER BY p.priority DESC, s.start_time
                LIMIT 1
            """, (current_day, current_time, current_time))
            
            result = cursor.fetchone()
            conn.close()
            
            if result:
                self.logger.info(f"📅 Found active schedule: {result['playlist_name']} ({result['start_time']}-{result['end_time']})")
            
            return result
            
        except Exception as e:
            self.logger.error(f"❌ Error checking schedule: {e}")
            conn.close()
            return None
    
    def get_fallback_playlist(self):
        """Get highest priority playlist as fallback"""
        conn = self.get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor()
            cursor.execute("""
                SELECT id, name, priority
                FROM playlists
                WHERE is_active = 1
                ORDER BY 
                    CASE priority 
                        WHEN 'high' THEN 3
                        WHEN 'medium' THEN 2
                        WHEN 'low' THEN 1
                        ELSE 0
                    END DESC,
                    name
                LIMIT 1
            """)
            
            result = cursor.fetchone()
            conn.close()
            
            if result:
                self.logger.info(f"🔄 Using fallback playlist: {result['name']} (Priority: {result['priority']})")
            
            return result
            
        except Exception as e:
            self.logger.error(f"❌ Error getting fallback playlist: {e}")
            conn.close()
            return None
    
    def get_playlist_videos(self, playlist_id):
        """Get all videos in a playlist"""
        conn = self.get_db_connection()
        if not conn:
            return []
        
        try:
            cursor = conn.cursor()
            cursor.execute("""
                SELECT v.id, v.filename, v.file_path, v.display_name, v.duration
                FROM videos v
                JOIN video_playlists vp ON v.id = vp.video_id
                WHERE vp.playlist_id = ? AND v.is_active = 1
                ORDER BY vp.sort_order, v.display_name
            """, (playlist_id,))
            
            videos = cursor.fetchall()
            conn.close()
            
            return [dict(video) for video in videos]
            
        except Exception as e:
            self.logger.error(f"❌ Error getting playlist videos: {e}")
            conn.close()
            return []
    
    def get_next_video(self, playlist_id, shuffle=False):
        """Get next video from playlist"""
        # If playlist changed, reload videos
        if playlist_id != self.current_playlist_id:
            self.playlist_videos = self.get_playlist_videos(playlist_id)
            self.current_playlist_id = playlist_id
            self.current_video_index = 0
            
            if shuffle:
                random.shuffle(self.playlist_videos)
        
        if not self.playlist_videos:
            self.logger.warning(f"⚠️ No videos found in playlist {playlist_id}")
            return None
        
        # Get current video
        video = self.playlist_videos[self.current_video_index]
        
        # Move to next video (loop back to start)
        self.current_video_index = (self.current_video_index + 1) % len(self.playlist_videos)
        
        return video
    
    def stop_current_stream(self):
        """Stop current streaming process"""
        if self.current_process:
            try:
                self.logger.info("⏹️ Stopping current stream...")
                self.current_process.terminate()
                
                # Wait for graceful termination
                try:
                    self.current_process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    self.logger.warning("🔥 Force killing stream process...")
                    self.current_process.kill()
                
                self.current_process = None
                self.logger.info("✅ Stream stopped")
                
            except Exception as e:
                self.logger.error(f"❌ Error stopping stream: {e}")
    
    def start_video_stream(self, video):
        """Start streaming a specific video"""
        video_path = video['file_path']
        
        if not os.path.exists(video_path):
            self.logger.error(f"❌ Video file not found: {video_path}")
            return False
        
        # FFmpeg command for streaming
        ffmpeg_cmd = [
            'ffmpeg',
            '-re',  # Read input at native frame rate
            '-i', video_path,
            '-c:v', 'libx264',  # Video codec
            '-preset', 'fast',  # Encoding speed
            '-c:a', 'aac',  # Audio codec
            '-f', 'flv',  # Output format
            '-y',  # Overwrite output
            self.rtmp_url
        ]
        
        try:
            self.logger.info(f"▶️ Starting stream: {video['display_name']} ({video.get('duration', 'Unknown')} duration)")
            
            # Start FFmpeg process
            self.current_process = subprocess.Popen(
                ffmpeg_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                preexec_fn=os.setsid  # Create new process group
            )
            
            return True
            
        except Exception as e:
            self.logger.error(f"❌ Failed to start stream: {e}")
            return False
    
    def check_stream_health(self):
        """Check if current stream is still running"""
        if self.current_process:
            poll_result = self.current_process.poll()
            if poll_result is not None:
                # Process has ended
                self.logger.info(f"📺 Stream ended with code: {poll_result}")
                self.current_process = None
                return False
            return True
        return False
    
    def run_scheduler_cycle(self):
        """Run one cycle of the scheduler"""
        # Check current schedule
        schedule = self.get_current_schedule()
        target_playlist_id = None
        
        if schedule:
            target_playlist_id = schedule['playlist_id']
        else:
            # Use fallback playlist
            fallback = self.get_fallback_playlist()
            if fallback:
                target_playlist_id = fallback['id']
        
        if not target_playlist_id:
            self.logger.warning("⚠️ No playlist available for streaming")
            return
        
        # Check if we need to change playlist
        playlist_changed = target_playlist_id != self.current_playlist_id
        
        # Check if current stream is healthy
        stream_healthy = self.check_stream_health()
        
        # Start new stream if needed
        if not stream_healthy or playlist_changed:
            if playlist_changed:
                self.logger.info(f"🔄 Playlist change detected, switching to playlist {target_playlist_id}")
                self.stop_current_stream()
            
            # Get next video from playlist
            video = self.get_next_video(target_playlist_id, shuffle=True)
            if video:
                self.start_video_stream(video)
            else:
                self.logger.error(f"❌ No videos available in playlist {target_playlist_id}")
    
    def run(self):
        """Main scheduler loop"""
        self.logger.info("🚀 Starting Basic Scheduler...")
        self.is_running = True
        
        while self.is_running:
            try:
                self.run_scheduler_cycle()
                
                # Wait 60 seconds before next check
                for _ in range(60):
                    if not self.is_running:
                        break
                    time.sleep(1)
                
            except Exception as e:
                self.logger.error(f"❌ Scheduler cycle error: {e}")
                time.sleep(10)  # Wait before retrying
        
        self.logger.info("📡 Scheduler stopped")

if __name__ == "__main__":
    scheduler = BasicScheduler()
    scheduler.run()
