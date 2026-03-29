<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NewsImportService;
use Illuminate\Http\Request;

class AdminPostImportController extends Controller
{
    public function __construct(
        private readonly NewsImportService $newsImportService
    ) {
    }

    public function candidates()
    {
        return response()->json($this->newsImportService->getCandidates());
    }

    public function import(Request $request)
    {
        $candidates = $request->input('candidates', []);

        if (!is_array($candidates) || empty($candidates)) {
            return response()->json([
                'message' => 'Debes enviar al menos un candidato para importar.',
            ], 422);
        }

        return response()->json(
            $this->newsImportService->importCandidates($candidates)
        );
    }
}
