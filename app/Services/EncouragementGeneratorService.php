<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EncouragementGeneratorService
{
    public function generateVerse(string $moodText): array
    {
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'verseCitation' => ['type' => 'STRING'],
                'verseText' => ['type' => 'STRING'],
                'initialReflection' => ['type' => 'STRING'],
            ],
            'required' => ['verseCitation', 'verseText', 'initialReflection'],
        ];

        $prompt = <<<PROMPT
**Instrucciones:** Eres un erudito biblico y pastor que busca dar consuelo. Un usuario se siente **{$moodText}**. Tu tarea es doble:
1. Encuentra en la Biblia (version Reina Valera 1960) un versiculo que sea profundamente alentador y relevante para este estado de animo.
2. Escribe una reflexion personal y pastoral sobre ese versiculo, actuando como una conversacion con un amigo sabio. La reflexion debe:
   * Reconocer el estado de animo del usuario (ej. "Entiendo que hoy sientes {$moodText}...").
   * Explicar como el versiculo ofrece consuelo o esperanza.
   * Ofrecer un consejo practico y alentador.
   * Tener un tono calido, personal y esperanzador.

**Formato de Salida:** Devuelve la respuesta estrictamente en formato JSON usando las claves: "verseCitation", "verseText" e "initialReflection".
PROMPT;

        return $this->generateJson($prompt, $schema);
    }

    public function generateContext(string $verseCitation): array
    {
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'authorAndDate' => ['type' => 'STRING'],
                'locationAndSociety' => ['type' => 'STRING'],
                'originalMeaning' => ['type' => 'STRING'],
            ],
            'required' => ['authorAndDate', 'locationAndSociety', 'originalMeaning'],
        ];

        $prompt = <<<PROMPT
**Titulo:** Proporciona un contexto historico y teologico con conviccion.
**Instrucciones:** Eres un pastor y erudito biblico. Para el versiculo **{$verseCitation}**, presenta un analisis historico y cultural. La explicacion debe ser directa, sin dudas, y desde una perspectiva de fe que reconoce la Biblia como la Palabra de Dios.
**Puntos Clave a Cubrir:**
* **authorAndDate:** Identifica el autor y el periodo en el que fue escrito el libro.
* **locationAndSociety:** Describe el contexto social y cultural de la audiencia original.
* **originalMeaning:** Explica claramente el significado del versiculo para su publico en esa epoca.

**Formato de Salida:** Devuelve la respuesta estrictamente en formato JSON utilizando las llaves especificadas en el esquema.
PROMPT;

        return $this->generateJson($prompt, $schema);
    }

    public function generatePrayer(string $moodKey, string $verseCitation, string $verseText): string
    {
        $prompt = <<<PROMPT
**Titulo:** Genera una oracion personal basada en el versiculo.
**Instrucciones:** Basado en el versiculo **{$verseCitation}: "{$verseText}"** y el estado de animo del usuario de **{$moodKey}**, crea una oracion modelo.
**Puntos Clave de la Oracion:**
* **Reconocimiento:** Inicia reconociendo a Dios, haciendo referencia al versiculo.
* **Peticion:** Presenta una peticion relacionada con el estado de animo del usuario.
* **Confianza y Fe:** Concluye con una declaracion de fe.

**Tono:** El lenguaje debe ser devocional, humilde y honesto.
PROMPT;

        return $this->generateText($prompt);
    }

    private function generateJson(string $prompt, array $schema): array
    {
        $response = $this->requestGemini($prompt, $schema);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini devolvio una respuesta JSON invalida.');
        }

        return $decoded;
    }

    private function generateText(string $prompt): string
    {
        return trim($this->requestGemini($prompt));
    }

    private function requestGemini(string $prompt, ?array $schema = null): string
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model');
        $apiBaseUrl = rtrim((string) config('services.gemini.api_base_url'), '/');

        if ($apiKey === '') {
            throw new RuntimeException('Falta GEMINI_API_KEY en la configuracion del backend.');
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        if ($schema) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ];
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->post("{$apiBaseUrl}/models/{$model}:generateContent?key={$apiKey}", $payload)
            ->throw()
            ->json();

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new RuntimeException('Respuesta inesperada de Gemini.');
        }

        return $text;
    }
}
