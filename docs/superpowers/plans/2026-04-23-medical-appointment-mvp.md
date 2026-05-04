# Medical Appointment MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first working medical appointment system MVP with patient, doctor, staff, and admin roles.

**Architecture:** Use a separated Django REST API and React SPA. Django owns auth, permissions, data integrity, and appointment booking transactions; React owns the modern portal UI for patients, doctors, and staff. PostgreSQL is the target database, with SQLite acceptable only for early local smoke tests before PostgreSQL is configured.

**Tech Stack:** Django, Django REST Framework, PostgreSQL, pytest, React, Vite, Bootstrap, React Router, Axios.

---

## File Structure

Create this project layout:

```text
backend/
  manage.py
  requirements.txt
  pytest.ini
  config/
    __init__.py
    settings.py
    urls.py
    wsgi.py
    asgi.py
  accounts/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
    permissions.py
    serializers.py
    tests/test_auth_api.py
    urls.py
    views.py
  clinics/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
  providers/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
    serializers.py
    tests/test_doctor_api.py
    urls.py
    views.py
  services/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
    serializers.py
    tests/test_service_api.py
    urls.py
    views.py
  scheduling/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
    serializers.py
    tests/test_availability_api.py
    urls.py
    views.py
  appointments/
    __init__.py
    admin.py
    apps.py
    migrations/__init__.py
    models.py
    serializers.py
    tests/test_appointment_api.py
    tests/test_role_dashboards.py
    urls.py
    views.py
  seed/
    __init__.py
    management/
      __init__.py
      commands/
        __init__.py
        seed_demo.py
frontend/
  package.json
  index.html
  src/
    App.jsx
    main.jsx
    api/client.js
    auth/AuthContext.jsx
    components/AppLayout.jsx
    components/StatusBadge.jsx
    components/LoadingState.jsx
    pages/LoginPage.jsx
    pages/RegisterPage.jsx
    pages/PatientDashboard.jsx
    pages/BookingPage.jsx
    pages/MyAppointmentsPage.jsx
    pages/DoctorDashboard.jsx
    pages/StaffDashboard.jsx
    styles.css
```

Do not create portal features outside the MVP scope: payments, reports, document archive, televisits, queue ticketing, and notifications stay out of the first implementation.

## Task 1: Scaffold Backend And Frontend

**Files:**

- Create: `backend/requirements.txt`
- Create: `backend/pytest.ini`
- Create: `backend/manage.py`
- Create: `backend/config/settings.py`
- Create: `backend/config/urls.py`
- Create: `backend/config/wsgi.py`
- Create: `backend/config/asgi.py`
- Create: Django app package files listed in the file structure
- Create: `frontend/package.json`
- Create: `frontend/index.html`
- Create: `frontend/src/main.jsx`
- Create: `frontend/src/App.jsx`
- Create: `frontend/src/styles.css`
- Modify: `.gitignore`

- [ ] **Step 1: Create dependency manifests**

`backend/requirements.txt`:

```text
Django>=5.0,<6.0
djangorestframework>=3.15,<4.0
django-cors-headers>=4.3,<5.0
psycopg[binary]>=3.1,<4.0
pytest>=8.0,<9.0
pytest-django>=4.8,<5.0
```

`frontend/package.json`:

```json
{
  "scripts": {
    "dev": "vite --host 127.0.0.1",
    "build": "vite build",
    "preview": "vite preview --host 127.0.0.1"
  },
  "dependencies": {
    "@vitejs/plugin-react": "^5.0.0",
    "axios": "^1.7.0",
    "bootstrap": "^5.3.0",
    "vite": "^7.0.0",
    "react": "^19.0.0",
    "react-dom": "^19.0.0",
    "react-router-dom": "^7.0.0"
  },
  "devDependencies": {}
}
```

- [ ] **Step 2: Install dependencies**

Run:

```bash
cd backend
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
cd ../frontend
npm install
```

