<?php
/**
 * AI-Powered Valid ID Checker
 * Uses Groq API with vision capabilities to validate uploaded government IDs
 * Checks for: ID number, photo, name match, and birthday
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
define('GROQ_MODEL', 'meta-llama/llama-4-maverick-17b-128e-instruct'); // Llama 4 Maverick model

/**
 * Validate uploaded ID image using AI
 */
function validateIdWithAI($imageBase64, $mimeType, $residentData) {
    $firstName = $residentData['first_name'] ?? '';
    $lastName = $residentData['last_name'] ?? '';
    $middleName = $residentData['middle_name'] ?? '';
    $birthday = $residentData['birthday'] ?? '';
    
    // Format birthday for comparison - multiple formats for Philippine IDs
    $birthdayFormatted = '';
    if (!empty($birthday)) {
        $date = new DateTime($birthday);
        // Philippine IDs use various formats: 1987/10/04, October 04, 1987, 10/04/1987, 1987-10-04
        $birthdayFormatted = $date->format('Y/m/d') . ' or ' . $date->format('F d, Y') . ' or ' . $date->format('m/d/Y') . ' or ' . $date->format('Y-m-d');
    }
    
    $fullName = trim("$firstName $middleName $lastName");
    
    $prompt = <<<PROMPT
You are an AI assistant that validates Philippine government-issued identification documents. Analyze this ID image and verify it meets the following criteria:

**RESIDENT INFORMATION TO VERIFY:**
- Full Name: $fullName
- First Name: $firstName
- Last Name: $lastName
- Birthday: $birthdayFormatted

**VALIDATION CRITERIA:**
1. **ID Number Check**: Does the ID have a visible ID number/license number/control number? (e.g., "N03-12-123456" for Driver's License, or similar format)
2. **Photo Check**: Does the ID have a photo area (even if it shows a placeholder silhouette, it counts as having a photo area)?
3. **Name Check**: Does the name on the ID match or closely match the resident's name "$fullName"? Philippine IDs typically show: "LAST NAME, FIRST NAME MIDDLE NAME" format (e.g., "DELA CRUZ, JUAN PEDRO GARCIA")
4. **Birthday Check**: Does the Date of Birth on the ID match "$birthdayFormatted"? Philippine IDs show dates in formats like "1987/10/04" or similar.

**PHILIPPINE ID TYPES TO ACCEPT:**
- Driver's License (Non-Professional/Professional) - has LTO logo, License No., shows nationality as "PHL"
- Philippine National ID (PhilSys)
- Passport
- Postal ID
- Voter's ID (COMELEC)
- PhilHealth ID
- SSS ID
- GSIS ID
- PRC ID (Professional Regulation Commission)
- Senior Citizen ID
- PWD ID
- Unified Multi-Purpose ID (UMID)
- School ID (with registration number)
- Barangay ID
- NBI Clearance (with NBI No.)
- Police Clearance

**IMPORTANT:**
- Reject random images, selfies, documents that are not IDs, blurry/unreadable IDs
- Be lenient with name matching - allow for nicknames, Jr/Sr/III suffixes, abbreviated middle names
- The ID should be clearly visible and readable
- If you see official government logos (LTO, DFA, PhilSys, etc.), it's likely a valid ID

**RESPOND IN THIS EXACT JSON FORMAT:**
{
    "is_valid": true or false,
    "id_type": "Type of ID detected or 'Unknown'",
    "has_id_number": true or false,
    "has_photo": true or false,
    "name_matches": true or false,
    "birthday_matches": true or false,
    "name_on_id": "Name found on ID or 'Not visible'",
    "birthday_on_id": "Birthday found on ID or 'Not visible'",
    "confidence": "high", "medium", or "low",
    "issues": ["List of specific issues found"],
    "message": "Brief explanation for the user"
}
PROMPT;

    $payload = [
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
        'max_tokens' => 1024
    ];

    // Try with primary API key first
    $apiKeys = [GROQ_API_KEY, GROQ_API_KEY_FALLBACK];
    $response = null;
    $httpCode = 0;
    $error = null;
    $lastError = null;
    
    foreach ($apiKeys as $index => $apiKey) {
        $ch = curl_init(GROQ_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // If successful response, break the loop
        if (!$error && $httpCode === 200) {
            break;
        }
        
        // Check if it's a rate limit error or similar that warrants trying fallback
        if ($httpCode === 429 || $httpCode === 503 || $httpCode >= 500 || $error) {
            $lastError = $error ? $error : json_decode($response, true)['error']['message'] ?? 'API error';
            // Try next API key if available
            if ($index < count($apiKeys) - 1) {
                continue;
            }
        } else {
            // For other errors, don't try fallback
            break;
        }
    }

    if ($error) {
        return [
            'success' => false,
            'error' => 'Failed to connect to validation service: ' . $error
        ];
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'API request failed';
        return [
            'success' => false,
            'error' => 'Validation service error: ' . $errorMessage . ($lastError ? ' (Tried fallback key)' : '')
        ];
    }

    $data = json_decode($response, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error' => 'Invalid response from validation service'
        ];
    }

    $content = $data['choices'][0]['message']['content'];
    
    // Extract JSON from the response (handle markdown code blocks)
    if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
        $jsonStr = $matches[0];
    } else {
        return [
            'success' => false,
            'error' => 'Could not parse validation response'
        ];
    }

    $validationResult = json_decode($jsonStr, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid validation response format'
        ];
    }

    return [
        'success' => true,
        'validation' => $validationResult
    ];
}

