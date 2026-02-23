# 🦷 FalconCare - Guía Técnica del Proyecto

Este documento detalla el paso a paso de la construcción de FalconCare, una aplicación de gestión dental desarrollada con **Symfony 7** y **PostgreSQL (Neon)**.

## 🛠️ 1. Configuración Inicial del Proyecto

1. **Crear el proyecto:** `symfony new FalconCare`
2. **Entrar a la carpeta:** `cd FalconCare`
3. **Instalar el motor de Base de Datos:** `composer require symfony/orm-pack`
4. **Instalar el generador de código:** `composer require symfony/maker-bundle --dev`
5. **Configuración de XAMPP (PostgreSQL):**
   Es necesario descomentar las siguientes líneas en el `php.ini` para permitir la comunicación con Neon:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
6.  **Soporte para Angular:** `composer require symfony/serializer-pack` (para convertir entidades a JSON) [cite: 12-02-2026].
7.  **Arrancar el servidor:** `symfony server:start` [cite: 12-02-2026].
8. **Seguridad de Credenciales:** * Añadir fichero `.env.local` con la ruta de acceso a Neon [cite: 12-02-2026].
    * Configurar `.gitignore` para no subir estas credenciales a GitHub [cite: 12-02-2026].


# 🦷 FalconCare - Desarrollo del Sistema Dental

Registro técnico de la construcción de la infraestructura, entidades y sincronización con la base de datos **Neon** [cite: 12-02-2026].

## 🛠️ 1. Configuración de Entorno y Backend

* **Soporte para Angular:** `composer require symfony/serializer-pack` (Permite la conversión de entidades a JSON) [cite: 12-02-2026].
* **Servidor Local:** Ejecución mediante `symfony server:start` [cite: 12-02-2026].
* **Seguridad de Credenciales:**
    * Creación del fichero `.env.local` para almacenar la URL de acceso a **Neon** [cite: 12-02-2026].
    * Configuración de `.gitignore` para excluir credenciales sensibles del repositorio público [cite: 12-02-2026].

---

## 🏗️ 2. Definición de Entidades (Clases Modernas)

Se han diseñado las siguientes **clases** bajo estándares modernos para el modelado clínico [cite: 12-02-2026]:

### 👤 Acceso y Personal
* **User:** Identificación por email y gestión de contraseñas con hash seguro [cite: 12-02-2026].
* **Doctor:** Datos personales, especialidad médica y calendario de días asignados [cite: 12-02-2026].

### 🏥 Pacientes y Gestión Clínica
* **Patient:** Ficha completa con documento de identidad, SS, contacto y antecedentes clínicos [cite: 12-02-2026].
* **Box:** Gestión de gabinetes con nombre, capacidad y estado de disponibilidad [cite: 12-02-2026].
* **Treatment:** Catálogo de servicios con descripción y tiempos estimados [cite: 12-02-2026].
* **Pathology:** Registro de patologías con codificación por colores de protocolo [cite: 12-02-2026].
* **Tooth:** Identificación técnica de piezas dentales [cite: 12-02-2026].

### 📅 Lógica de Citas y Odontograma
* **Appointment (Cita):** Gestión de agenda con fecha, hora y estado.
    * **Relaciones (ManyToOne):** Patient, Doctor, Box, Treatment [cite: 12-02-2026].
* **Odontogram:** Registro detallado por superficie dental.
    * **Relaciones (ManyToOne):** Appointment (visit), Tooth, Pathology [cite: 12-02-2026].
* **Document:** Gestión de archivos y capturas vinculadas al historial.
    * **Relaciones (ManyToOne):** Patient [cite: 12-02-2026].

---

## 🔄 3. Sincronización con Base de Datos (Neon)

Protocolo seguido para asegurar la integridad de los datos en la nube [cite: 12-02-2026]:

1. **Verificación de Conexión:** `php bin/console doctrine:query:sql "SELECT current_database();"` [cite: 12-02-2026].
2. **Snapshot de Seguridad:** Copia de seguridad realizada en el panel de **Neon** antes de cambios estructurales.
3. **Limpieza de Esquema:**
   ```powershell
   php bin/console doctrine:schema:drop --force --full-database

## 🚀 4. Despliegue de Estructura

Una vez definida la lógica de las entidades, se ejecutan los comandos de Doctrine para materializar los cambios en la base de datos de Neon [cite: 12-02-2026]:

```powershell
# Generar el archivo de migración basado en las entidades
php bin/console make:migration

# Aplicar los cambios a la base de datos
php bin/console doctrine:migrations:migrate