import sys
import os
import time
import logging

# To be able to import local package
modulesPath = sys.argv[1]
sys.path.append(modulesPath)

from modules import yt_dlp

videoID = sys.argv[2]
videoURL = sys.argv[3]
storageDir = sys.argv[4]

# Set the log file path and name
log_file = os.path.join(f"{storageDir}\\logs\\video_download.log")
# Configure logging
logging.basicConfig(
    filename=log_file,
    level=logging.DEBUG,
    format="%(asctime)s \n%(message)s",
    datefmt="[%Y-%m-%d] [%H:%M:%S]",
)

savePath = f"{storageDir}\\app\\videos\\new\\{videoID}\\"
fileName = "%(title)s.%(ext)s"

ydl_opts = {
    "outtmpl": f"{savePath}{fileName}",
    "live_from_start": True,
    "wait_for_video": [
        1,
        120,
    ],  # Wait from min 1 second to max 120 seconds before retry
    "format": "bestvideo+bestaudio/best",
    "noplaylist": True,
}

# Retry 5 times every 30 seconds
for i in range(5):
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download(videoURL)
        break
    except yt_dlp.utils.DownloadError as error:
        logging.error(f"Error occurred:\n{error}")

        # Wait 30 second before retrying
        time.sleep(30)
    except Exception as error:
        # Log error
        logging.error(f"Error of other type occurred:\n{error}")
        break
