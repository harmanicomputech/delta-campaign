const THEMES = ['light', 'dark', 'system'];

function readPreference(key, fallback) {
    try {
        return localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
}

function writePreference(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Storage can be unavailable (private mode); the preference simply won't persist.
    }
}

export function applyTheme(preference) {
    const dark =
        preference === 'dark' ||
        (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
}

export function registerStores(Alpine) {
    Alpine.store('theme', {
        preference: readPreference('theme', 'system'),

        init() {
            applyTheme(this.preference);
            window
                .matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', () => this.preference === 'system' && applyTheme('system'));
        },

        set(preference) {
            if (!THEMES.includes(preference)) return;
            this.preference = preference;
            writePreference('theme', preference);
            applyTheme(preference);
        },

        get isDark() {
            return document.documentElement.dataset.theme === 'dark';
        },
    });

    Alpine.store('sidebar', {
        collapsed: readPreference('sidebar-collapsed', 'false') === 'true',
        mobileOpen: false,

        toggle() {
            this.collapsed = !this.collapsed;
            document.documentElement.dataset.sidebar = this.collapsed ? 'collapsed' : 'expanded';
            writePreference('sidebar-collapsed', String(this.collapsed));
        },
    });

    Alpine.store('network', {
        online: navigator.onLine,

        init() {
            window.addEventListener('online', () => (this.online = true));
            window.addEventListener('offline', () => (this.online = false));
        },
    });

    let nextToastId = 1;

    Alpine.store('toasts', {
        items: [],

        push({ type = 'info', title = '', message = '', timeout = 5000 } = {}) {
            const id = nextToastId++;
            this.items.push({ id, type, title, message });
            if (timeout > 0) setTimeout(() => this.dismiss(id), timeout);
            return id;
        },

        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });
}
