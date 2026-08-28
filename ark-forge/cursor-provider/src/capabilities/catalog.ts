export const PROVIDER_ID = "cursor";
export const PROVIDER_VERSION = "0.1.0";
export const PROVIDER_NAMESPACE = "cursor.*";
export const BRIDGE_PROTOCOL = 1;
export const MANIFEST_VERSION = 1;
export const DEFAULT_LISTEN_HOST = "127.0.0.1";
export const DEFAULT_LISTEN_PORT = 9472;

export interface ProviderManifestEntry {
  id: typeof PROVIDER_ID;
  version: string;
  manifest_version: number;
  namespace: typeof PROVIDER_NAMESPACE;
  healthy: boolean;
}

export interface ProviderManifest {
  provider: ProviderManifestEntry;
  capabilities: CapabilityIdentity[];
}

export type CapabilityMode = "observe" | "invoke";

export interface RegisteredCapability {
  id: string;
  mode: CapabilityMode;
}

export interface CapabilityIdentity extends RegisteredCapability {
  display_name: string;
  description: string;
  domain: string;
  resource: string;
  operation: string;
  version: number;
  stability: "stable" | "experimental" | "deprecated";
  permissions: string[];
  arguments: [];
  result: { type: string; description: string };
  available: boolean;
  provider: typeof PROVIDER_ID;
}

export const OBSERVE_CAPABILITY_IDS = [
  "cursor.workspace.folders.read",
  "cursor.editor.active.read",
  "cursor.editor.selection.read",
] as const;

export type ObserveCapabilityId = (typeof OBSERVE_CAPABILITY_IDS)[number];

export function registeredCapabilities(): RegisteredCapability[] {
  return OBSERVE_CAPABILITY_IDS.map((id) => ({
    id,
    mode: "observe" as const,
  }));
}

function observeCapability(
  id: ObserveCapabilityId,
  displayName: string,
  description: string,
  domain: string,
  resource: string,
  operation: string,
  resultDescription: string
): CapabilityIdentity {
  return {
    id,
    mode: "observe",
    display_name: displayName,
    description,
    domain,
    resource,
    operation,
    version: 1,
    stability: "experimental",
    permissions: [],
    arguments: [],
    result: {
      type: "object",
      description: resultDescription,
    },
    available: true,
    provider: PROVIDER_ID,
  };
}

export function capabilityCatalog(): CapabilityIdentity[] {
  return [
    observeCapability(
      "cursor.workspace.folders.read",
      "Workspace Folders",
      "Read workspace folder roots visible to Cursor.",
      "workspace",
      "folders",
      "read",
      "Workspace folder names and absolute paths."
    ),
    observeCapability(
      "cursor.editor.active.read",
      "Active Editor",
      "Read the currently focused editor file and language.",
      "editor",
      "active",
      "read",
      "Active file path and language id."
    ),
    observeCapability(
      "cursor.editor.selection.read",
      "Editor Selection",
      "Read the current text selection in the active editor.",
      "editor",
      "selection",
      "read",
      "Selected text and 1-based line range in the active file."
    ),
  ];
}

export function isObserveCapabilityId(
  value: string
): value is ObserveCapabilityId {
  return (OBSERVE_CAPABILITY_IDS as readonly string[]).includes(value);
}

export function buildProviderManifest(healthy = true): ProviderManifest {
  return {
    provider: {
      id: PROVIDER_ID,
      version: PROVIDER_VERSION,
      manifest_version: MANIFEST_VERSION,
      namespace: PROVIDER_NAMESPACE,
      healthy,
    },
    capabilities: capabilityCatalog(),
  };
}
