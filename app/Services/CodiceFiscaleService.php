<?php

namespace App\Services;

use App\Support\ComuniItalianiCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CodiceFiscaleService
{
    private const MONTH_CODES = [
        1 => 'A',
        2 => 'B',
        3 => 'C',
        4 => 'D',
        5 => 'E',
        6 => 'H',
        7 => 'L',
        8 => 'M',
        9 => 'P',
        10 => 'R',
        11 => 'S',
        12 => 'T',
    ];

    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    private const EVEN_VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
    ];

    public static function calcola(
        string          $cognome,
        string          $nome,
        CarbonInterface $dataNascita,
        string          $sesso,
        string          $luogoNascita,
    ): string
    {
        $partial = self::codiceCognome($cognome)
            . self::codiceNome($nome)
            . $dataNascita->format('y')
            . self::MONTH_CODES[(int)$dataNascita->format('n')]
            . self::codiceGiorno($dataNascita, $sesso)
            . self::codiceLuogo($luogoNascita);

        return $partial . self::carattereDiControllo($partial);
    }

    private static function codiceCognome(string $cognome): string
    {
        return self::codiceAnagrafico($cognome);
    }

    private static function codiceAnagrafico(string $valore): string
    {
        $consonanti = self::estraiConsonanti($valore);
        $vocali = self::estraiVocali($valore);

        return str_pad(substr($consonanti . $vocali, 0, 3), 3, 'X');
    }

    private static function estraiConsonanti(string $valore): string
    {
        return preg_replace('/[AEIOU]/', '', self::normalizzaTesto($valore)) ?? '';
    }

    private static function normalizzaTesto(string $valore): string
    {
        return preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($valore))) ?? '';
    }

    private static function estraiVocali(string $valore): string
    {
        preg_match_all('/[AEIOU]/', self::normalizzaTesto($valore), $matches);

        return implode('', $matches[0]);
    }

    private static function codiceNome(string $nome): string
    {
        $consonanti = self::estraiConsonanti($nome);

        if (strlen($consonanti) >= 4) {
            return $consonanti[0] . $consonanti[2] . $consonanti[3];
        }

        return self::codiceAnagrafico($nome);
    }

    private static function codiceGiorno(CarbonInterface $dataNascita, string $sesso): string
    {
        $giorno = (int)$dataNascita->format('d');
        $normalizedGender = strtoupper(trim($sesso));

        if (!in_array($normalizedGender, ['M', 'F'], true)) {
            throw new InvalidArgumentException('Sesso non valido.');
        }

        if ($normalizedGender === 'F') {
            $giorno += 40;
        }

        return str_pad((string)$giorno, 2, '0', STR_PAD_LEFT);
    }

    private static function codiceLuogo(string $luogoNascita): string
    {
        $normalizedPlace = self::normalizzaLuogo($luogoNascita);
        $code = ComuniItalianiCatalog::codeFor($normalizedPlace);

        if ($code === null) {
            throw new InvalidArgumentException('Luogo di nascita non trovato.');
        }

        return $code;
    }

    private static function normalizzaLuogo(string $valore): string
    {
        $normalized = Str::upper(Str::ascii($valore));
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private static function carattereDiControllo(string $partial): string
    {
        $sum = 0;

        foreach (str_split($partial) as $index => $character) {
            $position = $index + 1;
            $sum += $position % 2 === 0
                ? self::EVEN_VALUES[$character]
                : self::ODD_VALUES[$character];
        }

        return chr(($sum % 26) + ord('A'));
    }
}
