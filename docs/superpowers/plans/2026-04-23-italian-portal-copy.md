# Italian Portal Copy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Translate the visible appointment portal UI from English to Italian with direct string replacement.

**Architecture:** Keep the current React and Django structure. Update hard-coded visible text in frontend components and user-facing backend validation messages without introducing an i18n dependency.

**Tech Stack:** React, Vite, Django REST Framework, Docker Compose.

---

### Task 1: Frontend Copy

**Files:**
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/components/AppLayout.jsx`
- Modify: `frontend/src/components/LoadingState.jsx`
- Modify: `frontend/src/components/StatusBadge.jsx`
- Modify: `frontend/src/pages/*.jsx`
- Modify: `frontend/index.html`

- [ ] Replace labels, headings, helper text, button text, empty states, status labels, and validation fallback messages with Italian copy.
- [ ] Preserve route paths, API names, state values, and component names.

### Task 2: Backend User-Facing Messages

**Files:**
- Modify: `backend/accounts/serializers.py`
- Modify: `backend/appointments/serializers.py`
- Modify: `backend/appointments/views.py`
- Modify: `backend/providers/views.py`
- Modify: `backend/scheduling/views.py`

- [ ] Translate validation and API error messages that are surfaced directly in the frontend.
- [ ] Preserve test names and technical identifiers.

### Task 3: Verification

**Files:**
- All modified files.

- [ ] Run `docker compose exec frontend npm run build`.
- [ ] Run `docker compose exec backend pytest -q`.
- [ ] Run `git diff --check`.
- [ ] Commit with message `Translate portal UI to Italian`.
