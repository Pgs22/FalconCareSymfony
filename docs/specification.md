# Especificación funcional — FalconCare (basada en wireframes)

Este documento desglosa todas las funcionalidades del sistema a partir de los cuatro wireframes: **Ventana inicial (Panel de Control)**, **Pantalla de paciente (Expediente)**, **Pantalla de odontograma** y **Pantalla de agenda**. Cada feature se relaciona con la pantalla correspondiente y se describe con: qué hace, quién la usa, inputs/outputs y reglas de negocio.

---

## Referencia de wireframes

| ID | Pantalla | Título en wireframe | Contexto |
|----|----------|---------------------|----------|
| **W1** | Ventana inicial | Panel de Control con Agenda por Box y Alertas (DentalFlow) | Dashboard principal tras login |
| **W2** | Pantalla de paciente | Edición de Datos de Contacto / Expediente Integral (DentalBoard) | Ficha de un paciente concreto |
| **W3** | Pantalla de odontograma | Explorador de Odontograma Detallado (DentalHub) | Odontograma por visita/paciente |
| **W4** | Pantalla de agenda | Agenda Dental - Gestión de Citas por Box (DentalEase) | Calendario por día/box |

---

## 1. Funcionalidades del Panel de Control (W1)

### 1.1 Navegación principal (sidebar)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Muestra el menú fijo con: Panel de Control (activo), Agenda, Pacientes, Inventario, Configuración. Incluye branding (logo, nombre “DentalFlow”, tipo “Educación”) y perfil del usuario logueado (foto, nombre, rol). |
| **Quién la usa** | Usuario autenticado (ej. Dra. Chen, Endodoncista). |
| **Inputs** | Clic en cada enlace de navegación. |
| **Outputs** | Cambio de vista a la sección elegida; elemento activo resaltado (Panel de Control). |
| **Reglas de negocio** | El ítem activo debe estar claramente diferenciado; la navegación es persistente en todas las vistas derivadas. |

**Wireframe:** W1 (sidebar izquierdo).

---

### 1.2 Resumen general y saludo contextual

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Muestra un título de resumen (“Resumen General”), saludo personalizado (“Buenos días, Dra. Chen”) y una frase de contexto (“Aquí está el resumen de su práctica diaria”). |
| **Quién la usa** | Usuario autenticado (médico/staff). |
| **Inputs** | Sesión del usuario (nombre, rol). |
| **Outputs** | Texto de saludo y subtítulo en el header del panel. |
| **Reglas de negocio** | El saludo debe usar el nombre y/o rol del usuario logueado; el mensaje es informativo, no editable. |

**Wireframe:** W1 (header y sección superior del contenido).

---

### 1.3 Búsqueda global

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Permite buscar en un único campo por “pacientes, registros, citas…”. El campo tiene icono de lupa y placeholder descriptivo. |
| **Quién la usa** | Usuario autenticado. |
| **Inputs** | Texto introducido por el usuario en el campo de búsqueda. |
| **Outputs** | (En el wireframe no se define resultado; se asume listado o redirección a resultados de pacientes/registros/citas). |
| **Reglas de negocio** | La búsqueda debe abarcar al menos pacientes, registros y citas; el ámbito puede depender del rol. |

**Wireframe:** W1 (header, barra de búsqueda).

---

### 1.4 Notificaciones

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Muestra un botón de notificaciones con icono y un indicador visual (punto rojo) cuando hay notificaciones sin leer. |
| **Quién la usa** | Usuario autenticado. |
| **Inputs** | Clic en el botón. |
| **Outputs** | (En el wireframe no se muestra el panel desplegable; se asume lista de notificaciones). |
| **Reglas de negocio** | Debe existir un estado “tiene notificaciones” que active el indicador; las notificaciones son propias del usuario. |

**Wireframe:** W1 (header, icono campana).

---

### 1.5 Acceso rápido a nuevo paciente

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Botón principal “Nuevo Paciente” con icono “add” que permite iniciar el flujo de alta de paciente. |
| **Quién la usa** | Usuario con permiso para dar de alta pacientes (médico/staff/admin). |
| **Inputs** | Clic en “Nuevo Paciente”. |
| **Outputs** | Navegación o apertura de formulario/modal de nuevo paciente. |
| **Reglas de negocio** | Solo usuarios autorizados pueden crear pacientes; el flujo debe ser accesible desde el panel. |

