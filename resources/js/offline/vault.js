// Encrypted IndexedDB storage for field data kept on the device.
//
// Records are encrypted with AES-GCM under a per-device key that is created as
// non-extractable: script can use it but never read it out, and it never leaves
// the browser. This protects data at rest against casual inspection of the
// device's storage; it is not a defence against malware running in the browser.

const DB_NAME = 'campaign-field';
const DB_VERSION = 1;
const STORES = ['keys', 'queue', 'lists', 'meta'];

let dbPromise = null;
let keyPromise = null;

function request(req) {
    return new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export function available() {
    return typeof indexedDB !== 'undefined' && !!window.crypto?.subtle;
}

function open() {
    if (!dbPromise) {
        dbPromise = new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                for (const name of STORES) {
                    if (!req.result.objectStoreNames.contains(name)) req.result.createObjectStore(name);
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }
    return dbPromise;
}

async function tx(store, mode, fn) {
    const db = await open();
    const transaction = db.transaction(store, mode);
    const result = await fn(transaction.objectStore(store));
    await new Promise((resolve, reject) => {
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
    return result;
}

async function key() {
    if (!keyPromise) {
        keyPromise = (async () => {
            const existing = await tx('keys', 'readonly', (s) => request(s.get('main')));
            if (existing) return existing;
            const created = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
            await tx('keys', 'readwrite', (s) => request(s.put(created, 'main')));
            return created;
        })();
    }
    return keyPromise;
}

async function seal(value) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await key(), new TextEncoder().encode(JSON.stringify(value)));
    return { iv, data };
}

async function unseal(record) {
    try {
        const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: record.iv }, await key(), record.data);
        return JSON.parse(new TextDecoder().decode(plain));
    } catch {
        return null; // Unreadable (e.g. the key was cleared): treat as missing.
    }
}

export async function put(store, id, value) {
    const sealed = await seal(value);
    await tx(store, 'readwrite', (s) => request(s.put(sealed, id)));
}

export async function get(store, id) {
    const record = await tx(store, 'readonly', (s) => request(s.get(id)));
    return record ? unseal(record) : null;
}

export async function all(store) {
    const [keys, records] = await tx(store, 'readonly', async (s) => [await request(s.getAllKeys()), await request(s.getAll())]);
    const values = await Promise.all(records.map(unseal));
    return keys.map((id, i) => ({ id, value: values[i] })).filter((row) => row.value !== null);
}

export async function remove(store, id) {
    await tx(store, 'readwrite', (s) => request(s.delete(id)));
}

export async function clear(store) {
    await tx(store, 'readwrite', (s) => request(s.clear()));
}
