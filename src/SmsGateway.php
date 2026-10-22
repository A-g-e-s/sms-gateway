<?php declare(strict_types=1);

namespace Ages\SmsGateway;

use Ages\SmsGateway\Config\SmsManagerConfig;
use Ages\SmsGateway\Entity\CreditBalance;
use Ages\SmsGateway\Entity\Enum\Provider;
use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;
use Ages\SmsGateway\Entity\Spend;
use Ages\SmsGateway\Exception\GatewayInitializationException;
use Ages\SmsGateway\Exception\ProviderException;
use DateTimeInterface;
use Ages\SmsGateway\Gateway\ProviderInterface;
use Ages\SmsGateway\Gateway\SmsManager\SmsManagerProvider;
use Ages\SmsGateway\Exception\InvalidPhoneNumberException;
use Ages\SmsGateway\Exception\MessageTooLongException;

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
     * Aktuální stav kreditu u poskytovatele.
     *
     * @throws ProviderException
     */
    public function balance(Provider|null $provider = null): CreditBalance
    {
        return $this->provider($provider ?? $this->defaultProvider())->balance();
    }

    /**
     * Útrata za období (včetně obou krajních dnů) – součty a rozpad po dnech.
     *
     * @throws ProviderException
     */
    public function spend(DateTimeInterface $from, DateTimeInterface $to, Provider|null $provider = null): Spend
    {
        return $this->provider($provider ?? $this->defaultProvider())->spend($from, $to);
    }

    /**
     * Sestaví zprávu s výchozím regionem poskytovatele (předvolba pro čísla bez +420).
     *
     * @param array<string, string|int> $payload
     * @throws InvalidPhoneNumberException
     * @throws MessageTooLongException
     */
    public function message(string $text, string $phoneNumber, array $payload = [], bool $gsm7 = false, bool $single = false): SmsMessage
    {
        return SmsMessage::of($text, $phoneNumber, $payload, $this->defaultRegion(), $gsm7, $single);
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
