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
        return [
            'id' => $this->id,
            'start_address' => $this->start_address,
            'start_place_id' => $this->start_place_id,
            'start_postal_code' => $this->start_postal_code,
            'start_city' => $this->start_city,
            'start_country' => $this->start_country,
            'start_country_code' => $this->start_country_code,
            'start_street' => $this->start_street,
            'start_street_number' => $this->start_street_number,
            'start_latitude' => $this->start_latitude,
            'start_longitude' => $this->start_longitude,
            'end_address' => $this->end_address,
            'end_place_id' => $this->end_place_id,
            'end_postal_code' => $this->end_postal_code,
            'end_city' => $this->end_city,
            'end_country' => $this->end_country,
            'end_country_code' => $this->end_country_code,
            'end_street' => $this->end_street,
            'end_street_number' => $this->end_street_number,
            'end_latitude' => $this->end_latitude,
            'end_longitude' => $this->end_longitude,
            'started_at' => $this->started_at?->toDateString(),
            'distance_km' => $this->distance_km,
            'distance_status' => $this->distance_status,
            'distance_error' => $this->distance_error,
            'created_at' => $this->created_at,
        ];
    }
}
