<?php declare(strict_types=1);

namespace Ages\SmsGateway\Entity\Enum;

enum SendStatus
{
    /** Poskytovatel zprávu přijal k odeslání. */
    case Accepted;
    /** Poskytovatel request zpracoval, ale zprávu odmítl (neplatné číslo, kredit, …). */
    case Rejected;
    /** Request se nepodařilo zpracovat (síť, autorizace, chyba API). */
    case Failed;
}
