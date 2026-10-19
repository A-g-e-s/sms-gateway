<?php declare(strict_types=1);

namespace Ages\SmsGateway\Gateway;

use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;

interface ProviderInterface
{

    /**
     * Chyby poskytovatele i sítě vrací v SendResult (status Rejected / Failed), nevyhazuje je.
     */
    public function send(SmsMessage $message): SendResult;

}
