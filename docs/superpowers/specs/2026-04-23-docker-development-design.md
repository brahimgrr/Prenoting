# Docker Development Design

Date: 2026-04-23

## Goal

Dockerize the medical appointment MVP so the Django backend, PostgreSQL database, and React frontend can run locally with one Compose command.

## Design

The development stack will use three services:

- `db`: PostgreSQL 16 with persistent named volume.
- `backend`: Django 5 running on Python 3.12, connected to `db`, applying migrations and demo seed data at startup, then serving on port `8000`.
- `frontend`: Vite React running on Node 22, serving on port `5173` and calling the backend at `http://127.0.0.1:8000/api`.

The Docker setup is for local development. It will preserve the existing non-Docker setup and document both paths in `README.md`.

## Files

- Root `docker-compose.yml`.
- Root `.env.example`.
- Root `.dockerignore`.
- `backend/Dockerfile`.
- `backend/docker-entrypoint.sh`.
- `frontend/Dockerfile`.
- README Docker instructions.

## Verification

- `docker compose config` must validate.
- Build/run will be attempted. If Docker is unavailable in this environment, document the exact blocker.
- Existing frontend build should still pass.
