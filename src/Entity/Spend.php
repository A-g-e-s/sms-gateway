<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

use DateTimeImmutable;

/** Útrata za období – součty a rozpad po dnech (jen dny, kdy se něco posílalo). */
final readonly class Spend
{

    public int $sent;

    public int $delivered;

    public float $priceCzk;

    public float $priceEur;

    /**
     * @param list<SpendDay> $days seřazené podle data
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $days,
    ) {
        $sent = $delivered = 0;
        $priceCzk = $priceEur = 0.0;
        foreach ($days as $day) {
            $sent += $day->sent;
            $delivered += $day->delivered;
            $priceCzk += $day->priceCzk;
            $priceEur += $day->priceEur;
        }

        $this->sent = $sent;
        $this->delivered = $delivered;
        $this->priceCzk = round($priceCzk, 2);
        $this->priceEur = round($priceEur, 2);
    }

    /** Průměrná cena jedné odeslané zprávy v CZK. */
    public function averagePriceCzk(): float
    {
        return $this->sent > 0 ? round($this->priceCzk / $this->sent, 2) : 0.0;
    }

}
