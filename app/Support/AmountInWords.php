<?php

namespace App\Support;

class AmountInWords
{
    private const ONES = [
        '', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE',
        'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN',
        'SEVENTEEN', 'EIGHTEEN', 'NINETEEN',
    ];

    private const TENS = [
        '', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY',
    ];

    /**
     * Convert a peso amount to words, e.g. "TEN THOUSAND FIFTY PESOS ONLY".
     */
    public static function peso(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        $pesos  = (int) floor($amount);
        $cents  = (int) round(($amount - $pesos) * 100);

        if ($pesos === 0 && $cents === 0) {
            return 'ZERO PESOS ONLY';
        }

        $words = self::chunk($pesos);

        if ($cents > 0) {
            $centWords = self::chunk($cents) . ' CENTAVO' . ($cents === 1 ? '' : 'S');

            return $pesos > 0
                ? trim($words) . ' PESOS AND ' . $centWords . ' ONLY'
                : $centWords . ' ONLY';
        }

        return trim($words) . ' PESOS ONLY';
    }

    private static function chunk(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $ten  = (int) floor($number / 10);
            $rest = $number % 10;

            return trim(self::TENS[$ten] . ($rest ? ' ' . self::ONES[$rest] : ''));
        }

        if ($number < 1000) {
            $hundred = (int) floor($number / 100);
            $rest    = $number % 100;

            return trim(self::ONES[$hundred] . ' HUNDRED' . ($rest ? ' ' . self::chunk($rest) : ''));
        }

        if ($number < 1_000_000) {
            $thousand = (int) floor($number / 1000);
            $rest     = $number % 1000;

            return trim(self::chunk($thousand) . ' THOUSAND' . ($rest ? ' ' . self::chunk($rest) : ''));
        }

        if ($number < 1_000_000_000) {
            $million = (int) floor($number / 1_000_000);
            $rest    = $number % 1_000_000;

            return trim(self::chunk($million) . ' MILLION' . ($rest ? ' ' . self::chunk($rest) : ''));
        }

        $billion = (int) floor($number / 1_000_000_000);
        $rest    = $number % 1_000_000_000;

        return trim(self::chunk($billion) . ' BILLION' . ($rest ? ' ' . self::chunk($rest) : ''));
    }
}
