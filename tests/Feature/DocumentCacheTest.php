<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    public function test_lists_are_cached_without_repeating_the_query_and_separated_by_user(): void
    {
        $amina = User::factory()->create();
        $karim = User::factory()->create();
        $amina->documents()->create(['title' => 'Document Amina', 'category' => 'Formation']);
        $karim->documents()->create(['title' => 'Document Karim', 'category' => 'Projet']);

        $this->actingAs($amina, 'sanctum');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/documents')->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'Document Amina']);
        $this->assertTrue(Cache::has('documents:user:'.$amina->id));
        $this->assertIsArray(Cache::get('documents:user:'.$amina->id));
        $firstQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from "documents"'))->count();
        $this->assertGreaterThan(0, $firstQueries);

        DB::flushQueryLog();
        $this->getJson('/api/documents')->assertOk()->assertJsonFragment(['title' => 'Document Amina']);
        $secondQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from "documents"'))->count();
        $this->assertSame(0, $secondQueries);
        DB::disableQueryLog();

        $this->actingAs($karim, 'sanctum');
        $this->getJson('/api/documents')->assertOk()->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Document Karim'])
            ->assertJsonMissing(['title' => 'Document Amina']);
        $this->assertTrue(Cache::has('documents:user:'.$karim->id));
    }

    public function test_create_update_and_delete_invalidate_only_the_owners_list(): void
    {
        $amina = User::factory()->create();
        $karim = User::factory()->create();
        $document = $amina->documents()->create(['title' => 'Avant', 'category' => 'Formation']);
        Cache::put('documents:user:'.$karim->id, ['sentinelle'], 300);
        $this->actingAs($amina, 'sanctum');

        $this->getJson('/api/documents')->assertJsonFragment(['title' => 'Avant']);
        $this->putJson('/api/documents/'.$document->id, ['title' => 'Après', 'category' => 'Projet'])->assertOk();
        $this->assertFalse(Cache::has('documents:user:'.$amina->id));
        $this->assertSame(['sentinelle'], Cache::get('documents:user:'.$karim->id));
        $this->getJson('/api/documents')->assertJsonFragment(['title' => 'Après'])->assertJsonMissing(['title' => 'Avant']);

        $this->postJson('/api/documents', ['title' => 'Nouveau', 'category' => 'Formation'])->assertCreated();
        $this->assertFalse(Cache::has('documents:user:'.$amina->id));
        $this->getJson('/api/documents')->assertJsonCount(2);
        $this->deleteJson('/api/documents/'.$document->id)->assertNoContent();
        $this->assertFalse(Cache::has('documents:user:'.$amina->id));
        $this->getJson('/api/documents')->assertJsonCount(1)->assertJsonMissing(['title' => 'Après']);
        $this->assertSame(['sentinelle'], Cache::get('documents:user:'.$karim->id));
    }

    public function test_upload_invalidates_the_list_after_the_file_is_saved(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $document = $user->documents()->create(['title' => 'PDF', 'category' => 'Formation']);
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/documents')->assertOk();
        $this->assertTrue(Cache::has('documents:user:'.$user->id));

        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('exemple.pdf', "%PDF-1.4\nContenu\n%%EOF"),
        ])->assertCreated();
        $this->assertFalse(Cache::has('documents:user:'.$user->id));
        $this->getJson('/api/documents')->assertOk()->assertJsonMissingPath('0.file_path');
    }
}
