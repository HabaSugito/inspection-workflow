<?php

namespace Tests\Feature;

use App\Models\Inspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateInspectionTest extends TestCase
{
    use RefreshDatabase;

    private const string TODAY = '2026-11-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY.' 12:00:00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jane Smith',
            'vehicle_registration' => 'AB12 CDE',
            'requested_date' => '2026-12-01',
            'notes' => 'Customer prefers a morning visit.',
        ], $overrides);
    }

    public function test_ac101_valid_request_creates_a_pending_inspection_with_no_inspector(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload());

        $response->assertCreated()->assertJson([
            'data' => [
                'customer_name' => 'Jane Smith',
                'vehicle_registration' => 'AB12 CDE',
                'requested_date' => '2026-12-01',
                'notes' => 'Customer prefers a morning visit.',
                'status' => 'pending',
                'inspector_name' => null,
            ],
        ]);
        $response->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']]);

        $this->assertDatabaseCount('inspections', 1);
        $this->assertDatabaseHas('inspections', [
            'id' => $response->json('data.id'),
            'customer_name' => 'Jane Smith',
            'status' => 'pending',
            'inspector_name' => null,
        ]);
    }

    public function test_ac102_notes_may_be_omitted(): void
    {
        $payload = $this->validPayload();
        unset($payload['notes']);

        $response = $this->postJson('/api/inspections', $payload);

        $response->assertCreated()->assertJsonPath('data.notes', null);
        $this->assertDatabaseHas('inspections', ['id' => $response->json('data.id'), 'notes' => null]);
    }

    #[DataProvider('requiredFields')]
    public function test_ac103_missing_required_field_is_rejected(string $field): void
    {
        $payload = $this->validPayload();
        unset($payload[$field]);

        $response = $this->postJson('/api/inspections', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertDatabaseCount('inspections', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFields(): array
    {
        return [
            'customer name' => ['customer_name'],
            'vehicle registration' => ['vehicle_registration'],
            'requested date' => ['requested_date'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_ac104_invalid_requested_date_is_rejected(string $date): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload(['requested_date' => $date]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['requested_date']);
        $this->assertDatabaseCount('inspections', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'not a date' => ['not-a-date'],
            'a day that does not exist' => ['2027-02-30'],
            'day first' => ['12/01/2027'],
            'no leading zeros' => ['2027-1-5'],
            'a date with a time' => ['2027-01-05T10:00:00'],
            'the 29th of February in a year that is not a leap year' => ['2027-02-29'],
        ];
    }

    #[DataProvider('fieldLimits')]
    public function test_ac105_value_longer_than_its_limit_is_rejected(string $field, int $limit): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([$field => str_repeat('a', $limit + 1)]));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertDatabaseCount('inspections', 0);
    }

    #[DataProvider('fieldLimits')]
    public function test_ac105_value_exactly_at_its_limit_is_accepted(string $field, int $limit): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([$field => str_repeat('a', $limit)]));

        $response->assertCreated();
        $this->assertDatabaseCount('inspections', 1);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function fieldLimits(): array
    {
        return [
            'customer name' => ['customer_name', 255],
            'vehicle registration' => ['vehicle_registration', 20],
            'notes' => ['notes', 2000],
        ];
    }

    public function test_ac106_validation_error_is_json_with_message_and_field_errors_and_stores_nothing(): void
    {
        $response = $this->postJson('/api/inspections', []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => ['customer_name', 'vehicle_registration', 'requested_date'],
            ])
            ->assertJsonMissingPath('errors.notes');
        $this->assertIsString($response->json('message'));
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ac106_validation_error_is_json_without_a_json_accept_header(): void
    {
        $response = $this->post('/api/inspections', []);

        $response->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message', 'errors' => ['customer_name']]);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ac107_status_and_inspector_name_in_the_request_are_ignored(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'status' => 'completed',
            'inspector_name' => 'Sam Inspector',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.inspector_name', null);
        $this->assertDatabaseHas('inspections', [
            'id' => $response->json('data.id'),
            'status' => 'pending',
            'inspector_name' => null,
        ]);
    }

    public function test_ac108_requested_date_before_today_is_rejected(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload(['requested_date' => '2026-11-09']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['requested_date']);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ac108_requested_date_of_today_is_accepted(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload(['requested_date' => self::TODAY]));

        $response->assertCreated()->assertJsonPath('data.requested_date', self::TODAY);
    }

    public function test_ac109_vehicle_registration_is_stored_and_returned_in_upper_case(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload(['vehicle_registration' => 'ab12 cde']));

        $response->assertCreated();
        $this->assertSame('AB12 CDE', $response->json('data.vehicle_registration'));
        $this->assertSame('AB12 CDE', DB::table('inspections')->value('vehicle_registration'));
    }

    public function test_ac104_the_29th_of_february_in_a_leap_year_is_accepted(): void
    {
        $this->postJson('/api/inspections', $this->validPayload(['requested_date' => '2028-02-29']))
            ->assertCreated()
            ->assertJsonPath('data.requested_date', '2028-02-29');
    }

    #[DataProvider('valuesOfTheWrongType')]
    public function test_ac103_value_of_the_wrong_type_is_rejected(string $field, mixed $value): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([$field => $value]));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertDatabaseCount('inspections', 0);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function valuesOfTheWrongType(): array
    {
        return [
            'customer name as a list' => ['customer_name', ['Jane']],
            'customer name as a number' => ['customer_name', 123],
            'vehicle registration as a list' => ['vehicle_registration', ['AB12']],
            'vehicle registration as a number' => ['vehicle_registration', 123],
            'requested date as a list' => ['requested_date', ['2026-12-01']],
            'requested date as a number' => ['requested_date', 20261201],
            'notes as a list' => ['notes', ['a note']],
            'notes as a number' => ['notes', 123],
        ];
    }

    #[DataProvider('requiredFieldsWithEmptyValues')]
    public function test_ac103_required_field_that_is_empty_is_rejected(string $field, mixed $value): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([$field => $value]));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertDatabaseCount('inspections', 0);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function requiredFieldsWithEmptyValues(): array
    {
        $cases = [];

        foreach (['customer_name', 'vehicle_registration', 'requested_date'] as $field) {
            $cases["{$field} empty"] = [$field, ''];
            $cases["{$field} spaces only"] = [$field, '   '];
            $cases["{$field} null"] = [$field, null];
        }

        return $cases;
    }

    public function test_ac101_spaces_around_a_value_are_removed_and_spaces_inside_are_kept(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'customer_name' => '  Jane  Smith  ',
            'vehicle_registration' => '  ab12 cde  ',
        ]));

        $response->assertCreated();
        $row = DB::table('inspections')->first();
        $this->assertSame('Jane  Smith', $row->customer_name);
        $this->assertSame('AB12 CDE', $row->vehicle_registration);
    }

    public function test_ac102_empty_notes_are_stored_as_no_notes(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload(['notes' => '']));

        $response->assertCreated()->assertJsonPath('data.notes', null);
        $this->assertNull(DB::table('inspections')->value('notes'));
    }

    public function test_ac101_text_in_other_scripts_is_stored_as_sent(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'customer_name' => '山田 太郎',
            'notes' => 'Bring the service book 📘',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', '山田 太郎')
            ->assertJsonPath('data.notes', 'Bring the service book 📘');
    }

    public function test_ac105_limits_count_characters_not_bytes(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'customer_name' => str_repeat('田', 255),
        ]));

        $response->assertCreated();
    }

    public function test_ac105_vehicle_registration_that_grows_past_its_limit_in_upper_case_is_rejected(): void
    {
        // "ß" becomes "SS" in upper case, so 20 characters become 40.
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'vehicle_registration' => str_repeat('ß', 20),
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['vehicle_registration']);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ac105_vehicle_registration_that_fits_its_limit_in_upper_case_is_accepted(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'vehicle_registration' => str_repeat('ß', 10),
        ]));

        $response->assertCreated();
        $this->assertSame(str_repeat('SS', 10), DB::table('inspections')->value('vehicle_registration'));
    }

    public function test_ac107_id_and_timestamps_in_the_request_are_ignored(): void
    {
        $response = $this->postJson('/api/inspections', $this->validPayload([
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ]));

        $response->assertCreated()->assertJsonPath('data.created_at', '2026-11-10T12:00:00Z');
        $this->assertNotSame(999, $response->json('data.id'));
        $this->assertDatabaseMissing('inspections', ['id' => 999]);
    }

    public function test_ac108_today_is_accepted_up_to_the_last_second_of_the_day(): void
    {
        $this->travelTo(self::TODAY.' 23:59:59');

        $this->postJson('/api/inspections', $this->validPayload(['requested_date' => self::TODAY]))
            ->assertCreated();
    }

    public function test_ac108_yesterday_is_rejected_from_the_first_second_of_the_day(): void
    {
        $this->travelTo(self::TODAY.' 00:00:00');

        $this->postJson('/api/inspections', $this->validPayload(['requested_date' => '2026-11-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requested_date']);
    }

    public function test_ac109_the_model_upper_cases_the_registration_for_any_writer(): void
    {
        $inspection = Inspection::factory()->create(['vehicle_registration' => 'ab12 cde']);

        $this->assertSame('AB12 CDE', DB::table('inspections')->where('id', $inspection->id)->value('vehicle_registration'));
    }

    public function test_ac107_the_model_does_not_accept_status_or_inspector_by_mass_assignment(): void
    {
        $inspection = Inspection::create($this->validPayload([
            'status' => 'completed',
            'inspector_name' => 'Sam Inspector',
        ]));

        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'status' => 'pending',
            'inspector_name' => null,
        ]);
    }
}
