# Security, Privacy and Compliance Requirements

**FalconCare Backend** – Requisitos de seguridad, privacidad y cumplimiento para **datos de pacientes** y **subida de archivos**, incluyendo autenticación, control de acceso por roles (RBAC), cifrado, política de retención y consideraciones básicas GDPR en **contexto educativo**.

**Referencia:** Issue #4 – Security, Privacy and Compliance Requirements.

---

## Scope (alineado con el Issue #4)

Este documento define los requisitos de seguridad y privacidad exigidos en el issue, con la siguiente cobertura:

| Requisito del issue | Cubierto en |
|---------------------|-------------|
| **Patient data** (datos de pacientes) | §1.2 RBAC, §3 Retención/borrado, §4 Auditoría, §5.1 |
| **File uploads** (subida de archivos) | §2.2 Cifrado en reposo, §3 Retención/borrado, §5.2 |
| **Authentication** (autenticación) | §1.1 |
| **Role-based access control** (RBAC) | §1.2 |
| **Encryption** (cifrado en tránsito y en reposo) | §2 |
| **Retention policy** (política de retención) | §3.1, §3.3 |
| **Basic GDPR considerations** (consideraciones GDPR básicas) | §3.2, §5.3 |
| **Educational context** (contexto educativo) | §1.3, §3.1, §3.2, §5.3 |

---

## 1. Authentication and RBAC model

Definición del modelo de autenticación y del control de acceso basado en roles (RBAC), aplicable al acceso a **datos de pacientes** y recursos del sistema (incl. **archivos**).

### 1.1 Authentication

- **Mecanismo:** Autenticación basada en sesión o token (JWT/OAuth2) para la API.
- **Requisitos:**
  - Identificación única por usuario (login/email + contraseña o SSO en futuras fases).
  - Contraseñas almacenadas con hash seguro (algoritmo recomendado: Argon2id o bcrypt), nunca en claro.
  - Política de contraseñas: longitud mínima, complejidad y, en producción, rotación según política.
  - Sesiones con tiempo de expiración y renovación; invalidación en cierre de sesión.
  - En entorno educativo: credenciales de prueba claramente identificadas y no reutilizables en producción.

### 1.2 Role-Based Access Control (RBAC)

- **Roles definidos:**
  - **ROLE_ADMIN:** Gestión completa (usuarios, configuración, auditoría).
  - **ROLE_PROFESSIONAL / ROLE_DENTIST:** Acceso a pacientes asignados, historial clínico, odontogramas, citas y radiografías necesarias para su práctica.
  - **ROLE_STAFF / ROLE_RECEPTION:** Acceso a agenda, citas y datos básicos de pacientes (sin historial clínico sensible completo).
  - **ROLE_USER (opcional):** Acceso limitado a datos propios (ej. paciente que consulta su información).

- **Reglas de acceso:**
  - Los datos clínicos (historial, odontograma, radiografías) solo son accesibles según rol y, cuando aplique, asignación al paciente o al caso.
  - Principio de mínimo privilegio: cada rol solo accede a los recursos necesarios para su función.
  - Los endpoints de la API deben comprobar rol y, si aplica, pertenencia al recurso (paciente/clínica) antes de devolver o modificar datos.

### 1.3 Consideraciones para el contexto educativo

- Diferenciar claramente entornos de “desarrollo/educación” y “producción”.
- Datos de prueba anonimizados o sintéticos; no usar datos reales de pacientes en entornos educativos sin cumplimiento legal.

---

## 2. Encryption in transit and at rest

Especificación del cifrado en tránsito y en reposo para la API, la base de datos y los **archivos subidos** (radiografías, documentos).

### 2.1 Encryption in transit

- **HTTPS obligatorio** en todos los accesos al backend (TLS 1.2 como mínimo, recomendado TLS 1.3).
- Configuración del servidor web y/o proxy (Nginx, Apache, cloud) para:
  - Redirección HTTP → HTTPS.
  - Cifrado fuerte y desactivación de protocolos y cifrados obsoletos.
- La base de datos en la nube (Neon/PostgreSQL) debe ser accedida únicamente con conexiones que usen **SSL/TLS** (ej. `sslmode=require` en la cadena de conexión).

### 2.2 Encryption at rest

