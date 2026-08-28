from __future__ import annotations

import json
from typing import Any

import httpx

CLIENT_NAME = "ARK Console"
CLIENT_VERSION = "0.1"


class BridgeError(RuntimeError):
    pass


class BridgeClient:
    def __init__(self, base_url: str, token: str) -> None:
        self.base_url = base_url.rstrip("/")
        self._headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
            "X-ARK-Client": CLIENT_NAME,
            "X-ARK-Client-Version": CLIENT_VERSION,
        }

    def fetch_state(self) -> dict[str, Any]:
        return self._get("/bridge/state")

    def observe(self, capability: str, arguments: dict[str, Any] | None = None) -> dict[str, Any]:
        payload = {
            "capability": capability,
            "arguments": arguments or {},
        }
        return self._post("/bridge/observe", payload)

    def invoke(self, capability: str, arguments: dict[str, Any] | None = None) -> dict[str, Any]:
        payload = {
            "capability": capability,
            "arguments": arguments or {},
        }
        return self._post("/bridge/invoke", payload)

    def ping(self) -> dict[str, Any]:
        return self._get("/bridge/ping")

    def _get(self, path: str) -> dict[str, Any]:
        with httpx.Client(timeout=30.0) as client:
            response = client.get(f"{self.base_url}{path}", headers=self._headers)
        return self._decode(response)

    def _post(self, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        with httpx.Client(timeout=60.0) as client:
            response = client.post(
                f"{self.base_url}{path}",
                headers=self._headers,
                content=json.dumps(payload),
            )
        return self._decode(response)

    def _decode(self, response: httpx.Response) -> dict[str, Any]:
        if response.status_code == 401:
            raise BridgeError("ARK Bridge rejected the bearer token (401). Run `ark-bridge pair`.")
        if response.status_code >= 400:
            raise BridgeError(f"Bridge HTTP {response.status_code}: {response.text}")
        data = response.json()
        if not isinstance(data, dict):
            raise BridgeError("Bridge returned non-object JSON")
        return data
