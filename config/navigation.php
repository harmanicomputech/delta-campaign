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
| - "permission": permission required to see the item (a list means any of them).
| - "icon":       Lucide icon name (resources/icons/<name>.svg).
| - "mobile":     pin to the mobile bottom bar.
| - "production": false hides the item in production (development tools).
|
*/

return [

    [
        'label' => 'nav.groups.overview',
        'items' => [
            ['label' => 'nav.dashboard', 'icon' => 'layout-dashboard', 'route' => 'workspace.dashboard', 'phase' => 3, 'permission' => 'dashboard.view', 'mobile' => true],
            ['label' => 'nav.command_centre', 'icon' => 'radar', 'route' => 'workspace.command-centre', 'phase' => 6, 'permission' => 'command-centre.view'],
            ['label' => 'nav.approvals', 'icon' => 'stamp', 'route' => 'workspace.approvals', 'phase' => 6, 'permission' => 'approvals.view'],
        ],
    ],

    [
        'label' => 'nav.groups.people',
        'items' => [
            ['label' => 'nav.supporters', 'icon' => 'users', 'route' => 'workspace.supporters.index', 'phase' => 4, 'permission' => 'supporters.view', 'mobile' => true],
            ['label' => 'nav.volunteers', 'icon' => 'hand-heart', 'route' => 'workspace.volunteers.index', 'phase' => 5, 'permission' => 'volunteers.view'],
            ['label' => 'nav.training', 'icon' => 'graduation-cap', 'route' => 'workspace.training.index', 'phase' => 5, 'permission' => ['training.take', 'training.manage']],
            ['label' => 'nav.polling_agents', 'icon' => 'shield-check', 'route' => 'workspace.election.agents', 'phase' => 11, 'permission' => 'polling-agents.view'],
        ],
    ],

    [
        'label' => 'nav.groups.field',
        'items' => [
            ['label' => 'nav.tasks', 'icon' => 'list-checks', 'route' => 'workspace.tasks.index', 'phase' => 5, 'permission' => ['tasks.view', 'tasks.work'], 'mobile' => true],
            ['label' => 'nav.canvassing', 'icon' => 'map-pinned', 'route' => 'workspace.canvassing.index', 'phase' => 5, 'permission' => ['canvassing.view', 'canvassing.record'], 'mobile' => true],
            ['label' => 'nav.events', 'icon' => 'calendar-days', 'route' => 'workspace.events.index', 'phase' => 10, 'permission' => 'events.view'],
        ],
    ],

    [
        'label' => 'nav.groups.communications',
        'items' => [
            ['label' => 'nav.messaging', 'icon' => 'send', 'route' => 'workspace.messaging.index', 'phase' => 9, 'permission' => 'messaging.view'],
            ['label' => 'nav.content', 'icon' => 'newspaper', 'route' => 'workspace.content.index', 'phase' => 10, 'permission' => ['content.view', 'projects.manage', 'projects.approve'], 'also' => ['workspace.projects.*']],
            ['label' => 'nav.rapid_response', 'icon' => 'siren', 'route' => 'workspace.rapid-response.index', 'phase' => 10, 'permission' => 'rapid-response.view'],
            ['label' => 'nav.contact_inbox', 'icon' => 'inbox', 'route' => 'workspace.contact.index', 'phase' => 10, 'permission' => 'contact.manage'],
            ['label' => 'nav.ussd_simulator', 'icon' => 'smartphone', 'route' => 'workspace.ussd-simulator', 'phase' => 8, 'permission' => 'ussd.simulate'],
        ],
    ],

    [
        'label' => 'nav.groups.election_day',
        'items' => [
            ['label' => 'nav.my_polling_unit', 'icon' => 'vote', 'route' => 'workspace.election.agent', 'phase' => 11, 'permission' => 'election.report', 'mobile' => true],
            ['label' => 'nav.election_day', 'icon' => 'layout-dashboard', 'route' => 'workspace.election.hub', 'phase' => 11, 'permission' => ['polling-agents.view', 'incidents.view', 'results.view'], 'also' => ['workspace.election.units.*', 'workspace.election.setup', 'workspace.election.packs']],
            ['label' => 'nav.incidents', 'icon' => 'triangle-alert', 'route' => 'workspace.election.incidents.index', 'phase' => 11, 'permission' => 'incidents.view'],
            ['label' => 'nav.results', 'icon' => 'file-check', 'route' => 'workspace.election.results', 'phase' => 11, 'permission' => 'results.view'],
        ],
    ],

    [
        'label' => 'nav.groups.administration',
        'items' => [
            ['label' => 'nav.geography', 'icon' => 'map', 'route' => 'workspace.geography', 'phase' => 6, 'permission' => 'geography.view'],
            ['label' => 'nav.users_roles', 'icon' => 'key-round', 'route' => 'workspace.users.index', 'phase' => 3, 'permission' => 'users.view'],
            ['label' => 'nav.audit_log', 'icon' => 'scroll-text', 'route' => 'workspace.audit.index', 'phase' => 3, 'permission' => 'audit.view'],
            ['label' => 'nav.privacy', 'icon' => 'lock-keyhole', 'route' => 'workspace.privacy.index', 'phase' => 4, 'permission' => 'privacy.manage'],
            ['label' => 'nav.settings', 'icon' => 'settings', 'route' => 'workspace.settings', 'phase' => 6, 'permission' => 'settings.manage'],
            ['label' => 'nav.exports', 'icon' => 'download', 'route' => 'workspace.exports.index', 'phase' => 4, 'permission' => 'supporters.export'],
            ['label' => 'nav.design_system', 'icon' => 'palette', 'route' => 'design-system.index', 'phase' => 1, 'permission' => 'system.health', 'production' => false],
        ],
    ],

];
