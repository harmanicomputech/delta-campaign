// Automated WCAG 2.1 AA checks (axe-core) across public, staff and agent pages, in light and dark themes.
// Run against a development server with sample data: node tests/Browser/accessibility.cjs [output-dir]
// Needs DESIGN_SYSTEM_ENABLED=true (the developer SMS outbox supplies sign-in codes) and a fresh admin (php artisan campaign:create-admin 08030000001 "…").
const { chromium } = require('playwright');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const out = process.argv[2] ?? path.join(__dirname, 'output');
fs.mkdirSync(out, { recursive: true });
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const axe = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

function b32(s) { const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; for (const c of s.replace(/\s|=/g, '')) bits += a.indexOf(c).toString(2).padStart(5, '0'); const o = []; for (let i = 0; i + 8 <= bits.length; i += 8) o.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(o); }
function totp(secret) { const buf = Buffer.alloc(8); buf.writeUInt32BE(Math.floor(Date.now() / 30000), 4); const h = crypto.createHmac('sha1', b32(secret)).update(buf).digest(); const o = h[19] & 15; return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0'); }
async function codeFor(page, e164, previous) { for (let i = 0; i < 30; i++) { await page.goto(base + '/design-system/outbox'); const m = (await page.locator('main').innerText()).match(new RegExp(`\\${e164}[\\s\\S]*?(\\d{6}) is your`)); if (m && m[1] !== previous) return m[1]; await page.waitForTimeout(500); } return null; }
async function signIn(page, phone, e164) {
  await page.goto(base + '/design-system/outbox'); const before = ((await page.locator('main').innerText()).match(new RegExp(`\\${e164}[\\s\\S]*?(\\d{6}) is your`)) || [])[1];
  await page.goto(base + '/login'); await page.getByLabel('Phone number').fill(phone); await page.getByRole('button', { name: 'Send code' }).click(); await page.waitForURL('**/login/verify');
  const code = await codeFor(page, e164, before); await page.goto(base + '/login/verify'); await page.locator('#otp-0').fill(code); await page.getByRole('button', { name: 'Verify and continue' }).click();
}

async function audit(page, url, label, theme) {
  // The theme is chosen before the page loads (as a returning visitor would), so no colour transition is caught mid-way.
  await page.evaluate((t) => { try { localStorage.setItem('theme', t); } catch (e) {} }, theme).catch(() => {});
  await page.goto(base + url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(250);
  await page.evaluate(axe); // Evaluated over the DevTools protocol, so the page's CSP does not block it.
  const result = await page.evaluate(async () => await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] }, resultTypes: ['violations'] }));
  return result.violations.map(v => ({ page: label, theme, id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.length, target: v.nodes[0]?.target.join(' '),
    details: v.nodes.map(n => ({ target: n.target.join(' '), html: n.html.slice(0, 160), summary: (n.any[0]?.message ?? n.failureSummary ?? '').slice(0, 200) })) }));
}

(async () => {
  const browser = await chromium.launch();
  const findings = []; let audited = 0;
  const run = async (page, pages) => { for (const [label, url] of pages) for (const theme of ['light', 'dark']) { findings.push(...await audit(page, url, label, theme)); audited++; } };

  const pub = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const event = await (async () => { await pub.goto(base + '/events'); return await pub.locator('main a[href*="/events/"]').first().getAttribute('href'); })();
  await run(pub, [['Home', '/'], ['About', '/about'], ['Projects', '/projects'], ['News', '/news'], ['Events', '/events'], ['Event', new URL(event, base).pathname], ['Join', '/join'], ['Volunteer', '/volunteer'],
    ['Polling unit lookup', '/polling-unit'], ['Contact', '/contact'], ['Privacy', '/privacy'], ['Preferences', '/preferences'], ['LGAs', '/lgas'], ['Sign in', '/login']]);

  const admin = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await signIn(admin, '0803 000 0001', '+2348030000001'); await admin.waitForURL('**/two-factor/setup'); await admin.waitForTimeout(500);
  const secret = (await admin.locator('p.font-mono').first().innerText()).replace(/\s/g, '');
  for (const [i, d] of [...totp(secret)].entries()) await admin.locator('#otp-' + i).fill(d);
  await admin.getByRole('button', { name: 'Confirm and enable' }).click(); await admin.getByText('Save your recovery codes').waitFor();
  await admin.goto(base + '/workspace/election-day/agents'); const unit = await admin.locator('main a[href*="/units/"]').first().getAttribute('href');
  await run(admin, [['Dashboard', '/workspace'], ['Command centre', '/workspace/command-centre'], ['Approvals', '/workspace/approvals'], ['Supporters', '/workspace/supporters'],
    ['Volunteers', '/workspace/volunteers'], ['Training', '/workspace/training'], ['Tasks', '/workspace/tasks'], ['Canvassing', '/workspace/canvassing'], ['Events (staff)', '/workspace/events'],
    ['Messaging', '/workspace/messaging'], ['New campaign', '/workspace/messaging/new'], ['Content', '/workspace/content'], ['Content editor', '/workspace/content/new?type=news'],
    ['Projects (staff)', '/workspace/projects'], ['Rapid response', '/workspace/rapid-response'], ['Contact inbox', '/workspace/inbox'], ['Election hub', '/workspace/election-day'],
    ['Agents', '/workspace/election-day/agents'], ['Incidents', '/workspace/election-day/incidents'], ['Results', '/workspace/election-day/results'], ['Polling unit', new URL(unit, base).pathname],
    ['Election setup', '/workspace/election-day/setup'], ['Evidence packs', '/workspace/election-day/evidence-packs'], ['Users', '/workspace/users'], ['Geography', '/workspace/geography'],
    ['Notifications', '/workspace/notifications'], ['Account', '/workspace/account'], ['USSD simulator', '/workspace/ussd-simulator']]);

  const agent = await (await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })).newPage();
  await signIn(agent, '0817 000 0101', '+2348170000101'); await agent.waitForURL('**/workspace');
  await run(agent, [['Agent: today', '/workspace/my-polling-unit'], ['Agent: turnout', '/workspace/my-polling-unit?tab=turnout'], ['Agent: incident', '/workspace/my-polling-unit?tab=incident'], ['Agent: result', '/workspace/my-polling-unit?tab=result']]);

  fs.writeFileSync(path.join(out, 'accessibility.json'), JSON.stringify(findings, null, 2));
  const byRule = {}; for (const f of findings) { byRule[f.id] ??= { impact: f.impact, help: f.help, pages: new Set(), nodes: 0 }; byRule[f.id].pages.add(f.page + ' (' + f.theme + ')'); byRule[f.id].nodes += f.nodes; }
  console.log(`Audited ${audited} page/theme combinations: ${findings.length} violation group(s).`);
  for (const [id, r] of Object.entries(byRule)) console.log(`- ${id} [${r.impact}] ${r.help}: ${r.nodes} node(s) on ${[...r.pages].slice(0, 6).join(', ')}${r.pages.size > 6 ? ' …' : ''}`);
  await browser.close();
  process.exit(findings.length === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