**Wireframe:** W1 (header, botón “Nuevo Paciente”).

---

### 1.6 KPIs: Pacientes hoy

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Tarjeta que muestra el número de “Pacientes Hoy” (ej. 12) y una variación (ej. “+4%”). Incluye icono (grupo) y etiqueta “Pacientes Hoy”. |
| **Quién la usa** | Usuario autenticado (médico/staff). |
| **Inputs** | Datos agregados de citas del día actual. |
| **Outputs** | Número total de pacientes con cita hoy; variación porcentual respecto a un periodo de referencia. |
| **Reglas de negocio** | “Pacientes hoy” = pacientes con al menos una cita en el día actual; la variación debe estar definida (ej. mismo día semana anterior). |

**Wireframe:** W1 (tarjeta “Pacientes Hoy”).

---

### 1.7 KPIs: Resultados pendientes

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Tarjeta que muestra “Resultados Pendientes” (ej. 3) con etiqueta “Acción Requerida” y icono (biotech). |
| **Quién la usa** | Usuario autenticado (médico/staff). |
| **Inputs** | Registros de resultados (ej. laboratorio, informes) en estado “pendiente”. |
| **Outputs** | Número de resultados pendientes de revisar o completar. |
| **Reglas de negocio** | Un resultado está “pendiente” según criterio clínico o de flujo (ej. no revisado, no cerrado); requiere acción del profesional. |

**Wireframe:** W1 (tarjeta “Resultados Pendientes”).

---

### 1.8 KPIs: Alertas de stock bajo

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Tarjeta que muestra “Alertas de Stock Bajo” (ej. 1) con icono de inventario. |
| **Quién la usa** | Usuario autenticado (staff/admin, posiblemente médico). |
| **Inputs** | Niveles de inventario comparados con umbrales de “stock bajo”. |
| **Outputs** | Número de alertas activas de stock bajo. |
| **Reglas de negocio** | Una alerta se genera cuando el stock de un ítem cae bajo el umbral definido; el número es la cantidad de ítems o de alertas según diseño. |

**Wireframe:** W1 (tarjeta “Alertas de Stock Bajo”).

---

### 1.9 Agenda del día (lista)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Lista la “Agenda de Hoy” con franjas horarias. Cada fila puede ser: (a) cita con paciente (nombre, box, doctor, tratamiento, duración, estado “En Curso”/“Confirmado”/“Llegada”) o (b) hueco “Vacío” con opción “Asignar Turno”. Incluye enlace “Ver calendario completo”. |
| **Quién la usa** | Usuario autenticado (médico/staff). |
| **Inputs** | Fecha del día actual; citas del día asociadas a boxes y doctores. |
| **Outputs** | Lista ordenada por hora: hora inicio, duración, paciente, box, doctor, tratamiento, estado. Huecos vacíos identificables y accionables. |
| **Reglas de negocio** | Las citas se muestran en el orden cronológico del día; los huecos sin cita permiten asignar nuevo turno; los estados (En Curso, Confirmado, Llegada) siguen reglas de flujo definidas. |

**Wireframe:** W1 (bloque “Agenda de Hoy”).

---

### 1.10 Acciones por cita en el panel (menú más opciones)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | En cada fila de cita hay un botón “more_vert” que abre un menú de acciones sobre esa cita (editar, cancelar, etc., según diseño). |
| **Quién la usa** | Usuario autenticado con permiso para gestionar citas. |
| **Inputs** | Clic en el botón de opciones; selección de acción. |
| **Outputs** | Ejecución de la acción (editar cita, cancelar, cambiar estado, etc.). |
| **Reglas de negocio** | Las acciones disponibles pueden depender del estado de la cita y del rol; cancelar puede requerir confirmación o motivo. |

**Wireframe:** W1 (botón “more_vert” en cada cita).

---

### 1.11 Asignar turno en hueco vacío

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | En una fila “Vacío” de la agenda del día, un enlace/botón “Asignar Turno” permite crear una nueva cita en ese hueco (hora y box ya sugeridos). |
| **Quién la usa** | Usuario con permiso para crear/gestión de citas. |
| **Inputs** | Clic en “Asignar Turno”; hora y box del hueco. |
| **Outputs** | Apertura de formulario o vista de creación de cita con fecha/hora/box prefijados. |
| **Reglas de negocio** | El hueco debe corresponder a un box y una franja horaria válida; no se puede asignar si el slot está ocupado. |

