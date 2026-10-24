<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.
// Keys not translated yet fall back to English.

return array_replace(require __DIR__.'/../en/training.php', [
    'intro' => 'Short lessons and quiz wey go prepare volunteers and polling agents for field.',
    'none' => 'No training module yet',
    'none_help' => 'Your coordinators go put modules here.',
    'progress' => 'You don pass :done out of :total modules',
    'progress_help' => 'If you pass all the modules for your role, your onboarding don complete.',
    'start' => 'Start',
    'quiz_help' => 'Answer all the questions. You need :mark% to pass, and you fit try again.',
    'answer_all' => 'Answer all the questions before you submit.',
    'submit' => 'Submit answers',
    'passed' => 'You don pass',
    'not_passed' => 'You never pass',
    'not_started' => 'You never start',
    'passed_toast' => 'You pass with :score%',
    'failed_toast' => 'You get :score%. You need :mark% to pass.',
    'passed_help' => 'Well done. You don finish this module.',
    'retry_help' => 'Read the module again, then try again when you ready.',
    'retry' => 'Try again',
    'best' => 'Your best score so far: :score%',
]);
