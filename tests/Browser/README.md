# Browser checks (Playwright)

Run against a running app (`php artisan serve`, with `npm run build` and Horizon running):

```bash
node tests/Browser/responsive.cjs     # screenshots: 3 viewports × light/dark; fails on console errors or horizontal overflow
node tests/Browser/interactions.cjs   # keyboard, dialogs, Livewire table/form, locale, mobile drawer, offline banner
```

`BASE_URL` overrides the target (default `http://127.0.0.1:8000`). Screenshots go to `tests/Browser/output/` (git-ignored).
Requires the `playwright` package and a Chromium build available to it.
