/**
 * Genera FalconCare-API.postman_collection.json alineada con /api/docs.json.
 * Uso: node postman/generate-collection.mjs
 */
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const baseUrl = process.env.FALCONCARE_API_BASE_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000';

const saveTokenScript = [
  'if (pm.response.code === 200) {',
  "  const json = pm.response.json();",
  "  pm.environment.set('accessToken', json.accessToken);",
  "  pm.environment.set('token', json.accessToken);",
  '}',
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  "pm.test('JWT accessToken', () => {",
  '  const json = pm.response.json();',
  "  pm.expect(json).to.have.property('accessToken');",
  "  pm.expect(json.tokenType).to.eql('Bearer');",
  '});',
].join('\n');

const invalidLoginScript = [
  "pm.test('Status 401', () => pm.response.to.have.status(401));",
  "pm.test('Invalid credentials', () => {",
  "  pm.expect(pm.response.json().error).to.eql('Invalid credentials');",
  '});',
].join('\n');

const validationLoginScript = [
  "pm.test('Status 422', () => pm.response.to.have.status(422));",
  "pm.test('Validation failed', () => {",
  "  pm.expect(pm.response.json().error).to.eql('Validation failed');",
  '});',
].join('\n');

function req(name, method, path, opts = {}) {
  const {
    auth = true,
    body = null,
    tests = ["pm.test('OK', () => pm.expect(pm.response.code).to.be.oneOf([200, 201, 204]));"],
    prerequest = [],
  } = opts;
  const urlPath = path.startsWith('/') ? path : `/${path}`;
  const item = {
    name,
    request: {
      method,
      header: [{ key: 'Accept', value: 'application/json' }],
      url: `{{baseUrl}}${urlPath}`,
    },
    event: [],
  };
  if (body) {
    item.request.header.push({ key: 'Content-Type', value: 'application/json' });
    item.request.body = { mode: 'raw', raw: JSON.stringify(body, null, 2) };
  }
  if (!auth) {
    item.request.auth = { type: 'noauth' };
  } else {
    item.request.auth = {
      type: 'bearer',
      bearer: [{ key: 'token', value: '{{accessToken}}', type: 'string' }],
    };
  }
  if (tests.length) {
    item.event.push({
      listen: 'test',
      script: { type: 'text/javascript', exec: tests },
    });
  }
  if (prerequest.length) {
    item.event.push({
      listen: 'prerequest',
      script: { type: 'text/javascript', exec: prerequest },
    });
  }
  return item;
}

const collection = {
  info: {
    name: 'FalconCare API',
    description:
      'Colección alineada con Symfony. Ejecutar primero **auth → Login — JWT issued** con entorno **FalconCare Local**. Credenciales por defecto: fixtures (`admin@falconcare.com` / `admin123`).',
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  variable: [{ key: 'baseUrl', value: baseUrl }],
  item: [
    {
      name: 'health',
      item: [
        req('Health check', 'GET', '/api/health', {
          auth: false,
          tests: ["pm.test('Status 200', () => pm.response.to.have.status(200));"],
        }),
      ],
    },
    {
      name: 'auth',
      item: [
        req('Login — JWT issued', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: '{{adminEmail}}', password: '{{adminPassword}}' },
          tests: saveTokenScript.split('\n'),
        }),
        req('Login — Invalid credentials', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: 'noexiste@falconcare.com', password: 'wrong' },
          tests: invalidLoginScript.split('\n'),
        }),
        req('Login — Validation failed', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: '', password: '' },
          tests: validationLoginScript.split('\n'),
        }),
      ],
    },
    {
      name: 'patients',
      item: [
        req('List patients', 'GET', '/api/patients'),
        req('Get patient', 'GET', '/api/patients/{{patientId}}'),
        req('Patient appointments', 'GET', '/api/patients/{{patientId}}/appointments'),
        req('Patient documents', 'GET', '/api/patients/{{patientId}}/documents'),
      ],
    },
    {
      name: 'appointment',
      item: [
        req('Index by date', 'GET', '/api/appointment/index?date={{visitDate}}'),
        req('Weekly', 'GET', '/api/appointment/weekly?date={{visitDate}}'),
        req('Statuses', 'GET', '/api/appointment/statuses'),
        req('Setup form', 'GET', '/api/appointment/setup-appointment-form?date={{visitDate}}'),
        req('Doctors', 'GET', '/api/appointment/doctors'),
        req('Get appointment', 'GET', '/api/appointment/{{appointmentId}}'),
      ],
    },
    {
      name: 'documents',
      item: [
        req('List documents (patientId)', 'GET', '/api/documents?patientId={{patientId}}'),
      ],
    },
    {
      name: 'pathologies',
      item: [
        req('List pathologies', 'GET', '/api/pathologies'),
        req('Pathology types', 'GET', '/api/pathologies/types'),
      ],
    },
    {
      name: 'users',
      item: [
        req('List users (admin)', 'GET', '/api/users'),
        req('Get user', 'GET', '/api/users/1'),
      ],
    },
    {
      name: 'treatments',
      item: [req('By patient', 'GET', '/api/treatments/patient/{{patientId}}')],
    },
  ],
};

const out = join(__dirname, 'FalconCare-API.postman_collection.json');
writeFileSync(out, JSON.stringify(collection, null, 2), 'utf8');
console.log(`[postman] Written ${out}`);
