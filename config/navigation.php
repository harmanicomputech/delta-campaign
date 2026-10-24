<?php

/*
|--------------------------------------------------------------------------
| Workspace navigation
|--------------------------------------------------------------------------
|
| The single source of truth for the workspace sidebar, mobile navigation and
| command palette.
|
| - "route":      named route. Items without a route are not yet built and are
|                 rendered as disabled "Planned" entries, never as dead links.
| - "phase":      implementation phase that delivers the item.
| - "permission": permission required to see the item (enforced from Phase 3).
| - "icon":       Lucide icon name (resources/icons/<name>.svg).
| - "mobile":     pin to the mobile bottom bar.
|
*/

return [

    [
        'label' => 'nav.groups.overview',
        'items' => [
            ['label' => 'nav.dashboard', 'icon' => 'layout-dashboard', 'route' => 'design-system.shell', 'phase' => 1, 'permission' => null, 'mobile' => true],
            ['label' => 'nav.command_centre', 'icon' => 'radar', 'route' => null, 'phase' => 6, 'permission' => 'command-centre.view'],
            ['label' => 'nav.approvals', 'icon' => 'stamp', 'route' => null, 'phase' => 6, 'permission' => 'approvals.view'],
        ],
    ],

    [
        'label' => 'nav.groups.people',
        'items' => [
            ['label' => 'nav.supporters', 'icon' => 'users', 'route' => null, 'phase' => 4, 'permission' => 'supporters.view', 'mobile' => true],
            ['label' => 'nav.volunteers', 'icon' => 'hand-heart', 'route' => null, 'phase' => 5, 'permission' => 'volunteers.view'],
            ['label' => 'nav.polling_agents', 'icon' => 'shield-check', 'route' => null, 'phase' => 11, 'permission' => 'polling-agents.view'],
        ],
    ],

    [
        'label' => 'nav.groups.field',
        'items' => [
            ['label' => 'nav.tasks', 'icon' => 'list-checks', 'route' => null, 'phase' => 5, 'permission' => 'tasks.view', 'mobile' => true],
            ['label' => 'nav.canvassing', 'icon' => 'map-pinned', 'route' => null, 'phase' => 5, 'permission' => 'canvassing.view'],
            ['label' => 'nav.events', 'icon' => 'calendar-days', 'route' => null, 'phase' => 10, 'permission' => 'events.view'],
        ],
    ],

    [
        'label' => 'nav.groups.communications',
        'items' => [
            ['label' => 'nav.messaging', 'icon' => 'send', 'route' => null, 'phase' => 9, 'permission' => 'messaging.view'],
            ['label' => 'nav.content', 'icon' => 'newspaper', 'route' => null, 'phase' => 10, 'permission' => 'content.view'],
            ['label' => 'nav.rapid_response', 'icon' => 'siren', 'route' => null, 'phase' => 10, 'permission' => 'rapid-response.view'],
            ['label' => 'nav.ussd_simulator', 'icon' => 'smartphone', 'route' => null, 'phase' => 8, 'permission' => 'ussd.simulate'],
        ],
    ],

    [
        'label' => 'nav.groups.election_day',
        'items' => [
            ['label' => 'nav.incidents', 'icon' => 'triangle-alert', 'route' => null, 'phase' => 11, 'permission' => 'incidents.view'],
            ['label' => 'nav.results', 'icon' => 'file-check', 'route' => null, 'phase' => 11, 'permission' => 'results.view'],
        ],
    ],

    [
        'label' => 'nav.groups.administration',
        'items' => [
            ['label' => 'nav.geography', 'icon' => 'map', 'route' => null, 'phase' => 2, 'permission' => 'geography.view'],
            ['label' => 'nav.users_roles', 'icon' => 'key-round', 'route' => null, 'phase' => 3, 'permission' => 'users.manage'],
            ['label' => 'nav.audit_log', 'icon' => 'scroll-text', 'route' => null, 'phase' => 3, 'permission' => 'audit.view'],
            ['label' => 'nav.privacy', 'icon' => 'lock-keyhole', 'route' => null, 'phase' => 4, 'permission' => 'privacy.manage'],
            ['label' => 'nav.design_system', 'icon' => 'palette', 'route' => 'design-system.index', 'phase' => 1, 'permission' => null],
        ],
    ],

];