Expected: pip installs Django/DRF/pytest packages and npm creates `frontend/package-lock.json`.

- [ ] **Step 3: Create Django project files**

`backend/config/settings.py` must include:

```python
from pathlib import Path
import os

BASE_DIR = Path(__file__).resolve().parent.parent
SECRET_KEY = os.environ.get("DJANGO_SECRET_KEY", "dev-only-secret-key")
DEBUG = os.environ.get("DJANGO_DEBUG", "1") == "1"
ALLOWED_HOSTS = ["127.0.0.1", "localhost"]

INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "corsheaders",
    "rest_framework",
    "accounts",
    "clinics",
    "providers",
    "services",
    "scheduling",
    "appointments",
    "seed",
]

MIDDLEWARE = [
    "corsheaders.middleware.CorsMiddleware",
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "config.urls"
TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
            ],
        },
    }
]
WSGI_APPLICATION = "config.wsgi.application"

DATABASES = {
    "default": {
        "ENGINE": os.environ.get("DB_ENGINE", "django.db.backends.sqlite3"),
        "NAME": os.environ.get("DB_NAME", BASE_DIR / "db.sqlite3"),
        "USER": os.environ.get("DB_USER", ""),
        "PASSWORD": os.environ.get("DB_PASSWORD", ""),
        "HOST": os.environ.get("DB_HOST", ""),
        "PORT": os.environ.get("DB_PORT", ""),
    }
}

AUTH_PASSWORD_VALIDATORS = []
LANGUAGE_CODE = "en-us"
TIME_ZONE = "Europe/Rome"
USE_I18N = True
USE_TZ = True
STATIC_URL = "static/"
DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

CORS_ALLOWED_ORIGINS = ["http://127.0.0.1:5173", "http://localhost:5173"]
CSRF_TRUSTED_ORIGINS = ["http://127.0.0.1:5173", "http://localhost:5173"]

REST_FRAMEWORK = {
    "DEFAULT_AUTHENTICATION_CLASSES": [
        "rest_framework.authentication.SessionAuthentication",
    ],
    "DEFAULT_PERMISSION_CLASSES": [
        "rest_framework.permissions.IsAuthenticated",
    ],
}
```

`backend/config/urls.py`:

```python
from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("admin/", admin.site.urls),
    path("api/auth/", include("accounts.urls")),
    path("api/doctors/", include("providers.urls")),
    path("api/services/", include("services.urls")),
    path("api/availability/", include("scheduling.urls")),
    path("api/appointments/", include("appointments.urls")),
]
```

- [ ] **Step 4: Create minimal React app**

`frontend/src/main.jsx`:

```jsx
import React from "react";
import { createRoot } from "react-dom/client";
import "bootstrap/dist/css/bootstrap.min.css";
import "./styles.css";
import App from "./App.jsx";

createRoot(document.getElementById("root")).render(<App />);
```

`frontend/src/App.jsx`:

```jsx
export default function App() {
  return (
    <main className="container py-5">
      <h1>Medical Appointment System</h1>
      <p className="text-muted">MVP scaffold is running.</p>
    </main>
  );
}
```

`frontend/index.html`:

```html
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Medical Appointment System</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
```

- [ ] **Step 5: Verify scaffold**

Run:

```bash
cd backend
. .venv/bin/activate
python manage.py check
cd ../frontend
npm run build
```

Expected: Django system check reports no issues; Vite build succeeds.

- [ ] **Step 6: Commit**

```bash
git add backend frontend .gitignore
git commit -m "Scaffold Django and React apps"
```

## Task 2: Implement Domain Models, Admin, And Seed Data

**Files:**

- Create: `backend/accounts/models.py`
- Create: `backend/clinics/models.py`
- Create: `backend/providers/models.py`
- Create: `backend/services/models.py`
- Create: `backend/scheduling/models.py`
- Create: `backend/appointments/models.py`
- Create: matching `admin.py` files
- Create: `backend/seed/management/commands/seed_demo.py`
- Test: `backend/appointments/tests/test_appointment_api.py`

