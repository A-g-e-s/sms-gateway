# ages/sms-gateway

Jednotné API pro odesílání SMS. Zatím podporuje **SmsManager** (REST API v2, `https://api.smsmngr.com/v2/`). Aplikace pracuje jen s entitami balíčku — přidání dalšího poskytovatele nevyžaduje změnu aplikačního kódu.

Balíček jen odesílá. Evidenci zpráv (outbox, stavy, vazbu na objednávku) si vede aplikace.

## Požadavky

- PHP 8.4+
- `guzzlehttp/guzzle` ^7.9
- `brick/phonenumber` ^0.8

## Konfigurace (Nette DI / NEON)

```neon
services:
    smsManagerConfig: Ages\SmsGateway\Config\SmsManagerConfig(
        apiKey: 'api-klic-ze-smsmanageru'
        # defaultRegion: CZ                       # výchozí hodnota – předvolba pro čísla bez +420
        # apiUrl: https://api.smsmngr.com/v2/     # výchozí hodnota
        # timeout: 30                             # výchozí hodnota
    )

    - Ages\SmsGateway\SmsGateway
```

Konfigurace poskytovatele je **volitelná** — `SmsGateway` autowire dle přítomných typů v kontejneru a klienta sestaví líně až při prvním odeslání. Prázdný `apiKey` hodí `GatewayInitializationException` až při použití.

---

## Použití

### Odeslání SMS

```php
use Ages\SmsGateway\Exception\InvalidPhoneNumberException;
use Ages\SmsGateway\Exception\MessageTooLongException;

try {
    $message = $this->smsGateway->message(
        text:        'Ahoj Jiří, děkujeme za objednávku…',
        phoneNumber: $order->invoicePhone,          // '777 123 456', '+420777123456', …
        payload:     ['sms_outbox_id' => $sms->id], // volitelné – poskytovatel ho vrací ve webhooku
        gsm7:        true,                          // převést na GSM 7-bit (bez diakritiky)
        single:      true,                          // volitelné – musí odejít jako 1 SMS
    );
} catch (InvalidPhoneNumberException $e) {
    // neplatné číslo – API se vůbec nevolalo
} catch (MessageTooLongException $e) {
    // text by se rozdělil na víc SMS ($e->length / $e->maxLength) – API se vůbec nevolalo
}

$res = $this->smsGateway->send($message);

if ($res->isAccepted()) {
    $sms->requestId = $res->requestId;
    $sms->messageId = $res->messageId;
} else {
    // $res->status   → SendStatus::Rejected / Failed
    // $res->errorCode, $res->errorMessage
}
```

`send()` použije výchozího (první nakonfigurovaný) poskytovatele. Konkrétního zvolíš enumem:

```php
use Ages\SmsGateway\Entity\Enum\Provider;

$this->smsGateway->provider(Provider::SmsManager)->send($message);
// nebo
$this->smsGateway->send($message, Provider::SmsManager);
```

Zprávu lze sestavit i bez gateway: `SmsMessage::of($text, $phone, $payload, defaultRegion: 'SK', gsm7: true, single: true)`.

### Stavy odeslání (`SendStatus` enum)

| Hodnota    | Význam                                                               | `requestId` | `messageId` |
|------------|----------------------------------------------------------------------|-------------|-------------|
| `Accepted` | poskytovatel zprávu přijal k odeslání                                | ano         | ano         |
| `Rejected` | request prošel, ale zprávu odmítl (číslo, kredit, …)                 | ano         | —           |
| `Failed`   | request selhal (síť `transport`, `401`/`403` klíč, `5xx`, `invalid_response`) | —  | —           |

Chyby poskytovatele ani sítě **nevyhazují výjimku** — vždy se vrátí `SendResult`.

### Text a segmenty (GSM 7-bit)

```php
use Ages\SmsGateway\Text\Gsm7;

Gsm7::convert('Žluťoučký kůň “úpěl”'); // 'Zlutoucky kun "upel"' – diakritika a typografické znaky pryč
Gsm7::isGsm7($text);                    // false = pošle se v UCS-2 (jen 70 znaků / SMS)
Gsm7::segments($text);                  // počet účtovaných SMS
Gsm7::isSingle($text);                  // true = odejde jako 1 SMS
Gsm7::singleLength($text);              // limit 1 SMS pro tento text (160 / 70)
$message->segments();
```

| Kódování   | 1 SMS                            | vícedílná SMS                         |
|------------|----------------------------------|---------------------------------------|
| GSM 7-bit  | 160 znaků (`Gsm7::SingleLength`) | 153 / segment (`Gsm7::MultiLength`)  |
| UCS-2      | 70 znaků (`Gsm7::SingleLengthUcs2`) | 67 / segment (`Gsm7::MultiLengthUcs2`) |

Znaky rozšířené tabulky (`^{}\[~]|€`) zabírají 2 místa. Limity odpovídají [SmsManageru](https://smsmanager.cz/tool/delka-sms); API přijme až 1000 znaků a delší text rozdělí.

---

## Struktury

### `SmsMessage`

| Property      | Typ                     | Popis                                   |
|---------------|-------------------------|-----------------------------------------|
| `text`        | `string`                | text zprávy                             |
| `phoneNumber` | `string`                | číslo v E.164 (`+420777123456`)          |
| `payload`     | `array<string, string>` | vlastní data pro webhook poskytovatele  |

### `SendResult`

| Property       | Typ            | Popis                                  |
|----------------|----------------|----------------------------------------|
| `status`       | `SendStatus`   |                                        |
| `requestId`    | `string\|null` | ID requestu u poskytovatele            |
| `messageId`    | `string\|null` | ID zprávy u poskytovatele              |
| `errorCode`    | `string\|null` | HTTP kód / kód odmítnutí / `transport` |
| `errorMessage` | `string\|null` |                                        |

---

## Výjimky

Všechny rozšiřují `Ages\SmsGateway\Exception\SmsGatewayException`.

| Třída                            | Kdy nastane                                                    |
|----------------------------------|----------------------------------------------------------------|
| `GatewayInitializationException` | poskytovatel není nakonfigurován / chybí `apiKey`              |
| `InvalidPhoneNumberException`    | telefonní číslo nejde rozparsovat nebo není možné              |
| `MessageTooLongException`        | `single: true` a text by se rozdělil na víc SMS (`$length`, `$maxLength`) |

---

## Architektura

```
src/
├── Config/
│   └── SmsManagerConfig.php          konfigurace SmsManager
├── Entity/
│   ├── Enum/
│   │   ├── Provider.php              výběr poskytovatele (SmsManager)
│   │   └── SendStatus.php            Accepted / Rejected / Failed
│   ├── SmsMessage.php                vstupní entita (normalizace čísla, GSM 7)
│   └── SendResult.php
├── Exception/
│   ├── SmsGatewayException.php
│   ├── GatewayInitializationException.php
│   ├── InvalidPhoneNumberException.php
│   └── MessageTooLongException.php
├── Gateway/
│   ├── ProviderInterface.php         send(SmsMessage): SendResult
│   └── SmsManager/
│       └── SmsManagerProvider.php    POST /message, hlavička x-api-key
├── Text/
│   └── Gsm7.php                      převod do GSM 7-bit, počítání segmentů
└── SmsGateway.php                    DI entry point – provider(Provider), send(), message()
```
