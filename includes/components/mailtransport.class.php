<?php

namespace Aowow;

use PHPMailer\PHPMailer\PHPMailer;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Deployment-owned transport; account templates and token handling remain in Util. */
final class MailTransport
{
    public static function send(string $recipient, string $subject, string $body, string $headers) : bool
    {
        $config = defined('AOWOW_MAIL') ? AOWOW_MAIL : [];
        if (!is_array($config))
            return self::failure('configuration');

        $transport = $config['transport'] ?? 'mail';
        if ($transport === 'disabled')
            return false;
        if ($transport === 'mail')
            return mail($recipient, $subject, $body, $headers);
        if ($transport !== 'smtp')
            return self::failure('configuration');

        $config += [
            'host'=>'', 'port'=>587, 'encryption'=>'tls', 'auth'=>true,
            'username'=>'', 'password'=>'', 'auth_type'=>'',
            'from_email'=>'', 'from_name'=>'', 'reply_to'=>'',
            'timeout'=>10, 'command_timeout'=>10, 'ca_file'=>''
        ];
        if (!self::valid($config))
            return self::failure('configuration');

        $mailer = null;
        try
        {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = filter_var($config['host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$config['host'].']' : $config['host'];
            $mailer->Port = $config['port'];
            $mailer->SMTPAuth = $config['auth'];
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
            $mailer->AuthType = $config['auth_type'];
            $mailer->SMTPSecure = match ($config['encryption']) {
                'tls'=>PHPMailer::ENCRYPTION_STARTTLS, 'ssl'=>PHPMailer::ENCRYPTION_SMTPS, default=>''
            };
            $mailer->SMTPAutoTLS = false;                  // Honor the chosen mode; TLS never silently downgrades.
            $mailer->SMTPDebug = 0;
            $mailer->Debugoutput = static function () : void {};
            $mailer->Timeout = $config['timeout'];
            $mailer->getSMTPInstance()->Timelimit = $config['command_timeout'];
            $mailer->SMTPOptions = ['ssl'=>[
                'verify_peer'=>true, 'verify_peer_name'=>true, 'allow_self_signed'=>false
            ]];
            if ($config['ca_file'] !== '')
                $mailer->SMTPOptions['ssl']['cafile'] = $config['ca_file'];

            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->isHTML(false);
            $mailer->setFrom($config['from_email'] ?: (string)Cfg::get('CONTACT_EMAIL'),
                $config['from_name'] ?: (string)Cfg::get('NAME_SHORT'));
            $mailer->addReplyTo($config['reply_to'] ?: (string)Cfg::get('CONTACT_EMAIL'));
            $mailer->addAddress($recipient);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            return $mailer->send();
        }
        catch (\Throwable)
        {
            // Never log credentials, recipient, message, SMTP transcript or provider response.
            return self::failure('SMTP');
        }
        finally
        {
            $mailer?->getSMTPInstance()->close();          // Do not wait for QUIT after a failed operation.
        }
    }

    private static function valid(array $config) : bool
    {
        foreach (['host','username','password','auth_type','encryption','from_email','from_name','reply_to','ca_file'] as $key)
            if (!is_string($config[$key]) || strlen($config[$key]) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $config[$key]))
                return false;

        // One hostname/IP only: PHPMailer also accepts URL prefixes and multiple hosts, which are excluded here.
        if (!filter_var($config['host'], FILTER_VALIDATE_IP) &&
            !filter_var($config['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
            return false;
        if ($config['host'] === '' || !is_int($config['port']) || $config['port'] < 1 || $config['port'] > 65535 ||
            !is_bool($config['auth']) || !in_array($config['encryption'], ['tls','ssl','none'], true) ||
            !in_array($config['auth_type'], ['', 'LOGIN','PLAIN','CRAM-MD5'], true))
            return false;
        if ($config['auth'] && ($config['encryption'] === 'none' || $config['username'] === '' || $config['password'] === ''))
            return false;
        foreach (['timeout','command_timeout'] as $key)
            if (!is_int($config[$key]) || $config[$key] < 1 || $config[$key] > 30)
                return false;
        if ($config['ca_file'] !== '' && (!is_file($config['ca_file']) || !is_readable($config['ca_file'])))
            return false;
        foreach (['from_email','reply_to'] as $key)
            if ($config[$key] !== '' && !filter_var($config[$key], FILTER_VALIDATE_EMAIL))
                return false;
        return true;
    }

    private static function failure(string $category) : bool
    {
        error_log('AoWoW mail delivery failed ('.$category.'); check private mail configuration and relay availability.');
        return false;
    }
}
