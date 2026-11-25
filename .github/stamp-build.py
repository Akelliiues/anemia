import json
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

manifest = Path(sys.argv[1])
release = json.loads(manifest.read_text(encoding="utf-8"))
thailand = timezone(timedelta(hours=7))
release["build"] = datetime.now(thailand).strftime("%Y%m%d%H%M%S")
manifest.write_text(json.dumps(release, indent=2) + "\n", encoding="utf-8")
print(f"version={release['version']}")
print(f"build={release['build']}")
