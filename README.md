# FalconCare

> Dental clinic management system built with **Symfony 7** and **PostgreSQL** (compatible with [Neon](https://neon.tech)). Manages patients, doctors, appointments, treatments, odontograms, and clinical documentation, with authentication and registration.

**Application code:** `FalconCare/` folder. All installation and run commands must be executed from `FalconCare/`.

---

## Prerequisites

- **PHP** >= 8.2
- **Composer** 2.x
- **PostgreSQL** 16+ (or an account on [Neon](https://neon.tech))
- **PHP extensions:** `ctype`, `iconv`, `pdo_pgsql`, `pgsql`, `json`, `mbstring`, `openssl`, `xml`, `zip`

### PHP configuration (XAMPP or other)

In `php.ini`, uncomment or add:

```ini
extension=pdo_pgsql
extension=pgsql
```

> Restart the web server or PHP after changing `php.ini`.

---

## Installation (recommended order)

### 1. Clone the repository

```bash
git clone <repository-url>
cd FalconCareSymfony
```

### 2. Enter the Symfony project and install dependencies

```bash
cd FalconCare
composer install
```

This installs dependencies and automatically runs: cache clear, assets install, and importmap. You do not need to run them manually.

### 3. Configure the database

Create the `.env.local` file inside `FalconCare/` (it is not committed to Git) and set the PostgreSQL connection URL:

```env
DATABASE_URL="postgresql://user:password@host:5432/dbname?serverVersion=16&charset=utf8"
```

- **Local PostgreSQL:** replace `user`, `password`, `host` (e.g. `127.0.0.1`), `dbname`, and if you use another version, `serverVersion`.
- **Neon:** copy the connection string from the Neon dashboard (Connection string) and paste it into `DATABASE_URL`. Adjust `serverVersion` if required by Neon documentation.

> **Important:** do not commit `.env.local` or credentials to the repository. `.env.local` is already in `.gitignore`.

### 4. Verify the database connection

From the `FalconCare/` folder:

```bash
php bin/console doctrine:query:sql "SELECT current_database();"
```

If it returns your database name, the connection is correct.

### 5. Run migrations

From `FalconCare/`:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

With `--no-interaction`, all pending migrations are applied without prompting. In development you can omit the flag and confirm when prompted.

If you later change entities and need to generate a new migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### 6. (Optional) Configure APP_SECRET

If `APP_SECRET` is empty in `.env`, set a value in `.env.local` (e.g. a long random string). The application may run without it in development, but it is recommended for sessions and cookies.

### 7. Start the development server

From `FalconCare/`:

```bash
symfony server:start
```

Or with the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

Open in your browser the URL shown by the command (e.g. `http://localhost:8000` or `http://127.0.0.1:8000`).

---

## Quick summary (with repo already cloned)

```bash
cd FalconCare
composer install
# Create .env.local with correct DATABASE_URL
php bin/console doctrine:query:sql "SELECT current_database();"
php bin/console doctrine:migrations:migrate --no-interaction
symfony server:start
```

---

## Project structure (in `FalconCare/`)

| Area            | Entity       | Brief description |
| :-------------- | :----------- | :---------------- |
| Access          | `User`       | Email, hashed password |
| Staff           | `Doctor`     | Specialty, assigned days |
| Patients        | `Patient`    | Record, document, SS, contact, medical history |
| Clinic          | `Box`        | Cabinets, capacity, availability |
| Catalogs        | `Treatment`, `Pathology`, `Tooth` | Treatments, pathologies, tooth units |
| Schedule        | `Appointment` | Appointments (Patient, Doctor, Box, Treatment) |
| Odontogram      | `Odontogram` | Per tooth surface (Appointment, Tooth, Pathology) |
| Documents       | `Document`   | History files (Patient) |

**Main folders:** `src/Entity/`, `src/Repository/`, `src/Controller/`, `src/Form/`, `src/Security/`, `config/`.

---

## Security and API

- Passwords are handled by Symfony’s **Security** component.
- `symfony/serializer` is installed to expose entities as JSON (e.g. for an Angular frontend).

---

## Tests and useful commands

**Run tests** (from `FalconCare/`):

```bash
composer test
# or
./vendor/bin/phpunit
```

**Useful commands** (always from `FalconCare/`):

| Command | Description |
| :------ | :---------- |
| `php bin/console doctrine:migrations:migrate` | Apply migrations |
| `php bin/console make:migration` | Generate migration after changing entities |
| `php bin/console cache:clear` | Clear cache |
| `php bin/console list` | List available commands |

---

## License

Proprietary project. See `FalconCare/composer.json`.
