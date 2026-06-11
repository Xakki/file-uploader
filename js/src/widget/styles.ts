/**
 * Widget styles. All colors are CSS custom properties; the light palette is the
 * default (defined on `.fu-theme-light`) and `.fu-theme-dark` overrides them.
 * Both built-in templates (floating widget + inline form) consume these vars,
 * so element rules never hard-code a hex value — themeable end to end.
 */
export const CSS = `
.fu-theme-light,.fu-theme-dark{
  --fu-surface:#fff;
  --fu-surface-alt:#f9fafb;
  --fu-surface-accent:#eef2ff;
  --fu-border:#e5e7eb;
  --fu-border-soft:#f3f4f6;
  --fu-text:#111827;
  --fu-text-muted:#374151;
  --fu-text-subtle:#4b5563;
  --fu-text-faint:#9ca3af;
  --fu-accent:#4f46e5;
  --fu-accent-strong:#3730a3;
  --fu-accent-soft:#6366f1;
  --fu-accent-grad-from:#4f46e5;
  --fu-accent-grad-to:#8b5cf6;
  --fu-dropzone-border:#cbd5f5;
  --fu-dropzone-from:#f9fafb;
  --fu-dropzone-to:#eef2ff;
  --fu-toggle-bg:#1f2937;
  --fu-toggle-fg:#fff;
  --fu-success:#059669;
  --fu-error:#dc2626;
  --fu-shadow:rgba(15,23,42,.25);
  --fu-shadow-strong:rgba(15,23,42,.35);
  --fu-pill-bg:#eef2ff;
  --fu-pill-fg:#4f46e5;
  --fu-pill-secondary-bg:#f3f4f6;
  --fu-pill-secondary-fg:#374151;
}
.fu-theme-dark{
  --fu-surface:#1e293b;
  --fu-surface-alt:#0f172a;
  --fu-surface-accent:#312e81;
  --fu-border:#334155;
  --fu-border-soft:#1e293b;
  --fu-text:#f1f5f9;
  --fu-text-muted:#cbd5e1;
  --fu-text-subtle:#94a3b8;
  --fu-text-faint:#64748b;
  --fu-accent:#818cf8;
  --fu-accent-strong:#a5b4fc;
  --fu-accent-soft:#6366f1;
  --fu-accent-grad-from:#6366f1;
  --fu-accent-grad-to:#a855f7;
  --fu-dropzone-border:#475569;
  --fu-dropzone-from:#1e293b;
  --fu-dropzone-to:#312e81;
  --fu-toggle-bg:#4f46e5;
  --fu-toggle-fg:#fff;
  --fu-success:#34d399;
  --fu-error:#f87171;
  --fu-shadow:rgba(0,0,0,.5);
  --fu-shadow-strong:rgba(0,0,0,.6);
  --fu-pill-bg:#312e81;
  --fu-pill-fg:#c7d2fe;
  --fu-pill-secondary-bg:#334155;
  --fu-pill-secondary-fg:#cbd5e1;
}
.fu-widget{position:fixed;bottom:24px;right:24px;z-index:1050;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
.fu-toggle{width:54px;height:54px;border-radius:50%;background:var(--fu-toggle-bg);color:var(--fu-toggle-fg);display:flex;align-items:center;justify-content:center;font-size:24px;cursor:pointer;border:none;box-shadow:0 15px 30px var(--fu-shadow-strong);transition:transform .2s ease,box-shadow .2s ease;}
.fu-toggle:hover{transform:translateY(-2px);box-shadow:0 18px 32px var(--fu-shadow-strong);}
.fu-modal{position:absolute;bottom:70px;right:0;min-width:300px;max-width:90vw;background:var(--fu-surface);border-radius:16px;padding:20px;box-shadow:0 25px 45px var(--fu-shadow);display:none;flex-direction:column;gap:16px;color:var(--fu-text);}
.fu-modal.fu-open{display:flex;}
.fu-form{background:var(--fu-surface);border-radius:16px;padding:20px;box-shadow:0 10px 30px var(--fu-shadow);display:flex;flex-direction:column;gap:16px;color:var(--fu-text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
.fu-header{display:flex;align-items:center;justify-content:space-between;gap:8px;}
.fu-title{font-size:16px;font-weight:600;color:var(--fu-text);}
.fu-close{background:none;border:none;color:var(--fu-text-faint);font-size:18px;cursor:pointer;line-height:1;}
.fu-close:hover{color:var(--fu-text-subtle);}
.fu-dropzone{border:2px dashed var(--fu-dropzone-border);border-radius:14px;padding:20px;text-align:center;color:var(--fu-text-subtle);background:linear-gradient(145deg,var(--fu-dropzone-from),var(--fu-dropzone-to));transition:border-color .2s ease,background .2s ease;cursor:pointer;}
.fu-dropzone:hover,.fu-dropzone.fu-active{border-color:var(--fu-accent-soft);background:var(--fu-surface-accent);}
.fu-dropzone input{display:none;}
.fu-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.fu-status{font-size:13px;color:var(--fu-text-subtle);min-height:18px;}
.fu-status.fu-error{color:var(--fu-error);}
.fu-progress{flex:1;height:6px;background:var(--fu-border);border-radius:9999px;overflow:hidden;}
.fu-progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--fu-accent-grad-from),var(--fu-accent-grad-to));transition:width .2s ease;}
.fu-queue{display:none;flex-direction:column;gap:10px;}
.fu-queue-item{background:var(--fu-surface-alt);border-radius:12px;padding:10px 12px;box-shadow:inset 0 0 0 1px var(--fu-border);display:flex;flex-direction:column;gap:6px;transition:opacity .3s ease;}
.fu-queue-item.fu-fade-out{opacity:0;transition:opacity 3s ease;}
.fu-queue-name{font-size:13px;font-weight:500;color:var(--fu-text);word-break:break-word;cursor:pointer;}
.fu-queue-meta{display:flex;align-items:center;gap:8px;font-size:12px;}
.fu-queue-percent{font-size:12px;font-weight:600;color:var(--fu-text-subtle);min-width:38px;text-align:right;font-variant-numeric:tabular-nums;}
.fu-queue-success{color:var(--fu-success);}
.fu-queue-error{color:var(--fu-error);}
.fu-icon-btn{border:none;background:none;color:var(--fu-accent);cursor:pointer;font-size:16px;display:inline-flex;align-items:center;justify-content:center;padding:0;line-height:1;}
.fu-icon-btn:hover{color:var(--fu-accent-strong);}
.fu-icon-btn[disabled]{color:var(--fu-text-faint);cursor:not-allowed;}
.fu-list{display:none;flex-direction:column;gap:8px;}
.fu-list.fu-open{display:flex;}
.fu-table{width:100%;border-collapse:collapse;font-size:13px;color:var(--fu-text-muted);}
.fu-table th{font-weight:600;text-align:left;border-bottom:1px solid var(--fu-border);padding:6px 4px;}
.fu-table td{padding:6px 4px;border-bottom:1px solid var(--fu-border-soft);vertical-align:middle;}
.fu-table tr:last-child td{border-bottom:none;}
.fu-table a{color:var(--fu-accent);}
.fu-table button{border:none;background:none;color:var(--fu-accent);cursor:pointer;font-size:12px;padding:0 4px;}
.fu-empty{font-size:13px;color:var(--fu-text-faint);text-align:center;padding:12px 0;}
.fu-list-actions{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.fu-pill{border:none;border-radius:9999px;background:var(--fu-pill-bg);color:var(--fu-pill-fg);padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;}
.fu-pill.secondary{background:var(--fu-pill-secondary-bg);color:var(--fu-pill-secondary-fg);}
`;
