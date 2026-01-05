<?php
/**
 * AI-Powered GCash Receipt Validator
 * Uses Groq API with vision capabilities to validate uploaded GCash payment receipts
 * Extracts reference number and validates receipt authenticity
 */

session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Groq API Configuration
define('GROQ_API_KEY', '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');
define('GROQ_API_KEY_FALLBACK', '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'); // Alternative API key for rate limit fallback
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'meta-llama/llama-4-maverick-17b-128e-instruct');

/**
 * Validate GCash receipt image using AI
 */
function validateGCashReceipt($imageBase64, $mimeType) {
    $prompt = <<<PROMPT
You are an AI assistant that validates GCash payment receipts for the eBarangay system. Analyze this image and determine if it is a valid GCash payment receipt that meets our STRICT requirements.

**REQUIRED RECIPIENT INFORMATION (MUST MATCH EXACTLY):**
- Recipient Name: Must show "ER***A O." or similar masked format (ER with asterisks followed by A O.)
- Recipient Number: Must show "+63 965 721 4742" (with or without spaces/formatting)
- Amount: Must be EXACTLY ₱25.00 or 25.00 (the barangay document fee)

**VALID GCASH RECEIPT CHARACTERISTICS:**
1. **GCash Branding**: Must have GCash logo, blue color scheme, or "Sent via GCash" text
2. **Protected Name**: The recipient name MUST be partially hidden/masked (e.g., "ER***A O.")
3. **Recipient Phone**: Must display "+63 965 721 4742" 
4. **Amount**: Must be exactly ₱25.00 (Total Amount Sent: ₱25.00)
5. **Reference Number**: Must have a reference number (13-15 digits, format like "2036446533366" or "2012 120 513868")
6. **Transaction Type**: "Sent via GCash", "Express Send", "Send Money", or similar

**CRITICAL VALIDATION RULES:**
- REJECT if recipient name is NOT "ER***A O." (or very similar masked format)
- REJECT if phone number is NOT "+63 965 721 4742"
- REJECT if amount is NOT ₱25.00
- REJECT if no reference number found
- REJECT if not a GCash receipt

**EXTRACT THE FOLLOWING:**
- Reference Number (IMPORTANT: Extract the FULL reference number, removing any spaces. Usually 13-15 digits)
- Amount (must be 25.00)
- Recipient name (must match ER***A O.)
- Recipient number (must match +63 965 721 4742)

**INVALID RECEIPTS:**
- Wrong recipient (not ER***A O.)
- Wrong phone number (not +63 965 721 4742)
- Wrong amount (not ₱25.00)
- Edited/fake receipts
- Receipts without reference number
- Random images, photos, documents

**RESPOND IN THIS EXACT JSON FORMAT:**
{
    "is_valid": true or false,
    "reference_number": "2036446533366" (digits only, no spaces) or null if not found,
    "amount": "25.00" or null if not found,
    "recipient_name": "ER***A O." or null if not found,
    "recipient_phone": "+63 965 721 4742" or null if not found,
    "has_masked_name": true or false,
    "has_correct_recipient": true or false,
    "has_correct_amount": true or false,
    "has_gcash_branding": true or false,
    "confidence": "high", "medium", or "low",
    "issues": ["list of ALL issues found"],
    "message": "Brief description of validation result"
}

Analyze the image carefully. Be VERY STRICT about the recipient name, phone number, and amount. Only mark as valid if ALL requirements are met.
PROMPT;

    // Prepare the API request
    $requestData = [
        'model' => GROQ_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$mimeType;base64,$imageBase64"
                        ]
                    ]
                ]
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 1000
    ];

    // Try with primary API key first, then fallback if needed
    $apiKeys = [GROQ_API_KEY, GROQ_API_KEY_FALLBACK];
    $response = null;
    $httpCode = 0;
    $curlError = null;
    $lastError = null;
    
    foreach ($apiKeys as $index => $apiKey) {
        $ch = curl_init(GROQ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // If successful response, break the loop
        if (!$curlError && $httpCode === 200) {
            break;
        }
        
        // Check if it's a rate limit error or similar that warrants trying fallback
        if ($httpCode === 429 || $httpCode === 503 || $httpCode >= 500 || $curlError) {
            $lastError = $curlError ? $curlError : json_decode($response, true)['error']['message'] ?? 'API error';
            // Try next API key if available
            if ($index < count($apiKeys) - 1) {
                continue;
            }
        } else {
            // For other errors, don't try fallback
            break;
        }
    }

    if ($curlError) {
        return [
            'success' => false,
            'error' => 'API connection error: ' . $curlError
        ];
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        return [
            'success' => false,
            'error' => 'API error: ' . ($errorData['error']['message'] ?? 'Unknown error') . ($lastError ? ' (Tried fallback key)' : ''),
            'http_code' => $httpCode
        ];
    }

    $responseData = json_decode($response, true);
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error' => 'Invalid API response format'
        ];
    }

    $aiContent = $responseData['choices'][0]['message']['content'];
    
    // Try to extract JSON from the response
    $jsonMatch = preg_match('/\{[\s\S]*\}/', $aiContent, $matches);
    
    if ($jsonMatch) {
        $validationResult = json_decode($matches[0], true);
        if ($validationResult && isset($validationResult['is_valid'])) {
            // Clean up reference number - remove any non-digits
            if (!empty($validationResult['reference_number'])) {
                $validationResult['reference_number'] = preg_replace('/\D/', '', $validationResult['reference_number']);
            }
            return [
                'success' => true,
                'validation' => $validationResult
            ];
        }
    }

    // If JSON parsing failed, try to extract reference number manually
    $refMatch = preg_match('/(\d{4})\s*(\d{3})\s*(\d{6})/', $aiContent, $refMatches);
    $referenceNumber = $refMatch ? $refMatches[1] . $refMatches[2] . $refMatches[3] : null;

    return [
        'success' => true,
        'validation' => [
            'is_valid' => false,
            'reference_number' => $referenceNumber,
            'amount' => null,
            'has_masked_name' => false,
            'has_gcash_branding' => false,
            'confidence' => 'low',
            'issues' => ['Could not fully parse AI response'],
            'message' => 'Manual review required',
            'raw_response' => $aiContent
        ]
    ];
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check for uploaded file
if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['receipt'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
$maxSize = 10 * 1024 * 1024; // 10MB

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid file type. Please upload an image (JPG, PNG, WEBP).'
    ]);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    echo json_encode([
        'success' => false, 
        'error' => 'File too large. Maximum size is 10MB.'
    ]);
    exit;
}

// Read and encode file
$imageData = file_get_contents($file['tmp_name']);
$imageBase64 = base64_encode($imageData);

// Validate with AI
$result = validateGCashReceipt($imageBase64, $mimeType);

echo json_encode($result);
