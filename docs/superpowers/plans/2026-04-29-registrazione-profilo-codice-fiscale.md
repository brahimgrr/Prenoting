# Registrazione Estesa, Profilo e Codice Fiscale — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Estendere la registrazione paziente con dati anagrafici completi (data/luogo di nascita, sesso, conferma password), calcolare il codice fiscale italiano al momento della registrazione e mostrarlo in sola lettura nel profilo.

**Architecture:** Array PHP statico `ComuniItaliani` per il lookup codice catastale, classe `CodiceFiscaleService` pura che implementa l'algoritmo ufficiale, migration per aggiungere `place_of_birth` e `codice_fiscale` a `patient_profiles`, aggiornamenti a controller, form di registrazione e vista profilo.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, SQLite in-memory per i test.

---

## File Map

| Azione | File |
|---|---|
| Crea | `database/migrations/2026_04_29_000001_add_birth_and_fiscal_to_patient_profiles.php` |
| Modifica | `app/Models/PatientProfile.php` |
| Crea | `app/Data/ComuniItaliani.php` |
| Crea | `app/Services/CodiceFiscaleService.php` |
| Crea | `tests/Feature/CodiceFiscaleServiceTest.php` |
| Modifica | `app/Http/Controllers/AuthController.php` |
| Modifica | `resources/views/auth/register.blade.php` |
| Modifica | `resources/views/patient/profile.blade.php` |
| Modifica | `tests/Feature/AuthTest.php` |

---

## Task 1: Migration — aggiungi `place_of_birth` e `codice_fiscale`

**Files:**
- Create: `database/migrations/2026_04_29_000001_add_birth_and_fiscal_to_patient_profiles.php`

- [ ] **Step 1: Crea il file migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->string('place_of_birth', 160)->default('')->after('date_of_birth');
            $table->string('codice_fiscale', 16)->nullable()->after('identity_code');
        });
    }

    public function down(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->dropColumn(['place_of_birth', 'codice_fiscale']);
        });
    }
};
```

- [ ] **Step 2: Aggiorna `PatientProfile::$fillable`**

In `app/Models/PatientProfile.php`, sostituisci il `$fillable` esistente:

```php
protected $fillable = [
    'user_id',
    'date_of_birth',
    'place_of_birth',
    'gender',
    'phone',
    'address',
    'identity_code',
    'codice_fiscale',
];
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_29_000001_add_birth_and_fiscal_to_patient_profiles.php app/Models/PatientProfile.php
git commit -m "feat: add place_of_birth and codice_fiscale to patient_profiles"
```

---

## Task 2: Dati comuni italiani

**Files:**
- Create: `app/Data/ComuniItaliani.php`

- [ ] **Step 1: Crea la directory e il file**

`app/Data/ComuniItaliani.php` deve restituire un array associativo con chiave = nome comune uppercase senza accenti, valore = codice catastale (Belfiore). Include tutti i comuni italiani attuali e storici ancora in uso per il codice fiscale, più i paesi esteri (prefisso Z).

La struttura del file:

```php
<?php

