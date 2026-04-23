# Portal Roles And Route Views Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `admin` from portal roles and make doctor/staff navigation routes render distinct views.

**Architecture:** Keep existing Django and React modules. Narrow backend staff permissions to the Staff group, and parameterize existing doctor/staff dashboard components with route modes instead of creating duplicate data-loading code.

**Tech Stack:** Django REST Framework, React, React Router, Vite, Docker Compose.

---

### Task 1: Backend Portal Role Permission

**Files:**
- Modify: `backend/accounts/permissions.py`
- Modify: `backend/appointments/tests/test_role_dashboards.py`

- [ ] Add a failing test that authenticates a superuser and expects `403` from `/api/appointments/staff/`.
- [ ] Update `IsStaffUser` so only `user_role(request.user) == "staff"` passes.
- [ ] Run `docker compose exec backend pytest appointments/tests/test_role_dashboards.py -q`.

### Task 2: Frontend Role Routing

**Files:**
- Modify: `frontend/src/auth/AuthContext.jsx`
- Modify: `frontend/src/components/AppLayout.jsx`
- Modify: `frontend/src/App.jsx`

- [ ] Remove `admin` from role-to-route mapping.
- [ ] Remove `admin` from staff route `allowedRoles`.
- [ ] Keep unsupported-role handling for authenticated users without patient, doctor, or staff access.

### Task 3: Distinct Doctor And Staff Route Views

**Files:**
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/pages/DoctorDashboard.jsx`
- Modify: `frontend/src/pages/StaffDashboard.jsx`

- [ ] Pass `mode="today"` to `/doctor` and `mode="schedule"` to `/doctor/schedule`.
- [ ] Pass `mode="operations"` to `/staff` and `mode="appointments"` to `/staff/appointments`.
- [ ] Use each mode to change title, description, stats, and whether summary/filter/table sections are shown.
- [ ] Run `docker compose exec frontend npm run build`.

### Task 4: Commit

**Files:**
- All modified files from Tasks 1-3.

- [ ] Run `git diff --check`.
- [ ] Run `git status --short`.
- [ ] Commit with message `Refine portal roles and route views`.
