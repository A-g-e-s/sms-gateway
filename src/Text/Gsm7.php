<?php declare(strict_types=1);

namespace Ages\SmsGateway\Text;

/**
 * Převod textu do znakové sady GSM 03.38 (7-bit) a počítání segmentů SMS.
 *
 * Zpráva v GSM 7-bit má 160 znaků (vícedílná 153 / segment); jakmile obsahuje
 * jediný znak mimo sadu (např. diakritiku), posílá se v UCS-2 jen se 70 (67) znaky.
 */
final class Gsm7
{

    public const array MAP = [
        'Á' => 'A',
        'Ä' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'á' => 'a',
        'ä' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'Č' => 'C',
        'Ć' => 'C',
        'č' => 'c',
        'ć' => 'c',
        'Ď' => 'D',
        'ď' => 'd',
        'É' => 'E',
        'Ě' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'é' => 'e',
        'ě' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'Ň' => 'N',
        'Ñ' => 'N',
        'ň' => 'n',
        'ñ' => 'n',
        'Ó' => 'O',
        'Ö' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'ó' => 'o',
        'ö' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'Ř' => 'R',
        'ř' => 'r',
        'Š' => 'S',
        'Ś' => 'S',
        'š' => 's',
        'ś' => 's',
        'Ť' => 'T',
        'ť' => 't',
        'Ú' => 'U',
        'Ů' => 'U',
        'Ü' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'ú' => 'u',
        'ů' => 'u',
        'ü' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'Ý' => 'Y',
        'ý' => 'y',
        'Ž' => 'Z',
        'Ź' => 'Z',
        'Ż' => 'Z',
        'ž' => 'z',
        'ź' => 'z',
        'ż' => 'z',

        '„' => '"',
        '“' => '"',
        '”' => '"',
        '‹' => "'",
        '›' => "'",
        '‘' => "'",
        '’' => "'",
        '—' => '-',
        '–' => '-',
        '−' => '-',
        '…' => '...',

        "\u{00A0}" => ' ',
        "\t" => ' ',

        '€' => 'EUR',
        '™' => ' TM',
        '©' => '(c)',
        '®' => '(R)',
        '°' => ' deg',
    ];

    public const string DEFAULT_ALPHABET = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Znaky rozšířené tabulky – každý zabírá 2 septety (escape + znak). */
    public const string EXTENSION_ALPHABET = "^{}\[~]|€";

    /** Text bez diakritiky a znaků mimo GSM 7-bit (nepřevoditelné znaky se zahodí). */
    public static function convert(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $s = strtr($text, self::MAP);
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        $out = [];
        foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
            if (self::isGsm7Char($ch)) {
                $out[] = $ch;
            }
        }

        $res = implode('', $out);
        $res = (string) (preg_replace('~[ \t]+~u', ' ', $res) ?? $res);
        $res = (string) (preg_replace('~ *\n *~u', "\n", $res) ?? $res);

        return trim($res);
    }

    public static function isGsm7(string $text): bool
    {
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            if (!self::isGsm7Char($ch)) {
                return false;
            }
        }

        return true;
    }

    /** Počet znaků (septetů u GSM 7-bit, UTF-16 jednotek u UCS-2), jak je účtuje operátor. */
    public static function length(string $text): int
    {
        if (!self::isGsm7($text)) {
            return intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);
        }

        $length = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $length += str_contains(self::EXTENSION_ALPHABET, $ch) ? 2 : 1;
        }

        return $length;
    }

    /** Počet SMS segmentů, na které se text rozdělí. */
    public static function segments(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        [$single, $multi] = self::isGsm7($text) ? [160, 153] : [70, 67];
        $length = self::length($text);

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    private static function isGsm7Char(string $ch): bool
    {
        return str_contains(self::DEFAULT_ALPHABET, $ch) || str_contains(self::EXTENSION_ALPHABET, $ch);
    }

}
