<?php
// Read incoming JSON
$input = json_decode(file_get_contents('php://input'), true);

$tail = $input['tail_number'] ?? null;
$total = $input['total_hours'] ?? null;

if (!$tail || !$total) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Forward to Base44 internal function
$ch = curl_init("https://68b70ceea9ef88cb5680ab07.base44.com/functions/receiveExternalTachTime");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'tail_number' => $tail,
    'total_hours' => $total
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . getenv('TACH_API_KEY')
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(['error' => 'Relay failed', 'detail' => $err]);
    exit;
}

// Return Base44’s JSON response
http_response_code($code);
echo $response;
