# Backend — Database and Entities (Symfony)

This document describes the backend database schema, entities, and repositories implemented for the project, and the commands that were used to apply all changes to the Neon cloud database.

---

## 1. Overview

The application is built with **Symfony** and **Doctrine ORM**. The persistence layer is aligned with an English specification: all table and column names follow that specification, and the optional Supplies module (Materials, Suppliers, Material–Treatment protocol) is not included.

The cloud database used is **Neon** (PostgreSQL). Connection is configured via `DATABASE_URL` in `.env.local`.

---

## 2. Entities and Tables

### 2.1 Patient (`src/Entity/Patient.php`)

The **Patient** entity is mapped to the table `patients`. The following attributes are defined: **Patient_ID** (primary key), **National_ID** (unique), **First_Name**, **Last_Name**, **Social_Security_Number**, **Phone**, **Email**, **Address**, **Billing_Information**, **Reason_for_Consultation**, **Family_History**, **Health_Status**, **Lifestyle_Habits**, **Medication_Allergies**, and **Registration_Date**. A one-to-many association to **Visit** and a one-to-many association to **Document** are defined so that a patient’s visit history and documents can be retrieved.

### 2.2 Dentist (`src/Entity/Dentist.php`)

The **Dentist** entity is mapped to the table `dentists`. The following attributes are defined: **Doctor_ID** (primary key), **First_Name**, **Last_Name**, **Specialty**, **Assigned_Day_of_Week**, **Phone**, and **Email**. A one-to-many association to **Visit** is defined so that visits can be grouped or scheduled by dentist.

### 2.3 Box (`src/Entity/Box.php`)

The **Box** entity is mapped to the table `boxes`. The following attributes are defined: **Box_ID** (primary key, column `id_box`), **Box_Name**, **Status** (enum: Active / Inactive), and **Capacity**. A one-to-many association to **Visit** is defined so that physical occupancy of treatment rooms can be tracked. The table was introduced as `boxes` (the previous name `treatment_rooms` was replaced in both the mapping and the database).

### 2.4 Visit (`src/Entity/Visit.php`)

The **Visit** entity is mapped to the table `visits`. The following attributes are defined: **Visit_ID** (primary key), **Patient_ID** (foreign key), **Doctor_ID** (foreign key), **Box_ID** (foreign key), **Treatment_ID** (foreign key, nullable), **Visit_Date**, **Visit_Time**, **Reason_for_Consultation**, and **Notes**. Many-to-one associations to **Patient**, **Dentist**, **Box**, and **Treatment** are defined, and a one-to-many association to **OdontogramDetail** is defined so that each visit can record multiple odontogram entries.

### 2.5 Treatment (`src/Entity/Treatment.php`)

The **Treatment** entity is mapped to the table `treatment`. The following attributes are defined: **Treatment_ID** (primary key, column `id_treatment`), **Treatment_Name**, **Description**, and **Estimated_Duration**. A one-to-many association to **Visit** is defined so that visits can be linked to a treatment.

### 2.6 Pathology (`src/Entity/Pathology.php`)

The **Pathology** entity is mapped to the table `pathologies`. The following attributes are defined: **Pathology_ID** (primary key), **Description** (e.g. Caries, Missing Tooth), and **Protocol_Color** (e.g. Red/Blue). A one-to-many association to **OdontogramDetail** is defined so that each pathology can be referenced in multiple odontogram details.

### 2.7 Tooth (`src/Entity/Tooth.php`)

The **Tooth** entity is mapped to the table `teeth`. The following attributes are defined: **Tooth_ID** (primary key) and **Description** (e.g. numbering 11–48). A one-to-many association to **OdontogramDetail** is defined so that each tooth can be referenced in multiple odontogram details.

### 2.8 OdontogramDetail (`src/Entity/OdontogramDetail.php`)

The **OdontogramDetail** entity is mapped to the table `odontogram_details`. The following attributes are defined: **Detail_ID** (primary key), **Visit_ID** (foreign key), **Tooth_ID** (foreign key), **Pathology_ID** (foreign key), **Tooth_Surface** (e.g. Buccal, Occlusal), and **Coordinates_3D** (optional JSON for 3D functionality). Many-to-one associations to **Visit**, **Tooth**, and **Pathology** are defined.

### 2.9 Document (`src/Entity/Document.php`)

The **Document** entity is mapped to the table `documents`. The following attributes are defined: **Image_ID** (primary key), **Patient_ID** (foreign key), **Type** (e.g. X-ray, Scan), **File_Path** (URL or path), and **Capture_Date**. A many-to-one association to **Patient** is defined so that documents are linked to a patient.

---

## 3. Repositories

Each entity is bound to a Doctrine repository class used for database queries:

| Entity        | Repository class                         | Location |
|---------------|------------------------------------------|----------|
| Patient       | `App\Repository\PatientRepository`       | `src/Repository/PatientRepository.php` |
| Dentist       | `App\Repository\DentistRepository`       | `src/Repository/DentistRepository.php` |
| Box           | `App\Repository\BoxRepository`           | `src/Repository/BoxRepository.php` |
| Visit         | `App\Repository\VisitRepository`        | `src/Repository/VisitRepository.php` |
| Treatment     | `App\Repository\TreatmentRepository`     | `src/Repository/TreatmentRepository.php` |
| Pathology     | `App\Repository\PathologyRepository`     | `src/Repository/PathologyRepository.php` |
| Tooth         | `App\Repository\ToothRepository`         | `src/Repository/ToothRepository.php` |
| OdontogramDetail | `App\Repository\OdontogramDetailRepository` | `src/Repository/OdontogramDetailRepository.php` |
| Document      | `App\Repository\DocumentRepository`      | `src/Repository/DocumentRepository.php` |

