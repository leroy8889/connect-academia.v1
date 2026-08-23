<?php
declare(strict_types=1);

namespace Core;

class Response
{
    /**
     * Réponse JSON. Le corps est encodé avec JSON_HEX_TAG|AMP|APOS|QUOT :
     * les caractères `< > & ' "` sortent en séquences `\uXXXX` (transparent
     * pour tout parseur JSON) — défense en profondeur qui neutralise toute
     * tentative de sniffing XSS, en complément du `Content-Type: application/json`
     * et de l'en-tête global `X-Content-Type-Options: nosniff`.
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        // Le corps est du JSON servi en `application/json` + `nosniff`, et les
        // métacaractères HTML sont échappés en hexadécimal ci-dessus : la sortie
        // ne peut pas être interprétée comme du HTML/JS. On déclare donc à Psalm
        // que cet encodage neutralise la teinte HTML (faux positif TaintedHtml /
        // TaintedTextWithQuotes du sink `echo`).
        /**
         * @psalm-taint-escape html
         * @psalm-taint-escape has_quotes
         */
        $body = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        echo $body;
        exit;
    }

    public static function redirect(string $path, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . self::safeLocation($path), true, $status);
        exit;
    }

    /**
     * Sécurité (WEB-04) — construit une valeur d'en-tête `Location` sûre :
     *   1. suppression des caractères CRLF/NUL (anti injection d'en-tête) ;
     *   2. seuls les chemins internes (`/…` NON suivi de `/` ni `\`) sont
     *      acceptés puis préfixés par BASE_URL — les navigateurs replient
     *      `\` en `/`, donc "/\evil.com" deviendrait "//evil.com" (URL
     *      protocol-relative → redirection externe) si BASE_URL est vide ;
     *   3. une URL absolue n'est tolérée que si elle vise la MÊME origine
     *      (HTTP_HOST identique) ; tout le reste retombe sur l'accueil.
     * La valeur retournée ne peut donc jamais provoquer d'open-redirect ni
     * d'injection d'en-tête : c'est ce que déclare @psalm-taint-escape.
     *
     * @psalm-taint-escape header
     */
    private static function safeLocation(string $path): string
    {
        $path = str_replace(["\r", "\n", "\0"], '', $path);

        if (preg_match('#^/(?![/\\\\])#', $path)) {
            // Chemin interne fourni par l'application → on préfixe BASE_URL.
            if (defined('BASE_URL') && BASE_URL !== '') {
                return BASE_URL . $path;
            }
            return $path;
        }

        // URL absolue ou protocol-relative : on n'accepte QUE la même origine
        // (ex. HTTP_REFERER légitime), en n'en gardant que le chemin+query.
        $safe = self::sameOriginPath($path);
        if ($safe !== null) {
            return $safe;
        }
        return (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL . '/' : '/';
    }

    /**
     * Extrait le chemin interne d'une URL absolue UNIQUEMENT si elle pointe
     * vers la même origine que la requête courante. Renvoie null sinon.
     * Le chemin renvoyé contient déjà l'éventuel préfixe BASE_URL (il provient
     * d'une URL réelle du site) : ne pas le re-préfixer.
     */
    private static function sameOriginPath(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return null;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || strcasecmp($parts['host'], $host) !== 0) {
            return null;
        }
        $p = $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $p .= '?' . $parts['query'];
        }
        return preg_match('#^/(?![/\\\\])#', $p) ? $p : null;
    }

    public static function view(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        // Sécurité (WEB-08) — durcissement de extract() : on ne crée que des
        // variables au nom valide et on n'écrase JAMAIS une variable déjà
        // définie ici ($viewPath, $data, $layout…). Empêche toute injection
        // de variable si une clé venait un jour d'une entrée utilisateur.
        $safeData = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/^[a-zA-Z_]\w*$/', $key)) {
                $safeData[$key] = $value;
            }
        }

        // Les clés retenues sont validées par la regex `^[a-zA-Z_]\w*$` et
        // extract() tourne en EXTR_SKIP : aucune variable existante n'est
        // écrasée et aucun nom arbitraire n'est créé. Le tableau est donc sûr
        // vis-à-vis d'extract() — on l'annonce à Psalm (faux positif
        // TaintedExtract ; la teinte ne porte que sur des valeurs de vue,
        // échappées à l'affichage dans les gabarits).
        /** @psalm-taint-escape extract */
        $viewVars = $safeData;
        extract($viewVars, EXTR_SKIP);

        ob_start();
        $viewFile = BASE_PATH . '/app/Views/' . $viewPath . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }
        require $viewFile;
        $content = ob_get_clean();

        if ($layout) {
            $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout introuvable : {$layoutFile}");
            }
            require $layoutFile;
        } else {
            echo $content;
        }
    }
}
