<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php#booking');
verify_csrf();

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$serviceId = filter_input(INPUT_POST, 'service_type_id', FILTER_VALIDATE_INT);
$address = trim((string)($_POST['address'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$date = trim((string)($_POST['preferred_date'] ?? ''));
$time = trim((string)($_POST['preferred_time'] ?? ''));
$details = trim((string)($_POST['details'] ?? ''));

if ($name === '' || mb_strlen($name) > 150 || $phone === '' || mb_strlen($phone) > 30 ||
    !$serviceId || $address === '' || mb_strlen($address) > 255 || mb_strlen($details) > 2000) {
    flash('error', 'Please check the required fields and try again.');
    redirect('index.php#booking');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Please enter a valid email address or leave it blank.');
    redirect('index.php#booking');
}
if ($date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        flash('error', 'Please choose today or a future date.');
        redirect('index.php#booking');
    }
}
$allowedTimes = ['', 'Morning', 'Afternoon', 'Evening'];
if (!in_array($time, $allowedTimes, true)) $time = '';

try {
    $pdo = db();
    $serviceStmt = $pdo->prepare('SELECT id,name FROM service_types WHERE id=? AND is_active=1');
    $serviceStmt->execute([$serviceId]);
    $service = $serviceStmt->fetch();
    if (!$service) throw new RuntimeException('Please select a valid service.');

    $pdo->beginTransaction();
    $customerStmt = $pdo->prepare('INSERT INTO customers (name,phone,email) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email)');
    $customerStmt->execute([$name, $phone, $email !== '' ? $email : null]);
    $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
    $customerStmt->execute([$phone]);
    $customerId = (int)$customerStmt->fetchColumn();

    $ref = 'TNY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare('INSERT INTO bookings (booking_ref,customer_id,service_type_id,customer_name,customer_phone,customer_email,service_address,preferred_date,preferred_time,details) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$ref,$customerId,$service['id'],$name,$phone,$email !== '' ? $email : null,$address,$date !== '' ? $date : null,$time !== '' ? $time : null,$details !== '' ? $details : null]);
    $bookingId = (int)$pdo->lastInsertId();
    $message = "Hello TONNY, I submitted a service request.\nBooking reference: {$ref}\nName: {$name}\nPhone: {$phone}\nService: {$service['name']}\nLocation: {$address}\nPreferred date: " . ($date ?: 'Flexible') . "\nPreferred time: " . ($time ?: 'Flexible') . "\nDetails: " . ($details ?: 'None') . "\nPlease confirm availability and quotation.";
    $log = $pdo->prepare('INSERT INTO notification_logs (channel,recipient,subject,message,status,related_type,related_id) VALUES (?,?,?,?,?,?,?)');
    $log->execute(['whatsapp', envv('WHATSAPP_NUMBER','254723777601'), 'New booking '.$ref, $message, 'queued', 'booking', $bookingId]);
    $pdo->commit();

    $_SESSION['last_booking_ref'] = $ref;
    $_SESSION['last_booking_message'] = $message;
    redirect('booking-success.php');
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Booking submission failed: ' . $ex->getMessage());
    flash('error', 'We could not save your request right now. Please try again or contact us on WhatsApp.');
    redirect('index.php#booking');
}
