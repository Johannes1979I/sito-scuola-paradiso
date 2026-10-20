<?php
/* invia.php — riceve i moduli del sito (contatti, pre-iscrizione, Open Day) e li spedisce
   via email alla segreteria. Nessun messaggio viene salvato sul server.

   Configurazione facoltativa (NON pubblicata su GitHub), cercata in quest'ordine:
     ../private/mail-config.php   (fuori dalla cartella pubblica: consigliato)
     admin/mail-config.php
   Modello: admin/mail-config.example.php. Senza configurazione usa mail() del server
   (Postfix locale) e scrive a segreteria@scuolasantamariadelparadiso.it. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Europe/Rome');

function out(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    out(405, ['ok' => false, 'error' => 'Metodo non consentito.']);
}

/* ---------- configurazione ---------- */
$cfg = [];
foreach ([dirname(__DIR__) . '/private/mail-config.php', __DIR__ . '/admin/mail-config.php'] as $f) {
    if (@is_file($f)) { $c = include $f; if (is_array($c)) { $cfg = $c; } break; }
}
$TO        = (string)($cfg['to'] ?? 'segreteria@scuolasantamariadelparadiso.it');
$FROM      = (string)($cfg['from'] ?? 'webmaster@scuolasantamariadelparadiso.it');
$FROM_NAME = (string)($cfg['fromName'] ?? 'Sito Scuola Santa Maria del Paradiso');
$SMTP      = is_array($cfg['smtp'] ?? null) ? $cfg['smtp'] : null;

/* ---------- solo dal nostro sito ---------- */
$host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) {
    out(403, ['ok' => false, 'error' => 'Origine non consentita.']);
}

/* ---------- campo trappola per i robot: se compilato fingiamo successo ---------- */
if (trim((string)($_POST['sito_web'] ?? '')) !== '') { out(200, ['ok' => true]); }

/* ---------- i moduli ammessi: etichetta di ogni campo e campi obbligatori ---------- */
$FORMS = [
    'Richiesta di contatto dal sito' => [
        'fields'   => ['nome' => 'Nome', 'cognome' => 'Cognome', 'email' => 'Email', 'messaggio' => 'Messaggio', 'privacy' => 'Consenso privacy'],
        'required' => ['nome', 'cognome', 'email', 'messaggio', 'privacy'],
        'who'      => ['nome', 'cognome'],
    ],
    'Richiesta di pre-iscrizione' => [
        'fields'   => ['alunno' => 'Alunno', 'ordine' => 'Scuola', 'genitore' => 'Genitore', 'telefono' => 'Telefono', 'email' => 'Email', 'messaggio' => 'Note', 'privacy' => 'Consenso privacy'],
        'required' => ['alunno', 'ordine', 'genitore', 'telefono', 'email', 'privacy'],
        'who'      => ['alunno'],
    ],
    'Prenotazione Open Day' => [
        'fields'   => ['nome' => 'Nome e cognome', 'telefono' => 'Telefono', 'email' => 'Email', 'giorno_preferito' => 'Giorno preferito', 'messaggio' => 'Messaggio', 'privacy' => 'Consenso privacy'],
        'required' => ['nome', 'telefono', 'email', 'privacy'],
        'who'      => ['nome'],
    ],
];
// il sito invia «Oggetto — Nome della scuola»: teniamo solo la parte prima del trattino
$modulo = trim(explode(' — ', (string)($_POST['subject'] ?? ''))[0]);
if (!isset($FORMS[$modulo])) { out(400, ['ok' => false, 'error' => 'Modulo non riconosciuto.']); }
$F = $FORMS[$modulo];

/* ---------- pulizia e controllo dei campi ---------- */
$val = [];
foreach ($F['fields'] as $k => $label) {
    $v = (string)($_POST[$k] ?? '');
    $long = ($k === 'messaggio');
    $v = $long ? preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $v)) : preg_replace('/\p{C}/u', ' ', $v);
    $v = trim((string)$v);
    if ((function_exists('mb_strlen') ? mb_strlen($v) : strlen($v)) > ($long ? 5000 : 200)) { out(400, ['ok' => false, 'error' => 'Il campo «' . $label . '» è troppo lungo.']); }
    $val[$k] = $v;
}
foreach ($F['required'] as $k) {
    if ($val[$k] === '') { out(400, ['ok' => false, 'error' => 'Compila il campo «' . $F['fields'][$k] . '».']); }
}
if (!filter_var($val['email'], FILTER_VALIDATE_EMAIL)) { out(400, ['ok' => false, 'error' => 'Indirizzo email non valido.']); }

