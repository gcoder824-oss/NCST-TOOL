<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['reservation_id', 'reported_by', 'severity', 'description', 'photo_evidence_path', 'status'])]
class Incident extends Model
{
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
