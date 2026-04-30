# README Angular - Estados de Cita (Appointment)

Guia para frontend Angular sobre el manejo de estados de cita con el backend.
Base route: `/api/appointment`.

## 1) Estados oficiales

Estos son los estados que puede devolver el backend:

- `Programada`
- `Confirmada`
- `En curs`
- `Arribada`
- `Cancelada`
- `Finalitzada`
- `Falta consentiment`

Recomendacion Angular: usar estas cadenas exactas para pintar estados. El desplegable manual solo debe enviar `Confirmada`, `Arribada` o `Cancelada`.

## 2) Flujo de estados

Flujo esperado de negocio:

- Alta de cita:
  - Por defecto: `Programada`
  - Si el paciente no tiene odontograma previo: `Falta consentiment`
- Gestion manual desde UI (desplegable):
  - Cambio via endpoint `/status` solo a `Confirmada`, `Arribada` o `Cancelada`
- Apertura de cita desde agenda:
  - Se fuerza `En curs` automaticamente en `GET /api/appointment/{id}/open`
- Cierre o finalizacion de cita:
  - Se marca `Finalitzada`

## 3) Endpoint para cambiar estado manualmente

Endpoint para cargar opciones:

- `GET /api/appointment/statuses`

Respuesta OK (200):

```json
{
  "ok": true,
  "code": "APPOINTMENT_STATUSES",
  "statuses": [
    "Programada",
    "Falta consentiment",
    "En curs",
    "Finalitzada",
    "Confirmada",
    "Arribada",
    "Cancelada"
  ],
  "manualStatuses": [
    "Confirmada",
    "Arribada",
    "Cancelada"
  ]
}
```

Angular debe usar `manualStatuses` para pintar el `<select>`.

Endpoint:

- `PATCH /api/appointment/{id}/status`
- `PUT /api/appointment/{id}/status`

Body soportado:

```json
{
  "status": "Confirmada"
}
```

Tambien acepta string JSON (`"Confirmada"`) y objeto con `stateName`.

Respuesta OK (200):

```json
{
  "ok": true,
  "code": "APPOINTMENT_STATUS_UPDATED",
  "messageKey": "appointment.status.updated",
  "id": 123,
  "status": "Confirmada",
  "appointment": {
    "id": 123,
    "status": "Confirmada"
  }
}
```

Errores importantes:

- JSON mal formado: `400`, `code = INVALID_JSON`
- Estado vacio/no string: `400`, `code = VALIDATION_ERROR`
- Estado fuera de lista manual: `400`, `code = INVALID_STATUS`

## 4) Apertura de cita y odontograma

Endpoint de apertura:

- `GET /api/appointment/{id}/open`

Comportamiento backend:

- Cambia el estado de la cita a `En curs`.
- Si el paciente no tiene odontograma, crea uno y lo enlaza.
- Devuelve JSON con `status`, `odontogramId` y `appointment.status` actualizados.

`POST /api/odontograms/open` solo crea o reutiliza odontograma. No cambia el estado de la cita; el estado lo controla agenda mediante `/api/appointment/{id}/open`.

## 5) Cierre / finalizacion de cita

Endpoints:

- `POST /api/appointment/{id}/close`
- `POST /api/appointment/{id}/finish`
- `PATCH /api/appointment/{id}/finish`

Comportamiento:

- Cambia estado a `Finalitzada`.
- Devuelve JSON con `status` y `appointment.status` actualizados.

## 6) Sugerencia de implementacion en Angular

```ts
export const APPOINTMENT_STATUSES = [
  'Programada',
  'Confirmada',
  'En curs',
  'Arribada',
  'Cancelada',
  'Finalitzada',
  'Falta consentiment'
] as const;

export const MANUAL_APPOINTMENT_STATUSES = [
  'Confirmada',
  'Arribada',
  'Cancelada'
] as const;

export type AppointmentStatus = typeof APPOINTMENT_STATUSES[number];
export type ManualAppointmentStatus = typeof MANUAL_APPOINTMENT_STATUSES[number];
```

```ts
updateStatus(id: number, status: ManualAppointmentStatus) {
  return this.http.patch(`/api/appointment/${id}/status`, { status });
}

getAppointmentStatuses() {
  return this.http.get<{
    statuses: AppointmentStatus[];
    manualStatuses: ManualAppointmentStatus[];
  }>(`/api/appointment/statuses`);
}

openAppointment(id: number) {
  return this.http.get(`/api/appointment/${id}/open`);
}
```

## 7) Checklist rapido frontend

- El select de estados debe usar solo valores de `MANUAL_APPOINTMENT_STATUSES`.
- Al recibir `INVALID_STATUS`, mostrar mensaje con la lista permitida del backend.
- En crear, editar, abrir, cerrar y finalizar, repintar usando `response.status` o `response.appointment.status`.
- Enviar JWT en `Authorization: Bearer <token>` cuando aplique.

## 8) QA - Criterios de aceptacion

- El desplegable de estado muestra exactamente los 3 estados manuales permitidos por backend.
- Al guardar desde el desplegable, el estado queda persistido y visible tras recargar agenda.
- Si se envia `En curs`, `Programada`, `Finalitzada` o `Falta consentiment` a `/status`, backend responde `INVALID_STATUS`.
- Al abrir cita desde agenda, la cita pasa a `En curs` y la respuesta trae el estado actualizado.
- Al cerrar o finalizar cita, la cita pasa a `Finalitzada` y la respuesta trae el estado actualizado.
