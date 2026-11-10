<?php

namespace App\Http\Resources;

use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inspection
 */
class InspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'vehicle_registration' => $this->vehicle_registration,
            'requested_date' => $this->requested_date->format('Y-m-d'),
            'notes' => $this->notes,
            'status' => $this->status->value,
            'inspector_name' => $this->inspector_name,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