**Wireframe:** W1 (filas “Vacío” con “Asignar Turno”).

---

### 1.12 Alertas de alergias del día

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Bloque “Alertas de Alergias” que lista pacientes con cita hoy que tienen alergias registradas. Cada ítem muestra: nombre del paciente, hora de la cita, tipo de alergia (ej. Penicilina, Látex, Reacción anestesia). Se distinguen niveles (ej. rojo crítico / ámbar sensibilidad). Botón “Ver más alertas del día”. |
| **Quién la usa** | Médico y staff (seguridad del paciente). |
| **Inputs** | Citas del día; datos de alergias/medicación de cada paciente. |
| **Outputs** | Lista de pacientes con alergia y hora de cita; clasificación visual (rojo/ámbar). |
| **Reglas de negocio** | Solo se muestran pacientes con cita ese día; las alergias deben estar registradas en el expediente; la severidad puede determinar el color (crítico vs. precaución). |

**Wireframe:** W1 (panel “Alertas de Alergias”).

---

### 1.13 Estado del stock por sala/caja

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Bloque “Estado del Stock” con barras de progreso por “Caja” (ej. Caja 1 Tratamiento 82%, Caja 2 Higiene 18%). Incluye texto explicativo (ej. “Bajo en Composite”) y botón “Solicitud de Reabastecimiento”. Tooltip/info: “Monitorear niveles de inventario por sala de tratamiento”. |
| **Quién la usa** | Staff y/o admin (gestión de inventario). |
| **Inputs** | Niveles de stock por caja/sala; umbrales para “saludable” vs “bajo”. |
| **Outputs** | Porcentaje por caja; indicación de “suministros saludables” o “bajo en [producto]”; acción de solicitud de reabastecimiento. |
| **Reglas de negocio** | El porcentaje debe calcularse respecto a un máximo o ideal por caja; bajo umbral se considera “stock bajo” y se muestra alerta; la solicitud de reabastecimiento inicia un flujo interno (no definido en detalle en el wireframe). |

**Wireframe:** W1 (panel “Estado del Stock”).

---

## 2. Funcionalidades de la Pantalla de Paciente / Expediente (W2)

### 2.1 Navegación y branding (header)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Header con logo, nombre “DentalBoard”, búsqueda “Buscar pacientes…”, botón “Nuevo Paciente”, notificaciones y avatar del usuario. |
| **Quién la usa** | Usuario autenticado. |
| **Inputs** | Búsqueda; clic en Nuevo Paciente / notificaciones / perfil. |
| **Outputs** | Navegación o resultados de búsqueda. |
| **Reglas de negocio** | Consistente con el resto de la aplicación; la búsqueda en header puede ser global de pacientes. |

**Wireframe:** W2 (header).

---

### 2.2 Perfil resumido del paciente (sidebar)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | En el lateral se muestra foto, nombre completo (ej. Sarah Jenkins), ID y edad (ej. #928374, 24 años), y estado “Tratamiento Activo”. |
| **Quién la usa** | Usuario que visualiza el expediente. |
| **Inputs** | Datos del paciente seleccionado (identidad, estado de tratamiento). |
| **Outputs** | Visualización fija del paciente actual en la sección. |
| **Reglas de negocio** | El ID debe ser único; “Tratamiento Activo” es un estado definido (ej. tiene plan de tratamiento no cerrado). |

**Wireframe:** W2 (sidebar izquierdo, perfil).

---

### 2.3 Navegación interna del expediente

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Menú lateral: Expediente Integral (activo), Odontogramas, Gestión de Archivos, Citas y Visitas, Facturación. Botón “Cerrar Sesión” al pie. |
| **Quién la usa** | Usuario que consulta/edita el expediente. |
| **Inputs** | Clic en cada sección. |
| **Outputs** | Cambio de contenido dentro del expediente (misma pantalla, distintas secciones). |
| **Reglas de negocio** | Cada ítem lleva a la vista correspondiente del mismo paciente; “Expediente Integral” agrupa datos de contacto, alergias, enfermedades, historial de visitas. |

**Wireframe:** W2 (sidebar, navegación).

---

