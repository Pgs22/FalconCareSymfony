# README Angular - Estados de Cita (Appointment)

Guia para frontend Angular sobre el manejo de estados de cita con el backend.
Base route: `/api/appointment`.

## 1) Estados oficiales permitidos

Estos son los estados validos en backend:

- `Programada`
- `Confirmada`
- `En curs`
- `Cancel·lada`
- `Finalitzada`
- `Falta Consentiment`

Recomendacion Angular: usar estas cadenas exactas (incluyendo mayusculas y acentos) en el desplegable.

## 2) Flujo de estados

Flujo esperado de negocio:

- Alta de cita:
  - Por defecto: `Programada`
  - Si el paciente no tiene odontograma previo: `Falta Consentiment`
- Gestion manual desde UI (desplegable):
  - Cambio via endpoint `/status`
- Apertura de cita (abrir odontograma):
  - Se fuerza `En curs` automaticamente en backend
- Cierre de cita:
  - Se marca `Finalitzada`

## 3) Endpoint para cambiar estado manualmente

Endpoint:

- `PATCH /api/appointment/{id}/status`
- `PUT /api/appointment/{id}/status`

Body soportado (3 formatos):

1. String JSON:

```json
"Confirmada"
```

2. Objeto con `status`:

```json
{
  "status": "Confirmada"
}
```

3. Objeto con `stateName`:

```json
{
  "stateName": "Confirmada"
}
```

Respuesta OK (200):

```json
{
  "ok": true,
  "code": "APPOINTMENT_STATUS_UPDATED",
  "messageKey": "appointment.status.updated",
  "id": 123,
  "status": "Confirmada"
}
```

Errores importantes:

- JSON mal formado:
  - `400`
  - `code: INVALID_JSON`
- Estado vacio/no string:
  - `400`
  - `code: VALIDATION_ERROR`
- Estado fuera de lista permitida:
  - `400`
  - `code: INVALID_STATUS`

## 4) Apertura de cita y odontograma

Endpoint de apertura:

- `GET /api/appointment/{id}/open`

Comportamiento backend actual:

- Cambia el estado de la cita a `En curs`.
- Si el paciente no tiene odontograma, crea uno y lo enlaza.
- Redirige a la vista de odontograma.

Implicacion para Angular:

- Tras abrir cita, refrescar la agenda/lista para pintar el nuevo estado `En curs`.

## 5) Cierre de cita

Endpoint:

- `POST /api/appointment/{id}/close`

Comportamiento:

- Cambia estado a `Finalitzada`.

## 6) Sugerencia de implementacion en Angular

### Modelo de estados

```ts
export const APPOINTMENT_STATUSES = [
  'Programada',
  'Confirmada',
  'En curs',
  'Cancel·lada',
  'Finalitzada',
  'Falta Consentiment'
] as const;

export type AppointmentStatus = typeof APPOINTMENT_STATUSES[number];
```

### Actualizar estado desde desplegable

```ts
updateStatus(id: number, status: AppointmentStatus) {
  return this.http.patch(`/api/appointment/${id}/status`, { status });
}
```

### Abrir cita

```ts
openAppointment(id: number) {
  // Backend redirige a odontograma y marca estado En curs.
  window.location.href = `/api/appointment/${id}/open`;
}
```

## 7) Checklist rapido frontend

- El select de estados debe usar solo valores de `APPOINTMENT_STATUSES`.
- Al recibir `INVALID_STATUS`, mostrar mensaje con la lista permitida del backend.
- Al abrir cita (`/open`), refrescar agenda al volver para reflejar `En curs`.
- Al cerrar (`/close`), actualizar inmediatamente el estado local a `Finalitzada`.
- Enviar JWT en `Authorization: Bearer <token>`.

## 8) QA - Criterios de aceptacion

- El desplegable de estado muestra exactamente los 6 estados permitidos por backend.
- Al guardar desde el desplegable, el estado queda persistido y visible tras recargar agenda.
- Si el body viene con JSON invalido, la UI no rompe y muestra error controlado.
- Si se envia un estado no permitido, la UI muestra mensaje de validacion y no cambia estado local.
- Al usar Abrir cita, la cita pasa a `En curs` al refrescar la vista.
- Al usar Cerrar cita, la cita pasa a `Finalitzada` y desaparece cualquier accion de apertura en UI.

## 9) Casos de prueba sugeridos

### Caso 1 - Cambio manual valido (objeto con status)

Precondicion:

- Cita existente en estado `Programada`.

Accion:

- `PATCH /api/appointment/{id}/status` con body:

```json
{
  "status": "Confirmada"
}
```

Resultado esperado:

- HTTP 200.
- `ok = true`.
- `status = Confirmada`.

### Caso 2 - Cambio manual valido (string JSON)

Accion:

- `PATCH /api/appointment/{id}/status` con body:

```json
"En curs"
```

Resultado esperado:

- HTTP 200.
- Estado actualizado a `En curs`.

### Caso 3 - JSON invalido

Accion:

- `PATCH /api/appointment/{id}/status` con body mal formado:

```json
{ "status": "Confirmada"
```

Resultado esperado:

- HTTP 400.
- `code = INVALID_JSON`.
- La UI muestra mensaje de error y mantiene el valor anterior.

### Caso 4 - Estado no permitido

Accion:

- `PATCH /api/appointment/{id}/status` con body:

```json
{
  "status": "Reprogramada"
}
```

Resultado esperado:

- HTTP 400.
- `code = INVALID_STATUS`.
- Respuesta incluye `allowedStatuses`.

### Caso 5 - Abrir cita

Accion:

- Navegar a `GET /api/appointment/{id}/open` desde boton Abrir cita.

Resultado esperado:

- Redireccion a odontograma.
- Estado de cita en agenda pasa a `En curs` al refrescar.

### Caso 6 - Cerrar cita

Accion:

- `POST /api/appointment/{id}/close`.

Resultado esperado:

- HTTP 200.
- Estado final `Finalitzada`.
- Front oculta boton Abrir cita y/o acciones no permitidas para cita cerrada.
