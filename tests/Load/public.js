// k6 load test: public website (target from the brief: 5,000 concurrent visitors).
// k6 run -e BASE_URL=https://staging.example tests/Load/public.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [{ duration: '2m', target: Number(__ENV.VUS || 5000) }, { duration: '5m', target: Number(__ENV.VUS || 5000) }, { duration: '1m', target: 0 }],
  thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<800'] },
};

const pages = ['/', '/news', '/events', '/projects', '/lgas', '/polling-unit', '/join', '/about'];

export default function () {
  const res = http.get(`${__ENV.BASE_URL}${pages[Math.floor(Math.random() * pages.length)]}`);
  check(res, { 'status 200': (r) => r.status === 200 });
  sleep(3 + Math.random() * 7); // Reading time between pages.
}
