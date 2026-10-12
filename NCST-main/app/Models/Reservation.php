<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['transaction_id', 'user_id', 'resource_id', 'quantity', 'purpose', 'duration', 'status', 'remarks', 'pass_qr_token', 'requested_at', 'approved_at', 'borrowed_at', 'due_date', 'returned_at', 'is_walk_in'])]
class Reservation extends Model
{
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'borrowed_at' => 'datetime',
            'due_date' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