- **Base de datos:** Utilizar las capacidades del proveedor (Neon, cloud) para cifrado en reposo de datos y backups.
- **Archivos subidos (radiografías, documentos):**
  - Almacenamiento en un almacén (filesystem o objeto, ej. S3) con cifrado en reposo habilitado.
  - Opcional: cifrado adicional a nivel de aplicación para ficheros especialmente sensibles (claves gestionadas de forma segura, no en el código).
- **Secrets y configuración:** Variables de entorno y secretos (claves de API, `APP_SECRET`, credenciales de BD) nunca en el código ni en repositorios; uso de `.env.local` / secretos del entorno de despliegue.

---

## 3. Data retention and deletion policy

Política de retención y borrado de datos, aplicable a **datos de pacientes**, **archivos subidos** (radiografías, documentos) y logs; incluye consideraciones para el **contexto educativo**.

### 3.1 Retention (conservación)

- **Datos clínicos (historial, odontogramas, tratamientos):** Conservación según obligación legal aplicable (en España, normativa sanitaria y de historia clínica). En contexto educativo, definir un periodo de retención explícito (ej. duración del curso o X años desde última actividad).
- **Radiografías y archivos adjuntos:** Mismo criterio que los datos clínicos; periodo de retención documentado y alineado con la política de historia clínica.
- **Datos de auditoría y logs de acceso:** Conservación durante un periodo definido (ej. 1–2 años) para investigación de incidentes y cumplimiento, salvo que la ley exija más.

### 3.2 Deletion (derecho al olvido y limpieza)

- **Derecho de supresión (GDPR/ LOPDGDD):** Procedimiento para atender solicitudes de eliminación de datos personales:
  - Identificación del titular y verificación de la solicitud.
  - Eliminación o anonimización de datos personales en base de datos, historial, y archivos asociados (incluidas radiografías y documentos).
  - Respuesta al interesado en el plazo legal.
- **Anonimización:** Cuando la ley permita conservar datos para fines estadísticos o de mejora, usar anonimización irreversible en lugar de borrado simple.
- **Entorno educativo:** Al final del curso o proyecto, definir proceso de borrado o anonimización de datos de prueba y de cualquier dato personal utilizado en prácticas.

### 3.3 Backups

- Los backups deben respetar la misma política de retención y, cuando corresponda, ser eliminados o anonimizados según la política de supresión.

---

## 4. Access audit and logging requirements

Requisitos de auditoría de acceso y registro (logging), incluyendo accesos a **datos de pacientes** y a **archivos** (radiografías, documentos).

### 4.1 Audit trail (rastreabilidad)

- Registrar:
  - **Quién:** Identificador del usuario (o sistema) que realiza la acción.
  - **Qué:** Tipo de operación (lectura/creación/actualización/borrado) y recurso afectado (ej. paciente, visita, radiografía).
  - **Cuándo:** Marca temporal (UTC).
  - **Resultado:** Éxito o fallo (y código de error si aplica).
- Incluir en el ámbito de auditoría:
  - Acceso a datos de pacientes (consulta de historial, descarga de archivos).
  - Modificaciones en datos clínicos y en configuración sensible.
  - Accesos con privilegios elevados (administración).
  - Fallos de autenticación o autorización repetidos.

### 4.2 Logging

- **Logs de aplicación:** Errores, fallos de seguridad y eventos relevantes (autenticación, cambios de permisos, exportaciones masivas). Sin incluir datos clínicos completos ni contraseñas.
- **Logs de acceso HTTP:** Método, ruta, código de respuesta, y (según política) IP y user-agent; no registrar cuerpos de petición con datos personales o sanitarios.
- **Protección de logs:** Los ficheros y canales de log deben tener permisos restringidos y ser accesibles solo por personal autorizado; en producción, considerar envío a un sistema centralizado con control de acceso.

### 4.3 Revisión y respuesta

- Revisión periódica de logs y auditoría para detectar accesos anómalos o abusos.
- Procedimiento para actuar ante incidentes (escalado, análisis, medidas correctivas y, si aplica, notificación a autoridad o afectados).

---

## 5. Security requirements for patient data and file uploads

### 5.1 Datos de pacientes

- Tratamiento conforme a normativa de protección de datos (GDPR/LOPDGDD) y de historia clínica.
- Acceso solo por personal autorizado según RBAC y necesidad de conocimiento.
- Minimización de datos: recoger y conservar solo lo necesario para la finalidad del tratamiento.
- Integridad: controles (validación, transacciones, backups) para evitar pérdida o corrupción de datos.

### 5.2 Subida de archivos (radiografías, documentos)

