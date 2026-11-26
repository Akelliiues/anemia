import json
import re
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

manifest = Path(sys.argv[1])
release = json.loads(manifest.read_text(encoding="utf-8"))
thailand = timezone(timedelta(hours=7))
release["build"] = datetime.now(thailand).strftime("%Y%m%d%H%M%S")
update_script = manifest.with_name("installed-update.js")
source = update_script.read_text(encoding="utf-8")
source, replacements = re.subn(
    r"(?<=const runningBuild=')\d{14}(?=';)", release["build"], source
)
if replacements != 1:
    raise ValueError("Installed app build marker is missing or duplicated")
update_script.write_text(source, encoding="utf-8")
for pattern in ("*.html", "*.php"):
    for page in manifest.parent.glob(pattern):
        contents = page.read_bytes()
        updated = re.sub(
            rb'src="installed-update\.js(?:\?v=[^"\r\n]+)?"',
            f'src="installed-update.js?v={release["build"]}"'.encode(),
            contents,
        )
        if contents != updated:
            page.write_bytes(updated)
manifest.write_text(json.dumps(release, indent=2) + "\n", encoding="utf-8")
print(f"version={release['version']}")
print(f"build={release['build']}")
