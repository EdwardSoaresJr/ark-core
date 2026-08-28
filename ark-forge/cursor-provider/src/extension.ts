import * as vscode from "vscode";

import { loadBridgeConfig } from "./bridge-config";
import {
  ensureBridgeRegistration,
  startBridgeRegistrationLoop,
} from "./bridge-register";
import {
  DEFAULT_LISTEN_HOST,
  DEFAULT_LISTEN_PORT,
  PROVIDER_ID,
  PROVIDER_VERSION,
} from "./capabilities/catalog";
import { type ProviderServer, startProviderServer } from "./server";

const OUTPUT_CHANNEL = "ARK Cursor Runtime Provider";

let server: ProviderServer | null = null;
let registrationTimer: NodeJS.Timeout | null = null;

export async function activate(context: vscode.ExtensionContext): Promise<void> {
  const output = vscode.window.createOutputChannel(OUTPUT_CHANNEL);
  context.subscriptions.push(output);

  const bridgeConfig = loadBridgeConfig();
  if (!bridgeConfig) {
    output.appendLine(
      "ARK Bridge config not found. Run ARK Bridge once to create %USERPROFILE%\\.ark\\bridge.json"
    );
    void vscode.window.showWarningMessage(
      "ARK Cursor Runtime Provider: bridge.json not found — provider server not started."
    );
    return;
  }

  const host =
    process.env.ARK_CURSOR_PROVIDER_HOST ?? DEFAULT_LISTEN_HOST;
  const port = Number.parseInt(
    process.env.ARK_CURSOR_PROVIDER_PORT ?? String(DEFAULT_LISTEN_PORT),
    10
  );

  try {
    server = await startProviderServer({
      bridgeSecret: bridgeConfig.bridge_secret,
      host,
      port,
    });
  } catch (error) {
    const message =
      error instanceof Error ? error.message : "failed to start provider server";
    output.appendLine(`Failed to start provider server: ${message}`);
    void vscode.window.showErrorMessage(
      `ARK Cursor Runtime Provider: could not bind ${host}:${port}`
    );
    return;
  }

  output.appendLine(
    `${PROVIDER_ID} v${PROVIDER_VERSION} listening at ${server.baseUrl}`
  );

  const registrationStatus = await ensureBridgeRegistration(
    bridgeConfig,
    server.baseUrl
  );

  if (registrationStatus === "registered") {
    output.appendLine("Registered with ARK Bridge.");
  } else if (registrationStatus === "already_connected") {
    output.appendLine("Already connected to ARK Bridge.");
  } else {
    output.appendLine("ARK Bridge unavailable — will retry registration every 30s.");
  }

  registrationTimer = startBridgeRegistrationLoop(
    bridgeConfig,
    server.baseUrl,
    (message) => output.appendLine(message)
  );

  context.subscriptions.push(
    vscode.window.onDidChangeWindowState((state) => {
      if (state.focused) {
        void ensureBridgeRegistration(bridgeConfig, server!.baseUrl).then(
          (status) => {
            if (status === "registered") {
              output.appendLine("Re-registered with ARK Bridge after focus.");
            }
          }
        );
      }
    })
  );

  output.appendLine("Discovery: GET /manifest · Health: GET /health · Observe: POST /observe");

  context.subscriptions.push({
    dispose: () => {
      if (registrationTimer) {
        clearInterval(registrationTimer);
        registrationTimer = null;
      }
      void stopServer(output);
    },
  });
}

export async function deactivate(): Promise<void> {
  if (registrationTimer) {
    clearInterval(registrationTimer);
    registrationTimer = null;
  }
  await stopServer();
}

async function stopServer(
  output?: vscode.OutputChannel
): Promise<void> {
  if (!server) {
    return;
  }

  const closing = server;
  server = null;

  try {
    await closing.close();
    output?.appendLine("Provider server stopped.");
  } catch (error) {
    const message =
      error instanceof Error ? error.message : "failed to stop provider server";
    output?.appendLine(`Failed to stop provider server: ${message}`);
  }
}
