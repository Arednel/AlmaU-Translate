import sys
import os
import json
import time
import logging

# To be able to import local package
modulesPath = sys.argv[1]
sys.path.append(modulesPath)

from modules import yt_dlp

playlistURL = sys.argv[2]
storageDir = sys.argv[3]

# Set the log file path and name
log_file = os.path.join(f"{storageDir}\\logs\\playlist_info_extract.log")
# Configure logging
logging.basicConfig(
    filename=log_file,
    level=logging.DEBUG,
    format="%(asctime)s \n%(message)s",
    datefmt="[%Y-%m-%d] [%H:%M:%S]",
)

ydl_opts = {
    "extract_flat": True,
}

for i in range(5):
    try:
        urls = []
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            playlist_info = ydl.extract_info(playlistURL, download=False)
            for entry in playlist_info["entries"]:
                urls.append(entry["url"])
                print(entry["url"])

        with open(f"{storageDir}\\app\\playlists\\playlist.json", "w") as json_file:
            json.dump(urls, json_file)
    except yt_dlp.utils.DownloadError as error:
        logging.error(f"Error occurred:\n{error}")

        # Wait 30 second before retrying
        time.sleep(30)
    except Exception as error:
        # Log error
        logging.error(f"Error of other type occurred:\n{error}")
        break
