<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class CreditLog extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'user_id', 'change_amount', 'balance_after', 'reason', 'reservation_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
