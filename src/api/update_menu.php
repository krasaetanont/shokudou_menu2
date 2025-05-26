<?php

// Start session to check login status
session_start();

// Require the autoloader for Composer dependencies (e.g., Dotenv, Spatie/pdf-to-text if used)
require_once __DIR__ . '/../../vendor/autoload.php';

// Use the database configuration (assuming this class is correctly defined elsewhere)
use App\Config\DatabaseConfig;

// Load environment variables (for database credentials, API keys, etc.)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Set content type to JSON for API responses
header('Content-Type: application/json');

// --- Authentication Check ---
// This assumes your authentication logic sets $_SESSION['access_token'] upon successful login.
$isLoggedIn = isset($_SESSION['access_token']);

if (!$isLoggedIn) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ]);
    exit;
}

// --- Gemini API Key (IMPORTANT: Replace with your actual key or use environment variable) ---
// It's highly recommended to store API keys in environment variables for security.
// Example: getenv('GEMINI_API_KEY')
$geminiApiKey = getenv('GEMINI_API_KEY') ?: ""; // Fallback to empty string if not set

if (empty($geminiApiKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API key is not configured.'
    ]);
    exit;
}

/**
 * Fetches data from the Gemini API.
 *
 * @param string $prompt The prompt text to send to Gemini.
 * @param array $generationConfig Configuration for the generation.
 * @param string $apiKey The Gemini API key.
 * @return array|false The decoded JSON response from Gemini, or false on error.
 */
function callGeminiApi(string $prompt, array $generationConfig, string $apiKey) {
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
 * Extracts food information from PDF text using the Gemini API and inserts it into the database.
 *
 * This function assumes the PDF content has already been extracted into plain text.
 *
 * @param string $pdfText The plain text content extracted from the PDF.
 * @param PDO $db The PDO database connection object.
 * @param string $geminiApiKey The Gemini API key.
 * @return array An associative array with 'success' status and 'message', and 'inserted_count'.
 */
function extractAndInsertFoodInfo(string $pdfText, PDO $db, string $geminiApiKey): array {
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

    $geminiResponse = callGeminiApi($prompt, $generationConfig, $geminiApiKey);
    $insertedCount = 0;
    $errors = [];

    if ($geminiResponse &&
        isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonString = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
        $extractedData = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($extractedData)) {
            // Prepare the SQL INSERT statement
            $stmt = $db->prepare('INSERT INTO menu (name, price, available, available_date, tag) VALUES (:name, :price, TRUE, :available_date, :tag)');

            foreach ($extractedData as $item) {
                // Validate required fields from Gemini's output
                if (!isset($item['name']) || !isset($item['price'])) {
                    $errors[] = "Skipping item due to missing name or price: " . json_encode($item);
                    continue;
                }

                // Sanitize and prepare data for insertion
                $name = filter_var($item['name'], FILTER_SANITIZE_STRING);
                $price = filter_var($item['price'], FILTER_SANITIZE_NUMBER_INT); // Price should be an integer
                $availableDate = isset($item['date']) && $item['date'] ? $item['date'] : null; // Use null if date is not provided
                $tag = isset($item['tag']) && $item['tag'] ? $item['tag'] : null; // Use null if tag is not provided

                try {
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price, PDO::PARAM_INT);
                    $stmt->bindParam(':available_date', $availableDate); // PDO will handle null correctly
                    $stmt->bindParam(':tag', $tag); // PDO will handle null correctly

                    $stmt->execute();
                    $insertedCount++;
                } catch (PDOException $e) {
                    $errors[] = "Database insert error for item '" . $name . "': " . $e->getMessage();
                    error_log("Database insert error: " . $e->getMessage());
                }
            }
            return [
                'success' => true,
                'message' => "Successfully extracted and inserted $insertedCount menu items.",
                'inserted_count' => $insertedCount,
                'errors' => $errors
            ];

        } else {
            error_log("Failed to decode JSON from Gemini response or response is not an array: " . json_last_error_msg());
            return [
                'success' => false,
                'message' => 'Failed to parse Gemini API response.',
                'inserted_count' => 0,
                'errors' => $errors
            ];
        }
    } else {
        error_log("No valid content found in Gemini response or API call failed.");
        return [
            'success' => false,
            'message' => 'Failed to get a valid response from Gemini API.',
            'inserted_count' => 0,
            'errors' => $errors
        ];
    }
}

// --- Conceptual PDF Text Extraction Function ---
// This part is crucial and needs to be implemented based on your server setup.
// It assumes `pdftotext` is installed and accessible, or you're using a PHP library.
function getPdfText(string $pdfFilePath): string {
    // Example using pdftotext (requires poppler-utils on Linux/macOS)
    // Ensure `pdftotext` is in your server's PATH or provide its full path.
    $command = "pdftotext " . escapeshellarg($pdfFilePath) . " -"; // '-' outputs to stdout
    $text = shell_exec($command);
    if ($text === null) {
        error_log("Failed to extract text from PDF. Is 'pdftotext' installed and in PATH?");
        return "";
    }
    return $text;

    // Alternative: Using Spatie/pdf-to-text library (install via Composer: composer require spatie/pdf-to-text)
    /*
    try {
        $text = (new Spatie\PdfToText\Pdf($pdfFilePath))->text();
        return $text;
    } catch (Exception $e) {
        error_log("Error extracting PDF text with Spatie/pdf-to-text: " . $e->getMessage());
        return "";
    }
    */
}


// --- Main Execution Logic ---
// This script expects a POST request with 'pdf_path'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_path'])) {
    $pdfFilePath = filter_var($_POST['pdf_path'], FILTER_SANITIZE_STRING);

    if (!file_exists($pdfFilePath)) {
        echo json_encode([
            'success' => false,
            'message' => 'PDF file not found at specified path: ' . $pdfFilePath
        ]);
        exit;
    }

    try {
        // Get database connection
        $db = DatabaseConfig::getInstance()->getConnection();

        // Extract text from PDF
        $pdfContent = getPdfText($pdfFilePath);

        if (!empty($pdfContent)) {
            // Extract and insert food info into the database
            $result = extractAndInsertFoodInfo($pdfContent, $db, $geminiApiKey);
            echo json_encode($result);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to extract text content from the PDF.'
            ]);
        }

    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log('An unexpected error occurred: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ]);
    }

} else {
    // If not a POST request or missing pdf_path
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or missing PDF path. Please send a POST request with "pdf_path".'
    ]);
}

?>
