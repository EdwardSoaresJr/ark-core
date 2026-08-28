from __future__ import annotations

import json
import sys
from typing import Any

from openai import OpenAI

from .bridge import BridgeClient
from .config import ConsoleConfig, ENABLED_TOOLS_V01, load_config
from .dispatcher import ToolDispatcher, format_tool_output
from .tools import build_tools_from_bridge_state

SYSTEM_INSTRUCTIONS = (
    "You are ARK Console, a local engineering assistant connected to ARK Bridge on this "
    "workstation. Use tools for live workstation facts. Do not invent git status or repo "
    "state — call the tool when the user asks about git."
)


def run_ask(config: ConsoleConfig, question: str) -> str:
    bridge = BridgeClient(config.bridge_url, config.bridge_token)
    dispatcher = ToolDispatcher(bridge)
    tools = build_tools_from_bridge_state(bridge.fetch_state(), ENABLED_TOOLS_V01)

    client = OpenAI(api_key=config.openai_api_key)

    response = client.responses.create(
        model=config.openai_model,
        instructions=SYSTEM_INSTRUCTIONS,
        input=question,
        tools=tools,
    )

    while True:
        function_calls = [item for item in response.output if item.type == "function_call"]
        if not function_calls:
            return response.output_text or ""

        tool_outputs: list[dict[str, Any]] = []
        for call in function_calls:
            args = json.loads(call.arguments or "{}")
            result = dispatcher.dispatch(call.name, args)
            tool_outputs.append(
                {
                    "type": "function_call_output",
                    "call_id": call.call_id,
                    "output": format_tool_output(result),
                }
            )

        response = client.responses.create(
            model=config.openai_model,
            previous_response_id=response.id,
            input=tool_outputs,
            tools=tools,
        )


def run_repl(config: ConsoleConfig) -> None:
    bridge = BridgeClient(config.bridge_url, config.bridge_token)
    bridge.fetch_state()
    print("ARK Console v0.1 — connected to ARK Bridge")
    print(f"Model: {config.openai_model}")
    print(f"Tools: {', '.join(ENABLED_TOOLS_V01)}")
    print("Type a question, or `exit` to quit.\n")

    while True:
        try:
            question = input("you> ").strip()
        except (EOFError, KeyboardInterrupt):
            print()
            break

        if not question:
            continue
        if question.lower() in {"exit", "quit", "q"}:
            break

        try:
            answer = run_ask(config, question)
            print(f"\nassistant> {answer}\n")
        except Exception as error:  # noqa: BLE001 — top-level CLI surface
            print(f"\nerror> {error}\n", file=sys.stderr)


def main(argv: list[str] | None = None) -> int:
    args = list(sys.argv[1:] if argv is None else argv)

    if not args:
        config = load_config()
        run_repl(config)
        return 0

    command = args[0]

    if command == "ask":
        if len(args) < 2:
            print("Usage: python -m ark_console ask \"What's my git status?\"", file=sys.stderr)
            return 1
        question = " ".join(args[1:])
        config = load_config()
        answer = run_ask(config, question)
        print(answer)
        return 0

    if command == "test-bridge":
        from .config import load_bridge_only_config

        bridge_url, bridge_token = load_bridge_only_config()
        bridge = BridgeClient(bridge_url, bridge_token)
        state = bridge.fetch_state()
        healthy = state.get("healthy")
        print(f"Bridge: {bridge_url}")
        print(f"Healthy: {healthy}")
        result = bridge.observe("forge.git.status.read", {})
        print(json.dumps(result, indent=2))
        return 0

    print("Usage:", file=sys.stderr)
    print("  python -m ark_console", file=sys.stderr)
    print("  python -m ark_console ask \"What's my git status?\"", file=sys.stderr)
    print("  python -m ark_console test-bridge", file=sys.stderr)
    return 1
