<?php
declare(strict_types=1);

namespace Core;

use Models\SecurityAlert;
use Services\Orientation\SmsService;
use Services\Orientation\WhatsAppService;

/**
 * Détection d'intrusion légère (IDS applicatif) + alertes super-admin.
 *
 * Deux points d'entrée :
 *   • SecurityMonitor::guard()          — appelé une fois avant le dispatch,
 *     inspecte la requête entrante (URI, query, corps, user-agent) à la
 *     recherche de signatures d'attaque (SQLi, XSS, traversée de répertoire,
 *     RCE, scanners), et surveille le volume par IP (flood/scan).
 *   • SecurityMonitor::loginFailure()   — appelé sur chaque échec de login
 *     admin ; déclenche une alerte « force brute » au-delà d'un seuil.
 *
 * Robustesse : TOUT est encapsulé dans des try/catch. Le moniteur ne doit
 * JAMAIS interrompre une requête légitime ni propager d'exception — en cas de
 * panne DB/Redis il échoue en silence (fail-open).
 *
 * Blocage : par défaut le moniteur se contente d'ALERTER (non bloquant). Le
 * super-admin peut activer le blocage des attaques évidentes (403) via le
 * paramètre `security_block_attacks`. Les signatures utilisées pour bloquer
 * sont volontairement de très haute confiance (jamais présentes dans le trafic
 * normal de la plateforme).
 */
final class SecurityMonitor
{
    /** Signatures d'attaque haute-confiance → alertent et peuvent bloquer. */
    private const SIGNATURES = [
        'sqli' => [
            'severity' => 'elevee',
            'label'    => 'Injection SQL présumée',
            'regex'    => '/(\bunion\b[\s\/*]+\bselect\b|\bselect\b.+\bfrom\b.+\binformation_schema\b|\bor\b\s+1\s*=\s*1\b|\'\s*or\s*\'1\'\s*=\s*\'1|\bsleep\s*\(\s*\d|\bbenchmark\s*\(|\bload_file\s*\(|\binto\s+outfile\b|\bupdatexml\s*\(|\bextractvalue\s*\()/i',
        ],
        'xss' => [
            'severity' => 'elevee',
            'label'    => 'Injection de script (XSS) présumée',
            'regex'    => '/(<script\b|<\/script>|onerror\s*=|onload\s*=|javascript:|<iframe\b|document\.cookie|<svg\b[^>]*onload|<img\b[^>]*onerror)/i',
        ],
        'path_traversal' => [
            'severity' => 'elevee',
            'label'    => 'Traversée de répertoire présumée',
            'regex'    => '#(\.\./\.\./|\.\.\\\\\.\.\\\\|/etc/passwd|/etc/shadow|/proc/self/|c:\\\\windows|php://|file://|expect://|data://text)#i',
        ],
        'rce' => [
            'severity' => 'critique',
            'label'    => 'Exécution de code à distance présumée',
            'regex'    => '/(\$\{jndi:|\bshell_exec\s*\(|\bpassthru\s*\(|\bproc_open\s*\(|\/bin\/(?:sh|bash)\b|\bwget\s+http|\bcurl\s+http.+\|\s*sh)/i',
        ],
    ];

    /** Chemins typiques de scanners / bots hostiles → alerte (non bloquant). */
    private const SCANNER_PATHS = [
        '/wp-admin', '/wp-login.php', '/xmlrpc.php', '/.env', '/.git/',
        '/phpmyadmin', '/pma', '/vendor/phpunit', '/.aws/', '/config.php.bak',
        '/.ssh/', '/actuator', '/solr/', '/console', '/.vscode/',
    ];

    /** Seuils. */
    private const FLOOD_WINDOW   = 60;    // secondes
    private const FLOOD_MAX      = 200;   // requêtes / IP / fenêtre
    private const BF_WINDOW      = 900;   // 15 min
    private const BF_MAX         = 5;     // échecs login admin / IP / fenêtre
    private const NOTIFY_TTL     = 3600;  // 1 e-mail max / (type+ip) / heure
    private const DEDUPE_MINUTES = 30;    // regroupement des alertes identiques

    // ── Point d'entrée global (avant dispatch) ───────────────────────────────

