/**
 * Genera FalconCare-API.postman_collection.json alineada con /api/docs.json (OpenAPI 3).
 * Uso: node postman/generate-collection.mjs
 * Requiere API en marcha para validación opcional; la colección se genera siempre.
 */
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const baseUrl = (process.env.FALCONCARE_API_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const today = new Date().toISOString().slice(0, 10);

const collectionPrerequest = [
  "const today = new Date().toISOString().slice(0, 10);",
  "pm.environment.set('visitDate', today);",
  "pm.environment.set('documentCaptureDate', today);",
];

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
  "  pm.expect(json.user).to.have.property('email');",
  '});',
];

const invalidLoginScript = [
  "pm.test('Status 401', () => pm.response.to.have.status(401));",
  "pm.test('Invalid credentials', () => {",
  "  pm.expect(pm.response.json().error).to.eql('Invalid credentials');",
  '});',
];

const validationLoginScript = [
  "pm.test('Status 422', () => pm.response.to.have.status(422));",
  "pm.test('Validation failed', () => {",
  "  pm.expect(pm.response.json().error).to.eql('Validation failed');",
  '});',
];

const registerDoctorPrerequest = [
  "const uid = Date.now();",
  "pm.environment.set('registerDoctorEmail', `postman.dr.${uid}@falconcare.test`);",
];

const registerDoctorTests = [
  "pm.test('Status 201', () => pm.response.to.have.status(201));",
  "pm.test('Doctor user created', () => {",
  '  const json = pm.response.json();',
  "  pm.expect(json).to.have.property('id');",
  "  pm.expect(json.email).to.eql(pm.environment.get('registerDoctorEmail'));",
  "  pm.expect(json.roles).to.include('ROLE_DOCTOR');",
  '});',
];

const skipIfNoIdentityPrerequest = [
  "if (!pm.environment.get('identityDocument')) {",
  '  pm.execution.skipRequest();',
  '}',
];

const bootstrapPatientsScript = [
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  'const patientsPayload = pm.response.json();',
  'const patientsList = Array.isArray(patientsPayload) ? patientsPayload : [];',
  "pm.test('Patient list not empty', () => pm.expect(patientsList.length).to.be.above(0));",
  'if (patientsList.length) {',
  "  pm.environment.set('patientId', String(patientsList[0].id));",
  '  const withDoc = patientsList.find((p) => p.identityDocument);',
  '  if (withDoc?.identityDocument) {',
  "    pm.environment.set('identityDocument', withDoc.identityDocument);",
  '  }',
  '}',
];

const bootstrapAppointmentsScript = [
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  'const appointmentsPayload = pm.response.json();',
  "pm.test('Appointments array', () => pm.expect(appointmentsPayload).to.be.an('array'));",
  'if (Array.isArray(appointmentsPayload) && appointmentsPayload[0]?.id) {',
  "  pm.environment.set('appointmentId', String(appointmentsPayload[0].id));",
  '}',
];

const bootstrapDocumentsScript = [
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  'const documentsPayload = pm.response.json();',
  "const documentsList = Array.isArray(documentsPayload) ? documentsPayload : (documentsPayload.member || documentsPayload['hydra:member'] || []);",
  'if (documentsList[0]?.id) {',
  "  pm.environment.set('documentId', String(documentsList[0].id));",
  '}',
];

const bootstrapTreatmentsScript = [
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  'const treatmentsPayload = pm.response.json();',
  'const treatmentsList = Array.isArray(treatmentsPayload) ? treatmentsPayload : [];',
  'if (treatmentsList[0]?.id) {',
  "  pm.environment.set('treatmentId', String(treatmentsList[0].id));",
  '}',
];

const bootstrapUsersScript = [
  "pm.test('Status 200', () => pm.response.to.have.status(200));",
  'const users = pm.response.json();',
  "pm.test('Users array', () => pm.expect(users).to.be.an('array'));",
  "const admin = users.find((u) => (u.email || '').toLowerCase() === 'admin@falconcare.com') || users[0];",
  'if (admin?.id) {',
  "  pm.environment.set('adminUserId', String(admin.id));",
  '}',
];

const okJson = ["pm.test('Status 200', () => pm.response.to.have.status(200));"];

const ok2xx = ["pm.test('OK', () => pm.expect(pm.response.code).to.be.oneOf([200, 201, 204]));"];

