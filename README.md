# FalconCare – Backend (Symfony API)

## 📌 Descripción general

**FalconCare Backend** es la API REST desarrollada con **Symfony** que da soporte al frontend de la aplicación FalconCare. Este backend es el encargado de gestionar la **lógica de negocio**, el **acceso a la base de datos** y la **exposición segura de datos clínicos**, simulando el funcionamiento real de una clínica odontológica en un entorno educativo.

El proyecto forma parte del trabajo final del módulo **MP0616 (DAW2)** y ha sido diseñado para trabajar de forma desacoplada del frontend, permitiendo una arquitectura moderna basada en **cliente–servidor**.

---

## 🎯 Objetivos del backend

El backend tiene como objetivos principales:

* Centralizar y persistir toda la información clínica.
* Exponer una **API REST** clara y estructurada.
* Separar la lógica de negocio de la capa de presentación.
* Garantizar la integridad y coherencia de los datos.
* Facilitar la integración con el frontend en Angular.
* Servir como base escalable para futuras ampliaciones (autenticación, roles, IA, etc.).

---

## 🧩 Funcionalidades principales

La API proporciona endpoints para la gestión de:

* **Pacientes**
  Creación, consulta, actualización y listado de pacientes.

* **Primera visita**
  Almacenamiento de datos personales, motivo de consulta y datos iniciales.

* **Odontograma**
  Persistencia de patologías, tratamientos y estados dentales asociados a cada paciente.

* **Historial clínico**
  Registro de antecedentes, alergias, medicación y evolución por visitas.

* **Agenda y citas**
  Gestión de citas, tiempos asignados y relación con profesionales y boxes.

* **Radiografías**
  Asociación de imágenes y metadatos al historial del paciente.

---

## 🛠️ Tecnologías utilizadas

* **PHP 8.x**
* **Symfony** (framework backend)
* **Doctrine ORM**
* **MySQL / MariaDB** (base de datos relacional)
* **API REST (JSON)**

---

## 🗄️ Base de datos

El backend utiliza una **base de datos relacional**, diseñada para reflejar la estructura clínica real:

* Pacientes
* Visitas
* Odontogramas
* Tratamientos
* Citas
* Radiografías
* Materiales (objetivo ampliable)

La gestión del esquema se realiza mediante **migraciones de Doctrine**, permitiendo versionar y mantener la evolución de la base de datos de forma controlada.

---

## 📁 Estructura general del proyecto

El proyecto sigue la arquitectura estándar de Symfony:

* `src/Controller` → Controladores de la API
* `src/Entity` → Entidades de Doctrine
* `src/Repository` → Acceso a datos
* `src/Service` → Lógica de negocio
* `config/` → Configuración del framework
* `migrations/` → Migraciones de base de datos

Esta estructura favorece la mantenibilidad, la separación de responsabilidades y la escalabilidad del sistema.

---

## 🔐 Seguridad y validación

El backend está preparado para:

* Validación de datos de entrada.
* Control de errores mediante respuestas HTTP normalizadas.
* Futuras implementaciones de autenticación y autorización (JWT, roles, etc.).

Actualmente, la seguridad se enfoca en un contexto educativo y de desarrollo.

---

## 🚀 Instalación y ejecución

1. Clonar el repositorio:

   ```bash
   git clone <URL_DEL_REPOSITORIO_BACKEND>
   ```

2. Instalar dependencias:

   ```bash
   composer install
   ```

3. Configurar el archivo `.env` con los datos de la base de datos:

   ```env
   DATABASE_URL="mysql://usuario:password@127.0.0.1:3306/falconcare"
   ```

4. Crear la base de datos y ejecutar migraciones:

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. Ejecutar el servidor de desarrollo:

   ```bash
   symfony serve
   ```

La API quedará disponible por defecto en:

```text
http://localhost:8000
```

---

## 🔗 Integración con el frontend

El backend expone endpoints REST que son consumidos por el frontend desarrollado en Angular.

La comunicación se realiza mediante **JSON**, manteniendo una separación clara entre:

* Presentación (frontend)
* Lógica de negocio y persistencia (backend)

---

## 📌 Estado del proyecto

🔧 **En desarrollo**
El backend se encuentra en fase activa de implementación y ampliación, alineado con la evolución del frontend y los objetivos del proyecto.

Este README se actualizará conforme se incorporen nuevas entidades, endpoints o mecanismos de seguridad.

---

## 👥 Equipo de desarrollo

* Adrián Palma
* Patricia
* Maxime

**Equipo:** Speed Falcons

---

## 📄 Licencia

Proyecto desarrollado con fines **educativos** dentro del ciclo formativo DAW2.

Su uso y redistribución quedan limitados al contexto académico, salvo indicación expresa.
