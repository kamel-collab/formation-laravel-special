import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://app:8080';
const token = __ENV.K6_TOKEN;
const testLimite = __ENV.SCENARIO === 'limite';

if (!token) {
  throw new Error('Définissez K6_TOKEN avant de lancer le test.');
}

export const options = testLimite
  ? { vus: 1, iterations: 6 }
  : { vus: Number(__ENV.VUS || 1), duration: __ENV.DURATION || '3s' };

const lectures = new Counter('reponses_200');
const limites = new Counter('reponses_429');
const autres = new Counter('autres_reponses');
const dureeLecture = new Trend('lecture_200_ms', true);

export default function () {
  const response = http.get(`${baseUrl}/api/documents`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (response.status === 200) {
    lectures.add(1);
    dureeLecture.add(response.timings.duration);
  } else if (response.status === 429) {
    limites.add(1);
  } else {
    autres.add(1);
  }

  check(response, { 'lecture réussie (200)': (r) => r.status === 200 });
  if (testLimite) {
    console.log(`Réponse HTTP : ${response.status}`);
  } else {
    sleep(1);
  }
}
