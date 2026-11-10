<?php

namespace Tests\Feature;

use App\Models\Inspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ViewInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac201_list_returns_every_inspection_newest_first(): void
    {
        $first = Inspection::factory()->create();
        $second = Inspection::factory()->create();
        $third = Inspection::factory()->create();

        $response = $this->getJson('/api/inspections');

        $response->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(
            [$third->id, $second->id, $first->id],
            $response->json('data.*.id'),
        );
    }

    public function test_ac201_list_items_have_the_fields_the_ui_shows(): void
    {
        Inspection::factory()->create([
            'customer_name' => 'Jane Smith',
            'vehicle_registration' => 'AB12 CDE',
        ]);

        $this->getJson('/api/inspections')->assertOk()->assertJsonPath('data.0', fn (array $item) => $item['customer_name'] === 'Jane Smith'
            && $item['vehicle_registration'] === 'AB12 CDE'
            && $item['status'] === 'pending');
    }

    public function test_ac202_list_is_empty_when_there_are_no_inspections(): void
    {
        $this->getJson('/api/inspections')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_ac203_an_inspection_can_be_retrieved_by_id(): void
    {
        $this->travelTo('2026-11-10 09:00:00');
        $other = Inspection::factory()->create();
        $wanted = Inspection::factory()->create([
            'customer_name' => 'Jane Smith',
            'vehicle_registration' => 'AB12 CDE',
            'requested_date' => '2026-12-01',
            'notes' => 'Customer prefers a morning visit.',
        ]);

        $response = $this->getJson("/api/inspections/{$wanted->id}");

        $this->assertNotSame($other->id, $wanted->id);
        $response->assertOk()->assertExactJson([
            'data' => [
                'id' => $wanted->id,
                'customer_name' => 'Jane Smith',
                'vehicle_registration' => 'AB12 CDE',
                'requested_date' => '2026-12-01',
                'notes' => 'Customer prefers a morning visit.',
                'status' => 'pending',
                'inspector_name' => null,
                'next_action' => 'assign',
                'assigned_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'created_at' => '2026-11-10T09:00:00Z',
                'updated_at' => '2026-11-10T09:00:00Z',
            ],
        ]);
    }

    #[DataProvider('unknownIds')]
    public function test_ac204_unknown_id_returns_a_json_404_with_a_fixed_message(string $id): void
    {
        Inspection::factory()->create();

        $this->getJson("/api/inspections/{$id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Inspection not found.']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownIds(): array
    {
        return [
            'a number that matches no inspection' => ['999999'],
            'not a number' => ['abc'],
            'zero' => ['0'],
            'a negative number' => ['-1'],
            'a decimal' => ['1.5'],
            'a number too large to be an id' => ['99999999999999999999999'],
            'text that looks like SQL' => ["1' OR '1'='1"],
        ];
    }

    public function test_ac204_unknown_id_returns_json_without_a_json_accept_header(): void
    {
        $this->get('/api/inspections/999999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Inspection not found.']);
    }

    #[DataProvider('unsupportedMethods')]
    public function test_ac204_a_method_the_api_does_not_offer_returns_a_json_405(string $method, string $path): void
    {
        $inspection = Inspection::factory()->create();
        $before = $inspection->fresh()->toArray();

        $response = $this->json($method, str_replace('{id}', (string) $inspection->id, $path), ['status' => 'completed']);

        $response->assertMethodNotAllowed()->assertJsonStructure(['message']);
        $this->assertSame($before, $inspection->fresh()->toArray());
        $this->assertDatabaseCount('inspections', 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsupportedMethods(): array
    {
        return [
            'PUT one inspection' => ['PUT', '/api/inspections/{id}'],
            'PATCH one inspection' => ['PATCH', '/api/inspections/{id}'],
            'DELETE one inspection' => ['DELETE', '/api/inspections/{id}'],
            'DELETE the list' => ['DELETE', '/api/inspections'],
        ];
    }

    public function test_ac204_an_unknown_path_under_the_api_returns_a_json_404(): void
    {
        $this->get('/api/unknown')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }
}
