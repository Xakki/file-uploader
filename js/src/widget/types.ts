import type { Strings } from './strings';

export interface WidgetRoutes {
  upload?: string;
  list?: string;
  delete?: string;
  restore?: string;
  cleanup?: string;
}

/** Which built-in layout `createUploader` mounts. */
export type WidgetTemplate = 'widget' | 'form';

/** Colour scheme. `'auto'` follows the OS `prefers-color-scheme` and updates live. */
export type WidgetTheme = 'light' | 'dark' | 'auto';

export interface WidgetConfig {
  endpointBase?: string;
  routes?: WidgetRoutes;
  routePlaceholder?: string;
  chunkSize?: number;
  method?: string;
  allowList?: boolean;
  allowDelete?: boolean;
  allowDeleteAllFiles?: boolean;
  allowCleanup?: boolean;
  locale?: string;
  auth?: 'csrf' | 'bearer' | 'none';
  token?: string | null;
  csrfTokenField?: string;
  headers?: Record<string, string>;
  i18n?: Record<string, Partial<Strings>>;
  styles?: {
    raw?: string;
    toggle?: Partial<CSSStyleDeclaration>;
    modal?: Partial<CSSStyleDeclaration>;
    dropzone?: Partial<CSSStyleDeclaration>;
  };
  container?: string | HTMLElement | null;
  /** Layout to mount: floating `'widget'` (default) or inline `'form'`. */
  template?: WidgetTemplate;
  /** Colour scheme, default `'light'`. `'auto'` tracks the OS preference live. */
  theme?: WidgetTheme;
}

export interface WidgetInstance {
  root: HTMLElement;
  refresh: () => Promise<void>;
  destroy: () => void;
  /** Swap the colour scheme at runtime; `'auto'` (re)subscribes to the media query. */
  setTheme: (theme: WidgetTheme) => void;
}

/** A file row as returned by the list endpoint (FileResponse, see protocol/SPEC.md §3.2). */
export interface FileRow {
  id: string;
  name: string;
  size: number;
  mime: string;
  url: string | null;
  createdAt: string;
  deletedAt: string | null;
  lastModified: number | null;
  own?: boolean;
}