### 2.4 Aviso de registro incompleto y primera visita

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Banner de aviso: “Registro de Nuevo Paciente Incompleto” con texto “Debe agendar la primera visita para activar el expediente” y botón “Agendar Primera Visita”. |
| **Quién la usa** | Usuario que gestiona el expediente (médico/staff). |
| **Inputs** | Estado del paciente (registro incompleto = sin primera visita agendada/completada). |
| **Outputs** | Aviso visible y acción “Agendar Primera Visita”. |
| **Reglas de negocio** | El expediente se considera “incompleto” hasta que exista (y opcionalmente se haya realizado) la primera visita; agendar primera visita es un flujo obligatorio para activar el expediente. |

**Wireframe:** W2 (banner amarillo).

---

### 2.5 Título y acciones de expediente (Guardar / Exportar PDF)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Título “Expediente Integral del Paciente” con descripción, y botones “Exportar PDF” y “Guardar Cambios”. |
| **Quién la usa** | Usuario que edita o consulta el expediente. |
| **Inputs** | Clic en “Guardar Cambios” (persiste cambios del formulario); clic en “Exportar PDF”. |
| **Outputs** | Guardado de datos en BD; descarga de PDF del expediente (contenido a definir). |
| **Reglas de negocio** | Solo se permite guardar si hay cambios válidos; el PDF debe incluir datos permitidos por ley (LOPD) y solo para uso autorizado. |

**Wireframe:** W2 (cabecera del contenido principal).

---

### 2.6 Alergias críticas (gestión)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Bloque “Alergias Críticas” con lista de alergias (ej. Penicilina, Látex). Cada ítem tiene opción de eliminar (icono cerrar). Botón “+” para añadir nueva alergia. |
| **Quién la usa** | Médico o staff autorizado. |
| **Inputs** | Lista actual de alergias; acción añadir (nombre/tipo); acción eliminar (ítem). |
| **Outputs** | Lista actualizada de alergias críticas; persistencia en el expediente. |
| **Reglas de negocio** | Las alergias críticas deben mostrarse de forma muy visible; añadir/eliminar debe quedar registrado (auditoría); no se debe permitir eliminar sin confirmación si hay uso clínico. |

**Wireframe:** W2 (tarjeta “Alergias Críticas”).

---

### 2.7 Datos de contacto (edición)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Sección “Datos de Contacto” con campos: Teléfono Móvil, Correo Electrónico, Dirección. Botón “Editar” para pasar a modo edición; botón “Confirmar Datos” para validar y guardar. |
| **Quién la usa** | Staff o médico (actualización de datos del paciente). |
| **Inputs** | Valores actuales; usuario modifica teléfono, email, dirección. |
| **Outputs** | Datos de contacto guardados; posible validación (formato teléfono, email). |
| **Reglas de negocio** | Email y teléfono deben ser válidos; los cambios deben persistirse y quedar en historial si se requiere trazabilidad. |

**Wireframe:** W2 (bloque “Datos de Contacto”).

---

### 2.8 Enfermedades y condiciones (checklist)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Sección “Enfermedades y Condiciones” con checklist (ej. Diabetes Tipo 2, Hipertensión, Asma, Tabaquismo). Cada ítem puede tener nota (ej. “En control farmacológico”, “Sin antecedentes reportados”). |
| **Quién la usa** | Médico o staff. |
| **Inputs** | Selección/deselección de condiciones; notas por condición. |
| **Outputs** | Lista de condiciones activas/inactivas y notas asociadas guardadas en el expediente. |
| **Reglas de negocio** | Las condiciones son listas predefinidas o configurables; las notas son texto libre con posible límite de longitud. |

**Wireframe:** W2 (bloque “Enfermedades y Condiciones”).

---

### 2.9 Historial de visitas (lista y odontograma por visita)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | “Historial de Visitas” con lista cronológica de visitas. Cada visita muestra: título del tratamiento (ej. Tratamiento de Conducto, Limpieza y Revisión), fecha, descripción breve, botones “Ver Odontograma de Visita” y “Notas Médicas”. Botón “Ver Todo el Historial”. |
| **Quién la usa** | Médico o staff. |
| **Inputs** | Clic en “Ver Odontograma de Visita” o “Notas Médicas”; clic en “Ver Todo el Historial”. |
| **Outputs** | Navegación al odontograma de esa visita o a las notas; listado completo del historial. |
| **Reglas de negocio** | Cada visita puede tener un odontograma asociado; el historial se ordena por fecha (más reciente primero). |

**Wireframe:** W2 (bloque “Historial de Visitas”).

---

