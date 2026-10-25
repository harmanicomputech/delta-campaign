<?php

// Replies stay within one SMS (160 characters) where possible.

return [
    'help' => 'Commands: STOP to stop messages, START to get updates again. Staff: REPORT <polling unit code> <message>.',
    'stopped' => 'You will no longer receive campaign messages. Reply START to get updates again.',
    'started' => 'You will receive campaign updates by SMS. Reply STOP anytime to stop.',
    'not_open' => 'Sign-ups are not open yet. Please try again later.',
    'report_not_registered' => 'REPORT is for registered campaign staff only.',
    'report_format' => 'Format: REPORT <polling unit code> <message>',
    'report_unknown_unit' => 'Polling unit :code was not found. Check the code and send again.',
    'report_outside_area' => 'That polling unit is outside your assigned area.',
    'report_received' => 'Report received. Ref :ref. Your coordinator has been notified.',
    'report_task_title' => 'SMS report from :name · :code',
    'report_task_description' => 'Sent by SMS from :name for polling unit :code (:unit).',
];
