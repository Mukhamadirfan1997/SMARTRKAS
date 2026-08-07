import './bootstrap';

import Alpine from 'alpinejs';
import { openUrl } from '@tauri-apps/plugin-opener';
import { invoke } from '@tauri-apps/api/core';

window.Alpine = Alpine;

Alpine.start();

// Desktop (Tauri): webview memblokir target="_blank", jadi link eksternal
// dibuka lewat browser default. Link internal yang membuka PDF/unduhan
// ditangani lewat dialog "Save As" native.
const isTauri = typeof window !== 'undefined' && typeof window.__TAURI_INTERNALS__ !== 'undefined';

if (isTauri) {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const anchor = target.closest('a[target="_blank"]');
        if (anchor === null) {
            return;
        }

        try {
            const url = new URL(anchor.href);
            if (url.origin !== window.location.origin) {
                event.preventDefault();
                void openUrl(anchor.href);
            } else {
                // Link internal (mis. cetak kwitansi): arahkan ke dialog save.
                event.preventDefault();
                void window.SmartRKAS.saveDownload(anchor.href);
            }
        } catch {
            return;
        }
    });
}

function filenameFromDisposition(resp) {
    const cd = resp.headers.get('Content-Disposition') || '';
    // Prioritas filename* (UTF-8, bisa percent-encoded), lalu filename biasa.
    const star = cd.match(/filename\*=(?:UTF-8'')?"?([^";]+)"?/i);
    if (star) {
        try {
            return decodeURIComponent(star[1]);
        } catch {
            return star[1];
        }
    }
    const plain = cd.match(/filename="?([^";]+)"?/i);
    return plain ? plain[1] : 'download';
}

// Helper global untuk mode desktop. Tidak aktif di mode web.
window.SmartRKAS = window.SmartRKAS || {};

if (isTauri) {
    window.SmartRKAS.saveDownload = async (url, filename) => {
        try {
            const resp = await fetch(url, { credentials: 'same-origin' });
            if (!resp.ok) {
                return false;
            }
            return await window.SmartRKAS.saveResponse(resp, filename);
        } catch {
            return false;
        }
    };

    window.SmartRKAS.saveResponse = async (resp, filename) => {
        try {
            const buffer = await resp.arrayBuffer();
            const bytes = new Uint8Array(buffer);
            let binary = '';
            const chunk = 0x8000;
            for (let i = 0; i < bytes.length; i += chunk) {
                binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
            }
            const base64Data = btoa(binary);
            return await invoke('save_download', {
                base64Data,
                filename: filename || filenameFromDisposition(resp),
            });
        } catch {
            return false;
        }
    };
}

// Desktop: arahkan tautan unduhan (export Excel, backup, PDF laporan) ke
// dialog "Save As" native karena WebView tidak menangani respons download
// maupun popup window.open.
if (isTauri) {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const anchor = target.closest('a');
        if (anchor === null) {
            return;
        }

        let url;
        try {
            url = new URL(anchor.href);
        } catch {
            return;
        }

        if (url.origin !== window.location.origin) {
            return;
        }

        const path = url.pathname;
        const isDownload =
            /^\/exports\/[^/]+\/download\/?$/.test(path) ||
            /\/pengaturan\/backup\/download\//.test(path);

        if (!isDownload) {
            return;
        }

        event.preventDefault();
        void window.SmartRKAS.saveDownload(anchor.href);
    });
}

// Desktop: form dengan target="_blank" (kwitansi batch) di-fetch via POST lalu
// hasil PDF-nya disimpan lewat dialog native.
if (isTauri) {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const isPopup = form.target === '_blank';
        const isSameOrigin = form.action === '' || (() => {
            try {
                return new URL(form.action).origin === window.location.origin;
            } catch {
                return false;
            }
        })();

        if (!isPopup || !isSameOrigin) {
            return;
        }

        event.preventDefault();
        void (async () => {
            const action = form.action || window.location.href;
            const method = (form.method || 'GET').toUpperCase();
            const body = method === 'GET' ? null : new FormData(form);
            const resp = await fetch(action, {
                method,
                body,
                credentials: 'same-origin',
                headers: method === 'GET' ? undefined : { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (resp.ok) {
                await window.SmartRKAS.saveResponse(resp);
            }
        })();
    });
}
