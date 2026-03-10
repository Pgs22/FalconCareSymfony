# Functional specification — FalconCare (wireframe-based)

---

## Introduction

This document is the single source of truth for the FalconCare functional specification. It breaks down all system functionalities derived from four wireframes: **Initial window (Dashboard)**, **Patient screen (Record)**, **Odontogram screen**, and **Agenda screen**. Each feature is linked to its corresponding screen and described in terms of: what it does, who uses it, inputs/outputs, and business rules. **Acceptance criteria** (bullet points) are provided for every feature so that QA can verify the implementation in a consistent and repeatable way.

The document contains the five required elements:

1. **Introduction** — This section: purpose, scope, and structure of the document.
2. **List of features** — A single table listing all 42 features by ID and name, with the wireframe (W1–W4) each belongs to.
3. **Feature ↔ Wireframe map** — *Wireframe reference* table (after this introduction) and *Feature ↔ Wireframe map (summary by wireframe)* in section 5, which maps each wireframe to its features.
4. **Complete API** — Section 7: full REST API surface (methods, routes, input parameters, request bodies, responses, JSON examples, validation rules, and authorization for all endpoints).
5. **Acceptance criteria** — Under every feature in sections 1–4: clear, verifiable bullet points so that QA can validate the implementation.

In addition, sections 1–4 provide detailed feature descriptions (what it does, who uses it, inputs/outputs, business rules), and section 6 summarises cross-cutting entities and concepts. All content is in English. The specification does not include implementation or code; it serves as a functional reference for development and testing.

---

## Wireframe reference

| ID | Screen | Wireframe title | Context |
|----|--------|------------------|---------|
| **W1** | Initial window | Dashboard with Box Schedule and Alerts (DentalFlow) | Main dashboard after login |
| **W2** | Patient screen | Contact Data Editing / Full Patient Record (DentalBoard) | A specific patient’s record |
| **W3** | Odontogram screen | Detailed Odontogram Explorer (DentalHub) | Odontogram per visit/patient |
| **W4** | Agenda screen | Dental Agenda — Appointment Management by Box (DentalEase) | Calendar by day/box |

---

## List of features

All features are listed below with a unique identifier (section number) and the wireframe (W1–W4) they belong to. This table is the **feature ↔ wireframe map** (by feature).

| Feature ID | Feature name | Wireframe |
|------------|--------------|-----------|
| 1.1 | Main navigation (sidebar) | W1 |
| 1.2 | General summary and contextual greeting | W1 |
| 1.3 | Global search | W1 |
| 1.4 | Notifications | W1 |
| 1.5 | Quick access to new patient | W1 |
| 1.6 | KPIs: Patients today | W1 |
| 1.7 | KPIs: Pending results | W1 |
| 1.8 | KPIs: Low stock alerts | W1 |
| 1.9 | Today’s schedule (list) | W1 |
| 1.10 | Per-appointment actions on the panel (more-options menu) | W1 |
| 1.11 | Assign slot in empty slot | W1 |
| 1.12 | Allergy alerts for the day | W1 |
| 1.13 | Stock status by room/box | W1 |
| 2.1 | Navigation and branding (header) | W2 |
| 2.2 | Patient summary profile (sidebar) | W2 |
| 2.3 | Internal record navigation | W2 |
| 2.4 | Incomplete registration notice and first visit | W2 |
| 2.5 | Record title and actions (Save / Export PDF) | W2 |
| 2.6 | Critical allergies (management) | W2 |
| 2.7 | Contact data (editing) | W2 |
| 2.8 | Diseases and conditions (checklist) | W2 |
| 2.9 | Visit history (list and odontogram per visit) | W2 |
| 2.10 | File and X-ray management | W2 |
| 2.11 | Legal footer | W2 |
| 3.1 | Header and search | W3 |
| 3.2 | Patient context and actions (Print / Save) | W3 |
| 3.3 | Full dentition (FDI / Universal system) | W3 |
| 3.4 | Tooth/surface status (pathology protocol) | W3 |
| 3.5 | Odontogram change history | W3 |
| 3.6 | Quick actions (Notes, X-ray, Files, Appointments) | W3 |
| 3.7 | AI analysis (scan) | W3 |
| 4.1 | Navigation and “Agenda” view | W4 |
| 4.2 | Monthly calendar (mini calendar) | W4 |
| 4.3 | Filter by boxes | W4 |
| 4.4 | Day occupancy | W4 |
| 4.5 | Disinfection buffer notice | W4 |
| 4.6 | View selector (Day / By Box / Week) and date | W4 |
| 4.7 | “New Appointment” button | W4 |
| 4.8 | Agenda grid by box (columns and bands) | W4 |
| 4.9 | Appointment block on the grid (content and actions) | W4 |
| 4.10 | Appointment detail panel (form) | W4 |
| 4.11 | Close detail panel | W4 |

**Total: 42 features** across 4 wireframes (W1: 13, W2: 11, W3: 7, W4: 11).

---

## 1. Dashboard features (W1)

### 1.1 Main navigation (sidebar)

| Aspect | Description |
|--------|-------------|
| **What it does** | Displays a fixed menu with: Dashboard (active), Agenda, Patients, Inventory, Settings. Includes branding (logo, name “DentalFlow”, type “Education”) and the logged-in user’s profile (photo, name, role). |
| **Who uses it** | Authenticated user (e.g. Dr. Chen, Endodontist). |
| **Inputs** | Click on each navigation link. |
| **Outputs** | Switch to the selected section; active item highlighted (Dashboard). |
| **Business rules** | The active item must be clearly distinct; navigation is persistent across all derived views. |

**Wireframe:** W1 (left sidebar).

**Acceptance criteria**

- When the user is on the dashboard, the sidebar shows exactly five navigation items: Dashboard, Agenda, Patients, Inventory, Settings.
- The currently active section (Dashboard) is visually distinct from the other menu items (e.g. different background or colour).
- Clicking each navigation link loads the corresponding section; the active indicator updates to the selected item.
- The sidebar displays the application logo and name (“DentalFlow”) and the logged-in user’s photo, name, and role.
- The sidebar remains visible and unchanged when navigating between Dashboard, Agenda, Patients, Inventory, and Settings.

---

### 1.2 General summary and contextual greeting

| Aspect | Description |
|--------|-------------|
| **What it does** | Shows a summary title (“General Summary”), personalised greeting (e.g. “Good morning, Dr. Chen”) and a context line (e.g. “Here is the summary of your daily practice”). |
| **Who uses it** | Authenticated user (clinician/staff). |
| **Inputs** | User session (name, role). |
| **Outputs** | Greeting text and subtitle in the panel header. |
| **Business rules** | The greeting must use the logged-in user’s name and/or role; the message is informational and not editable. |

**Wireframe:** W1 (header and top section of content).

**Acceptance criteria**

- The page displays a “General Summary” (or equivalent) title in the header area.
- The greeting text includes the logged-in user’s first or full name (e.g. “Good morning, Dr. Chen”).
- A subtitle or context line is visible (e.g. “Here is the summary of your daily practice”).
- When the logged-in user changes, the greeting updates to reflect the new user’s name.
- The greeting and subtitle are read-only (not editable by the user).

---

### 1.3 Global search

| Aspect | Description |
|--------|-------------|
| **What it does** | Provides a single search field for “patients, records, appointments…”. The field has a search icon and descriptive placeholder. |
| **Who uses it** | Authenticated user. |
| **Inputs** | Text entered by the user in the search field. |
| **Outputs** | (Not defined in the wireframe; assumed to be a list or redirect to results for patients/records/appointments.) |
| **Business rules** | Search must cover at least patients, records, and appointments; scope may depend on role. |

**Wireframe:** W1 (header, search bar).

**Acceptance criteria**

- A single search input is visible in the header with a search (magnifier) icon and a placeholder such as “Search patients, records, appointments…”.
- Submitting a search (Enter or button) returns or navigates to results that include matches from at least one of: patients, records, appointments.
- Empty search or invalid input is handled without breaking the page (e.g. empty results or validation message).
- Search scope or results respect the user’s role (e.g. restricted data is not returned to unauthorised roles).

---

### 1.4 Notifications

| Aspect | Description |
|--------|-------------|
| **What it does** | Shows a notifications button with icon and a visual indicator (red dot) when there are unread notifications. |
| **Who uses it** | Authenticated user. |
| **Inputs** | Click on the button. |
| **Outputs** | (The wireframe does not show the dropdown panel; a list of notifications is assumed.) |
| **Business rules** | A “has notifications” state must drive the indicator; notifications are user-specific. |

**Wireframe:** W1 (header, bell icon).

**Acceptance criteria**

- A notifications button (bell icon) is visible in the header.
- When the user has at least one unread notification, a clear visual indicator (e.g. red dot or badge) is shown on or next to the button.
- When the user has no unread notifications, the indicator is hidden or inactive.
- Clicking the button opens a panel or page that lists notifications for the current user only.
- Marking notifications as read (if implemented) updates the indicator accordingly.

