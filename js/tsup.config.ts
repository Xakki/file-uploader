import { defineConfig } from 'tsup';

export default defineConfig([
  // npm consumers: tree-shakeable ESM + CJS + types, per subpath entry.
  {
    entry: {
      'core/index': 'src/core/index.ts',
      'widget/index': 'src/widget/index.ts',
    },
    format: ['esm', 'cjs'],
    dts: true,
    sourcemap: true,
    clean: true,
    treeshake: true,
  },
  // <script> consumers (no bundler): one minified UMD bundling core + widget,
  // exposing window.FileUploaderWidget and auto-mounting from window.FileUploadConfig.
  {
    entry: { 'file-uploader.umd': 'src/widget/umd.ts' },
    format: ['iife'],
    globalName: 'FileUploaderWidget',
    minify: true,
    sourcemap: true,
  },
]);
