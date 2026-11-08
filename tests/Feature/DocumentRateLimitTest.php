<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DocumentRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    public function test_list_is_limited_per_user_and_recovers_after_one_minute(): void
    {
        $amina = User::factory()->create();
        $karim = User::factory()->create();
        $this->actingAs($amina, 'sanctum');

        for ($number = 1; $number <= 5; $number++) {
            $this->getJson('/api/documents')->assertOk()->assertHeader('X-RateLimit-Limit', '5');
        }

        $blocked = $this->getJson('/api/documents')->assertStatus(429);
        $this->assertGreaterThan(0, (int) $blocked->headers->get('Retry-After'));

        $this->actingAs($karim, 'sanctum');
        $this->getJson('/api/documents')->assertOk()->assertHeader('X-RateLimit-Remaining', '4');

        $this->travel(61)->seconds();
        $this->actingAs($amina, 'sanctum');
        $this->getJson('/api/documents')->assertOk();
    }

    public function test_authentication_precedes_the_limit_and_other_document_routes_remain_available(): void
    {
        for ($number = 0; $number < 6; $number++) {
            $this->getJson('/api/documents')->assertUnauthorized();
        }

        $user = User::factory()->create();
        $document = $user->documents()->create(['title' => 'Exemple', 'category' => 'Formation']);
        $this->actingAs($user, 'sanctum');
        for ($number = 0; $number < 5; $number++) {
            $this->getJson('/api/documents')->assertOk();
        }
        $this->getJson('/api/documents')->assertStatus(429);
        $this->getJson('/api/documents/'.$document->id)->assertOk();
        $this->postJson('/api/documents', ['title' => 'Autre document', 'category' => 'Projet'])->assertCreated();
    }
}
