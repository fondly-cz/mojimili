<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Passport OAuth klíče (MCP server + API) uložené v databázi. Vždy existuje
 * nanejvýš jeden řádek – generuje ho admin v administraci. Privátní klíč je
 * šifrovaný a nikdy se neposílá do frontendu ($hidden).
 */
class PassportKey extends Model
{
    protected $fillable = [
        'private_key',
        'public_key',
    ];

    protected $hidden = [
        'private_key',
        'public_key',
    ];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
        ];
    }
}