/**
 * Main request handler
 */
function handleRequest() {
    // Check for POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'error' => 'Invalid request method'];
    }

    // Check if user is logged in
    if (empty($_SESSION['resident_id'])) {
        return ['success' => false, 'error' => 'Not authenticated'];
    }

    // Check if file was uploaded
    if (!isset($_FILES['validId']) || $_FILES['validId']['error'] !== UPLOAD_ERR_OK) {
        // Try alternate field names
        $fieldNames = ['validId', 'valid_id', 'idPicture', 'proofOfResidency'];
        $uploadedFile = null;
        
        foreach ($fieldNames as $fieldName) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES[$fieldName];
                break;
            }
        }
        
        if (!$uploadedFile) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        $_FILES['validId'] = $uploadedFile;
    }

    $file = $_FILES['validId'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        // If it's a PDF, we can't process it with vision AI
        if ($mimeType === 'application/pdf') {
            return [
                'success' => false, 
                'error' => 'PDF files cannot be validated automatically. Please upload an image (JPG, PNG) of your valid ID.'
            ];
        }
        return ['success' => false, 'error' => 'Invalid file type. Please upload an image file (JPG, PNG).'];
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 10MB.'];
    }

    // Get resident data from database
    include_once 'db.php';
    
    $residentId = $_SESSION['resident_id'];
    $stmt = $conn->prepare("SELECT first_name, last_name, middle_name, suffix, birthday FROM residents WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $residentData = $result->fetch_assoc();
    $stmt->close();

    if (!$residentData) {
        return ['success' => false, 'error' => 'Resident data not found'];
    }

    // Read and encode the image
    $imageContent = file_get_contents($file['tmp_name']);
    $imageBase64 = base64_encode($imageContent);

    // Validate with AI
    $validationResult = validateIdWithAI($imageBase64, $mimeType, $residentData);

    if (!$validationResult['success']) {
        return $validationResult;
    }

    $validation = $validationResult['validation'];
    
    // Determine overall validity
    $isValid = isset($validation['is_valid']) && $validation['is_valid'] === true;
    $hasIdNumber = isset($validation['has_id_number']) && $validation['has_id_number'] === true;
    $hasPhoto = isset($validation['has_photo']) && $validation['has_photo'] === true;
    $nameMatches = isset($validation['name_matches']) && $validation['name_matches'] === true;
    $birthdayMatches = isset($validation['birthday_matches']) && $validation['birthday_matches'] === true;
    
    // Build detailed feedback
    $issues = [];
    if (!$hasIdNumber) $issues[] = 'No ID number detected on the document';
    if (!$hasPhoto) $issues[] = 'No photo detected on the ID';
    if (!$nameMatches) $issues[] = 'Name on ID does not match your registered name';
    if (!$birthdayMatches) $issues[] = 'Birthday on ID does not match your registered birthday';
    
    // Final validation - all criteria must pass
    $finalValid = $isValid && $hasIdNumber && $hasPhoto && $nameMatches && $birthdayMatches;
    
    return [
        'success' => true,
        'is_valid' => $finalValid,
        'details' => [
            'id_type' => $validation['id_type'] ?? 'Unknown',
            'has_id_number' => $hasIdNumber,
            'has_photo' => $hasPhoto,
            'name_matches' => $nameMatches,
            'birthday_matches' => $birthdayMatches,
            'name_on_id' => $validation['name_on_id'] ?? 'Not detected',
            'birthday_on_id' => $validation['birthday_on_id'] ?? 'Not detected',
            'confidence' => $validation['confidence'] ?? 'unknown'
        ],
        'issues' => !empty($validation['issues']) ? $validation['issues'] : $issues,
        'message' => $validation['message'] ?? ($finalValid ? 'ID validated successfully' : 'ID validation failed. Please upload a valid government ID with your correct information.')
    ];
}

// Execute and return response
echo json_encode(handleRequest());
