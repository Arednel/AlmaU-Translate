import os
import sys
import cv2
import json
import pytesseract
from pytesseract import Output

# Get video name
videoID = sys.argv[1]
videoName = sys.argv[2]
imageNumber = sys.argv[3]
storageDir = sys.argv[4]

img = cv2.imread(storageDir + "images\\processing\\" + videoID + "\\" + videoName + "_" + imageNumber + ".png")

# Get output from tesseract as dictionary
dictionary = pytesseract.image_to_data(img, lang="rus+eng", output_type=Output.DICT)

# Ensure_ascii=False to allow non-ASCII characters
json_output = json.dumps(dictionary, ensure_ascii=False, indent=2) 

# Explicitly encode to UTF-8 before printing
with open(storageDir + "\\output\\" + videoID + "\\" + videoName + "_" + imageNumber + "_output.json", "w", encoding="utf-8") as file:
    file.write(json_output)