- [ ] **Step 1: Write model tests**

Create `backend/appointments/tests/test_appointment_api.py` with:

```python
from datetime import timedelta
from django.contrib.auth.models import User
from django.utils import timezone
import pytest

from accounts.models import PatientProfile
from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from services.models import DoctorService, MedicalService
from scheduling.models import AvailabilitySlot
from appointments.models import Appointment


@pytest.mark.django_db
def test_appointment_string_and_slot_relation():
    patient_user = User.objects.create_user(username="patient@example.com", password="pass")
    doctor_user = User.objects.create_user(username="doctor@example.com", password="pass")
    patient = PatientProfile.objects.create(user=patient_user, phone="+390000000")
    specialty = Specialty.objects.create(name="Cardiology")
    doctor = DoctorProfile.objects.create(user=doctor_user, display_name="Dr. Rossi", specialty=specialty)
    service = MedicalService.objects.create(name="Cardiology consultation", specialty=specialty, duration_minutes=30)
    DoctorService.objects.create(doctor=doctor, service=service)
    clinic = ClinicLocation.objects.create(name="Main Clinic", address="Via Roma 1")
    start = timezone.now() + timedelta(days=1)
    slot = AvailabilitySlot.objects.create(
        doctor=doctor,
        clinic=clinic,
        start_at=start,
        end_at=start + timedelta(minutes=30),
    )

    appointment = Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=slot.start_at,
        end_at=slot.end_at,
    )

    assert "Cardiology consultation" in str(appointment)
    assert appointment.status == Appointment.Status.CONFIRMED
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd backend
. .venv/bin/activate
pytest appointments/tests/test_appointment_api.py::test_appointment_string_and_slot_relation -v
```

Expected: FAIL because model classes do not exist yet.

- [ ] **Step 3: Implement models**

Use these model fields exactly:

```python
# accounts/models.py
from django.conf import settings
from django.db import models


class PatientProfile(models.Model):
    user = models.OneToOneField(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="patient_profile")
    date_of_birth = models.DateField(null=True, blank=True)
    gender = models.CharField(max_length=32, blank=True)
    phone = models.CharField(max_length=32)
    address = models.CharField(max_length=255, blank=True)
    identity_code = models.CharField(max_length=64, blank=True)

    def __str__(self):
        return self.user.get_full_name() or self.user.username
```

```python
# providers/models.py
from django.conf import settings
from django.db import models


class Specialty(models.Model):
    name = models.CharField(max_length=120, unique=True)
    description = models.TextField(blank=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class DoctorProfile(models.Model):
    user = models.OneToOneField(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="doctor_profile")
    display_name = models.CharField(max_length=160)
    specialty = models.ForeignKey(Specialty, on_delete=models.PROTECT, related_name="doctors")
    bio = models.TextField(blank=True)
    license_number = models.CharField(max_length=80, blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ["display_name"]

    def __str__(self):
        return self.display_name
```

```python
# clinics/models.py
from django.db import models


class ClinicLocation(models.Model):
    name = models.CharField(max_length=160)
    address = models.CharField(max_length=255)
    phone = models.CharField(max_length=32, blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name
```

```python
# services/models.py
from django.db import models
from providers.models import DoctorProfile, Specialty


class MedicalService(models.Model):
    class Category(models.TextChoices):
        VISIT = "visit", "Visit"
        EXAM = "exam", "Exam"

    name = models.CharField(max_length=160)
    category = models.CharField(max_length=16, choices=Category.choices, default=Category.VISIT)
    specialty = models.ForeignKey(Specialty, on_delete=models.PROTECT, related_name="services")
    duration_minutes = models.PositiveIntegerField(default=30)
    price = models.DecimalField(max_digits=8, decimal_places=2, null=True, blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class DoctorService(models.Model):
    doctor = models.ForeignKey(DoctorProfile, on_delete=models.CASCADE, related_name="doctor_services")
    service = models.ForeignKey(MedicalService, on_delete=models.CASCADE, related_name="doctor_services")

    class Meta:
        unique_together = [("doctor", "service")]

    def __str__(self):
        return f"{self.doctor} - {self.service}"
```

