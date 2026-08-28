import type { BridgeConfig } from "./bridge-config";
import { PROVIDER_ID } from "./capabilities/catalog";

const DEFAULT_BRIDGE_HOST = "127.0.0.1";
const DEFAULT_BRIDGE_PORT = 9471;
const REGISTRATION_INTERVAL_MS = 30_000;

export function bridgeBaseUrl(config: BridgeConfig): string {
  const host = config.listen_host ?? DEFAULT_BRIDGE_HOST;
  const port = config.listen_port ?? DEFAULT_BRIDGE_PORT;
  return `http://${host}:${port}`;
}

function bridgeHeaders(bridgeConfig: BridgeConfig): Record<string, string> {
  return {
    Authorization: `Bearer ${bridgeConfig.bridge_secret}`,
    "Content-Type": "application/json",
  };
}

export async function isRegisteredWithBridge(
  bridgeConfig: BridgeConfig
): Promise<boolean> {
  const url = `${bridgeBaseUrl(bridgeConfig)}/bridge/providers`;
  const response = await fetch(url, {
    headers: { Authorization: `Bearer ${bridgeConfig.bridge_secret}` },
  });

  if (!response.ok) {
    return false;
  }

  const providers = (await response.json()) as Array<{
    id: string;
    healthy?: boolean;
    registered?: boolean;
  }>;

  return providers.some(
    (provider) =>
      provider.id === PROVIDER_ID &&
      provider.registered !== false &&
      provider.healthy === true
  );
}

export async function registerWithBridge(
  bridgeConfig: BridgeConfig,
  providerBaseUrl: string
): Promise<void> {
  const url = `${bridgeBaseUrl(bridgeConfig)}/bridge/providers/register`;
  const response = await fetch(url, {
    method: "POST",
    headers: bridgeHeaders(bridgeConfig),
    body: JSON.stringify({
      id: PROVIDER_ID,
      base_url: providerBaseUrl,
    }),
  });

  if (!response.ok) {
    const body = await response.text();
    throw new Error(`Bridge registration failed (${response.status}): ${body}`);
  }
}

export async function ensureBridgeRegistration(
  bridgeConfig: BridgeConfig,
  providerBaseUrl: string
): Promise<"registered" | "already_connected" | "bridge_unavailable"> {
  try {
    const connected = await isRegisteredWithBridge(bridgeConfig);
    if (connected) {
      return "already_connected";
    }
  } catch {
    return "bridge_unavailable";
  }

  try {
    await registerWithBridge(bridgeConfig, providerBaseUrl);
    return "registered";
  } catch {
    return "bridge_unavailable";
  }
}

export function startBridgeRegistrationLoop(
  bridgeConfig: BridgeConfig,
  providerBaseUrl: string,
  onStatus: (message: string) => void
): NodeJS.Timeout {
  const tick = () => {
    void ensureBridgeRegistration(bridgeConfig, providerBaseUrl).then((status) => {
      if (status === "registered") {
        onStatus("Registered with ARK Bridge.");
      } else if (status === "bridge_unavailable") {
        onStatus("ARK Bridge unavailable — will retry registration.");
      }
    });
  };

  tick();
  return setInterval(tick, REGISTRATION_INTERVAL_MS);
}
