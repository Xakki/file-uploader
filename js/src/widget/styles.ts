export const CSS = `
.fu-widget{position:fixed;bottom:24px;right:24px;z-index:1050;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
.fu-toggle{width:54px;height:54px;border-radius:50%;background:#1f2937;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;cursor:pointer;box-shadow:0 15px 30px rgba(15,23,42,.35);transition:transform .2s ease,box-shadow .2s ease;}
.fu-toggle:hover{transform:translateY(-2px);box-shadow:0 18px 32px rgba(15,23,42,.4);}
.fu-modal{position:absolute;bottom:70px;right:0;min-width:300px;max-width:90vw;background:#fff;border-radius:16px;padding:20px;box-shadow:0 25px 45px rgba(15,23,42,.25);display:none;flex-direction:column;gap:16px;}
.fu-modal.fu-open{display:flex;}
.fu-header{display:flex;align-items:center;justify-content:space-between;gap:8px;}
.fu-title{font-size:16px;font-weight:600;color:#111827;}
.fu-close{background:none;border:none;color:#9ca3af;font-size:18px;cursor:pointer;line-height:1;}
.fu-dropzone{border:2px dashed #cbd5f5;border-radius:14px;padding:20px;text-align:center;color:#4b5563;background:linear-gradient(145deg,#f9fafb,#eef2ff);transition:border-color .2s ease,background .2s ease;cursor:pointer;}
.fu-dropzone:hover,.fu-dropzone.fu-active{border-color:#6366f1;background:#eef2ff;}
.fu-dropzone input{display:none;}
.fu-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.fu-status{font-size:13px;color:#4b5563;min-height:18px;}
.fu-progress{height:4px;background:#e5e7eb;border-radius:9999px;overflow:hidden;}
.fu-progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,#4f46e5,#8b5cf6);transition:width .2s ease;}
.fu-queue{display:none;flex-direction:column;gap:10px;}
.fu-queue-item{background:#f9fafb;border-radius:12px;padding:10px 12px;box-shadow:inset 0 0 0 1px #e5e7eb;display:flex;flex-direction:column;gap:6px;transition:opacity .3s ease;}
.fu-queue-item.fu-fade-out{opacity:0;transition:opacity 3s ease;}
.fu-queue-name{font-size:13px;font-weight:500;color:#111827;word-break:break-word;cursor:pointer;}
.fu-queue-meta{display:flex;align-items:center;gap:8px;font-size:12px;}
.fu-queue-success{color:#059669;}
.fu-queue-error{color:#dc2626;}
.fu-icon-btn{border:none;background:none;color:#4f46e5;cursor:pointer;font-size:16px;display:inline-flex;align-items:center;justify-content:center;padding:0;line-height:1;}
.fu-icon-btn:hover{color:#3730a3;}
.fu-icon-btn[disabled]{color:#9ca3af;cursor:not-allowed;}
.fu-list{display:none;flex-direction:column;gap:8px;}
.fu-list.fu-open{display:flex;}
.fu-table{width:100%;border-collapse:collapse;font-size:13px;color:#374151;}
.fu-table th{font-weight:600;text-align:left;border-bottom:1px solid #e5e7eb;padding:6px 4px;}
.fu-table td{padding:6px 4px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
.fu-table tr:last-child td{border-bottom:none;}
.fu-table button{border:none;background:none;color:#4f46e5;cursor:pointer;font-size:12px;padding:0 4px;}
.fu-empty{font-size:13px;color:#9ca3af;text-align:center;padding:12px 0;}
.fu-list-actions{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.fu-pill{border:none;border-radius:9999px;background:#eef2ff;color:#4f46e5;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;}
.fu-pill.secondary{background:#f3f4f6;color:#374151;}
`;
