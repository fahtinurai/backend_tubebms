<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;

// ADMIN
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VehicleController;
use App\Http\Controllers\Api\Admin\VehicleAssignmentController;
use App\Http\Controllers\Api\Admin\PartController;
use App\Http\Controllers\Api\Admin\StockMovementController;
//nawra menambahkan RepairController
use App\Http\Controllers\Api\Admin\RepairController;
use App\Http\Controllers\Api\Admin\FinanceTransactionController;
use App\Http\Controllers\Api\Admin\PartUsageApprovalController;
use App\Http\Controllers\Api\Admin\DamageReportController as AdminDamageReportController;

// DRIVER
use App\Http\Controllers\Api\Driver\DamageReportController as DriverDamageReportController;

// TECHNICIAN
use App\Http\Controllers\Api\Technician\DamageReportController as TechnicianDamageReportController;
use App\Http\Controllers\Api\Technician\PartUsageController as TechnicianPartUsageController;

// MOBILE
use App\Http\Controllers\Api\Mobile\FcmTokenController;


// PUBLIC
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // FCM TOKEN
    Route::post('/mobile/fcm-token', [FcmTokenController::class, 'store']);
    Route::post('/mobile/fcm-token/delete', [FcmTokenController::class, 'destroy']);

    // =========================
    // ADMIN
    // =========================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('vehicles', VehicleController::class);

        Route::get('vehicle-assignments', [VehicleAssignmentController::class, 'index']);
        Route::post('vehicle-assignments', [VehicleAssignmentController::class, 'store']);
        Route::delete('vehicle-assignments/{vehicleAssignment}', [VehicleAssignmentController::class, 'destroy']);
        Route::post('assign-vehicle', [VehicleAssignmentController::class, 'store']);

        Route::get('damage-reports', [AdminDamageReportController::class, 'index']);
        Route::get('damage-reports/{damageReport}', [AdminDamageReportController::class, 'show']);
        Route::post('damage-reports/{damageReport}/complete', [AdminDamageReportController::class, 'markAsCompleted']);
        Route::get('damage-reports/follow-ups/list', [AdminDamageReportController::class, 'followUps']);

        // Inventory
        Route::get('parts', [PartController::class, 'index']);
        Route::post('parts', [PartController::class, 'store']);
        Route::put('parts/{part}', [PartController::class, 'update']);
        Route::delete('parts/{part}', [PartController::class, 'destroy']);

        Route::get('stock-movements', [StockMovementController::class, 'index']);
        Route::post('stock-movements', [StockMovementController::class, 'store']);

        // Part usage approval
        Route::get('part-usages', [PartUsageApprovalController::class, 'index']);
        Route::get('part-usages/pending', [PartUsageApprovalController::class, 'pending']);
        Route::post('part-usages/{partUsage}/approve', [PartUsageApprovalController::class, 'approve']);
        Route::post('part-usages/{partUsage}/reject', [PartUsageApprovalController::class, 'reject']);

        // Nawra menambahkan route Repairs
        Route::get('repairs', [RepairController::class, 'index']);
        Route::get('repairs/{repair}', [RepairController::class, 'show']);
        Route::post('repairs', [RepairController::class, 'store']);
        Route::post('repairs/{repair}/finalize', [RepairController::class, 'finalize']);

        // Finance
        Route::get('transactions', [FinanceTransactionController::class, 'index']);
        Route::post('transactions', [FinanceTransactionController::class, 'store']);
        Route::put('transactions/{financeTransaction}', [FinanceTransactionController::class, 'update']);
        Route::delete('transactions/{financeTransaction}', [FinanceTransactionController::class, 'destroy']);
    });

    // =========================
    // DRIVER
    // =========================
    Route::middleware('role:driver')->prefix('driver')->group(function () {

        Route::post('vehicles/verify', [DriverDamageReportController::class, 'verifyVehicle']);
        Route::get('vehicles', [DriverDamageReportController::class, 'myVehicles']);

        Route::post('damage-reports', [DriverDamageReportController::class, 'store']);
        Route::get('damage-reports', [DriverDamageReportController::class, 'index']);
        Route::get('damage-reports/{damageReport}', [DriverDamageReportController::class, 'show']);
        Route::put('damage-reports/{damageReport}', [DriverDamageReportController::class, 'update']);
        Route::delete('damage-reports/{damageReport}', [DriverDamageReportController::class, 'destroy']);
    });

    // =========================
    // TECHNICIAN
    // =========================
    Route::middleware('role:teknisi')->prefix('technician')->group(function () {

        Route::get('test-fcm', [TechnicianDamageReportController::class, 'testFcm']);

        Route::get('damage-reports', [TechnicianDamageReportController::class, 'index']);
        Route::get('damage-reports/{damageReport}', [TechnicianDamageReportController::class, 'show']);

        Route::post('damage-reports/{damageReport}/respond', [TechnicianDamageReportController::class, 'respond']);
        Route::put('technician-responses/{technicianResponse}', [TechnicianDamageReportController::class, 'updateResponse']);
        Route::get('my-responses', [TechnicianDamageReportController::class, 'myResponses']);

        // ✅ Sparepart request
        Route::post('part-usages', [TechnicianPartUsageController::class, 'store']);
        Route::get('my-part-usages', [TechnicianPartUsageController::class, 'myUsages']);
    });
});
