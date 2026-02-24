# FalconCare API Documentation

Base URL (development): `http://localhost:8000` (or your configured `DEFAULT_URI`).

**Issue #5 (Backend — CRUD Users) checklist:**  
✅ Users table/migration (`migrations/Version20260223122413.php`) · ✅ REST create, read, update, delete (`/api/users`) · ✅ Input validation & error handling (entity + form, 422) · ✅ Unit tests (`tests/Controller/Api/UserControllerTest.php`) · ✅ Documentation (this file).

---

## Authentication

All **Users API** endpoints require an authenticated user with **ROLE_ADMIN**. Use the application login (e.g. form login / session). For initial setup, create an admin user via registration or console (e.g. `php bin/console security:hash-password` and manual insert, or a custom command).

---

## Users API (CRUD)

User management for doctors, admins, and staff. The `user` table is created via Doctrine migrations (see `migrations/Version20260223122413.php`).

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/users` | List all users | ROLE_ADMIN |
| GET | `/api/users/{id}` | Get one user | ROLE_ADMIN |
| POST | `/api/users` | Create user | ROLE_ADMIN |
| PUT | `/api/users/{id}` | Update user | ROLE_ADMIN |
| PATCH | `/api/users/{id}` | Update user (same as PUT) | ROLE_ADMIN |
| DELETE | `/api/users/{id}` | Delete user | ROLE_ADMIN |

### List users

```http
GET /api/users
```

**Response:** `200 OK`  
**Body:** JSON array of user objects. Each object has `id`, `email`, `roles`. The `password` field is never returned.

---

### Get one user

```http
GET /api/users/{id}
```

**Parameters:** `id` (integer, path)  
**Response:** `200 OK` with a single user object, or `404 Not Found` if the user does not exist.

---

### Create user

```http
POST /api/users
Content-Type: application/json
```

**Body:**

```json
{
  "email": "doctor@example.com",
  "plainPassword": "securePassword123",
  "roles": ["ROLE_USER", "ROLE_DOCTOR"]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Valid email, unique. Max 180 characters. |
| plainPassword | string | yes (on create) | Min 6 characters. Stored hashed. |
| roles | array | no | Allowed: `ROLE_USER`, `ROLE_ADMIN`, `ROLE_DOCTOR`, `ROLE_STAFF`. Default: user gets `ROLE_USER`. |

**Response:** `201 Created` with the created user (without `password`), or `422 Unprocessable Entity` with validation errors.

**Validation error response (422):**

```json
{
  "error": "Validation failed",
  "errors": [
    { "field": "email", "message": "This value is not a valid email address." },
    { "field": "plainPassword", "message": "Please enter a password." }
  ]
}
```

---

### Update user

```http
PUT /api/users/{id}
Content-Type: application/json
```

**Parameters:** `id` (integer, path)  
**Body:** Same as create; `plainPassword` is optional. If present, the password is updated (hashed). Omit to leave password unchanged.

```json
{
  "email": "updated@example.com",
  "roles": ["ROLE_USER", "ROLE_ADMIN"]
}
```

**Response:** `200 OK` with the updated user, `404 Not Found`, or `422 Unprocessable Entity` for validation errors.

---

### Delete user

```http
DELETE /api/users/{id}
```

**Parameters:** `id` (integer, path)  
**Response:** `204 No Content` on success, or `404 Not Found` if the user does not exist.

---

## Error handling

| Status | Meaning |
|--------|--------|
| 401 Unauthorized | Not authenticated. |
| 403 Forbidden | Authenticated but missing ROLE_ADMIN. |
| 404 Not Found | User with the given `id` not found. |
| 422 Unprocessable Entity | Validation failed. Body includes `error` and `errors` (list of `field` and `message`). |

---

## Database schema (users)

The `user` table is created by the Doctrine migration. Columns: `id` (identity), `email` (VARCHAR 180, unique), `roles` (JSON), `password` (VARCHAR 255, hashed). Run:

```bash
php bin/console doctrine:migrations:migrate
```

if the table is not yet present.

---

## Running API tests

Unit tests for the Users API live in `tests/Controller/Api/UserControllerTest.php`. They require a test database (e.g. set `DATABASE_URL` in `.env.test.local`; the project uses the same DB as dev in test via `config/packages/test/doctrine.yaml`). Run:

```bash
./vendor/bin/phpunit tests/Controller/Api/UserControllerTest.php
```
