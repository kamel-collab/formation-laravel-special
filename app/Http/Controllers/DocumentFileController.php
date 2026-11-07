<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentFileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentFileController extends Controller
{
    public function store(DocumentFileRequest $request, string $document): JsonResponse
    {
        $path = null;

        try {
            return DB::transaction(function () use ($request, $document, &$path): JsonResponse {
                $record = $request->user()->documents()->lockForUpdate()->findOrFail($document);

                if ($record->file_path !== null) {
                    return response()->json(['message' => 'Ce document possède déjà un fichier.'], 409);
                }

                $path = $request->file('file')->store('documents', 'local');
                if ($path === false) {
                    abort(500, 'Impossible de stocker le fichier.');
                }

                $record->file_path = $path;
                $record->save();

                return response()->json(['message' => 'Fichier enregistré.'], 201);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    public function download(Request $request, string $document): StreamedResponse
    {
        $record = $request->user()->documents()->findOrFail($document);
        $disk = Storage::disk('local');

        if ($record->file_path === null || ! $disk->exists($record->file_path)) {
            abort(404);
        }

        return $disk->download($record->file_path, 'document-'.$record->id.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
