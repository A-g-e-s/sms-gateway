<?php declare(strict_types=1);

namespace Ages\SmsGateway\Gateway\SmsManager;

use Ages\SmsGateway\Config\SmsManagerConfig;
use Ages\SmsGateway\Entity\CreditBalance;
use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;
use Ages\SmsGateway\Entity\Spend;
use Ages\SmsGateway\Entity\SpendDay;
use Ages\SmsGateway\Exception\GatewayInitializationException;
use Ages\SmsGateway\Exception\ProviderException;
use Ages\SmsGateway\Gateway\ProviderInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * SmsManager – odesílání přes JSON API v2 (POST /message), účet přes REST API v1.
 *
 * Request:  {body, to: [{phone_number}], payload?: {…}}
 * Response: {request_id, accepted: [{message_id, …}], rejected: [{…}]}
 *
 * REST v1:  GET /credit                    → {credit: {credit_czk, credit_eur, currency, vat, tz}}
 *           GET /requests?dates=Y-m-d,…    → {requests: [{date, stats: {msg_sent, price_czk, price_eur, …}}]}
 *           (price_* v /requests není v dokumentaci, ale API je vrací a sedí se součtem GET /messages)
 */
class SmsManagerProvider implements ProviderInterface
{

    private const string MethodSend = 'message';

    private const string MethodCredit = 'credit';

    private const string MethodRequests = 'requests';

    /** Počet dní v jednom dotazu na /requests (rok prošel ověřeně). */
    private const int MaxDatesPerRequest = 366;

    private Client $httpClient;

    public function __construct(
        private readonly SmsManagerConfig $config,
        Client|null $httpClient = null,
    ) {
        if ($config->apiKey === '') {
            throw new GatewayInitializationException('SmsManager: apiKey is not set.');
        }

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($config->apiUrl, '/') . '/',
            'timeout' => $config->timeout,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): SendResult
    {
        $payload = [
            'body' => $message->text,
            'to' => [
                ['phone_number' => $message->digitsPhoneNumber()],
            ],
        ];
        if ($message->payload !== []) {
            $payload['payload'] = $message->payload;
        }

        try {
            $response = $this->httpClient->post(self::MethodSend, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $this->config->apiKey,
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            return SendResult::failed('transport', $e->getMessage());
        }

        $code = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if ($code === 401 || $code === 403) {
            return SendResult::failed((string) $code, 'Unauthorized – check SmsManager apiKey.');
        }

        if ($code < 200 || $code >= 300) {
            return SendResult::failed((string) $code, self::errorMessage($data) ?? sprintf('HTTP %d: %s', $code, mb_substr($body, 0, 500)));
        }

        if (!is_array($data)) {
            return SendResult::failed('invalid_response', sprintf('SmsManager returned invalid JSON: %s', mb_substr($body, 0, 500)));
        }

        $requestId = isset($data['request_id']) && is_scalar($data['request_id']) ? (string) $data['request_id'] : null;

        $accepted = $data['accepted'] ?? null;
        $first = is_array($accepted) ? ($accepted[0] ?? null) : null;
        if (is_array($first) && isset($first['message_id']) && is_scalar($first['message_id'])) {
            return SendResult::accepted($requestId, (string) $first['message_id']);
        }

        $rejected = $data['rejected'] ?? null;
        $reason = is_array($rejected) ? ($rejected[0] ?? null) : null;

        return SendResult::rejected(
            $requestId,
            is_array($reason) ? self::scalarField($reason, ['code', 'error_code', 'status']) : null,
            is_array($reason) ? self::errorMessage($reason) : null,
        );
    }

    public function balance(): CreditBalance
    {
        $credit = $this->restGet(self::MethodCredit)['credit'] ?? null;
        if (!is_array($credit)) {
            throw new ProviderException('SmsManager: credit response has no "credit" object.');
        }

        $currency = strtoupper(self::scalarField($credit, ['currency']) ?? 'czk');
        $czk = self::number($credit['credit_czk'] ?? null);
        $eur = self::number($credit['credit_eur'] ?? null);

        return new CreditBalance(
            amount: $currency === 'EUR' ? $eur : $czk,
            currency: $currency,
            amountCzk: $czk,
            amountEur: $eur,
            vatPayer: self::scalarField($credit, ['vat']) === '1',
            timezone: self::scalarField($credit, ['tz']),
        );
    }

    public function spend(DateTimeInterface $from, DateTimeInterface $to): Spend
    {
        $start = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $end = DateTimeImmutable::createFromInterface($to)->setTime(0, 0);
        if ($start > $end) {
            throw new \InvalidArgumentException('SmsManager: spend period start is after its end.');
        }

        $dates = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $dates[] = $day->format('Y-m-d');
        }

        $days = [];
        foreach (array_chunk($dates, self::MaxDatesPerRequest) as $chunk) {
            $requests = $this->restGet(self::MethodRequests, ['dates' => implode(',', $chunk)])['requests'] ?? [];
            foreach (is_array($requests) ? $requests : [] as $row) {
                if (is_array($row)) {
                    $days[] = self::spendDay($row, $start->getTimezone());
                }
            }
        }
        usort($days, static fn (SpendDay $a, SpendDay $b): int => $a->date <=> $b->date);

        return new Spend($start, $end, $days);
    }