### 2.10 Gestión de archivos y radiografías

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Sección “Gestión de Archivos y Radiografías” con: botones “Ver Historial de Radiografías”, “Filtrar”, “Subir Nuevo Archivo”; grid de archivos (ej. imagen panorámica, PDF consentimiento) con tipo (Radiografía/Documento), nombre, fecha de carga y tamaño. Acciones por archivo: zoom, descarga. Zona “Arrastrar nuevos archivos” (JPG, PNG, PDF, máx 15MB). |
| **Quién la usa** | Médico o staff. |
| **Inputs** | Selección de archivos para subir; filtros; clic en ver/descargar. |
| **Outputs** | Archivos listados; nuevos archivos asociados al paciente; descarga o visualización. |
| **Reglas de negocio** | Formatos permitidos y tamaño máximo (15MB) deben validarse; los archivos son privados al paciente (LOPD); el historial de radiografías puede ser un subconjunto filtrado por tipo. |

**Wireframe:** W2 (sección “Gestión de Archivos y Radiografías”).

---

### 2.11 Pie de página legal

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Texto: “© 2023 Sistema DentalBoard • Información Médica Confidencial • Protegido por LOPD”. |
| **Quién la usa** | Todos (informativo). |
| **Inputs** | Ninguno. |
| **Outputs** | Mensaje legal y de confidencialidad visible. |
| **Reglas de negocio** | El sistema debe cumplir LOPD y tratar la información como confidencial. |

**Wireframe:** W2 (footer).

---

## 3. Funcionalidades de la Pantalla de Odontograma (W3)

### 3.1 Header y búsqueda

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Header con logo “DentalHub”, búsqueda “Buscar pacientes o tratamientos…”, notificaciones (con indicador) y perfil del usuario (ej. Dra. Sarah Wilson, Ortodoncista). |
| **Quién la usa** | Usuario autenticado. |
| **Inputs** | Texto de búsqueda; clic en notificaciones/perfil. |
| **Outputs** | Resultados de búsqueda o navegación. |
| **Reglas de negocio** | Misma política de búsqueda que en otras pantallas. |

**Wireframe:** W3 (header).

---

### 3.2 Contexto del paciente y acciones (Imprimir / Guardar)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Título “Explorador de Odontograma”, subtítulo con “Paciente: Juan Pérez (ID: #84921)” y “Última Visita: 12 Oct, 2023”. Botones “Imprimir Reporte” y “Guardar Tratamiento”. |
| **Quién la usa** | Médico que trabaja con el odontograma. |
| **Inputs** | Clic en “Imprimir Reporte” o “Guardar Tratamiento”. |
| **Outputs** | Impresión del odontograma/reporte; persistencia de los cambios del tratamiento en el odontograma. |
| **Reglas de negocio** | Guardar debe asociar los cambios a la visita actual (o crear visita si aplica); imprimir debe reflejar el estado actual del odontograma. |

**Wireframe:** W3 (zona superior del contenido).

---

### 3.3 Dentición completa (sistema FDI / Universal)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Vista “Dentición Completa (FDI)” con cuadrantes: Superior Derecho, Superior Izquierdo, Inferior Derecho, Inferior Izquierdo. Cada diente tiene número FDI (ej. 18–11, 21–28, 31–38, 41–48; temporal 51–55, 61–65, 71–75, 81–85). Selector “Sistema FDI” / “Universal”. |
| **Quién la usa** | Médico. |
| **Inputs** | Selección de sistema (FDI/Universal); clic en diente o superficie. |
| **Outputs** | Vista del odontograma en el sistema elegido; posibilidad de editar estado por diente/superficie. |
| **Reglas de negocio** | La numeración FDI es estándar; la conversión o vista “Universal” debe ser coherente; cada diente puede tener estado por superficie. |

**Wireframe:** W3 (grid de dientes).

---

### 3.4 Estados de diente/superficie (protocolo de patología)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Panel “Protocolo de Patología” con opciones: Sano/Limpio, Caries, Amalgama, Composite, Corona Cerámica, Ausente/Extracción. El usuario selecciona una opción y aplica al diente/superficie en el odontograma. Colores: sano (neutro), caries (rojo), amalgama (gris), composite (amarillo), corona (azul/índigo), ausente (marcado/desvanecido). |
| **Quién la usa** | Médico. |
| **Inputs** | Selección de patología; selección de diente y/o superficie en el odontograma. |
| **Outputs** | Actualización visual del diente/superficie; registro del estado (y asociación a la visita). |
| **Reglas de negocio** | Un diente/superficie tiene un estado por patología; “Sano” restablece; “Ausente” indica extracción/pérdida; los estados deben guardarse con la visita. |

