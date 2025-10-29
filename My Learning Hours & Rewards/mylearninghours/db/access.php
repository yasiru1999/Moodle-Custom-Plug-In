<?php
// File: blocks/mylearninghours/db/access.php

$capabilities = [
    'block/mylearninghours:myaddinstance' => [
        'captype'     => 'read',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [ 'user'=>CAP_ALLOW ],
    ],
    'block/mylearninghours:addinstance' => [
        'riskbitmask' => RISK_SPAM|RISK_XSS,
        'captype'     => 'read',
        'contextlevel'=> CONTEXT_BLOCK,
        'archetypes'  => [ 'manager'=>CAP_ALLOW ],
    ],
];
