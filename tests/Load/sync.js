// k6 load test: field-app synchronisation (target from the brief: 1,000 concurrent devices).
// Needs signed-in test accounts. Provide a JSON file of {cookie, csrf, entryIds[]} per device:
// k6 run -e BASE_URL=https://staging.example -e DEVICES=devices.json tests/Load/sync.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const devices = new SharedArray('devices', () => JSON.parse(open(__ENV.DEVICES)));

export const options = {
  scenarios: { devices: { executor: 'constant-vus', vus: Math.min(devices.length, Number(__ENV.VUS || 1000)), duration: __ENV.DURATION || '5m' } },
  thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<1500'] },
};

export default function () {
  const d = devices[(__VU - 1) % devices.length];
  const headers = { Cookie: d.cookie, 'X-CSRF-TOKEN': d.csrf, 'Content-Type': 'application/json', Accept: 'application/json' };
  const items = Array.from({ length: 10 }, (_, i) => ({ type: 'contact', uuid: uuidv4(), walk_list_entry_id: d.entryIds[i % d.entryIds.length], outcome: 'reached', occurred_at: new Date().toISOString() }));
  const res = http.post(`${__ENV.BASE_URL}/api/v1/sync`, JSON.stringify({ device_id: `k6-${__VU}`, items }), { headers });
  check(res, { 'status 200': (r) => r.status === 200 });
  http.get(`${__ENV.BASE_URL}/api/v1/sync/walk-lists`, { headers });
  sleep(60); // Devices sync about once a minute (and the API allows 30 requests/min per person).
}
