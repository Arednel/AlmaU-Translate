import os
import cv2
import json
import pytesseract
from pytesseract import Output

# Get current directory (Project/python)
CurrentDir = os.path.dirname( __file__ )

img = cv2.imread(CurrentDir +'\\1.png')

# Get output from tesseract as dictionary
dictionary = pytesseract.image_to_data(img, lang="rus+eng", output_type=Output.DICT)

# Ensure_ascii=False to allow non-ASCII characters
json_output = json.dumps(dictionary, ensure_ascii=False, indent=2) 

# Explicitly encode to UTF-8 before printing
with open(CurrentDir + '\\output.json', "w", encoding="utf-8") as file:
    file.write(json_output)