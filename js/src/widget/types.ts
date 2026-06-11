import type { Strings } from './strings';

export interface WidgetRoutes {
  upload?: string;
  list?: string;
  delete?: string;
  restore?: string;
  cleanup?: string;
}

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
}

export interface WidgetInstance {
  root: HTMLElement;
  refresh: () => Promise<void>;
  destroy: () => void;
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