**Wireframe:** W3 (panel lateral “Protocolo de Patología”).

---

### 3.5 Historial de cambios del odontograma

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Tabla “Historial de Cambios”: Fecha y Hora, Diente #, Superficie, Acción (ej. Examinado, Prep. Corona, Nota Agregada), Doctor, Estado (Pendiente/Guardado). Botón “Ver Todo”. |
| **Quién la usa** | Médico o staff (revisión/auditoría). |
| **Inputs** | Datos de cambios ya registrados. |
| **Outputs** | Lista ordenada de cambios del odontograma del paciente. |
| **Reglas de negocio** | Cada cambio debe quedar registrado con timestamp, diente, superficie, acción, doctor y estado; “Guardado” = persistido. |

**Wireframe:** W3 (tabla “Historial de Cambios”).

---

### 3.6 Acciones rápidas (Notas, Rayos-X, Archivos, Citas)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Cuatro botones: Notas, Rayos-X, Archivos, Citas. Permiten ir a notas clínicas, radiografías, gestión de archivos o citas del paciente desde el contexto del odontograma. |
| **Quién la usa** | Médico. |
| **Inputs** | Clic en cada botón. |
| **Outputs** | Navegación a la sección correspondiente del mismo paciente. |
| **Reglas de negocio** | El contexto (paciente, visita) se mantiene al cambiar de sección. |

**Wireframe:** W3 (panel lateral, “Acciones Rápidas”).

---

### 3.7 Análisis IA (escaneo)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Bloque “Análisis IA” con texto “Detecta caries ocultas con nuestro asistente de IA” y botón “Ejecutar Escaneo”. |
| **Quién la usa** | Médico. |
| **Inputs** | Clic en “Ejecutar Escaneo”; posiblemente datos del odontograma o imágenes. |
| **Outputs** | (En wireframe no se detalla: se asume informe o sugerencias de detección de caries). |
| **Reglas de negocio** | El resultado de IA es de apoyo, no sustituye el criterio clínico; los datos utilizados deben cumplir normativa de datos personales y sanitarios. |

**Wireframe:** W3 (panel lateral, “Análisis IA”).

---

## 4. Funcionalidades de la Pantalla de Agenda (W4)

### 4.1 Navegación y vista “Agenda”

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Header con logo “DentalEase”, navegación: Panel, Agenda (activa), Pacientes, Inventario. Búsqueda “Buscar citas…”, avatar del usuario. |
| **Quién la usa** | Usuario autenticado. |
| **Inputs** | Clic en cada ítem de navegación; texto de búsqueda. |
| **Outputs** | Cambio de vista o resultados de búsqueda de citas. |
| **Reglas de negocio** | La vista “Agenda” es la principal de esta pantalla; la búsqueda filtra por citas. |

**Wireframe:** W4 (header).

---

### 4.2 Calendario mensual (minicalendario)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Panel lateral con mes (ej. Octubre 2023), flechas anterior/siguiente, grid de días (Do–Sa). El día seleccionado (ej. 5) se resalta. |
| **Quién la usa** | Usuario que gestiona la agenda. |
| **Inputs** | Clic en flechas (cambiar mes); clic en un día. |
| **Outputs** | Cambio de mes; selección del día cuya agenda se muestra en el área principal. |
| **Reglas de negocio** | El día seleccionado determina la fecha de la vista “Agenda de Hoy” / por box. |

**Wireframe:** W4 (sidebar izquierdo, calendario).

---

### 4.3 Filtro por boxes

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Sección “Filtrar Boxes” con checkboxes por box (BOX 1, BOX 2, …). Permite mostrar/ocultar columnas de la agenda por box. |
| **Quién la usa** | Usuario que visualiza la agenda. |
| **Inputs** | Marcar/desmarcar cada box. |
| **Outputs** | Columnas visibles en la rejilla de la agenda según boxes seleccionados. |
| **Reglas de negocio** | Al menos un box debe poder estar visible; la lista de boxes viene de configuración o catálogo. |

**Wireframe:** W4 (sidebar, “Filtrar Boxes”).

---

