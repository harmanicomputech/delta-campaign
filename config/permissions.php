<?php

/*
|--------------------------------------------------------------------------
| Permissions and roles
|--------------------------------------------------------------------------
|
| Source of truth for RBAC, applied by RolesAndPermissionsSeeder (idempotent).
| Permissions say WHAT a user may do; the scope of their role assignment says
| WHERE (statewide, district, LGA, ward or polling unit). Both are enforced
| server-side on every query, export and job.
|
| scope_level: the geographic level assignments of the role are made at.
| privileged:  requires a TOTP second factor.
|
*/

return [

    'permissions' => [
        'workspace' => [
            'dashboard.view' => 'Open the workspace dashboard',
            'command-centre.view' => 'View the leadership command centre',
            'reports.view' => 'View aggregate reports',
            'approvals.view' => 'View the approvals inbox',
        ],
        'geography' => [
            'geography.view' => 'Browse districts, LGAs, wards and polling units',
            'geography.import' => 'Upload and apply geographic reference-data imports',
        ],
        'users' => [
            'users.view' => 'View user accounts in scope',
            'users.manage' => 'Create, suspend and reactivate users in scope',
            'roles.assign' => 'Assign and revoke roles within own scope',
            'roles.assign-privileged' => 'Assign privileged roles',
        ],
        'audit' => [
            'audit.view' => 'View the audit log',
            'audit.verify' => 'Verify the audit-log hash chain',
        ],
        'supporters' => [
            'supporters.view' => 'View supporter records in scope (masked contact details)',
            'supporters.view-contact' => 'Reveal full supporter phone numbers (access-logged)',
            'supporters.create' => 'Register supporters',
            'supporters.update' => 'Correct supporter records',
            'supporters.verify' => 'Verify or reject supporter records',
            'supporters.export' => 'Export supporter records in scope',
        ],
        'privacy' => [
            'privacy.manage' => 'Manage privacy notices, retention and suppression',
            'dsr.manage' => 'Handle data-subject requests',
        ],
        'volunteers' => [
            'volunteers.view' => 'View volunteers in scope',
            'volunteers.manage' => 'Assign, suspend and edit volunteers in scope',
            'volunteers.approve' => 'Approve or reject volunteer applications',
            'training.manage' => 'Create training modules and quizzes',
            'training.take' => 'Complete training modules',
        ],
        'field' => [
            'tasks.view' => 'View tasks in scope',
            'tasks.manage' => 'Create, assign and escalate tasks in scope',
            'tasks.work' => 'Work on tasks assigned to me',
            'canvassing.view' => 'View walk lists and contacts in scope',
            'canvassing.manage' => 'Create and assign walk lists; review data quality',
            'canvassing.record' => 'Record contacts on my walk lists',
            'events.view' => 'View events',
            'events.manage' => 'Create, publish and cancel events; check in attendees',
        ],
        'communications' => [
            'messaging.view' => 'View message campaigns and delivery',
            'messaging.create' => 'Draft message campaigns',
            'messaging.approve' => 'Approve message campaigns (not own)',
            'messaging.templates' => 'Manage message templates',
            'content.view' => 'View content in the CMS',
            'content.create' => 'Draft content',
            'content.review' => 'Review and approve content',
            'content.publish' => 'Publish, schedule and withdraw content',
            'projects.manage' => 'Draft public project records',
            'projects.approve' => 'Approve and publish public project records',
            'rapid-response.view' => 'View rapid-response cases',
            'rapid-response.manage' => 'Create and work rapid-response cases',
            'rapid-response.approve' => 'Approve rapid-response publication (incl. legal stage)',
            'contact.manage' => 'Read and answer messages from the public contact form',
            'ussd.simulate' => 'Use the USSD simulator',
        ],
        'election' => [
            'polling-agents.view' => 'View polling-agent assignments in scope',
            'polling-agents.manage' => 'Assign polling agents in scope',
            'election.report' => 'Submit election-day reports for my assigned polling unit',
            'incidents.view' => 'View incidents in scope',
            'incidents.manage' => 'Triage and resolve incidents in scope',
            'results.view' => 'View result submissions in scope',
            'results.verify' => 'Verify result submissions (not own)',
            'results.official' => 'Record official-source results',
            'evidence.view' => 'View and download evidence (access-logged)',
            'evidence.export' => 'Generate evidence packs',
        ],
        'system' => [
            'settings.manage' => 'Change platform settings',
            'imports.manage' => 'View all imports and exports',
            'system.horizon' => 'Open the queue dashboard',
            'system.health' => 'View detailed system health',
        ],
    ],

    /*
    | Self-service permissions act only on the holder's own work (assigned tasks,
    | own canvassing, own training, own polling-unit reports). They grant no power
    | over other people or data, so they are ignored by the anti-escalation rule
    | that an assigner must hold every permission of the role they grant.
    */
    'self_service' => ['tasks.work', 'canvassing.record', 'training.take', 'election.report'],

    'roles' => [
        'super_admin' => [
            'name' => 'Super administrator',
            'scope_level' => 'state',
            'privileged' => true,
            'description' => 'Full platform administration.',
            'permissions' => ['*'],
        ],
        'dg' => [
            'name' => 'Director General',
            'scope_level' => 'state',
            'privileged' => true,
            'description' => 'Statewide leadership visibility and approvals.',
            'permissions' => [
                'dashboard.view', 'command-centre.view', 'reports.view', 'approvals.view', 'geography.view',
                'users.view', 'audit.view', 'supporters.view', 'supporters.export', 'volunteers.view', 'volunteers.approve',
                'tasks.view', 'tasks.manage', 'canvassing.view', 'events.view', 'messaging.view', 'messaging.approve',
                'content.view', 'content.review', 'content.publish', 'projects.approve', 'rapid-response.view', 'rapid-response.approve',
                'polling-agents.view', 'incidents.view', 'results.view', 'evidence.view', 'evidence.export',
            ],
        ],
        'state_director' => [
            'name' => 'State director',
            'scope_level' => 'state',
            'privileged' => true,
            'description' => 'Runs statewide operations.',
            'permissions' => [
                'dashboard.view', 'command-centre.view', 'reports.view', 'approvals.view', 'geography.view', 'geography.import',
                'users.view', 'users.manage', 'roles.assign', 'supporters.view', 'supporters.create', 'supporters.update',
                'supporters.verify', 'supporters.export', 'volunteers.view', 'volunteers.manage', 'volunteers.approve',
                'training.manage', 'tasks.view', 'tasks.manage', 'canvassing.view', 'canvassing.manage', 'events.view',
                'events.manage', 'messaging.view', 'messaging.create', 'messaging.approve', 'content.view', 'contact.manage', 'polling-agents.view',
                'polling-agents.manage', 'incidents.view', 'incidents.manage', 'results.view', 'results.verify', 'evidence.view',
                'evidence.export', 'ussd.simulate',
            ],
        ],
        'district_coordinator' => [
            'name' => 'Senatorial district coordinator',
            'scope_level' => 'district',
            'privileged' => false,
            'description' => 'Coordinates one senatorial district.',
            'permissions' => [
                'dashboard.view', 'command-centre.view', 'reports.view', 'approvals.view', 'geography.view', 'users.view',
                'users.manage', 'roles.assign', 'supporters.view', 'supporters.create', 'supporters.update', 'supporters.verify',
                'volunteers.view', 'volunteers.manage', 'volunteers.approve', 'tasks.view', 'tasks.manage', 'canvassing.view',
                'canvassing.manage', 'events.view', 'events.manage', 'messaging.view', 'messaging.create', 'polling-agents.view',
                'polling-agents.manage', 'incidents.view', 'incidents.manage', 'results.view', 'results.verify', 'evidence.view',
            ],
        ],
        'lga_coordinator' => [
            'name' => 'LGA coordinator',
            'scope_level' => 'lga',
            'privileged' => false,
            'description' => 'Coordinates one LGA.',
            'permissions' => [
                'dashboard.view', 'reports.view', 'approvals.view', 'geography.view', 'users.view', 'users.manage', 'roles.assign',
                'supporters.view', 'supporters.create', 'supporters.update', 'supporters.verify', 'volunteers.view',
                'volunteers.manage', 'volunteers.approve', 'tasks.view', 'tasks.manage', 'canvassing.view', 'canvassing.manage',
                'events.view', 'events.manage', 'messaging.view', 'messaging.create', 'polling-agents.view', 'polling-agents.manage',
                'incidents.view', 'incidents.manage', 'results.view', 'results.verify', 'evidence.view',
            ],
        ],
        'ward_coordinator' => [
            'name' => 'Ward coordinator',
            'scope_level' => 'ward',
            'privileged' => false,
            'description' => 'Coordinates one ward.',
            'permissions' => [
                'dashboard.view', 'reports.view', 'geography.view', 'users.view', 'supporters.view', 'supporters.create',
                'supporters.update', 'volunteers.view', 'volunteers.manage', 'tasks.view', 'tasks.manage', 'tasks.work',
                'canvassing.view', 'canvassing.manage', 'canvassing.record', 'events.view', 'polling-agents.view',
                'incidents.view', 'results.view', 'training.take',
            ],
        ],
        'volunteer' => [
            'name' => 'Volunteer',
            'scope_level' => 'ward',
            'privileged' => false,
            'description' => 'Field volunteer in one ward.',
            'permissions' => ['dashboard.view', 'tasks.work', 'canvassing.record', 'supporters.create', 'events.view', 'training.take'],
        ],
        'polling_agent' => [
            'name' => 'Polling agent',
            'scope_level' => 'polling_unit',
            'privileged' => false,
            'description' => 'Election-day reporting for one polling unit.',
            'permissions' => ['dashboard.view', 'election.report', 'tasks.work', 'training.take'],
        ],
        'content_editor' => [
            'name' => 'Content editor',
            'scope_level' => 'state',
            'privileged' => false,
            'description' => 'Drafts public content and project records.',
            'permissions' => ['dashboard.view', 'content.view', 'content.create', 'projects.manage', 'events.view', 'events.manage', 'contact.manage', 'geography.view'],
        ],
        'rapid_response' => [
            'name' => 'Rapid-response officer',
            'scope_level' => 'state',
            'privileged' => false,
            'description' => 'Works claims and incidents needing a public response.',
            'permissions' => ['dashboard.view', 'rapid-response.view', 'rapid-response.manage', 'content.view', 'content.create', 'incidents.view', 'evidence.view'],
        ],
        'data_protection_officer' => [
            'name' => 'Data Protection Officer',
            'scope_level' => 'state',
            'privileged' => true,
            'description' => 'Oversees personal data, consent, retention and data-subject requests.',
            'permissions' => [
                'dashboard.view', 'privacy.manage', 'dsr.manage', 'audit.view', 'audit.verify', 'supporters.view',
                'supporters.view-contact', 'supporters.update', 'users.view', 'messaging.view', 'imports.manage', 'reports.view',
            ],
        ],
        'analyst' => [
            'name' => 'Analyst',
            'scope_level' => 'state',
            'privileged' => false,
            'description' => 'Aggregate reporting only; no access to individual contact details.',
            'permissions' => ['dashboard.view', 'command-centre.view', 'reports.view', 'geography.view'],
        ],
    ],

];
