<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $vehicle = $this->activeVehicle;

        return [
            'id' => $this->id,
            'name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'company_name' => $profile?->company_name,
            'country' => $profile?->country,
            'car_registration_number' => $vehicle?->registration_number,
            'car_make_model' => $vehicle?->make_model,
            'car_mileage' => $vehicle?->odometer_km,
            'active_vehicle_id' => $vehicle?->id,
            'created_at' => $this->created_at,
        ];
    }
}
