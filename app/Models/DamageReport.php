<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TechnicianResponse;

class DamageReport extends Model
{
    protected $table = 'damage_reports';

    protected $fillable = [
    'vehicle_id',
    'driver_id',
    'description',
    'status',
    ];
    
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function technicianResponses()
    {
        // history tetap kronologis dibuat
        return $this->hasMany(TechnicianResponse::class, 'damage_id', 'id')
                    ->orderBy('created_at', 'asc');
    }

    public function latestTechnicianResponse()
    {
        return $this->hasOne(TechnicianResponse::class, 'damage_id', 'id')
                    ->latestOfMany('updated_at');
    }

}
