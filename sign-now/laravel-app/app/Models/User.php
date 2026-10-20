<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'lastname', 'email', 'password',
    'company', 'phone', 'street', 'apt', 'city', 'state', 'zip', 'country',
    'signature',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . ($this->lastname ?? ''));
    }

    public function getFullAddressAttribute(): string
    {
        $street = (string) ($this->street ?? '');
        $apt = (string) ($this->apt ?? '');
        $parts = [];

        if ($street !== '') {
            $parts[] = $apt !== '' ? "{$street}, {$apt}" : $street;
        } elseif ($apt !== '') {
            $parts[] = $apt;
        }

        $cityLine = implode(', ', array_filter([(string) $this->city, (string) $this->state]));
        if ((string) $this->zip !== '') {
            $cityLine = trim($cityLine . ' ' . $this->zip);
        }
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        if ((string) $this->country !== '') {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }

    /**
     * Signing-profile data in the same shape as Customer::toCustomerData(),
     * used to auto-fill admin-assigned fields during finalization.
     */
    public function toSigningData(): array
    {
        return [
            'UserID' => (string) $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname ?? '',
            'fullName' => $this->full_name,
            'email' => $this->email,
            'companyName' => $this->company ?? '',
            'phone' => $this->phone ?? '',
            'fullAddress' => $this->full_address,
            'street' => $this->street ?? '',
            'city' => $this->city ?? '',
            'state' => $this->state ?? '',
            'zip' => $this->zip ?? '',
            'country' => $this->country ?? '',
        ];
    }
}
