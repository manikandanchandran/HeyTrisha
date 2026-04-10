<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_url',
        'api_key_hash',
        'openai_key',
        'email',
        'username',
        'password',
        'first_name',
        'last_name',
        'db_name',
        'db_username',
        'db_password',
        'wordpress_version',
        'woocommerce_version',
        'plugin_version',
        'is_active',
        'query_count',
        'last_query_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'query_count' => 'integer',
        'last_query_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_hash',
        'openai_key',
        'password',
        'db_password',
    ];

    /**
     * Get decrypted OpenAI API key
     *
     * @return string
     */
    public function getOpenAIKey()
    {
        try {
            return Crypt::decryptString($this->openai_key);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt OpenAI key for site: ' . $this->site_url, [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set encrypted OpenAI API key
     *
     * @param string $key
     * @return void
     */
    public function setOpenAIKey($key)
    {
        $this->openai_key = Crypt::encryptString($key);
    }

    /**
     * Generate and return a new API key
     *
     * @return string Plain text API key (return this to user ONCE)
     */
    public static function generateAPIKey()
    {
        return 'ht_' . bin2hex(random_bytes(32));
    }

    /**
     * Hash API key for storage
     *
     * @param string $apiKey Plain text API key
     * @return string Hashed API key
     */
    public static function hashAPIKey($apiKey)
    {
        return hash('sha256', $apiKey);
    }

    /**
     * Find site by API key
     *
     * @param string $apiKey Plain text API key
     * @return Site|null
     */
    public static function findByAPIKey($apiKey)
    {
        $hash = self::hashAPIKey($apiKey);
        return self::where('api_key_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Increment query count
     *
     * @return void
     */
    public function incrementQueryCount()
    {
        $this->increment('query_count');
        $this->update(['last_query_at' => now()]);
    }

    /**
     * Set encrypted database password
     *
     * @param string $password
     * @return void
     */
    public function setDbPassword($password)
    {
        if (!empty($password)) {
            $this->db_password = Crypt::encryptString($password);
        }
    }

    /**
     * Get decrypted database password
     *
     * @return string|null
     */
    public function getDbPassword()
    {
        if (empty($this->db_password)) {
            return null;
        }
        
        try {
            return Crypt::decryptString($this->db_password);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt database password for site: ' . $this->site_url, [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}