    /**
     * @param array<mixed> $row
     */
    private static function spendDay(array $row, DateTimeZone $timezone): SpendDay
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', self::scalarField($row, ['date']) ?? '', $timezone);
        $stats = $row['stats'] ?? null;
        if ($date === false || !is_array($stats)) {
            throw new ProviderException('SmsManager: invalid row in /requests response.');
        }
        if (!isset($stats['price_czk'])) {
            // Cena není v dokumentaci /requests – když ji API přestane vracet, nesmí to tiše vrátit nulu.
            throw new ProviderException(sprintf('SmsManager: /requests returned no price for %s.', $date->format('Y-m-d')));
        }

        return new SpendDay(
            date: $date,
            sent: (int) self::number($stats['msg_sent'] ?? null),
            delivered: (int) self::number($stats['count_delivered'] ?? null),
            notDelivered: (int) self::number($stats['count_nondelivered'] ?? null),
            notSent: (int) self::number($stats['count_notsent'] ?? null),
            priceCzk: self::number($stats['price_czk']),
            priceEur: self::number($stats['price_eur'] ?? null),
        );
    }

    /**
     * GET na REST API v1. Na rozdíl od send() chyby vyhazuje.
     *
     * @param array<string, string> $query
     * @return array<mixed>
     * @throws ProviderException
     */
    private function restGet(string $method, array $query = []): array
    {
        try {
            $response = $this->httpClient->get($this->config->restApiUrl . $method, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $this->config->apiKey,
                ],
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            throw new ProviderException(sprintf('SmsManager: request %s failed: %s', $method, $e->getMessage()), null, $e);
        }

        $code = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if ($code === 401 || $code === 403) {
            throw new ProviderException('SmsManager: unauthorized – check apiKey.', $code);
        }

        if ($code < 200 || $code >= 300) {
            throw new ProviderException(self::errorMessage($data) ?? sprintf('SmsManager: HTTP %d: %s', $code, mb_substr($body, 0, 500)), $code);
        }

        if (!is_array($data) || ($data['success'] ?? true) === false) {
            throw new ProviderException(self::errorMessage($data) ?? sprintf('SmsManager: invalid response: %s', mb_substr($body, 0, 500)), $code);
        }

        return $data;
    }

    /** Číslo i číselný řetězec ("1035.770000…") → float zaokrouhlený na haléře. */
    private static function number(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private static function errorMessage(mixed $data): string|null
    {
        return is_array($data) ? self::scalarField($data, ['message', 'reason', 'error', 'detail']) : null;
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $keys
     */
    private static function scalarField(array $data, array $keys): string|null
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }

}
