<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use App\Services\FcmService;
use App\Services\NodeEventPublisher;

class ServiceBookingApprovalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'requested');

        $q = ServiceBooking::with([
            'damageReport.vehicle',
            'damageReport.driver',
        ])->latest();

        // status=all -> tampilkan semua
        if ($status && $status !== 'all') {
            $q->where('status', $status);
        }

        return response()->json($q->get());
    }

    /**
     * Approve / set jadwal booking
     * - scheduled_at ditentukan admin
     * - estimated_finish_at opsional (atau wajib kalau kamu mau)
     */
    public function approve(Request $request, ServiceBooking $booking, FcmService $fcm)
    {
        $request->validate([
            'scheduled_at' => 'required|date',
            'estimated_finish_at' => 'nullable|date|after_or_equal:scheduled_at',
            'note_admin' => 'nullable|string',
        ]);

        if (!in_array($booking->status, ['requested','rescheduled'], true)) {
            return response()->json([
                'message' => 'Booking tidak dalam status yang bisa di-approve.'
            ], 422);
        }

        $booking->update([
            'status' => 'approved',
            'scheduled_at' => $request->scheduled_at,
            'estimated_finish_at' => $request->estimated_finish_at,
            'note_admin' => $request->note_admin,
        ]);

        $booking->load(['damageReport.vehicle', 'damageReport.driver']);

        // =========================
        // FCM ke DRIVER (jadwal diset)
        // =========================
        try {
            $report = $booking->damageReport;
            if ($report && $report->driver) {
                $plate = $report->vehicle->plate_number ?? '-';

                $fcm->sendToUser(
                    $report->driver,
                    'Booking Servis Disetujui',
                    'Jadwal servis untuk kendaraan ' . $plate . ' sudah ditetapkan.',
                    [
                        'type' => 'service_booking',
                        'role' => 'driver',
                        'report_id' => (string) $report->id,
                        'booking_id' => (string) $booking->id,
                        'status' => (string) $booking->status,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // jangan bikin approve gagal
        }

        // =========================
        // Node event (web admin realtime)
        // =========================
        try {
            NodeEventPublisher::publish('service_booking.approved', [
                'booking_id' => $booking->id,
                'damage_report_id' => $booking->damage_report_id,
                'status' => $booking->status,
                'scheduled_at' => $booking->scheduled_at,
                'estimated_finish_at' => $booking->estimated_finish_at,
                'updated_at' => $booking->updated_at,
            ], ['admin']);
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Booking di-approve',
            'data' => $booking,
        ]);
    }

    /**
     * Reschedule
     */
    public function reschedule(Request $request, ServiceBooking $booking, FcmService $fcm)
    {
        $request->validate([
            'scheduled_at' => 'required|date',
            'estimated_finish_at' => 'nullable|date|after_or_equal:scheduled_at',
            'note_admin' => 'nullable|string',
        ]);

        if (!in_array($booking->status, ['approved','requested','rescheduled'], true)) {
            return response()->json([
                'message' => 'Booking tidak bisa di-reschedule.'
            ], 422);
        }

        $booking->update([
            'status' => 'rescheduled',
            'scheduled_at' => $request->scheduled_at,
            'estimated_finish_at' => $request->estimated_finish_at,
            'note_admin' => $request->note_admin,
        ]);

        $booking->load(['damageReport.vehicle', 'damageReport.driver']);

        try {
            $report = $booking->damageReport;
            if ($report && $report->driver) {
                $plate = $report->vehicle->plate_number ?? '-';

                $fcm->sendToUser(
                    $report->driver,
                    'Jadwal Booking Diubah',
                    'Jadwal servis kendaraan ' . $plate . ' telah diubah.',
                    [
                        'type' => 'service_booking',
                        'role' => 'driver',
                        'report_id' => (string) $report->id,
                        'booking_id' => (string) $booking->id,
                        'status' => (string) $booking->status,
                    ]
                );
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Booking di-reschedule',
            'data' => $booking,
        ]);
    }

    public function cancel(Request $request, ServiceBooking $booking, FcmService $fcm)
    {
        $request->validate([
            'note_admin' => 'nullable|string',
        ]);

        $booking->update([
            'status' => 'canceled',
            'note_admin' => $request->note_admin,
        ]);

        $booking->load(['damageReport.vehicle', 'damageReport.driver']);

        try {
            $report = $booking->damageReport;
            if ($report && $report->driver) {
                $plate = $report->vehicle->plate_number ?? '-';

                $fcm->sendToUser(
                    $report->driver,
                    'Booking Dibatalkan',
                    'Booking servis kendaraan ' . $plate . ' dibatalkan admin.',
                    [
                        'type' => 'service_booking',
                        'role' => 'driver',
                        'report_id' => (string) $report->id,
                        'booking_id' => (string) $booking->id,
                        'status' => (string) $booking->status,
                    ]
                );
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Booking dibatalkan',
            'data' => $booking,
        ]);
    }
}
