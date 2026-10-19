<?php declare(strict_types=1);

namespace Ages\SmsGateway\Config;

/**
 * Konfigurace SmsManager REST API v2 (https://api.smsmngr.com/v2/).
 *
 * API klíč se posílá v hlavičce `x-api-key`.
 */
readonly class SmsManagerConfig
{

    public const string API_URL = 'https://api.smsmngr.com/v2/';

    public function __construct(
        public string $apiKey,
        /** Region (ISO 3166-1 alpha-2) pro čísla zadaná bez mezinárodní předvolby. */
        public string $defaultRegion = 'CZ',
        public string $apiUrl = self::API_URL,
        /** Timeout requestu v sekundách. */
        public int $timeout = 30,
    ) {
    }

}
