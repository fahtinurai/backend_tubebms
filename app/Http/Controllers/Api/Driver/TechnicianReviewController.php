<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\TechnicianReview;
use Illuminate\Http\Request;
use App\Services\FcmService;
use App\Services\NodeEventPublisher;

class TechnicianReviewController extends Controller
{
    public function show(Request $request, DamageReport $damageReport)
    {
        $driver = $request->user();

        if ((int) $damageReport->driver_id !== (int) $driver->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // ✅ lebih aman: ambil review berdasarkan damage_report_id + driver_id
        $review = TechnicianReview::with('technician')
            ->where('damage_report_id', $damageReport->id)
            ->where('driver_id', $driver->id)
            ->first();

        return response()->json($review);
    }

    public function store(Request $request, DamageReport $damageReport, FcmService $fcm)
    {
        $driver = $request->user();

        if ((int) $damageReport->driver_id !== (int) $driver->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        // teknisi penanggung jawab: ambil dari latestTechnicianResponse
        $damageReport->load(['latestTechnicianResponse', 'vehicle']);

        $latest = $damageReport->latestTechnicianResponse;
        if (! $latest) {
            return response()->json(['message' => 'Belum ada respon teknisi.'], 422);
        }

        // hanya boleh setelah selesai
        if (strtolower((string) $latest->status) !== 'selesai') {
            return response()->json(['message' => 'Rating hanya bisa setelah status selesai.'], 422);
        }

        $review = TechnicianReview::updateOrCreate(
            [
                'damage_report_id' => $damageReport->id,
                'driver_id' => $driver->id, // ✅ kunci juga driver
            ],
            [
                'technician_id' => $latest->technician_id,
                'rating' => (int) $validated['rating'],
                'review' => $validated['review'] ?? null,
                'reviewed_at' => now(), // ✅ sudah benar
            ]
        );

        $review->load('technician');

        // =========================
        // Optional: notif teknisi
        // =========================
        try {
            if ($review->technician) {
                $plate = $damageReport->vehicle->plate_number ?? '-';
                $fcm->sendToUser(
                    $review->technician,
                    'Rating Baru',
                    'Kamu mendapat rating baru untuk perbaikan kendaraan ' . $plate,
                    [
                        'type' => 'technician_review',
                        'role' => 'technician',
                        'report_id' => (string) $damageReport->id,
                        'review_id' => (string) $review->id,
                        'rating' => (string) $review->rating,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // notif jangan bikin API gagal
        }

        // Optional: event untuk admin dashboard
        try {
            NodeEventPublisher::publish('technician_review.created', [
                'review_id' => $review->id,
                'damage_report_id' => $review->damage_report_id,
                'technician_id' => $review->technician_id,
                'driver_id' => $review->driver_id,
                'rating' => $review->rating,
                'reviewed_at' => optional($review->reviewed_at)->toISOString(),
                'created_at' => optional($review->created_at)->toISOString(),
            ], ['admin']);
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Review tersimpan',
            'data' => $review,
        ], 201);
    }
}
