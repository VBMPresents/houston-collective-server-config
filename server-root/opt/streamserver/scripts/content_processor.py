#!/usr/bin/env python3
"""
SRS Streaming Server - Content Processor
Phase 2 Step 6: Content Processing Pipeline
Handles thumbnail generation, format validation, and file organization
"""

import os
import sys
import sqlite3
import subprocess
import shutil
import logging
from datetime import datetime
from pathlib import Path
import hashlib
import json

# Configuration
DATABASE_PATH = "/opt/streamserver/database/streaming.db"
CONTENT_PATH = "/opt/streamserver/content"
TEMP_PATH = "/opt/streamserver/temp"
THUMBNAIL_PATH = "/opt/streamserver/web/assets/thumbnails"
SUPPORTED_FORMATS = {'.mp4', '.mkv', '.avi', '.mov', '.m4v', '.flv', '.ts', '.webm'}

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/opt/streamserver/logs/content_processor.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class ContentProcessor:
    def __init__(self):
        self.db_path = DATABASE_PATH
        self.content_path = CONTENT_PATH
        self.temp_path = TEMP_PATH
        self.thumbnail_path = THUMBNAIL_PATH
        self.processed_count = 0
        self.error_count = 0
        self.thumbnail_count = 0
        
        # Ensure directories exist
        self._ensure_directories()
        
    def _ensure_directories(self):
        """Create required directories if they don't exist"""
        for path in [self.temp_path, self.thumbnail_path]:
            Path(path).mkdir(parents=True, exist_ok=True)
            shutil.chown(path, user='streamadmin', group='streamadmin')
    
    def get_db_connection(self):
        """Create database connection with WAL mode"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.execute("PRAGMA journal_mode = WAL")
            conn.row_factory = sqlite3.Row
            return conn
        except Exception as e:
            logger.error(f"Database connection failed: {e}")
            raise

    def validate_video_format(self, file_path):
        """Validate video file format and integrity"""
        try:
            cmd = [
                'ffprobe', '-v', 'error', '-select_streams', 'v:0',
                '-show_entries', 'stream=codec_name,width,height,duration',
                '-of', 'csv=p=0', str(file_path)
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0 and result.stdout.strip():
                return True, "Valid video file"
            else:
                return False, f"Invalid video file: {result.stderr}"
                
        except subprocess.TimeoutExpired:
            return False, "Validation timeout"
        except Exception as e:
            return False, f"Validation error: {e}"

    def generate_thumbnail(self, video_path, video_id, duration=None):
        """Generate thumbnail for video using FFmpeg"""
        try:
            thumbnail_filename = f"video_{video_id}.jpg"
            thumbnail_full_path = Path(self.thumbnail_path) / thumbnail_filename
            
            # Calculate timestamp for thumbnail (10% into video or 10 seconds, whichever is smaller)
            if duration and duration > 0:
                timestamp = min(duration * 0.1, 10)
            else:
                timestamp = 10
            
            cmd = [
                'ffmpeg', '-ss', str(timestamp), '-i', str(video_path),
                '-vframes', '1', '-q:v', '2', '-vf', 'scale=320:240',
                '-y', str(thumbnail_full_path)
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
            
            if result.returncode == 0 and thumbnail_full_path.exists():
                # Update database with thumbnail path
                conn = self.get_db_connection()
                conn.execute(
                    "UPDATE videos SET thumbnail_path = ? WHERE id = ?",
                    (f"/assets/thumbnails/{thumbnail_filename}", video_id)
                )
                conn.commit()
                conn.close()
                
                logger.info(f"Generated thumbnail for video {video_id}: {thumbnail_filename}")
                return True, thumbnail_filename
            else:
                logger.warning(f"Thumbnail generation failed for video {video_id}: {result.stderr}")
                return False, result.stderr
                
        except subprocess.TimeoutExpired:
            logger.error(f"Thumbnail generation timeout for video {video_id}")
            return False, "Thumbnail generation timeout"
        except Exception as e:
            logger.error(f"Thumbnail generation error for video {video_id}: {e}")
            return False, str(e)

    def optimize_video(self, video_path, target_path=None):
        """Optimize video for streaming (optional)"""
        try:
            if target_path is None:
                target_path = Path(self.temp_path) / f"optimized_{Path(video_path).name}"
            
            cmd = [
                'ffmpeg', '-i', str(video_path),
                '-c:v', 'libx264', '-preset', 'fast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '128k',
                '-movflags', '+faststart',
                '-y', str(target_path)
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=1800)  # 30 min timeout
            
            if result.returncode == 0 and Path(target_path).exists():
                logger.info(f"Video optimized: {video_path} -> {target_path}")
                return True, str(target_path)
            else:
                logger.warning(f"Video optimization failed: {result.stderr}")
                return False, result.stderr
                
        except subprocess.TimeoutExpired:
            logger.error(f"Video optimization timeout: {video_path}")
            return False, "Optimization timeout"
        except Exception as e:
            logger.error(f"Video optimization error: {e}")
            return False, str(e)

    def organize_content(self, source_path, target_directory="processed"):
        """Organize content into structured directories"""
        try:
            source = Path(source_path)
            target_base = Path(self.content_path) / target_directory
            
            # Create year/month directory structure
            date = datetime.now()
            target_dir = target_base / str(date.year) / f"{date.month:02d}"
            target_dir.mkdir(parents=True, exist_ok=True)
            
            target_path = target_dir / source.name
            
            # Move file if not already in target location
            if source.resolve() != target_path.resolve():
                shutil.move(str(source), str(target_path))
                logger.info(f"Organized content: {source} -> {target_path}")
                return str(target_path)
            
            return str(source_path)
            
        except Exception as e:
            logger.error(f"Content organization failed: {e}")
            return str(source_path)

    def process_video(self, video_id):
        """Process a single video: validate, generate thumbnail, optionally optimize"""
        try:
            conn = self.get_db_connection()
            
            # Get video info from database
            cursor = conn.execute("SELECT * FROM videos WHERE id = ?", (video_id,))
            video = cursor.fetchone()
            
            if not video:
                logger.error(f"Video {video_id} not found in database")
                return False
            
            video_path = Path(video['file_path'])
            
            if not video_path.exists():
                logger.error(f"Video file not found: {video_path}")
                conn.execute("UPDATE videos SET is_active = 0 WHERE id = ?", (video_id,))
                conn.commit()
                conn.close()
                return False
            
            logger.info(f"Processing video {video_id}: {video['filename']}")
            
            # Validate video format
            is_valid, validation_msg = self.validate_video_format(video_path)
            if not is_valid:
                logger.error(f"Video validation failed for {video_id}: {validation_msg}")
                conn.execute("UPDATE videos SET is_active = 0 WHERE id = ?", (video_id,))
                conn.commit()
                conn.close()
                return False
            
            # Generate thumbnail if not exists
            if not video['thumbnail_path']:
                success, result = self.generate_thumbnail(video_path, video_id, video['duration'])
                if success:
                    self.thumbnail_count += 1
            
            # Update processing timestamp
            conn.execute(
                "UPDATE videos SET date_modified = CURRENT_TIMESTAMP WHERE id = ?",
                (video_id,)
            )
            conn.commit()
            conn.close()
            
            self.processed_count += 1
            logger.info(f"Successfully processed video {video_id}")
            return True
            
        except Exception as e:
            logger.error(f"Error processing video {video_id}: {e}")
            self.error_count += 1
            return False

    def process_all_videos(self, regenerate_thumbnails=False):
        """Process all videos in database"""
        try:
            conn = self.get_db_connection()
            
            # Get videos to process
            if regenerate_thumbnails:
                cursor = conn.execute("SELECT id FROM videos WHERE is_active = 1")
            else:
                cursor = conn.execute("SELECT id FROM videos WHERE is_active = 1 AND thumbnail_path IS NULL")
            
            video_ids = [row['id'] for row in cursor.fetchall()]
            total_videos = len(video_ids)
            
            logger.info(f"Processing {total_videos} videos...")
            
            for i, video_id in enumerate(video_ids, 1):
                try:
                    self.process_video(video_id)
                    
                    # Progress reporting
                    if i % 10 == 0 or i == total_videos:
                        progress = (i / total_videos) * 100
                        logger.info(f"Progress: {i}/{total_videos} ({progress:.1f}%)")
                        
                except Exception as e:
                    logger.error(f"Failed to process video {video_id}: {e}")
                    self.error_count += 1
                    continue
            
            conn.close()
            
            logger.info(f"Processing completed:")
            logger.info(f"  Processed: {self.processed_count} videos")
            logger.info(f"  Thumbnails: {self.thumbnail_count} generated")
            logger.info(f"  Errors: {self.error_count} videos")
            
            return True
            
        except Exception as e:
            logger.error(f"Batch processing failed: {e}")
            return False

    def cleanup_temp_files(self):
        """Clean up temporary files older than 24 hours"""
        try:
            temp_path = Path(self.temp_path)
            if not temp_path.exists():
                return
            
            current_time = datetime.now().timestamp()
            cleaned_count = 0
            
            for file_path in temp_path.iterdir():
                if file_path.is_file():
                    file_age = current_time - file_path.stat().st_mtime
                    if file_age > 86400:  # 24 hours
                        file_path.unlink()
                        cleaned_count += 1
            
            if cleaned_count > 0:
                logger.info(f"Cleaned up {cleaned_count} temporary files")
                
        except Exception as e:
            logger.error(f"Temp file cleanup failed: {e}")

    def generate_processing_report(self):
        """Generate processing report with statistics"""
        try:
            conn = self.get_db_connection()
            
            # Get processing statistics
            stats = {}
            
            # Total videos
            cursor = conn.execute("SELECT COUNT(*) as count FROM videos WHERE is_active = 1")
            stats['total_videos'] = cursor.fetchone()['count']
            
            # Videos with thumbnails
            cursor = conn.execute("SELECT COUNT(*) as count FROM videos WHERE is_active = 1 AND thumbnail_path IS NOT NULL")
            stats['videos_with_thumbnails'] = cursor.fetchone()['count']
            
            # Videos by format
            cursor = conn.execute("SELECT format, COUNT(*) as count FROM videos WHERE is_active = 1 GROUP BY format ORDER BY count DESC")
            stats['formats'] = dict(cursor.fetchall())
            
            # Processing summary
            stats['processing_summary'] = {
                'processed_count': self.processed_count,
                'thumbnail_count': self.thumbnail_count,
                'error_count': self.error_count,
                'processing_date': datetime.now().isoformat()
            }
            
            conn.close()
            
            # Save report
            report_path = Path('/opt/streamserver/logs/processing_report.json')
            with open(report_path, 'w') as f:
                json.dump(stats, f, indent=2)
            
            logger.info(f"Processing report saved: {report_path}")
            return stats
            
        except Exception as e:
            logger.error(f"Report generation failed: {e}")
            return None

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Content Processor for SRS Streaming Server')
    parser.add_argument('--video-id', type=int, help='Process specific video by ID')
    parser.add_argument('--all', action='store_true', help='Process all videos')
    parser.add_argument('--regenerate-thumbnails', action='store_true', help='Regenerate all thumbnails')
    parser.add_argument('--cleanup', action='store_true', help='Clean up temporary files')
    parser.add_argument('--report', action='store_true', help='Generate processing report')
    
    args = parser.parse_args()
    
    processor = ContentProcessor()
    
    try:
        if args.video_id:
            logger.info(f"Processing video ID: {args.video_id}")
            success = processor.process_video(args.video_id)
            sys.exit(0 if success else 1)
            
        elif args.all or args.regenerate_thumbnails:
            logger.info("Processing all videos...")
            success = processor.process_all_videos(args.regenerate_thumbnails)
            
        elif args.cleanup:
            logger.info("Cleaning up temporary files...")
            processor.cleanup_temp_files()
            success = True
            
        elif args.report:
            logger.info("Generating processing report...")
            report = processor.generate_processing_report()
            success = report is not None
            
        else:
            parser.print_help()
            sys.exit(1)
        
        if args.report or args.all:
            processor.generate_processing_report()
            
        sys.exit(0 if success else 1)
        
    except KeyboardInterrupt:
        logger.info("Processing interrupted by user")
        sys.exit(1)
    except Exception as e:
        logger.error(f"Processing failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