function req(name, method, path, opts = {}) {
  const {
    auth = true,
    body = null,
    tests = ok2xx,
    prerequest = [],
    description = '',
  } = opts;
  const urlPath = path.startsWith('/') ? path : `/${path}`;
  const item = {
    name,
    request: {
      method,
      header: [{ key: 'Accept', value: 'application/json' }],
      url: `{{baseUrl}}${urlPath}`,
      description,
    },
    event: [],
  };
  if (body) {
    item.request.header.push({ key: 'Content-Type', value: 'application/json' });
    item.request.body = { mode: 'raw', raw: JSON.stringify(body, null, 2) };
  }
  item.request.auth = auth
    ? { type: 'bearer', bearer: [{ key: 'token', value: '{{accessToken}}', type: 'string' }] }
    : { type: 'noauth' };
  if (tests.length) {
    item.event.push({ listen: 'test', script: { type: 'text/javascript', exec: tests } });
  }
  if (prerequest.length) {
    item.event.push({ listen: 'prerequest', script: { type: 'text/javascript', exec: prerequest } });
  }
  return item;
}

const collection = {
  info: {
    name: 'FalconCare API',
    description:
      'Colección generada desde OpenAPI (/api/docs.json). Orden recomendado: **health** → **auth** (login guarda JWT) → resto.\n\n' +
      'Entorno: **FalconCare Local**. Fixtures: `admin@falconcare.com` / `admin123`.\n\n' +
      'Regenerar: `node postman/generate-collection.mjs` · Ejecutar: `node postman/run-newman.mjs`',
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  variable: [
    { key: 'baseUrl', value: baseUrl },
    { key: 'visitDate', value: today },
    { key: 'documentCaptureDate', value: today },
  ],
  event: [
    {
      listen: 'prerequest',
      script: { type: 'text/javascript', exec: collectionPrerequest },
    },
  ],
  item: [
    {
      name: '00 — health',
      description: 'Público, sin JWT.',
      item: [
        req('GET /api/health', 'GET', '/api/health', {
          auth: false,
          tests: [
            "pm.test('Status 200', () => pm.response.to.have.status(200));",
            "pm.test('status ok', () => pm.expect(pm.response.json().status).to.eql('ok'));",
          ],
        }),
      ],
    },
    {
      name: '01 — auth',
      description: 'Login JWT y casos de error. register-doctor es público.',
      item: [
        req('POST /api/auth/login — JWT issued', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: '{{adminEmail}}', password: '{{adminPassword}}' },
          tests: saveTokenScript,
          description: 'Guarda accessToken en el entorno.',
        }),
        req('POST /api/auth/login — Invalid credentials', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: 'noexiste@falconcare.com', password: 'wrong' },
          tests: invalidLoginScript,
        }),
        req('POST /api/auth/login — Validation failed', 'POST', '/api/auth/login', {
          auth: false,
          body: { email: '', password: '' },
          tests: validationLoginScript,
        }),
        req('POST /api/auth/register-doctor', 'POST', '/api/auth/register-doctor', {
          auth: false,
          body: {
            fullName: 'Postman Test Doctor',
            email: '{{registerDoctorEmail}}',
            password: 'postmanPass123',
          },
          prerequest: registerDoctorPrerequest,
          tests: registerDoctorTests,
          description: 'Email único por ejecución (variable registerDoctorEmail).',
        }),
      ],
    },
    {
      name: '02 — patients',
      item: [
        req('GET /api/patients — list (bootstrap ids)', 'GET', '/api/patients', {
          tests: bootstrapPatientsScript,
        }),
        req('GET /api/patients/{id}', 'GET', '/api/patients/{{patientId}}', { tests: okJson }),
        req('GET /api/patients/by-identity/{identityDocument}', 'GET', '/api/patients/by-identity/{{identityDocument}}', {
          prerequest: skipIfNoIdentityPrerequest,
          tests: [
            "pm.test('Status 200', () => pm.response.to.have.status(200));",
            "pm.test('Array result', () => pm.expect(pm.response.json()).to.be.an('array'));",
          ],
        }),
        req('GET /api/patients/{id}/appointments', 'GET', '/api/patients/{{patientId}}/appointments', {
          tests: bootstrapAppointmentsScript,
        }),
        req('GET /api/patients/{id}/documents', 'GET', '/api/patients/{{patientId}}/documents', {
          tests: bootstrapDocumentsScript,
        }),
      ],
    },
    {
      name: '03 — appointment',
      item: [
        req('GET /api/appointment/index', 'GET', '/api/appointment/index?date={{visitDate}}', { tests: okJson }),
        req('GET /api/appointment/weekly', 'GET', '/api/appointment/weekly?date={{visitDate}}', { tests: okJson }),
        req('GET /api/appointment/statuses', 'GET', '/api/appointment/statuses', { tests: okJson }),
        req('GET /api/appointment/setup-appointment-form', 'GET', '/api/appointment/setup-appointment-form?date={{visitDate}}', {
          tests: okJson,
        }),
        req('GET /api/appointment/doctors', 'GET', '/api/appointment/doctors', { tests: okJson }),
        req('GET /api/appointment/{id}', 'GET', '/api/appointment/{{appointmentId}}', { tests: okJson }),
        req('GET /api/appointment/{id}/open', 'GET', '/api/appointment/{{appointmentId}}/open', { tests: okJson }),
      ],
    },
    {
      name: '04 — documents',
      item: [
        req('GET /api/documents?patientId=…', 'GET', '/api/documents?patientId={{patientId}}', {
          tests: bootstrapDocumentsScript,
        }),
        req('GET /api/documents — missing patient (400)', 'GET', '/api/documents', {
          tests: [
            "pm.test('Status 400', () => pm.response.to.have.status(400));",
            "pm.test('Patient filter required', () => {",
            '  const json = pm.response.json();',
            "  pm.expect(json.code || json.error).to.be.ok;",
            '});',
          ],
          description: 'Listado global deshabilitado; debe exigir filtro de paciente.',
        }),
        req('GET /api/documents/captureDate', 'GET', '/api/documents/captureDate?patientId={{patientId}}&date={{documentCaptureDate}}', {
          tests: okJson,
        }),
        req('GET /api/documents/patient-docs/{identityDocument}', 'GET', '/api/documents/patient-docs/{{identityDocument}}', {
          prerequest: skipIfNoIdentityPrerequest,
          tests: okJson,
        }),
        req('GET /api/documents/{id}', 'GET', '/api/documents/{{documentId}}?patientId={{patientId}}', { tests: okJson }),
      ],
    },
    {
      name: '05 — pathologies',
      item: [
        req('GET /api/pathologies', 'GET', '/api/pathologies', { tests: okJson }),
        req('GET /api/pathologies/types', 'GET', '/api/pathologies/types', { tests: okJson }),
      ],
    },
    {
      name: '06 — treatments',
      item: [
        req('GET /api/treatments/patient/{id}', 'GET', '/api/treatments/patient/{{patientId}}', {
          tests: bootstrapTreatmentsScript,
        }),
        req('GET /api/treatments/{id}', 'GET', '/api/treatments/{{treatmentId}}', { tests: okJson }),
      ],
    },
    {
      name: '07 — users (admin)',
      item: [
        req('GET /api/users — list (bootstrap adminUserId)', 'GET', '/api/users', { tests: bootstrapUsersScript }),
        req('GET /api/users/{id}', 'GET', '/api/users/{{adminUserId}}', { tests: okJson }),
      ],
    },
  ],
};

