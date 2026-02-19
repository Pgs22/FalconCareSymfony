# Security, Privacy and Compliance Requirements

## Overview

This document defines the security and privacy requirements for the FalconCare (Speed Falcons) dental clinic management system, a web-based solution designed to simulate real-world dental clinic operations for educational purposes. The system supports practical training for students enrolled in the Auxiliary Nursing in Dental Clinics program at Stucom Córcega. These requirements specifically address patient data protection, clinical file upload handling (including radiographs and diagnostic images), and secure management of the interactive odontogram system. All security measures are designed to ensure compliance with data protection regulations while maintaining an appropriate security posture for an educational context.

## Scope

This document covers security and privacy requirements for the following system components:

- **Patient Management**: Secure handling of patient registration data, including personal information (DNI, Social Security number), contact details, billing information, and medical history
- **Clinical Documentation**: Protection of clinical records, including initial visit records, exploration data, and clinical history (family antecedents, lifestyle habits, current medication, and allergies)
- **Interactive Odontogram System**: Secure storage and management of odontogram data, including pathology records, treatment markers, and graphical representations of dental conditions
- **Radiography Repository**: Secure upload, storage, and access control for diagnostic images and clinical documents
- **Intelligent Appointment System**: Secure management of appointment scheduling, doctor assignments, and box resource allocation
- Authentication mechanisms and user access control
- Role-based access control (RBAC) implementation
- Encryption requirements for data in transit and at rest
- Data retention and deletion policies
- Access audit and logging requirements
- Basic GDPR considerations for educational use at Stucom Córcega

### Database Schema (Scope of Protected Data)

The following tables and entities are in scope. All names are in English and match the implemented database schema.

| Table | Purpose | Key fields |
|-------|---------|------------|
| **patients** | Central patient record and clinical history | id (PK), dni (unique), first_name, last_name, social_security_number, phone, email, address, billing_data, consultation_reason, family_history, health_status, lifestyle_habits, medication_allergies, registration_date |
| **dentists** | Expert staff and scheduling logic | id (PK), first_name, last_name, specialty, assigned_weekday, phone, email |
| **boxes** | Physical resources and status | id (PK), name, status (Active/Inactive), capacity (default 2) |
| **treatments** | Treatment types for visits | id (PK), name, description, price |
| **visits** | Appointments linking patient, box, and dentist | id (PK), patient_id (FK), dentist_id (FK), box_id (FK), treatment_id (FK), visit_date, visit_time, consultation_reason, observations |
| **pathologies** | Odontogram catalogue (e.g. caries, absence) | id (PK), description, protocol_color |
| **teeth** | Tooth catalogue (e.g. numbering 11–48) | id (PK), description, position |
| **odontogram_details** | Clinical exploration (2D/3D) | id (PK), visit_id (FK), tooth_id (FK), pathology_id (FK), tooth_face (e.g. vestibular, occlusal), coordinates_3d (optional) |
| **radiographies** | External images and documents | id (PK), patient_id (FK), type (e.g. radiography, scan), file_path, capture_date |

**Key relationships:** Patient 1:N Visits (full history); Dentist 1:N Visits (agenda); Box 1:N Visits (resource use); Visit 1:N Odontogram_details (multiple pathologies per visit). Treatment is referenced by Visit (treatment_id). Radiographies are linked to patients.

---

## 1. Authentication and RBAC Model

### 1.1 Authentication Requirements

User authentication is implemented through a secure credential-based system. The following requirements are established:

- **Password Requirements**: Passwords must be hashed using secure algorithms (bcrypt or Argon2) before storage. Passwords are never stored in plain text format.
- **Session Management**: User sessions are managed securely with appropriate timeout mechanisms. Session tokens are generated using cryptographically secure methods.
- **Multi-Factor Authentication**: Multi-factor authentication (MFA) may be implemented for administrative accounts as an optional security enhancement.

### 1.2 Role-Based Access Control (RBAC)

A role-based access control model is implemented to ensure that users are granted access only to the resources and functions appropriate to their role. The following roles are defined:

#### Role Definitions

