import './bootstrap';

import Alpine from 'alpinejs';
import { openUrl } from '@tauri-apps/plugin-opener';

window.Alpine = Alpine;

Alpine.start();

// Desktop (Tauri): webview memblokir target="_blank", jadi link eksternal
// dibuka lewat browser default. Link internal (origin sama) tidak disentuh.
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
            if (url.origin === window.location.origin) {
                return;
            }
        } catch {
            return;
        }

        event.preventDefault();
        void openUrl(anchor.href);
    });
}
