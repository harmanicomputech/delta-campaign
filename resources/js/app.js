import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerStores } from './stores';
import commandPalette from './components/command-palette';
import qrCode from './components/qr-code';
import { uuid } from './support/uuid';
import { syncStore } from './offline/sync';

registerStores(Alpine);

Alpine.magic('uuid', () => uuid);
Alpine.store('sync', syncStore(Alpine));

// Low-data mode: remembered per device and sent to the server as a plain cookie
// so pages can skip background polling. Follows the browser's Save-Data hint by default.
Alpine.store('lowData', {
    on: false,
    init() {
        const saved = document.cookie.match(/(?:^|;\s*)low_data=([01])/);
        this.on = saved ? saved[1] === '1' : !!navigator.connection?.saveData;
    },
    toggle() {
        this.on = !this.on;
        document.cookie = `low_data=${this.on ? 1 : 0}; path=/; max-age=31536000; SameSite=Lax`;
        window.location.reload();
    },
});

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}

Alpine.data('commandPalette', commandPalette);
Alpine.data('qrCode', qrCode);

// Livewire components and controllers emit `toast` events: $this->dispatch('toast', type: 'success', title: '…').
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    Alpine.store('toasts').push(detail ?? {});
});

Livewire.start();
