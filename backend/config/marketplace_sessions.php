<?php

return [
    'member_minutes' => max(1, (int) env('MARKETPLACE_MEMBER_SESSION_MINUTES', 480)),
    'admin_minutes' => max(1, (int) env('MARKETPLACE_ADMIN_SESSION_MINUTES', 30)),
];
