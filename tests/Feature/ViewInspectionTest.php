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
        ];
    }

    public function test_ac204_unknown_id_returns_json_without_a_json_accept_header(): void
    {
        $this->get('/api/inspections/999999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Inspection not found.']);
    }
}
