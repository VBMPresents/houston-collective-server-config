#!/usr/bin/env python3
"""
Smart Scheduler for The Houston Collective Streaming Server
Advanced automation with priority resolution, intelligent gap filling, and emergency controls
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
from datetime import datetime, timedelta
from pathlib import Path

class SmartScheduler:
    def __init__(self):
        # Configuration
        self.db_path = '/opt/streamserver/database/streaming.db'
        self.content_dir = '/opt/streamserver/content'
        self.log_dir = '/opt/streamserver/logs'
        self.config_dir = '/opt/streamserver/config'
        self.rtmp_url = 'rtmp://127.0.0.1:1935/live/test'
        
        # Current streaming state
        self.current_process = None
        self.current_playlist_id = None
        self.current_video_index = 0
        self.playlist_videos = []
        self.is_running = False
        self.emergency_override = False
        self.override_playlist_id = None
        
        # Smart scheduling state
        self.last_schedule_check = None
        self.schedule_cache = []
        self.analytics = {
            'streams_started': 0,
            'schedule_switches': 0,
            'emergency_overrides': 0,
            'errors': 0,
            'uptime_start': datetime.now()
        }
        
        # Setup
        self.setup_logging()
        self.setup_directories()
        self.load_emergency_config()
        
        # Signal handlers
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        signal.signal(signal.SIGUSR1, self.emergency_override_handler)
        
        self.logger.info("🧠 Smart Scheduler initialized with advanced features")
    
    def setup_logging(self):
        """Setup comprehensive logging system"""
        os.makedirs(self.log_dir, exist_ok=True)
        
        # Create formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        
        # Setup logger
        self.logger = logging.getLogger('SmartScheduler')
        self.logger.setLevel(logging.INFO)
        
        # File handler for all logs
        file_handler = logging.FileHandler(f'{self.log_dir}/smart_scheduler.log')
        file_handler.setFormatter(formatter)
        self.logger.addHandler(file_handler)
        
        # Console handler for real-time monitoring
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setFormatter(formatter)
        self.logger.addHandler(console_handler)
        
        # Separate analytics log
        self.analytics_logger = logging.getLogger('Analytics')
        analytics_handler = logging.FileHandler(f'{self.log_dir}/analytics.log')
        analytics_handler.setFormatter(formatter)
        self.analytics_logger.addHandler(analytics_handler)
    
    def setup_directories(self):
        """Ensure all required directories exist"""
        os.makedirs(self.config_dir, exist_ok=True)
        os.makedirs(self.log_dir, exist_ok=True)
    
    def load_emergency_config(self):
        """Load emergency override configuration"""
        config_file = f'{self.config_dir}/emergency.json'
        self.emergency_config = {
            'enabled': True,
            'fallback_playlist_id': None,
            'max_retries': 3,
            'retry_delay': 30
        }
        
        try:
            if os.path.exists(config_file):
                with open(config_file, 'r') as f:
                    self.emergency_config.update(json.load(f))
        except Exception as e:
            self.logger.warning(f"⚠️ Could not load emergency config: {e}")
    
    def signal_handler(self, signum, frame):
        """Handle shutdown signals gracefully"""
        self.logger.info(f"📡 Received signal {signum}, shutting down gracefully...")
        self.save_analytics()
        self.is_running = False
        self.stop_current_stream()
        sys.exit(0)
    
    def emergency_override_handler(self, signum, frame):
        """Handle emergency override signal (SIGUSR1)"""
        self.logger.warning("🚨 Emergency override activated via signal!")
        self.emergency_override = True
        self.analytics['emergency_overrides'] += 1
    
    def cleanup_ffmpeg_processes(self):
        """Clean up any orphaned FFmpeg processes"""
        try:
            # Kill any existing FFmpeg processes streaming to RTMP
            result = subprocess.run(['pkill', '-f', 'ffmpeg.*rtmp'], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                self.logger.info("🧹 Cleaned up orphaned FFmpeg processes")
            
            # Wait a moment for processes to die
            time.sleep(2)
            
            # Double-check no processes remain
            result = subprocess.run(['pgrep', '-f', 'ffmpeg.*rtmp'], 
                                  capture_output=True, text=True)
            if result.stdout.strip():
                self.logger.warning(f"⚠️ Some FFmpeg processes still running: {result.stdout.strip()}")
                # Force kill if necessary
                subprocess.run(['pkill', '-9', '-f', 'ffmpeg.*rtmp'], 
                              capture_output=True, text=True)
                time.sleep(1)
            
        except Exception as e:
            self.logger.error(f"❌ Error during FFmpeg cleanup: {e}")
            self.analytics['errors'] += 1


    def get_db_connection(self):
        """Get database connection with error handling"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.row_factory = sqlite3.Row
            return conn
        except Exception as e:
            self.logger.error(f"❌ Database connection failed: {e}")
            self.analytics['errors'] += 1
            return None
    
    def get_active_schedules(self):
        """Get all potentially active schedules for intelligent analysis"""
        now = datetime.now()
        current_day = now.weekday()
        current_day = 0 if current_day == 6 else current_day + 1  # Convert to our format
        current_time = now.strftime('%H:%M')
        
        conn = self.get_db_connection()
        if not conn:
            return []
        
        try:
            cursor = conn.cursor()
            cursor.execute("""
                SELECT s.*, p.name as playlist_name, p.priority, p.playlist_type,
                       p.shuffle_enabled, p.loop_enabled
                FROM schedule s
                JOIN playlists p ON s.playlist_id = p.id
                WHERE s.is_active = 1 
                AND (
                    (s.day_of_week = ? AND s.start_time <= ? AND s.end_time > ?)
                    OR (s.repeat_type = 'daily')
                )
                ORDER BY 
                    CASE p.priority 
                        WHEN 'high' THEN 3
                        WHEN 'medium' THEN 2
                        WHEN 'low' THEN 1
                        ELSE 0
                    END DESC,
                    s.start_time
            """, (current_day, current_time, current_time))
            
            schedules = cursor.fetchall()
            conn.close()
            
            return [dict(schedule) for schedule in schedules]
            
        except Exception as e:
            self.logger.error(f"❌ Error getting schedules: {e}")
            self.analytics['errors'] += 1
            conn.close()
            return []
    
    def resolve_schedule_conflicts(self, schedules):
        """Intelligent conflict resolution for overlapping schedules"""
        if len(schedules) <= 1:
            return schedules[0] if schedules else None
        
        now = datetime.now()
        current_time = now.strftime('%H:%M')
        
        # Priority-based resolution
        high_priority = [s for s in schedules if s['priority'] == 'high']
        if high_priority:
            winner = high_priority[0]
            self.logger.info(f"🎯 Schedule conflict resolved: HIGH priority '{winner['playlist_name']}' wins")
            return winner
        
        # Time-based resolution for same priority
        medium_priority = [s for s in schedules if s['priority'] == 'medium']
        if medium_priority:
            # Choose the one that started most recently
            winner = max(medium_priority, key=lambda x: x['start_time'])
            self.logger.info(f"🎯 Schedule conflict resolved: Most recent medium priority '{winner['playlist_name']}'")
            return winner
        
        # Default to first low priority
        low_priority = [s for s in schedules if s['priority'] == 'low']
        if low_priority:
            winner = low_priority[0]
            self.logger.info(f"🎯 Schedule conflict resolved: Low priority '{winner['playlist_name']}'")
            return winner
        
        return schedules[0]
    
    def get_intelligent_fallback(self):
        """Smart fallback selection based on time patterns and usage"""
        now = datetime.now()
        hour = now.hour
        
        conn = self.get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor()
            
            # Time-based smart selection
            if 6 <= hour < 12:  # Morning
                priority_order = "('medium', 'high', 'low')"
                time_context = "morning"
            elif 12 <= hour < 18:  # Afternoon/Prime Time
                priority_order = "('high', 'medium', 'low')"
                time_context = "prime time"
            elif 18 <= hour < 22:  # Evening
                priority_order = "('medium', 'high', 'low')"
                time_context = "evening"
            else:  # Late night/early morning
                priority_order = "('low', 'medium', 'high')"
                time_context = "late night"
            
            cursor.execute(f"""
                SELECT id, name, priority, playlist_type, shuffle_enabled, loop_enabled
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
                self.logger.info(f"🧠 Smart fallback for {time_context}: '{result['name']}' (Priority: {result['priority']})")
            
            return result
            
        except Exception as e:
            self.logger.error(f"❌ Error getting intelligent fallback: {e}")
            self.analytics['errors'] += 1
            conn.close()
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
            
            if shuffle:
                random.shuffle(video_list)
                self.logger.info(f"🎲 Shuffled {len(video_list)} videos in playlist {playlist_id}")
            
            return video_list
            
        except Exception as e:
            self.logger.error(f"❌ Error getting playlist videos: {e}")
            self.analytics['errors'] += 1
            conn.close()
            return []
    
    def get_next_video(self, playlist_id, shuffle=False, loop=True):
        """Smart video selection with advanced playlist management"""
        # Reload playlist if changed
        if playlist_id != self.current_playlist_id:
            self.playlist_videos = self.get_playlist_videos(playlist_id, shuffle)
            self.current_playlist_id = playlist_id
            self.current_video_index = 0
            self.logger.info(f"📋 Loaded playlist {playlist_id} with {len(self.playlist_videos)} videos")
        
        if not self.playlist_videos:
            self.logger.warning(f"⚠️ No videos found in playlist {playlist_id}")
            return None
        
        # Get current video
        if self.current_video_index < len(self.playlist_videos):
            video = self.playlist_videos[self.current_video_index]
        else:
            if loop:
                self.current_video_index = 0
                video = self.playlist_videos[0]
                self.logger.info("🔄 Playlist loop: restarting from beginning")
            else:
                self.logger.info("⏹️ Playlist ended (no loop)")
                return None
        
        # Advance to next video
        self.current_video_index += 1
        
        return video
    
    def stop_current_stream(self):
        """Stop current stream with enhanced error handling"""
        if self.current_process:
            try:
                self.logger.info("⏹️ Stopping current stream...")
                self.current_process.terminate()
                
                # Wait for graceful termination
                try:
                    self.current_process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    self.logger.warning("🔥 Force killing stream process...")
                    self.current_process.kill()
                    self.current_process.wait()
                
                self.current_process = None
                self.logger.info("✅ Stream stopped successfully")
                
            except Exception as e:
                self.logger.error(f"❌ Error stopping stream: {e}")
                self.analytics['errors'] += 1
    
    def start_video_stream(self, video, quality_preset="fast"):
        """Enhanced video streaming with quality options"""
        video_path = video['file_path']
        
        if not os.path.exists(video_path):
            self.logger.error(f"❌ Video file not found: {video_path}")
            self.analytics['errors'] += 1
            return False
        
        # Enhanced FFmpeg command with better quality settings
        ffmpeg_cmd = [
            'ffmpeg',
            '-re',  # Read at native frame rate
            '-i', video_path,
            '-c:v', 'libx264',
            '-preset', quality_preset,
            '-tune', 'zerolatency',  # Low latency streaming
            '-crf', '28',  # Constant quality
            '-maxrate', '1500k',  # Max bitrate
            '-bufsize', '4000k',  # Buffer size
            '-c:a', 'aac',
            '-b:a', '128k',  # Audio bitrate
            '-f', 'flv',
            '-y',
            self.rtmp_url
        ]
        
        try:
            duration_str = video.get('duration', 'Unknown')
            resolution_str = video.get('resolution', 'Unknown')
            file_size = video.get('file_size', 0)
            
            self.logger.info(f"▶️ Starting stream: {video['display_name']}")
            self.logger.info(f"📊 Video stats: {duration_str} duration, {resolution_str}, {file_size} bytes")
            
            # Start FFmpeg with enhanced logging
            self.current_process = subprocess.Popen(
                ffmpeg_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                preexec_fn=os.setsid
            )
            
            self.analytics['streams_started'] += 1
        
            return True
            
        except Exception as e:
            self.logger.error(f"❌ Failed to start stream: {e}")
            self.analytics['errors'] += 1
            return False
    
    def check_stream_health(self):
        """Advanced stream health monitoring with natural ending detection"""
        if not self.current_process:
            return False
        
        poll_result = self.current_process.poll()
        if poll_result is not None:
            # Get FFmpeg output for debugging
            try:
                stdout, stderr = self.current_process.communicate(timeout=1)
                if stderr:
                    error_snippet = stderr.decode()[-200:]  # Last 200 chars
                    self.logger.warning(f"📺 Stream ended with code {poll_result}: ...{error_snippet}")
            except:
                self.logger.info(f"📺 Stream ended with code: {poll_result}")
            
            self.current_process = None
            
            # CRITICAL FIX: Return different values based on exit code
            if poll_result == 0:
                self.logger.info("✅ Video ended naturally - will advance to next video")
                return "ended_naturally"
            else:
                self.logger.warning(f"❌ Stream crashed with code {poll_result} - will retry same video")
                return False
        
        # CRITICAL: Check if HLS files are being created
        from pathlib import Path
        import time
        hls_path = Path("/opt/streamserver/srs/hls/live/test.m3u8")
        if not hls_path.exists():
            self.logger.warning("⚠️ HLS playlist file missing")
            return False
            
        # Check if HLS file is recent (within last 45 seconds)
        if time.time() - hls_path.stat().st_mtime > 45:
            self.logger.warning("⚠️ HLS file is stale - stream appears hung")
            return False
        
        return True
    
    def save_analytics(self):
        """Save analytics and runtime statistics"""
        try:
            uptime = datetime.now() - self.analytics['uptime_start']
            analytics_file = f'{self.log_dir}/analytics_summary.json'
            
            summary = {
                'session_start': self.analytics['uptime_start'].isoformat(),
                'session_end': datetime.now().isoformat(),
                'uptime_seconds': uptime.total_seconds(),
                'streams_started': self.analytics['streams_started'],
                'schedule_switches': self.analytics['schedule_switches'],
                'emergency_overrides': self.analytics['emergency_overrides'],
                'errors': self.analytics['errors'],
                'current_playlist': self.current_playlist_id
            }
            
            with open(analytics_file, 'w') as f:
                json.dump(summary, f, indent=2)
                
            self.logger.info(f"📊 Analytics saved: {self.analytics['streams_started']} streams, {uptime} uptime")
            
        except Exception as e:
            self.logger.error(f"❌ Failed to save analytics: {e}")
    
    def run_smart_cycle(self):
        """Main intelligent scheduling cycle with enhanced stream management"""
        target_playlist_id = None
        schedule_source = "fallback"
        
        # CRITICAL FIX: Periodically check for override clearing
        try:
            config_file = f'{self.config_dir}/emergency.json'
            if os.path.exists(config_file):
                with open(config_file, 'r') as f:
                    config = json.load(f)
                    if 'emergency_override' in config and not config['emergency_override']:
                        if self.emergency_override:
                            self.logger.info("✅ Emergency override cleared via web interface")
                            self.emergency_override = False
        except Exception as e:
            self.logger.warning(f"⚠️ Error checking override config: {e}")


        # Check for emergency override
        if self.emergency_override and self.override_playlist_id:
            target_playlist_id = self.override_playlist_id
            schedule_source = "emergency_override"
            self.logger.warning(f"🚨 Emergency override active: playlist {target_playlist_id}")
        else:
            # Get active schedules
            active_schedules = self.get_active_schedules()
            
            if active_schedules:
                # Resolve conflicts intelligently
                winning_schedule = self.resolve_schedule_conflicts(active_schedules)
                if winning_schedule:
                    target_playlist_id = winning_schedule['playlist_id']
                    schedule_source = f"schedule_{winning_schedule['id']}"
                    
                    # Log schedule switches
                    if target_playlist_id != self.current_playlist_id:
                        self.analytics['schedule_switches'] += 1
                        self.logger.info(f"📅 Schedule switch: {winning_schedule['playlist_name']} ({winning_schedule['start_time']}-{winning_schedule['end_time']})")
            
            # Intelligent fallback if no schedule
            if not target_playlist_id:
                fallback = self.get_intelligent_fallback()
                if fallback:
                    target_playlist_id = fallback['id']
                    schedule_source = "smart_fallback"
        
        if not target_playlist_id:
            self.logger.warning("⚠️ No playlist available for streaming")
            return
        
        # Check stream health with enhanced detection
        stream_health = self.check_stream_health()
        playlist_changed = target_playlist_id != self.current_playlist_id
        
        # CRITICAL FIX: Handle natural endings vs crashes differently
        if stream_health == "ended_naturally":
            self.logger.info("🎬 Video completed naturally - advancing to next video")
            video = self.get_next_video(target_playlist_id, shuffle=True, loop=True)
            if video:
                self.start_video_stream(video)
            else:
                self.logger.error(f"❌ No videos available in playlist {target_playlist_id}")
                self.analytics['errors'] += 1
        elif not stream_health or playlist_changed:
            # Stream unhealthy or playlist change needed
            if playlist_changed:
                self.logger.info(f"🔄 Switching to playlist {target_playlist_id} (Source: {schedule_source})")
            else:
                self.logger.warning("🚨 Stream unhealthy - restarting current video")
                # Don't advance index on crash - retry same video
                if self.current_video_index > 0:
                    self.current_video_index -= 1
            
            self.stop_current_stream()
            
            # Get next video with smart selection
            video = self.get_next_video(target_playlist_id, shuffle=True, loop=True)
            if video:
                self.start_video_stream(video)
            else:
                self.logger.error(f"❌ No videos available in playlist {target_playlist_id}")
                self.analytics['errors'] += 1
    
    def run(self):
        """Main smart scheduler loop with enhanced monitoring"""
        self.logger.info("🧠 Starting Smart Scheduler with advanced features...")
        self.is_running = True
        
        # Clean up any orphaned processes at startup
        self.cleanup_ffmpeg_processes()
        cycle_count = 0
        
        while self.is_running:
            try:
                cycle_count += 1
                self.run_smart_cycle()
                
                # Periodic analytics save with robust error handling
                # Periodic analytics save with robust error handling
                if cycle_count % 360 == 0:  # Every hour (360 cycles * 10s = 1 hour)
                    try:
                        self.save_analytics()
                        self.logger.info(f"📊 Hourly stats: {self.analytics['streams_started']} streams, {self.analytics['errors']} errors")
                    except Exception as e:
                        self.logger.warning(f"⚠️ Analytics save failed: {e}")
                
                # Wait 10 seconds with graceful shutdown check
                for i in range(10):
                    if not self.is_running:
                        break
                    time.sleep(1)
                    
            except Exception as e:
                self.logger.error(f"❌ Smart scheduler cycle error: {e}")
                self.analytics['errors'] += 1
                time.sleep(30)  # Wait before retrying on error
        
        self.save_analytics()
        
        self.save_analytics()
        self.logger.info("🧠 Smart Scheduler stopped")

if __name__ == "__main__":
    scheduler = SmartScheduler()
    scheduler.run()
    scheduler.run()
