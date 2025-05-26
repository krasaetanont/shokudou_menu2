<?php

// Start session to check login status
session_start();

// Require the autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use App\Config\DatabaseConfig;
// Set content type to JSON
header('Content-Type: application/json');

if (isset($_SESSION['access_token'])) {
    // User is logged in
    $isLoggedIn = true;
} else {
    // User is not logged in
    $isLoggedIn = false;
}

// Check if user is logged in
if (!$isLoggedIn) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

/**
 * Process the uploaded PDF by calling the update_menu.php functionality
 * 
 * @param string $pdfFilePath Path to the uploaded PDF file
 * @return array Result of the processing operation
 */
function processUploadedMenu($pdfFilePath) {
    // Since we can't directly include update_menu.php (it has its own response handling),
    // we'll replicate its core functionality here
    
    // Load database configuration
    
    // Get Gemini API key
    $geminiApiKey = $_ENV['GEMINI_API_KEY'] ?: "";
    
    if (empty($geminiApiKey)) {
        return [
            'success' => false,
            'message' => 'Gemini API key is not configured.'
        ];
    }
    
    try {
        // Get database connection
        $db = DatabaseConfig::getInstance()->getConnection();
        
        // Extract text from PDF
        $pdfContent = getPdfText($pdfFilePath);
        
        if (!empty($pdfContent)) {
            // Extract filename from path for date information
            $fileName = basename($pdfFilePath);
            
            // Extract and insert food info into the database
            $result = extractAndInsertFoodInfo($pdfContent, $db, $geminiApiKey, $fileName);
            return $result;
        } else {
            return [
                'success' => false,
                'message' => 'Failed to extract text content from the PDF.'
            ];
        }
        
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database connection error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log('An unexpected error occurred: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ];
    }
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
 * @param string $pdfText The plain text content extracted from the PDF.
 * @param PDO $db The PDO database connection object.
 * @param string $geminiApiKey The Gemini API key.
 * @param string $fileName The filename of the PDF (e.g., "2025.06.pdf")
 * @return array An associative array with 'success' status and 'message', and 'inserted_count'.
 */
function extractAndInsertFoodInfo(string $pdfText, PDO $db, string $geminiApiKey, string $fileName): array {
    // Extract year and month from filename (e.g., "2025.06.pdf" -> year: 2025, month: 06)
    $fileInfo = '';
    if (preg_match('/^(\d{4})\.(\d{2})\.pdf$/', $fileName, $matches)) {
        $year = $matches[1];
        $month = $matches[2];
        $fileInfo = "The filename is '{$fileName}' which indicates this menu is for {$year}-{$month}. ";
    }
    
    $prompt = "{$fileInfo}Given the following text from a food list, extract the name, price, date (if available, format as YYYY-MM-DD), and a tag from the following categories: 'Set A', 'Set B', 'カレー', '丼', '中華麺', '和麺', 'ご飯'. 

    IMPORTANT: The filename indicates the year and month for this menu. If specific dates are not mentioned in the text for individual items, use the year and month from the filename to generate appropriate dates. For example, if the filename is '2025.06.pdf', items without specific dates should be assigned dates in June 2025 (like 2025-06-01, 2025-06-02, etc.).

    If a tag cannot be determined from the text, leave it null. Provide the output as a JSON array of objects.

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
                'required' => ['name', 'price']
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
                $price = filter_var($item['price'], FILTER_SANITIZE_NUMBER_INT);
                $availableDate = isset($item['date']) && $item['date'] ? $item['date'] : null;
                $tag = isset($item['tag']) && $item['tag'] ? $item['tag'] : null;

                try {
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price, PDO::PARAM_INT);
                    $stmt->bindParam(':available_date', $availableDate);
                    $stmt->bindParam(':tag', $tag);

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

/**
 * Extract text from PDF file
 * 
 * @param string $pdfFilePath Path to the PDF file
 * @return string Extracted text content
 */
function getPdfText(string $pdfFilePath): string {
    // Example using pdftotext (requires poppler-utils on Linux/macOS)
    $command = "pdftotext " . escapeshellarg($pdfFilePath) . " -";
    $text = shell_exec($command);
    if ($text === null) {
        error_log("Failed to extract text from PDF. Is 'pdftotext' installed and in PATH?");
        return "";
    }
    return $text;
}

// Only process POST requests with file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $pattern = "/^\d{4}\.(0[1-9]|1[0-2])\.pdf$/";

    if (!preg_match($pattern, $file['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid file name format. Please use YYYY.MM.pdf format.'
        ]);
        exit;
    }

    $uploadDir = '/var/www/html/shokudouMenu2/menuFile/';
    $uploadFile = $uploadDir . basename($file['name']);

    if (file_exists($uploadFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'File already exists. Please rename the file and try again.'
        ]);
        exit;
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
        // File uploaded successfully, now process it
        $processingResult = processUploadedMenu($uploadFile);
        
        if ($processingResult['success']) {
            // Both upload and processing were successful
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded and processed successfully. ' . $processingResult['message'],
                'fileName' => htmlspecialchars($file['name']),
                'processing_result' => $processingResult
            ]);
        } else {
            // Upload succeeded but processing failed
            echo json_encode([
                'success' => false,
                'message' => 'File uploaded successfully, but processing failed: ' . $processingResult['message'],
                'fileName' => htmlspecialchars($file['name']),
                'processing_result' => $processingResult
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error uploading file.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or invalid request method.'
    ]);
}