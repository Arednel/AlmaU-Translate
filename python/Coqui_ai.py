import sys
import os
import tempfile
import json

# Fix for MPLCONFIGDIR error
os.environ["MPLCONFIGDIR"] = tempfile.mkdtemp()

# To be able to import local package
modulesPath = sys.argv[1]
sys.path.append(modulesPath)

from modules.TTS.api import TTS

translatedTextPath = sys.argv[2]
audioOutputPath = sys.argv[3]
audioOutputPath += "_generated_speech.wav"

# Read from json translated text
with open(translatedTextPath, "r") as file:
    translatedText = json.load(file)

api = TTS(model_name="tts_models/kaz/fairseq/vits")
api.tts_to_file(translatedText["text"], file_path=audioOutputPath)
