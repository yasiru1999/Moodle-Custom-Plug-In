<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Capability definitions for block_completion_stats.
 *
 * @package    block_completion_stats
 * @copyright  2025, Yasiru Navoda Jayasekara
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/completion_stats:addinstance' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ],
    'block/completion_stats:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ],
];
