<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;

class InputParserService
{
    public function parse(string $message)
    {
        $apiKey = env('GEMINI_API_KEY');

        $prompt = "
You are a finance assistant.

Return STRICT JSON only.

Possible intents:
- add_income
- add_expense
- get_income
- get_expense
- get_summary

Fields:
- intent
- amount
- category
- note
- start_date
- end_date
- transaction_date

If field unavailable use null.

User message:
{$message}
";

        $response = Http::post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey,
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ]
            ]
        );
        
$data = $response->json();

Log::info($data);
        $text =
            $response['candidates'][0]['content']['parts'][0]['text']
            ?? '{}';

        /*
        |--------------------------------------------------------------------------
        | Remove markdown json block if Gemini adds it
        |--------------------------------------------------------------------------
        */

        $text = str_replace('```json', '', $text);

        $text = str_replace('```', '', $text);
        \Log::info($text);

        $parsed = json_decode($text, true);

if (!$parsed || !$this->validateParsedData($parsed)) {

    Log::error('Invalid AI response', [
        'raw_response' => $text
    ]);

    return [
        'error' => true,
        'message' => 'Invalid AI response'
    ];
}

return $parsed;
    }

    private function validateParsedData(array $parsed)
{
    $allowedIntents = [
        'add_income',
        'add_expense',
        'get_income',
        'get_expense',
        'get_summary'
    ];

    /*
    |--------------------------------------------------------------------------
    | Intent validation
    |--------------------------------------------------------------------------
    */

    if (
        !isset($parsed['intent']) ||
        !in_array($parsed['intent'], $allowedIntents)
    ) {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Amount validation
    |--------------------------------------------------------------------------
    */

    if (
        isset($parsed['amount']) &&
        !is_null($parsed['amount']) &&
        !is_numeric($parsed['amount'])
    ) {
        return false;
    }

    return true;
}
    
}