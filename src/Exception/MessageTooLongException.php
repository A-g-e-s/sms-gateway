<?php declare(strict_types=1);

namespace Ages\SmsGateway\Exception;

/** Zpráva vyžadovaná jako jedna SMS by se rozdělila na víc částí. */
class MessageTooLongException extends SmsGatewayException
{

    public function __construct(
        public readonly int $length,
        public readonly int $maxLength,
    ) {
        parent::__construct(sprintf('SMS has %d characters, a single SMS allows at most %d', $length, $maxLength));
    }

}
