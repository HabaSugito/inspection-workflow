<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'vehicle_registration' => ['required', 'string', 'max:20'],
            'requested_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
