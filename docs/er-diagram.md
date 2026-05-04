# ER Diagram

Schema aggiornato dopo la semplificazione a singolo medico. Il diagramma include solo le tabelle applicative attuali e omette le tabelle tecniche Laravel come `cache`, `sessions`, `password_reset_tokens` e `migrations`.

> Nota: tutte le tabelle includono i timestamp standard `creato_il` (`created_at`) e `aggiornato_il` (`updated_at`), gestiti automaticamente da Eloquent. Sono omessi dal diagramma per leggibilita.

```mermaid
erDiagram
  USERS {
    bigint id PK
    string nome_utente UK
    string email
    string nome
    string cognome
    string password
    string ruolo
  }

  PATIENT_PROFILES {
    bigint id PK
    bigint id_utente FK
    date data_di_nascita
    string luogo_di_nascita
    string codice_fiscale
    string sesso
    string telefono
    string indirizzo
  }

  DOCTOR_PROFILES {
    bigint id PK
    bigint id_utente FK
    string nome_visualizzato
    text biografia
    string numero_albo
    boolean attivo
  }

  MEDICAL_SERVICES {
    bigint id PK
    string nome UK
    string categoria
    int durata_minuti
    decimal prezzo
    boolean attivo
  }

  AVAILABILITY_SLOTS {
    bigint id PK
    datetime inizio_il UK
    datetime fine_il
    boolean bloccato
    boolean prenotato
  }

  APPOINTMENTS {
    bigint id PK
    bigint id_paziente FK
    bigint id_prestazione FK
    bigint id_slot FK
    datetime inizio_il
    datetime fine_il
    string stato
    text note
    text motivo_annullamento
  }

  USERS ||--o| PATIENT_PROFILES : "ha profilo paziente"
  USERS ||--|| DOCTOR_PROFILES : "ha profilo medico (unico)"
  PATIENT_PROFILES ||--o{ APPOINTMENTS : "prenota"
  MEDICAL_SERVICES ||--o{ APPOINTMENTS : "prestazione"
  AVAILABILITY_SLOTS ||--o| APPOINTMENTS : "slot prenotato"
```

Note dominio attuale:

- L'applicazione e pensata per un solo medico: la relazione `USERS — DOCTOR_PROFILES` e modellata come **1 a 1 obbligatoria** (esiste sempre uno e un solo utente con `role='doctor'`, creato via seeder, con il corrispondente record in `doctor_profiles`). `DOCTOR_PROFILES` resta per autenticazione/profilo dell'area medico ma non viene piu collegata a slot o appuntamenti.
- La specialita e fissa a livello applicativo, ad esempio dermatologia.
- Gli appuntamenti non salvano piu medico o ambulatorio, perche il sistema lavora con un solo medico e senza scelta ambulatorio.
- Le disponibilita sono globali del medico unico: `availability_slots.start_at` e univoco.
- I trattamenti dell'area medico coincidono con le prestazioni prenotabili e vengono salvati in `medical_services`.

Vincoli principali:

- `users.nome_utente` e univoco.
- `patient_profiles.id_utente` e univoco.
- `doctor_profiles.id_utente` e univoco.
- `medical_services.nome` e univoco.
- `availability_slots.inizio_il` e univoco.
