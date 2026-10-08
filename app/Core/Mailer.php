<?php
declare(strict_types=1);

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private static function make(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST']     ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
        $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
        $mail->SMTPSecure = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'ssl') === 'tls'
                                ? PHPMailer::ENCRYPTION_STARTTLS
                                : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 465);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 8; // évite le blocage 120s quand SMTP injoignable
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'contact@connectacademia.io',
            $_ENV['MAIL_FROM_NAME']    ?? "Connect'Academia"
        );
        return $mail;
    }

    /**
     * Coupe-circuit local : avec `MAIL_DISABLED=true` dans le .env, rien ne
     * part sur le réseau, l'envoi est seulement journalisé et compté comme
     * réussi. Défaut : désactivé, la production n'est pas concernée.
     *
     * Raison d'être : la suite de tests fait tourner les tâches planifiées sur
     * la base de développement, dont la table `admins` contient de VRAIES
     * adresses. Une exécution locale a suffi à envoyer une alerte réelle aux
     * super administrateurs. Le garde-fou appartient au code d'envoi, pas à
     * chaque appelant : il n'y a qu'un seul endroit à ne pas oublier.
     */
    private static function coupe(): bool
    {
        return in_array(strtolower(trim((string)($_ENV['MAIL_DISABLED'] ?? ''))), ['1', 'true', 'yes'], true);
    }

    public static function send(string $to, string $toName, string $subject, string $html, string $text = ''): bool
    {
        if (self::coupe()) {
            error_log("[Mailer] DÉSACTIVÉ (MAIL_DISABLED) → {$to} — {$subject}");
            return true;
        }

        try {
            $mail = self::make();
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
            $mail->send();
            error_log("[Mailer] Envoyé → {$to} — {$subject}");
            return true;
        } catch (\Throwable $e) {
            error_log("[Mailer] Échec → {$to} — {$subject} : " . $e->getMessage());
            return false;
        }
    }
}