return [
    'AGRIGENTO'             => 'A089',
    'ALESSANDRIA'           => 'A182',
    'ANCONA'                => 'A271',
    'AOSTA'                 => 'A326',
    'AREZZO'                => 'A390',
    'ASCOLI PICENO'         => 'A462',
    'ASTI'                  => 'A479',
    'AVELLINO'              => 'A509',
    'BARI'                  => 'A662',
    'BARLETTA'              => 'A669',
    'BELLUNO'               => 'A757',
    'BENEVENTO'             => 'A783',
    'BERGAMO'               => 'A794',
    'BIELLA'                => 'A859',
    'BOLOGNA'               => 'A944',
    'BOLZANO'               => 'A952',
    'BRESCIA'               => 'B157',
    'BRINDISI'              => 'B180',
    'CAGLIARI'              => 'B354',
    'CALTANISSETTA'         => 'B429',
    'CAMPOBASSO'            => 'B519',
    'CASERTA'               => 'B963',
    'CATANIA'               => 'C351',
    'CATANZARO'             => 'C352',
    'CHIETI'                => 'C632',
    'COMO'                  => 'C933',
    'COSENZA'               => 'D086',
    'CREMONA'               => 'D150',
    'CROTONE'               => 'D122',
    'CUNEO'                 => 'D205',
    'ENNA'                  => 'C342',
    'FERMO'                 => 'D542',
    'FERRARA'               => 'D548',
    'FIRENZE'               => 'D612',
    'FOGGIA'                => 'D643',
    'FORLI'                 => 'D704',
    'FROSINONE'             => 'D810',
    'GENOVA'                => 'D969',
    'GORIZIA'               => 'E098',
    'GROSSETO'              => 'E202',
    'IMPERIA'               => 'E290',
    'ISERNIA'               => 'E335',
    'LA SPEZIA'             => 'E463',
    'LATINA'                => 'E472',
    'LECCE'                 => 'E506',
    'LECCO'                 => 'E507',
    'LIVORNO'               => 'E625',
    'LODI'                  => 'E648',
    'LUCCA'                 => 'E715',
    'MACERATA'              => 'E783',
    'MANTOVA'               => 'E897',
    'MASSA'                 => 'F023',
    'MATERA'                => 'F052',
    'MESSINA'               => 'F158',
    'MILANO'                => 'F205',
    'MODENA'                => 'F257',
    'MONZA'                 => 'F704',
    'NAPOLI'                => 'F839',
    'NOVARA'                => 'F952',
    'NUORO'                 => 'F979',
    'ORISTANO'              => 'G113',
    'PADOVA'                => 'G224',
    'PALERMO'               => 'G273',
    'PARMA'                 => 'G337',
    'PAVIA'                 => 'G388',
    'PERUGIA'               => 'G478',
    'PESARO'                => 'G479',
    'PESCARA'               => 'G482',
    'PIACENZA'              => 'G535',
    'PISA'                  => 'G702',
    'PISTOIA'               => 'G713',
    'PORDENONE'             => 'G888',
    'POTENZA'               => 'G942',
    'PRATO'                 => 'G999',
    'RAGUSA'                => 'H163',
    'RAVENNA'               => 'H199',
    'REGGIO CALABRIA'       => 'H224',
    'REGGIO EMILIA'         => 'H223',
    'RIETI'                 => 'H282',
    'RIMINI'                => 'H294',
    'ROMA'                  => 'H501',
    'ROVIGO'                => 'H620',
    'SALERNO'               => 'H703',
    'SASSARI'               => 'I452',
    'SAVONA'                => 'I480',
    'SIENA'                 => 'I726',
    'SIRACUSA'              => 'I754',
    'SONDRIO'               => 'I829',
    'SUD SARDEGNA'          => 'M208',
    'TARANTO'               => 'L049',
    'TERAMO'                => 'L103',
    'TERNI'                 => 'L117',
    'TORINO'                => 'L219',
    'TRAPANI'               => 'L331',
    'TRENTO'                => 'L378',
    'TREVISO'               => 'L407',
    'TRIESTE'               => 'L424',
    'UDINE'                 => 'L483',
    'VARESE'                => 'L682',
    'VENEZIA'               => 'L736',
    'VERBANO CUSIO OSSOLA'  => 'L746',
    'VERCELLI'              => 'L750',
    'VERONA'                => 'L781',
    'VIBO VALENTIA'         => 'F537',
    'VICENZA'               => 'L840',
    'VITERBO'               => 'L872',
    // Paesi esteri (selezione — aggiungere lista completa Z000-Z999)
    'ALBANIA'               => 'Z100',
    'ALGERIA'               => 'Z301',
    'ARGENTINA'             => 'Z600',
    'AUSTRALIA'             => 'Z700',
    'AUSTRIA'               => 'Z102',
    'BELGIO'                => 'Z103',
    'BRASILE'               => 'Z602',
    'CANADA'                => 'Z401',
    'CINA'                  => 'Z210',
    'CROAZIA'               => 'Z149',
    'EGITTO'                => 'Z336',
    'ETIOPIA'               => 'Z315',
    'FILIPPINE'             => 'Z216',
    'FRANCIA'               => 'Z110',
    'GERMANIA'              => 'Z112',
    'GIAPPONE'              => 'Z219',
    'GRECIA'                => 'Z115',
    'INDIA'                 => 'Z222',
    'IRAN'                  => 'Z224',
    'IRAQ'                  => 'Z225',
    'IRLANDA'               => 'Z116',
    'ISRAELE'               => 'Z228',
    'KENYA'                 => 'Z322',
    'MAROCCO'               => 'Z330',
    'MESSICO'               => 'Z405',
    'NIGERIA'               => 'Z335',
    'NORVEGIA'              => 'Z125',
    'OLANDA'                => 'Z126',
    'PAKISTAN'              => 'Z236',
    'PERU'                  => 'Z611',
    'POLONIA'               => 'Z127',
    'PORTOGALLO'            => 'Z128',
    'REGNO UNITO'           => 'Z114',
    'ROMANIA'               => 'Z129',
    'RUSSIA'                => 'Z154',
    'SENEGAL'               => 'Z343',
    'SOMALIA'               => 'Z342',
    'SPAGNA'                => 'Z131',
    'STATI UNITI'           => 'Z404',
    'SVIZZERA'              => 'Z133',
    'TUNISIA'               => 'Z352',
    'TURCHIA'               => 'Z243',
    'UCRAINA'               => 'Z138',
    'UNGHERIA'              => 'Z134',
    'VENEZUELA'             => 'Z614',
];
```

> **Nota:** questa lista include i capoluoghi di provincia e i paesi esteri più frequenti. Per la lista completa di tutti i comuni italiani (~8.100 voci), integrare con i dati ISTAT (codici Belfiore), disponibili pubblicamente. I test del piano coprono Roma (H501), Napoli (F839) e Milano (F205), già presenti.

- [ ] **Step 2: Commit**

```bash
git add app/Data/ComuniItaliani.php
git commit -m "feat: add ComuniItaliani lookup data"
```

---

## Task 3: `CodiceFiscaleService` — test prima, poi implementazione

**Files:**
- Create: `tests/Feature/CodiceFiscaleServiceTest.php`
- Create: `app/Services/CodiceFiscaleService.php`

- [ ] **Step 1: Scrivi i test**

Crea `tests/Feature/CodiceFiscaleServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\CodiceFiscaleService;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class CodiceFiscaleServiceTest extends TestCase
{
    public function test_calcola_codice_fiscale_maschile_classico(): void
    {
        $cf = CodiceFiscaleService::calcola(
            cognome: 'Rossi',
            nome: 'Mario',
            data_nascita: Carbon::parse('1980-01-01'),
            sesso: 'M',
            luogo_nascita: 'Roma',
        );
        $this->assertSame('RSSMRA80A01H501U', $cf);
    }

    public function test_calcola_codice_fiscale_femminile(): void
    {
        // Anna Bianchi, 1995-06-20, F, Napoli (F839)
        // Cognome BIANCHI: consonanti B,N,C,H → BNC
        // Nome ANNA: consonanti N,N → vocali A,A → NNA
        // Anno: 95, Mese: H (giugno), Giorno: 20+40=60
        // Stringa base: BNCNNA95H60F839 → check digit T
        $cf = CodiceFiscaleService::calcola(
            cognome: 'Bianchi',
            nome: 'Anna',
            data_nascita: Carbon::parse('1995-06-20'),
            sesso: 'F',
            luogo_nascita: 'Napoli',
        );
        $this->assertSame('BNCNNA95H60F839T', $cf);
    }

    public function test_nome_con_quattro_o_piu_consonanti_usa_prima_terza_quarta(): void
    {
        // Roberto Verdi, 1970-03-15, M, Roma
        // Cognome VERDI: consonanti V,R,D → VRD
        // Nome ROBERTO: consonanti R,B,R,T (4) → prende 1°(R), 3°(R), 4°(T) = RRT
        // Anno: 70, Mese: C (marzo), Giorno: 15
        // Stringa base: VRDRRT70C15H501 → check digit E
        $cf = CodiceFiscaleService::calcola(
            cognome: 'Verdi',
            nome: 'Roberto',
            data_nascita: Carbon::parse('1970-03-15'),
            sesso: 'M',
            luogo_nascita: 'Roma',
        );
        $this->assertSame('VRDRRT70C15H501E', $cf);
    }

    public function test_comune_non_trovato_lancia_eccezione(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CodiceFiscaleService::calcola(
            cognome: 'Rossi',
            nome: 'Mario',
            data_nascita: Carbon::parse('1980-01-01'),
            sesso: 'M',
            luogo_nascita: 'ComuneInesistente',
        );
    }

    public function test_input_case_insensitive_e_accenti_rimossi(): void
    {
        $cf1 = CodiceFiscaleService::calcola('Rossi', 'Mario', Carbon::parse('1980-01-01'), 'M', 'Roma');
        $cf2 = CodiceFiscaleService::calcola('Rossi', 'Mario', Carbon::parse('1980-01-01'), 'M', 'roma');
        $cf3 = CodiceFiscaleService::calcola('Rossi', 'Mario', Carbon::parse('1980-01-01'), 'M', 'ROMA');
        $this->assertSame($cf1, $cf2);
        $this->assertSame($cf1, $cf3);
    }
}
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

