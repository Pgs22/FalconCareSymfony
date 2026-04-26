# Documentacion Symfony -> Angular (Patient + Allergies)

## 1) Estado actual del backend

Se mantiene un unico controlador para Patient:

- `src/Controller/Api/PatientApiController.php`

Se elimino el controlador legacy:

- `src/Controller/PatientController.php`

Toda la operativa de paciente (listar, detalle, crear, actualizar, borrar, buscar por documento) sigue disponible via `/api/patients`.

## 2) Endpoints Patient vigentes

- `GET /api/patients`
- `GET /api/patients/{id}`
- `GET /api/patients/by-identity/{identityDocument}`
- `POST /api/patients`
- `PUT /api/patients/{id}`
- `PATCH /api/patients/{id}`
- `DELETE /api/patients/{id}`
- `GET /api/patients/{id}/documents`

Nota: actualmente no existe `POST /api/patients/new` en backend.

## 3) Cambio nuevo: allergies por bitmask

Ademas del campo de texto historico `medicationAllergies`, ahora existe el campo numerico `allergiesBitmask`.

Entidad:

- Columna DB: `patient.allergies_bitmask` (INT, default 0)
- Propiedad API: `allergiesBitmask`
- Propiedad API alternativa por lista: `selectedAllergies`

## 4) Catalogo de flags (potencias de 2)

- `1` -> `ALLERGY_PENICILLIN`
- `2` -> `ALLERGY_LATEX`
- `4` -> `ALLERGY_ANESTHESIA`
- `8` -> `ALLERGY_NSAIDS`
- `16` -> `ALLERGY_CHLORHEXIDINE`

Ejemplo:

- Penicillin (`1`) + Anesthesia (`4`) => bitmask `5`

Formula:

- `bitmask = flag1 | flag2 | ...`

## 5) Contrato request (POST/PUT/PATCH)

Se mantiene compatibilidad con alergias en texto y se anade el bitmask.

### 5.1 Campos de alergias aceptados

Texto (legacy):

- `medicationAllergies`
- `medication_allergies`

Regla: si envias ambos, deben tener el mismo valor.

Bitmask (nuevo):

- `allergiesBitmask` (numero entero)
- `selectedAllergies` (array de flags enteros)

Regla: si envias `selectedAllergies`, backend calcula `allergiesBitmask` automaticamente.

### 5.2 Ejemplo create/update

```json
{
  "identityDocument": "12345678A",
  "firstName": "Ada",
  "lastName": "Lovelace",
  "phone": "600000000",
  "email": "ada@example.com",
  "address": "Street 1",
  "consultationReason": "Revision",
  "familyHistory": "None",
  "healthStatus": "Good",
  "lifestyleHabits": "Healthy",
  "medicationAllergies": "PENICILLIN, LATEX",
  "medication_allergies": "PENICILLIN, LATEX",
  "allergiesBitmask": 3,
  "selectedAllergies": [1, 2]
}
```

## 6) Contrato response (Patient)

En respuestas de paciente ahora vienen:

- `medicationAllergies`
- `medication_allergies`
- `allergiesBitmask`
- `selectedAllergies`

Y se mantiene compatibilidad de profile image:

- `profile_image`
- `profile_image_url`
- `profileImage`
- `profileImageUrl`

## 7) Instrucciones para Angular

## 7.1 Modelo

Anadir al modelo de Patient:

- `allergiesBitmask?: number`
- `selectedAllergies?: number[]`

## 7.2 Escritura (toApiPatientBody)

Cuando construyas payload para create/update:

1. Mantener el envio de `medicationAllergies` y `medication_allergies` (compatibilidad actual).
2. Enviar ademas:
- `allergiesBitmask`
- `selectedAllergies`

Recomendacion:

- Si UI trabaja con checkboxes por alergia, generar `selectedAllergies` y `allergiesBitmask` en el front.
- Si UI solo guarda el entero, enviar al menos `allergiesBitmask`.

## 7.3 Lectura (adaptPatient)

Prioridad recomendada:

1. Si existe `selectedAllergies`, usarla como fuente principal.
2. Si no existe pero hay `allergiesBitmask`, derivar lista localmente.
3. Mantener `medicationAllergies` para mostrar texto legacy en pantallas existentes.

## 7.4 Helpers TypeScript sugeridos

```ts
export const AllergyFlag = {
  PENICILLIN: 1,
  LATEX: 2,
  ANESTHESIA: 4,
  NSAIDS: 8,
  CHLORHEXIDINE: 16,
} as const;

export function buildAllergiesBitmask(selected: number[]): number {
  return selected.reduce((mask, flag) => mask | flag, 0);
}

export function selectedFromBitmask(mask: number): number[] {
  const all = [
    AllergyFlag.PENICILLIN,
    AllergyFlag.LATEX,
    AllergyFlag.ANESTHESIA,
    AllergyFlag.NSAIDS,
    AllergyFlag.CHLORHEXIDINE,
  ];
  return all.filter((flag) => (mask & flag) === flag);
}
```

## 8) Checklist rapido para integrar sin romper

1. Usar solo rutas `/api/patients` (no `/patient/*`).
2. Mantener la compatibilidad de texto en `medicationAllergies`.
3. Anadir soporte a `allergiesBitmask` y `selectedAllergies` en create/update/read.
4. Si existe codigo que llama `/api/patients/new`, migrarlo a `POST /api/patients`.
