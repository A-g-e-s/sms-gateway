<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

/** Aktuální stav kreditu u poskytovatele. */
final readonly class CreditBalance
{

    public function __construct(
        /** Zůstatek v měně účtu ($currency). */
        public float $amount,
        /** Měna účtu (ISO 4217, např. CZK). */
        public string $currency,
        public float $amountCzk,
        public float $amountEur,
        /** Účet je plátcem DPH. */
        public bool $vatPayer = false,
        /** Časová zóna účtu (např. Europe/Prague). */
        public string|null $timezone = null,
    ) {
    }

}