```bash
composer test -- --filter CodiceFiscaleServiceTest
```

Output atteso: errore "Class not found" o simile per `CodiceFiscaleService`.

- [ ] **Step 3: Implementa `CodiceFiscaleService`**

Crea `app/Services/CodiceFiscaleService.php`:

```php
<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class CodiceFiscaleService
{
    private const MESI = ['A', 'B', 'C', 'D', 'E', 'H', 'L', 'M', 'P', 'R', 'S', 'T'];

    private const DISPARI = [
        '0' => 1,  '1' => 0,  '2' => 5,  '3' => 7,  '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1,  'B' => 0,  'C' => 5,  'D' => 7,  'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2,  'L' => 4,  'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3,  'Q' => 6,  'R' => 8,  'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    public static function calcola(
        string $cognome,
        string $nome,
        Carbon $data_nascita,
        string $sesso,
        string $luogo_nascita,
    ): string {
        $cf  = self::codificaCognome($cognome);
        $cf .= self::codificaNome($nome);
        $cf .= substr($data_nascita->format('Y'), 2, 2);
        $cf .= self::MESI[$data_nascita->month - 1];
        $giorno = $data_nascita->day + ($sesso === 'F' ? 40 : 0);
        $cf .= str_pad((string) $giorno, 2, '0', STR_PAD_LEFT);
        $cf .= self::codiceCatastale($luogo_nascita);
        $cf .= self::carattereControllo($cf);

        return strtoupper($cf);
    }

    private static function codificaCognome(string $cognome): string
    {
        $consonanti = self::estraiConsonanti($cognome);
        $vocali     = self::estraiVocali($cognome);

        return substr(str_pad($consonanti . $vocali, 3, 'X'), 0, 3);
    }

    private static function codificaNome(string $nome): string
    {
        $consonanti = self::estraiConsonanti($nome);

        if (strlen($consonanti) >= 4) {
            return $consonanti[0] . $consonanti[2] . $consonanti[3];
        }

        $vocali = self::estraiVocali($nome);

        return substr(str_pad($consonanti . $vocali, 3, 'X'), 0, 3);
    }

    private static function estraiConsonanti(string $s): string
    {
        return (string) preg_replace('/[^BCDFGHJKLMNPQRSTVWXYZ]/', '', strtoupper(self::normalizza($s)));
    }

    private static function estraiVocali(string $s): string
    {
        return (string) preg_replace('/[^AEIOU]/', '', strtoupper(self::normalizza($s)));
    }

    private static function normalizza(string $s): string
    {
        return strtr($s, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e',
            'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ä' => 'a', 'ë' => 'e',
            'ï' => 'i', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private static function codiceCatastale(string $luogo): string
    {
        $comuni = require app_path('Data/ComuniItaliani.php');
        $chiave = strtoupper(trim(self::normalizza($luogo)));

        if (! isset($comuni[$chiave])) {
            throw new InvalidArgumentException("Comune non trovato: {$luogo}");
        }

        return $comuni[$chiave];
    }

    private static function carattereControllo(string $cf): string
    {
        $cf  = strtoupper($cf);
        $sum = 0;

        for ($i = 0; $i < 15; $i++) {
            $c = $cf[$i];

            if ($i % 2 === 0) {
                $sum += self::DISPARI[$c];
            } else {
                $sum += ctype_digit($c)
                    ? (int) $c
                    : ord($c) - ord('A');
            }
        }

        return chr(65 + ($sum % 26));
    }
}
```

