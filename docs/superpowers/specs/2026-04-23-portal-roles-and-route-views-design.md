# Portal Roles And Route Views Design

## Scope

The appointment portal exposes three product roles: patient, doctor, and staff. The Django admin site may still exist for technical administration, but `admin` is not a portal role and must not grant access to staff portal screens or staff API endpoints.

Doctor and staff navigation entries must show distinct page contexts when selected. The existing shared data can remain, but route titles, descriptions, and visible sections should make `/doctor` different from `/doctor/schedule`, and `/staff` different from `/staff/appointments`.

## Design

- Backend role helpers continue to identify patients, doctors, and staff group members. Superusers are treated as a non-portal user for appointment API permissions.
- Staff API permissions require the `Staff` group only.
- Frontend role routing maps only patient, doctor, and staff. Unsupported users receive the unsupported role page instead of being treated as staff.
- `DoctorDashboard` receives a view mode: today summary for `/doctor`, schedule list for `/doctor/schedule`.
- `StaffDashboard` receives a view mode: operations summary for `/staff`, appointment work queue for `/staff/appointments`.

## Verification

- Add a backend test proving superusers cannot access staff appointment endpoints.
- Run backend role dashboard tests.
- Run the frontend production build in the Docker container.
