<?php
/* Autenticazione e helper condivisi del pannello admin */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_cfg(): array {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/config.php'; }
    return $c;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}

function csrf_check($t): bool {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function is_logged(): bool {
    return !empty($_SESSION['auth']);
}

/* Per pagine HTML: redirect al login se non autenticato */
function require_login_redirect(): void {
    if (!is_logged()) { header('Location: index.php'); exit; }
}

/* Per endpoint JSON: 401 se non autenticato */
function require_login_api(): void {
    if (!is_logged()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

function content_path(): string {
    return dirname(__DIR__) . '/content.json';
}

function read_content(): array {
    $p = content_path();
    if (is_file($p)) {
        $d = json_decode((string)file_get_contents($p), true);
        return is_array($d) ? $d : [];
    }
    return [];
}

function write_content(array $data): bool {
    return @file_put_contents(
        content_path(),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

/* Pagine .html modificabili nella root del sito */
function site_pages(): array {
    $root = dirname(__DIR__);
    $pages = [];
    foreach (glob($root . '/*.html') as $f) {
        $pages[] = basename($f);
    }
    sort($pages);
    return $pages;
}

/* ---------- Configurazione sito (menu di navigazione) ---------- */
function default_nav(): array {
    return [
        ['page' => 'index.html', 'label' => 'Home'],
        ['page' => 'chi-siamo.html', 'label' => 'Chi siamo'],
        ['page' => 'offerta-formativa.html', 'label' => 'Offerta formativa'],
        ['page' => 'vita-scolastica.html', 'label' => 'Vita scolastica'],
        ['page' => 'teatro.html', 'label' => 'Teatro'],
        ['page' => 'sostienici.html', 'label' => 'Sostienici'],
        ['page' => 'news.html', 'label' => 'News'],
        ['page' => 'contatti.html', 'label' => 'Contatti'],
    ];
}
function site_path(): string { return dirname(__DIR__) . '/site.json'; }
function read_site(): array {
    $p = site_path();
    if (is_file($p)) {
        $d = json_decode((string)file_get_contents($p), true);
        if (is_array($d)) {
            if (!isset($d['nav']) || !is_array($d['nav'])) { $d['nav'] = default_nav(); }
            return $d;
        }
    }
    return ['nav' => default_nav()];
}
function write_site(array $d): bool {
    return @file_put_contents(
        site_path(),
        json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

/* Slug di pagina da un'etichetta: "I nostri Progetti" -> "i-nostri-progetti.html" */
function slugify_page(string $label): string {
    $s = strtolower(trim($label));
    $tr = ['à'=>'a','á'=>'a','è'=>'e','é'=>'e','ì'=>'i','í'=>'i','ò'=>'o','ó'=>'o','ù'=>'u','ú'=>'u',
           'ç'=>'c',"'"=>'-','’'=>'-'];
    $s = strtr($s, $tr);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s;
}
function reserved_slugs(): array { return ['admin', 'assets', 'uploads', 'content', 'site', 'config']; }