---

### 1.5 Quick access to new patient

| Aspect | Description |
|--------|-------------|
| **What it does** | Primary “New Patient” button with “add” icon to start the new patient registration flow. |
| **Who uses it** | User with permission to register patients (clinician/staff/admin). |
| **Inputs** | Click on “New Patient”. |
| **Outputs** | Navigation or opening of new patient form/modal. |
| **Business rules** | Only authorised users may create patients; the flow must be accessible from the dashboard. |

**Wireframe:** W1 (header, “New Patient” button).

**Acceptance criteria**

- A primary “New Patient” button with an “add” (plus) icon is visible in the header on the dashboard.
- Clicking “New Patient” opens the new patient registration flow (form, modal, or dedicated page).
- Users without permission to create patients do not see the button, or see it disabled, or receive 403 when attempting the action.
- After successfully creating a patient from this flow, the user is redirected or the list updates to include the new patient.

---

### 1.6 KPIs: Patients today

| Aspect | Description |
|--------|-------------|
| **What it does** | Card showing the number of “Patients Today” (e.g. 12) and a variation (e.g. “+4%”). Includes icon (group) and label “Patients Today”. |
| **Who uses it** | Authenticated user (clinician/staff). |
| **Inputs** | Aggregated data from appointments for the current day. |
| **Outputs** | Total number of patients with an appointment today; percentage variation versus a reference period. |
| **Business rules** | “Patients today” = patients with at least one appointment on the current day; the variation must be defined (e.g. same weekday in the previous week). |

**Wireframe:** W1 (“Patients Today” card).

**Acceptance criteria**

- A card is displayed with the label “Patients Today” (or equivalent) and a numeric value.
- The number equals the count of distinct patients who have at least one appointment on the current day (server or client date).
- When a variation indicator is shown (e.g. “+4%”), it is calculated against a defined reference (e.g. same weekday previous week) and the value is numerically correct.
- The card includes the expected icon (e.g. group/people). Updating today’s appointments and refreshing the dashboard updates the displayed number.

---

### 1.7 KPIs: Pending results

| Aspect | Description |
|--------|-------------|
| **What it does** | Card showing “Pending Results” (e.g. 3) with label “Action Required” and icon (biotech). |
| **Who uses it** | Authenticated user (clinician/staff). |
| **Inputs** | Records of results (e.g. lab, reports) in “pending” state. |
| **Outputs** | Number of results pending review or completion. |
| **Business rules** | A result is “pending” according to clinical or workflow criteria (e.g. not reviewed, not closed); it requires action by the professional. |

**Wireframe:** W1 (“Pending Results” card).

**Acceptance criteria**

- A card is displayed with the label “Pending Results” (or equivalent) and a numeric value.
- The number equals the count of results (e.g. lab, reports) in a “pending” state as defined by the system (e.g. not reviewed or not closed).
- The card shows an “Action Required” (or equivalent) label and the expected icon (e.g. biotech). When the pending count is zero, the card shows 0 and the label/icon remain consistent.

---

### 1.8 KPIs: Low stock alerts

| Aspect | Description |
|--------|-------------|
| **What it does** | Card showing “Low Stock Alerts” (e.g. 1) with inventory icon. |
| **Who uses it** | Authenticated user (staff/admin, possibly clinician). |
| **Inputs** | Inventory levels compared with “low stock” thresholds. |
| **Outputs** | Number of active low stock alerts. |
| **Business rules** | An alert is generated when an item’s stock falls below the defined threshold; the number may represent items or alerts depending on design. |

**Wireframe:** W1 (“Low Stock Alerts” card).

**Acceptance criteria**

- A card is displayed with the label “Low Stock Alerts” (or equivalent) and a numeric value.
- The number equals the count of active low-stock alerts (e.g. items or boxes below the configured threshold).
- The card displays the expected icon (e.g. inventory). When no items are below threshold, the card shows 0. When inventory data is updated and thresholds are crossed, the displayed count updates after refresh or in real time.

---

### 1.9 Today’s schedule (list)

| Aspect | Description |
|--------|-------------|
| **What it does** | Lists “Today’s Agenda” with time slots. Each row is either: (a) an appointment with a patient (name, box, doctor, treatment, duration, status “In Progress”/“Confirmed”/“Arrived”) or (b) an “Empty” slot with “Assign Slot” option. Includes “View full calendar” link. |
| **Who uses it** | Authenticated user (clinician/staff). |
| **Inputs** | Current date; day’s appointments linked to boxes and doctors. |
| **Outputs** | List ordered by time: start time, duration, patient, box, doctor, treatment, status. Empty slots identifiable and actionable. |
| **Business rules** | Appointments are shown in chronological order for the day; slots without an appointment allow assigning a new slot; statuses (In Progress, Confirmed, Arrived) follow defined workflow rules. |

**Wireframe:** W1 (“Today’s Agenda” block).

**Acceptance criteria**

- A “Today’s Agenda” (or equivalent) block is displayed with a list of time slots for the current day.
- Each row is either an appointment (showing patient name, box, doctor, treatment, duration, and status) or an “Empty” slot with an “Assign Slot” (or equivalent) action.
- Appointments are ordered chronologically by start time. Status values shown are from the defined set (e.g. In Progress, Confirmed, Arrived).
- A “View full calendar” (or equivalent) link is present and navigates to the full agenda/calendar view.
- Empty slots correctly reflect time range and box; clicking “Assign Slot” opens the appointment creation flow with date, time, and box pre-filled.

---

### 1.10 Per-appointment actions on the panel (more-options menu)

| Aspect | Description |
|--------|-------------|
| **What it does** | Each appointment row has a “more_vert” button that opens a menu of actions for that appointment (edit, cancel, etc., as per design). |
| **Who uses it** | Authenticated user with permission to manage appointments. |
| **Inputs** | Click on the options button; selection of an action. |
| **Outputs** | Execution of the action (edit appointment, cancel, change status, etc.). |
| **Business rules** | Available actions may depend on appointment status and role; cancel may require confirmation or reason. |

**Wireframe:** W1 (“more_vert” button on each appointment).

**Acceptance criteria**

- Each appointment row in “Today’s Agenda” has a visible “more” (e.g. more_vert) button or equivalent.
- Clicking it opens a menu that includes at least “Edit” and “Cancel” (or equivalent actions).
- Selecting “Edit” opens the appointment in edit mode or the appointment detail form with data loaded. Selecting “Cancel” triggers the cancel flow (with confirmation if required).
- Actions shown or allowed may depend on appointment status and user role; unauthorised actions are hidden or return 403.

---

### 1.11 Assign slot in empty slot

| Aspect | Description |
|--------|-------------|
| **What it does** | On an “Empty” row in the day’s agenda, an “Assign Slot” link/button creates a new appointment in that slot (time and box already suggested). |
| **Who uses it** | User with permission to create/manage appointments. |
| **Inputs** | Click on “Assign Slot”; time and box of the slot. |
| **Outputs** | Opening of the appointment creation form or view with date/time/box pre-filled. |
| **Business rules** | The slot must correspond to a valid box and time range; assignment is not allowed if the slot is already occupied. |

**Wireframe:** W1 (“Empty” rows with “Assign Slot”).

**Acceptance criteria**

- Rows in “Today’s Agenda” that have no appointment are clearly labelled as “Empty” (or equivalent) and show the time range and box.
- Each empty row has an “Assign Slot” (or equivalent) link or button. Clicking it opens the appointment creation form or view.
- The form opens with the slot’s date, start time, and box pre-filled and not editable (or clearly defaulted). The user can select patient, doctor, duration, and other fields.
- Submitting the form creates the appointment in that slot; if the slot was meanwhile occupied, the system returns a validation or conflict error (e.g. 422 or 409).

---

### 1.12 Allergy alerts for the day

| Aspect | Description |
|--------|-------------|
| **What it does** | “Allergy Alerts” block listing patients with an appointment today who have registered allergies. Each item shows: patient name, appointment time, allergy type (e.g. Penicillin, Latex, Local anaesthesia reaction). Levels are distinguished (e.g. red for critical / amber for sensitivity). “View more alerts for the day” button. |
| **Who uses it** | Clinician and staff (patient safety). |
| **Inputs** | Day’s appointments; each patient’s allergy/medication data. |
| **Outputs** | List of patients with allergy and appointment time; visual classification (red/amber). |
| **Business rules** | Only patients with an appointment that day are shown; allergies must be recorded in the record; severity may determine colour (critical vs. precaution). |

**Wireframe:** W1 (“Allergy Alerts” panel).

**Acceptance criteria**

