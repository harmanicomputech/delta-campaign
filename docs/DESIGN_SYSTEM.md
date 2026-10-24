# Design System

The live reference is at `/design-system` (local and staging only). This document records the rules.

## Brand status

**Provisional.** No approved logo, palette or typography was supplied. The current direction is a
deep "Delta green" primary with a restrained gold accent on warm-neutral "ink" greys. The monogram in
the shell is built from `APP_NAME` initials and is **not** a logo.

To apply the approved brand: edit the `--color-brand-*` and `--color-accent-*` scales (and fonts if
needed) in `resources/css/app.css`. Nothing else should need to change.

## Tokens

Defined in `resources/css/app.css`:

1. **Primitive scales** (`@theme static`): `brand`, `accent`, `ink`, radii, easing.
2. **Semantic roles** (`--c-*` on `:root` / `[data-theme=dark]`), exposed to Tailwind via
   `@theme inline`. Components use roles only:

| Role utility | Use |
| --- | --- |
| `bg-canvas` | Page background |
| `bg-surface`, `bg-surface-raised`, `bg-surface-sunken`, `bg-surface-hover` | Panels, overlays, wells, hover rows |
| `border-line`, `border-line-strong` | Hairlines, control borders |
| `text-fg`, `text-muted`, `text-subtle` | Primary, secondary and tertiary text |
| `bg-primary`, `text-primary`, `bg-primary-soft`, `text-primary-soft-fg` | Brand actions and selected states |
| `success`, `warning`, `danger`, `info` (+ `-soft`) | Status |
| `bg-nav`, `text-nav-fg`, `text-nav-muted` | Navigation chrome (dark in both themes) |

Themes: `light`, `dark`, `system`, stored in `localStorage` and applied before first paint. The
sidebar's collapsed state is applied the same way (`data-sidebar` on `<html>`).

## Components

Anonymous Blade components in `resources/views/components/ui` (`<x-ui.*>`), shell pieces in
`components/shell`, layouts in `resources/views/layouts` (`<x-layouts::app|auth|public>`).

| Group | Components |
| --- | --- |
| Actions | `button` (primary, secondary, soft, ghost, danger; sm/md/lg; icon; `href`; `loading` for Livewire actions) |
| Status | `badge`, `sample-badge`, `spinner`, `progress`, `avatar`, `kbd` |
| Forms | `field`, `input`, `phone-input`, `otp-input`, `select`, `textarea`, `checkbox`, `switch` |
| Feedback | `alert`, `empty-state`, `skeleton`, toasts (`$store.toasts.push()` or Livewire `dispatch('toast', …)`) |
| Overlays | `modal`, `confirm-dialog`, `slide-over`, `dropdown` + `dropdown-item`, `tooltip` |
| Structure | `page-header`, `breadcrumbs`, `card`, `tabs` |
| Data | `table`, `th` (sortable), `td`, `stat` (sparkline; `sample` flag), `pagination.workspace` view |
| Icons | `<x-icon name="…">`, using Lucide icons listed in `scripts/icons.json` (`npm run icons`) |

Livewire reference implementations: `App\Livewire\DesignSystem\SampleRecordsTable` (search, filter,
sort, server-side pagination, selection, bulk action with confirmation, detail slide-over) and
`SampleForm` (validation on blur and submit).

## Rules

- **Real data only.** Metrics come from real queries. Without data, a tile shows "—" and says where the
  data will come from. Illustrative figures carry the `SAMPLE` badge.
- **Election-day data labels.** Always one of *Submitted*, *Verified* or *Official source*.
- **No dead controls.** Unbuilt modules appear as disabled "Planned · Phase N" items.
- **Cards group related content.** They are not a default wrapper for everything.
- **Accessibility.** Labels on every control, errors linked with `aria-describedby`, `aria-invalid` on
  invalid fields, focus-trapped dialogs that return focus, visible focus rings, skip link, landmarks,
  touch targets of at least 44 px on mobile, and `prefers-reduced-motion` honoured.
- **Motion.** Transitions of 150–300 ms that never block an action.
- **Tables.** Always server-side pagination and filtering; never load full datasets into the browser.

## Verified in Phase 1

Rendered at 1440 px, 834 px and 390 px, in light and dark themes, using Playwright. No horizontal
overflow, console errors or CSP violations. Interaction checks covered the command palette, theme
persistence, sidebar collapse, modal focus trap and focus return, confirmation dialog, Livewire
validation, table search/sort/pagination/selection, slide-over, locale switching, mobile drawer,
touch-target size, lazy loading and the offline banner.
