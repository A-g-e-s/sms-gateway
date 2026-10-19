<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

use Ages\SmsGateway\Exception\InvalidPhoneNumberException;
use Ages\SmsGateway\Text\Gsm7;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberFormat;
use Brick\PhoneNumber\PhoneNumberParseException;

/**
 * Vstupní entita pro odeslání SMS.
 *
 * Telefonní číslo se při vytvoření normalizuje do E.164 (+420777123456);
 * neplatné číslo hodí InvalidPhoneNumberException ještě před voláním API.
 */
final readonly class SmsMessage
{

    /**
     * @param array<string, string> $payload
     */
    private function __construct(
        public string $text,
        /** Číslo ve formátu E.164 (+420777123456). */
        public string $phoneNumber,
        /** Vlastní data vrácená poskytovatelem ve webhooku (např. ID záznamu v aplikaci). */
        public array $payload,
    ) {
    }

    /**
     * @param array<string, string|int> $payload
     * @param bool $gsm7 true = text převést do GSM 7-bit (bez diakritiky) – levnější a delší segmenty
     * @throws InvalidPhoneNumberException
     */
    public static function of(
        string $text,
        string $phoneNumber,
        array $payload = [],
        string $defaultRegion = 'CZ',
        bool $gsm7 = false,
    ): self {
        try {
            $number = PhoneNumber::parse($phoneNumber, $defaultRegion);
        } catch (PhoneNumberParseException $e) {
            throw new InvalidPhoneNumberException(sprintf('Invalid phone number "%s": %s', $phoneNumber, $e->getMessage()), 0, $e);
        }

        if (!$number->isPossibleNumber()) {
            throw new InvalidPhoneNumberException(sprintf('Invalid phone number "%s"', $phoneNumber));
        }

        return new self(
            $gsm7 ? Gsm7::convert($text) : $text,
            $number->format(PhoneNumberFormat::E164),
            array_map(static fn(string|int $v): string => (string) $v, $payload),
        );
    }

    /** Číslo jen z číslic (420777123456). */
    public function digitsPhoneNumber(): string
    {
        return ltrim($this->phoneNumber, '+');
    }

    public function segments(): int
    {
        return Gsm7::segments($this->text);
    }

}
