<?php

/*
| Settings that authorised staff can change at runtime (settings.manage).
| Values here are defaults; changes are stored in system_settings and audited.
*/

return [
    'privacy.retention_months' => ['type' => 'integer', 'default' => (int) env('SUPPORTER_RETENTION_MONTHS', 36), 'label' => 'Supporter retention (months)', 'min' => 6, 'max' => 120],
    'privacy.dsr_response_days' => ['type' => 'integer', 'default' => (int) env('DSR_RESPONSE_DAYS', 30), 'label' => 'Data-subject request deadline (days)', 'min' => 1, 'max' => 90],
];
