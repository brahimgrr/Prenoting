# ER Diagram

Schema aggiornato dopo la semplificazione a singolo medico. Il diagramma include solo le tabelle applicative attuali e omette le tabelle tecniche Laravel come `cache`, `sessions`, `password_reset_tokens` e `migrations`.

```mermaid
erDiagram
  USERS {
    bigint id PK
    string username UK
    string email
    string first_name
    string last_name
    string password
    string role
    string remember_token
    timestamp created_at
    timestamp updated_at
  }

  PATIENT_PROFILES {
    bigint id PK
    bigint user_id FK
    date date_of_birth
    string place_of_birth
    string codice_fiscale
    string gender
    string phone
    string address
    timestamp created_at
    timestamp updated_at
  }

  DOCTOR_PROFILES {
    bigint id PK
    bigint user_id FK
    string display_name
    text bio
    string license_number
    boolean is_active
    timestamp created_at
    timestamp updated_at
  }

  MEDICAL_SERVICES {
    bigint id PK
    string name UK
    string category
    int duration_minutes
    decimal price
    boolean is_active
    timestamp created_at
    timestamp updated_at
  }

  AVAILABILITY_SLOTS {
    bigint id PK
    datetime start_at UK
    datetime end_at
    boolean is_blocked
    boolean is_booked
    timestamp created_at
    timestamp updated_at
  }

  APPOINTMENTS {
    bigint id PK
    bigint patient_id FK
    bigint service_id FK
    bigint slot_id FK
    datetime start_at
    datetime end_at
    string status
    text notes
    text cancellation_reason
    timestamp created_at
    timestamp updated_at
  }

  USERS ||--o| PATIENT_PROFILES : "ha profilo paziente"
  USERS ||--o| DOCTOR_PROFILES : "ha profilo medico"
  PATIENT_PROFILES ||--o{ APPOINTMENTS : "prenota"
  MEDICAL_SERVICES ||--o{ APPOINTMENTS : "prestazione"
  AVAILABILITY_SLOTS ||--o| APPOINTMENTS : "slot prenotato"
```

Note dominio attuale:

- L'applicazione e pensata per un solo medico, quindi `DOCTOR_PROFILES` resta per autenticazione/profilo dell'area medico ma non viene piu collegata a slot o appuntamenti.
- La specialita e fissa a livello applicativo, ad esempio dermatologia.
- Gli appuntamenti non salvano piu medico o ambulatorio, perche il sistema lavora con un solo medico e senza scelta ambulatorio.
- Le disponibilita sono globali del medico unico: `availability_slots.start_at` e univoco.
- I trattamenti dell'area medico coincidono con le prestazioni prenotabili e vengono salvati in `medical_services`.

Vincoli principali:

- `users.username` e univoco.
- `patient_profiles.user_id` e univoco.
- `doctor_profiles.user_id` e univoco.
- `medical_services.name` e univoco.
- `availability_slots.start_at` e univoco.