- **Tipos permitidos:** Lista blanca de extensiones y tipos MIME (ej. imágenes médicas, PDF). Rechazar el resto.
- **Límites:** Tamaño máximo por fichero y por usuario/sesión para evitar abusos.
- **Validación:** Comprobar contenido (magic bytes) además de extensión; no confiar solo en el nombre de archivo.
- **Almacenamiento:** Fuera del árbol web público; servir mediante controlador que compruebe autenticación y autorización antes de devolver el archivo.
- **Nomenclatura y rutas:** Evitar nombres predecibles; usar identificadores únicos (UUID) para rutas de descarga.
- **Sanitización:** No ejecutar ni interpretar archivos subidos como código; almacenar como binarios y servir con cabeceras de contenido seguras.

### 5.3 Basic GDPR considerations for the educational context

*(Consideraciones básicas GDPR en contexto educativo — requisito explícito del Issue #4.)*

- **Base legal:** En entorno real, consentimiento o ejecución de contrato/prestación de servicios sanitarios; en **contexto educativo**, consentimiento explícito o uso de datos sintéticos/anónimos para no tratar datos reales de pacientes sin base legal adecuada.
- **Transparencia:** Informar a los usuarios (y en prácticas, a los “pacientes” de prueba) sobre qué datos se recogen, finalidad, plazo de conservación y derechos (GDPR arts. 13–14).
- **Derechos del interesado:** Procedimientos para ejercer acceso, rectificación, supresión, limitación del tratamiento, portabilidad y oposición, en los plazos legales (GDPR art. 12).
- **Registro de actividades de tratamiento:** Documentar categorías de datos, finalidades, destinatarios, plazos y medidas de seguridad (este documento sirve como base para ese registro en el ámbito del proyecto).
- **Contexto educativo:** Uso de datos de prueba o anonimizados; borrado o anonimización al final del curso; no reutilizar credenciales o datos educativos en producción.

---

## Checklist (Issue #4)

Cumplimiento estricto del checklist del issue:

- [x] **Define authentication and RBAC model** — §1 Authentication and RBAC model (autenticación §1.1, roles y reglas de acceso §1.2, contexto educativo §1.3).
- [x] **Specify encryption in transit and at rest** — §2 Encryption in transit and at rest (§2.1 tránsito: HTTPS/TLS; §2.2 reposo: BD, archivos subidos, secretos).
- [x] **Draft data retention and deletion policy** — §3 Data retention and deletion policy (retención §3.1, supresión/anonimización §3.2, backups §3.3; incluye datos de pacientes, archivos y contexto educativo).
- [x] **Document access audit and logging requirements** — §4 Access audit and logging requirements (audit trail §4.1, logging §4.2, revisión y respuesta §4.3; incluye acceso a datos de pacientes y archivos).
- [x] **Add security requirements to /docs/security.md** — Este archivo `docs/security.md` contiene todos los requisitos anteriores más §5 (patient data, file uploads y GDPR en contexto educativo).

---

## Database schema (sincronizado con la BD en la nube)

Para soportar estos requisitos en la base de datos (Neon/PostgreSQL), están creadas y aplicadas las siguientes tablas vía migraciones de Doctrine:

| Tabla | Propósito (Issue #4) |
|-------|----------------------|
| **user** | Autenticación y RBAC: email, roles (JSON), password (hash), is_active, created_at. |
| **audit_log** | Auditoría de acceso: user_id, action, resource_type, resource_id, ip, user_agent, created_at, success, result_code. Índices en created_at, user_id y (resource_type, resource_id) para consultas y política de retención. |

La migración `Version20260218175432` (Issue #4: User y AuditLog) se ha ejecutado contra la base de datos en la nube. Para aplicar o revisar migraciones: `php bin/console doctrine:migrations:migrate`.

---

## Compliance statement

Este documento cumple estrictamente con el enunciado del **Issue #4**:

- **Define** los requisitos de seguridad y privacidad para **patient data** (datos de pacientes) y **file uploads** (subida de archivos).
- **Incluye:** authentication (§1.1), role-based access control (§1.2), encryption in transit and at rest (§2), retention policy (§3), basic GDPR considerations for the educational context (§3.2, §5.3).

Los cinco ítems del **checklist** del issue están cumplidos y referenciados arriba.

---

*Documento vivo: revisar y actualizar con la evolución del proyecto y de la normativa aplicable. En caso de uso con datos reales de pacientes, es recomendable una revisión legal y por el responsable de protección de datos.*
