import sys
import tempfile
import json

# Fix for MPLCONFIGDIR error
import os
os.environ["MPLCONFIGDIR"] = tempfile.mkdtemp()

from TTS.api import TTS

translatedTextPath = sys.argv[1]
audioOutputPath = sys.argv[2]
audioOutputPath += '_generated_speech.wav'

# Read from json translated text
with open(translatedTextPath, 'r') as file:
    translatedText = json.load(file)

api = TTS(model_name="tts_models/kaz/fairseq/vits")
api.tts_to_file(translatedText['text'], file_path=audioOutputPath)