- An “Allergy Alerts” (or equivalent) block is displayed listing only patients who have an appointment today and have at least one allergy recorded in their record.
- Each list item shows patient name, appointment time, and allergy type (e.g. Penicillin, Latex, Local anaesthesia reaction).
- Critical allergies are visually distinguished (e.g. red) from precaution/sensitivity (e.g. amber). A “View more alerts for the day” (or equivalent) button is present and expands or navigates to show all such alerts.
- Adding or removing an allergy in a patient record, or changing today’s appointments, updates the list after refresh or in real time.

---

### 1.13 Stock status by room/box

| Aspect | Description |
|--------|-------------|
| **What it does** | “Stock Status” block with progress bars per “Box” (e.g. Box 1 Treatment 82%, Box 2 Hygiene 18%). Includes explanatory text (e.g. “Low on Composite”) and “Restock Request” button. Tooltip/info: “Monitor inventory levels by treatment room”. |
| **Who uses it** | Staff and/or admin (inventory management). |
| **Inputs** | Stock levels per box/room; thresholds for “healthy” vs “low”. |
| **Outputs** | Percentage per box; “supplies healthy” or “low in [product]” indication; restock request action. |
| **Business rules** | Percentage must be calculated against a maximum or target per box; below threshold is “low stock” and shows an alert; restock request starts an internal flow (not detailed in the wireframe). |

**Wireframe:** W1 (“Stock Status” panel).

**Acceptance criteria**

- A “Stock Status” (or equivalent) block shows at least one progress bar per box/room (e.g. Box 1 Treatment, Box 2 Hygiene).
- Each bar displays a percentage (e.g. 82%, 18%) calculated against the defined maximum or target for that box. When below the low-stock threshold, explanatory text (e.g. “Low on Composite”) is shown and the bar or label uses an alert style (e.g. red).
- A “Restock Request” (or equivalent) button is present; clicking it initiates the restock request flow (form or confirmation). A tooltip or info icon explains that the block monitors inventory levels by treatment room.

---

## 2. Patient screen / Record features (W2)

### 2.1 Navigation and branding (header)

| Aspect | Description |
|--------|-------------|
| **What it does** | Header with logo, name “DentalBoard”, search “Search patients…”, “New Patient” button, notifications, and user avatar. |
| **Who uses it** | Authenticated user. |
| **Inputs** | Search query; click on New Patient / notifications / profile. |
| **Outputs** | Navigation or search results. |
| **Business rules** | Consistent with the rest of the application; header search may be global for patients. |

**Wireframe:** W2 (header).

**Acceptance criteria**

- The patient record screen displays a header with the application logo and name (e.g. “DentalBoard”), a search field (e.g. “Search patients…”), a “New Patient” button, notifications icon, and user avatar.
- The search field allows searching for patients; submitting a query returns or navigates to matching patients. Clicking “New Patient” opens the new patient flow. Clicking notifications or avatar behaves as defined (e.g. open notifications or profile).
- The header is consistent in structure and behaviour with the rest of the application.

---

### 2.2 Patient summary profile (sidebar)

