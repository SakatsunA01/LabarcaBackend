<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpotifyReleaseImportService;
use Illuminate\Http\Request;

class AdminLanzamientoImportController extends Controller
{
    public function __construct(
        private readonly SpotifyReleaseImportService $spotifyReleaseImportService
    ) {
    }

    public function candidates()
    {
        return response()->json($this->spotifyReleaseImportService->getCandidates());
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
            $this->spotifyReleaseImportService->importCandidates($candidates)
        );
    }
}
