<?php

namespace App\Actions;

use App\Models\PassportKey;
use phpseclib3\Crypt\RSA;

/**
 * Vygeneruje nový RSA pár pro Passport a uloží ho jako jediný řádek v tabulce
 * passport_keys. Stejný postup jako `php artisan passport:keys` (phpseclib),
 * jen výsledek putuje do DB místo na disk. Znovuvygenerování odpojí stávající
 * MCP klienty – vydané tokeny se starým klíčem už neověří.
 */
class GeneratePassportKeys
{
    public function generate(int $length = 4096): PassportKey
    {
        $key = RSA::createKey($length);

        $keys = PassportKey::query()->firstOrNew([]);
        $keys->private_key = (string) $key;
        $keys->public_key = (string) $key->getPublicKey();
        $keys->save();

        return $keys;
    }
}
