<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'vehicle_registration',
        'requested_date',
        'notes',
    ];

    protected $attributes = [
        'status' => InspectionStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date:Y-m-d',
            'status' => InspectionStatus::class,
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function vehicleRegistration(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => self::normaliseRegistration($value),
        );
    }

    /**
     * The one definition of how a registration is stored. The form request
     * validates this form, so the two cannot drift apart.
     */
    public static function normaliseRegistration(string $value): string
    {
        return mb_strtoupper($value);
    }
}
