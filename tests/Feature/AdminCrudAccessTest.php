<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrudAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_reads_remain_public(): void
    {
        $evento = Evento::create([
            'nombre' => 'Evento Publico',
            'fecha' => now()->addWeek(),
            'lugar' => 'Teatro Central',
            'imagenUrl' => '/public/storage/eventos/demo.jpg',
        ]);

        $this->getJson('/api/eventos')->assertOk();
        $this->getJson("/api/eventos/{$evento->id}")->assertOk();
    }

    public function test_product_reads_remain_public(): void
    {
        $product = Product::create([
            'name' => 'General',
            'price_ars' => 10000,
            'stock' => 20,
            'is_active' => true,
        ]);

        $this->getJson('/api/products')->assertOk();
        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_guest_cannot_write_events_or_products(): void
    {
        $this->postJson('/api/eventos', [])->assertUnauthorized();
        $this->postJson('/api/products', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_write_events_or_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['admin_sn' => false]));

        $eventPayload = [
            'nombre' => 'Evento Restringido',
            'fecha' => now()->addWeek()->toISOString(),
            'lugar' => 'Auditorio',
            'imagenUrl' => '/public/storage/eventos/demo.jpg',
        ];

        $productPayload = [
            'name' => 'VIP',
            'price_ars' => 15000,
            'stock' => 10,
            'is_active' => true,
        ];

        $this->postJson('/api/eventos', $eventPayload)->assertForbidden();
        $this->postJson('/api/products', $productPayload)->assertForbidden();
    }

    public function test_admin_can_write_events_and_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['admin_sn' => true]));

        $eventPayload = [
            'nombre' => 'Evento Admin',
            'fecha' => now()->addWeek()->toISOString(),
            'lugar' => 'Microestadio',
            'imagenUrl' => '/public/storage/eventos/demo.jpg',
        ];

        $productPayload = [
            'name' => 'Entrada Admin',
            'price_ars' => 18000,
            'stock' => 50,
            'is_active' => true,
        ];

        $this->postJson('/api/eventos', $eventPayload)->assertCreated();
        $this->postJson('/api/products', $productPayload)->assertCreated();
    }
}
