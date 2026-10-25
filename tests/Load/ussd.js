// k6 load test: USSD callbacks (target from the brief: 200 requests/second).
// k6 run -e BASE_URL=https://staging.example -e WEBHOOK_SECRET=… tests/Load/ussd.js
// Run against STAGING only, from an address the webhook rate limit allows (12,000/min per IP).
import http from 'k6/http';
import { check } from 'k6';

export const options = {
  scenarios: { ussd: { executor: 'constant-arrival-rate', rate: Number(__ENV.RATE || 200), timeUnit: '1s', duration: __ENV.DURATION || '2m', preAllocatedVUs: 100, maxVUs: 400 } },
  thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<500', 'p(99)<1000'] },
};

// A realistic mix: opening the menu, choosing an option, and paging.
const steps = ['', '1', '2', '3', '1*1', '2*1'];

export default function () {
  const session = `k6-${__VU}-${__ITER}`;
  const text = steps[__ITER % steps.length];
  const res = http.post(`${__ENV.BASE_URL}/webhooks/ussd?key=${__ENV.WEBHOOK_SECRET}`, {
    sessionId: session, serviceCode: __ENV.SERVICE_CODE || '*384*1#', phoneNumber: `+234809${String(__VU * 1000 + (__ITER % 1000)).padStart(7, '0')}`, text,
  });
  check(res, { 'status 200': (r) => r.status === 200, 'CON/END reply': (r) => /^(CON|END) /.test(r.body), 'fits a USSD screen': (r) => r.body.length <= 182 });
}
