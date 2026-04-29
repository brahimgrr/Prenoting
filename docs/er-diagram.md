# ER Diagram

This diagram is based on the Laravel schema in `database/migrations`.

Notes:
- It covers the domain model and omits framework support tables such as `cache`, `cache_locks`, `sessions`, and `password_reset_tokens`.
- The branch currently contains both the legacy shared catalog path (`medical_services` + `doctor_services`) and the newer doctor-owned catalog path (`doctor_treatment_offerings`).
- Appointments still reference `medical_services`, not `doctor_treatment_offerings`.
- This Mermaid version is written for broad parser compatibility, so some uniqueness constraints are described here instead of encoded inline.
- Unique constraints in schema: `users.username`, `specialties.name`, `patient_profiles.user_id`, `doctor_profiles.user_id`, `doctor_services(doctor_id, service_id)`, `doctor_treatment_offerings(doctor_id, name)`, `availability_slots(doctor_id, start_at)`.

```mermaid
erDiagram
  USERS {
    bigint id PK
    string username
    string email
    string role
    string first_name
    string last_name
  }

  PATIENT_PROFILES {
    bigint id PK
    bigint user_id FK
    date date_of_birth
    string gender
    string phone
    string address
    string identity_code
  }

  DOCTOR_PROFILES {
    bigint id PK
    bigint user_id FK
    bigint specialty_id FK
    string display_name
    string license_number
    boolean is_active
  }

  SPECIALTIES {
    bigint id PK
    string name
    text description
  }

  CLINIC_LOCATIONS {
    bigint id PK
    string name
    string address
    string phone
    boolean is_active
  }

  MEDICAL_SERVICES {
    bigint id PK
    bigint specialty_id FK
    string name
    string category
    int duration_minutes
    decimal price
    boolean is_active
  }

  DOCTOR_SERVICES {
    bigint id PK
    bigint doctor_id FK
    bigint service_id FK
  }

  DOCTOR_TREATMENT_OFFERINGS {
    bigint id PK
    bigint doctor_id FK
    bigint specialty_id FK
    string name
    string category
    int duration_minutes
    decimal price
    boolean is_active
  }

  AVAILABILITY_SLOTS {
    bigint id PK
    bigint doctor_id FK
    bigint clinic_id FK
    datetime start_at
    datetime end_at
    boolean is_blocked
    boolean is_booked
  }

  APPOINTMENTS {
    bigint id PK
    bigint patient_id FK
    bigint doctor_id FK
    bigint service_id FK
    bigint clinic_id FK
    bigint slot_id FK
    datetime start_at
    datetime end_at
    string status
  }

  APPOINTMENT_STATUS_HISTORY {
    bigint id PK
    bigint appointment_id FK
    bigint changed_by FK
    string previous_status
    string new_status
    timestamp changed_at
  }

  USERS ||--o| PATIENT_PROFILES : has
  USERS ||--o| DOCTOR_PROFILES : has
  SPECIALTIES ||--o{ DOCTOR_PROFILES : classifies
  SPECIALTIES ||--o{ MEDICAL_SERVICES : defines
  SPECIALTIES ||--o{ DOCTOR_TREATMENT_OFFERINGS : scopes
  DOCTOR_PROFILES ||--o{ DOCTOR_SERVICES : offers_legacy
  MEDICAL_SERVICES ||--o{ DOCTOR_SERVICES : linked_in_legacy
  DOCTOR_PROFILES ||--o{ DOCTOR_TREATMENT_OFFERINGS : owns
  DOCTOR_PROFILES ||--o{ AVAILABILITY_SLOTS : publishes
  CLINIC_LOCATIONS ||--o{ AVAILABILITY_SLOTS : hosts
  PATIENT_PROFILES ||--o{ APPOINTMENTS : books
  DOCTOR_PROFILES ||--o{ APPOINTMENTS : receives
  MEDICAL_SERVICES ||--o{ APPOINTMENTS : used_for
  CLINIC_LOCATIONS ||--o{ APPOINTMENTS : happens_at
  AVAILABILITY_SLOTS ||--o{ APPOINTMENTS : scheduled_in
  APPOINTMENTS ||--o{ APPOINTMENT_STATUS_HISTORY : tracks
  USERS ||--o{ APPOINTMENT_STATUS_HISTORY : changes
```
