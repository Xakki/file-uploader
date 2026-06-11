// @vitest-environment happy-dom
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it, vi } from 'vitest';

/**
 * Guards the UMD global *shape*: tsup builds `umd.ts` as an IIFE with globalName
 * `FileUploaderWidget`, so esbuild assigns the module exports to the global. The
 * api methods must be own properties of `window.FileUploaderWidget` — a regression
 * to `export default api` (only) nests them under `.default`, breaking
 * `FileUploaderWidget.createWidget(...)` for `<script>` consumers (the demo landing).
 *
 * Skipped when dist is absent (clean checkout before `npm run build`); the build
 * step in `make test-js` produces it. Path is cwd-relative — vitest runs from `js/`.
 */
const umdPath = resolve(process.cwd(), 'dist/file-uploader.umd.global.js');

describe.runIf(existsSync(umdPath))('built UMD global (FileUploaderWidget)', () => {
  it('exposes api methods directly on the global', () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ data: { files: [] } }) })),
    );
    delete (globalThis as { FileUploadConfig?: unknown }).FileUploadConfig;

    const code = readFileSync(umdPath, 'utf8');
    // Capture the IIFE's assigned value via a trailing expression — reliable across
    // realms (reading globalThis after an indirect eval is flaky under vitest).
    const api = (0, eval)(`${code}\n;FileUploaderWidget`) as Record<string, unknown>;

    expect(typeof api?.createWidget).toBe('function');
    expect(typeof api?.createForm).toBe('function');
    expect(typeof api?.createUploader).toBe('function');
    expect(typeof api?.mountFromGlobalConfig).toBe('function');

    vi.unstubAllGlobals();
  });
});
