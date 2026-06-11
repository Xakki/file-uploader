/**
 * UMD/IIFE entry for no-bundler `<script>` usage. Exposes window.FileUploaderWidget
 * (and the legacy window.FileUploadWidget alias) and auto-mounts from
 * window.FileUploadConfig on load — a drop-in for the server-rendered bootstrap.
 */
import { createWidget, mountFromGlobalConfig } from './index';

const api = { init: createWidget, createWidget, mountFromGlobalConfig };

const target = globalThis as typeof globalThis & {
  FileUploaderWidget?: typeof api;
  FileUploadWidget?: typeof api;
};
target.FileUploaderWidget = api;
target.FileUploadWidget = api; // backward-compatible global name

if (typeof document !== 'undefined') {
  mountFromGlobalConfig();
}

export default api;