```python
# scheduling/models.py
from django.db import models
from clinics.models import ClinicLocation
from providers.models import DoctorProfile


class AvailabilitySlot(models.Model):
    doctor = models.ForeignKey(DoctorProfile, on_delete=models.CASCADE, related_name="availability_slots")
    clinic = models.ForeignKey(ClinicLocation, on_delete=models.PROTECT, related_name="availability_slots")
    start_at = models.DateTimeField()
    end_at = models.DateTimeField()
    is_blocked = models.BooleanField(default=False)
    is_booked = models.BooleanField(default=False)

    class Meta:
        ordering = ["start_at"]
        constraints = [
            models.UniqueConstraint(fields=["doctor", "start_at"], name="unique_doctor_slot_start"),
        ]

    def __str__(self):
        return f"{self.doctor} {self.start_at:%Y-%m-%d %H:%M}"
```

```python
# appointments/models.py
from django.conf import settings
from django.db import models
from accounts.models import PatientProfile
from clinics.models import ClinicLocation
from providers.models import DoctorProfile
from scheduling.models import AvailabilitySlot
from services.models import MedicalService


class Appointment(models.Model):
    class Status(models.TextChoices):
        CONFIRMED = "confirmed", "Confirmed"
        CHECKED_IN = "checked_in", "Checked in"
        COMPLETED = "completed", "Completed"
        CANCELLED = "cancelled", "Cancelled"
        NO_SHOW = "no_show", "No show"

    patient = models.ForeignKey(PatientProfile, on_delete=models.PROTECT, related_name="appointments")
    doctor = models.ForeignKey(DoctorProfile, on_delete=models.PROTECT, related_name="appointments")
    service = models.ForeignKey(MedicalService, on_delete=models.PROTECT, related_name="appointments")
    clinic = models.ForeignKey(ClinicLocation, on_delete=models.PROTECT, related_name="appointments")
    slot = models.OneToOneField(AvailabilitySlot, on_delete=models.PROTECT, related_name="appointment")
    start_at = models.DateTimeField()
    end_at = models.DateTimeField()
    status = models.CharField(max_length=24, choices=Status.choices, default=Status.CONFIRMED)
    notes = models.TextField(blank=True)
    cancellation_reason = models.TextField(blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["-start_at"]

    def __str__(self):
        return f"{self.service} with {self.doctor} for {self.patient}"
```

- [ ] **Step 4: Register models in admin**

Each app `admin.py` should register its models with searchable list displays. For `appointments/admin.py`:

```python
from django.contrib import admin
from .models import Appointment


@admin.register(Appointment)
class AppointmentAdmin(admin.ModelAdmin):
    list_display = ("service", "doctor", "patient", "clinic", "start_at", "status")
    list_filter = ("status", "clinic", "doctor", "service")
    search_fields = ("patient__user__username", "doctor__display_name", "service__name")
```

- [ ] **Step 5: Create migrations and verify tests**

Run:

```bash
cd backend
. .venv/bin/activate
python manage.py makemigrations
python manage.py migrate
pytest appointments/tests/test_appointment_api.py::test_appointment_string_and_slot_relation -v
```

Expected: migrations are created and test passes.

- [ ] **Step 6: Add seed command**

`backend/seed/management/commands/seed_demo.py` should create one admin, staff user, patient user, two doctor users, two clinics, three specialties, four services, doctor-service mappings, and future slots for the next seven days.

Run:

```bash
cd backend
. .venv/bin/activate
python manage.py seed_demo
```

Expected: command prints `Demo data seeded`.

- [ ] **Step 7: Commit**

