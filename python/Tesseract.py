import os
import sys
import cv2
import json
import pytesseract
from pytesseract import Output

# Get video name
videoName = sys.argv[1]
 
# Get current directory (Project/python)
currentDir = os.getcwd()

# Get the parent directory (one level up)
parentDir = os.path.abspath(os.path.join(currentDir, os.pardir))

storageDir = parentDir + "\\storage\\app\\"

img = cv2.imread(storageDir + "images\\" + videoName + ".jpg")

# Get output from tesseract as dictionary
dictionary = pytesseract.image_to_data(img, lang="rus+eng", output_type=Output.DICT)

# Ensure_ascii=False to allow non-ASCII characters
json_output = json.dumps(dictionary, ensure_ascii=False, indent=2) 

# Explicitly encode to UTF-8 before printing
with open(storageDir + "\\output\\output.json", "w", encoding="utf-8") as file:
    file.write(json_output)