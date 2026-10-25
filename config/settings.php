<?php

/*
| Settings that authorised staff can change at runtime (settings.manage).
| Values here are defaults; changes are stored in system_settings and audited.
*/

return [
    'privacy.retention_months' => ['type' => 'integer', 'default' => (int) env('SUPPORTER_RETENTION_MONTHS', 36), 'label' => 'Supporter retention (months)', 'min' => 6, 'max' => 120],
    'privacy.dsr_response_days' => ['type' => 'integer', 'default' => (int) env('DSR_RESPONSE_DAYS', 30), 'label' => 'Data-subject request deadline (days)', 'min' => 1, 'max' => 90],

    // Communications engine. Costs are estimates in naira: set them from your provider's tariff.
    'messaging.approval_threshold' => ['type' => 'integer', 'default' => 100, 'label' => 'Recipients needing a second approver', 'min' => 1, 'max' => 1000000],
    'messaging.monthly_budget' => ['type' => 'integer', 'default' => (int) env('MESSAGING_MONTHLY_BUDGET', 50000), 'label' => 'Monthly messaging budget (₦)', 'min' => 0, 'max' => 100000000],
    'messaging.sms_segment_cost' => ['type' => 'decimal', 'default' => env('MESSAGING_SMS_SEGMENT_COST', '4.00'), 'label' => 'Estimated cost per SMS segment (₦)', 'min' => 0, 'max' => 100],
    'messaging.whatsapp_message_cost' => ['type' => 'decimal', 'default' => env('MESSAGING_WHATSAPP_COST', '10.00'), 'label' => 'Estimated cost per WhatsApp message (₦)', 'min' => 0, 'max' => 500],
    'messaging.quiet_start' => ['type' => 'integer', 'default' => 21, 'label' => 'Quiet hours start (hour, 0–23)', 'min' => 0, 'max' => 23],
    'messaging.quiet_end' => ['type' => 'integer', 'default' => 8, 'label' => 'Quiet hours end (hour, 0–23)', 'min' => 0, 'max' => 23],
    'messaging.rate_per_second' => ['type' => 'integer', 'default' => 10, 'label' => 'Provider send rate (messages per second)', 'min' => 1, 'max' => 500],
];
