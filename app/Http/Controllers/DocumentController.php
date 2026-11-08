<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $key = 'documents:user:'.$request->user()->id;
        $documents = Cache::remember($key, now()->addMinutes(5),
            fn () => $request->user()->documents()->get()->toArray());

        return response()->json($documents);
    }

    public function show(Request $request, string $document): JsonResponse
    {
        return response()->json($request->user()->documents()->findOrFail($document));
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = $request->user()->documents()->create($data);
        Cache::forget('documents:user:'.$request->user()->id);

        return response()->json($document, 201);
    }

    public function update(DocumentRequest $request, string $document): JsonResponse
    {
        $data = $request->validated();

        $document = $request->user()->documents()->findOrFail($document);
        $document->update($data);
        Cache::forget('documents:user:'.$request->user()->id);

        return response()->json($document);
    }

    public function destroy(Request $request, string $document): Response
    {
        DB::transaction(function () use ($request, $document): void {
            $record = $request->user()->documents()->lockForUpdate()->findOrFail($document);

            if ($record->file_path !== null && ! Storage::disk('local')->delete($record->file_path)) {
                abort(500, 'Impossible de supprimer le fichier.');
            }

            $record->delete();
        });
        Cache::forget('documents:user:'.$request->user()->id);

        return response()->noContent();
    }
}
