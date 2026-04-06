import urllib.request
from PIL import Image
import io

url = "https://upload.wikimedia.org/wikipedia/commons/c/c3/World_map_blank_black.png"
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req) as response:
    img_data = response.read()

img = Image.open(io.BytesIO(img_data)).convert('L')
img = img.resize((120, 60))
pixels = img.load()

# output as array of strings
out = []
for y in range(60):
    row = ""
    for x in range(120):
        p = pixels[x, y]
        # Wiki image: oceans are transparent/white, land is black
        if p < 128:
            row += "1"
        else:
            row += "0"
    out.append(row)

with open('map_data.js', 'w') as f:
    f.write("const worldMap = [\n")
    for row in out:
        f.write(f"  '{row}',\n")
    f.write("];\n")
