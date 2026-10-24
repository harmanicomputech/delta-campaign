import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerStores } from './stores';
import commandPalette from './components/command-palette';
import qrCode from './components/qr-code';
import { uuid } from './support/uuid';

registerStores(Alpine);

Alpine.magic('uuid', () => uuid);

Alpine.data('commandPalette', commandPalette);
Alpine.data('qrCode', qrCode);

// Livewire components and controllers emit `toast` events: $this->dispatch('toast', type: 'success', title: '…').
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    Alpine.store('toasts').push(detail ?? {});
});

Livewire.start();
