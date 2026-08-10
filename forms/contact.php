<?php
// ===== DEBUG (TEMP) =====
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// ========================

header('Content-Type: application/json; charset=utf-8');

// -------------- CONFIG --------------
// REQUIRED: where to receive messages
$TO_EMAIL = 'info@labnex.lk';         // <- change this
$TO_NAME  = 'Sales / Contact Form';

// RECOMMENDED: use SMTP (set to true + fill creds). If false, falls back to mail()
$USE_SMTP = false;                         // true to enable SMTP
$SMTP_HOST = 'smtp.yourprovider.com';
$SMTP_PORT = 587;                          // 587 (STARTTLS) or 465 (SMTPS)
$SMTP_USER = 'no-reply@yourdomain.com';
$SMTP_PASS = 'YOUR_SMTP_PASSWORD';
$SMTP_SECURE = 'tls';                      // 'tls' or 'ssl'
$FROM_EMAIL = 'no-reply@labnex.lk';   // must be authenticated domain
$FROM_NAME  = 'Labnex.lk';

// -------------- INPUT ---------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Accept both form-encoded and JSON
$payload = $_POST;
if (empty($payload)) {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $payload = $json;
  }
}

$name    = trim($payload['name']    ?? '');
$email   = trim($payload['email']   ?? '');
$subject = trim($payload['subject'] ?? 'Website Contact');
$message = trim($payload['message'] ?? '');
$phone   = trim($payload['phone']   ?? '');
$source  = trim($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? '');

// -------------- VALIDATION ----------
$errors = [];
if ($name === '')    $errors[] = 'Name is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($message === '') $errors[] = 'Message is required';

if ($errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => implode(', ', $errors)]);
  exit;
}

// -------------- MESSAGE -------------
$lines = [
  "Name: $name",
  "Email: $email",
  ($phone ? "Phone: $phone" : null),
  "From: $source",
  "---- Message ----",
  $message
];
$body = implode("\n", array_filter($lines));

// -------------- SEND ----------------
if ($USE_SMTP) {
  // SMTP via PHPMailer (no Composer). You must upload PHPMailer library files to /forms/phpmailer/
  // Download PHPMailer: https://github.com/PHPMailer/PHPMailer (upload src/* to /forms/phpmailer/src)
  try {
    // Adjust paths if you place PHPMailer elsewhere
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
    require __DIR__ . '/phpmailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    if ($SMTP_SECURE === 'ssl') {
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
      $mail->Port = 465;
    } else {
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = $SMTP_PORT ?: 587;
    }

    $mail->setFrom($FROM_EMAIL, $FROM_NAME);
    $mail->addAddress($TO_EMAIL, $TO_NAME);
    $mail->addReplyTo($email, $name);

    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = $body;

    if (!$mail->send()) {
      throw new Exception('SMTP send failed: ' . $mail->ErrorInfo);
    }

    echo json_encode(['ok' => true, 'message' => 'Message sent (SMTP)']);
    exit;

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
  }
} else {
  // Native mail() fallback (may be disabled by host)
  $headers = [];
  $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($FROM_NAME), $FROM_EMAIL);
  $headers[] = 'Reply-To: ' . $email;
  $headers[] = 'Content-Type: text/plain; charset=UTF-8';
  $headers[] = 'X-Mailer: PHP/' . phpversion();

  $ok = @mail($TO_EMAIL, $subject, $body, implode("\r\n", $headers));
 if ($ok) {
  header('Content-Type: text/plain; charset=utf-8');
  echo 'OK';
  exit;
}
else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail() failed on server. Enable SMTP.']);
    exit;
  }
}

?>