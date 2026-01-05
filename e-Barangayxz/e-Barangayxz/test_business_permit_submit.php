<?php
// test_business_permit_submit.php - Test what's being received
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

$data = [
    'session_resident_id' => $_SESSION['resident_id'] ?? 'NOT SET',
    'post_keys' => array_keys($_POST),
    'post_data' => [
        'firstName' => $_POST['firstName'] ?? 'MISSING',
        'lastName' => $_POST['lastName'] ?? 'MISSING',
        'dateOfBirth' => $_POST['dateOfBirth'] ?? 'MISSING',
        'completeAddress' => $_POST['completeAddress'] ?? 'MISSING',
        'contactNumber' => $_POST['contactNumber'] ?? 'MISSING',
        'businessName' => $_POST['businessName'] ?? 'MISSING',
        'businessType' => $_POST['businessType'] ?? 'MISSING',
        'businessLocation' => $_POST['businessLocation'] ?? 'MISSING',
        'modeOfRelease' => $_POST['modeOfRelease'] ?? 'MISSING',
        'document_type' => $_POST['document_type'] ?? 'MISSING',
    ]
];

echo json_encode($data);
?>