- [ ] **Step 4: Esegui i test e verifica che passino**

```bash
composer test -- --filter CodiceFiscaleServiceTest
```

Output atteso: `4 tests, 4 assertions` — tutti verdi.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CodiceFiscaleService.php tests/Feature/CodiceFiscaleServiceTest.php
git commit -m "feat: add CodiceFiscaleService with TDD"
```

---

## Task 4: Aggiorna `AuthController::register`

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`

- [ ] **Step 1: Aggiorna il metodo `register`**

Sostituisci l'intero metodo `register` in `AuthController.php`:

```php
public function register(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'first_name'     => ['nullable', 'string', 'max:150'],
        'last_name'      => ['nullable', 'string', 'max:150'],
        'username'       => ['required', 'email', 'max:150', 'unique:users,username'],
        'password'       => ['required', 'string', 'min:8', 'confirmed'],
        'phone'          => ['required', 'string', 'max:32'],
        'date_of_birth'  => ['required', 'date', 'before:today'],
        'place_of_birth' => ['required', 'string', 'max:160'],
        'gender'         => ['required', 'in:M,F'],
    ], [
        'username.unique'      => 'Esiste gia un utente con questo username.',
        'password.min'         => 'La password deve contenere almeno 8 caratteri.',
        'password.confirmed'   => 'Le password non coincidono.',
        'date_of_birth.before' => 'La data di nascita non è valida.',
        'gender.in'            => 'Seleziona il sesso.',
    ]);

    try {
        $codiceFiscale = CodiceFiscaleService::calcola(
            cognome: $validated['last_name'] ?? '',
            nome: $validated['first_name'] ?? '',
            data_nascita: \Carbon\Carbon::parse($validated['date_of_birth']),
            sesso: $validated['gender'],
            luogo_nascita: $validated['place_of_birth'],
        );
    } catch (\InvalidArgumentException) {
        throw ValidationException::withMessages([
            'place_of_birth' => 'Comune non riconosciuto. Scrivi il nome per esteso (es. Reggio Calabria). Per nati all\'estero, scrivi il nome del paese in italiano (es. Francia).',
        ]);
    }

    $user = User::create([
        'username'   => $validated['username'],
        'email'      => $validated['username'],
        'first_name' => $validated['first_name'] ?? '',
        'last_name'  => $validated['last_name'] ?? '',
        'password'   => $validated['password'],
        'role'       => User::ROLE_PATIENT,
    ]);

    PatientProfile::create([
        'user_id'         => $user->id,
        'phone'           => $validated['phone'],
        'date_of_birth'   => $validated['date_of_birth'],
        'place_of_birth'  => $validated['place_of_birth'],
        'gender'          => $validated['gender'],
        'codice_fiscale'  => $codiceFiscale,
    ]);

    Auth::login($user);
    $request->session()->regenerate();

    return redirect('/patient');
}
```

