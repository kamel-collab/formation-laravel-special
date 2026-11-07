<?php

namespace Tests\Feature;

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
}
