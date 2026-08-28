import type { IncomingMessage } from "node:http";

export function extractBearerToken(request: IncomingMessage): string | null {
  const header = request.headers.authorization;
  if (!header || typeof header !== "string") {
    return null;
  }

  const match = /^Bearer\s+(.+)$/i.exec(header.trim());
  return match?.[1]?.trim() ?? null;
}

export function constantTimeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) {
    return false;
  }

  let mismatch = 0;
  for (let i = 0; i < a.length; i += 1) {
    mismatch |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return mismatch === 0;
}

export function authorizeRequest(
  request: IncomingMessage,
  expectedSecret: string
): boolean {
  const token = extractBearerToken(request);
  if (!token) {
    return false;
  }
  return constantTimeEqual(token, expectedSecret);
}
