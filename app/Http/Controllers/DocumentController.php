<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Document::all());
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document);
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = Document::create($data);

        return response()->json($document, 201);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();

        $document->update($data);

        return response()->json($document);
    }

    public function destroy(Document $document): Response
    {
        $document->delete();

        return response()->noContent();
    }
}
