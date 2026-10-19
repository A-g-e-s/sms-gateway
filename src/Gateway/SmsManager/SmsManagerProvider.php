<?php declare(strict_types=1);

namespace Ages\SmsGateway\Gateway\SmsManager;

use Ages\SmsGateway\Config\SmsManagerConfig;
use Ages\SmsGateway\Entity\SendResult;
use Ages\SmsGateway\Entity\SmsMessage;
use Ages\SmsGateway\Exception\GatewayInitializationException;
use Ages\SmsGateway\Gateway\ProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * SmsManager REST API v2 – POST /message.
 *
 * Request:  {body, to: [{phone_number}], payload?: {…}}
 * Response: {request_id, accepted: [{message_id, …}], rejected: [{…}]}
 */
class SmsManagerProvider implements ProviderInterface
{

    private const string MethodSend = 'message';

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
