<?php
header('Content-Type: application/json'); // Force JSON response
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start(); // Capture any unexpected output

require_once(__DIR__ . '/tcpdf/tcpdf.php');

// 🔹 Capture any errors
$debug_log = __DIR__ . "/pdf_debug.log";
file_put_contents($debug_log, "🚀 PDF Generation Request Received\n", FILE_APPEND);

$data = json_decode(file_get_contents("php://input"), true);

// 🔹 Log incoming data
file_put_contents($debug_log, "📩 Raw Input Data: " . file_get_contents("php://input") . "\n", FILE_APPEND);
file_put_contents($debug_log, "📦 Decoded Data: " . print_r($data, true) . "\n", FILE_APPEND);

// 🔹 Capture output buffer (if any)
$output = ob_get_clean();
if (!empty($output)) {
    file_put_contents($debug_log, "⚠️ Unexpected Output: " . $output . "\n", FILE_APPEND);
    echo json_encode(["status" => "error", "message" => "Unexpected output detected. Check logs."]);
    exit;
}

// Create PDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

$pdf->Cell(0, 10, "Personalized Flight Training Financial Outlook", 0, 1, 'C');
$pdf->Ln(5);
$pdf->Cell(0, 10, "Student: " . $data['first_name'], 0, 1);
$pdf->Cell(0, 10, "Program: " . $data['training_program'], 0, 1);
$pdf->Cell(0, 10, "Down Payment: $" . number_format($data['down_payment'], 2), 0, 1);
$pdf->Cell(0, 10, "Estimated Monthly Payment: $" . number_format($data['monthly_payment'], 2), 0, 1);
$pdf->Cell(0, 10, "Financing Type: " . $data['payment_plan'], 0, 1);
$pdf->Cell(0, 10, "Co-Signer: " . $data['cosigner'], 0, 1);

$pdfFilename = 'pdfs/' . uniqid() . '_financing_summary.pdf';
$pdfPath = __DIR__ . '/' . $pdfFilename;
$pdfUrl = 'https://amelia-i.com/wp-content/plugins/custom-payment-plans/' . $pdfFilename;

$pdf->Output($pdfPath, 'F'); // Save PDF file

// ✅ Send Email with PDF Attachment
$email_to = $data['email'];
$subject = "Your Financing Plan - Piston Aviation";
$headers = "From: Piston Aviation <no-reply@amelia-i.com>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"boundary\"\r\n";

// Email Body
$message = "--boundary\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$message .= "
    <p>Dear {$data['first_name']},</p>
    <p>Attached is your financing plan summary for your flight training at Piston Aviation.</p>
    <p><a href='$pdfUrl'>Click here to view/download your PDF</a></p>
    <p>Next Step: Complete your loan application with Stratus Financial:</p>
    <p><a href='https://apply.stratus.finance/pistonaviation8450001'>Apply for Financing</a></p>
    <p>Best Regards,<br>Piston Aviation</p>
    \r\n";

// ✅ Attach PDF
$file_content = file_get_contents($pdfPath);
$encoded_file = chunk_split(base64_encode($file_content));
$message .= "--boundary\r\n";
$message .= "Content-Type: application/pdf; name=\"" . basename($pdfFilename) . "\"\r\n";
$message .= "Content-Disposition: attachment; filename=\"" . basename($pdfFilename) . "\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n";
$message .= $encoded_file . "\r\n";
$message .= "--boundary--";

// ✅ Send Email
mail($email_to, $subject, $message, $headers);

// ✅ Return Response with PDF URL
echo json_encode(["status" => "success", "pdf_url" => $pdfUrl]);

?>


