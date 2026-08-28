from __future__ import annotations

import sys

from .config import load_config
from .seed import seed_state
from .server import serve
from .store import EventStore


def main(argv: list[str] | None = None) -> int:
    args = list(sys.argv[1:] if argv is None else argv)

    if not args or args[0] == "serve":
        serve()
        return 0

    if args[0] == "seed":
        config = load_config()
        store = EventStore(config.state_dir)
        force = "--force" in args
        seed_state(store, config, force=force)
        print(f"Seeded state in {config.state_dir}")
        return 0

    print("Usage:", file=sys.stderr)
    print("  python -m ark_engineering", file=sys.stderr)
    print("  python -m ark_engineering serve", file=sys.stderr)
    print("  python -m ark_engineering seed [--force]", file=sys.stderr)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
