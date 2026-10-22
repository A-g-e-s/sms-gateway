<?php declare(strict_types=1);

namespace Ages\SmsGateway\Gateway;

use Ages\SmsGateway\Entity\CreditBalance;
use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;
use Ages\SmsGateway\Entity\Spend;
use Ages\SmsGateway\Exception\ProviderException;
use DateTimeInterface;

interface ProviderInterface
{

    /**
     * Chyby poskytovatele i sítě vrací v SendResult (status Rejected / Failed), nevyhazuje je.
     */
    public function send(SmsMessage $message): SendResult;

    /**
     * Aktuální stav kreditu.
     *
     * @throws ProviderException
     */
    public function balance(): CreditBalance;

    /**
     * Útrata za období (včetně obou krajních dnů, po dnech v časové zóně účtu).
     *
     * @throws ProviderException
     */
    public function spend(DateTimeInterface $from, DateTimeInterface $to): Spend;

}