### 4.4 Ocupación del día

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Indicador “Ocupación” con barra de progreso (ej. 78%) y texto “19/24 HUECOS OCUPADOS HOY”. |
| **Quién la usa** | Staff/médico. |
| **Inputs** | Citas del día; definición de “hueco” (slot de tiempo). |
| **Outputs** | Porcentaje y ratio de huecos ocupados vs. total. |
| **Reglas de negocio** | “Hueco” = slot asignable (ej. cada 30 min o según configuración); el total de huecos depende del horario y de los boxes. |

**Wireframe:** W4 (sidebar, “Ocupación”).

---

### 4.5 Aviso de buffer de desinfección

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Mensaje fijo: “Se reservan 5 minutos entre pacientes para la limpieza y desinfección del box.” |
| **Quién la usa** | Todos (informativo). |
| **Inputs** | Ninguno. |
| **Outputs** | Conocimiento del usuario sobre la regla. |
| **Reglas de negocio** | Entre dos citas consecutivas en el mismo box debe existir un buffer de 5 minutos (no asignable a paciente); la agenda debe respetarlo al crear/mover citas. |

**Wireframe:** W4 (banner superior del contenido).

---

### 4.6 Selector de vista (Día / Por Box / Semana) y fecha

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Título con fecha (ej. “Jueves, 5 de Octubre”) y selector de vista: “Día”, “Por Box” (activo), “Semana”. |
| **Quién la usa** | Usuario que consulta la agenda. |
| **Inputs** | Clic en Día / Por Box / Semana. |
| **Outputs** | Cambio de representación: por día (posiblemente lista), por box (columnas por box), por semana (vista semanal). |
| **Reglas de negocio** | “Por Box” muestra una columna por box con slots horarios; la fecha mostrada es la seleccionada en el minicalendario. |

**Wireframe:** W4 (debajo del banner, selector de vista).

---

### 4.7 Botón “Nueva Cita”

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Botón principal “Nueva Cita” que abre el flujo de creación de cita. |
| **Quién la usa** | Usuario con permiso para gestionar citas. |
| **Inputs** | Clic en “Nueva Cita”. |
| **Outputs** | Apertura del panel o formulario de detalle de cita (vacío). |
| **Reglas de negocio** | Igual que en W1: solo usuarios autorizados pueden crear citas. |

**Wireframe:** W4 (botón “Nueva Cita”).

---

### 4.8 Rejilla de agenda por box (columnas y franjas)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Columna fija de horas (08:00–17:00, cada hora); una columna por box (ej. BOX 1 “SALA DE ORTODONCIA”, BOX 2 “SALA DE CIRUGÍA”). Cada cita se muestra como bloque posicionado por hora y duración; hay franjas “buffer” de 5 min entre citas (rayado). Línea de “ahora” (ej. 12:15) en rojo. |
| **Quién la usa** | Usuario que visualiza o edita la agenda. |
| **Inputs** | Fecha seleccionada; citas y boxes; configuración de horario y duración de slot. |
| **Outputs** | Visualización de citas por box y hora; huecos libres; buffers visibles. |
| **Reglas de negocio** | No se pueden solapar dos citas en el mismo box; el buffer de 5 min es obligatorio entre citas; la línea de “ahora” se actualiza en tiempo real (opcional). |

**Wireframe:** W4 (rejilla principal).

---

### 4.9 Bloque de cita en la rejilla (contenido y acciones)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Cada cita muestra: nombre del paciente, doctor, franja horaria, y en hover: botones editar y cancelar. Puede mostrar etiqueta “CONFIRMADO” y “Patología: …” (ej. Sensibilidad molar #14). Botón “more_vert” para más opciones. |
| **Quién la usa** | Usuario que gestiona citas. |
| **Inputs** | Clic en cita (abrir detalle); clic en editar/cancelar/más opciones. |
| **Outputs** | Apertura del panel de detalle de cita; ejecución de editar/cancelar. |
| **Reglas de negocio** | Al hacer clic en una cita se abre el panel lateral de detalle con los datos de esa cita; cancelar puede requerir confirmación. |

**Wireframe:** W4 (bloques de cita en la rejilla).

---

