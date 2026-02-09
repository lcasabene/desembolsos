
<?php
function process_form($form) {
    $to = $form['email']['to'];
    $subject = $form['subject'];
    $name = htmlspecialchars($_POST['custom_U958']);
    $email = htmlspecialchars($_POST['Email']);

    $message = "Nuevo mensaje de contacto:\n";
    $message .= "Nombre: " . $name . "\n";
    $message .= "Email: " . $email . "\n";

    $headers = "From: " . $form['email']['from'] . "\r\n" .
                "Reply-To: " . $email . "\r\n" .
                "X-Mailer: PHP/" . phpversion();

    mail($to, $subject, $message, $headers);
    echo json_encode(['success' => true]);
}
?>