```bash
git add backend
git commit -m "Add appointment domain models"
```

## Task 3: Authentication, Profiles, And Role Permissions API

**Files:**

- Create: `backend/accounts/permissions.py`
- Create: `backend/accounts/serializers.py`
- Create: `backend/accounts/views.py`
- Create: `backend/accounts/urls.py`
- Test: `backend/accounts/tests/test_auth_api.py`

- [ ] **Step 1: Write auth API tests**

`backend/accounts/tests/test_auth_api.py`:

```python
import pytest
from django.contrib.auth.models import User
from django.urls import reverse
from rest_framework.test import APIClient

from accounts.models import PatientProfile


@pytest.mark.django_db
def test_patient_can_register_and_fetch_current_user():
    client = APIClient()
    response = client.post("/api/auth/register/", {
        "username": "sara@example.com",
        "password": "strong-pass-123",
        "first_name": "Sara",
        "last_name": "Conti",
        "phone": "+390000000",
    }, format="json")

    assert response.status_code == 201
    assert response.data["user"]["role"] == "patient"

    client.login(username="sara@example.com", password="strong-pass-123")
    me = client.get("/api/auth/me/")

    assert me.status_code == 200
    assert me.data["username"] == "sara@example.com"


@pytest.mark.django_db
def test_me_requires_authentication():
    response = APIClient().get("/api/auth/me/")
    assert response.status_code == 403
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd backend
. .venv/bin/activate
pytest accounts/tests/test_auth_api.py -v
```

Expected: FAIL because routes and serializers are missing.

- [ ] **Step 3: Implement role helper and permissions**

`backend/accounts/permissions.py`:

```python
from rest_framework.permissions import BasePermission


def user_role(user):
    if not user or not user.is_authenticated:
        return "anonymous"
    if user.is_superuser:
        return "admin"
    if user.groups.filter(name="Staff").exists():
        return "staff"
    if hasattr(user, "doctor_profile"):
        return "doctor"
    if hasattr(user, "patient_profile"):
        return "patient"
    return "user"


class IsPatient(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) == "patient"


class IsDoctor(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) == "doctor"


class IsStaffUser(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) in {"staff", "admin"}
```

- [ ] **Step 4: Implement serializers and views**

`RegisterSerializer.create()` must create a `User` and `PatientProfile`. `MeView` must return `id`, `username`, `first_name`, `last_name`, and `role`.

Use DRF `APIView` endpoints:

- `POST /api/auth/register/`
- `GET /api/auth/me/`
- `POST /api/auth/login/`
- `POST /api/auth/logout/`

Login uses Django `authenticate()` and `login()`. Logout uses Django `logout()`.

- [ ] **Step 5: Run auth tests**

Run:

```bash
cd backend
. .venv/bin/activate
pytest accounts/tests/test_auth_api.py -v
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/accounts backend/config/urls.py
git commit -m "Add authentication and role APIs"
```

## Task 4: Catalog And Availability APIs

**Files:**

- Create: `backend/providers/serializers.py`
- Create: `backend/providers/views.py`
- Create: `backend/providers/urls.py`
- Create: `backend/services/serializers.py`
- Create: `backend/services/views.py`
- Create: `backend/services/urls.py`
- Create: `backend/scheduling/serializers.py`
- Create: `backend/scheduling/views.py`
- Create: `backend/scheduling/urls.py`
- Test: `backend/services/tests/test_service_api.py`
- Test: `backend/providers/tests/test_doctor_api.py`
- Test: `backend/scheduling/tests/test_availability_api.py`

- [ ] **Step 1: Write list/search tests**

Tests must assert:

- `/api/services/` returns active services.
- `/api/services/?search=cardio` filters by service name.
- `/api/doctors/` returns active doctors.
- `/api/doctors/?specialty=<id>` filters by specialty.
- `/api/availability/?service=<id>` returns only unblocked, unbooked slots for doctors who offer the service.
- `/api/availability/?doctor=<id>` returns only that doctor's slots.

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd backend
. .venv/bin/activate
pytest services/tests/test_service_api.py providers/tests/test_doctor_api.py scheduling/tests/test_availability_api.py -v
```

Expected: FAIL because APIs do not exist.

- [ ] **Step 3: Implement read-only serializers**

Service serializer fields:

```python
["id", "name", "category", "specialty", "specialty_name", "duration_minutes", "price"]
```

Doctor serializer fields:

```python
["id", "display_name", "specialty", "specialty_name", "bio", "license_number"]
```

Availability serializer fields:

```python
["id", "doctor", "doctor_name", "clinic", "clinic_name", "start_at", "end_at"]
```

- [ ] **Step 4: Implement read-only API views**

Use `ListAPIView` for service, doctor, and availability endpoints. Filtering should be explicit in `get_queryset()` using query params: `search`, `specialty`, `clinic`, `doctor`, `service`, `date`.

- [ ] **Step 5: Run catalog tests**

Run:

```bash
cd backend
. .venv/bin/activate
pytest services/tests/test_service_api.py providers/tests/test_doctor_api.py scheduling/tests/test_availability_api.py -v
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/providers backend/services backend/scheduling backend/config/urls.py
git commit -m "Add doctor service and availability APIs"
```

## Task 5: Appointment Booking, Rescheduling, Cancellation, And Status History

**Files:**

- Modify: `backend/appointments/serializers.py`
- Modify: `backend/appointments/views.py`
- Modify: `backend/appointments/urls.py`
- Test: `backend/appointments/tests/test_appointment_api.py`

- [ ] **Step 1: Add appointment API tests**

Extend `test_appointment_api.py` with tests that prove:

- A patient can book an available slot.
- Booking marks the slot as booked.
- A second booking attempt for the same slot returns HTTP 400.
- A patient can list only their own appointments.
- A patient can cancel their own appointment.
- A patient can reschedule to a different available slot.
- Status history is created on cancellation and reschedule.

For the double-booking test, assert:

```python
assert second_response.status_code == 400
assert "slot" in second_response.data
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd backend
. .venv/bin/activate
pytest appointments/tests/test_appointment_api.py -v
```

Expected: FAIL because appointment API is missing.

- [ ] **Step 3: Implement serializers**

Create serializers:

- `AppointmentSerializer`
- `AppointmentCreateSerializer`
- `AppointmentCancelSerializer`
- `AppointmentRescheduleSerializer`

`AppointmentCreateSerializer` input fields:

```python
["slot", "service", "notes"]
```

The serializer must validate that the selected doctor offers the selected service using `DoctorService`.

- [ ] **Step 4: Implement transactional create**

Use `transaction.atomic()` and `AvailabilitySlot.objects.select_for_update().get(id=slot_id)` inside `perform_create`. If `is_booked` or `is_blocked` is true, return a validation error:

```python
{"slot": ["This slot is no longer available."]}
```

Set:

- `patient` from `request.user.patient_profile`
- `doctor` from locked slot
- `clinic` from locked slot
- `start_at` and `end_at` from locked slot
- `status` to `confirmed`
- `slot.is_booked = True`

- [ ] **Step 5: Implement list/detail/cancel/reschedule**

Routes:

- `GET /api/appointments/`
- `POST /api/appointments/`
- `GET /api/appointments/<id>/`
- `POST /api/appointments/<id>/cancel/`
- `POST /api/appointments/<id>/reschedule/`

Patients can only access appointments where `appointment.patient.user == request.user`.

- [ ] **Step 6: Run appointment tests**

Run:

```bash
cd backend
. .venv/bin/activate
pytest appointments/tests/test_appointment_api.py -v
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/appointments backend/scheduling
git commit -m "Add appointment booking workflow"
```

## Task 6: Doctor And Staff Dashboard APIs

**Files:**

- Modify: `backend/appointments/views.py`
- Modify: `backend/appointments/urls.py`
- Test: `backend/appointments/tests/test_role_dashboards.py`

- [ ] **Step 1: Write role dashboard tests**

Create tests that assert:

- Doctor endpoint returns only appointments assigned to the logged-in doctor.
- Doctor can update own appointment status to `checked_in`, `completed`, or `no_show`.
- Staff endpoint returns appointments across all doctors.
- Staff can filter by date, clinic, doctor, service, and status.
- Staff can update any appointment status.
- Patient cannot access doctor or staff endpoints.

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd backend
. .venv/bin/activate
pytest appointments/tests/test_role_dashboards.py -v
```