/* ---------- limite: massimo 5 invii ogni 15 minuti dallo stesso indirizzo ---------- */
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$rl = rtrim(sys_get_temp_dir(), '/') . '/smp-form-' . sha1($ip . '|smp') . '.json';
$now = time();
$hits = array_values(array_filter((array)json_decode((string)@file_get_contents($rl), true), function ($t) use ($now) { return is_int($t) && $t > $now - 900; }));
if (count($hits) >= 5) { out(429, ['ok' => false, 'error' => 'Troppi invii ravvicinati: riprova tra qualche minuto.']); }

/* ---------- composizione dell'email ---------- */
function hdr(string $s): string { return trim(str_replace(["\r", "\n"], ' ', $s)); }
function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

$who = trim(implode(' ', array_map(function ($k) use ($val) { return $val[$k]; }, $F['who'])));
$subject = '[Sito] ' . $modulo . ($who !== '' ? ' — ' . $who : '');
$lines = ['Nuovo messaggio dal sito della scuola (modulo «' . $modulo . '»).', ''];
foreach ($F['fields'] as $k => $label) {
    if ($val[$k] === '' || $k === 'privacy') { continue; }
    $lines[] = ($k === 'messaggio') ? "$label:\n" . $val[$k] : "$label: " . $val[$k];
}
$lines[] = '';
$lines[] = 'Consenso privacy: ' . ($val['privacy'] !== '' ? 'sì' : 'no');
$lines[] = '—';
$pagina = (string)($_POST['pagina'] ?? '');
if ($pagina !== '' && filter_var($pagina, FILTER_VALIDATE_URL)) { $lines[] = 'Pagina: ' . hdr($pagina); }
$lines[] = 'Inviato il ' . date('d/m/Y') . ' alle ' . date('H:i');
$lines[] = 'Per rispondere basta premere «Rispondi»: la risposta arriverà direttamente a ' . $val['email'] . '.';
$body = chunk_split(base64_encode(implode("\r\n", str_replace("\n", "\r\n", $lines))));

$msgId = '<' . bin2hex(random_bytes(8)) . '.' . $now . '@' . substr(strrchr($FROM, '@') ?: '@localhost', 1) . '>';
$headers = [
    'From: ' . enc($FROM_NAME) . ' <' . hdr($FROM) . '>',
    'Reply-To: ' . hdr($val['email']),
    'Message-ID: ' . $msgId,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
];

/* ---------- invio: SMTP autenticato se configurato, altrimenti mail() del server ---------- */
function smtp_send(array $s, string $from, string $to, string $data)
{
    $host = (string)$s['host']; $port = (int)($s['port'] ?? 587); $secure = (string)($s['secure'] ?? 'tls');
    $verify = (bool)($s['verify'] ?? true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { return "connessione a $host:$port fallita ($es)"; }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') { break; } } return $r; };
    $cmd = function ($c, array $ok) use ($fp, $read) {
        if ($c !== null) { fwrite($fp, $c . "\r\n"); }
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) { throw new RuntimeException(trim($r) ?: 'nessuna risposta'); }
        return $r;
    };
    $ehlo = preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    try {
        $cmd(null, [220]);
        $cmd("EHLO $ehlo", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) { throw new RuntimeException('STARTTLS non riuscito'); }
            $cmd("EHLO $ehlo", [250]);
        }
        if (!empty($s['user'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string)$s['user']), [334]);
            $cmd(base64_encode((string)($s['pass'] ?? '')), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        fwrite($fp, preg_replace('/^\./m', '..', $data) . "\r\n.\r\n");
        $cmd(null, [250]);
        $cmd('QUIT', [221]);
    } catch (Throwable $e) {
        fclose($fp);
        return $e->getMessage();
    }
    fclose($fp);
    return true;
}

if ($SMTP && !empty($SMTP['host'])) {
    $data = implode("\r\n", array_merge([
        'Date: ' . date(DATE_RFC2822),
        'To: <' . hdr($TO) . '>',
        'Subject: ' . enc($subject),
    ], $headers)) . "\r\n\r\n" . $body;
    $res = smtp_send($SMTP, $FROM, $TO, $data);
} else {
    $res = @mail($TO, enc($subject), $body, implode("\r\n", $headers)) ? true : 'mail() ha restituito false';
}

if ($res !== true) {
    error_log('invia.php: invio non riuscito (' . $modulo . '): ' . $res);   // nessun dato personale nel log
    out(502, ['ok' => false, 'error' => 'Invio non riuscito.']);
}
$hits[] = $now;
@file_put_contents($rl, json_encode($hits), LOCK_EX);
out(200, ['ok' => true]);
