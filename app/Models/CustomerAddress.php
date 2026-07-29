<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'label',
        'phone',
        'phone_normalized',
        'country_code',
        'city',
        'area',
        'street',
        'notes',
        'address_hash',
        'is_default',
    ];

    protected $hidden = [
        'phone_normalized',
        'address_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'bool',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isDeleted(): bool
    {
        return $this->trashed();
    }
}