Expected: FAIL because doctor/staff dashboard routes are missing.

- [ ] **Step 3: Implement dashboard routes**

Routes:

- `GET /api/appointments/doctor/schedule/`
- `POST /api/appointments/doctor/<id>/status/`
- `GET /api/appointments/staff/`
- `POST /api/appointments/staff/<id>/status/`

Use `IsDoctor` for doctor routes and `IsStaffUser` for staff routes.

- [ ] **Step 4: Run role dashboard tests**

Run:

```bash
cd backend
. .venv/bin/activate
pytest appointments/tests/test_role_dashboards.py -v
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/appointments backend/accounts
git commit -m "Add doctor and staff appointment APIs"
```

## Task 7: React App Shell, Auth, And Routing

**Files:**

- Create: `frontend/src/api/client.js`
- Create: `frontend/src/auth/AuthContext.jsx`
- Create: `frontend/src/components/AppLayout.jsx`
- Create: `frontend/src/components/StatusBadge.jsx`
- Create: `frontend/src/components/LoadingState.jsx`
- Create: `frontend/src/pages/LoginPage.jsx`
- Create: `frontend/src/pages/RegisterPage.jsx`
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/styles.css`

- [ ] **Step 1: Add API client**

`frontend/src/api/client.js`:

```jsx
import axios from "axios";

export const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
  withCredentials: true,
});
```

- [ ] **Step 2: Add auth context**

`AuthContext.jsx` must expose:

```jsx
{
  user,
  loading,
  login(username, password),
  logout(),
  register(payload),
  refreshUser()
}
```

After login/register, call `/auth/me/` and route by `user.role`:

- patient -> `/patient`
- doctor -> `/doctor`
- staff/admin -> `/staff`

- [ ] **Step 3: Add portal layout**

`AppLayout.jsx` renders a dark left sidebar on desktop, top bar on mobile, content area, and role-specific navigation links.

Patient nav:

- Dashboard
- Book appointment
- My appointments
- Profile

Doctor nav:

- Today
- Schedule

Staff nav:

- Daily operations
- Appointments

- [ ] **Step 4: Add login/register pages**

Login fields:

- username/email
- password

Register fields:

- first name
- last name
- email username
- password
- phone

Both pages show Bootstrap field errors from API responses.

- [ ] **Step 5: Build frontend**

Run:

```bash
cd frontend
npm run build
```

Expected: Vite build succeeds.

- [ ] **Step 6: Commit**

```bash
git add frontend
git commit -m "Add React auth shell"
```

## Task 8: Patient Dashboard, Booking UI, And Appointment Management

**Files:**

- Create: `frontend/src/pages/PatientDashboard.jsx`
- Create: `frontend/src/pages/BookingPage.jsx`
- Create: `frontend/src/pages/MyAppointmentsPage.jsx`
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/styles.css`

- [ ] **Step 1: Implement patient dashboard**

Dashboard must show:

- Upcoming appointment count.
- Next appointment card.
- Quick actions: `Book by service`, `Book by doctor`, `My appointments`.
- A compact list of upcoming appointments.

- [ ] **Step 2: Implement booking page**

The booking page must include:

- Bootstrap tabs for `Service` and `Doctor`.
- Search box.
- Clinic filter.
- Result list.
- Date/slot buttons.
- Confirmation summary.