### 4.10 Panel de detalle de cita (formulario)

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Panel lateral “Detalle de Cita” con: Paciente (selector “Seleccionar paciente existente…” o “NUEVO PACIENTE”), Fecha de cita, Hora inicio, Duración (30/60/90/120 min), Doctor (selector), BOX (selector), Patología/Motivo (selector: Limpieza, Tratamiento de Conducto, Ortodoncia, Extracción…), Notas clínicas (textarea). Aviso de “5 min añadidos para desinfección del BOX”. Botones “Cancelar” y “Guardar Cita”. |
| **Quién la usa** | Usuario que crea o edita una cita. |
| **Inputs** | Valores de cada campo; selección de paciente (existente o nuevo). |
| **Outputs** | Cita creada o actualizada; validación de solapamientos y buffer. |
| **Reglas de negocio** | Paciente obligatorio; fecha, hora, duración, doctor y box obligatorios; no solapamiento en el mismo box; buffer de 5 min automático al finalizar; la duración seleccionada puede determinar el slot ocupado. |

**Wireframe:** W4 (panel derecho “Detalle de Cita”).

---

### 4.11 Cerrar panel de detalle

| Aspecto | Descripción |
|--------|-------------|
| **Qué hace** | Botón “close” en el panel de detalle para cerrar el panel sin guardar (o tras guardar). |
| **Quién la usa** | Usuario que ha abierto el detalle. |
| **Inputs** | Clic en cerrar. |
| **Outputs** | Panel oculto; cambios no guardados pueden perderse (con o sin confirmación según diseño). |
| **Reglas de negocio** | Si hay cambios sin guardar, se puede pedir confirmación antes de cerrar. |

**Wireframe:** W4 (cabecera del panel “Detalle de Cita”).

---

## 5. Resumen por wireframe

| Wireframe | Nombre corto | Funcionalidades listadas |
|-----------|--------------|---------------------------|
| **W1** | Panel de Control | Navegación, saludo, búsqueda global, notificaciones, nuevo paciente, KPIs (pacientes hoy, resultados pendientes, stock bajo), agenda del día, acciones por cita, asignar turno en hueco, alertas de alergias, estado del stock por caja, solicitud de reabastecimiento |
| **W2** | Expediente paciente | Header y búsqueda, perfil resumido, navegación interna, aviso registro incompleto / primera visita, guardar y exportar PDF, alergias críticas (CRUD), datos de contacto (edición), enfermedades y condiciones, historial de visitas y odontograma por visita, gestión de archivos y radiografías, pie legal |
| **W3** | Odontograma | Header y búsqueda, contexto paciente e imprimir/guardar, dentición FDI/Universal, protocolo de patología (estados de diente/superficie), historial de cambios, acciones rápidas (Notas, Rayos-X, Archivos, Citas), análisis IA |
| **W4** | Agenda por box | Navegación y vista Agenda, minicalendario, filtro por boxes, ocupación del día, aviso buffer 5 min, selector Día/Por Box/Semana, nueva cita, rejilla por box con franjas y buffers, bloque de cita con acciones, panel detalle de cita (formulario completo), cerrar panel |

---

## 6. Entidades y conceptos transversales (inferidos de los wireframes)

- **Usuario**: roles (médico, staff, admin); nombre, foto, rol mostrado en UI.
- **Paciente**: ID, nombre, edad, foto, estado (ej. Tratamiento Activo, Registro incompleto); datos de contacto; alergias; enfermedades/condiciones; historial de visitas; archivos/radiografías.
- **Cita**: paciente, doctor, box, fecha, hora inicio, duración; estado (Confirmado, En curso, Llegada…); patología/motivo; notas clínicas; buffer 5 min post-cita.
- **Box**: identificador (1, 2…), nombre de sala (ej. Ortodoncia, Cirugía); slots horarios.
- **Visita**: fecha; tratamiento realizado; odontograma asociado; notas.
- **Odontograma**: asociado a visita/paciente; dientes por cuadrante; estado por superficie (sano, caries, amalgama, composite, corona, ausente); historial de cambios.
- **Inventario/Stock**: por caja o sala; nivel en %; umbral “bajo”; alertas; solicitud de reabastecimiento.
- **Alergia**: tipo (Penicilina, Látex, etc.); severidad (crítica vs. precaución); asociada a paciente.
- **Archivo/documento**: tipo (radiografía, documento); nombre, fecha, tamaño; asociado a paciente; formato y tamaño máx. (ej. 15MB).

---

*Documento generado a partir exclusivamente de los cuatro wireframes proporcionados. No incluye implementación ni código; sirve como especificación funcional de referencia para FalconCare.*
