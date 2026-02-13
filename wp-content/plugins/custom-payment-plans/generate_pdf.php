<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/tcpdf/tcpdf.php');

// 🔹 Capture Errors
$debug_log = __DIR__ . "/pdf_debug.log";
file_put_contents($debug_log, "🚀 PDF Generation Request Received\n", FILE_APPEND);

// 🔹 Capture incoming data
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    file_put_contents($debug_log, "❌ JSON Decode Failed. Raw Input: " . $raw_input . "\n", FILE_APPEND);
    echo json_encode(["status" => "error", "message" => "Invalid JSON received"]);
    exit;
}

// ✅ Ensure All Expected Fields Exist
$fields = ['first_name', 'email', 'training_program', 'down_payment', 'monthly_payment', 'payment_plan', 'cosigner'];
foreach ($fields as $field) {
    if (!isset($data[$field])) {
        $data[$field] = "N/A"; // Default to "N/A" instead of null
    }
}

// ✅ Ensure the `pdfs/` directory exists
$pdfDir = __DIR__ . '/pdfs/';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}

// ✅ Create PDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 12);

$pdf->Cell(0, 10, "Personalized Flight Training Financial Outlook", 0, 1, 'C');
$pdf->Ln(5);
$pdf->Cell(0, 10, "Student: " . $data['first_name'], 0, 1);
$pdf->Cell(0, 10, "Program: " . $data['training_program'], 0, 1);
$pdf->Cell(0, 10, "Down Payment: $" . number_format((float)$data['down_payment'], 2), 0, 1);
$pdf->Cell(0, 10, "Estimated Monthly Payment: $" . number_format((float)$data['monthly_payment'], 2), 0, 1);
$pdf->Cell(0, 10, "Financing Type: " . $data['payment_plan'], 0, 1);
$pdf->Cell(0, 10, "Co-Signer: " . $data['cosigner'], 0, 1);

$pdfFilename = 'pdfs/' . uniqid() . '_financing_summary.pdf';
$pdfPath = $pdfDir . $pdfFilename;
$pdfUrl = 'https://amelia-i.com/wp-content/plugins/custom-payment-plans/' . $pdfFilename;

$pdf->Output($pdfPath, 'F'); // Save PDF file

// ✅ Send Email
$email_to = $data['email'];
$subject = "Your Financing Plan - Piston Aviation";
$headers = "From: Piston Aviation <no-reply@amelia-i.com>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$message = "<p>Dear {$data['first_name']},</p>
            <p>Attached is your financing plan summary.</p>
            <p><a href='$pdfUrl'>Click here to view/download your PDF</a></p>";

mail($email_to, $subject, $message, $headers);

// ✅ Return Response with PDF URL
echo json_encode(["status" => "success", "pdf_url" => $pdfUrl]);

?>


