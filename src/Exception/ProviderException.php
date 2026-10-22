<?php declare(strict_types=1);

namespace Ages\SmsGateway\Exception;

/** Dotaz na účet poskytovatele (kredit, útrata) selhal – síť, autorizace nebo neplatná odpověď. */
class ProviderException extends SmsGatewayException
{

    public function __construct(
        string $message,
        /** HTTP status, null u chyby sítě / neplatné odpovědi. */
        public readonly int|null $statusCode = null,
        \Throwable|null $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

}
