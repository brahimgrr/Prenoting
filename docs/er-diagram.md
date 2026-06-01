# ER Diagram

Schema aggiornato dopo il passaggio dalle disponibilita salvate come slot fisici alla disponibilita dinamica basata su regole. Il diagramma usa i nomi reali di tabelle e colonne presenti nel database e omette le tabelle tecniche Laravel come `cache`, `cache_locks`, `sessions`, `password_reset_tokens` e `migrations`.

![ER Diagram](er-diagram.png)

> Nota: tutte le tabelle includono i timestamp standard `creato_il` (`created_at`) e `aggiornato_il` (`updated_at`), gestiti automaticamente da Eloquent. Sono omessi dal diagramma per leggibilita.

```mermaid
erDiagram
  USERS {
    bigint id PK
    string email UK
    string first_name
    string last_name
    string password
    string role
    string remember_token
  }

  PATIENT_PROFILES {
    bigint id PK
    bigint user_id FK "unique"
    date date_of_birth
    string place_of_birth
    string gender
    string phone
    string address
    string codice_fiscale
  }

  DOCTOR_PROFILES {
    bigint id PK
    bigint user_id FK "unique"
    string display_name
    text bio
    string license_number
    string phone
    string clinic_address
    boolean is_active
  }

  MEDICAL_SERVICES {
    bigint id PK
    string name UK
    string category
    int duration_minutes
    decimal price
    boolean is_active
  }

  WORKING_HOURS {
    bigint id PK
    bigint doctor_profile_id FK
    tinyint weekday
    time start_time
    time end_time
    date effective_from
    date effective_until
    boolean is_active
  }

  SPECIAL_OPENINGS {
    bigint id PK
    bigint doctor_profile_id FK
    date date
    time start_time
    time end_time
    string note
  }

  CLOSURES {
    bigint id PK
    bigint doctor_profile_id FK
    date date
    time start_time "nullable"
    time end_time "nullable"
    string reason
  }

  APPOINTMENTS {
    bigint id PK
    bigint patient_id FK
    bigint doctor_profile_id FK "nullable"
    bigint service_id FK
    datetime start_at
    datetime end_at
    string status
    text notes
    text cancellation_reason
  }

  USERS ||--o| PATIENT_PROFILES : "has patient profile"
  USERS ||--o| DOCTOR_PROFILES : "has doctor profile"
  DOCTOR_PROFILES ||--o{ WORKING_HOURS : "defines recurring hours"
  DOCTOR_PROFILES ||--o{ SPECIAL_OPENINGS : "adds special openings"
  DOCTOR_PROFILES ||--o{ CLOSURES : "blocks closures"
  DOCTOR_PROFILES |o--o{ APPOINTMENTS : "receives"
  PATIENT_PROFILES ||--o{ APPOINTMENTS : "books"
  MEDICAL_SERVICES ||--o{ APPOINTMENTS : "uses service"
```

Note dominio attuale:

- L'applicazione resta pensata per un solo medico e un solo studio fisico. Le regole di disponibilita sono comunque collegate a `doctor_profiles` per mantenere chiara la proprieta del calendario.
- Il catalogo prestazioni e condiviso nel singolo studio: non esiste piu una tabella ponte tra medico e prestazioni.
- Non esiste piu una tabella di slot fisici futuri. Gli orari prenotabili vengono generati dinamicamente a partire da `working_hours`, `special_openings`, `closures` e dagli appuntamenti attivi.
- La griglia di inizio appuntamento resta fissa a 30 minuti. La durata effettiva viene presa da `medical_services.duration_minutes` e deve entrare interamente in una finestra aperta.
- `closures` ha precedenza su aperture ordinarie e straordinarie. Se `start_time` e `end_time` sono nulle, la chiusura vale per l'intera giornata.
- Gli appuntamenti copiano `start_at` e `end_at` per conservare lo storico anche se le regole di disponibilita cambiano in seguito.
- `appointments.doctor_profile_id` e nullable a livello database per compatibilita con la migrazione verso la disponibilita dinamica; il flusso applicativo corrente lo valorizza quando crea o aggiorna una prenotazione.

Vincoli principali:

- `users.email` e univoco.
- `patient_profiles.user_id` e univoco.
- `doctor_profiles.user_id` e univoco.
- `medical_services.name` e univoco.
- Le prenotazioni non dipendono da uno slot persistito: la disponibilita viene riverificata in transazione bloccando il profilo medico e controllando sovrapposizioni con appuntamenti attivi.
