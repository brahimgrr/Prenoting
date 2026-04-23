# Docker Development Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a local Docker Compose development environment for the full MVP.

**Architecture:** Compose runs PostgreSQL, Django, and Vite as separate services. Backend startup waits for Postgres, runs migrations and seed data, then starts Django on `0.0.0.0:8000`; frontend runs Vite on `0.0.0.0:5173`.

**Tech Stack:** Docker Compose, PostgreSQL 16, Python 3.12 slim, Node 22 Alpine, Django, Vite.

---

### Task 1: Add Docker Runtime Files

**Files:**
- Create: `docker-compose.yml`
- Create: `.env.example`
- Create: `.dockerignore`
- Create: `backend/Dockerfile`
- Create: `backend/docker-entrypoint.sh`
- Create: `frontend/Dockerfile`
- Modify: `README.md`

- [ ] Add Compose services for `db`, `backend`, and `frontend`.
- [ ] Configure backend environment with `DB_ENGINE=django.db.backends.postgresql`, `DB_HOST=db`, and credentials matching the database service.
- [ ] Add backend entrypoint to wait for Postgres, run migrations, seed demo data, and start Django.
- [ ] Add frontend Dockerfile using Node 22 and Vite host binding.
- [ ] Document Docker setup, local URLs, and useful commands in README.
- [ ] Run `docker compose config`.
- [ ] Run `npm run build`.
- [ ] Commit with message `Dockerize development environment`.

## Self-Review

- Spec coverage: Compose, Dockerfiles, env example, entrypoint, README, and verification are covered.
- Placeholder scan: no placeholder work remains.
- Type consistency: service names and environment variables match current Django settings.
