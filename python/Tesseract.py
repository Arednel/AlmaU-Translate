import sys
import json

# To be able to import local package
modulesPath = sys.argv[1]
sys.path.append(modulesPath)

from modules import PIL
from PIL import Image
from modules import pytesseract
from pytesseract import Output

# Get video name
videoID = sys.argv[2]
videoName = sys.argv[3]
imageNumber = sys.argv[4]
storageDir = sys.argv[5]

img = Image.open(
    f"{storageDir}images\\processing\\{videoID}\\{videoName}_{imageNumber}.png"
)

# Get output from tesseract as dictionary
dictionary = pytesseract.image_to_data(img, lang="rus+eng", output_type=Output.DICT)

# Ensure_ascii=False to allow non-ASCII characters
json_output = json.dumps(dictionary, ensure_ascii=False, indent=2)

# Explicitly encode to UTF-8 before printing
with open(
    f"{storageDir}\\output\\{videoID}\\{videoName}_{imageNumber}_output.json",
    "w",
    encoding="utf-8",
) as file:
    file.write(json_output)
