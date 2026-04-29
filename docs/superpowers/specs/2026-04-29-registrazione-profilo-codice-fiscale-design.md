# Design: Registrazione estesa, Profilo e Codice Fiscale

**Data:** 2026-04-29
**Branch:** patient-feat-php

---

## Obiettivo

Estendere la registrazione paziente con i dati anagrafici completi, renderli visibili nel profilo in sola lettura, e calcolare automaticamente il codice fiscale italiano al momento della registrazione.

---

## 1. Database

### Migration: `add_birth_and_fiscal_fields_to_patient_profiles`

Aggiunge a `patient_profiles`:

| Colonna | Tipo | Vincolo |
|---|---|---|
| `place_of_birth` | `string(160)` | `after('date_of_birth')` |
| `codice_fiscale` | `string(16)` | `nullable`, `after('identity_code')` |

Il campo `gender` esiste già — viene solo popolato in fase di registrazione.

### Modello `PatientProfile`

Aggiungere `place_of_birth` e `codice_fiscale` al `$fillable`.

---

## 2. Dati comuni: `app/Data/ComuniItaliani.php`

File PHP che restituisce un array associativo:

```php
return [
    'ROMA' => 'H501',
    'MILANO' => 'F205',
    // ...~8.100 voci totali
];
```

- Chiavi: nome comune uppercase, accenti rimossi, normalizzato
- Include: comuni italiani attuali, comuni soppressi con codice ancora valido, paesi esteri (es. `'FRANCIA' => 'Z110'`)
- Usato solo in fase di registrazione e dal service — non una tabella DB

---

## 3. Service: `app/Services/CodiceFiscaleService.php`

```php
class CodiceFiscaleService
{
    public static function calcola(
        string $cognome,
        string $nome,
        Carbon $data_nascita,
        string $sesso,        // 'M' o 'F'
        string $luogo_nascita
    ): string
}
```

### Algoritmo (standard Agenzia delle Entrate)

1. **Cognome** — estrae consonanti, poi vocali, poi X fino a 3 caratteri
2. **Nome** — se ≥4 consonanti: prende 1ª, 3ª, 4ª; altrimenti consonanti poi vocali poi X
3. **Anno** — ultime 2 cifre dell'anno di nascita
4. **Mese** — lettera da tabella fissa (A=gennaio … T=dicembre)
5. **Giorno** — giorno di nascita; +40 se sesso = F
6. **Codice catastale** — lookup da `ComuniItaliani` normalizzando l'input
7. **Carattere di controllo** — algoritmo ufficiale pari/dispari

### Errori

Lancia `InvalidArgumentException` se il comune non è trovato. Il controller la cattura e la converte in errore di validazione sul campo `place_of_birth`.

---

## 4. Registrazione

### Form `resources/views/auth/register.blade.php`

Campi aggiunti ai già esistenti (nome, cognome, email, telefono, password):

| Campo | Input HTML | Obbligatorio |
|---|---|---|
| Data di nascita | `type="date"` | Sì |
| Luogo di nascita | `type="text"` | Sì |
| Sesso | `<select>` con opzioni M/F | Sì |
| Conferma password | `type="password"` name="password_confirmation" | Sì |

Messaggio di aiuto sotto "Luogo di nascita": *"Scrivi il nome per esteso (es. Reggio Calabria). Per nati all'estero, scrivi il nome del paese in italiano."*

### Controller `AuthController::register`

Aggiornamenti:
- Validazione aggiunta: `date_of_birth` (required, date, before: oggi), `place_of_birth` (required, string, max:160), `gender` (required, in:M,F), `password` (aggiunta regola `confirmed`)
- `PatientProfile::create` aggiornato con `place_of_birth`, `gender`, `date_of_birth`
- Dopo la creazione del profilo: chiama `CodiceFiscaleService::calcola()`, salva `codice_fiscale` sul profilo
- Eccezione del service → `ValidationException` sul campo `place_of_birth`

---

## 5. Profilo paziente

### Vista `resources/views/patient/profile.blade.php`

La sezione "Dati anagrafici" (readonly) mostra:

| Label | Valore |
|---|---|
| Nome | `$user->first_name` |
| Cognome | `$user->last_name` |
| Sesso | `$profile->gender === 'M' ? 'Maschile' : 'Femminile'` |
| Data di nascita | `$profile->date_of_birth->format('d/m/Y')` |
| Luogo di nascita | `$profile->place_of_birth` |
| Codice fiscale | `$profile->codice_fiscale` |
| Codice identificativo | `$profile->identity_code` (invariato) |

Tutti i campi sono `<input readonly>`. Nessuna modifica alle sezioni "Contatto" e "Password".

---

## Fuori scope

- Modifica del codice fiscale dopo la registrazione
- Validazione esterna del codice fiscale (es. API Agenzia delle Entrate)
- Autocomplete comune nel form
- Gestione comuni con nomi ambigui (gestita dal messaggio di aiuto)
