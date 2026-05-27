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
            'end_address' => $this->end_address,
            'distance_km' => $this->distance_km,
            'distance_status' => $this->distance_status,
            'distance_error' => $this->distance_error,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
        ];
    }
}
