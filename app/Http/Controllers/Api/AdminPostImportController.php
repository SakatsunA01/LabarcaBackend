<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiNewsGeneratorService;
use App\Services\NewsImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminPostImportController extends Controller
{
    public function __construct(
        private readonly NewsImportService $newsImportService,
        private readonly GeminiNewsGeneratorService $geminiNewsGeneratorService,
    ) {
    }

    public function candidates()
    {
        return response()->json($this->newsImportService->getCandidates());
    }

    public function generate(Request $request)
    {
        $date = $request->query('date');
        $count = (int) $request->query('count', 6);

        if (!$date) {
            $date = now()->toDateString();
        }

        try {
            Carbon::parse($date);
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $count = max(1, min(10, $count));

        return response()->json(
            $this->geminiNewsGeneratorService->generateCandidates($date, $count)
        );
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
