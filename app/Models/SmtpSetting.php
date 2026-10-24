<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SMTP server nastavený v administraci (nanejvýš jeden řádek). Je-li aktivní,
 * přebíjí MAIL_* z .env. Heslo je šifrované a nikdy se neposílá do frontendu.
 */
class SmtpSetting extends Model
{
    protected $fillable = [
        'enabled',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /**
     * Přepíše mail config hodnotami z administrace a zahodí už vytvořený
     * transport, aby se příští odeslání připojilo k novému serveru.
     */
    public function applyToConfig(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => $this->port,
            // 'smtps' = implicitní TLS (SSL); jinak Symfony samo zapne STARTTLS, je-li dostupné.
            'mail.mailers.smtp.scheme' => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $this->password,
            'mail.from.address' => $this->from_address,
            'mail.from.name' => $this->from_name ?: config('mail.from.name'),
        ]);

        app('mail.manager')->purge('smtp');
    }

    public static function active(): ?self
    {
        return static::query()->where('enabled', true)->first();
    }
}