| Aspect | Description |
|--------|-------------|
| **What it does** | Side panel shows photo, full name (e.g. Sarah Jenkins), ID and age (e.g. #928374, 24 years), and status “Active Treatment”. |
| **Who uses it** | User viewing the record. |
| **Inputs** | Selected patient data (identity, treatment status). |
| **Outputs** | Fixed display of the current patient in the section. |
| **Business rules** | ID must be unique; “Active Treatment” is a defined status (e.g. has an open treatment plan). |

**Wireframe:** W2 (left sidebar, profile).

**Acceptance criteria**

- The sidebar shows the current patient’s photo (or placeholder), full name, ID (e.g. #928374), and age (or date of birth). The patient ID is unique in the system.
- A status label is displayed (e.g. “Active Treatment”); the value corresponds to the patient’s current status in the database. When the patient record is opened for another patient, the sidebar updates to show that patient’s data.

---

### 2.3 Internal record navigation

| Aspect | Description |
|--------|-------------|
| **What it does** | Side menu: Full Record (active), Odontograms, File Management, Appointments and Visits, Billing. “Log out” button at the bottom. |
| **Who uses it** | User viewing or editing the record. |
| **Inputs** | Click on each section. |
| **Outputs** | Content change within the record (same screen, different sections). |
| **Business rules** | Each item goes to the corresponding view for the same patient; “Full Record” groups contact data, allergies, conditions, visit history. |

**Wireframe:** W2 (sidebar, navigation).

**Acceptance criteria**

- The sidebar lists: Full Record (active by default on this screen), Odontograms, File Management, Appointments and Visits, Billing. A “Log out” button is at the bottom.
- Clicking each item loads the corresponding content for the same patient (same screen, different section). The active section is visually indicated. “Full Record” shows contact data, allergies, conditions, and visit history. “Log out” ends the session as per application behaviour.

---

### 2.4 Incomplete registration notice and first visit

| Aspect | Description |
|--------|-------------|
| **What it does** | Banner: “New Patient Registration Incomplete” with text “You must schedule the first visit to activate the record” and “Schedule First Visit” button. |
| **Who uses it** | User managing the record (clinician/staff). |
| **Inputs** | Patient status (incomplete registration = no first visit scheduled/completed). |
| **Outputs** | Visible notice and “Schedule First Visit” action. |
| **Business rules** | The record is “incomplete” until the first visit exists (and optionally has been completed); scheduling the first visit is a mandatory flow to activate the record. |

**Wireframe:** W2 (yellow banner).

**Acceptance criteria**

- When the patient is in “incomplete registration” state (e.g. no first visit scheduled or completed), a visible banner is displayed with a message such as “New Patient Registration Incomplete” and “You must schedule the first visit to activate the record.”
- The banner includes a “Schedule First Visit” (or equivalent) button. Clicking it opens the flow to schedule the first appointment for this patient. Once the first visit is scheduled (and optionally completed), the banner is hidden or the record is no longer considered incomplete according to business rules.

---

### 2.5 Record title and actions (Save / Export PDF)

| Aspect | Description |
|--------|-------------|
| **What it does** | Title “Full Patient Record” with description, and “Export PDF” and “Save Changes” buttons. |
| **Who uses it** | User editing or viewing the record. |
| **Inputs** | Click on “Save Changes” (persists form changes); click on “Export PDF”. |
| **Outputs** | Data saved to the database; download of record PDF (content to be defined). |
| **Business rules** | Save is only allowed when there are valid changes; the PDF must include only data permitted by law (e.g. GDPR/data protection) and for authorised use only. |

**Wireframe:** W2 (main content header).

**Acceptance criteria**

- The record view shows a title “Full Patient Record” (or equivalent) with a short description, plus “Export PDF” and “Save Changes” buttons.
- “Save Changes” is enabled only when there are unsaved changes; clicking it persists all valid form data and shows success feedback. Invalid data shows validation messages and does not save. “Export PDF” triggers download (or generation) of a PDF containing only data permitted by data protection rules and for authorised use.

---

### 2.6 Critical allergies (management)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Critical Allergies” block with a list of allergies (e.g. Penicillin, Latex). Each item has a remove option (close icon). “+” button to add a new allergy. |
| **Who uses it** | Clinician or authorised staff. |
| **Inputs** | Current allergy list; add action (name/type); remove action (item). |
| **Outputs** | Updated list of critical allergies; persistence in the record. |
| **Business rules** | Critical allergies must be highly visible; add/remove must be logged (audit); removal may require confirmation when there is clinical use. |

**Wireframe:** W2 (“Critical Allergies” card).

**Acceptance criteria**

- A “Critical Allergies” block lists all critical allergies for the patient (e.g. Penicillin, Latex). Each item has a remove (close) control. A “+” (or equivalent) button adds a new allergy.
- Adding an allergy: user enters type/name (and optionally severity); on submit the item appears in the list and is persisted. Removing an allergy: user confirms if required; the item is removed from the list and from the database. Add/remove actions are auditable (logged) as per business rules. The block is always visible when viewing the full record so critical allergies are prominent.

---

### 2.7 Contact data (editing)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Contact Data” section with fields: Mobile Phone, Email, Address. “Edit” button to switch to edit mode; “Confirm Data” button to validate and save. |
| **Who uses it** | Staff or clinician (updating patient data). |
| **Inputs** | Current values; user edits phone, email, address. |
| **Outputs** | Contact data saved; possible validation (phone format, email). |
| **Business rules** | Email and phone must be valid; changes must be persisted and may be kept in history for traceability. |

**Wireframe:** W2 (“Contact Data” block).

**Acceptance criteria**

- The “Contact Data” section displays Mobile Phone, Email, and Address. An “Edit” button switches to edit mode (fields become editable). A “Confirm Data” (or equivalent) button validates and saves; after save, the section returns to read-only or shows success.
- Validation: email must be a valid format; phone must match the defined format (if any). Invalid input shows field-level errors and does not persist. Saved data is stored in the database and, if required, changes are traceable (e.g. history or audit).

---

### 2.8 Diseases and conditions (checklist)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Diseases and Conditions” section with a checklist (e.g. Type 2 Diabetes, Hypertension, Asthma, Smoking). Each item may have a note (e.g. “On drug therapy”, “No reported history”). |
| **Who uses it** | Clinician or staff. |
| **Inputs** | Selecting/deselecting conditions; notes per condition. |
| **Outputs** | List of active/inactive conditions and associated notes saved in the record. |
| **Business rules** | Conditions are predefined or configurable lists; notes are free text with an optional length limit. |

**Wireframe:** W2 (“Diseases and Conditions” block).

**Acceptance criteria**

- The “Diseases and Conditions” section shows a checklist of predefined or configurable conditions (e.g. Type 2 Diabetes, Hypertension, Asthma, Smoking). Each item can be checked/unchecked and may have an optional note (e.g. “On drug therapy”, “No reported history”).
- Saving the record persists the selected conditions and notes. Notes respect any maximum length. The list reflects the last saved state when the record is reopened.

---

### 2.9 Visit history (list and odontogram per visit)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Visit History” with a chronological list of visits. Each visit shows: treatment title (e.g. Root Canal, Cleaning and Check-up), date, short description, “View Visit Odontogram” and “Medical Notes” buttons. “View Full History” button. |
| **Who uses it** | Clinician or staff. |
| **Inputs** | Click on “View Visit Odontogram” or “Medical Notes”; click on “View Full History”. |
| **Outputs** | Navigation to that visit’s odontogram or to notes; full history list. |
| **Business rules** | Each visit may have an associated odontogram; history is ordered by date (most recent first). |

**Wireframe:** W2 (“Visit History” block).

**Acceptance criteria**

- “Visit History” displays a chronological list of visits (most recent first). Each visit shows treatment title (e.g. Root Canal, Cleaning and Check-up), date, and a short description. Buttons “View Visit Odontogram” and “Medical Notes” (or equivalent) are present. “View Full History” is available.
- Clicking “View Visit Odontogram” navigates to the odontogram for that visit (or shows a message if none exists). Clicking “Medical Notes” opens notes for that visit. “View Full History” shows the complete list. Each visit with an associated odontogram is linked correctly.

---

### 2.10 File and X-ray management

| Aspect | Description |
|--------|-------------|
| **What it does** | “File and X-ray Management” section with: “View X-ray History”, “Filter”, “Upload New File” buttons; file grid (e.g. panoramic image, consent PDF) with type (X-ray/Document), name, upload date and size. Per-file actions: zoom, download. “Drag new files” area (JPG, PNG, PDF, max 15MB). |
| **Who uses it** | Clinician or staff. |
| **Inputs** | File selection for upload; filters; click to view/download. |
| **Outputs** | Listed files; new files linked to the patient; download or viewing. |
| **Business rules** | Allowed formats and maximum size (15MB) must be validated; files are private to the patient (data protection); X-ray history may be a subset filtered by type. |

**Wireframe:** W2 (“File and X-ray Management” section).

**Acceptance criteria**

- The section includes “View X-ray History”, “Filter”, and “Upload New File” (or equivalent). A file grid shows type (X-ray/Document), name, upload date, and size. Each file has zoom and download actions. A “Drag new files” area accepts JPG, PNG, PDF with a maximum size of 15MB.
- Upload: only allowed formats and size are accepted; invalid files show a clear error. New files are associated with the current patient and appear in the list. Filter by type shows the correct subset. Download returns the file content; access is restricted to users authorised for that patient’s record (data protection).

---

### 2.11 Legal footer

| Aspect | Description |
|--------|-------------|
| **What it does** | Text: “© 2026 Clínica Dental FalconCare • Información médica confidencial • Protegida por la ley de protección de datos”. |
| **Who uses it** | All (informational). |
| **Inputs** | None. |
| **Outputs** | Visible legal and confidentiality message. |
| **Business rules** | The system must comply with data protection law and treat information as confidential. |

**Wireframe:** W2 (footer).

**Acceptance criteria**

- The footer displays the required legal and confidentiality text (e.g. “© 2026 Clínica Dental FalconCare • Información médica confidencial • Protegida por la ley de protección de datos”). The text is visible on the patient record screen (and optionally on other screens as per design). The system’s handling of personal data complies with the stated data protection obligations.

---

## 3. Odontogram screen features (W3)

### 3.1 Header and search

| Aspect | Description |
|--------|-------------|
| **What it does** | Header with “DentalHub” logo, search “Search patients or treatments…”, notifications (with indicator), and user profile (e.g. Dr. Sarah Wilson, Orthodontist). |
| **Who uses it** | Authenticated user. |
| **Inputs** | Search text; click on notifications/profile. |
| **Outputs** | Search results or navigation. |
| **Business rules** | Same search policy as on other screens. |

**Wireframe:** W3 (header).

**Acceptance criteria**

- The odontogram screen header shows the application logo and name (e.g. “DentalHub”), a search field (e.g. “Search patients or treatments…”), a notifications control with indicator, and the current user’s profile (name and role). Search and notifications behave consistently with the rest of the application (same policy and scope).

---

### 3.2 Patient context and actions (Print / Save)

| Aspect | Description |
|--------|-------------|
| **What it does** | Title “Odontogram Explorer”, subtitle with “Patient: Juan Pérez (ID: #84921)” and “Last Visit: 12 Oct, 2026”. “Print Report” and “Save Treatment” buttons. |
| **Who uses it** | Clinician working with the odontogram. |
| **Inputs** | Click on “Print Report” or “Save Treatment”. |
| **Outputs** | Printing of odontogram/report; persistence of treatment changes in the odontogram. |
| **Business rules** | Save must associate changes to the current visit (or create a visit if applicable); print must reflect the current state of the odontogram. |

**Wireframe:** W3 (top area of content).

**Acceptance criteria**

- The screen shows the title “Odontogram Explorer” (or equivalent), a subtitle with the current patient’s name and ID (e.g. “Patient: Juan Pérez (ID: #84921)”), and “Last Visit” date. “Print Report” and “Save Treatment” buttons are visible.
- “Save Treatment” persists the current odontogram changes and associates them to the current visit (or creates a visit if applicable). Success or error feedback is shown. “Print Report” produces a print view or file that reflects the current state of the odontogram (teeth and statuses). The patient and visit context are correct for the opened record.

---

### 3.3 Full dentition (FDI / Universal system)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Full Dentition (FDI)” view with quadrants: Upper Right, Upper Left, Lower Right, Lower Left. Each tooth has an FDI number (e.g. 18–11, 21–28, 31–38, 41–48; primary 51–55, 61–65, 71–75, 81–85). “FDI System” / “Universal” selector. |
| **Who uses it** | Clinician. |
| **Inputs** | System selection (FDI/Universal); click on tooth or surface. |
| **Outputs** | Odontogram view in the chosen system; ability to edit status per tooth/surface. |
| **Business rules** | FDI numbering is standard; conversion or “Universal” view must be consistent; each tooth may have a status per surface. |

**Wireframe:** W3 (tooth grid).

**Acceptance criteria**

- The “Full Dentition (FDI)” view displays four quadrants (Upper Right, Upper Left, Lower Right, Lower Left) with correct FDI numbering for permanent teeth (e.g. 18–11, 21–28, 31–38, 41–48) and primary teeth where applicable (e.g. 51–55, 61–65, 71–75, 81–85). A selector allows switching between “FDI” and “Universal” systems.
- In FDI mode, tooth numbers match the standard FDI notation. In Universal mode, the mapping is consistent and documented. Clicking a tooth or surface allows selecting or editing its status. The grid updates visually when status changes.

---

### 3.4 Tooth/surface status (pathology protocol)

| Aspect | Description |
|--------|-------------|
| **What it does** | “Pathology Protocol” panel with options: Healthy/Clean, Caries, Amalgam, Composite, Ceramic Crown, Missing/Extraction. The user selects an option and applies it to the tooth/surface on the odontogram. Colours: healthy (neutral), caries (red), amalgam (grey), composite (yellow), crown (blue/indigo), missing (marked/faded). |
| **Who uses it** | Clinician. |
| **Inputs** | Pathology selection; selection of tooth and/or surface on the odontogram. |
| **Outputs** | Visual update of the tooth/surface; recording of status (and link to the visit). |
| **Business rules** | A tooth/surface has one status per pathology; “Healthy” resets; “Missing” indicates extraction/loss; statuses must be saved with the visit. |

**Wireframe:** W3 (lateral panel “Pathology Protocol”).

**Acceptance criteria**

- The “Pathology Protocol” panel offers: Healthy/Clean, Caries, Amalgam, Composite, Ceramic Crown, Missing/Extraction. Each option has the specified colour (e.g. caries=red, amalgam=grey, composite=yellow, crown=blue/indigo, missing=faded). The user selects an option then clicks a tooth/surface to apply it.
- Applying “Healthy” resets the tooth/surface to healthy. Applying “Missing” marks it as extracted/lost. The selected status is stored with the visit and reflected in the grid. Each tooth/surface has at most one pathology status at a time. Saving persists all changes.

---

### 3.5 Odontogram change history

| Aspect | Description |
|--------|-------------|
| **What it does** | “Change History” table: Date and Time, Tooth #, Surface, Action (e.g. Examined, Crown Prep, Note Added), Doctor, Status (Pending/Saved). “View All” button. |
| **Who uses it** | Clinician or staff (review/audit). |
| **Inputs** | Already recorded change data. |
| **Outputs** | Ordered list of odontogram changes for the patient. |
| **Business rules** | Each change must be recorded with timestamp, tooth, surface, action, doctor and status; “Saved” = persisted. |

**Wireframe:** W3 (“Change History” table).

**Acceptance criteria**

- A “Change History” table is displayed with columns: Date and Time, Tooth #, Surface, Action (e.g. Examined, Crown Prep, Note Added), Doctor, Status (e.g. Pending, Saved). Rows are ordered by date/time (e.g. most recent first). A “View All” button expands or navigates to the full list.
- Each row corresponds to a recorded change; “Saved” means the change was persisted. New actions (e.g. applying a pathology) create a new row with the current user as Doctor and the correct tooth, surface, and action. The table is read-only for QA verification of audit trail.

---

### 3.6 Quick actions (Notes, X-ray, Files, Appointments)

| Aspect | Description |
|--------|-------------|
| **What it does** | Four buttons: Notes, X-ray, Files, Appointments. They navigate to clinical notes, X-rays, file management, or the patient’s appointments from the odontogram context. |
| **Who uses it** | Clinician. |
| **Inputs** | Click on each button. |
| **Outputs** | Navigation to the corresponding section for the same patient. |
| **Business rules** | Context (patient, visit) is preserved when switching section. |

**Wireframe:** W3 (lateral panel, “Quick Actions”).

**Acceptance criteria**

- Four buttons are visible: Notes, X-ray, Files, Appointments. Clicking each navigates to the corresponding section (clinical notes, X-rays, file management, or appointments) for the same patient and, when applicable, the same visit. The patient and visit context are preserved in the target view (no wrong patient or visit is shown).

---

### 3.7 AI analysis (scan)

| Aspect | Description |
|--------|-------------|
| **What it does** | “AI Analysis” block with text “Detect hidden caries with our AI assistant” and “Run Scan” button. |
| **Who uses it** | Clinician. |
| **Inputs** | Click on “Run Scan”; possibly odontogram data or images. |
| **Outputs** | (Not detailed in the wireframe: assumed to be a report or caries detection suggestions.) |
| **Business rules** | AI output is supportive and does not replace clinical judgement; data used must comply with personal and health data regulations. |

**Wireframe:** W3 (lateral panel, “AI Analysis”).

**Acceptance criteria**

- An “AI Analysis” block is displayed with explanatory text (e.g. “Detect hidden caries with our AI assistant”) and a “Run Scan” (or equivalent) button. Clicking “Run Scan” triggers the AI scan (using odontogram data or images as designed). The result is presented as a report or suggestions; the UI clearly states that the output is supportive and does not replace clinical judgement. Data used for the scan is handled in line with personal and health data regulations. If the feature is not yet implemented, the button may be disabled or show a “Coming soon” message.

---

## 4. Agenda screen features (W4)

### 4.1 Navigation and “Agenda” view

| Aspect | Description |
|--------|-------------|
| **What it does** | Header with “DentalEase” logo, navigation: Dashboard, Agenda (active), Patients, Inventory. Search “Search appointments…”, user avatar. |
| **Who uses it** | Authenticated user. |
| **Inputs** | Click on each navigation item; search text. |
| **Outputs** | View change or appointment search results. |
| **Business rules** | The “Agenda” view is the main one on this screen; search filters by appointments. |

**Wireframe:** W4 (header).

**Acceptance criteria**

- The agenda screen header shows the application logo and name (e.g. “DentalEase”), navigation items (Dashboard, Agenda active, Patients, Inventory), a search field (e.g. “Search appointments…”), and the user avatar. Clicking each navigation item loads the correct section. The Agenda item is visually active on this screen. Search filters or finds appointments as designed.

---

### 4.2 Monthly calendar (mini calendar)

| Aspect | Description |
|--------|-------------|
| **What it does** | Side panel with month (e.g. October 2026), previous/next arrows, day grid (Sun–Sat). The selected day (e.g. 5) is highlighted. |
| **Who uses it** | User managing the agenda. |
| **Inputs** | Click on arrows (change month); click on a day. |
| **Outputs** | Month change; selection of the day whose agenda is shown in the main area. |
| **Business rules** | The selected day determines the date for the “Today’s Agenda” / per-box view. |

**Wireframe:** W4 (left sidebar, calendar).

**Acceptance criteria**

- A mini calendar shows the current month (e.g. October 2026) with previous/next controls and a day grid (e.g. Sun–Sat). The selected day (e.g. 5) is clearly highlighted. Clicking a day sets it as the selected date; the main agenda area updates to show that day’s schedule. Clicking previous/next changes the month; the selected day, if still visible, remains selected or is reset as per design.

---

### 4.3 Filter by boxes

| Aspect | Description |
|--------|-------------|
| **What it does** | “Filter Boxes” section with a checkbox per box (BOX 1, BOX 2, …). Allows showing/hiding agenda columns by box. |
| **Who uses it** | User viewing the agenda. |
| **Inputs** | Checking/unchecking each box. |
| **Outputs** | Columns visible in the agenda grid according to selected boxes. |
| **Business rules** | At least one box must be visible; the box list comes from configuration or catalogue. |

**Wireframe:** W4 (sidebar, “Filter Boxes”).

**Acceptance criteria**

- A “Filter Boxes” section lists each box (e.g. BOX 1, BOX 2) with a checkbox. Checking a box shows that box’s column in the agenda grid; unchecking hides it. At least one box must remain selected (or the grid shows no columns with a clear message). The list of boxes matches the configured or catalogue data.

---

### 4.4 Day occupancy

| Aspect | Description |
|--------|-------------|
| **What it does** | “Occupancy” indicator with progress bar (e.g. 78%) and text “19/24 SLOTS OCCUPIED TODAY”. |
| **Who uses it** | Staff/clinician. |
| **Inputs** | Day’s appointments; definition of “slot” (time slot). |
| **Outputs** | Percentage and ratio of occupied slots vs total. |
| **Business rules** | “Slot” = assignable time slot (e.g. every 30 min or as configured); total slots depend on schedule and boxes. |

**Wireframe:** W4 (sidebar, “Occupancy”).

**Acceptance criteria**

- An “Occupancy” indicator shows a progress bar and text (e.g. “19/24 SLOTS OCCUPIED TODAY”). The percentage and ratio match the selected day: occupied slots vs total assignable slots for that day (based on schedule and boxes). The total number of slots is derived from the configured slot duration (e.g. 30 minutes) and opening hours. Updating the date or appointments and refreshing updates the indicator.

---

### 4.5 Disinfection buffer notice

| Aspect | Description |
|--------|-------------|
| **What it does** | Fixed message: “5 minutes are reserved between patients for box cleaning and disinfection.” |
| **Who uses it** | All (informational). |
| **Inputs** | None. |
| **Outputs** | User awareness of the rule. |
| **Business rules** | Between two consecutive appointments in the same box there must be a 5-minute buffer (not assignable to a patient); the agenda must enforce it when creating or moving appointments. |

**Wireframe:** W4 (top content banner).

**Acceptance criteria**

- A fixed message is displayed: “5 minutes are reserved between patients for box cleaning and disinfection.” (or equivalent). The message is always visible when the agenda is shown. When creating or moving an appointment, the system does not allow placing two appointments in the same box with less than 5 minutes between end of one and start of the next; validation or UI prevents this.

---

### 4.6 View selector (Day / By Box / Week) and date

| Aspect | Description |
|--------|-------------|
| **What it does** | Title with date (e.g. “Thursday, 5 October”) and view selector: “Day”, “By Box” (active), “Week”. |
| **Who uses it** | User consulting the agenda. |
| **Inputs** | Click on Day / By Box / Week. |
| **Outputs** | Change of representation: by day (possibly list), by box (one column per box), by week (weekly view). |
| **Business rules** | “By Box” shows one column per box with time slots; the displayed date is the one selected in the mini calendar. |

**Wireframe:** W4 (below banner, view selector).

**Acceptance criteria**

- The selected date is shown in the title (e.g. “Thursday, 5 October”). A view selector has options: “Day”, “By Box” (active by default on this screen), “Week”. Clicking “By Box” shows one column per box with time slots. Clicking “Day” shows the day view (e.g. single list). Clicking “Week” shows the weekly view. The displayed date is the one selected in the mini calendar and does not change unless the user selects another day.

---

### 4.7 “New Appointment” button

| Aspect | Description |
|--------|-------------|
| **What it does** | Primary “New Appointment” button that opens the appointment creation flow. |
| **Who uses it** | User with permission to manage appointments. |
| **Inputs** | Click on “New Appointment”. |
| **Outputs** | Opening of the appointment detail panel or form (empty). |
| **Business rules** | Same as W1: only authorised users may create appointments. |

**Wireframe:** W4 (“New Appointment” button).

**Acceptance criteria**

- A “New Appointment” (or equivalent) button is visible. Clicking it opens the appointment detail panel or form in create mode (empty fields). Only users with permission to manage appointments see the button or can complete creation; otherwise the button is hidden/disabled or the submit returns 403.

---

### 4.8 Agenda grid by box (columns and bands)

| Aspect | Description |
|--------|-------------|
| **What it does** | Fixed time column (08:00–17:00, hourly); one column per box (e.g. BOX 1 “ORTHODONTICS ROOM”, BOX 2 “SURGERY ROOM”). Each appointment is shown as a block positioned by time and duration; 5-minute “buffer” bands between appointments (striped). Red “current time” line (e.g. 12:15). |
| **Who uses it** | User viewing or editing the agenda. |
| **Inputs** | Selected date; appointments and boxes; schedule and slot duration configuration. |
| **Outputs** | Appointments displayed by box and time; free slots; visible buffers. |
| **Business rules** | Two appointments cannot overlap in the same box; the 5-minute buffer between appointments is mandatory; the “current time” line may update in real time (optional). |

**Wireframe:** W4 (main grid).

**Acceptance criteria**

- The grid has a fixed time column (e.g. 08:00–17:00, hourly) and one column per selected box (e.g. BOX 1 “ORTHODONTICS ROOM”, BOX 2 “SURGERY ROOM”). Each appointment is a block positioned by start time and duration; no two blocks overlap in the same column. Between consecutive appointments in the same box a 5-minute buffer band is shown (e.g. striped). A “current time” line (e.g. red) is shown at the correct horizontal position when viewing today; it may update in real time. Free slots are visible as empty space.

---

### 4.9 Appointment block on the grid (content and actions)

| Aspect | Description |
|--------|-------------|
| **What it does** | Each appointment shows: patient name, doctor, time range, and on hover: edit and cancel buttons. It may show “CONFIRMED” label and “Pathology: …” (e.g. Molar sensitivity #14). “more_vert” button for more options. |
| **Who uses it** | User managing appointments. |
| **Inputs** | Click on appointment (open detail); click on edit/cancel/more options. |
| **Outputs** | Opening of appointment detail panel; execution of edit/cancel. |
| **Business rules** | Clicking an appointment opens the lateral detail panel with that appointment’s data; cancel may require confirmation. |

**Wireframe:** W4 (appointment blocks on the grid).

**Acceptance criteria**

- Each appointment block shows patient name, doctor, and time range. On hover (or click), edit and cancel controls appear. A “CONFIRMED” (or equivalent) label is shown when status is confirmed. “Pathology: …” (or reason) is shown when present. A “more_vert” (or equivalent) button opens additional actions. Clicking the block opens the appointment detail panel with that appointment’s data loaded. Edit and cancel behave as designed; cancel may require confirmation.

---

### 4.10 Appointment detail panel (form)

| Aspect | Description |
|--------|-------------|
| **What it does** | Lateral “Appointment Detail” panel with: Patient (selector “Select existing patient…” or “NEW PATIENT”), Appointment date, Start time, Duration (30/60/90/120 min, optional when procedure type is set), Doctor (selector), Box (selector), Procedure type / Pathology type (PathologyType selector, e.g. “First visit”, “Follow-up”), Treatment (selector, e.g. “Initial treatment”, “Root canal”), Pathology/Reason (selector or free text), Clinical notes / observations (textarea). When a PathologyType and Treatment are selected and duration is left empty, the system sets duration from the PathologyType default (see §6.1). Notice “5 min added for BOX disinfection”. “Cancel” and “Save Appointment” buttons. |
| **Who uses it** | User creating or editing an appointment. |
| **Inputs** | Value for each field; patient selection (existing or new); optional procedure type and treatment for default duration. |
| **Outputs** | Appointment created or updated; overlap and buffer validation; duration filled from pathology type default when applicable (see §6.1). |
| **Business rules** | Patient required; date, start time, doctor and box required; duration required or defaulted from PathologyType when procedure type (and optionally treatment) is selected; no overlap in the same box; 5-minute buffer automatically added at the end. |

**Wireframe:** W4 (right panel “Appointment Detail”).

**Acceptance criteria**

- The panel includes: Patient (selector “Select existing patient…” or “NEW PATIENT”), Appointment date, Start time, Duration (e.g. 30/60/90/120 min), Doctor (selector), Box (selector), Procedure type (PathologyType, e.g. “First visit”), Treatment (e.g. “Initial treatment”), Pathology/Reason (selector), Clinical notes / observations (textarea). A notice states that 5 minutes are added for box disinfection. “Cancel” and “Save Appointment” buttons are present. When the user selects a procedure type and treatment and leaves duration empty, the system sets duration from the pathology type’s default (§6.1). All required fields are validated; overlapping or buffer violation shows a clear error. On success, the appointment is created or updated and the grid refreshes. “NEW PATIENT” opens the new patient flow and, on success, the new patient can be selected for the appointment.

---

### 4.11 Close detail panel

| Aspect | Description |
|--------|-------------|
| **What it does** | “Close” button on the detail panel to close the panel without saving (or after saving). |
| **Who uses it** | User who opened the detail. |
| **Inputs** | Click on close. |
| **Outputs** | Panel hidden; unsaved changes may be lost (with or without confirmation as per design). |
| **Business rules** | If there are unsaved changes, confirmation may be requested before closing. |

**Wireframe:** W4 (“Appointment Detail” panel header).

**Acceptance criteria**

- A close (X) button is visible on the appointment detail panel. Clicking it closes the panel. If there are unsaved changes, the system either prompts for confirmation (“Discard changes?”) or discards without prompt, as per design. After closing, the panel is hidden and the grid remains visible. If the user had just saved, closing does not show a discard prompt.

---

## 5. Feature ↔ Wireframe map (summary by wireframe)

This table maps each wireframe to its features (short names). For the reverse mapping (feature ID → wireframe), see the *List of features* table at the start of the document.

| Wireframe | Short name | Listed features |
|-----------|------------|------------------|
| **W1** | Dashboard | Navigation, greeting, global search, notifications, new patient, KPIs (patients today, pending results, low stock), today’s agenda, per-appointment actions, assign slot in empty slot, allergy alerts, stock status by box, restock request |
| **W2** | Patient record | Header and search, summary profile, internal navigation, incomplete registration / first visit notice, save and export PDF, critical allergies (CRUD), contact data (editing), diseases and conditions, visit history and odontogram per visit, file and X-ray management, legal footer |
| **W3** | Odontogram | Header and search, patient context and print/save, FDI/Universal dentition, pathology protocol (tooth/surface status), change history, quick actions (Notes, X-ray, Files, Appointments), AI analysis |
| **W4** | Agenda by box | Navigation and Agenda view, mini calendar, filter by boxes, day occupancy, 5-min buffer notice, Day/By Box/Week selector, new appointment, grid by box with bands and buffers, appointment block with actions, appointment detail panel (full form), close panel |

---

## 6. Cross-cutting entities and concepts (inferred from wireframes)

- **User**: roles (clinician, staff, admin); name, photo, role shown in the UI.
- **Patient**: ID, name, age, photo, status (e.g. Active Treatment, Incomplete registration); contact data; allergies; diseases/conditions; visit history; files/X-rays.
- **Appointment**: patient, doctor, box, date, start time; observations (TEXT); duration_minutes (INT); treatment_id (nullable when using PathologyType for default duration); status (Confirmed, In progress, Arrived…); pathology/reason; 5-min post-appointment buffer. When duration is empty and a procedure type is selected, the controller sets duration from PathologyType (see §6.1).
- **Box**: identifier (1, 2…), room name (e.g. Orthodontics, Surgery); time slots.
- **Visit**: logical concept (one appointment = one visit); date; treatment performed; associated odontogram; notes. Implemented as or linked to Appointment in the backend.
- **Odontogram**: linked to visit (Appointment)/patient; teeth by quadrant; status per surface (healthy, caries, amalgam, composite, crown, missing); change history.
- **OdontogramaDetail**: per-tooth record within an odontogram; tooth number (FDI/Universal); linked to Odontogram and Pathology; has a collection of **ToothFace** (one per surface).
- **ToothFace**: id; faceName (e.g. occlusal, mesial, distal, buccal, lingual); many-to-one to OdontogramaDetail. Represents a single tooth surface; each OdontogramaDetail can have multiple ToothFace records.
- **PathologyType**: id; name (e.g. “First visit”, “Follow-up”); default_duration (integer, minutes). Used when scheduling to prefill appointment duration.
- **Pathology**: id; description; protocolColor; visualType; many-to-many Treatment; one-to-many OdontogramaDetail. **pathology_type_id** (many-to-one PathologyType, optional): for scheduling and default duration.
- **Treatment**: linked to pathologies and to Appointment; e.g. “Initial treatment”, “Root canal”. Treatment has treatmentName, description, estimatedDuration.
- **Inventory/Stock**: per box or room; level in %; “low” threshold; alerts; restock request.
- **Allergy**: type (Penicillin, Latex, etc.); severity (critical vs. precaution); linked to patient.
- **File/document**: type (X-ray, document); name, date, size; linked to patient; format and max size (e.g. 15MB).

### 6.1 Appointment controller: default duration from PathologyType

When creating a new appointment with a selected **procedure type** (PathologyType) and **treatment** (Treatment):

- **Example:** PathologyType “First visit” with Treatment “Initial treatment”.
- If the **duration** field is empty or not supplied, the controller sets the appointment duration from the selected pathology type: `$appointment->setDurationMinutes($pathologyType->getDefaultDuration())`.
- If the user explicitly provides a duration, that value is used and the default is not applied.

---

## 7. API surface

This section defines the REST API endpoints required to support the functional specification. Base URL is the application root (e.g. `http://localhost:8000`). All API responses use JSON unless stated otherwise. Authentication is session-based (or token-based if implemented); authorization is stated per endpoint.

---

### 7.1 Users

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/users` | List all users |
| GET | `/api/users/{id}` | Get one user by id |
| POST | `/api/users` | Create user |
| PUT | `/api/users/{id}` | Update user (full) |
| PATCH | `/api/users/{id}` | Update user (partial, same as PUT) |
| DELETE | `/api/users/{id}` | Delete user |

**Input parameters**

- **Path:** `id` (integer, required for show/update/delete).

**Request body (POST / PUT / PATCH)**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Valid email, unique. Max 180 chars. |
| plainPassword | string | yes on create, no on update | Min 6 chars. Stored hashed. |
| roles | array of string | no | Allowed: `ROLE_USER`, `ROLE_ADMIN`, `ROLE_DOCTOR`, `ROLE_STAFF`. Default `ROLE_USER`. |

**Example request (POST)**

```json
{
  "email": "doctor@example.com",
  "plainPassword": "securePassword123",
  "roles": ["ROLE_USER", "ROLE_DOCTOR"]
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list (GET collection) or single user (GET/PUT/PATCH). |
| 201 | Created — user created (POST). |
| 204 | No Content — user deleted (DELETE). |
| 400 | Bad Request — malformed JSON or invalid request. |
| 401 | Unauthorized — not authenticated. |
| 403 | Forbidden — authenticated but missing required role. |
| 404 | Not Found — user with given `id` not found. |
| 422 | Unprocessable Entity — validation failed. |
| 500 | Internal Server Error — server error. |

**Example response (200, single user)**

```json
{
  "id": 1,
  "email": "doctor@example.com",
  "roles": ["ROLE_USER", "ROLE_DOCTOR"]
}
```

**Example validation error (422)**

```json
{
  "error": "Validation failed",
  "errors": [
    { "field": "email", "message": "This value is not a valid email address." },
    { "field": "plainPassword", "message": "Please enter a password." }
  ]
}
```

**Validation rules**

- `email`: not blank, valid email format, max length 180, unique in database.
- `plainPassword`: on create — not blank, min 6, max 4096; on update — optional, same length rules if present.
- `roles`: each element must be one of `ROLE_USER`, `ROLE_ADMIN`, `ROLE_DOCTOR`, `ROLE_STAFF`; at least one role typically present.

**Authorization**

- All user endpoints: **ROLE_ADMIN**. Unauthenticated or non-admin receive 401 or 403.

---

### 7.2 Patients

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/patients` | List patients (optional query: search, status) |
| GET | `/api/patients/{id}` | Get one patient by id |
| GET | `/api/patients/by-identity/{identityDocument}` | Get patient by identity document (e.g. NIF) |
| POST | `/api/patients` | Create patient |
| PUT | `/api/patients/{id}` | Update patient |
| DELETE | `/api/patients/{id}` | Delete patient |

**Input parameters**

- **Path:** `id` (integer), `identityDocument` (string, for by-identity).
- **Query (GET list):** `search` (string, optional), `status` (string, optional, e.g. active_treatment, incomplete).

**Request body (POST / PUT)**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | yes | Full name. Max length as per entity. |
| email | string | no | Valid email. |
| phone | string | no | Phone number. |
| address | string | no | Full address. |
| identityDocument | string | no | National ID / NIF. Unique if present. |
| dateOfBirth | string | no | ISO 8601 date (YYYY-MM-DD). |
| medicationAllergies | string | no | Free text or structured; critical allergies. |
| medicalConditions | array or string | no | Diseases/conditions; format TBD. |
| status | string | no | e.g. `active_treatment`, `incomplete_registration`. |

**Example request (POST)**

```json
{
  "name": "Sarah Jenkins",
  "email": "s.jenkins@email.com",
  "phone": "+34 612 345 678",
  "address": "Calle Gran Vía 12, 4A, 28013 Madrid",
  "identityDocument": "12345678A",
  "dateOfBirth": "1999-05-15",
  "medicationAllergies": "Penicillin, Latex"
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list or single patient. |
| 201 | Created — patient created. |
| 204 | No Content — patient deleted. |
| 400 | Bad Request — invalid input. |
| 401 | Unauthorized. |
| 403 | Forbidden. |
| 404 | Not Found — patient not found. |
| 422 | Unprocessable Entity — validation failed. |
| 500 | Internal Server Error. |

**Example response (200, single patient)**

```json
{
  "id": 1,
  "name": "Sarah Jenkins",
  "email": "s.jenkins@email.com",
  "phone": "+34 612 345 678",
  "address": "Calle Gran Vía 12, 4A, 28013 Madrid",
  "identityDocument": "12345678A",
  "dateOfBirth": "1999-05-15",
  "medicationAllergies": "Penicillin, Latex",
  "status": "active_treatment",
  "createdAt": "2026-10-01T10:00:00+00:00"
}
```

**Validation rules**

- `name`: not blank; max length as defined in entity.
- `email`: valid email format if present; unique per patient.
- `identityDocument`: unique if present; format as per business rules.
- `dateOfBirth`: valid date; optional.

**Authorization**

- List, show, by-identity: authenticated user (e.g. ROLE_DOCTOR, ROLE_STAFF, ROLE_ADMIN).
- Create, update, delete: ROLE_STAFF or ROLE_ADMIN (or as per project policy).

---

### 7.3 Appointments

Appointments are backed by the **Appointment** entity (see §6). To populate procedure type and treatment selectors, the API may expose **GET /api/pathology_types** (id, name, default_duration) and **GET /api/treatments** (id, treatmentName, etc.). The project uses a **Doctor** entity for the appointment’s doctor.

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/appointments` | List appointments (query: date, boxId, doctorId, patientId) |
| GET | `/api/appointments/{id}` | Get one appointment |
| POST | `/api/appointments` | Create appointment |
| PUT | `/api/appointments/{id}` | Update appointment |
| DELETE | `/api/appointments/{id}` | Delete appointment |

**Input parameters**

- **Path:** `id` (integer).
- **Query (GET list):** `date` (ISO date), `boxId`, `doctorId`, `patientId` (integers, optional filters).

**Request body (POST / PUT)**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patientId | integer | yes | Patient id. |
| doctorId | integer | yes | Doctor id. |
| boxId | integer | yes | Box id. |
| date | string | yes | ISO 8601 date (YYYY-MM-DD). |
| startTime | string | yes | Time (HH:MM or ISO time). |
| durationMinutes | integer | no* | Duration in minutes. If omitted and pathologyTypeId is set, the server sets it from PathologyType.default_duration (see §6.1). |
| pathologyTypeId | integer | no | PathologyType id; used for default duration when durationMinutes is empty. |
| treatmentId | integer | no | Treatment id; nullable on appointment. |
| reason | string | no | Free-text pathology/reason (e.g. consultationReason). |
| status | string | no | e.g. confirmed, in_progress, arrived, cancelled. |
| clinicalNotes | string | no | Free text (maps to appointment observations). |

*See §6.1 for default-duration behaviour.

**Example request (POST)**

```json
{
  "patientId": 1,
  "doctorId": 2,
  "boxId": 1,
  "date": "2026-10-05",
  "startTime": "12:00",
  "pathologyTypeId": 1,
  "treatmentId": 2,
  "clinicalNotes": "Severe sensitivity to cold/heat on tooth #14. Pulp evaluation required."
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list or single appointment. |
| 201 | Created — appointment created. |
| 204 | No Content — appointment deleted. |
| 400 | Bad Request — e.g. overlap or invalid slot. |
| 401 | Unauthorized. |
| 403 | Forbidden. |
| 404 | Not Found. |
| 422 | Unprocessable Entity — validation or business rule failed. |
| 500 | Internal Server Error. |

**Example response (200, single appointment)**

```json
{
  "id": 1,
  "patient": { "id": 1, "name": "Eleanor Pena" },
  "doctor": { "id": 2, "name": "Dr. Michael Ross" },
  "box": { "id": 2, "name": "Surgery Room" },
  "date": "2026-10-05",
  "startTime": "12:00",
  "durationMinutes": 90,
  "status": "confirmed",
  "reason": "Root canal",
  "clinicalNotes": "Severe sensitivity..."
}
```

**Validation rules**

- `patientId`, `doctorId`, `boxId`: required; must exist.
- `date`: required; valid date.
- `startTime`, `durationMinutes`: required; within opening hours; duration in allowed set (e.g. 30, 60, 90, 120).
- No overlap: two appointments cannot occupy the same box for overlapping times.
- Buffer: 5 minutes between consecutive appointments in the same box (not assignable to a patient).

**Authorization**

- List, show: authenticated user with access to agenda (e.g. ROLE_DOCTOR, ROLE_STAFF, ROLE_ADMIN).
- Create, update, delete: ROLE_STAFF or ROLE_ADMIN (or as per project policy).

---

### 7.4 Boxes

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/boxes` | List all boxes |
| GET | `/api/boxes/{id}` | Get one box |

**Input parameters**

- **Path:** `id` (integer).

**Request body**

- None for GET. (POST/PUT only if box management is required; not in wireframes.)

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list or single box. |
| 404 | Not Found — box not found. |
| 401/403 | Unauthorized / Forbidden if auth required. |

**Example response (200, single box)**

```json
{
  "id": 1,
  "name": "Orthodontics Room",
  "identifier": "BOX 1"
}
```

**Validation rules**

- Read-only in this specification; create/update rules TBD if endpoints are added.

**Authorization**

- Authenticated user (e.g. any role that can see the agenda).

---

### 7.5 Visits (and odontogram link)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/patients/{patientId}/visits` | List visits for a patient |
| GET | `/api/visits/{id}` | Get one visit (with odontogram id if any) |
| POST | `/api/visits` | Create visit (e.g. after appointment) |
| PUT | `/api/visits/{id}` | Update visit |

**Input parameters**

- **Path:** `patientId` (integer), `id` (integer).

**Request body (POST / PUT)**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patientId | integer | yes (POST) | Patient id. |
| appointmentId | integer | no | Link to appointment if any. |
| date | string | yes | ISO date. |
| treatmentSummary | string | no | e.g. "Root canal", "Cleaning and check-up". |
| notes | string | no | Free text. |

**Example response (200, list of visits)**

```json
[
  {
    "id": 1,
    "patientId": 1,
    "date": "2026-10-24",
    "treatmentSummary": "Root canal (Endodontics)",
    "notes": "Tooth #14. Procedure successful.",
    "odontogramId": 5
  }
]
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK. |
| 201 | Created. |
| 404 | Not Found. |
| 422 | Validation failed. |

**Validation rules**

- `patientId` must exist. `date` required and valid.

**Authorization**

- Authenticated clinician or staff; scope per patient as per policy.

---

### 7.6 Odontograms

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/visits/{visitId}/odontogram` | Get odontogram for a visit (or 404 if none) |
| GET | `/api/odontograms/{id}` | Get odontogram by id |
| POST | `/api/visits/{visitId}/odontogram` | Create or replace odontogram for visit |
| PUT | `/api/odontograms/{id}` | Update odontogram (tooth/surface states, history) |

**Input parameters**

- **Path:** `visitId` (integer), `id` (integer).

**Request body (POST / PUT)**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| teeth | array | no | List of tooth/surface states. See example. |
| system | string | no | `FDI` or `Universal`. |

**Example body (PUT — tooth states)**

```json
{
  "system": "FDI",
  "teeth": [
    { "toothId": 16, "surface": "occlusal", "status": "caries" },
    { "toothId": 24, "surface": "occlusal", "status": "crown" },
    { "toothId": 48, "status": "missing" }
  ]
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — odontogram data. |
| 201 | Created — odontogram created for visit. |
| 404 | Not Found — visit or odontogram not found. |
| 422 | Validation failed. |

**Validation rules**

- `toothId`: valid FDI or Universal number; `status`: one of healthy, caries, amalgam, composite, crown, missing; `surface` when applicable (e.g. occlusal, mesial, distal, buccal, lingual).

**Authorization**

- Authenticated clinician or staff; access to the patient/visit.

---

### 7.7 Documents / files (patient)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/patients/{patientId}/documents` | List documents for patient (query: type, fromDate, toDate) |
| GET | `/api/documents/{id}` | Get document metadata |
| GET | `/api/documents/{id}/download` | Download file content (binary or redirect) |
| POST | `/api/patients/{patientId}/documents` | Upload document (multipart/form-data) |
| PUT | `/api/documents/{id}` | Update document metadata |
| DELETE | `/api/documents/{id}` | Delete document |

**Input parameters**

- **Path:** `patientId` (integer), `id` (integer).
- **Query (list):** `type` (xray, document), `fromDate`, `toDate` (optional).

**Request body (POST — multipart)**

- `file` (file): required; max 15MB; allowed types e.g. JPG, PNG, PDF.
- `type` (string): e.g. `xray`, `document`.
- `caption` or `name` (string): optional.

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list or metadata. |
| 201 | Created — document uploaded. |
| 204 | No Content — document deleted. |
| 400 | Bad Request — e.g. file too large or wrong type. |
| 404 | Not Found. |
| 422 | Validation failed. |

**Example response (200, list item)**

```json
{
  "id": 1,
  "patientId": 1,
  "type": "xray",
  "name": "Panoramic_Sarah_2026.jpg",
  "uploadedAt": "2026-10-24T10:00:00+00:00",
  "sizeBytes": 4404019
}
```

**Validation rules**

- File size max 15MB; allowed formats JPG, PNG, PDF (or as configured). `type` required and from allowed set.

**Authorization**

- Access restricted to the patient’s record (ROLE_DOCTOR, ROLE_STAFF, ROLE_ADMIN); data protection applies.

---

### 7.8 Allergies (patient sub-resource)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/patients/{patientId}/allergies` | List allergies for patient |
| POST | `/api/patients/{patientId}/allergies` | Add allergy |
| DELETE | `/api/patients/{patientId}/allergies/{allergyId}` | Remove allergy |

**Request body (POST)**

```json
{
  "type": "Penicillin",
  "severity": "critical"
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list. |
| 201 | Created. |
| 204 | No Content — removed. |
| 404 | Not Found. |
| 422 | Validation failed. |

**Validation rules**

- `type`: not blank; `severity`: e.g. `critical`, `precaution`. Duplicates may be disallowed per business rule.

**Authorization**

- Same as patient record (clinician/staff/admin).

---

### 7.9 Dashboard / KPIs (read-only)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/dashboard/summary` | Patients today, pending results, low stock count (or separate endpoints) |
| GET | `/api/dashboard/today-agenda` | Today’s agenda (list or by box) |
| GET | `/api/dashboard/allergy-alerts` | Patients with appointment today who have allergies |
| GET | `/api/dashboard/stock-status` | Stock level per box/room |

**Input parameters**

- **Query:** `date` (optional, default today) for agenda and alerts.

**Request body**

- None.

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — JSON with counts and/or list. |
| 401/403 | Unauthorized / Forbidden. |

**Example response (200, summary)**

```json
{
  "patientsToday": 12,
  "patientsTodayVariationPercent": 4,
  "pendingResults": 3,
  "lowStockAlerts": 1
}
```

**Authorization**

- Authenticated user (e.g. ROLE_DOCTOR, ROLE_STAFF, ROLE_ADMIN).

---

### 7.10 Inventory / stock (optional)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/inventory/boxes` | Stock level per box (percentage, low-threshold alerts) |
| POST | `/api/inventory/restock-request` | Create restock request (body: boxId, items or free text) |

**Request body (POST)**

```json
{
  "boxId": 2,
  "comment": "Low on composite"
}
```

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK. |
| 201 | Created — restock request registered. |
| 422 | Validation failed. |

**Authorization**

- ROLE_STAFF or ROLE_ADMIN typically.

---

### 7.11 Global search (optional)

| Method | Route | Description |
|--------|--------|--------------|
| GET | `/api/search` | Search patients, records, appointments (query: `q`, optional `type`) |

**Input parameters**

- **Query:** `q` (string, required), `type` (optional: patients, appointments, documents).

**Responses**

| Code | Meaning |
|------|--------|
| 200 | OK — list of matches (unified or per type). |
| 400 | Bad Request — e.g. missing `q`. |

**Authorization**

- Authenticated user; scope may depend on role.

---

*This document is the authoritative functional specification for FalconCare. It was produced from the four wireframes and existing project context. It does not include implementation or code; it serves as the reference for development, QA, and API integration.*