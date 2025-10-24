<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;
use Carbon\Carbon;

/**
 * Password Reset Model
 *
 * @property string $email
 * @property string $token
 * @property \Carbon\Carbon|null $created_at
 */
class PasswordReset extends Model
{
    protected ?string $table = 'password_resets';
    
    // ✅ Email jadi primary key karena table gak punya id
    protected string $primaryKey = 'email';
    
    // ✅ Primary key bukan auto increment
    public bool $incrementing = false;
    
    // ✅ Primary key type string
    protected string $keyType = 'string';
    
    // ✅ Disable timestamps
    public bool $timestamps = false;
    
    protected array $fillable = [
        'email',
        'token',
        'created_at',
    ];
    
    protected array $casts = [
        'created_at' => 'datetime',
    ];
    
    /**
     * Check if token is expired (older than 1 hour).
     */
    public function isExpired(): bool
    {
        if (!$this->created_at) {
            return true;
        }
        
        return $this->created_at->addHour()->isPast();
    }
    
    /**
     * Scope: Valid tokens only (not expired).
     */
    public function scopeValid($query)
    {
        $oneHourAgo = Carbon::now()->subHour();
        return $query->where('created_at', '>=', $oneHourAgo);
    }
    
    /**
     * Scope: Tokens for specific email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}