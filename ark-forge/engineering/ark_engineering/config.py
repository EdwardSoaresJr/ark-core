from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

ENGINEERING_ROOT = Path(__file__).resolve().parent.parent
CONSOLE_ROOT = ENGINEERING_ROOT.parent / "console"
STATE_DIR = ENGINEERING_ROOT / "state"
DOCS_ROOT = ENGINEERING_ROOT.parent.parent / "docs" / "engineering"


@dataclass(frozen=True)
class EngineeringConfig:
    state_dir: Path
    bridge_url: str
    bridge_token: str
    openai_api_key: str | None
    openai_model: str
    default_repo: str
    port: int


def load_config(state_dir: Path | None = None) -> EngineeringConfig:
    load_dotenv(ENGINEERING_ROOT / ".env", override=False)
    load_dotenv(ENGINEERING_ROOT / "local.env", override=False)
    load_dotenv(CONSOLE_ROOT / ".env", override=False)
    load_dotenv(CONSOLE_ROOT / "local.env", override=False)

    api_key = os.getenv("OPENAI_API_KEY", "").strip() or None
    bridge_token = os.getenv("ARK_BRIDGE_TOKEN", "").strip()
    bridge_url = os.getenv("ARK_BRIDGE_URL", "http://127.0.0.1:9471").strip().rstrip("/")
    model = os.getenv("OPENAI_MODEL", "gpt-5.5").strip()
    repo = os.getenv(
        "ARK_REPO",
        str(ENGINEERING_ROOT.parent.parent),
    ).strip()
    port = int(os.getenv("ENGINEERING_PORT", "19472"))

    return EngineeringConfig(
        state_dir=state_dir or STATE_DIR,
        bridge_url=bridge_url,
        bridge_token=bridge_token,
        openai_api_key=api_key,
        openai_model=model,
        default_repo=repo,
        port=port,
    )


def load_bridge_config(config: EngineeringConfig) -> tuple[str, str]:
    if not config.bridge_token:
        raise RuntimeError(
            "Missing ARK_BRIDGE_TOKEN. Copy engineering.env.example to .env "
            "or reuse ark-forge/console/.env — run `ark-bridge pair` for token."
        )
    return config.bridge_url, config.bridge_token
