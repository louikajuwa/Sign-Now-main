<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'lastname',
        'email',
        'company',
        'phone',
        'street',
        'apt',
        'city',
        'state',
        'zip',
        'country',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->lastname);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = [];

        if ($this->street !== '') {
            $parts[] = $this->apt !== '' ? "{$this->street}, {$this->apt}" : $this->street;
        } elseif ($this->apt !== '') {
            $parts[] = $this->apt;
        }

        $cityLine = implode(', ', array_filter([$this->city, $this->state]));
        if ($this->zip !== '') {
            $cityLine = trim($cityLine . ' ' . $this->zip);
        }
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        if ($this->country !== '') {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }

    /**
     * Stable reference exposed as UserID in toCustomerData() — the external
     * user_id when present, otherwise the local row id.
     */
    public function getRefAttribute(): string
    {
        return $this->user_id ?: (string) $this->id;
    }

    /**
     * Shape consumed by the builder/fill JS (CUSTOMER_FIELD_MAP) — keys must
     * stay in sync with public/js/builder.js and the customer-key dropdown.
     */
    public function toCustomerData(): array
    {
        return [
            'UserID' => $this->ref,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'companyName' => $this->company,
            'phone' => $this->phone,
            'fullAddress' => $this->full_address,
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
        ];
    }

    public static function resolveRef(?string $ref): ?self
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }

        $customer = static::where('user_id', $ref)->first();
        if (!$customer && ctype_digit($ref)) {
            $customer = static::find((int) $ref);
        }

        return $customer;
    }

    /**
     * Resolve the customer linked to a document recipient: the explicit
     * recipients.customer_id link wins, falling back to an email match —
     * in which case the link is stored on the recipient for next time.
     */
    public static function forDocumentRecipient(Document $doc, string $email): ?self
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $recipient = Recipient::where('document_id', $doc->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($recipient?->customer_id) {
            $customer = static::find($recipient->customer_id);
            if ($customer) {
                return $customer;
            }
        }

        if ($email === '__admin__') {
            return null;
        }

        $customer = static::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($customer && $recipient && !$recipient->customer_id) {
            $recipient->update(['customer_id' => $customer->id]);
        }

        return $customer;
    }
}
