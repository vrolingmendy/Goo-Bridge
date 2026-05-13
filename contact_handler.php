<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

$form_error = '';
$form_success = isset($_GET['sent']) && $_GET['sent'] === '1';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    $form_error = 'Veuillez remplir tous les champs obligatoires.';
    return;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $form_error = 'Adresse email invalide.';
    return;
}

if ($phone !== '') {
    if (strlen($phone) > 32 || !preg_match('/^[\d\s().+-]{6,32}$/', $phone)) {
        $form_error = 'Numéro de téléphone invalide.';
        return;
    }
}

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
    $form_error = 'Impossible d\'enregistrer votre message pour le moment. Réessayez plus tard.';
    return;
}

$record = [
    't' => date('c'),
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'service' => $service,
    'message' => $message,
];
$json = json_encode($record, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $form_error = 'Erreur lors de l\'enregistrement du message.';
    return;
}
$line = $json . "\n";

if (file_put_contents($storageDir . '/contacts.log', $line, FILE_APPEND | LOCK_EX) === false) {
    $form_error = 'Impossible d\'enregistrer votre message pour le moment. Réessayez plus tard.';
    return;
}

require_once __DIR__ . '/includes/contact_mail.php';
send_contact_mail($record);

$redirect = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
header('Location: ' . $redirect . '?sent=1#contact', true, 303);
exit;
