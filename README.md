# Medical Appointment MVP

Local development uses a Django 5 backend and a Vite 7 React frontend.

## Requirements

- Python 3.10+ is required because the backend depends on Django 5 (`Django>=5.0,<6.0`).
- Node.js `>=20.19` or `>=22.12` is required by the Vite 7 lockfile.
- PostgreSQL is optional for local development; sqlite is the default.

## Backend Setup

```sh
cd backend
python3.10 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
python manage.py migrate
python manage.py seed_demo
python manage.py runserver 127.0.0.1:8000
```

By default, the backend uses sqlite at `backend/db.sqlite3`. No database environment variables are required for the default dev setup.

To use PostgreSQL instead, set the database variables consumed by `backend/config/settings.py`:

```sh
export DB_ENGINE=django.db.backends.postgresql
export DB_NAME=medical_appointment_mvp
export DB_USER=medical_appointment_user
export DB_PASSWORD=change-me
export DB_HOST=127.0.0.1
export DB_PORT=5432
```

Useful optional Django settings:

```sh
export DJANGO_SECRET_KEY=change-me
export DJANGO_DEBUG=1
export ALLOWED_HOSTS=localhost,127.0.0.1
```

## Frontend Setup

```sh
cd frontend
npm install
npm run dev
```

For a production build:

```sh
cd frontend
npm run build
```

The frontend API client expects the backend API at `http://127.0.0.1:8000/api`.

## Demo Seed Data

Run the demo seed after migrations:

```sh
cd backend
. .venv/bin/activate
python manage.py seed_demo
```

The command creates these demo users:

| Role | Username | Password | Email |
| --- | --- | --- | --- |
| Admin | `admin` | `admin123` | `admin@example.com` |
| Staff | `staff` | `staff123` | `staff@example.com` |
| Patient | `patient` | `patient123` | `patient@example.com` |
| Doctor | `doctor.heart` | `doctor123` | `doctor.heart@example.com` |
| Doctor | `doctor.skin` | `doctor123` | `doctor.skin@example.com` |

The seed also creates specialties, services, clinic locations, doctor-service mappings, and availability slots for the next seven days.

## Local URLs

- Frontend: `http://127.0.0.1:5173`
- Backend API: `http://127.0.0.1:8000/api`
- Django admin: `http://127.0.0.1:8000/admin`

## Manual Smoke Test

Start the backend:

```sh
cd backend
. .venv/bin/activate
python manage.py migrate
python manage.py seed_demo
python manage.py runserver 127.0.0.1:8000
```

In another terminal, start the frontend:

```sh
cd frontend
npm run dev
```

Open `http://127.0.0.1:5173` and verify:

- Patient can register.
- Patient can log in.
- Patient can search by service.
- Patient can search by doctor.
- Patient can book a slot.
- Patient sees appointment in dashboard.
- Patient can cancel appointment.
- Doctor can log in and see assigned appointment.
- Staff can log in and see daily operations dashboard.

## Verification Commands

Backend checks and tests:

```sh
cd backend
. .venv/bin/activate
python manage.py check
pytest -v
```

Frontend build:

```sh
cd frontend
npm run build
```
