const { chromium } = require('playwright');
const out = process.argv[2] ?? require('node:path').join(__dirname, 'output');
require('node:fs').mkdirSync(out, { recursive: true });
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const results = [];
const check = (name, ok, extra = '') => results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? ' — ' + extra : ''}`);
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => m.type() === 'error' && errors.push(m.text()));

  await page.goto(base + '/design-system', { waitUntil: 'networkidle' });

  // Command palette
  await page.keyboard.press('Control+k');
  const palette = page.getByRole('dialog', { name: 'Search' });
  await palette.waitFor({ state: 'visible' });
  await page.keyboard.type('dash');
  const options = await palette.getByRole('option').count();
  check('Command palette opens with Ctrl+K and filters', options === 1, `${options} result(s)`);
  await page.keyboard.press('Escape');
  await palette.waitFor({ state: 'hidden' });
  check('Command palette closes with Esc', true);

  // Theme
  await page.getByRole('button', { name: 'Theme' }).click();
  await page.getByRole('menuitemradio', { name: 'Dark' }).click();
  check('Theme switches to dark', (await page.evaluate(() => document.documentElement.dataset.theme)) === 'dark');
  await page.reload({ waitUntil: 'networkidle' });
  check('Theme persists across reload', (await page.evaluate(() => document.documentElement.dataset.theme)) === 'dark');
  await page.getByRole('button', { name: 'Theme' }).click();
  await page.getByRole('menuitemradio', { name: 'Light' }).click();

  // Sidebar collapse
  await page.getByRole('button', { name: 'Collapse sidebar' }).click();
  const width = await page.locator('aside').first().evaluate(el => el.getBoundingClientRect().width);
  await page.waitForTimeout(350);
  const collapsed = await page.locator('aside').first().evaluate(el => el.getBoundingClientRect().width);
  check('Sidebar collapses', collapsed < 100, `${Math.round(collapsed)}px`);
  await page.reload({ waitUntil: 'networkidle' });
  check('Collapsed state applied before paint after reload', (await page.evaluate(() => document.documentElement.dataset.sidebar)) === 'collapsed');
  await page.screenshot({ path: `${out}/interact-collapsed.png` });
  await page.getByRole('button', { name: 'Collapse sidebar' }).click();

  // Modal: focus trap and return focus
  const trigger = page.getByRole('button', { name: 'Open dialog' });
  await trigger.click();
  const dialog = page.getByRole('dialog', { name: 'Assign polling unit' });
  await dialog.waitFor({ state: 'visible' });
  await page.screenshot({ path: `${out}/interact-modal.png` });
  for (let i = 0; i < 8; i++) await page.keyboard.press('Tab');
  const focusInside = await dialog.evaluate(el => el.contains(document.activeElement));
  check('Modal traps focus', focusInside);
  await page.keyboard.press('Escape');
  await dialog.waitFor({ state: 'hidden' });
  check('Modal returns focus to trigger', await trigger.evaluate(el => el === document.activeElement));

  // Confirm dialog + toast
  await page.getByRole('button', { name: 'Delete…' }).first().click();
  await page.getByRole('button', { name: 'Delete draft' }).click();
  await page.getByText('Draft deleted').waitFor();
  check('Confirm dialog action shows toast', true);

  // Livewire form validation
  const form = page.locator('form[wire\\:submit="submit"]');
  await form.getByRole('button', { name: 'Validate' }).click();
  await page.getByText('The full name field is required.').waitFor();
  check('Server-side validation errors render inline', await form.locator('[aria-invalid="true"]').count() >= 2);
  await form.getByLabel('Full name').fill('Sample Tester');
  await form.getByLabel('Phone number').fill('0803 123 4567');
  await form.getByLabel('Phone number').blur();
  await form.getByLabel('Area').selectOption('area-2');
  await form.getByText('I understand this is a sample form').click();
  await page.waitForTimeout(400);
  await form.getByRole('button', { name: 'Validate' }).click();
  await page.getByText('Form is valid').waitFor();
  check('Valid submission normalises the phone number', await page.getByText('+234 ••• ••• 4567').count() > 0);

  // Livewire table
  const table = page.locator('table').filter({ hasText: 'Reference' });
  await page.getByLabel('Search records').fill('Person 01');
  await page.waitForResponse(r => r.url().includes('/livewire'));
  await page.waitForTimeout(300);
  const rows = await table.locator('tbody tr').count();
  check('Table search filters server-side', rows === 10, `${rows} rows for "Person 01"`);
  await page.getByLabel('Search records').fill('');
  await page.waitForTimeout(600);
  await page.getByRole('button', { name: 'Sort by Reference' }).click();
  await page.waitForTimeout(500);
  check('Table sorts ascending by reference', (await table.locator('tbody tr').first().innerText()).includes('SMP-0001'));
  await page.getByRole('button', { name: 'Sort by Reference' }).click();
  await page.waitForTimeout(500);
  check('Table sorts descending by reference', (await table.locator('tbody tr').first().innerText()).includes('SMP-0137'));
  await page.getByRole('button', { name: 'Next' }).click();
  await page.waitForTimeout(500);
  check('Table paginates', (await page.getByText('Showing 11–20 of 137').count()) === 1);
  await table.locator('tbody input[type=checkbox]').nth(0).check();
  await table.locator('tbody input[type=checkbox]').nth(1).check();
  await page.waitForTimeout(400);
  check('Row selection shows bulk bar', (await page.getByText('2 selected').count()) === 1);
  await table.getByRole('button', { name: /View details/ }).first().click();
  const slide = page.getByRole('dialog', { name: 'Record details' });
  await slide.waitFor({ state: 'visible' });
  await page.screenshot({ path: `${out}/interact-slideover.png` });
  check('Row detail slide-over opens', await slide.getByText('SMP-0127').count() > 0);
  await page.keyboard.press('Escape');

  // Locale
  await page.getByRole('button', { name: 'Language' }).click();
  await page.getByRole('menuitemradio', { name: /Naijá/ }).click();
  await page.waitForLoadState('networkidle');
  check('Locale switches to Pidgin', (await page.locator('html').getAttribute('lang')) === 'pcm' && await page.getByText('Pipul').count() > 0);
  await page.getByRole('button', { name: 'Language' }).click();
  await page.getByRole('menuitemradio', { name: /English/ }).click();

  // Mobile drawer
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true });
  const m = await mobile.newPage();
  m.on('pageerror', e => errors.push(e.message));
  await m.goto(base + '/design-system/shell', { waitUntil: 'networkidle' });
  await m.getByRole('button', { name: 'Open navigation' }).click();
  const drawer = m.getByRole('dialog', { name: 'Main navigation' });
  await drawer.waitFor({ state: 'visible' });
  await m.waitForTimeout(350);
  await m.screenshot({ path: `${out}/interact-drawer.png` });
  check('Mobile drawer opens', true);
  await m.keyboard.press('Escape');
  await drawer.waitFor({ state: 'hidden' });
  check('Mobile drawer closes with Esc', true);
  const targets = await m.locator('nav[aria-label="Mobile navigation"] a, nav[aria-label="Mobile navigation"] button').evaluateAll(els => els.map(e => e.getBoundingClientRect().height));
  check('Bottom bar touch targets ≥ 44px', targets.every(h => h >= 44), targets.map(Math.round).join(','));
  await m.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await m.getByText('All systems operational').waitFor({ timeout: 5000 });
  check('Lazy health panel loads when scrolled into view', true);

  // Offline indicator
  await mobile.setOffline(true);
  await m.waitForTimeout(300);
  check('Offline banner appears when connection drops', await m.getByText(/Offline — changes will wait/).first().isVisible());
  await mobile.setOffline(false);

  check('No JavaScript errors during interactions', errors.length === 0, errors.join(' | '));
  await browser.close();
  console.log(results.join('\n'));
})().catch(e => { console.log(results.join('\n')); console.error('ABORTED:', e.message); process.exit(1); });
