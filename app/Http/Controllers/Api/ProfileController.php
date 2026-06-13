<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = $request->user();
            $data = $request->validated();

            $profileData = Arr::only($data, ['last_name', 'company_name', 'country']);

            if (array_key_exists('name', $data)) {
                $profileData['first_name'] = $data['name'];
            }

            $user->profile()->updateOrCreate([], [
                'first_name' => $user->profile?->first_name ?? 'User',
                ...$profileData,
            ]);

            $vehicleData = ['is_active' => true];

            foreach ([
                'car_registration_number' => 'registration_number',
                'car_make_model' => 'make_model',
                'car_mileage' => 'odometer_km',
            ] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $vehicleData[$column] = $data[$input];
                }
            }

            $user->activeVehicle()->updateOrCreate([], $vehicleData);

            return $user->fresh();
        });

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user),
        ]);
    }
}