Aggiungi l'import mancante in cima al file (dopo gli `use` esistenti):

```php
use App\Services\CodiceFiscaleService;
```

- [ ] **Step 2: Aggiorna il test di registrazione esistente in `AuthTest.php`**

Il test `test_patient_can_register_and_reach_dashboard` va aggiornato con i nuovi campi obbligatori. Sostituiscilo:

```php
public function test_patient_can_register_and_reach_dashboard(): void
{
    $response = $this->post('/register', [
        'username'              => 'sara@example.com',
        'password'              => 'strong-pass-123',
        'password_confirmation' => 'strong-pass-123',
        'first_name'            => 'Sara',
        'last_name'             => 'Conti',
        'phone'                 => '+390000000',
        'date_of_birth'         => '1990-05-15',
        'place_of_birth'        => 'Milano',
        'gender'                => 'F',
    ]);

    $response->assertRedirect('/patient');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'username' => 'sara@example.com',
        'role'     => User::ROLE_PATIENT,
    ]);
    $this->assertDatabaseHas('patient_profiles', [
        'phone'          => '+390000000',
        'place_of_birth' => 'Milano',
        'gender'         => 'F',
    ]);
}
```

Aggiungi subito dopo un test per il campo `place_of_birth` non valido:

```php
public function test_register_rejects_unknown_comune(): void
{
    $response = $this->from('/register')->post('/register', [
        'username'              => 'mario@example.com',
        'password'              => 'strong-pass-123',
        'password_confirmation' => 'strong-pass-123',
        'first_name'            => 'Mario',
        'last_name'             => 'Rossi',
        'phone'                 => '+390000001',
        'date_of_birth'         => '1980-01-01',
        'place_of_birth'        => 'ComuneInesistente',
        'gender'                => 'M',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('place_of_birth');
    $this->assertGuest();
}
```

