<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->documents()->get());
    }

    public function show(Request $request, string $document): JsonResponse
    {
        return response()->json($request->user()->documents()->findOrFail($document));
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = $request->user()->documents()->create($data);

        return response()->json($document, 201);
    }

    public function update(DocumentRequest $request, string $document): JsonResponse
    {
        $data = $request->validated();

        $document = $request->user()->documents()->findOrFail($document);
        $document->update($data);

        return response()->json($document);
    }

    public function destroy(Request $request, string $document): Response
    {
        $request->user()->documents()->findOrFail($document)->delete();

        return response()->noContent();
    }
}
