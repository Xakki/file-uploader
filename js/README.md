# @xakki/file-uploader

Headless, backend-agnostic **chunked file upload client** speaking
**[Upload Protocol v1](../protocol/SPEC.md)**, plus an optional DOM widget. Tree-shakeable
ESM/CJS with a pre-built UMD bundle for `<script>` use.

```bash
npm i @xakki/file-uploader
```

Two independent entry points — import only what you need:

| Import | What | DOM? |
|---|---|---|
| `@xakki/file-uploader/core` | headless upload engine | no |
| `@xakki/file-uploader/widget` | Drag & Drop widget + file list | yes |

`.` re-exports `./core`.

## Core

```ts
import { uploadFile } from '@xakki/file-uploader/core';

const metadata = await uploadFile(file, {
  url: '/file-upload/chunks',
  chunkSize: 1024 * 1024,           // default 1 MiB
  credentials: 'same-origin',
  computeFileHash: true,            // send whole-file sha256 for server dedup/verify
  onProgress: (p) => console.log(Math.round(p.overall * 100), '%'),
});
```

`uploadFile(blob, options, transport?)` splits the blob into chunks, POSTs each as
`multipart/form-data`, and resolves with the final file metadata. It **retries network errors and
5xx only — never 4xx** (a rejected chunk fails fast). Pass an `AbortSignal` via `options.signal` to
cancel.

### Any backend

The client is not tied to this project's server. Point it anywhere and teach it how to read the
response:

```ts
const summaryId = await uploadFile<string>(file, {
  url: '/api/v1/summaries/upload',
  fieldNames: { fileChunk: 'file' },                 // rename protocol fields
  extraFields: { summaryId: '42' },                  // extra multipart fields
  isCompleted: (json) => json.data?.done === true,   // your "finished" predicate
  extractResult: (json) => json.data.summaryUuid,    // pull out your result
});
```

Other hooks: `headers` (static or factory), `formDataBuilder` (full body control), `uploadId`
(supply your own — must match `^upload-\d{13}-[a-z0-9]{8}$`), `maxRetries` / `retryDelay…`, and a
pluggable `Transport` (defaults to XHR so progress works; swap for tests or `fetch`).

Also exported: `generateUploadId()`, `wholeFileSha256(blob)`, `xhrTransport`, `UploadError`.

## Widget

Two built-in **templates** over one shared controller — pick with `template` (or use the sugar
functions). Both render the upload UI **and** the already-uploaded file list (loaded on mount).

```ts
import { createWidget, createForm, createUploader } from '@xakki/file-uploader/widget';

// floating button + modal (default), bottom-right of the page
createWidget({
  endpointBase: '/file-upload',
  routes: { upload: '/file-upload/chunks', list: '/file-upload/files', /* … */ },
  chunkSize: 1024 * 1024,
  allowList: true,
  allowDelete: true,
  theme: 'auto',                 // 'light' | 'dark' | 'auto' (follows prefers-color-scheme)
});

// inline panel rendered into a container — same logic, list visible by default
createForm({ container: '#uploader', endpointBase: '/file-upload', theme: 'dark' });

// or choose explicitly: createUploader({ template: 'widget' | 'form', ... })
```

`createWidget`/`createForm`/`createUploader` return a `WidgetInstance`:
`{ root, refresh(), destroy(), setTheme('light' | 'dark' | 'auto') }`. `setTheme` swaps the root's
`fu-theme-light`/`fu-theme-dark` class at runtime (handy for a page-level theme toggle).

### Theming & i18n

Styling is driven by CSS custom properties with a light default and a `.fu-theme-dark` override —
override the variables to restyle. Locales: `en` (default), `ru`, `es`, `pt`, `zh`, `fr`, `de`, `sr`
via `locale`; extend or override any string with `i18n`.

### No build step (UMD)

The PHP bindings render a bootstrap that sets `window.FileUploadConfig` and loads the pre-built UMD
bundle, which auto-mounts. To use it standalone:

```html
<div id="file-upload-widget"></div>
<script>window.FileUploadConfig = { endpointBase: '/file-upload', template: 'widget', routes: { /* … */ } };</script>
<script src="https://unpkg.com/@xakki/file-uploader/dist/file-uploader.umd.global.js" defer></script>
```

The UMD exposes `window.FileUploaderWidget` with `{ createWidget, createForm, createUploader,
mountFromGlobalConfig }`; auto-mount honors `window.FileUploadConfig.template`. `mountFromGlobalConfig()`
is also exported for manual mounting.

## Build & test

```bash
npm run build       # tsup → dist/ (ESM + CJS + UMD + d.ts)
npm test            # vitest
npm run typecheck   # tsc --noEmit
npm run vendor:php  # build + copy the UMD widget into the PHP bindings' assets
```

The core test suite runs the shared [`protocol/fixtures`](../protocol/fixtures) so the client stays
conformant with the PHP implementations.
