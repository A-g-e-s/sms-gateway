<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

use Ages\SmsGateway\Exception\InvalidPhoneNumberException;
use Ages\SmsGateway\Exception\MessageTooLongException;
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
     * @param bool $single true = zpráva musí odejít jako jedna SMS (160 znaků GSM 7-bit / 70 UCS-2), jinak výjimka
     * @throws InvalidPhoneNumberException
     * @throws MessageTooLongException
     */
    public static function of(
        string $text,
        string $phoneNumber,
        array $payload = [],
        string $defaultRegion = 'CZ',
        bool $gsm7 = false,
        bool $single = false,
    ): self {
        if ($gsm7) {
            $text = Gsm7::convert($text);
        }
        if ($single && !Gsm7::isSingle($text)) {
            throw new MessageTooLongException(Gsm7::length($text), Gsm7::singleLength($text));
        }

        try {
            $number = PhoneNumber::parse($phoneNumber, $defaultRegion);
        } catch (PhoneNumberParseException $e) {
            throw new InvalidPhoneNumberException(sprintf('Invalid phone number "%s": %s', $phoneNumber, $e->getMessage()), 0, $e);
        }

        if (!$number->isPossibleNumber()) {
            throw new InvalidPhoneNumberException(sprintf('Invalid phone number "%s"', $phoneNumber));
        }

        return new self(
            $text,
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
