<?php
/**
 * Quick mart Email Helper
 * Supports SMTP (via Gmail/Outlook) and Simulation
 */

function sendEmail($to, $subject, $body) {
    global $pdo;
    
    // Get Email settings
    $enabled = (int)get_setting('email_enabled', '0');
    if (!$enabled) return false;
    
    $provider = get_setting('email_provider', 'simulation');
    $from_email = get_setting('email_from_address', 'noreply@quickmart.com');
    $from_name = get_setting('email_from_name', 'Quick mart');

    // Log the attempt
    $log_msg = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject | Provider: $provider\n";
    file_put_contents(__DIR__ . '/../email_log.txt', $log_msg, FILE_APPEND);

    if ($provider === 'smtp') {
        return sendSMTPEmail($to, $subject, $body, $from_email, $from_name);
    } else {
        // Simulation: Save full email content to file
        $sim_content = "To: $to\nFrom: $from_name <$from_email>\nSubject: $subject\n\n$body\n" . str_repeat("-", 30) . "\n";
        file_put_contents(__DIR__ . '/../email_sim_messages.txt', $sim_content, FILE_APPEND);
        return true;
    }
}

/**
 * Basic SMTP implementation using sockets
 * Supports TLS (Port 587)
 */
function sendSMTPEmail($to, $subject, $body, $from_email, $from_name) {
    $host = get_setting('email_smtp_host', 'smtp.gmail.com');
    $port = (int)get_setting('email_smtp_port', '587');
    $user = get_setting('email_smtp_user');
    $pass = get_setting('email_smtp_pass');

    if (!$user || !$pass) {
        // Fallback to native mail if credentials are missing
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: $from_name <$from_email>\r\n";
        return mail($to, $subject, $body, $headers);
    }

    try {
        $timeout = 10;
        $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) return false;

        $getResponse = function($socket) {
            $response = "";
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == " ") break;
            }
            return $response;
        };

        $sendCommand = function($socket, $command) use ($getResponse) {
            fputs($socket, $command . "\r\n");
            return $getResponse($socket);
        };

        $getResponse($socket); // Initial response
        $sendCommand($socket, "EHLO " . $_SERVER['HTTP_HOST']);
        
        if ($port == 587) {
            $sendCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            $sendCommand($socket, "EHLO " . $_SERVER['HTTP_HOST']);
        }

        $sendCommand($socket, "AUTH LOGIN");
        $sendCommand($socket, base64_encode($user));
        $sendCommand($socket, base64_encode($pass));

        $sendCommand($socket, "MAIL FROM: <$user>");
        $sendCommand($socket, "RCPT TO: <$to>");
        $sendCommand($socket, "DATA");
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: $from_name <$from_email>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        
        fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $getResponse($socket);

        $sendCommand($socket, "QUIT");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
