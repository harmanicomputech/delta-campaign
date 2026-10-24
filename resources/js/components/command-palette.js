// Keyboard-driven navigation palette (Ctrl/⌘ + K).
// Items are rendered server-side into a JSON <script> so permissions stay server-controlled.
export default function commandPalette(sourceId) {
    return {
        open: false,
        query: '',
        active: 0,
        items: [],

        init() {
            const source = document.getElementById(sourceId);
            this.items = source ? JSON.parse(source.textContent) : [];

            window.addEventListener('keydown', (event) => {
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.open ? this.close() : this.show();
                }
            });
        },

        get results() {
            const terms = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
            if (!terms.length) return this.items;
            return this.items.filter((item) => {
                const haystack = `${item.label} ${item.group}`.toLowerCase();
                return terms.every((term) => haystack.includes(term));
            });
        },

        show() {
            this.open = true;
            this.query = '';
            this.active = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            this.open = false;
        },

        move(step) {
            const count = this.results.length;
            if (!count) return;
            this.active = (this.active + step + count) % count;
            this.$nextTick(() =>
                this.$refs.list?.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }),
            );
        },

        select(index = this.active) {
            const item = this.results[index];
            if (!item || !item.url) return;
            window.location.assign(item.url);
        },
    };
}
