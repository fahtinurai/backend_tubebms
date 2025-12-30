<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBooking extends Model
{
    protected $table = 'service_bookings';

    protected $fillable = [
        'damage_report_id',
        'driver_id',
        'vehicle_id',
        'requested_at',
        'scheduled_at',
        'estimated_finish_at',
        'status',
        'note_driver',
        'note_admin',
    ];

    protected $attributes = [
        'status' => 'requested',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'estimated_finish_at' => 'datetime',
    ];

    // =========================
    // Relations
    // =========================
    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id');
    }

    /**
     * Shortcut relations (tanpa kolom driver_id/vehicle_id)
     * Jadi Booking tetap bisa akses driver/vehicle via damageReport.
     */
    public function driver()
    {
        return $this->hasOneThrough(
            User::class,
            DamageReport::class,
            'id',        // Foreign key di damage_reports untuk booking->damage_report_id
            'id',        // PK users
            'damage_report_id', // local key di service_bookings
            'driver_id'  // FK users di damage_reports
        );
    }

    public function vehicle()
    {
        return $this->hasOneThrough(
            Vehicle::class,
            DamageReport::class,
            'id',
            'id',
            'damage_report_id',
            'vehicle_id'
        );
    }
}
