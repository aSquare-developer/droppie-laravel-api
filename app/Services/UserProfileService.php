<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
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

            return $user->fresh(['profile', 'activeVehicle']);
        });
    }
}
