<?php
// e:\Snecinatripu\app\helpers\mail.php
declare(strict_types=1);

/**
 * Odeslání e-mailu přes SMTP (STARTTLS / SSL) bez externí knihovny.
 * Heslo čte z config/mail.php (smtp_password_encrypted) přes crm_decrypt.
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';

if (!function_exists('crm_mail_config')) {
    /** @return array<string, mixed> */
    function crm_mail_config(): array
    {
        return require CRM_CONFIG_PATH . DIRECTORY_SEPARATOR . 'mail.php';
    }
}

if (!function_exists('crm_mail_smtp_read')) {
    function crm_mail_smtp_read($fp): string
    {
        $data = '';
        while ($line = @fgets($fp, 515)) {
            $data .= $line;
            if (strlen($line) < 4) {
                break;
            }
            if ($line[3] === ' ') {
                break;
            }
        }
        return $data;
    }
}

if (!function_exists('crm_mail_smtp_expect')) {
    function crm_mail_smtp_expect(string $buf, int $class): bool
    {
        return strlen($buf) >= 3 && (int) $buf[0] === $class;
    }
}

if (!function_exists('crm_mail_send')) {
    /**
     * Odeslání jednoduchého textového e-mailu.
     *
     * @param array{to:string, subject:string, body:string, reply_to?:string|null} $message
     */
    function crm_mail_send(array $message): bool
    {
        $cfg = crm_mail_config();
        if (empty($cfg['enabled'])) {
            return false;
        }

        $to = (string) $message['to'];
        $subject = (string) $message['subject'];
        $body = (string) $message['body'];
        $replyTo = isset($message['reply_to']) ? (string) $message['reply_to'] : '';

        if ($to === '' || $subject === '' || $body === '') {
            return false;
        }

        $fromEmail = (string) $cfg['from_email'];
        $fromName = (string) $cfg['from_name'];
        $host = (string) $cfg['smtp_host'];
        $port = (int) $cfg['smtp_port'];
        $enc = strtolower((string) $cfg['smtp_encryption']);
        $user = (string) $cfg['smtp_username'];
        $passEnc = (string) $cfg['smtp_password_encrypted'];
        $pass = $passEnc !== '' ? crm_decrypt($passEnc) : '';
        $timeout = (int) $cfg['smtp_timeout'];

        if ($host === '' || $pass === null || $pass === '') {
            return crm_mail_send_php_mail($fromEmail, $fromName, $to, $subject, $body, $replyTo);
        }

        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if ($fp === false) {
            return false;
        }
        stream_set_timeout($fp, $timeout);

        $read = static function () use ($fp): string {
            return crm_mail_smtp_read($fp);
        };
        $write = static function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };

        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        $ehloHost = 'localhost';
        $write('EHLO ' . $ehloHost);
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        if ($enc === 'tls') {
            $write('STARTTLS');
            $buf = $read();
            if (!crm_mail_smtp_expect($buf, 2)) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write('EHLO ' . $ehloHost);
            $buf = $read();
            if (!crm_mail_smtp_expect($buf, 2)) {
                fclose($fp);
                return false;
            }
        }

        $write('AUTH LOGIN');
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 3)) {
            fclose($fp);
            return false;
        }
        $write(base64_encode($user));
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 3)) {
            fclose($fp);
            return false;
        }
        $write(base64_encode($pass));
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        $write('RCPT TO:<' . $to . '>');
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        $write('DATA');
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 3)) {
            fclose($fp);
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $fromName !== ''
            ? sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail)
            : $fromEmail;

        $headers = [];
        $headers[] = 'From: ' . $fromHeader;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $encodedSubject;
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
        $data = preg_replace("/\r\n\./", "\r\n..", $data) ?? $data;

        fwrite($fp, $data . "\r\n.\r\n");
        $buf = $read();
        if (!crm_mail_smtp_expect($buf, 2)) {
            fclose($fp);
            return false;
        }

        $write('QUIT');
        fclose($fp);
        return true;
    }
}

if (!function_exists('crm_mail_send_php_mail')) {
    function crm_mail_send_php_mail(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        string $replyTo
    ): bool {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $fromHeader = $fromName !== ''
            ? sprintf('From: "%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail)
            : 'From: ' . $fromEmail;
        $headers[] = $fromHeader;
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $params = '-f' . $fromEmail;
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), $params);
    }
}
