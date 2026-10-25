<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.

return array_replace(require __DIR__.'/../en/sms.php', [
    'stopped' => 'You no go receive campaign message again. Reply START if you want update again.',
    'started' => 'You go dey receive campaign update for SMS. Reply STOP anytime to stop am.',
]);
