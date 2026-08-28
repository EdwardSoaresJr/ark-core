import * as vscode from "vscode";

import { isObserveCapabilityId, type ObserveCapabilityId } from "./catalog";

export interface ObserveResponse {
  ok: boolean;
  capability: string;
  result: Record<string, unknown> | null;
  error?: string;
}

export async function observeCapability(
  capabilityId: ObserveCapabilityId
): Promise<ObserveResponse> {
  const result = await dispatchObserve(capabilityId);
  return {
    ok: true,
    capability: capabilityId,
    result,
  };
}

export async function observeById(
  capabilityId: string
): Promise<ObserveResponse> {
  if (!isObserveCapabilityId(capabilityId)) {
    return {
      ok: false,
      capability: capabilityId,
      result: null,
      error: `unknown capability '${capabilityId}'`,
    };
  }

  return observeCapability(capabilityId);
}

async function dispatchObserve(
  capabilityId: ObserveCapabilityId
): Promise<Record<string, unknown>> {
  switch (capabilityId) {
    case "cursor.workspace.folders.read":
      return readWorkspaceFolders();
    case "cursor.editor.active.read":
      return readActiveEditor();
    case "cursor.editor.selection.read":
      return readEditorSelection();
    default:
      throw new Error(`unhandled capability '${capabilityId}'`);
  }
}

function readWorkspaceFolders(): Record<string, unknown> {
  const folders = (vscode.workspace.workspaceFolders ?? []).map((folder) => ({
    name: folder.name,
    path: folder.uri.fsPath,
  }));

  return { folders };
}

function readActiveEditor(): Record<string, unknown> {
  const editor = vscode.window.activeTextEditor;
  if (!editor) {
    return {
      file: null,
      language: null,
    };
  }

  return {
    file: editor.document.uri.fsPath,
    language: editor.document.languageId,
  };
}

function readEditorSelection(): Record<string, unknown> {
  const editor = vscode.window.activeTextEditor;
  if (!editor) {
    return {
      file: null,
      selection: "",
      startLine: null,
      endLine: null,
    };
  }

  const selectionText = editor.document.getText(editor.selection);
  const startLine = editor.selection.start.line + 1;
  const endLine = editor.selection.end.line + 1;

  return {
    file: editor.document.uri.fsPath,
    selection: selectionText,
    startLine,
    endLine,
  };
}