- **ROLE_ADMIN**: Administrative users with full system access. Administrative privileges include user management, system configuration, and access to all patient records.
- **ROLE_PROFESSIONAL**: Healthcare professionals (dentists, hygienists) and instructors who require access to patient records, treatment plans, clinical documentation, and the interactive odontogram system. Professional users are granted read and write access to patient data within their assigned scope, including the ability to create and modify odontogram entries, manage clinical histories, and access radiography repositories.
- **ROLE_STAFF**: Administrative staff members and students in training roles who require access to appointment scheduling, patient contact information, billing data, and basic patient record viewing. Staff members are granted limited access to patient records as necessary for their educational duties, including the ability to register new patients during appointment creation and view assigned patient information.
- **ROLE_USER**: Basic authenticated users (primarily students) with minimal system access. User-level access is restricted to non-sensitive system functions and read-only access to assigned patient records for educational purposes.

#### Access Control Implementation

- Access control is enforced at both the application and database levels.
- Role assignments are stored securely and validated on each request.
- Privilege escalation is prevented through strict role validation.
- Access to patient records is restricted based on the user's role and assigned scope.

---

## 2. Encryption in Transit and at Rest

### 2.1 Encryption in Transit

All data transmitted between the client application and the server is encrypted using industry-standard protocols:

- **HTTPS/TLS**: All communications are encrypted using Transport Layer Security (TLS) version 1.2 or higher. TLS certificates are maintained and renewed according to security best practices.
- **API Communications**: REST API endpoints are accessed exclusively over HTTPS. Unencrypted HTTP connections are not permitted for any data transmission.
- **Database Connections**: Database connections are secured using SSL/TLS encryption. Connection strings include SSL mode requirements to ensure encrypted database communications.

### 2.2 Encryption at Rest

Sensitive data stored in the database and file system is protected through encryption mechanisms:

- **Database Encryption**: Patient data, including personally identifiable information (PII), is stored in an encrypted format. Database-level encryption is implemented using PostgreSQL's native encryption capabilities or application-level encryption where appropriate.
- **File Storage Encryption**: Uploaded files, including radiographs, diagnostic images, clinical documents, and odontogram-related files, are encrypted before storage. The radiography repository module implements encryption for all stored images to protect patient diagnostic data. File encryption keys are managed securely and stored separately from encrypted data.
- **Backup Encryption**: Database backups and file system backups are encrypted to prevent unauthorized access to data in backup storage.

### 2.3 Key Management

Encryption keys are managed according to the following principles:

- Encryption keys are stored securely and are not embedded in application code or configuration files.
- Key rotation policies are established to ensure that encryption keys are rotated periodically.
- Access to encryption keys is restricted to authorized system components only.

---

## 3. Data Retention and Deletion Policy

### 3.1 Data Retention Periods

Data retention periods are established based on legal requirements and operational needs:

- **Patient Records**: Patient medical records, including initial visit data, clinical histories, and odontogram records, are retained for a minimum period as required by applicable healthcare regulations. In the educational context at Stucom Córcega, patient records may be retained for the duration of the academic program plus an additional retention period as specified by institutional policies. Each patient record includes registration data (DNI, Social Security number), contact information, billing data, medical history (family antecedents, lifestyle habits, medication, allergies), and all associated clinical documentation.
- **Odontogram Data**: Odontogram records, including pathology markers, treatment indicators, and graphical representations, are retained in accordance with patient record retention policies. Each odontogram entry is associated with a specific visit and patient record.
- **Radiography Repository**: Clinical images and diagnostic documents stored in the radiography repository are retained in accordance with patient record retention policies. Images are associated with specific patient records and visits.
- **Appointment Records**: Appointment scheduling data, including doctor assignments, box allocations, and treatment associations, are retained for operational and educational purposes as specified by institutional policies.
- **Audit Logs**: Access and action audit logs are retained for a minimum of one year to support security monitoring and compliance verification.

### 3.2 Data Deletion Procedures

Data deletion is performed according to established procedures:

- **Automated Deletion**: Data that exceeds retention periods is identified and flagged for deletion through automated processes.
- **Secure Deletion**: Deletion procedures ensure that data is securely removed from all storage systems, including primary databases, backup systems, and file storage.
- **Deletion Verification**: Deletion operations are logged and verified to ensure complete removal of data from all systems.
- **Right to Erasure**: Patient requests for data deletion are processed in accordance with data protection regulations, subject to legal retention requirements.

