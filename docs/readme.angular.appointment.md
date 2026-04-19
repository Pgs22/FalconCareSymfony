# README Angular - Integracion de Appointment API

Guia de contrato backend para Angular sobre citas.
Base route del controlador: `/api/appointment`.

## 1) Endpoints principales

- `GET /api/appointment/index?date=YYYY-MM-DD`
- `GET /api/appointment/weekly?date=YYYY-MM-DD`
- `POST /api/appointment/new`
- `PUT /api/appointment/{id}/update`
- `POST /api/appointment/{id}/update`
- `PATCH /api/appointment/{id}/status`
- `PUT /api/appointment/{id}/status`
- `GET /api/appointment/{id}`
- `POST /api/appointment/{id}/close`
- `DELETE /api/appointment/{id}`
- `DELETE /api/appointment/{id}/delete` (legacy)
- `POST /api/appointment/{id}/delete` (legacy)

## 2) Guardar cita (create/update)

En create y update se aceptan:

- Duracion visible:
  - `durationMinutes`
  - o `duration`
- Buffer limpieza de box:
  - `cleaningMinutes`
  - o `cleaningTime`
  - o `cleaning_time`

Tambien puedes enviar `totalBlockTime`, pero backend calcula bloque real con duracion + limpieza.

### Valores permitidos para limpieza

`cleaningMinutes` solo permite: `5`, `10`, `15`.

Si llega otro valor, backend responde `400` con:

- `code: VALIDATION_ERROR`
- `error.field: cleaningMinutes`
- `error.messageKey: appointment.cleaning_minutes.invalid`
- `error.allowedValues: [5, 10, 15]`

## 3) Estado de cita (endpoint status)

Endpoint: `PATCH|PUT /api/appointment/{id}/status`

Formatos aceptados:

- body string JSON: `"Confirmada"`
- body objeto: `{ "status": "Confirmada" }`
- body objeto: `{ "stateName": "Confirmada" }`

Estados permitidos por este endpoint:

- `Confirmada`
- `En curs`
- `Cancel·lada`

Si no es valido, responde `400` con `code: INVALID_STATUS`.

Nota: otros estados internos pueden existir en backend (`Programada`, `Falta Consentiment`, `Finalitzada`) pero no se permiten en este endpoint de cambio manual.

## 4) Primera visita

Al crear cita, si el paciente tiene `lastOdontogramId = null`, backend asigna estado inicial:

- `Falta Consentiment`

Si no, el estado inicial por defecto es:

- `Programada`

## 5) Campos utiles en respuesta de listado

`GET /api/appointment/index` y `GET /api/appointment/weekly` devuelven por cita:

- `duration` (duracion clinica visible)
- `cleaningTime`
- `cleaning_time`
- `cleaningMinutes`
- `totalBlockTime`

## 6) Importante sobre persistencia del buffer

Actualmente **no** hay columna dedicada en DB para `cleaningMinutes`.

Consecuencia:

- El valor enviado (`5`, `10`, `15`) se usa en el flujo de guardado/validacion de solapes.
- No se garantiza persistencia historica por cita tras recarga desde BD.

Recomendacion frontend:

- Enviar siempre `cleaningMinutes` en cada create/update.

## 7) Ejemplo de payload create/update

```json
{
  "visitDate": "2026-04-20",
  "visitTime": "10:30",
  "consultationReason": "Control",
  "observations": "",
  "patient": 12,
  "doctor": 3,
  "box": 2,
  "treatment": 9,
  "duration": 30,
  "durationMinutes": 30,
  "cleaningTime": 5,
  "cleaning_time": 5,
  "cleaningMinutes": 5,
  "totalBlockTime": 35
}
```

## 8) Auth

Las rutas de appointment deben ir con JWT en `Authorization: Bearer <token>`.

Si falta token, veras `401 Unauthorized`.
