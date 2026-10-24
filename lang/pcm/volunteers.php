<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.
// Keys not translated yet fall back to English.

return array_replace(require __DIR__.'/../en/volunteers.php', [
    'intro' => 'Volunteer wey apply and volunteer wey dey work for your area. If you approve volunteer, e go get account for im ward.',
    'applications' => 'Applications',
    'search' => 'Search name or full phone number',
    'none' => 'We no see any volunteer',
    'no_applications' => 'No application dey wait',
    'none_help' => 'Applications from website, USSD and staff go show here make una approve.',
    'add' => 'Add application',
    'add_help' => 'Write application for person wey agree make you do am for am.',
    'add_privacy' => 'Only write skills and time wey the person tell you. If you approve, we go send am welcome SMS.',
    'already_applied' => 'This number don already apply as volunteer.',
    'application_added' => 'We don add application for :name',
    'approved_ok' => 'We don approve :name',
    'approved_help' => 'Dem fit sign in now with their phone number.',
    'reject_help' => 'We go keep the reason for the record history.',
    'suspend_help' => 'We go remove their volunteer role sharp sharp. If na their only role, we go suspend their account and sign dem out.',
    'reassign_help' => 'E go move the volunteer and their field access go another ward for your area.',
    'ward_required' => 'Choose ward for this volunteer before you approve. Volunteer dey work for one ward.',
    'reason' => 'Reason',
]);
