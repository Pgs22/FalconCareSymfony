# Document Module Operation

## 1. General Description
This module manages the persistence and retrieval of documents associated with patients. It complies with RESTful standards, separating the HTTP control logic from the data access logic.

## 2. Main Components

### A. Controller (`DocumentApiController`)
Responsible for receiving HTTP requests and returning JSON responses
* **POST `/api/documents`**:
1. Receives the physical file and data (`file`, `patient`, `type`) using `multipart/form-data`
2. Validates the existence of the patient using `PatientRepository`
3. Calls the physical storage service (`handleFileStorage`)
4. Invokes the `create` method of `DocumentRepository` to persist the entity
5. Serializes the resulting `Document` object using serialization groups (`document:read`)

### B. Repository (`DocumentRepository`)
Responsible for interaction with the Doctrine database
* **`create` Method**: Receives the file name and data from the controller, creates the entity A `Document` with an immutable capture date (`DateTimeImmutable`) is stored in the database.

* **`findByCaptureDate` Method**:
1. Calculates a 24-hour range starting from a given date.
2. Uses **Doctrine Paginator** to optimize memory consumption when querying large volumes of data.
3. Applies date filters to the database using an optimized index.

## 3. Database Management and Migrations

To ensure performance and maintain structural integrity, database changes are managed through Doctrine migrations.

* **Migration `Version20260227123500` Migration (Patient Link)**:
1. **`up()`**: Executes `ALTER TABLE document ALTER COLUMN patient_id DROP NOT NULL;`. This modification allows documents to exist in the database without being immediately linked to a specific patient.
2. **`down()`**: Executes `ALTER TABLE document ALTER COLUMN patient_id SET NOT NULL;`. This reverts the column to require a patient link, ensuring strict relational integrity.

* **Migration `Version20260301214009` (Performance Index)**:
1. **`up()`**: Executes `CREATE INDEX idx_document_capture_date ON document (capture_date);`. This action creates a database index on the `capture_date` column to dramatically speed up document searches within specific date ranges.
2. **`down()`**: Executes `DROP INDEX idx_document_capture_date;`. This allows the change to be reverted if necessary, dropping the index to return the database to its previous state.