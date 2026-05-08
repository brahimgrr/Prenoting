# ER Diagram

Schema aggiornato dopo il passaggio dalle disponibilita salvate come slot fisici alla disponibilita dinamica basata su regole. Il diagramma include solo le tabelle applicative attuali e omette le tabelle tecniche Laravel come `cache`, `sessions`, `password_reset_tokens` e `migrations`.

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
    string telefono
    string indirizzo_studio
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

  WORKING_HOURS {
    bigint id PK
    bigint id_medico FK
    tinyint giorno_settimana
    time ora_inizio
    time ora_fine
    date valido_dal
    date valido_al
    boolean attivo
  }

  SPECIAL_OPENINGS {
    bigint id PK
    bigint id_medico FK
    date data
    time ora_inizio
    time ora_fine
    string nota
  }

  CLOSURES {
    bigint id PK
    bigint id_medico FK
    date data
    time ora_inizio
    time ora_fine
    string motivo
  }

  APPOINTMENTS {
    bigint id PK
    bigint id_paziente FK
    bigint id_medico FK
    bigint id_prestazione FK
    datetime inizio_il
    datetime fine_il
    string stato
    text note
    text motivo_annullamento
  }

  USERS ||--o| PATIENT_PROFILES : "ha profilo paziente"
  USERS ||--|| DOCTOR_PROFILES : "ha profilo medico"
  DOCTOR_PROFILES ||--o{ WORKING_HOURS : "configura orari ricorrenti"
  DOCTOR_PROFILES ||--o{ SPECIAL_OPENINGS : "aggiunge aperture straordinarie"
  DOCTOR_PROFILES ||--o{ CLOSURES : "blocca chiusure"
  DOCTOR_PROFILES ||--o{ APPOINTMENTS : "riceve"
  PATIENT_PROFILES ||--o{ APPOINTMENTS : "prenota"
  MEDICAL_SERVICES ||--o{ APPOINTMENTS : "prestazione"
```

Note dominio attuale:

- L'applicazione resta pensata per un solo medico e un solo studio fisico. Le regole di disponibilita sono comunque collegate a `doctor_profiles` per mantenere chiara la proprieta del calendario.
- Non esiste piu una tabella di slot fisici futuri. Gli orari prenotabili vengono generati dinamicamente a partire da `working_hours`, `special_openings`, `closures` e dagli appuntamenti attivi.
- La griglia di inizio appuntamento resta fissa a 30 minuti. La durata effettiva viene presa da `medical_services.durata_minuti` e deve entrare interamente in una finestra aperta.
- `closures` ha precedenza su aperture ordinarie e straordinarie. Se `ora_inizio` e `ora_fine` sono nulle, la chiusura vale per l'intera giornata.
- Gli appuntamenti copiano `inizio_il` e `fine_il` per conservare lo storico anche se le regole di disponibilita cambiano in seguito.

Vincoli principali:

- `users.nome_utente` e univoco.
- `patient_profiles.id_utente` e univoco.
- `doctor_profiles.id_utente` e univoco.
- `medical_services.nome` e univoco.
- Le prenotazioni non dipendono da uno slot persistito: la disponibilita viene riverificata in transazione bloccando il profilo medico e controllando sovrapposizioni con appuntamenti attivi.
