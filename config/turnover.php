<?php

return [
    'notice_days_before_term_end' => (int) env('TURNOVER_NOTICE_DAYS', 7),
    'invitation_expire_minutes' => (int) env('TURNOVER_INVITATION_EXPIRE_MINUTES', 60 * 24),
    'remind_later_hours' => (int) env('TURNOVER_REMIND_LATER_HOURS', 24),
    'registration_rate_limit' => (int) env('TURNOVER_REGISTRATION_RATE_LIMIT', 10),
];
