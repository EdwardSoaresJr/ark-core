from __future__ import annotations

import re
from typing import Any

from .config import ENABLED_TOOLS_V01

# OpenAI function names: letters, numbers, underscores, hyphens
_TOOL_NAME_PATTERN = re.compile(r"[^a-zA-Z0-9_-]+")


def capability_id_to_tool_name(capability_id: str) -> str:
    return _TOOL_NAME_PATTERN.sub("_", capability_id)


def tool_name_to_capability_id(tool_name: str, enabled_ids: tuple[str, ...]) -> str | None:
    for capability_id in enabled_ids:
        if capability_id_to_tool_name(capability_id) == tool_name:
            return capability_id
    return None


def _json_schema_type(arg_type: str) -> str:
    if arg_type in {"integer", "number", "boolean"}:
        return arg_type
    return "string"


def capability_to_openai_tool(capability: dict[str, Any]) -> dict[str, Any]:
    cap_id = capability["id"]
    properties: dict[str, Any] = {}
    required: list[str] = []

    for arg in capability.get("arguments") or []:
        name = arg["name"]
        properties[name] = {
            "type": _json_schema_type(arg.get("type", "string")),
            "description": arg.get("description") or f"{name} argument",
        }
        if arg.get("enum_values"):
            properties[name]["enum"] = arg["enum_values"]
        if arg.get("required"):
            required.append(name)

    description = capability.get("description") or capability.get("display_name") or cap_id
    schema: dict[str, Any] = {
        "type": "object",
        "properties": properties,
        "additionalProperties": False,
    }
    if required:
        schema["required"] = required

    return {
        "type": "function",
        "name": capability_id_to_tool_name(cap_id),
        "description": description,
        "parameters": schema,
    }


def extract_capabilities(state: dict[str, Any]) -> list[dict[str, Any]]:
    """Bridge state returns a merged capability array (PR-006 provider router)."""
    capabilities = state.get("capabilities")
    if isinstance(capabilities, list):
        return capabilities
    if isinstance(capabilities, dict):
        nested = capabilities.get("capabilities")
        if isinstance(nested, list):
            return nested
    return []


def build_tools_from_bridge_state(
    state: dict[str, Any],
    enabled_ids: tuple[str, ...] = ENABLED_TOOLS_V01,
) -> list[dict[str, Any]]:
    capabilities = extract_capabilities(state)
    enabled = set(enabled_ids)
    tools: list[dict[str, Any]] = []

    for capability in capabilities:
        cap_id = capability.get("id")
        if cap_id not in enabled:
            continue
        if capability.get("available") is False:
            continue
        tools.append(capability_to_openai_tool(capability))

    if not tools:
        raise RuntimeError(
            "No enabled tools available from Bridge state. "
            f"Expected at least: {', '.join(enabled_ids)}"
        )

    return tools


def normalize_bridge_arguments(capability_id: str, arguments: dict[str, Any]) -> dict[str, Any]:
    """Forge invoke body uses `path`; registry may label it repo_path."""
    normalized = dict(arguments or {})
    for key in list(normalized.keys()):
        if normalized[key] in ("", None):
            normalized.pop(key)
    if capability_id == "forge.git.status.read":
        if "repo_path" in normalized and "path" not in normalized:
            normalized["path"] = normalized.pop("repo_path")
    return normalized
