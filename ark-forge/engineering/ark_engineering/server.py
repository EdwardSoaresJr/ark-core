from __future__ import annotations

import json
import mimetypes
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from .config import EngineeringConfig, load_config
from .observer import capture_evidence
from .planner import save_plan
from .reviewer import record_review
from .seed import project_state, seed_state
from .store import EventStore
from .worker import declare_done, start_work

STATIC_DIR = Path(__file__).resolve().parent / "static"


class EngineeringHandler(BaseHTTPRequestHandler):
    config: EngineeringConfig
    store: EventStore

    def log_message(self, format: str, *args: Any) -> None:  # noqa: A003
        return

    def do_GET(self) -> None:  # noqa: N802
        path = urlparse(self.path).path
        if path == "/":
            return self._serve_file(STATIC_DIR / "index.html")
        if path == "/api/state":
            return self._json_response(project_state(self.store))
        if path.startswith("/static/"):
            rel = path.removeprefix("/static/")
            return self._serve_file(STATIC_DIR / rel)
        self.send_error(404)

    def do_POST(self) -> None:  # noqa: N802
        path = urlparse(self.path).path
        body = self._read_json_body()
        try:
            if path == "/api/plan":
                if body.get("generate"):
                    event = save_plan(self.store, self.config, use_openai=True)
                else:
                    event = save_plan(
                        self.store,
                        self.config,
                        manual_text=str(body.get("manual_text", "")),
                    )
                return self._json_response({"ok": True, "event": event})
            if path == "/api/worker/start":
                event = start_work(self.store)
                return self._json_response({"ok": True, "event": event})
            if path == "/api/worker/done":
                event = declare_done(
                    self.store,
                    summary=str(body.get("summary", "")),
                    files_touched=str(body.get("files_touched", "")),
                )
                return self._json_response({"ok": True, "event": event})
            if path == "/api/observer/capture":
                result = capture_evidence(
                    self.store,
                    self.config,
                    test_command=str(body.get("test_command", "") or ""),
                )
                return self._json_response({"ok": True, "evidence": result})
            if path == "/api/reviewer/review":
                if body.get("generate"):
                    event = record_review(self.store, self.config, use_openai=True)
                else:
                    event = record_review(
                        self.store,
                        self.config,
                        decision=body.get("decision"),
                        reason=str(body.get("reason", "")),
                    )
                return self._json_response({"ok": True, "event": event})
            self.send_error(404)
        except Exception as error:  # noqa: BLE001 — HTTP surface
            self._json_response({"ok": False, "error": str(error)}, status=400)

    def _read_json_body(self) -> dict[str, Any]:
        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length) if length else b"{}"
        try:
            data = json.loads(raw.decode("utf-8") or "{}")
        except json.JSONDecodeError:
            data = {}
        return data if isinstance(data, dict) else {}

    def _serve_file(self, path: Path) -> None:
        if not path.is_file():
            self.send_error(404)
            return
        content = path.read_bytes()
        mime, _ = mimetypes.guess_type(str(path))
        self.send_response(200)
        self.send_header("Content-Type", mime or "application/octet-stream")
        self.send_header("Content-Length", str(len(content)))
        self.end_headers()
        self.wfile.write(content)

    def _json_response(self, payload: dict[str, Any], status: int = 200) -> None:
        body = json.dumps(payload, indent=2).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


def serve(config: EngineeringConfig | None = None) -> None:
    cfg = config or load_config()
    store = EventStore(cfg.state_dir)
    seed_state(store, cfg)

    handler = type(
        "BoundEngineeringHandler",
        (EngineeringHandler,),
        {"config": cfg, "store": store},
    )

    server = ThreadingHTTPServer(("127.0.0.1", cfg.port), handler)
    print(f"ARK Engineering v0.1 - http://127.0.0.1:{cfg.port}")
    print("Goal -> Task -> Plan -> Worker -> Observer -> Reviewer")
    print("Press Ctrl+C to stop.")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
