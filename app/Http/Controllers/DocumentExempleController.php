<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class DocumentExempleController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'id' => 12,
            'title' => 'Contrat de formation',
            'category' => 'Administratif',
        ]);
    }
}
