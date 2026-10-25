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
    'election_help' => 'Agents: ARRIVED - TURNOUT <number> - INCIDENT <type> <details> - RESULT A=<n> V=<n> R=<n> <PARTY>=<n> ... Types: VIOLENCE BUYING MATERIALS BVAS LATE OTHER',
    'election_not_agent' => 'This number has no polling-unit assignment for an active election.',
    'election_arrived' => 'Arrival recorded at :code. Next: send TURNOUT <number> during the day.',
    'election_turnout_format' => 'Format: TURNOUT <number accredited so far>, e.g. TURNOUT 120',
    'election_turnout_ok' => 'Turnout :count recorded. Thank you.',
    'election_incident_format' => 'Format: INCIDENT <type> <details>. Types: VIOLENCE THREAT INTIMIDATION BUYING MATERIALS BVAS LATE PROCEDURE ACCESS OTHER',
    'election_incident_ok' => 'Incident :ref recorded. Your coordinator has been alerted. Stay safe.',
    'election_result_format' => 'Format: RESULT A=<accredited> V=<valid> R=<rejected> <PARTY>=<votes> ... e.g. RESULT A=350 V=300 R=10 ABC=150 XYZ=150',
    'election_result_ok' => 'Result :ref received (unverified). Upload a photo of the result sheet in the app when you can.',
    'election_result_issues' => 'Result :ref received but needs checking: :issue',
];
