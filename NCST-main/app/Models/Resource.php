<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['asset_code', 'name', 'type', 'category', 'stock', 'status'])]
class Resource extends Model
{
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