    public static function guard(): void
    {
        try {
            $ip     = self::clientIp();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            $ua     = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

            // Corpus à inspecter : URI brute + valeurs GET/POST (chaînes) + UA.
            $haystack = rawurldecode($uri) . ' ' . self::flatten($_GET) . ' ' . self::flatten($_POST) . ' ' . $ua;

            // 1) Signatures d'attaque
            foreach (self::SIGNATURES as $type => $sig) {
                if (@preg_match($sig['regex'], $haystack)) {
                    self::record($type, $sig['severity'], $sig['label'], [
                        'motif'       => $type,
                        'echantillon' => mb_substr(trim($uri), 0, 180),
                    ]);
                    if (self::blockingEnabled() && !Session::isAdmin()) {
                        self::block();   // termine la requête (403)
                    }
                    break; // une signature suffit
                }
            }

            // 2) Sondes de scanners connus
            $path = strtolower(parse_url($uri, PHP_URL_PATH) ?: '/');
            foreach (self::SCANNER_PATHS as $probe) {
                if (str_contains($path, $probe)) {
                    self::record('scanner', 'moyenne', 'Sonde de scanner détectée', [
                        'chemin' => mb_substr($path, 0, 180),
                    ]);
                    break;
                }
            }

            // 3) Flood / scan de volume par IP
            self::checkFlood($ip);
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] guard: ' . $e->getMessage());
        }
    }

    // ── Échec de login admin (force brute) ───────────────────────────────────

    public static function loginFailure(string $email, string $ip): void
    {
        try {
            $redis = Redis::getInstance();
            $count = 1;
            if ($redis->isAvailable()) {
                $c = $redis->incrWithExpire('secmon:bf:' . $ip, self::BF_WINDOW);
                $count = $c !== false ? (int) $c : 1;
            }
            if ($count >= self::BF_MAX) {
                self::record('brute_force_admin', 'elevee',
                    'Tentatives de connexion admin répétées', [
                        'email_cible' => mb_substr($email, 0, 120),
                        'echecs'      => $count,
                        'fenetre_s'   => self::BF_WINDOW,
                    ], $ip);
            }
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] loginFailure: ' . $e->getMessage());
        }
    }

    // ── Violation de cloisonnement (IDOR / accès cross-tenant) ───────────────

    /**
     * Journalise une tentative d'accès à une ressource qui n'appartient pas à
     * l'acteur courant (ex. gestionnaire ouvrant le dossier d'un autre
     * établissement). Appelé par Core\TenantGuard quand le contrôle de
     * cloisonnement échoue. Fail-open : ne bloque jamais la requête (le
     * contrôleur a déjà renvoyé un 404/redirect), il ne fait qu'alerter.
     */
    public static function accessViolation(string $message, array $context = []): void
    {
        try {
            self::record('idor', 'elevee', $message, $context);
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] accessViolation: ' . $e->getMessage());
        }
    }

    // ── Nombre d'alertes non lues (pour le badge de navigation) ──────────────

    public static function unseenCount(): int
    {
        try {
            return SecurityAlert::countByStatut('nouveau');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private static function checkFlood(string $ip): void
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return;
        }
        $c = $redis->incrWithExpire('secmon:rate:' . $ip, self::FLOOD_WINDOW);
        $count = $c !== false ? (int) $c : 0;
        // On alerte une seule fois par fenêtre (au franchissement exact du seuil).
        if ($count === self::FLOOD_MAX) {
            self::record('flood', 'moyenne', 'Volume de requêtes anormal (flood/scan)', [
                'requetes'  => $count,
                'fenetre_s' => self::FLOOD_WINDOW,
            ], $ip);
        }
    }

    /**
     * Enregistre une alerte avec regroupement : si une alerte identique
     * (même type + IP) est encore « nouveau » et récente, on incrémente son
     * compteur d'occurrences au lieu de créer un doublon.
     */
    private static function record(string $type, string $severity, string $message,
                                   array $context = [], ?string $ip = null): void
    {
        try {
            $ip  = $ip ?? self::clientIp();
            $new = SecurityAlert::recordOrTouch([
                'type'       => $type,
                'severity'   => $severity,
                'ip'         => $ip,
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'method'     => mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10),
                'uri'        => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 512),
                'message'    => $message,
                'context'    => $context,
            ], self::DEDUPE_MINUTES);

            // Notification e-mail : seulement pour les alertes sérieuses,
            // et seulement pour une NOUVELLE alerte (pas les répétitions),
            // throttlée à 1/heure par (type+ip).
            if ($new && in_array($severity, ['elevee', 'critique'], true)) {
                self::notify($type, $severity, $message, $ip, $context);
            }
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] record: ' . $e->getMessage());
        }
    }

    private static function notify(string $type, string $severity, string $message,
                                   string $ip, array $context): void
    {
        try {
            $redis = Redis::getInstance();
            $key   = 'secmon:notified:' . $type . ':' . $ip;
            if ($redis->isAvailable()) {
                if ($redis->exists($key)) {
                    return; // déjà notifié récemment
                }
                $redis->set($key, '1', self::NOTIFY_TTL);
            }

            // Canal WhatsApp (super-admin) — indépendant de l'e-mail : même si
            // aucune adresse e-mail n'est configurée, l'alerte WhatsApp doit partir.
            self::notifyWhatsApp($type, $severity, $message, $ip);

            $to = $_ENV['SECURITY_ALERT_EMAIL'] ?? $_ENV['MAIL_USERNAME'] ?? '';
            if ($to === '') {
                return;
            }

            $sev  = strtoupper($severity);
            $app  = $_ENV['APP_URL'] ?? '';
            $when = date('d/m/Y H:i:s');
            $ctx  = htmlspecialchars(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
            $html = "
                <div style=\"font-family:Montserrat,Arial,sans-serif;max-width:560px;margin:auto\">
                  <div style=\"background:#1F1C2C;color:#fff;padding:20px 24px;border-radius:16px 16px 0 0\">
                    <div style=\"font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#B79BFF\">Alerte de sécurité</div>
                    <div style=\"font-size:20px;font-weight:700;margin-top:4px\">{$sev} — " . htmlspecialchars($message) . "</div>
                  </div>
                  <div style=\"border:1px solid #E5E7EB;border-top:none;border-radius:0 0 16px 16px;padding:20px 24px;color:#1F1C2C\">
                    <p style=\"margin:0 0 6px\"><strong>Type :</strong> " . htmlspecialchars($type) . "</p>
                    <p style=\"margin:0 0 6px\"><strong>IP :</strong> " . htmlspecialchars($ip) . "</p>
                    <p style=\"margin:0 0 6px\"><strong>Horodatage :</strong> {$when}</p>
                    <p style=\"margin:0 0 6px\"><strong>URI :</strong> " . htmlspecialchars(mb_substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 240)) . "</p>
                    <pre style=\"background:#F3F4F6;padding:12px;border-radius:8px;font-size:12px;overflow:auto\">{$ctx}</pre>
                    <a href=\"{$app}/admin/securite\" style=\"display:inline-block;margin-top:8px;background:#8B52FA;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:600\">Ouvrir le tableau de bord sécurité</a>
                  </div>
                </div>";
            $text = "Alerte sécurité [{$sev}] : {$message}\nType: {$type}\nIP: {$ip}\nQuand: {$when}\n";

            Mailer::send($to, 'Administrateur', "🔐 Sécurité [{$sev}] — {$message}", $html, $text);
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] notify: ' . $e->getMessage());
        }
    }

    /**
     * Alerte WhatsApp au super-admin. Réutilise le pilote WhatsApp existant
     * (WHATSAPP_DRIVER : log/twilio/meta) via WhatsAppService::envoyerBrut —
     * pas de contexte dossier, pas de journalisation orientation.
     *
     * Destinataire : SECURITY_ALERT_WHATSAPP (.env), numéro gabonais normalisé
     * en E.164. Silencieux si le numéro est absent ou invalide.
     *
     * Le throttle (clé Redis secmon:notified:*) partagé avec l'e-mail garantit
     * qu'un seul message part par (type+ip) et par heure.
     */
    private static function notifyWhatsApp(string $type, string $severity, string $message, string $ip): void
    {
        try {
            $raw = trim((string) ($_ENV['SECURITY_ALERT_WHATSAPP'] ?? ''));
            if ($raw === '') {
                return; // canal WhatsApp non configuré
            }

            $e164 = SmsService::normaliserNumero('+241', $raw);
            if ($e164 === null) {
                error_log('[SecurityMonitor] SECURITY_ALERT_WHATSAPP invalide : ' . $raw);
                return;
            }

            $sev  = strtoupper($severity);
            $when = date('d/m/Y H:i');
            $app  = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
            $uri  = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 120);

            $texte  = "🔐 Sécurité [{$sev}]\n{$message}\n";
            $texte .= "Type : {$type}\nIP : {$ip}\nQuand : {$when}\n";
            if ($uri !== '') {
                $texte .= "URI : {$uri}\n";
            }
            if ($app !== '') {
                $texte .= $app . '/admin/securite';
            }

            (new WhatsAppService())->envoyerBrut($e164, $texte);
        } catch (\Throwable $e) {
            error_log('[SecurityMonitor] notifyWhatsApp: ' . $e->getMessage());
        }
    }

    private static function block(): void
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
        }
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Requête bloquée.']], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>403</title>'
               . '<body style="font-family:Montserrat,Arial,sans-serif;background:#FAF9F5;color:#1F1C2C;display:grid;place-items:center;height:100vh;margin:0">'
               . '<div style="text-align:center"><div style="font-size:48px;font-weight:800;color:#8B52FA">403</div>'
               . '<p>Requête bloquée par le pare-feu applicatif.</p></div></body></html>';
        }
        exit;
    }

    private static function blockingEnabled(): bool
    {
        try {
            return (string) Settings::get('security_block_attacks', '0') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Aplati un tableau de requête en une chaîne inspectable. */
    private static function flatten(array $arr, int $depth = 0): string
    {
        if ($depth > 3) {
            return '';
        }
        $out = [];
        foreach ($arr as $v) {
            if (is_array($v)) {
                $out[] = self::flatten($v, $depth + 1);
            } elseif (is_scalar($v)) {
                $out[] = (string) $v;
            }
        }
        return implode(' ', $out);
    }

    /** IP client (REMOTE_ADDR — non spoofable, pas de confiance aveugle au proxy). */
    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
