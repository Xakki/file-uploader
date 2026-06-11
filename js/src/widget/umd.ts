/**
 * UMD/IIFE entry for no-bundler `<script>` usage.
 *
 * tsup builds this as an IIFE with globalName `FileUploaderWidget`, so esbuild
 * assigns this module's *exports* to the global. The **named** re-exports below
 * are therefore load-bearing: they become own properties of
 * `window.FileUploaderWidget` — `{ createWidget, createForm, createUploader,
 * mountFromGlobalConfig, init }`. A bare `export default api` would nest the api
 * under `window.FileUploaderWidget.default`, so a caller doing
 * `FileUploaderWidget.createWidget(...)` would get `undefined`.
 *
 * Also exposes the legacy `window.FileUploadWidget` alias and auto-mounts from
 * `window.FileUploadConfig` on load — a drop-in for the server-rendered bootstrap.
 */
import { createForm, createUploader, createWidget, mountFromGlobalConfig } from './index';

const init = createWidget;
const api = { init, createWidget, createForm, createUploader, mountFromGlobalConfig };

// esbuild owns the `FileUploaderWidget` global (globalName); we only set the
// backward-compatible alias here.
(globalThis as typeof globalThis & { FileUploadWidget?: typeof api }).FileUploadWidget = api;

if (typeof document !== 'undefined') {
  mountFromGlobalConfig();
}

export { createForm, createUploader, createWidget, mountFromGlobalConfig, init };
export default api;
