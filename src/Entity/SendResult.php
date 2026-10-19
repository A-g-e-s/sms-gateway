<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity;

use Ages\SmsGateway\Entity\Enum\SendStatus;

final readonly class SendResult
{

    private function __construct(
        public SendStatus $status,
        /** ID requestu u poskytovatele (je i u odmítnuté zprávy). */
        public string|null $requestId = null,
        /** ID zprávy u poskytovatele (jen u přijaté zprávy). */
        public string|null $messageId = null,
        public string|null $errorCode = null,
        public string|null $errorMessage = null,
    ) {
    }

    public static function accepted(string|null $requestId, string $messageId): self
    {
        return new self(SendStatus::Accepted, $requestId, $messageId);
    }

    public static function rejected(string|null $requestId, string|null $errorCode = null, string|null $errorMessage = null): self
    {
        return new self(SendStatus::Rejected, $requestId, null, $errorCode, $errorMessage);
    }

    public static function failed(string $errorCode, string $errorMessage): self
    {
        return new self(SendStatus::Failed, null, null, $errorCode, $errorMessage);
    }

    public function isAccepted(): bool
    {
        return $this->status === SendStatus::Accepted;
    }

}
