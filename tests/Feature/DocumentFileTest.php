<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_pdf_is_available_only_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = $owner->documents()->create(['title' => 'Contrat', 'category' => 'Formation']);
        $contents = "%PDF-1.4\nContenu de test\n%%EOF";

        $this->getJson('/api/documents/'.$document->id.'/file')->assertUnauthorized();
        $this->actingAs($owner, 'sanctum')->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('contrat.pdf', $contents),
        ])->assertCreated();

        $path = $document->fresh()->file_path;
        Storage::disk('local')->assertExists($path);
        $this->getJson('/api/documents/'.$document->id)->assertJsonMissingPath('file_path');
        $this->get('/api/documents/'.$document->id.'/file')->assertOk()->assertDownload('document-'.$document->id.'.pdf');
        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('autre.pdf', $contents),
        ])->assertStatus(409);

        $this->actingAs($other, 'sanctum');
        $this->getJson('/api/documents/'.$document->id.'/file')->assertNotFound();
        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('autre.pdf', $contents),
        ])->assertNotFound();

        $this->actingAs($owner, 'sanctum')->deleteJson('/api/documents/'.$document->id)->assertNoContent();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_invalid_uploads_are_rejected_without_storing_a_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $document = $user->documents()->create(['title' => 'Contrat', 'category' => 'Formation']);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('faux.pdf', 'Ce fichier est du texte.'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->createWithContent('document.txt', '%PDF-1.4'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/documents/'.$document->id.'/file', [
            'file' => UploadedFile::fake()->create('grand.pdf', 1025, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertNull($document->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }
}
