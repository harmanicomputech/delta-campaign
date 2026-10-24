const { chromium } = require('playwright');
const out = process.argv[2] ?? require('node:path').join(__dirname, 'output');
require('node:fs').mkdirSync(out, { recursive: true });
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const pages = [['home', '/'], ['ds', '/design-system'], ['shell', '/design-system/shell'], ['signin', '/design-system/auth/sign-in'], ['verify', '/design-system/auth/verify'], ['404', '/design-system/errors/404']];
const viewports = { desktop: { width: 1440, height: 900 }, tablet: { width: 834, height: 1112 }, mobile: { width: 390, height: 844 } };
(async () => {
  const browser = await chromium.launch();
  const problems = [];
  for (const theme of ['light', 'dark']) {
    for (const [vpName, vp] of Object.entries(viewports)) {
      const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1, colorScheme: theme });
      const page = await ctx.newPage();
      page.on('console', m => { if (['error', 'warning'].includes(m.type())) problems.push(`[${theme}/${vpName}] console ${m.type()}: ${m.text()}`); });
      page.on('pageerror', e => problems.push(`[${theme}/${vpName}] pageerror: ${e.message}`));
      for (const [name, path] of pages) {
        if (theme === 'dark' && vpName === 'tablet' && name !== 'shell') continue;
        await page.goto(base + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 0) problems.push(`[${theme}/${vpName}] ${name}: horizontal overflow ${overflow}px`);
        await page.screenshot({ path: `${out}/${theme}-${vpName}-${name}.png`, fullPage: name !== 'ds' });
      }
      await ctx.close();
    }
  }
  await browser.close();
  console.log(problems.length ? problems.join('\n') : 'No console errors, page errors or horizontal overflow.');
})();