Create appointment by calling:

```jsx
await api.post("/appointments/", {
  slot: selectedSlot.id,
  service: selectedService.id,
  notes
});
```

If API returns slot error, show:

```text
This slot is no longer available. Please choose another time.
```

- [ ] **Step 3: Implement my appointments**

List appointments grouped into upcoming and past/cancelled. Each upcoming appointment has:

- Details
- Reschedule
- Cancel

Cancel uses a Bootstrap modal and posts:

```jsx
await api.post(`/appointments/${appointment.id}/cancel/`, {
  cancellation_reason: reason
});
```

- [ ] **Step 4: Build frontend**

Run:

```bash
cd frontend
npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend
git commit -m "Add patient booking portal"
```

## Task 9: Doctor And Staff Dashboard UI

**Files:**

- Create: `frontend/src/pages/DoctorDashboard.jsx`
- Create: `frontend/src/pages/StaffDashboard.jsx`
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/styles.css`

- [ ] **Step 1: Implement doctor dashboard**

Doctor dashboard shows:

- Today's appointment count.
- Appointment list sorted by start time.
- Patient name, service, clinic, time, status.
- Status action buttons: `Checked in`, `Completed`, `No show`.

Status update call:

```jsx
await api.post(`/appointments/doctor/${appointment.id}/status/`, {
  status: nextStatus
});
```

- [ ] **Step 2: Implement staff dashboard**

Staff dashboard shows:

- Daily counts: total, checked in, cancelled.
- Filters: date, clinic, doctor, service, status.
- Dense appointment table.
- Appointment status controls.

Status update call:

```jsx
await api.post(`/appointments/staff/${appointment.id}/status/`, {
  status: nextStatus
});
```

- [ ] **Step 3: Build frontend**

Run:

```bash
cd frontend
npm run build
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add frontend
git commit -m "Add doctor and staff dashboards"
```

## Task 10: End-To-End Verification And Polish

**Files:**

- Modify: `README.md`
- Modify: `.gitignore` if runtime files appear
- Modify: frontend/backend files only for bugs found during verification

- [ ] **Step 1: Add README**

`README.md` must include:

- Backend setup commands.
- Frontend setup commands.
- PostgreSQL environment variables.
- Demo seed command.
- Demo users created by seed command.
- Local URLs.

- [ ] **Step 2: Run backend verification**

Run:

```bash
cd backend
. .venv/bin/activate
python manage.py check
pytest -v
```

Expected: all tests pass.

- [ ] **Step 3: Run frontend verification**

Run:

```bash
cd frontend
npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Run manual smoke test**

Run:

```bash
cd backend
. .venv/bin/activate
python manage.py migrate
python manage.py seed_demo
python manage.py runserver 127.0.0.1:8000
```

In another terminal:

```bash
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

- [ ] **Step 5: Commit**

```bash
git add README.md backend frontend .gitignore
git commit -m "Document and verify MVP setup"
```

## Self-Review

Spec coverage:

- Patient accounts: covered by Tasks 3, 7, and 8.
- Doctor role: covered by Tasks 2, 3, 6, 7, and 9.
- Staff dashboard: covered by Tasks 6, 7, and 9.
- Admin setup: covered by Task 2.
- Service-first and doctor-first booking: covered by Tasks 4, 5, and 8.
- Transactional booking: covered by Task 5.
- Modern Portal UI: covered by Tasks 7, 8, and 9.
- Error handling: covered by Tasks 3, 5, 7, and 8.
- Testing and seed data: covered by Tasks 2 through 10.

Placeholder scan result:

- No deferred MVP scope remains in the task list.
- Future-phase features are intentionally excluded from implementation tasks.

Type consistency:

- Appointment statuses match the approved design: `confirmed`, `checked_in`, `completed`, `cancelled`, `no_show`.
- API paths match the design, with doctor and staff dashboard actions placed under `/api/appointments/` for a smaller route surface.
