from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

CONSOLE_ROOT = Path(__file__).resolve().parent.parent
ENABLED_TOOLS_V01 = ("forge.git.status.read",)


@dataclass(frozen=True)
class ConsoleConfig:
    openai_api_key: str
    openai_model: str
    bridge_url: str
    bridge_token: str


def load_config() -> ConsoleConfig:
    load_dotenv(CONSOLE_ROOT / ".env", override=False)
    load_dotenv(CONSOLE_ROOT / "local.env", override=False)

    api_key = os.getenv("OPENAI_API_KEY", "").strip()
    bridge_token = os.getenv("ARK_BRIDGE_TOKEN", "").strip()
    bridge_url = os.getenv("ARK_BRIDGE_URL", "http://127.0.0.1:9471").strip().rstrip("/")
    model = os.getenv("OPENAI_MODEL", "gpt-5.5").strip()

    missing: list[str] = []
    if not api_key:
        missing.append("OPENAI_API_KEY")
    if not bridge_token:
        missing.append("ARK_BRIDGE_TOKEN")

    if missing:
        hint = CONSOLE_ROOT / "console.env.example"
        raise SystemExit(
            "Missing local config: "
            + ", ".join(missing)
            + f"\nCopy {hint.name} to .env in ark-forge/console/ and fill in values.\n"
            "Bridge token: run `ark-bridge pair` (or tray → Pair) on this machine."
        )

    return ConsoleConfig(
        openai_api_key=api_key,
        openai_model=model,
        bridge_url=bridge_url,
        bridge_token=bridge_token,
    )


def load_bridge_only_config() -> tuple[str, str]:
    """Bridge connectivity check without OpenAI key."""
    load_dotenv(CONSOLE_ROOT / ".env", override=False)
    load_dotenv(CONSOLE_ROOT / "local.env", override=False)

    bridge_token = os.getenv("ARK_BRIDGE_TOKEN", "").strip()
    bridge_url = os.getenv("ARK_BRIDGE_URL", "http://127.0.0.1:9471").strip().rstrip("/")
    if not bridge_token:
        raise SystemExit(
            "Missing ARK_BRIDGE_TOKEN in ark-forge/console/.env\n"
            "Run `ark-bridge pair` and paste the bearer token."
        )
    return bridge_url, bridge_token
