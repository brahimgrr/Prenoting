# Doctor Availability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let doctors add their own availability slots from the Agenda page.

**Architecture:** Add a doctor-only scheduling API endpoint that creates `AvailabilitySlot` records for the authenticated doctor's profile. Extend the existing `DoctorDashboard` schedule mode with a simple one-slot form that posts to the new endpoint and shows success/error feedback.

**Tech Stack:** Django REST Framework, React, Vite, Docker Compose.

---

### Task 1: Backend API

**Files:**
- Modify: `backend/scheduling/tests/test_availability_api.py`
- Modify: `backend/scheduling/serializers.py`
- Modify: `backend/scheduling/views.py`
- Modify: `backend/scheduling/urls.py`

- [ ] Add tests for doctor slot creation, patient denial, past time rejection, invalid range rejection, and overlap rejection.
- [ ] Add `DoctorAvailabilityCreateSerializer`.
- [ ] Add `DoctorAvailabilityCreateView`.
- [ ] Wire `doctor/` route under `/api/availability/doctor/`.
- [ ] Run `docker compose exec backend pytest scheduling/tests/test_availability_api.py -q`.

### Task 2: Frontend Agenda Form

**Files:**
- Modify: `frontend/src/pages/DoctorDashboard.jsx`

- [ ] Add form state for date, start time, end time, and clinic ID.
- [ ] Show form only in `mode="schedule"`.
- [ ] POST to `/availability/doctor/`.
- [ ] Show Italian success/error messages.
- [ ] Run `docker compose exec frontend npm run build`.

### Task 3: Verification

**Files:**
- All modified files.

- [ ] Run `docker compose exec backend pytest -q`.
- [ ] Run `docker compose exec frontend npm run build`.
- [ ] Run `git diff --check`.
- [ ] Commit with message `Allow doctors to add availability`.
