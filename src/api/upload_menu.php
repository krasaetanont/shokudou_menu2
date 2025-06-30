<?php
// Clean any previous output and start output buffering
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Start session to check login status
session_start();

// Require the autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use App\Config\DatabaseConfig;

// IMPORTANT: Disable error display for production - errors should only go to logs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set default timezone
date_default_timezone_set('Asia/Tokyo');

// Custom error handler to ensure JSON responses
function handleError($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    
    // Clean any output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Send JSON error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred',
        'error_code' => 'PHP_ERROR',
        'debug' => [
            'error' => $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ]
    ]);
    exit;
}

// Custom exception handler
function handleException($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred',
        'error_code' => 'EXCEPTION',
        'debug' => [
            'error' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ]
    ]);
    exit;
}

// Set custom error and exception handlers
set_error_handler('handleError');
set_exception_handler('handleException');

// Function to send JSON response and exit
function sendJsonResponse($data, $httpCode = 200) {
    // Clean any existing output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Function to log debug information
function logDebug($message, $data = null) {
    $logMessage = "[DEBUG] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}

$isLoggedIn = true; // For testing purposes

// Check if user is logged in
if (!$isLoggedIn) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Authentication required.',
        'error_code' => 'AUTH_REQUIRED'
    ], 401);
}

/**
 * Process the uploaded PDF by calling the update_menu.php functionality
 */
function processUploadedMenu($pdfFilePath) {
    logDebug("Starting PDF processing", ['file' => $pdfFilePath]);
    
    $geminiApiKey = $_ENV['GEMINI_API_KEY'] ?? "";

    if (empty($geminiApiKey)) {
        error_log('Gemini API key is not configured.');
        return [
            'success' => false,
            'message' => 'Gemini API key is not configured.',
            'error_code' => 'MISSING_API_KEY'
        ];
    }

    try {
        // Get database connection
        $db = DatabaseConfig::getInstance()->getConnection();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Extract text from PDF
        $pdfContent = getPdfText($pdfFilePath);

        if (!empty($pdfContent)) {
            $fileName = basename($pdfFilePath);
            $result = extractAndInsertFoodInfo($pdfContent, $db, $geminiApiKey, $fileName);
            logDebug("PDF processing completed", ['result' => $result]);
            return $result;
        } else {
            error_log('Failed to extract text content from the PDF: ' . $pdfFilePath);
            return [
                'success' => false,
                'message' => 'Failed to extract text content from the PDF. Check PDF file content or pdftotext installation.',
                'error_code' => 'PDF_EXTRACTION_FAILED'
            ];
        }

    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database connection error occurred',
            'error_code' => 'DB_CONNECTION_ERROR',
            'debug' => ['error' => $e->getMessage()]
        ];
    } catch (Exception $e) {
        error_log('An unexpected error occurred during processing: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred during processing',
            'error_code' => 'PROCESSING_ERROR',
            'debug' => ['error' => $e->getMessage()]
        ];
    }
}

/**
 * Fetches data from the Gemini API.
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    logDebug("Gemini API call", [
        'http_code' => $httpCode,
        'curl_errno' => $curlErrno,
        'response_length' => strlen($response)
    ]);

    if ($response === false) {
        $errorMessage = "cURL error ($curlErrno): " . $curlError;
        error_log($errorMessage);
        return false;
    }

    if ($httpCode !== 200) {
        $errorMessage = "Gemini API error (HTTP $httpCode): " . $response;
        error_log($errorMessage);
        return false;
    }

    $decodedResponse = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMessage = "JSON decode error from Gemini response: " . json_last_error_msg();
        error_log($errorMessage);
        return false;
    }

    return $decodedResponse;
}

/**
 * Extracts food information from PDF text using the Gemini API and inserts it into the database.
 */
