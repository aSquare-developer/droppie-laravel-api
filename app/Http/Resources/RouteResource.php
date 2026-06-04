<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startAddress = $this->startAddress;
        $endAddress = $this->endAddress;

        return [
            'id' => $this->id,
            'start_address' => $startAddress?->formatted_address,
            'start_place_id' => $startAddress?->place_id,
            'start_postal_code' => $startAddress?->postal_code,
            'start_city' => $startAddress?->city,
            'start_country' => $startAddress?->country,
            'start_country_code' => $startAddress?->country_code,
            'start_street' => $startAddress?->street,
            'start_street_number' => $startAddress?->street_number,
            'start_latitude' => $startAddress?->latitude,
            'start_longitude' => $startAddress?->longitude,
            'end_address' => $endAddress?->formatted_address,
            'end_place_id' => $endAddress?->place_id,
            'end_postal_code' => $endAddress?->postal_code,
            'end_city' => $endAddress?->city,
            'end_country' => $endAddress?->country,
            'end_country_code' => $endAddress?->country_code,
            'end_street' => $endAddress?->street,
            'end_street_number' => $endAddress?->street_number,
            'end_latitude' => $endAddress?->latitude,
            'end_longitude' => $endAddress?->longitude,
            'started_at' => $this->started_at?->toDateString(),
            'distance_km' => $this->distance_km,
            'distance_status' => $this->distance_status,
            'distance_error' => $this->distance_error,
            'created_at' => $this->created_at,
        ];
    }
}
