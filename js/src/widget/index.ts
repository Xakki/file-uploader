import { createUploader } from './widget';
import type { WidgetConfig, WidgetInstance } from './types';

export { createWidget, createForm, createUploader } from './widget';
export type {
  WidgetConfig,
  WidgetInstance,
  WidgetRoutes,
  WidgetTemplate,
  WidgetTheme,
  FileRow,
} from './types';
export type { Strings } from './strings';

/**
 * Mount an uploader from a global config object (window.FileUploadConfig), as
 * injected by the server-rendered bootstrap. Honors `template` (widget/form).
 * Returns null if no config is present.
 */
export function mountFromGlobalConfig(): WidgetInstance | null {
  const cfg = (globalThis as { FileUploadConfig?: WidgetConfig }).FileUploadConfig;

  return cfg ? createUploader(cfg) : null;
}
