<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_can_be_listed_and_retrieved(): void
    {
        $document = Document::create(['title' => 'Contrat', 'category' => 'Administratif']);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $document->id, 'title' => 'Contrat']);

        $this->getJson('/api/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('category', 'Administratif');
    }

    public function test_document_can_be_created_updated_and_deleted(): void
    {
        $response = $this->postJson('/api/documents', [
            'title' => 'Contrat',
            'category' => 'Administratif',
        ])->assertCreated()->assertJsonPath('title', 'Contrat');

        $id = $response->json('id');
        $this->assertDatabaseHas('documents', ['id' => $id, 'title' => 'Contrat']);

        $this->putJson('/api/documents/'.$id, [
            'title' => 'Contrat mis à jour',
            'category' => 'Formation',
        ])->assertOk()->assertJsonPath('title', 'Contrat mis à jour');

        $this->assertDatabaseHas('documents', ['id' => $id, 'category' => 'Formation']);
        $this->deleteJson('/api/documents/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('documents', ['id' => $id]);
        $this->getJson('/api/documents/'.$id)->assertNotFound();
    }

    public function test_invalid_data_is_rejected_on_create_and_update(): void
    {
        $document = Document::create(['title' => 'Original', 'category' => 'Formation']);

        $this->postJson('/api/documents', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'category']);

        $this->postJson('/api/documents', [
            'title' => str_repeat('a', 101),
            'category' => str_repeat('b', 51),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'category']);

        $this->putJson('/api/documents/'.$document->id, [
            'title' => 'Modifié',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Original',
            'category' => 'Formation',
        ]);
    }

    public function test_unknown_document_returns_not_found(): void
    {
        $this->getJson('/api/documents/999999')->assertNotFound();
        $this->putJson('/api/documents/999999', [
            'title' => 'Absent',
            'category' => 'Formation',
        ])->assertNotFound();
        $this->deleteJson('/api/documents/999999')->assertNotFound();
    }
}