function extractAndInsertFoodInfo(string $pdfText, PDO $db, string $geminiApiKey, string $fileName): array {
    // Extract year and month from filename
    $year = '';
    $month = '';
    $fileInfo = '';
    if (preg_match('/^(\d{4})\.(0[1-9]|1[0-2])\.pdf$/', $fileName, $matches)) {
        $year = $matches[1];
        $month = $matches[2];
        $fileInfo = "The filename is '{$fileName}' which indicates this menu is for {$year}-{$month}. ";
    } else {
        error_log("Filename '{$fileName}' does not match YYYY.MM.pdf format for date extraction.");
        $fileInfo = "The filename did not provide year/month information. ";
    }

    $prompt = "{$fileInfo}Given the following text from a food list, extract the name, price, date (if available, format as YYYY-MM-DD), and a tag from the following categories: 'A ランチ', 'B ランチ', 'カレー', '丼', '中華麺', '和麺', 'ご飯'.

    IMPORTANT: If specific dates are not mentioned in the text for individual items, and the filename provided year/month information, use the year and month from the filename to generate appropriate dates starting from the 1st of that month, incrementing for each subsequent item without a specific date.

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
                    'tag' => ['type' => 'STRING', 'enum' => ['A ランチ', 'B ランチ', 'カレー', '丼', '中華麺', '和麺', 'ご飯'], 'nullable' => true]
                ],
                'required' => ['name', 'price']
            ]
        ]
    ];

    $geminiResponse = callGeminiApi($prompt, $generationConfig, $geminiApiKey);
    $insertedCount = 0;
    $errors = [];

    if ($geminiResponse && isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonString = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
        $extractedData = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($extractedData)) {
            $stmt = $db->prepare('INSERT INTO menu (name, price, available, available_date, tag) VALUES (:name, :price, TRUE, :available_date, :tag)');
            $currentDay = 1;

            foreach ($extractedData as $item) {
                if (!isset($item['name']) || !isset($item['price'])) {
                    $errors[] = "Skipping item due to missing 'name' or 'price': " . json_encode($item);
                    continue;
                }

                $name = trim($item['name']);
                $price = (int)$item['price'];
                $tag = isset($item['tag']) && $item['tag'] ? trim($item['tag']) : null;

                $availableDate = null;
                if (isset($item['date']) && $item['date']) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['date'])) {
                        $availableDate = $item['date'];
                    } else {
                        $errors[] = "Invalid date format for item '{$name}': {$item['date']}";
                    }
                }

                if ($availableDate === null && !empty($year) && !empty($month)) {
                    $day = str_pad($currentDay, 2, '0', STR_PAD_LEFT);
                    $generatedDate = "{$year}-{$month}-{$day}";

                    if (checkdate((int)$month, (int)$day, (int)$year)) {
                        $availableDate = $generatedDate;
                        $currentDay++;
                    } else {
                        $errors[] = "Generated invalid date for item '{$name}': {$generatedDate}";
                    }
                }

                try {
                    $stmt->execute([
                        ':name' => $name,
                        ':price' => $price,
                        ':available_date' => $availableDate,
                        ':tag' => $tag
                    ]);
                    $insertedCount++;
                } catch (PDOException $e) {
                    $errorMsg = "Database insert error for item '{$name}': " . $e->getMessage();
                    $errors[] = $errorMsg;
                    error_log($errorMsg);
                }
            }

            return [
                'success' => true,
                'message' => "Successfully extracted and inserted $insertedCount menu items.",
                'inserted_count' => $insertedCount,
                'errors' => $errors
            ];

        } else {
            $jsonError = json_last_error_msg();
            error_log("Failed to decode JSON from Gemini response. JSON Error: {$jsonError}");
            return [
                'success' => false,
                'message' => "Failed to parse Gemini API response",
                'error_code' => 'GEMINI_JSON_PARSE_ERROR',
                'inserted_count' => 0,
                'errors' => $errors
            ];
        }
    } else {
        $geminiErrorMessage = "No valid content found in Gemini response or API call failed.";
        if (isset($geminiResponse['error']['message'])) {
            $geminiErrorMessage .= " Error: " . $geminiResponse['error']['message'];
        }
        error_log($geminiErrorMessage);
        return [
            'success' => false,
            'message' => 'Failed to get a valid response from Gemini API',
            'error_code' => 'GEMINI_API_ERROR',
            'inserted_count' => 0,
            'errors' => $errors
        ];
    }
}