### 3.3 Data Anonymization

For educational and research purposes, data may be anonymized rather than deleted:

- Patient identifiers are removed or replaced with pseudonyms to create anonymized datasets.
- Anonymization procedures are documented and verified to ensure that re-identification is not possible.
- Anonymized data may be retained beyond standard retention periods for educational and research purposes.

---

## 4. Access Audit and Logging Requirements

### 4.1 Audit Logging Scope

Comprehensive audit logging is implemented to track all access to patient data and system resources:

- **Authentication Events**: All login attempts, successful authentications, and authentication failures are logged with timestamps and user identifiers.
- **Data Access**: Access to patient records, including read and write operations, is logged with details of the user, accessed resource, timestamp, and action performed. This includes access to patient registration data, clinical histories, and odontogram records.
- **Odontogram Operations**: All odontogram interactions, including pathology markers, treatment indicators, and graphical modifications, are logged with user identification, patient record association, visit identifier, and operation details.
- **File Operations**: File uploads, downloads, and deletions in the radiography repository are logged with file identifiers, user information, patient association, and operation timestamps.
- **Appointment Management**: Appointment creation, modification, cancellation, and doctor assignment operations are logged with user identification, patient association, box allocation, and scheduling details.
- **Administrative Actions**: Administrative operations, including user management, role changes, and system configuration modifications, are logged with full details.

### 4.2 Log Data Requirements

Audit logs contain the following information:

- **User Identification**: User ID, username, and role are recorded for each logged event.
- **Resource Information**: Resource type, resource ID, and resource identifier are included in access logs.
- **Action Details**: Action type (create, read, update, delete), action result (success, failure), and result codes are recorded.
- **Temporal Information**: Precise timestamps are recorded for all logged events.
- **Network Information**: IP addresses and user agent information are logged for security analysis.

### 4.3 Log Storage and Protection

Audit logs are protected and stored according to security requirements:

- Logs are stored in a secure, tamper-resistant format to prevent unauthorized modification.
- Log access is restricted to authorized security and administrative personnel.
- Log retention periods are established to ensure logs are available for security investigations and compliance audits.
- Log integrity is verified through cryptographic mechanisms where applicable.

### 4.4 Log Monitoring and Analysis

Audit logs are monitored and analyzed to detect security incidents:

- Automated log analysis is performed to identify suspicious access patterns or unauthorized activities.
- Security alerts are generated for critical events, including multiple failed authentication attempts or unusual access patterns.
- Regular log reviews are conducted to ensure compliance with access policies and to identify potential security issues.

---

## 5. GDPR Considerations for Educational Context

### 5.1 Data Protection Principles

The system is designed to comply with General Data Protection Regulation (GDPR) principles, adapted for educational use:

- **Lawfulness, Fairness, and Transparency**: Data processing is conducted in a lawful manner with transparent communication to data subjects regarding data collection and use.
- **Purpose Limitation**: Personal data is collected and processed only for specified, legitimate purposes related to educational activities and clinical practice.
- **Data Minimization**: Only necessary personal data is collected and processed. Data collection is limited to what is required for the intended educational and clinical purposes.
- **Accuracy**: Personal data is kept accurate and up to date. Data subjects are provided with mechanisms to correct inaccurate information.
- **Storage Limitation**: Personal data is retained only for the period necessary to fulfill the purposes for which it was collected, as specified in the data retention policy.
- **Integrity and Confidentiality**: Appropriate technical and organizational measures are implemented to ensure data security and prevent unauthorized access or disclosure.

### 5.2 Data Subject Rights

Data subjects' rights are respected and facilitated:

- **Right of Access**: Data subjects are provided with access to their personal data upon request. Access requests are processed within the timeframes specified by applicable regulations.
- **Right to Rectification**: Data subjects may request correction of inaccurate personal data. Correction requests are processed promptly and verified.
- **Right to Erasure**: Data subjects may request deletion of their personal data, subject to legal retention requirements and legitimate interests.
- **Right to Data Portability**: Data subjects may request their data in a structured, commonly used format for transfer to another system.
- **Right to Object**: Data subjects may object to processing of their personal data for specific purposes, subject to legal requirements.