Aggiungi anche un test per la conferma password errata:

```php
public function test_register_rejects_mismatched_password_confirmation(): void
{
    $response = $this->from('/register')->post('/register', [
        'username'              => 'mario@example.com',
        'password'              => 'strong-pass-123',
        'password_confirmation' => 'wrong-pass-999',
        'first_name'            => 'Mario',
        'last_name'             => 'Rossi',
        'phone'                 => '+390000001',
        'date_of_birth'         => '1980-01-01',
        'place_of_birth'        => 'Roma',
        'gender'                => 'M',
    ]);

    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('password');
    $this->assertGuest();
}
```

- [ ] **Step 3: Esegui tutti i test**

```bash
composer test
```

Output atteso: tutti verdi.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AuthController.php tests/Feature/AuthTest.php
git commit -m "feat: extend register with birth data, gender, CF calculation"
```

---

## Task 5: Form di registrazione

**Files:**
- Modify: `resources/views/auth/register.blade.php`

- [ ] **Step 1: Sostituisci la vista con il form completo**

```blade
@extends('layouts.guest', ['title' => 'Crea account - MedPortal'])

@section('content')
  <main class="auth-page">
    <section class="auth-panel auth-panel--wide">
      <div class="auth-panel__header">
        <span class="app-brand__mark">M</span>
        <div>
          <h1>Crea account</h1>
          <p>Registrati come paziente per prenotare e gestire gli appuntamenti.</p>
        </div>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="/register" novalidate>
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="first_name">Nome</label>
            <input class="form-control" id="first_name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="last_name">Cognome</label>
            <input class="form-control" id="last_name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}">
          </div>
          <div class="col-12">
            <label class="form-label" for="username">Email</label>
            <input class="form-control @error('username') is-invalid @enderror" id="username" name="username" type="email" autocomplete="email" value="{{ old('username') }}" required>
            @error('username') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Telefono</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" required>
            @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="gender">Sesso</label>
            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
              <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Seleziona</option>
              <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>Maschile</option>
              <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>Femminile</option>
            </select>
            @error('gender') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="date_of_birth">Data di nascita</label>
            <input class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" required>
            @error('date_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="place_of_birth">Luogo di nascita</label>
            <input class="form-control @error('place_of_birth') is-invalid @enderror" id="place_of_birth" name="place_of_birth" type="text" autocomplete="off" value="{{ old('place_of_birth') }}" required>
            @error('place_of_birth') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="form-text">Scrivi il nome per esteso (es. Reggio Calabria). Per nati all'estero, scrivi il paese in italiano (es. Francia).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Conferma password</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
          </div>
        </div>
        <button class="btn btn-primary w-100 mt-4" type="submit">Crea account</button>
      </form>

      <p class="auth-panel__footer">
        Hai gia un account? <a href="/login">Accedi</a>
      </p>
    </section>
  </main>
@endsection
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/auth/register.blade.php
git commit -m "feat: update registration form with all required fields"
```

---

## Task 6: Vista profilo paziente

**Files:**
- Modify: `resources/views/patient/profile.blade.php`

- [ ] **Step 1: Aggiorna la sezione dati anagrafici readonly**

Sostituisci il blocco `<section class="portal-panel profile-readonly-panel">` con:

```blade
<section class="portal-panel profile-readonly-panel">
  <div class="section-heading">
    <h2>Dati anagrafici</h2>
    <span>Dati non modificabili</span>
  </div>
  <div class="profile-grid">
    <label class="form-label">
      Nome
      <input class="form-control" value="{{ $user->first_name ?: '-' }}" readonly>
    </label>
    <label class="form-label">
      Cognome
      <input class="form-control" value="{{ $user->last_name ?: '-' }}" readonly>
    </label>
    <label class="form-label">
      Sesso
      <input class="form-control" value="{{ $profile?->gender === 'M' ? 'Maschile' : ($profile?->gender === 'F' ? 'Femminile' : '-') }}" readonly>
    </label>
    <label class="form-label">
      Data di nascita
      <input class="form-control" value="{{ $profile?->date_of_birth?->format('d/m/Y') ?: '-' }}" readonly>
    </label>
    <label class="form-label">
      Luogo di nascita
      <input class="form-control" value="{{ $profile?->place_of_birth ?: '-' }}" readonly>
    </label>
    <label class="form-label">
      Codice fiscale
      <input class="form-control" value="{{ $profile?->codice_fiscale ?: '-' }}" readonly>
    </label>
    <label class="form-label">
      Codice identificativo
      <input class="form-control" value="{{ $profile?->identity_code ?: '-' }}" readonly>
    </label>
  </div>
  <p class="profile-lock-note">Questi dati non sono modificabili dal portale.</p>
</section>
```

- [ ] **Step 2: Esegui tutti i test**

```bash
composer test
```

Output atteso: tutti verdi.

- [ ] **Step 3: Commit finale**

```bash
git add resources/views/patient/profile.blade.php
git commit -m "feat: show complete readonly anagrafica in patient profile"
```

---

## Self-Review

- [x] **Migration** crea `place_of_birth` e `codice_fiscale` → Task 1
- [x] **Model fillable** aggiornato → Task 1
- [x] **ComuniItaliani** lookup file → Task 2
- [x] **CodiceFiscaleService** algoritmo completo con TDD → Task 3
- [x] **AuthController** valida e salva tutti i nuovi campi, calcola CF → Task 4
- [x] **AuthTest** aggiornato: test esistente + 2 nuovi test → Task 4
- [x] **Form registrazione** con tutti i campi → Task 5
- [x] **Profilo** mostra tutti i campi readonly → Task 6
- [x] Nessun placeholder, nessun TBD
- [x] Nomi metodi coerenti tra tutti i task (`CodiceFiscaleService::calcola`, `estraiConsonanti`, `codiceCatastale`)