/**
 * Extract text from PDF file
 */
function getPdfText(string $pdfFilePath): string {
    $commandCheck = shell_exec("which pdftotext 2>/dev/null");
    if (empty($commandCheck)) {
        error_log("'pdftotext' command not found. Please install poppler-utils.");
        return "";
    }

    $command = "pdftotext " . escapeshellarg($pdfFilePath) . " - 2>&1";
    $text = shell_exec($command);

    if ($text === null) {
        error_log("Failed to execute 'pdftotext' command for file: " . $pdfFilePath);
        return "";
    } elseif (stripos($text, "error") !== false) {
        error_log("pdftotext reported an error for file: " . $pdfFilePath . ". Output: " . $text);
        return "";
    }

    return $text;
}

// Main request handling
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse([
            'success' => false,
            'message' => 'Only POST requests are allowed',
            'error_code' => 'INVALID_METHOD'
        ], 405);
    }

    if (!isset($_FILES['file'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'No file uploaded',
            'error_code' => 'NO_FILE'
        ], 400);
    }

    $file = $_FILES['file'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped the file upload'
        ];

        $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error';
        error_log("File upload error: " . $errorMessage . " (Code: " . $file['error'] . ")");
        
        sendJsonResponse([
            'success' => false,
            'message' => $errorMessage,
            'error_code' => 'UPLOAD_ERROR'
        ], 400);
    }

    // Validate filename format
    $pattern = "/^\d{4}\.(0[1-9]|1[0-2])\.pdf$/";
    if (!preg_match($pattern, $file['name'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid file name format. Please use YYYY.MM.pdf format (e.g., 2025.06.pdf).',
            'error_code' => 'INVALID_FILENAME'
        ], 400);
    }

    $uploadDir = '/home/team1/public_html/toyouke/menuFile';
    
    // Check directory
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Could not create upload directory: " . $uploadDir);
            sendJsonResponse([
                'success' => false,
                'message' => 'Upload directory could not be created',
                'error_code' => 'DIRECTORY_ERROR'
            ], 500);
        }
    }

    if (!is_writable($uploadDir)) {
        error_log("Upload directory is not writable: " . $uploadDir);
        sendJsonResponse([
            'success' => false,
            'message' => 'Upload directory is not writable',
            'error_code' => 'DIRECTORY_PERMISSION_ERROR'
        ], 500);
    }

    $uploadFile = rtrim($uploadDir, '/') . '/' . basename($file['name']);

    // Check if file already exists
    if (file_exists($uploadFile)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'File with this name already exists. Please rename the file and try again.',
            'error_code' => 'FILE_EXISTS',
            'existing_file' => basename($file['name'])
        ], 409);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
        error_log("Failed to move uploaded file from {$file['tmp_name']} to {$uploadFile}");
        sendJsonResponse([
            'success' => false,
            'message' => 'Failed to save uploaded file',
            'error_code' => 'FILE_MOVE_ERROR'
        ], 500);
    }

    // Process the uploaded file
    $processingResult = processUploadedMenu($uploadFile);

    if ($processingResult['success']) {
        sendJsonResponse([
            'success' => true,
            'message' => 'File uploaded and processed successfully',
            'fileName' => basename($file['name']),
            'processing_result' => $processingResult
        ], 200);
    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'File uploaded but processing failed',
            'fileName' => basename($file['name']),
            'processing_result' => $processingResult
        ], 500);
    }

} catch (Exception $e) {
    error_log("Uncaught exception in main: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'An unexpected error occurred',
        'error_code' => 'UNEXPECTED_ERROR'
    ], 500);
}
?>