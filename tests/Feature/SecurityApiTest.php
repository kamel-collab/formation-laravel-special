<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_and_token_revocation(): void
    {
        User::factory()->create(['email' => 'amina@example.test', 'password' => 'Formation123!']);

        $this->getJson('/api/documents')->assertUnauthorized();
        $this->postJson('/api/login', [
            'email' => 'amina@example.test',
            'password' => 'incorrect',
        ])->assertUnauthorized();

        $token = $this->postJson('/api/login', [
            'email' => 'amina@example.test',
            'password' => 'Formation123!',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/documents')->assertOk();
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/documents')->assertUnauthorized();
    }

    public function test_users_cannot_access_other_users_or_unowned_documents(): void
    {
        $amina = User::factory()->create();
        $karim = User::factory()->create();
        $document = $amina->documents()->create(['title' => 'Privé', 'category' => 'Formation']);
        $legacy = Document::create(['title' => 'Ancien', 'category' => 'Formation']);

        $this->actingAs($karim, 'sanctum');
        $this->getJson('/api/documents')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/documents/'.$document->id)->assertNotFound();
        $this->putJson('/api/documents/'.$document->id, ['title' => 'Autre', 'category' => 'Formation'])->assertNotFound();
        $this->deleteJson('/api/documents/'.$document->id)->assertNotFound();
        $this->getJson('/api/documents/'.$legacy->id)->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'title' => 'Privé']);
        $this->assertDatabaseHas('documents', ['id' => $legacy->id, 'user_id' => null]);
    }
}
