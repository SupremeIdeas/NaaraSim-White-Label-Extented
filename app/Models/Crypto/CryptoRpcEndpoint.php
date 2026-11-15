<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** An RPC endpoint. The URL usually carries an API key, so it is encrypted at rest and never logged or shown in full. */
class CryptoRpcEndpoint extends Model
{
    protected $table = 'crypto_rpc_endpoints';

    protected $fillable = ['chain_id', 'role', 'url_encrypted', 'enabled', 'last_ok_at', 'last_error', 'last_head'];

    protected $hidden = ['url_encrypted'];

    protected function casts(): array
    {
        return ['url_encrypted' => 'encrypted', 'enabled' => 'boolean', 'last_ok_at' => 'datetime'];
    }

    /** Host only, for the admin screen and logs. */
    public function maskedUrl(): string
    {
        $host = parse_url((string) $this->url_encrypted, PHP_URL_HOST);

        return $host ? $host.'/***' : '***';
    }
}