### 5.3 Data Processing Documentation

Data processing activities are documented to demonstrate compliance:

- **Processing Records**: Records of processing activities are maintained, including purposes of processing, categories of data subjects, categories of personal data, and recipients of data.
- **Data Protection Impact Assessments**: Impact assessments are conducted for high-risk processing activities to identify and mitigate privacy risks.
- **Data Breach Procedures**: Procedures are established for detecting, reporting, and responding to personal data breaches in accordance with regulatory requirements.

### 5.4 Educational Context Considerations

Specific considerations for the educational context are addressed:

- **Informed Consent**: Consent for data processing is obtained from data subjects with clear information about the educational nature of the system and data use. Patients are informed that their data will be used for educational purposes at Stucom Córcega for training students in the Auxiliary Nursing in Dental Clinics program.
- **Student Data Protection**: Additional protections are implemented for student user data, including restrictions on data sharing and enhanced access controls. Student access to patient records is limited to educational purposes and supervised activities.
- **Educational Use Context**: The system is designed specifically for educational simulation purposes. All patient data used in the system is understood to be part of a simulated clinical environment for training purposes, with appropriate safeguards to protect any real patient data that may be used.
- **Training and Awareness**: Users (students and instructors) are provided with training on data protection requirements and privacy best practices. Training includes proper handling of patient data, secure use of the odontogram system, and appropriate access to radiography repositories.

---

## 6. Implementation Notes

### 6.1 Technical Implementation

The security requirements outlined in this document are implemented through:

- **Symfony Security Component**: Authentication and authorization mechanisms, including role-based access control for the four defined roles (ROLE_ADMIN, ROLE_PROFESSIONAL, ROLE_STAFF, ROLE_USER)
- **Doctrine ORM**: Database access layer with encrypted field support for sensitive patient data at rest, including patient records, clinical histories, and odontogram data
- **PostgreSQL Database**: Secure database storage with SSL/TLS encryption for database connections, implementing the schema defined for patients, dentists, boxes, treatments, visits, pathologies, teeth, odontogram_details, and radiographies
- **HTTPS/TLS Configuration**: Encryption in transit for all API communications and web application access
- **File Upload Security**: Secure handling of radiography uploads with validation, encryption, and access control
- **Audit Logging**: Comprehensive logging mechanisms tracking access to patient records, odontogram operations, file operations, and appointment management
- **Database-Level Constraints**: Foreign key constraints, unique constraints (e.g. patients.dni uniqueness), and referential integrity enforcement across patients, visits, odontogram_details, and radiographies
- **Application-Level Access Controls**: Role-based restrictions on patient record access, odontogram modifications, radiography repository access, and appointment management functions

### 6.2 Compliance Verification

Compliance with these requirements is verified through:

- Regular security audits and assessments
- Access log reviews and analysis
- Data retention policy compliance checks
- Encryption configuration verification
- User access rights reviews

### 6.3 Maintenance and Updates

Security requirements are maintained and updated through:

- Regular review of security policies and procedures
- Updates to address emerging security threats
- Compliance with evolving regulatory requirements
- Continuous improvement of security controls

---

## Conclusion

This document establishes the security and privacy requirements for the FalconCare (Speed Falcons) dental clinic management system, ensuring that patient data, clinical documentation, odontogram records, and radiography files are protected through appropriate technical and organizational measures. These requirements are specifically designed for implementation in the educational context of Stucom Córcega, supporting the Auxiliary Nursing in Dental Clinics training program while maintaining compliance with data protection regulations and industry best practices.

All security measures are implemented to provide robust protection for patient data while enabling legitimate educational use of the system, including:
- Secure patient registration and clinical history management
- Protected interactive odontogram system operations
- Secure radiography repository access and file management
- Controlled appointment scheduling and resource allocation
- Appropriate access controls for students, instructors, and administrative staff

The system is designed to simulate real-world dental clinic operations while maintaining the highest standards of data protection and privacy for educational purposes.
