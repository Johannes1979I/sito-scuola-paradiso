<?php
/* Modello di configurazione per invia.php (l'invio dei moduli del sito).
   Copialo come  private/mail-config.php  (cartella fuori dal web, consigliato)
   oppure come  admin/mail-config.php,  e compila i valori.
   Il file vero NON va su GitHub (è in .gitignore) e il deploy non lo carica.
   Senza configurazione invia.php usa mail() del server e scrive alla segreteria. */
return [
    'to'       => 'segreteria@scuolasantamariadelparadiso.it',   // dove arrivano i messaggi
    'from'     => 'webmaster@scuolasantamariadelparadiso.it',    // mittente (deve essere del dominio)
    'fromName' => 'Sito Scuola Santa Maria del Paradiso',

    // Facoltativo: invio tramite la casella della scuola (consigliato per non finire nello spam).
    // Se presente, usa SMTP autenticato invece di mail().
    // 'smtp' => [
    //     'host'   => 'mail.scuolasantamariadelparadiso.it',   // o il nome del server indicato dal provider
    //     'port'   => 587,          // 587 = STARTTLS ('secure' => 'tls'), 465 = SSL ('secure' => 'ssl')
    //     'secure' => 'tls',
    //     'user'   => 'webmaster@scuolasantamariadelparadiso.it',
    //     'pass'   => '********',
    //     'verify' => true,         // false solo se il certificato non corrisponde al nome del server
    // ],
];
