<?php declare(strict_types=1);

namespace Ages\SmsGateway\Config;

/**
 * Konfigurace SmsManager – JSON API v2 pro odesílání (https://api.smsmngr.com/v2/)
 * a REST API v1 pro účet: kredit, statistiky (https://rest-api.smsmngr.com/v1/).
 *
 * API klíč se posílá v hlavičce `x-api-key`.
 */
readonly class SmsManagerConfig
{

    public const string ApiUrl = 'https://api.smsmngr.com/v2/';

    public const string RestApiUrl = 'https://rest-api.smsmngr.com/v1/';

    public function __construct(
        public string $apiKey,
        /** Region (ISO 3166-1 alpha-2) pro čísla zadaná bez mezinárodní předvolby. */
        public string $defaultRegion = 'CZ',
        public string $apiUrl = self::ApiUrl,
        public string $restApiUrl = self::RestApiUrl,
        /** Timeout requestu v sekundách. */
        public int $timeout = 30,
    ) {
    }

}
