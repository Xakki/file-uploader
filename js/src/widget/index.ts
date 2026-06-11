import { createWidget } from './widget';
import type { WidgetConfig, WidgetInstance } from './types';

export { createWidget } from './widget';
export type { WidgetConfig, WidgetInstance, WidgetRoutes, FileRow } from './types';
export type { Strings } from './strings';

/**
 * Mount the widget from a global config object (window.FileUploadConfig), as
 * injected by the server-rendered bootstrap. Returns null if no config is present.
 */
export function mountFromGlobalConfig(): WidgetInstance | null {
  const cfg = (globalThis as { FileUploadConfig?: WidgetConfig }).FileUploadConfig;

  return cfg ? createWidget(cfg) : null;
}
