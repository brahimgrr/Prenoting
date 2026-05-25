# MedPortal Laravel Appointment MVP

This branch contains only the Laravel + MySQL + Blade migration of the medical appointment MVP. The legacy Django backend and React frontend live on `main`, not on this branch.

## Stack

- Laravel 13
- PHP 8.4
- MySQL 8.4
- Blade templates
- Eloquent models and migrations
- Bootstrap 5 with a small jQuery enhancement layer
- Vite for CSS and JavaScript assets

## Local Setup

Install PHP 8.4, Composer, Node.js 18+, and MySQL. Then run:

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

The app will be available at `http://127.0.0.1:8000`.

## Docker Setup

If Docker Desktop is running:

```sh
cp .env.example .env
docker compose up --build
```

This starts:

- Laravel app on `http://127.0.0.1:8080`
- MySQL on `127.0.0.1:3307`
- Vite dev server on `http://127.0.0.1:5173`

The Docker setup bind-mounts the project into the PHP and Vite containers, so Blade, PHP, CSS, and JavaScript edits are reflected without rebuilding the image. The app container runs migrations and seeds demo data on startup.

## Demo Accounts

| Role | Username | Password |
| --- | --- | --- |
| Patient | `patient` | `patient123` |
| Doctor | `doctor.derm` | `doctor123` |

## Main URLs

- Login: `/login`
- Register: `/register`
- Patient dashboard: `/patient`
- Patient booking: `/patient/book`
- Patient appointments: `/patient/appointments`
- Doctor dashboard: `/doctor`
- Doctor agenda: `/doctor/agenda`
- Staff operations: `/staff`
- Staff appointments: `/staff/appointments`

## Study Guide

For a two-person presentation split, see [`CODEBASE_STUDY_SPLIT.md`](CODEBASE_STUDY_SPLIT.md).

## Verification

Frontend assets:

```sh
npm run build
```

Laravel tests:

```sh
composer test
```

With Docker:

```sh
docker compose exec app ./vendor/bin/phpunit
```
