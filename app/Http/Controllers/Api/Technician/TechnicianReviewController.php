<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\TechnicianReview;
use Illuminate\Http\Request;

class TechnicianReviewController extends Controller
{
    /**
     * GET /api/technician/reviews
     * - list review milik teknisi login
     * - plus ringkasan (avg & total)
     */
    public function index(Request $request)
    {
        $tech = $request->user();

        $q = TechnicianReview::with([
                'driver:id,name',
                'damageReport:id,vehicle_id,driver_id,created_at',
                'damageReport.vehicle:id,plate_number',
            ])
            ->forTechnician((int) $tech->id)
            ->latestReviewed(); // ✅ COALESCE(reviewed_at, created_at)

        $items = $q->paginate(20);

        // ringkasan
        $avg = TechnicianReview::forTechnician((int) $tech->id)->avg('rating');
        $count = TechnicianReview::forTechnician((int) $tech->id)->count();

        return response()->json([
            'summary' => [
                'avg_rating' => $avg ? round((float) $avg, 2) : 0,
                'total_reviews' => $count,
            ],
            'data' => $items,
        ]);
    }

    /**
     * GET /api/technician/reviews/{review}
     * - detail review (harus milik teknisi ini)
     */
    public function show(Request $request, TechnicianReview $review)
    {
        $tech = $request->user();

        if (! $review->ownedByTechnician((int) $tech->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $review->load([
            'driver:id,name',
            'damageReport.vehicle:id,plate_number',
        ]);

        return response()->json($review);
    }
}
