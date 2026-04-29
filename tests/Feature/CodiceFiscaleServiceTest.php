<?php

namespace Tests\Feature;

use App\Services\CodiceFiscaleService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class CodiceFiscaleServiceTest extends TestCase
{
  public function test_calcola_codice_fiscale_per_un_paziente_nato_in_italia(): void
  {
    $codiceFiscale = CodiceFiscaleService::calcola(
      'Rossi',
      'Mario',
      CarbonImmutable::parse('1980-01-01'),
      'M',
      'Roma',
    );

    $this->assertSame('RSSMRA80A01H501U', $codiceFiscale);
  }

  public function test_calcola_normalizza_il_luogo_di_nascita(): void
  {
    $codiceFiscale = CodiceFiscaleService::calcola(
      'D\'Amico',
      'Giulia',
      CarbonImmutable::parse('1992-06-14'),
      'F',
      ' reggio calàbria ',
    );

    $this->assertSame(16, strlen($codiceFiscale));
    $this->assertSame('H224', substr($codiceFiscale, 11, 4));
  }

  public function test_calcola_codice_fiscale_per_comune_presente_nel_dataset_completo(): void
  {
    $codiceFiscale = CodiceFiscaleService::calcola(
      'Bianchi',
      'Elena',
      CarbonImmutable::parse('1995-08-03'),
      'F',
      'Misiliscemi',
    );

    $this->assertSame('M432', substr($codiceFiscale, 11, 4));
    $this->assertSame(16, strlen($codiceFiscale));
  }

  public function test_calcola_lancia_eccezione_se_il_luogo_non_esiste(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Luogo di nascita non trovato.');

    CodiceFiscaleService::calcola(
      'Rossi',
      'Mario',
      CarbonImmutable::parse('1980-01-01'),
      'M',
      'Atlantide',
    );
  }
}