All of these repositories extend Doctrine’s `ServiceEntityRepository` and are used for standard CRUD and custom queries against the corresponding tables.

---

## 4. Key Relationships

The following relationships are implemented and enforced by foreign keys in the database:

- **Patient 1:N Visits** — A patient is associated with many visits (complete visit history).
- **Dentist 1:N Visits** — A dentist is associated with many visits (schedule by specialist).
- **Box 1:N Visits** — A box (treatment room) is associated with many visits (physical occupancy).
- **Visit 1:N Odontogram_Details** — A visit is associated with many odontogram details (multiple pathologies per examination).
- **Patient 1:N Documents** — A patient is associated with many documents (e.g. X-rays, scans).

The optional Supplies module (Materials, Suppliers, Protocolo_Material / Treatment–Material protocol) is not implemented; the corresponding tables were not created or were removed from the schema.

---

## 5. Commands Executed to Apply Changes to the Neon Database

The following commands and steps were used to bring the Neon database in line with the entity mapping. They assume that the PHP CLI used has the **pdo_pgsql** extension enabled (e.g. the project’s PHP in `php-8.4.16-nts-Win32-vs17-x64` on Windows). The `DATABASE_URL` in `.env.local` must point to the Neon PostgreSQL instance.

### 5.1 Connection check

The connection to Neon was verified with:

```bash
php bin/console doctrine:query:sql "SELECT 1"
```

### 5.2 Schema creation and migrations

The main schema (patients, dentists, boxes, visits, treatment, pathologies, teeth, odontogram_details, documents) is created by the migration **Version20260220110000**. Where the migration history was not fully applied, the schema was synchronized with the entities using:

```bash
php bin/console doctrine:schema:update --force
```

So that the cloud database matched the current mapping (tables and columns were created or altered as needed).

### 5.3 Column renames (patients)

The `patients` table was aligned with the English specification by renaming columns:

```bash
php bin/console doctrine:query:sql "ALTER TABLE patients RENAME COLUMN telephone TO phone"
php bin/console doctrine:query:sql "ALTER TABLE patients RENAME COLUMN allergy_medications TO medication_allergies"
```

### 5.4 Column rename (dentists)

The `dentists` table was aligned by renaming:

```bash
php bin/console doctrine:query:sql "ALTER TABLE dentists RENAME COLUMN telephone TO phone"
```

### 5.5 Table rename (treatment rooms → boxes)

The table previously named `treatment_rooms` was renamed to `boxes`:

```bash
php bin/console doctrine:query:sql "ALTER TABLE treatment_rooms RENAME TO boxes"
```

(If the table was already named `boxes`, this command was skipped or failed harmlessly.)

### 5.6 Column rename (visits)

The `visits` table was aligned by renaming the observations column to notes:

```bash
php bin/console doctrine:query:sql "ALTER TABLE visits RENAME COLUMN observations TO notes"
```

### 5.7 Removal of optional tables

The **messenger_messages** table (Symfony Messenger) was dropped because the Doctrine transport for the message queue was not used:

```bash
php bin/console doctrine:query:sql "DROP TABLE IF EXISTS messenger_messages"
```

The optional Supplies module tables were removed so that they are not present in the cloud database:

```bash
php bin/console doctrine:query:sql "DROP TABLE IF EXISTS treatment_material_protocol CASCADE"
php bin/console doctrine:query:sql "DROP TABLE IF EXISTS materials CASCADE"
```

### 5.8 Schema validation

After applying the changes, the mapping and database were validated with:

```bash
php bin/console doctrine:schema:validate
```

Mapping is expected to be correct; the database may be reported as “not in sync” if, for example, the `messenger_messages` table is absent (by design). No further schema update is required for the core entities.

### 5.9 Cache

The application cache was cleared after schema changes:

```bash
php bin/console cache:clear
```

---

## 6. Migrations Included in the Project

The following migration files are present and document the evolution of the schema:

- **Version20260220110000** — Creates the main schema: patients, dentists, boxes, treatment, visits, pathologies, teeth, odontogram_details, documents (with foreign keys and indexes).
- **Version20260220120000** — Drops the `messenger_messages` table (Messenger no longer uses the Doctrine transport).
- **Version20260220130000** — Renames `treatment_rooms` to `boxes` (idempotent).
- **Version20260220140000** — Renames `patients.telephone` to `phone` and `patients.allergy_medications` to `medication_allergies` (idempotent).
- **Version20260220150000** — Renames `dentists.telephone` to `phone` (idempotent).
- **Version20260220160000** — Renames `visits.observations` to `notes` (idempotent).
- **Version20260220180000** — Drops the optional Supplies module tables: `treatment_material_protocol` and `materials`.

On a clean database, migrations can be run with:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

If the migration history in Neon does not match the local migration files, the SQL above (sections 5.3–5.7 and 5.7) was applied directly via `doctrine:query:sql` and/or `doctrine:schema:update --force` to align the cloud database with the current entities.

---

## 7. Summary

The backend is documented in English, with entities and repositories aligned to the specified table and column names. Patient, Dentist, Box, Visit, Treatment, Pathology, Tooth, OdontogramDetail, and Document are implemented and linked by the relationships described above. All changes were applied to the Neon database using the Doctrine console commands and SQL statements listed in section 5, and the optional Supplies module (Materials, Suppliers, Protocolo_Material) is not part of the current schema.
