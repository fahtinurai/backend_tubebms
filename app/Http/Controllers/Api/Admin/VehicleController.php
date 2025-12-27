<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;          
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        return response()->json(Vehicle::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'plate_number' => 'required|unique:vehicles',
            'brand'        => 'nullable|string',
            'model'        => 'nullable|string',
            'year'         => 'nullable|integer',
        ]);

        $vehicle = Vehicle::create([
            'brand'        => $request->brand,
            'model'        => $request->model,
            'plate_number' => $request->plate_number,
            'year'         => $request->year,
        ]);

        return response()->json($vehicle, 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'plate_number' => 'sometimes|unique:vehicles,plate_number,' . $vehicle->id,
        ]);

        $vehicle->update($request->only('brand', 'model', 'plate_number', 'year'));

        return response()->json($vehicle);
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return response()->json(['message' => 'Kendaraan dihapus']);
    }
}
