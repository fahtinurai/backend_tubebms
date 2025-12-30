<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use App\Services\FcmService;
use App\Services\NodeEventPublisher;

class ServiceBookingController extends Controller
{
    /**
     * Driver request booking (melekat ke DamageReport)
     * Driver hanya "mengajukan", admin yang menentukan jadwal final.
     */
    public function store(Request $request, DamageReport $damageReport, FcmService $fcm)
    {
        $driver = $request->user();

        // pastikan report milik driver
        if ((int) $damageReport->driver_id !== (int) $driver->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'preferred_at' => 'nullable|date',
            'note_driver'  => 'nullable|string',
        ]);

        // jangan booking kalau sudah selesai
        $damageReport->load('latestTechnicianResponse');
        $lastStatus = optional($damageReport->latestTechnicianResponse)->status ?? 'menunggu';
        if ($lastStatus === 'selesai') {
            return response()->json(['message' => 'Laporan sudah selesai.'], 422);
        }

        // 1 report = 1 booking
        $booking = ServiceBooking::updateOrCreate(
            ['damage_report_id' => $damageReport->id],
            [
                'driver_id'    => $driver->id,
                'vehicle_id'   => $damageReport->vehicle_id, 
                'requested_at' => now(),
                'status'       => 'requested',
                'note_driver'  => $request->note_driver
                    ?? ($request->preferred_at ? ('Preferensi jadwal: ' . $request->preferred_at) : null),
            ]
        );


        $booking->load(['damageReport.vehicle', 'damageReport.driver']);

        // Optional: notif ke admin (booking request)
        try {
            $plate = $damageReport->vehicle->plate_number ?? '-';
            $fcm->sendToRole(
                'admin',
                'Booking Servis Baru',
                'Driver mengajukan booking servis untuk kendaraan ' . $plate,
                [
                    'type' => 'service_booking',
                    'role' => 'admin',
                    'report_id' => (string) $damageReport->id,
                    'booking_id' => (string) $booking->id,
                    'status' => (string) $booking->status,
                ]
            );
        } catch (\Throwable $e) {}

        // node event (opsional)
        try {
            NodeEventPublisher::publish('service_booking.requested', [
                'booking_id' => $booking->id,
                'damage_report_id' => $booking->damage_report_id,
                'status' => $booking->status,
                'requested_at' => $booking->requested_at,
                'created_at' => $booking->created_at,
            ], ['admin']);
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Booking berhasil diajukan',
            'data' => $booking,
        ], 201);
    }


    public function show(Request $request, DamageReport $damageReport)
    {
        $driver = $request->user();

        if ((int) $damageReport->driver_id !== (int) $driver->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $damageReport->load(['booking']);
        return response()->json($damageReport->booking);
    }

    public function cancel(Request $request, ServiceBooking $booking, FcmService $fcm)
    {
        $driver = $request->user();

        // cek kepemilikan via damage report
        $booking->load(['damageReport.vehicle', 'damageReport.driver']);
        $report = $booking->damageReport;

        if (!$report || (int) $report->driver_id !== (int) $driver->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!in_array($booking->status, ['requested', 'approved', 'rescheduled'], true)) {
            return response()->json(['message' => 'Booking tidak bisa dibatalkan.'], 422);
        }

        $booking->update(['status' => 'canceled']);

        // notif admin (opsional)
        try {
            $plate = $report->vehicle->plate_number ?? '-';
            $fcm->sendToRole(
                'admin',
                'Booking Dibatalkan',
                'Driver membatalkan booking servis kendaraan ' . $plate,
                [
                    'type' => 'service_booking',
                    'role' => 'admin',
                    'report_id' => (string) $report->id,
                    'booking_id' => (string) $booking->id,
                    'status' => (string) $booking->status,
                ]
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Booking dibatalkan',
            'data' => $booking,
        ]);
    }
}
