from __future__ import annotations

import json
from typing import Any

from .bridge import BridgeClient, BridgeError
from .config import ENABLED_TOOLS_V01
from .tools import extract_capabilities, normalize_bridge_arguments, tool_name_to_capability_id


class ToolDispatcher:
    def __init__(self, bridge: BridgeClient) -> None:
        self.bridge = bridge
        self.enabled_ids = ENABLED_TOOLS_V01

    def dispatch(self, tool_name: str, arguments: dict[str, Any]) -> dict[str, Any]:
        capability_id = tool_name_to_capability_id(tool_name, self.enabled_ids)
        if capability_id is None:
            return {
                "ok": False,
                "error": f"Tool not enabled in ARK Console v0.1: {tool_name}",
            }

        args = normalize_bridge_arguments(capability_id, arguments)

        try:
            mode = self._capability_mode(capability_id)
            if mode == "observe":
                response = self.bridge.observe(capability_id, args)
            else:
                response = self.bridge.invoke(capability_id, args)
        except BridgeError as error:
            return {"ok": False, "error": str(error)}

        if not response.get("ok"):
            return {
                "ok": False,
                "error": response.get("error") or "Bridge capability failed",
                "capability": capability_id,
            }

        return {
            "ok": True,
            "capability": capability_id,
            "result": response.get("result"),
        }

    def _capability_mode(self, capability_id: str) -> str:
        state = self.bridge.fetch_state()
        for capability in extract_capabilities(state):
            if capability.get("id") == capability_id:
                return str(capability.get("mode") or "invoke")
        return "observe" if capability_id.endswith(".read") else "invoke"


def format_tool_output(payload: dict[str, Any]) -> str:
    return json.dumps(payload, indent=2)
