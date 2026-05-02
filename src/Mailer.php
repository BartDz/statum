<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    private function __construct(
        private readonly string  $host,
        private readonly int     $port,
        private readonly string  $user,
        private readonly string  $pass,
        private readonly string  $from,
        private readonly string  $fromName,
        private readonly string  $to,
        private readonly ?string $siteUrl,
    ) {}

    public static function fromEnv(): ?self
    {
        $to = getenv('ALERT_EMAIL');

        if (!$to) {
            return null;
        }

        return new self(
            host: getenv('SMTP_HOST') ?: '',
            port: (int) (getenv('SMTP_PORT') ?: 587),
            user: getenv('SMTP_USER') ?: '',
            pass: getenv('SMTP_PASS') ?: '',
            from: getenv('SMTP_FROM') ?: (getenv('SMTP_USER') ?: ''),
            fromName: getenv('SMTP_FROM_NAME') ?: (getenv('SITE_NAME') ?: 'statum'),
            to: $to,
            siteUrl: getenv('SITE_URL') ?: null,
        );
    }

    public function sendDownAlert(string $serviceName, string $url, int $statusCode, int $latencyMs): void
    {
        $this->send(
            "[statum] {$serviceName} is DOWN",
            $this->render('down', [
                '{{SERVICE_NAME}}' => htmlspecialchars($serviceName, ENT_QUOTES),
                '{{URL}}'          => htmlspecialchars($url, ENT_QUOTES),
                '{{STATUS_CODE}}'  => $statusCode === 0 ? 'Connection failed' : "HTTP {$statusCode}",
                '{{LATENCY_MS}}'   => $latencyMs,
                '{{TIME}}'         => gmdate('Y-m-d H:i:s') . ' UTC',
                '{{SITE_URL}}'     => htmlspecialchars($this->siteUrl ?? '', ENT_QUOTES),
            ])
        );
    }

    public function sendUpAlert(string $serviceName, string $url, int $statusCode, int $latencyMs): void
    {
        $this->send(
            "[statum] {$serviceName} is back UP",
            $this->render('up', [
                '{{SERVICE_NAME}}' => htmlspecialchars($serviceName, ENT_QUOTES),
                '{{URL}}'          => htmlspecialchars($url, ENT_QUOTES),
                '{{STATUS_CODE}}'  => $statusCode,
                '{{LATENCY_MS}}'   => $latencyMs,
                '{{TIME}}'         => gmdate('Y-m-d H:i:s') . ' UTC',
                '{{SITE_URL}}'     => htmlspecialchars($this->siteUrl ?? '', ENT_QUOTES),
            ])
        );
    }

    private function render(string $name, array $vars): string
    {
        $html = file_get_contents(__DIR__ . "/../templates/email_{$name}.html");

        if (!$this->siteUrl) {
            $html = preg_replace('/<!-- CTA_START -->.*?<!-- CTA_END -->/s', '', $html);
        }

        return str_replace(array_keys($vars), array_values($vars), $html);
    }

    private function send(string $subject, string $html): void
    {
        $mail = new PHPMailer(true);

        if ($this->host !== '') {
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->SMTPAuth   = $this->user !== '';
            $mail->Username   = $this->user;
            $mail->Password   = $this->pass;
            $mail->SMTPSecure = $this->port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->isMail();
        }

        $mail->setFrom($this->from !== '' ? $this->from : $this->to, $this->fromName);
        $mail->addAddress($this->to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->CharSet = 'UTF-8';

        $mail->send();
    }
}
