import * as fs from "node:fs";
import * as os from "node:os";
import * as path from "node:path";

export interface BridgeConfig {
  bridge_id: string;
  bridge_secret: string;
  listen_host?: string;
  listen_port?: number;
  forge_core_url?: string;
}

export function bridgeConfigPath(): string {
  const home = process.env.USERPROFILE ?? process.env.HOME ?? os.homedir();
  return path.join(home, ".ark", "bridge.json");
}

export function loadBridgeConfig(): BridgeConfig | null {
  const configPath = bridgeConfigPath();
  if (!fs.existsSync(configPath)) {
    return null;
  }

  try {
    const raw = fs.readFileSync(configPath, "utf8");
    const parsed = JSON.parse(raw) as BridgeConfig;
    if (!parsed.bridge_secret) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}
