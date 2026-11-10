<?php

namespace Tests\Feature;

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
            'another format' => ['01/12/2026'],
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
}
