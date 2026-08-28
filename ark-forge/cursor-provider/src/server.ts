import * as http from "node:http";

import { authorizeRequest } from "./auth";
import {
  BRIDGE_PROTOCOL,
  buildProviderManifest,
  capabilityCatalog,
  DEFAULT_LISTEN_HOST,
  DEFAULT_LISTEN_PORT,
  PROVIDER_ID,
  PROVIDER_VERSION,
  registeredCapabilities,
} from "./capabilities/catalog";
import { observeById } from "./capabilities/observe";

export interface ProviderServerOptions {
  bridgeSecret: string;
  host?: string;
  port?: number;
}

export interface ProviderServer {
  baseUrl: string;
  close: () => Promise<void>;
}

interface ObserveRequestBody {
  capability?: string;
  arguments?: Record<string, unknown>;
}

export function startProviderServer(
  options: ProviderServerOptions
): Promise<ProviderServer> {
  const host = options.host ?? DEFAULT_LISTEN_HOST;
  const port = options.port ?? DEFAULT_LISTEN_PORT;

  const server = http.createServer(async (request, response) => {
    try {
      await handleRequest(request, response, options.bridgeSecret);
    } catch (error) {
      sendJson(response, 500, {
        ok: false,
        error: error instanceof Error ? error.message : "internal error",
      });
    }
  });

  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, host, () => {
      const baseUrl = `http://${host}:${port}`;
      resolve({
        baseUrl,
        close: () =>
          new Promise<void>((closeResolve, closeReject) => {
            server.close((error) => {
              if (error) {
                closeReject(error);
                return;
              }
              closeResolve();
            });
          }),
      });
    });
  });
}

async function handleRequest(
  request: http.IncomingMessage,
  response: http.ServerResponse,
  bridgeSecret: string
): Promise<void> {
  const method = request.method ?? "GET";
  const url = new URL(request.url ?? "/", "http://127.0.0.1");

  if (method === "GET" && url.pathname === "/health") {
    sendJson(response, 200, {
      healthy: true,
      provider_version: PROVIDER_VERSION,
      bridge_protocol: BRIDGE_PROTOCOL,
    });
    return;
  }

  if (!authorizeRequest(request, bridgeSecret)) {
    sendJson(response, 401, { ok: false, error: "unauthorized" });
    return;
  }

  if (method === "GET" && url.pathname === "/manifest") {
    sendJson(response, 200, buildProviderManifest(true));
    return;
  }

  if (method === "GET" && url.pathname === "/capabilities") {
    sendJson(response, 200, {
      id: PROVIDER_ID,
      version: PROVIDER_VERSION,
      namespace: "cursor.*",
      capabilities: capabilityCatalog(),
      registered: registeredCapabilities(),
    });
    return;
  }

  if (
    (method === "POST" && url.pathname === "/observe") ||
    (method === "GET" && url.pathname === "/observe")
  ) {
    const body =
      method === "POST" ? await readJsonBody<ObserveRequestBody>(request) : {};
    const capability =
      body.capability ?? url.searchParams.get("capability") ?? undefined;

    if (!capability) {
      sendJson(response, 400, {
        ok: false,
        error: "capability is required",
      });
      return;
    }

    const observeResponse = await observeById(capability);
    const status = observeResponse.ok ? 200 : 404;
    sendJson(response, status, observeResponse);
    return;
  }

  sendJson(response, 404, { ok: false, error: "not found" });
}

function readJsonBody<T>(request: http.IncomingMessage): Promise<T> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = [];

    request.on("data", (chunk: Buffer) => {
      chunks.push(chunk);
    });

    request.on("end", () => {
      if (chunks.length === 0) {
        resolve({} as T);
        return;
      }

      try {
        const raw = Buffer.concat(chunks).toString("utf8");
        resolve(JSON.parse(raw) as T);
      } catch (error) {
        reject(error);
      }
    });

    request.on("error", reject);
  });
}

function sendJson(
  response: http.ServerResponse,
  status: number,
  body: unknown
): void {
  const payload = JSON.stringify(body);
  response.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Content-Length": Buffer.byteLength(payload),
  });
  response.end(payload);
}
