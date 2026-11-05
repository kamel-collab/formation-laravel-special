<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExempleDocumentTest extends TestCase
{
    public function test_exemple_document_returns_json(): void
    {
        $response = $this->getJson('/api/exemples/document');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'id' => 12,
                'title' => 'Contrat de formation',
                'category' => 'Administratif',
            ]);
    }

    public function test_exemple_document_does_not_accept_post(): void
    {
        $this->postJson('/api/exemples/document')->assertStatus(405);
    }
}
