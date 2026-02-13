<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/../calendar.v2/vendor/autoload.php'); // Adjust as needed

use Dompdf\Dompdf;

function generate_receipt($payment_id) {
  $db = getDB();

  // Get payment and student info
  $stmt = $db->prepare("SELECT p.*, s.first_name, s.last_name, s.email 
                        FROM wp_payments p 
                        JOIN wp_students s ON p.student_id = s.student_id 
                        WHERE p.id = ?");
  $stmt->execute([$payment_id]);
  $payment = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$payment) {
    error_log("❌ No payment found for ID: $payment_id");
    return null;
  }

  // Prepare HTML for PDF
  $html = "
    <style>
      body { font-family: Arial, sans-serif; font-size: 14px; }
      .section { margin-bottom: 10px; }
      h2 { text-align: center; }
    </style>

    <h2>Piston Aviation Payment Receipt</h2>
    <div class='section'><strong>Receipt ID:</strong> {$payment['id']}</div>
    <div class='section'><strong>Student:</strong> {$payment['first_name']} {$payment['last_name']}</div>
    <div class='section'><strong>Date:</strong> " . date('F j, Y') . "</div>
    <div class='section'><strong>Payment Method:</strong> {$payment['method']}</div>
    <div class='section'><strong>Amount Paid:</strong> \$" . number_format($payment['amount'], 2) . "</div>
    <div class='section'><strong>Aircraft Hours:</strong> {$payment['aircraft_hours']}</div>
    <div class='section'><strong>Instructor Hours:</strong> {$payment['instructor_hours']}</div>
    <div class='section'><strong>Coupon Used:</strong> " . ($payment['coupon_code'] ?: 'N/A') . "</div>
    <div class='section'>Thank you for flying with Piston Aviation!</div>
  ";

  // Create Dompdf instance
  $dompdf = new Dompdf\Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4');
  $dompdf->render();

  // Save to plugin uploads directory
  $upload_dir = __DIR__ . '/uploads';
  if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
  }

  $filename = "receipt_{$payment_id}.pdf";
  $full_path = "$upload_dir/$filename";

  // Write file
  try {
    file_put_contents($full_path, $dompdf->output());
    return "/wp-content/plugins/pistonpay/uploads/$filename";
  } catch (Exception $e) {
    error_log("❌ Failed to write receipt: " . $e->getMessage());
    return null;
  }
}


