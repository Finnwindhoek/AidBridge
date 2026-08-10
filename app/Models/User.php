<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'state',
        'is_disabled',
    ];

    /**
     * nric_encrypted is deliberately absent from $fillable so a crafted request
     * can never write raw ciphertext; it is only reachable via setNricAttribute().
     */
    protected $hidden = [
        'password',
        'remember_token',
        'nric_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_disabled' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Sensitive PII
    // ---------------------------------------------------------------------

    /**
     * Writes the NRIC as a Crypt ciphertext. Assigning $user->nric = '...' is the
     * only supported way to store it.
     */
    public function setNricAttribute(?string $value): void
    {
        $this->attributes['nric_encrypted'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    /**
     * Decrypts on read. Returns null rather than throwing when the ciphertext is
     * unreadable (e.g. APP_KEY was rotated), so listings never break.
     */
    public function getNricAttribute(): ?string
    {
        $cipher = $this->attributes['nric_encrypted'] ?? null;

        if (blank($cipher)) {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Display form for admin screens: only the last 4 digits are ever shown. */
    public function getMaskedNricAttribute(): ?string
    {
        $nric = $this->nric;

        return $nric === null ? null : str_repeat('•', max(strlen($nric) - 4, 0)).substr($nric, -4);
    }

    // ---------------------------------------------------------------------
    // RBAC helpers
    // ---------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBeneficiary(): bool
    {
        return $this->role === UserRole::Beneficiary;
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
