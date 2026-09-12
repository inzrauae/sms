<?php

namespace App\Services;

/**
 * Message encoding + segment maths.
 *
 * Billing depends entirely on this class, so it follows GSM 03.38 rather than
 * approximating with strlen(). Getting it wrong means you either eat the cost
 * of extra segments or overcharge your customers.
 */
class Sms
{
    // GSM 03.38 basic character set. Every character here costs 1 septet.
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        . "¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    // Extension table. Each of these costs 2 septets (escape + character).
    private const GSM_EXTENDED = '^{}\\[~]|€';

    public const LIMITS = [
        'gsm' => ['single' => 160, 'multi' => 153],
        'unicode' => ['single' => 70, 'multi' => 67],
    ];

    private static ?array $basicSet = null;
    private static ?array $extendedSet = null;

    private static function basicSet(): array
    {
        return self::$basicSet ??= array_fill_keys(mb_str_split(self::GSM_BASIC), true);
    }

    private static function extendedSet(): array
    {
        return self::$extendedSet ??= array_fill_keys(mb_str_split(self::GSM_EXTENDED), true);
    }

    /** True when every character can be carried by the 7-bit GSM alphabet. */
    public static function isGsm7(string $text): bool
    {
        $basic = self::basicSet();
        $extended = self::extendedSet();

        foreach (mb_str_split($text) as $char) {
            if (!isset($basic[$char]) && !isset($extended[$char])) {
                return false;
            }
        }

        return true;
    }

    /** Length of the message in septets, counting extended characters twice. */
    private static function gsm7Length(string $text): int
    {
        $extended = self::extendedSet();
        $length = 0;

        foreach (mb_str_split($text) as $char) {
            $length += isset($extended[$char]) ? 2 : 1;
        }

        return $length;
    }

    /**
     * Work out encoding, length and segment count for a message body.
     * Sinhala and Tamil both fall outside GSM-7, so they bill as unicode at
     * 70/67 characters per segment. Warn your users about that in the composer.
     */
    public static function analyse(string $text): array
    {
        $gsm = self::isGsm7($text);
        $encoding = $gsm ? 'gsm' : 'unicode';
        $limits = self::LIMITS[$encoding];

        // Unicode length is counted in UTF-16 code units to match the
        // composer's client-side counter and Text.lk's own accounting.
        $length = $gsm ? self::gsm7Length($text) : self::utf16Length($text);

        if ($length === 0) {
            $segments = 0;
        } elseif ($length <= $limits['single']) {
            $segments = 1;
        } else {
            $segments = (int) ceil($length / $limits['multi']);
        }

        $capacity = $segments <= 1 ? $limits['single'] : $limits['multi'] * $segments;

        return [
            'encoding' => $encoding,
            'type' => $gsm ? 'plain' : 'unicode', // maps to the Text.lk `type` parameter
            'length' => $length,
            'segments' => $segments,
            'capacity' => $capacity,
            'remaining' => $capacity - $length,
        ];
    }

    /** An emoji outside the BMP is a surrogate pair in UTF-16 and eats two. */
    private static function utf16Length(string $text): int
    {
        $length = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $length += mb_ord($char, 'UTF-8') > 0xFFFF ? 2 : 1;
        }

        return $length;
    }

    /** Credits consumed by one send: segments x recipients. */
    public static function unitsFor(string $text, int $recipientCount = 1): int
    {
        $segments = self::analyse($text)['segments'];

        return max($segments, 1) * max($recipientCount, 1);
    }

    /**
     * Normalise a Sri Lankan number to the 94XXXXXXXXX form Text.lk expects.
     * Accepts 0712345678, +94712345678, 94 71 234 5678, etc. Numbers that
     * already carry another country code are passed through with
     * punctuation stripped.
     */
    public static function normaliseNumber(?string $input, string $defaultCountry = '94'): ?string
    {
        if ($input === null) {
            return null;
        }

        $n = preg_replace('/[\s\-().]/', '', trim($input));
        if (str_starts_with($n, '+')) {
            $n = substr($n, 1);
        }
        if (!preg_match('/^\d+$/', $n)) {
            return null;
        }

        if (str_starts_with($n, '00')) {
            $n = substr($n, 2);
        }
        if (str_starts_with($n, '0')) {
            $n = $defaultCountry . substr($n, 1);
        } elseif (strlen($n) === 9) {
            $n = $defaultCountry . $n; // 712345678
        }

        if (strlen($n) < 9 || strlen($n) > 15) {
            return null;
        }

        return $n;
    }

    /**
     * Split and clean a recipient list.
     *
     * @return array{valid: string[], invalid: string[]}
     */
    public static function parseRecipients(string|array $input, string $defaultCountry = '94'): array
    {
        $raw = is_array($input) ? $input : preg_split('/[,\n;]+/', (string) $input);

        $valid = [];
        $invalid = [];
        $seen = [];

        foreach ($raw as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') {
                continue;
            }

            $number = self::normaliseNumber($trimmed, $defaultCountry);
            if (!$number) {
                $invalid[] = $trimmed;
                continue;
            }
            if (isset($seen[$number])) {
                continue; // never bill twice for the same number
            }
            $seen[$number] = true;
            $valid[] = $number;
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /** Sender IDs are alphanumeric, 11 characters maximum, or a plain number. */
    public static function validateSenderMask(?string $mask): array
    {
        $value = trim((string) $mask);
        if ($value === '') {
            return ['ok' => false, 'error' => 'Enter a sender name.'];
        }
        if (preg_match('/^\d{6,15}$/', $value)) {
            return ['ok' => true, 'value' => $value];
        }
        if (mb_strlen($value) > 11) {
            return ['ok' => false, 'error' => 'Sender names are limited to 11 characters.'];
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $value)) {
            return ['ok' => false, 'error' => 'Use letters, numbers, spaces, dots, hyphens or underscores.'];
        }

        return ['ok' => true, 'value' => $value];
    }
}
