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

            // The mileage is the reading at the end of the day it is entered. Re-saving the form
            // with the same value must not move that date, or trips since then would be lost.
            $currentVehicle = $user->activeVehicle()->first();

            if (array_key_exists('odometer_km', $vehicleData)
                && $this->mileageChanged($currentVehicle?->odometer_km, $vehicleData['odometer_km'])) {
                $vehicleData['odometer_recorded_on'] = $vehicleData['odometer_km'] === null ? null : today();
            }

            $user->activeVehicle()->updateOrCreate([], $vehicleData);

            return $user->fresh(['profile', 'activeVehicle']);
        });
    }

    private function mileageChanged(mixed $old, mixed $new): bool
    {
        if ($old === null || $new === null || $new === '') {
            return ($old === null) !== ($new === null || $new === '');
        }

        return round((float) $old, 1) !== round((float) $new, 1);
    }
}
