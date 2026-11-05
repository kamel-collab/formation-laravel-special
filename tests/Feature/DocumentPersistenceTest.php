<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentPersistenceTest extends TestCase
{
    public function test_document_can_be_saved_in_an_isolated_database(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');

        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);

        $this->assertTrue(Schema::connection('sqlite')->hasColumns('documents', [
            'id', 'title', 'category', 'created_at', 'updated_at',
        ]));

        $document = Document::create([
            'title' => 'Contrat de formation',
            'category' => 'Administratif',
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Contrat de formation',
            'category' => 'Administratif',
        ], 'sqlite');
        $this->assertSame('Contrat de formation', $document->fresh()->title);
        $this->assertNotNull($document->created_at);
    }
}
