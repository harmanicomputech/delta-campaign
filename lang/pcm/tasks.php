<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.
// Keys not translated yet fall back to English.

return array_replace(require __DIR__.'/../en/tasks.php', [
    'title' => 'Work wey dey ground',
    'intro' => 'Field work with owner, deadline and full history. If work pass deadline, we go escalate am to coordinators by itself.',
    'mine' => 'My work',
    'my_open' => 'My work wey never finish',
    'overdue' => 'Don pass deadline',
    'none' => 'No work',
    'none_mine_help' => 'Work wey dem give you go show here and for your notifications.',
    'comment_placeholder' => 'Add update…',
    'start' => 'Start',
    'complete' => 'E don finish',
    'reopen' => 'Open am again',
    'stale' => 'Another person don update this work. We dey show the latest one; abeg try again.',
    'overdue_banner' => 'This work suppose finish :date.',
    'notify_assigned' => 'New work: :title',
    'notify_overdue' => 'E don pass deadline: :title',
    'notify_escalated' => 'Dem don escalate am: :title',
]);
