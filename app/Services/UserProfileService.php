<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserProfileService
{
    public function __construct(private readonly GoogleAddressService $google)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $profileData = Arr::only($data, ['last_name', 'company_name', 'city']);

            if (array_key_exists('country_code', $data)) {
                $code = $data['country_code'] === null ? null : strtoupper($data['country_code']);
                $profileData['country_code'] = $code;
                $profileData['country'] = $code === null ? null : config('countries.'.$code);
            } elseif (array_key_exists('country', $data)) {
                // Older API clients still send the country as a name.
                $profileData['country'] = $data['country'];
                $profileData['country_code'] = $this->countryCode($data['country']);
            }

            $profileData += $this->cityLocation($user->profile, $profileData);

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

    /**
     * Look the city up again when it or the country changes, to bias address suggestions.
     *
     * @param  array<string, mixed>  $profileData
     * @return array<string, float|null>
     */
    private function cityLocation(?UserProfile $profile, array $profileData): array
    {
        $city = array_key_exists('city', $profileData) ? $profileData['city'] : $profile?->city;
        $countryCode = array_key_exists('country_code', $profileData) ? $profileData['country_code'] : $profile?->country_code;

        if ($profile !== null && $city === $profile->city && $countryCode === $profile->country_code) {
            return [];
        }

        $location = filled($city) ? $this->google->locateCity($city, $countryCode) : null;

        return [
            'city_latitude' => $location[0] ?? null,
            'city_longitude' => $location[1] ?? null,
        ];
    }

    private function countryCode(?string $country): ?string
    {
        if (blank($country)) {
            return null;
        }

        $name = mb_strtolower(trim($country));

        return collect(config('countries'))
            ->search(fn (string $countryName, string $code): bool => mb_strtolower($countryName) === $name || strtolower($code) === $name)
            ?: null;
    }
}
