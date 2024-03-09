import sys
import json

# To be able to import local package
modulesPath = sys.argv[1]
sys.path.append(modulesPath)

from modules import whisper

extractedAudioPath = sys.argv[2]
audioFormat = sys.argv[3]
whisperOutputDir = sys.argv[4]

model = whisper.load_model("base")
result = model.transcribe(f"{extractedAudioPath}.{audioFormat}")

json_data = json.dumps(result)
with open(f"{extractedAudioPath}.json", "w", encoding="utf-8") as file:
    file.write(json_data)
