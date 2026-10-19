<?php declare(strict_types=1);

namespace Ages\SmsGateway;

use Ages\SmsGateway\Config\SmsManagerConfig;
use Ages\SmsGateway\Entity\Enum\Provider;
use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;
use Ages\SmsGateway\Exception\GatewayInitializationException;
use Ages\SmsGateway\Gateway\ProviderInterface;
use Ages\SmsGateway\Gateway\SmsManager\SmsManagerProvider;
use Ages\SmsGateway\Exception\InvalidPhoneNumberException;

/**
 * DI vstupní bod – provider(Provider) vrátí ProviderInterface nakonfigurovaného poskytovatele,
 * send() odešle přes výchozího (první nakonfigurovaný).
 *
 * Klienti se sestavují líně při prvním použití.
 */
class SmsGateway
{

    private SmsManagerProvider|null $smsManager = null;

    public function __construct(
        private readonly SmsManagerConfig|null $smsManagerConfig = null,
    ) {
    }

    public function provider(Provider $provider): ProviderInterface
    {
        return match ($provider) {
            Provider::SmsManager => $this->smsManager(),
        };
    }

    public function isConfigured(Provider $provider): bool
    {
        return match ($provider) {
            Provider::SmsManager => $this->smsManagerConfig !== null,
        };
    }

    public function send(SmsMessage $message, Provider|null $provider = null): SendResult
    {
        return $this->provider($provider ?? $this->defaultProvider())->send($message);
    }

    /**
     * Sestaví zprávu s výchozím regionem poskytovatele (předvolba pro čísla bez +420).
     *
     * @param array<string, string|int> $payload
     * @throws InvalidPhoneNumberException
     */
    public function message(string $text, string $phoneNumber, array $payload = [], bool $gsm7 = false): SmsMessage
    {
        return SmsMessage::of($text, $phoneNumber, $payload, $this->defaultRegion(), $gsm7);
    }

    public function smsManager(): SmsManagerProvider
    {
        if ($this->smsManagerConfig === null) {
            throw new GatewayInitializationException(sprintf('SMS provider %s is not configured', Provider::SmsManager->name));
        }

        return $this->smsManager ??= new SmsManagerProvider($this->smsManagerConfig);
    }

    private function defaultProvider(): Provider
    {
        foreach (Provider::cases() as $provider) {
            if ($this->isConfigured($provider)) {
                return $provider;
            }
        }

        throw new GatewayInitializationException('No SMS provider is configured');
    }

    private function defaultRegion(): string
    {
        return $this->smsManagerConfig->defaultRegion ?? 'CZ';
    }

}
