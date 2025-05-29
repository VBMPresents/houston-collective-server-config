#!/usr/bin/env python3
"""
SRS Streaming Server - Content Scanner
Phase 2 Step 2: Content Discovery and Scanning System
Supports: MP4, MKV, AVI, MOV, M4V, FLV, TS, WebM
Enhanced with folder structure tracking - STREAMING SAFE
"""

import os
import sys
import sqlite3
import json
import subprocess
import hashlib
import logging
from datetime import datetime
from pathlib import Path

# Configuration
DATABASE_PATH = "/opt/streamserver/database/streaming.db"
CONTENT_PATH = "/opt/streamserver/content"
SUPPORTED_FORMATS = {'.mp4', '.mkv', '.avi', '.mov', '.m4v', '.flv', '.ts', '.webm'}

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/opt/streamserver/logs/content_scanner.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class ContentScanner:
    def __init__(self):
        self.db_path = DATABASE_PATH
        self.content_path = CONTENT_PATH
        self.processed_count = 0
        self.error_count = 0

    def get_db_connection(self):
        """Create database connection with WAL mode optimization"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.execute("PRAGMA journal_mode = WAL")
            conn.execute("PRAGMA synchronous = NORMAL")
            conn.execute("PRAGMA cache_size = 10000")
            conn.row_factory = sqlite3.Row
            return conn
        except Exception as e:
            logger.error(f"Database connection failed: {e}")
            raise

    def extract_metadata(self, file_path):
        """Extract video metadata using FFprobe"""
        try:
            cmd = [
                'ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_format', '-show_streams', str(file_path)
            ]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)

            if result.returncode != 0:
                logger.warning(f"FFprobe failed for {file_path}: {result.stderr}")
                return None

            data = json.loads(result.stdout)

            # Extract video stream info
            video_stream = next((s for s in data['streams'] if s['codec_type'] == 'video'), None)
            audio_stream = next((s for s in data['streams'] if s['codec_type'] == 'audio'), None)

            metadata = {
                'duration': int(float(data['format'].get('duration', 0))),
                'file_size': int(data['format'].get('size', 0)),
                'format': data['format'].get('format_name', ''),
                'bitrate': int(data['format'].get('bit_rate', 0)) // 1000,
            }

            if video_stream:
                width = video_stream.get('width', 0)
                height = video_stream.get('height', 0)
                metadata.update({
                    'resolution': f"{width}x{height}" if width and height else '',
                    'codec': video_stream.get('codec_name', ''),
                })

            if audio_stream:
                metadata.update({
                    'audio_codec': audio_stream.get('codec_name', ''),
                    'audio_bitrate': int(audio_stream.get('bit_rate', 0)) // 1000,
                    'audio_channels': audio_stream.get('channels', 0),
                })

            return metadata

        except subprocess.TimeoutExpired:
            logger.error(f"FFprobe timeout for {file_path}")
            return None
        except Exception as e:
            logger.error(f"Metadata extraction failed for {file_path}: {e}")
            return None

    def is_duplicate(self, conn, file_path):
        """Check if file already exists in database"""
        cursor = conn.execute("SELECT id FROM videos WHERE file_path = ?", (str(file_path),))
        return cursor.fetchone() is not None

    def add_video_to_db(self, conn, file_path, metadata):
        """Add video to database with metadata"""
        try:
            filename = file_path.name
            display_name = filename.rsplit('.', 1)[0]

            cursor = conn.execute("""
                INSERT INTO videos (
                    filename, file_path, display_name, duration, file_size,
                    format, resolution, bitrate, codec, audio_codec,
                    audio_bitrate, audio_channels
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                filename, str(file_path), display_name,
                metadata.get('duration'), metadata.get('file_size'),
                metadata.get('format'), metadata.get('resolution'),
                metadata.get('bitrate'), metadata.get('codec'),
                metadata.get('audio_codec'), metadata.get('audio_bitrate'),
                metadata.get('audio_channels')
            ))

            conn.commit()
            return cursor.lastrowid

        except sqlite3.IntegrityError:
            logger.warning(f"Duplicate entry for {file_path}")
            return None
        except Exception as e:
            logger.error(f"Database insert failed for {file_path}: {e}")
            return None

    def scan_directory(self, directory_path):
        """Recursively scan directory for video files"""
        video_files = []

        try:
            for root, dirs, files in os.walk(directory_path):
                for file in files:
                    file_path = Path(root) / file
                    if file_path.suffix.lower() in SUPPORTED_FORMATS:
                        if file_path.is_file() and file_path.stat().st_size > 0:
                            video_files.append(file_path)
                        else:
                            logger.warning(f"Invalid file: {file_path}")

        except Exception as e:
            logger.error(f"Directory scan failed for {directory_path}: {e}")

        return video_files

    def cleanup_missing_files(self, conn):
        """Remove database entries for missing files"""
        cursor = conn.execute("SELECT id, file_path FROM videos")
        missing_files = []

        for row in cursor.fetchall():
            if not Path(row['file_path']).exists():
                missing_files.append(row['id'])

        if missing_files:
            placeholders = ','.join('?' * len(missing_files))
            conn.execute(f"DELETE FROM videos WHERE id IN ({placeholders})", missing_files)
            conn.commit()
            logger.info(f"Removed {len(missing_files)} missing files from database")

        return len(missing_files)

    def scan_folders(self, conn):
        """STREAMING SAFE: Scan and populate folder structure without disrupting active streaming"""
        try:
            logger.info("Scanning folder structure (streaming-safe mode)...")
            
            # Get existing folders from database
            cursor = conn.execute("SELECT folder_path, video_count FROM folders")
            existing_folders = {row[0]: row[1] for row in cursor.fetchall()}
            
            found_folders = {}
            folders_added = 0
            folders_updated = 0

            # Walk through directory structure
            for root, dirs, files in os.walk(self.content_path):
                # Skip the root content directory itself
                if root == self.content_path:
                    continue

                # Get relative path from content directory
                rel_path = os.path.relpath(root, self.content_path)
                folder_name = os.path.basename(root)

                # Determine parent folder
                parent_path = os.path.dirname(rel_path)
                parent_folder = parent_path if parent_path != '.' else None

                # Count video files in this folder (not subdirectories)
                video_count = 0
                for file in files:
                    if Path(file).suffix.lower() in SUPPORTED_FORMATS:
                        video_count += 1

                found_folders[rel_path] = video_count

                # Check if folder exists and needs updating
                if rel_path in existing_folders:
                    if existing_folders[rel_path] != video_count:
                        # Update existing folder
                        conn.execute("""
                            UPDATE folders SET video_count = ?, date_modified = CURRENT_TIMESTAMP
                            WHERE folder_path = ?
                        """, (video_count, rel_path))
                        folders_updated += 1
                        logger.info(f"Updated folder: {rel_path} ({video_count} videos)")
                else:
                    # Insert new folder
                    try:
                        conn.execute("""
                            INSERT INTO folders 
                            (folder_path, folder_name, parent_folder, video_count)
                            VALUES (?, ?, ?, ?)
                        """, (rel_path, folder_name, parent_folder, video_count))
                        folders_added += 1
                        logger.info(f"Added folder: {rel_path} ({video_count} videos)")
                    except Exception as e:
                        logger.error(f"Error adding folder {rel_path}: {e}")

            # Remove folders that no longer exist (surgical removal)
            folders_removed = 0
            for existing_path in existing_folders:
                if existing_path not in found_folders:
                    conn.execute("DELETE FROM folders WHERE folder_path = ?", (existing_path,))
                    folders_removed += 1
                    logger.info(f"Removed missing folder: {existing_path}")

            conn.commit()
            logger.info(f"Folder scan completed: {folders_added} added, {folders_updated} updated, {folders_removed} removed")
            return folders_added + folders_updated

        except Exception as e:
            logger.error(f"Folder scan failed: {e}")
            conn.rollback()
            return 0

    def scan_content(self):
        """Main scanning function with streaming-safe folder tracking"""
        logger.info("Starting enhanced content scan (streaming-safe mode)...")

        if not Path(self.content_path).exists():
            logger.error(f"Content directory not found: {self.content_path}")
            return False

        try:
            conn = self.get_db_connection()

            # STREAMING SAFE: Scan folders first using safe operations
            folders_count = self.scan_folders(conn)

            # Cleanup missing files first
            self.cleanup_missing_files(conn)

            # Scan for video files
            video_files = self.scan_directory(self.content_path)
            total_files = len(video_files)

            logger.info(f"Found {total_files} video files to process")

            for i, file_path in enumerate(video_files, 1):
                try:
                    if self.is_duplicate(conn, file_path):
                        continue

                    metadata = self.extract_metadata(file_path)
                    if not metadata:
                        self.error_count += 1
                        continue

                    video_id = self.add_video_to_db(conn, file_path, metadata)
                    if video_id:
                        self.processed_count += 1
                        logger.info(f"Added: {file_path.name} (ID: {video_id})")
                    else:
                        self.error_count += 1

                    if i % 10 == 0 or i == total_files:
                        progress = (i / total_files) * 100
                        logger.info(f"Progress: {i}/{total_files} ({progress:.1f}%)")

                except Exception as e:
                    logger.error(f"Processing failed for {file_path}: {e}")
                    self.error_count += 1
                    continue

            conn.close()

            logger.info(f"Enhanced content scan completed (streaming-safe):")
            logger.info(f"  Folders tracked: {folders_count}")
            logger.info(f"  Processed: {self.processed_count} files")
            logger.info(f"  Errors: {self.error_count} files")
            logger.info(f"  Total files found: {total_files}")

            return True

        except Exception as e:
            logger.error(f"Content scan failed: {e}")
            return False

def main():
    """Main entry point"""
    if len(sys.argv) > 1 and sys.argv[1] == '--help':
        print("Usage: python3 content_scanner.py")
        print("Scans /opt/streamserver/content/ for video files and adds them to database")
        print("Now includes streaming-safe folder structure tracking for enhanced navigation")
        return

    scanner = ContentScanner()
    success = scanner.scan_content()
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()
