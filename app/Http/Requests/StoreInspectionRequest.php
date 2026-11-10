<?php

namespace App\Http\Requests;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
{
    /**
     * Upper-casing can lengthen a value ("ß" becomes "SS"), so the limit is
     * checked against the form that will be stored.
     */
    protected function prepareForValidation(): void
    {
        $registration = $this->input('vehicle_registration');

        if (is_string($registration)) {
            $this->merge(['vehicle_registration' => Inspection::normaliseRegistration($registration)]);
        }
    }

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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Laravel's own message shows the PHP format string, "Y-m-d".
            'requested_date.date_format' => 'The requested date must be a real date written as YYYY-MM-DD.',
        ];
    }
}
