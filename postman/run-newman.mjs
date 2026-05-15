/**
 * Ejecuta la colección Postman con Newman (sin instalar globalmente).
 * Requiere: API en http://127.0.0.1:8000 y fixtures (admin@falconcare.com / admin123).
 *
 *   node postman/run-newman.mjs
 */
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const collection = join(__dirname, 'FalconCare-API.postman_collection.json');
const environment = join(__dirname, 'FalconCare-Local.postman_environment.json');

const args = [
  'newman',
  'run',
  collection,
  '-e',
  environment,
  '--delay-request',
  '150',
  '--color',
  'on',
];

const result = spawnSync('npx', args, {
  stdio: 'inherit',
  shell: true,
  cwd: join(__dirname, '..'),
});

process.exit(result.status ?? 1);
