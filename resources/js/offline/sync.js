// Offline field capture: queue, cache of assigned walk lists, and background sync.
import { uuid } from '../support/uuid';
import * as vault from './vault';

const SYNC_URL = '/api/v1/sync';
const LISTS_URL = '/api/v1/sync/walk-lists';
const BATCH = 50;

function deviceId() {
    try {
        let id = localStorage.getItem('device-id');
        if (!id) {
            id = uuid();
            localStorage.setItem('device-id', id);
        }
        return id;
    } catch {
        return null;
    }
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function call(url, options = {}) {
    return fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        ...options,
    });
}

export function syncStore(Alpine) {
    return {
        supported: vault.available(),
        pending: 0,
        attention: [], // conflicts and rejections the person should look at
        syncing: false,
        lastSync: null,
        problem: null, // 'auth' | 'locked' | 'offline' | 'server'
        lists: [],
        timer: null,

        async init() {
            if (!this.supported) return;
            await this.refreshCounts();
            this.lists = (await vault.all('lists')).map((row) => row.value);
            this.lastSync = await vault.get('meta', 'last-sync');
            window.addEventListener('online', () => this.flush());
            this.timer = setInterval(() => navigator.onLine && this.pending > 0 && this.flush(), 60000);
            if (navigator.onLine && this.pending > 0) this.flush();
        },

        async refreshCounts() {
            const rows = await vault.all('queue');
            this.pending = rows.filter((row) => row.value.state === 'pending').length;
            this.attention = rows.filter((row) => row.value.state !== 'pending').map((row) => ({ uuid: row.id, ...row.value }));
        },

        /** Saves a contact on the device. Returns the queued item. */
        async enqueue(contact) {
            const item = {
                type: 'contact',
                uuid: contact.uuid ?? uuid(),
                walk_list_entry_id: contact.entryId,
                outcome: contact.outcome,
                notes: contact.notes || null,
                occurred_at: new Date().toISOString(),
                label: contact.label ?? '',
                list: contact.listName ?? '',
                state: 'pending',
            };
            await vault.put('queue', item.uuid, item);
            await this.markEntry(contact.listUuid, contact.entryId, 'visited');
            await this.refreshCounts();
            return item;
        },

        /**
         * Records a contact: straight to the server through Livewire when online,
         * otherwise (or when the request fails) on the device for later sync.
         */
        async capture(contact, online) {
            contact.uuid ??= uuid();
            if (navigator.onLine && online) {
                try {
                    await online(contact.uuid);
                    return 'sent';
                } catch {
                    // Fall through to the device queue; the UUID makes a later resend safe.
                }
            }
            await this.enqueue(contact);
            Alpine.store('toasts').push({ type: 'info', title: window.__i18n?.saved_offline ?? 'Saved on this device. It will sync when you are back online.' });
            return 'queued';
        },

        async flush() {
            if (!this.supported || this.syncing || !navigator.onLine) return;
            this.syncing = true;
            this.problem = null;
            try {
                const rows = (await vault.all('queue')).filter((row) => row.value.state === 'pending');
                for (let i = 0; i < rows.length; i += BATCH) {
                    const batch = rows.slice(i, i + BATCH);
                    const items = batch.map(({ value }) => ({ type: value.type, uuid: value.uuid, walk_list_entry_id: value.walk_list_entry_id, outcome: value.outcome, notes: value.notes, occurred_at: value.occurred_at }));
                    const response = await call(SYNC_URL, { method: 'POST', body: JSON.stringify({ device_id: deviceId(), items }) });

                    if (response.status === 401) { this.problem = 'auth'; break; }
                    if (response.status === 419 || response.status === 423) { this.problem = 'locked'; break; }
                    if (!response.ok) { this.problem = 'server'; break; }

                    const { results } = await response.json();
                    for (const result of results) {
                        const row = batch.find((r) => r.id === result.uuid);
                        if (!row) continue;
                        if (result.status === 'created' || result.status === 'duplicate') {
                            await vault.remove('queue', row.id);
                        } else if (result.status === 'conflict' || result.status === 'rejected') {
                            await vault.put('queue', row.id, { ...row.value, state: result.status, message: result.message });
                        } // 'error': stays pending and is retried.
                    }
                }
                this.lastSync = new Date().toISOString();
                await vault.put('meta', 'last-sync', this.lastSync);
            } catch {
                this.problem = navigator.onLine ? 'server' : 'offline';
            } finally {
                this.syncing = false;
                await this.refreshCounts();
            }
        },

        async retry(id) {
            const value = await vault.get('queue', id);
            if (value) await vault.put('queue', id, { ...value, state: 'pending', message: null });
            await this.refreshCounts();
            this.flush();
        },

        async discard(id) {
            await vault.remove('queue', id);
            await this.refreshCounts();
        },

        /** Downloads the person's assigned walk lists for offline use (encrypted on the device). */
        async refreshLists() {
            if (!this.supported || !navigator.onLine) return;
            try {
                const response = await call(LISTS_URL);
                if (!response.ok) return;
                const { walk_lists: lists, user } = await response.json();
                const previous = await vault.get('meta', 'user');
                // A different person on this device never sees the previous person's lists.
                if (previous && previous !== user) await vault.clear('lists');
                await vault.put('meta', 'user', user);
                const keep = new Set(lists.map((l) => l.uuid));
                for (const row of await vault.all('lists')) if (!keep.has(row.id)) await vault.remove('lists', row.id);
                for (const list of lists) await vault.put('lists', list.uuid, list);
                this.lists = lists;
            } catch {
                // Offline or blocked: keep what is cached.
            }
        },

        async markEntry(listUuid, entryId, status) {
            if (!listUuid) return;
            const list = await vault.get('lists', listUuid);
            if (!list) return;
            list.entries = list.entries.map((e) => (e.id === entryId ? { ...e, status } : e));
            await vault.put('lists', listUuid, list);
            this.lists = this.lists.map((l) => (l.uuid === listUuid ? list : l));
        },

        /** Called on sign-out: cached lists are removed; unsent captures stay encrypted until the same person syncs. */
        async forgetLists() {
            if (!this.supported) return;
            await vault.clear('lists');
            this.lists = [];
        },
    };
}
