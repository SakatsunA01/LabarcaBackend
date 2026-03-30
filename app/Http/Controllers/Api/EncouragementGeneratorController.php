<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EncouragementGeneratorService;
use Illuminate\Http\Request;

class EncouragementGeneratorController extends Controller
{
    public function __construct(
        private readonly EncouragementGeneratorService $encouragementGeneratorService
    ) {
    }

    public function generateVerse(Request $request)
    {
        $payload = $request->validate([
            'moodText' => ['required', 'string', 'max:120'],
        ]);

        return response()->json(
            $this->encouragementGeneratorService->generateVerse($payload['moodText'])
        );
    }

    public function generateContext(Request $request)
    {
        $payload = $request->validate([
            'verseCitation' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->encouragementGeneratorService->generateContext($payload['verseCitation'])
        );
    }

    public function generatePrayer(Request $request)
    {
        $payload = $request->validate([
            'moodKey' => ['required', 'string', 'max:80'],
            'verseCitation' => ['required', 'string', 'max:255'],
            'verseText' => ['required', 'string', 'max:5000'],
        ]);

        return response()->json([
            'text' => $this->encouragementGeneratorService->generatePrayer(
                $payload['moodKey'],
                $payload['verseCitation'],
                $payload['verseText']
            ),
        ]);
    }
}