const env = {
  id: 'falconcare-local-env',
  name: 'FalconCare Local',
  values: [
    { key: 'baseUrl', value: baseUrl, type: 'default', enabled: true },
    { key: 'accessToken', value: '', type: 'secret', enabled: true },
    { key: 'token', value: '', type: 'secret', enabled: true },
    { key: 'adminEmail', value: 'admin@falconcare.com', type: 'default', enabled: true },
    { key: 'adminPassword', value: 'admin123', type: 'secret', enabled: true },
    { key: 'registerDoctorEmail', value: '', type: 'default', enabled: true },
    { key: 'patientId', value: '1', type: 'default', enabled: true },
    { key: 'appointmentId', value: '1', type: 'default', enabled: true },
    { key: 'documentId', value: '1', type: 'default', enabled: true },
    { key: 'treatmentId', value: '1', type: 'default', enabled: true },
    { key: 'adminUserId', value: '1', type: 'default', enabled: true },
    { key: 'doctorId', value: '1', type: 'default', enabled: true },
    { key: 'boxId', value: '1', type: 'default', enabled: true },
    { key: 'identityDocument', value: '', type: 'default', enabled: true },
    { key: 'visitDate', value: today, type: 'default', enabled: true },
    { key: 'documentCaptureDate', value: today, type: 'default', enabled: true },
  ],
  _postman_variable_scope: 'environment',
};

writeFileSync(join(__dirname, 'FalconCare-API.postman_collection.json'), JSON.stringify(collection, null, 2), 'utf8');
writeFileSync(join(__dirname, 'FalconCare-Local.postman_environment.json'), JSON.stringify(env, null, 2), 'utf8');
console.log(`[postman] Collection + environment written (baseUrl=${baseUrl}, visitDate=${today})`);
