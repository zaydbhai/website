<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // reCAPTCHA verification
    $secretKey = "6LfI6-8rAAAAANdYp0bdVq95q_yg4njhOYAjR-13";
    $responseKey = $_POST['g-recaptcha-response'];
    $userIP = $_SERVER['REMOTE_ADDR'];
    $url = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$responseKey&remoteip=$userIP";
    
    $response = file_get_contents($url);
    $response = json_decode($response);
    
    if ($response->success) {
        // reCAPTCHA verification successful - process the form
        $name = htmlspecialchars(trim($_POST['name']));
        $email = htmlspecialchars(trim($_POST['email']));
        $subject = htmlspecialchars(trim($_POST['subject']));
        $message = htmlspecialchars(trim($_POST['message']));
        
        // Email details
        $to = "aqeelssuet@yahoo.com";
        $email_subject = "New Contact Form Message: " . $subject;
        $email_body = "You have received a new message from your website contact form.\n\n".
                      "Name: $name\n".
                      "Email: $email\n".
                      "Subject: $subject\n".
                      "Message:\n$message";
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        
        // Send email
        if (mail($to, $email_subject, $email_body, $headers)) {
            echo "Thank you! Your message has been sent successfully.";
        } else {
            echo "Sorry, there was an error sending your message. Please try again.";
        }
    } else {
        // reCAPTCHA verification failed
        echo "reCAPTCHA verification failed. Please try again.";
    }
} else {
    echo "Invalid request method.";
}
?>