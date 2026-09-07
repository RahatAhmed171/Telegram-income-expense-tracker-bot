<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class InputParserService
{
    public function parse(string $message)
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $currentDate = now()->toDateString();

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

DATE RULES:
- Current date is: {$currentDate}
- All date fields must use YYYY-MM-DD format.
- If the user says  today , convert it to the current date.
- If the user says  yesterday , convert it to the date before the current date.
- If the user says  tomorrow , convert it to the date after the current date.
- Never return words such as  today ,  yesterday , or  tomorrow  in date fields.
- Return null when no date is specified.

User message:
{$message}
";

        try {

            $response = Http::timeout(15)
                ->post(
                    "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}",
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

        } catch (Throwable $e) {

            Log::error('Gemini API request failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => true,
                'message' => 'AI service is currently unavailable.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Check HTTP response
        |--------------------------------------------------------------------------
        */

        if ($response->failed()) {

            Log::error('Gemini API returned an error', [
                'status' => $response->status(),
            ]);

            if ($response->status() === 429) {
                return [
                    'error' => true,
                    'message' => 'AI service is temporarily unavailable. Please try again later.'
                ];
            }

            return [
                'error' => true,
                'message' => 'AI service returned an error.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Decode API response
        |--------------------------------------------------------------------------
        */

        $data = $response->json();

        if (!is_array($data)) {

            Log::error('Gemini API returned invalid response format');

            return [
                'error' => true,
                'message' => 'Invalid response from AI service.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Extract generated text safely
        |--------------------------------------------------------------------------
        */

        $text =
            $data['candidates'][0]['content']['parts'][0]['text']
            ?? null;

        if (!$text || !is_string($text)) {

            Log::error('Gemini API returned no generated text', [
                'status' => $response->status(),
            ]);

            return [
                'error' => true,
                'message' => 'AI service returned an empty response.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Clean possible Markdown code fences
        |--------------------------------------------------------------------------
        */

        $text = trim($text);

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $text = trim($text);

        /*
        |--------------------------------------------------------------------------
        | Decode LLM JSON
        |--------------------------------------------------------------------------
        */

        $parsed = json_decode($text, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($parsed)
        ) {

            Log::error('Invalid JSON returned by Gemini', [
                'json_error' => json_last_error_msg(),
            ]);

            return [
                'error' => true,
                'message' => 'AI returned an invalid response.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Validate basic structure
        |--------------------------------------------------------------------------
        */

        if (!$this->validateParsedData($parsed)) {

            Log::error('Gemini returned invalid parsed data');

            return [
                'error' => true,
                'message' => 'AI returned invalid financial data.'
            ];
        }

        return $parsed;
    }


    private function isValidDate(?string $date): bool
{
    if ($date === null) {
        return true;
    }

    $parsedDate = \DateTime::createFromFormat('Y-m-d', $date);

    return $parsedDate !== false
        && $parsedDate->format('Y-m-d') === $date;
}

    private function validateParsedData(array $parsed): bool
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
    | Intent
    |--------------------------------------------------------------------------
    */

    if (
        !isset($parsed['intent']) ||
        !is_string($parsed['intent']) ||
        !in_array($parsed['intent'], $allowedIntents, true)
    ) {
        return false;
    }

    $intent = $parsed['intent'];

    /*
    |--------------------------------------------------------------------------
    | Amount
    |--------------------------------------------------------------------------
    */

    if (in_array($intent, ['add_income', 'add_expense'], true)) {

        if (
            !isset($parsed['amount']) ||
            !is_numeric($parsed['amount'])
        ) {
            return false;
        }

        if ((float) $parsed['amount'] <= 0) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Category
    |--------------------------------------------------------------------------
    */

    if (isset($parsed['category']) && !is_null($parsed['category'])) {

        if (
            !is_string($parsed['category']) ||
            trim($parsed['category']) === ''
        ) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Note
    |--------------------------------------------------------------------------
    */

    if (isset($parsed['note']) && !is_null($parsed['note'])) {

        if (!is_string($parsed['note'])) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction date
    |--------------------------------------------------------------------------
    */

    if (
    isset($parsed['transaction_date']) &&
    !is_null($parsed['transaction_date'])
) {
    if (
        !is_string($parsed['transaction_date']) ||
        !$this->isValidDate($parsed['transaction_date'])
    ) {
        return false;
    }
}

foreach (['start_date', 'end_date'] as $dateField) {
    if (
        isset($parsed[$dateField]) &&
        !is_null($parsed[$dateField])
    ) {
        if (
            !is_string($parsed[$dateField]) ||
            !$this->isValidDate($parsed[$dateField])
        ) {
            return false;
        }
    }
}
    /*
    |--------------------------------------------------------------------------
    | Date range
    |--------------------------------------------------------------------------
    */

    if (
        isset($parsed['start_date'], $parsed['end_date']) &&
        $parsed['start_date'] !== null &&
        $parsed['end_date'] !== null
    ) {
        if ($parsed['start_date'] > $parsed['end_date']) {
            return false;
        }
    }

    return true;
}
}