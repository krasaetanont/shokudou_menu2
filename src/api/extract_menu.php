<?php

/**
 * Fetches data from the Gemini API.
 *
 * @param string $prompt The prompt text to send to Gemini.
 * @param array $generationConfig Configuration for the generation.
 * @return array|false The decoded JSON response from Gemini, or false on error.
 */
function callGeminiApi(string $prompt, array $generationConfig) {
    $apiKey = "AIzaSyC8h3EZROLsxWYF1TyBkVwInAbaDWuonvY"; // IMPORTANT: Replace with your actual Gemini API Key.
                  // For security, consider storing this in environment variables.

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => $generationConfig
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("cURL error: " . curl_error($ch));
        return false;
    }

    if ($httpCode !== 200) {
        error_log("Gemini API error (HTTP $httpCode): " . $response);
        return false;
    }

    $decodedResponse = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return false;
    }

    return $decodedResponse;
}

/**
 * Extracts food information from PDF text using the Gemini API.
 *
 * This function assumes the PDF content has already been extracted into plain text.
 *
 * @param string $pdfText The plain text content extracted from the PDF.
 * @return array An array of extracted food items, or an empty array on failure.
 */
function extractFoodInfoFromPdfText(string $pdfText): array {
    $prompt = "Given the following text from a food list, extract the name, price, date (if available, format as YYYY-MM-DD), and a tag from the following categories: 'Set A', 'Set B', 'カレー', '丼', '中華麺', '和麺', 'ご飯'. If a date is not explicitly mentioned for an item, infer it from surrounding text or leave it null. If a tag cannot be determined, leave it null. Provide the output as a JSON array of objects.

    Example expected JSON structure:
    [
      {
        \"name\": \"Item Name\",
        \"price\": 123,
        \"date\": \"YYYY-MM-DD\",
        \"tag\": \"Tag Category\"
      }
    ]

    Here is the text:
    " . $pdfText;

    $generationConfig = [
        'responseMimeType' => 'application/json',
        'responseSchema' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'name' => ['type' => 'STRING'],
                    'price' => ['type' => 'NUMBER'],
                    'date' => ['type' => 'STRING', 'nullable' => true],
                    'tag' => ['type' => 'STRING', 'enum' => ['Set A', 'Set B', 'カレー', '丼', '中華麺', '和麺', 'ご飯'], 'nullable' => true]
                ],
                'required' => ['name', 'price'] // Name and price are always required
            ]
        ]
    ];

    $geminiResponse = callGeminiApi($prompt, $generationConfig);

    if ($geminiResponse &&
        isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonString = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
        $extractedData = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $extractedData;
        } else {
            error_log("Failed to decode JSON from Gemini response: " . json_last_error_msg());
            return [];
        }
    } else {
        error_log("No valid content found in Gemini response.");
        return [];
    }
}

// --- How to use it (Conceptual) ---

// Step 1: Get the PDF content as plain text.
// This is the part you need to implement using a PDF parsing library/tool.
// For demonstration, let's assume you have a function like this:
function getPdfText(string $pdfFilePath): string {
    // Example using pdftotext (requires pdftotext to be installed on your server)
    // You might need to adjust the path to pdftotext.
    $command = "pdftotext " . escapeshellarg($pdfFilePath) . " -"; // '-' outputs to stdout
    $text = shell_exec($command);
    if ($text === null) {
        error_log("Failed to extract text from PDF. Is 'pdftotext' installed and in PATH?");
        return "";
    }
    return $text;

    // Alternatively, using a PHP library like Spatie/pdf-to-text:
    // require 'vendor/autoload.php';
    // try {
    //     $text = (new Spatie\PdfToText\Pdf($pdfFilePath))->text();
    //     return $text;
    // } catch (Exception $e) {
    //     error_log("Error extracting PDF text with Spatie/pdf-to-text: " . $e->getMessage());
    //     return "";
    // }
}

// Example usage:
$pdfFilePath = '/var/www/html/shokudouMenu2/menuFile/2025.06.pdf'; // Replace with the actual path to your PDF file

$pdfContent = getPdfText($pdfFilePath);

if (!empty($pdfContent)) {
    $foodItems = extractFoodInfoFromPdfText($pdfContent);

    if (!empty($foodItems)) {
        echo "Extracted Food Items:\n";
        print_r($foodItems);
    } else {
        echo "Could not extract food items from the PDF text.\n";
    }
} else {
    echo "Failed to get text content from the PDF.\n";
}

?>