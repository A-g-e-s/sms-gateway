<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

use DateTimeImmutable;

/** Útrata a počty zpráv za jeden den. */
final readonly class SpendDay
{

    public function __construct(
        public DateTimeImmutable $date,
        /** Počet odeslaných zpráv (účtovaných částí). */
        public int $sent,
        public int $delivered,
        public int $notDelivered,
        /** Zprávy, které se vůbec neodeslaly. */
        public int $notSent,
        public float $priceCzk,
        public float $priceEur,
    ) {
    }

}
