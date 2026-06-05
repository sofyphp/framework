<?php

declare(strict_types=1);

use Sofy\Feature\Flag;
use Sofy\View\UI;

// ── Section builders (closures keep variables local) ──────────────────────────

$buttonsTab = static function (): string {
    return implode('', [
        UI::heading('Variants', 3),
        '<div class="sofy-btn-group">',
        UI::button('Primary', '#', 'primary'),
        UI::button('Ghost',   '#', 'ghost'),
        UI::button('Warning', '#', 'warning'),
        UI::button('Danger',  '#', 'danger'),
        UI::button('Success', '#', 'success'),
        '</div>',
        UI::divider(),
        UI::heading('Sizes', 3),
        '<div class="sofy-btn-group">',
        UI::button('Small',  '#', 'primary', 'sm'),
        UI::button('Medium', '#', 'primary'),
        UI::button('Large',  '#', 'primary', 'lg'),
        '</div>',
        '<div class="sofy-btn-group" style="margin-top:10px">',
        UI::button('Small',  '#', 'ghost', 'sm'),
        UI::button('Medium', '#', 'ghost'),
        UI::button('Large',  '#', 'ghost', 'lg'),
        '</div>',
        UI::divider(),
        UI::heading('Method override + confirm dialog', 3),
        UI::text('Danger button wraps a hidden POST form with _method=DELETE and a JS confirm.', true),
        '<div class="sofy-btn-group">',
        UI::deleteButton('/posts/42', 'Delete Post'),
        UI::button('Archive', '/posts/42/archive', 'warning', 'sm', 'PUT',   'Archive this post?'),
        UI::button('Publish', '/posts/42/publish', 'success', 'sm', 'PATCH', 'Publish now?'),
        '</div>',
    ]);
};

$badgesTab = static function (): string {
    return implode('', [
        UI::heading('Badges — all variants', 3),
        '<p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:20px">',
        UI::badge('default'),
        UI::badge('success', 'success'),
        UI::badge('warning', 'warning'),
        UI::badge('danger',  'danger'),
        UI::badge('info',    'info'),
        UI::badge('accent',  'accent'),
        '</p>',
        UI::divider(),
        UI::heading('Alerts — without title', 3),
        UI::alert('Operation completed successfully.', 'success'),
        UI::alert('Your trial expires in 3 days.', 'warning'),
        UI::alert('Failed to connect to the database.', 'danger'),
        UI::alert('Maintenance window scheduled for Sunday.', 'info'),
        UI::divider(),
        UI::heading('Alerts — with title', 3),
        UI::alert('Your account has been created and is ready to use.', 'success', 'Account created'),
        UI::alert('You have unsaved changes. Review before leaving.',   'warning', 'Unsaved changes'),
        UI::alert('Payment was declined. Please update your billing.',  'danger',  'Payment failed'),
        UI::alert('Two-factor authentication is now available.',        'info',    'New feature'),
    ]);
};

$contentTab = static function (): string {
    $phpSample = <<<'PHP'
<?php

return UI::page('Dashboard')
    ->nav('MyApp', ['/dashboard' => 'Dashboard'])
    ->header('Overview', UI::button('+ New', '/create', 'primary'))
    ->add(
        UI::grid(3, [
            UI::stat('Users', 1240, '+5%'),
        ]),
    )
    ->response();
PHP;

    $sqlSample = <<<'SQL'
SELECT u.id, u.name, count(o.id) AS orders
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id
ORDER BY orders DESC;
SQL;

    return implode('', [
        UI::heading('Headings h1 – h4', 3),
        UI::heading('Heading level 1', 1),
        UI::heading('Heading level 2', 2),
        UI::heading('Heading level 3', 3),
        UI::heading('Heading level 4', 4),
        UI::divider(),
        UI::heading('Text', 3),
        UI::text('Normal body text. The Sofy monospace style keeps everything clean and technical.'),
        UI::text('Muted helper text — use for hints, descriptions, and secondary information.', true),
        UI::divider(),
        UI::heading('Lists', 3),
        UI::ul(['Unordered item one', 'Unordered item two', 'Unordered item three']),
        UI::ol(['First ordered step', 'Second ordered step', 'Third ordered step']),
        UI::divider(),
        UI::heading('Code blocks', 3),
        UI::code($phpSample, 'php'),
        UI::code($sqlSample, 'sql'),
        UI::code("$ php sofy make:controller UserController\n\$ php sofy route:list\n\$ php sofy cache:clear", 'bash'),
        UI::divider(),
        UI::heading('Raw HTML', 3),
        UI::text('UI::raw() injects trusted markup as-is:', true),
        UI::raw('<p style="color:var(--accent);font-size:12px">Injected via <code>UI::raw()</code> — use only with trusted content.</p>'),
    ]);
};

$statsTab = static function (): string {
    return implode('', [
        UI::heading('Stats — trend up / down / neutral / no trend', 3),
        UI::grid(4, [
            UI::stat('Total Users', '1,240', '+5%',  'all time'),
            UI::stat('Active',        '983', '+2%',  'this month'),
            UI::stat('Churn Rate',   '3.1%', '-0.4%'),
            UI::stat('Banned',         '12'),
        ]),
        UI::divider(),
        UI::heading('Grid — 1 column', 3),
        UI::grid(1, [UI::stat('Single full-width stat', '$48,200', '+12%', 'monthly revenue')]),
        UI::heading('Grid — 2 columns', 3),
        UI::grid(2, [
            UI::stat('Signups today', '34',     '+18%'),
            UI::stat('Avg session',  '4m 12s',  '+3%'),
        ]),
        UI::heading('Grid — 3 columns', 3),
        UI::grid(3, [
            UI::stat('Requests / min', '2,840'),
            UI::stat('Error rate',     '0.12%', '-0.03%'),
            UI::stat('P99 latency',    '142 ms', '-8ms'),
        ]),
        UI::heading('Grid — 4 columns', 3),
        UI::grid(4, [
            UI::stat('CPU',    '38%'),
            UI::stat('RAM',    '5.2 GB'),
            UI::stat('Disk',   '121 GB'),
            UI::stat('Uptime', '99.98%', '+0.01%'),
        ]),
    ]);
};

$formsTab = static function (): string {
    return implode('', [
        UI::heading('All field types', 3),
        UI::form('/users')
            ->cols(fn(UI\Form $f) => $f
                ->input('First Name', 'first_name', placeholder: 'Alice', required: true)
                ->input('Last Name',  'last_name',  placeholder: 'Smith', required: true)
            )
            ->email('Email', 'email', required: true)
            ->password('Password', 'password', required: true)
            ->number('Age', 'age', value: 25)
            ->date('Birthday', 'birthday')
            ->range('Satisfaction', 'satisfaction', min: 0, max: 10, value: 7)
            ->select('Role', 'role', ['admin' => 'Admin', 'editor' => 'Editor', 'user' => 'User'], 'user')
            ->radio('Plan', 'plan', ['free' => 'Free', 'pro' => 'Pro ($9/mo)', 'team' => 'Team ($29/mo)'], 'free')
            ->textarea('Bio', 'bio', rows: 3, placeholder: 'Tell us about yourself…')
            ->file('Avatar', 'avatar', accept: 'image/*')
            ->checkbox('Send welcome email', 'send_welcome', checked: true)
            ->toggle('Subscribe to newsletter', 'newsletter', checked: true)
            ->submit('Create User'),
        UI::divider(),
        UI::heading('Form with validation errors', 3),
        UI::form('/login')
            ->email('Email', 'email', value: 'bad-email', required: true)
            ->password('Password', 'password', required: true)
            ->withErrors([
                'email'    => 'Enter a valid email address.',
                'password' => 'Password must be at least 8 characters.',
            ])
            ->submit('Sign In'),
        UI::divider(),
        UI::heading('Form with hints', 3),
        UI::form('/profile', 'PUT')
            ->input('Username', 'username', value: 'alice', required: true, hint: 'Lowercase letters and numbers only.')
            ->password('New Password', 'password', hint: 'Leave blank to keep your current password.')
            ->select('Timezone', 'tz', [
                'UTC'           => 'UTC',
                'US/Eastern'    => 'US/Eastern (UTC−5)',
                'US/Pacific'    => 'US/Pacific (UTC−8)',
                'Europe/Moscow' => 'Moscow (UTC+3)',
            ], hint: 'Used for scheduling and notifications.')
            ->submit('Save Changes', 'ghost'),
    ]);
};

$cardsTab = static function (): string {
    return implode('', [
        UI::heading('Body only', 3),
        UI::card(content: UI::text('A card without a title — just a plain surface for any content.')),
        UI::heading('With title', 3),
        UI::card('Card Title', UI::text('Body content inside a titled card.')),
        UI::heading('With title + header action', 3),
        UI::card('Recent Activity',
            UI::text('The header can hold any component — typically a button.')
        )->action(UI::button('View all', '/activity', 'ghost', 'sm')),
        UI::heading('With footer', 3),
        UI::card('Invoice #1042',
            implode('', [
                UI::text('Due date: April 30, 2026'),
                UI::text('Amount: $1,200.00'),
            ])
        )->footer('Last updated 2 minutes ago'),
        UI::heading('Title + action + footer', 3),
        UI::card('Team Members',
            UI::table(
                ['Name', 'Role', 'Status'],
                [
                    ['name' => 'Alice', 'role' => 'Admin',  'status' => 'active'],
                    ['name' => 'Bob',   'role' => 'Editor', 'status' => 'active'],
                ],
                [
                    'name',
                    fn($r) => UI::badge($r['role'],   'accent'),
                    fn($r) => UI::badge($r['status'], 'success'),
                ]
            )
        )
        ->action(UI::button('+ Invite', '/team/invite', 'primary', 'sm'))
        ->footer('3 seats remaining on your plan.'),
    ]);
};

$navigationTab = static function (): string {
    return implode('', [
        UI::heading('Breadcrumb', 3),
        UI::breadcrumb(['Home' => '/', 'Users' => '/users', 'Alice Smith' => null]),
        UI::breadcrumb(['Dashboard' => '/dashboard', 'Settings' => '/settings', 'Profile' => '/settings/profile', 'Edit' => null]),
        UI::divider(),
        UI::heading('Steps', 3),
        UI::steps(['Account', 'Profile', 'Payment', 'Confirm']),
        UI::steps(['Account', 'Profile', 'Payment', 'Confirm'], current: 2),
        UI::steps(['Account', 'Profile', 'Payment', 'Confirm'], current: 3),
        UI::steps(['Account', 'Profile', 'Payment', 'Confirm'], current: 4),
        UI::divider(),
        UI::heading('Pagination', 3),
        UI::text('Page 1 of 10:', true),
        UI::pagination(1, 10),
        UI::text('Page 5 of 10:', true),
        UI::pagination(5, 10),
        UI::text('Page 10 of 10:', true),
        UI::pagination(10, 10),
    ]);
};

$dataTab = static function () use (&$usersTable, &$routesTable): string {
    return implode('', [
        UI::heading('Progress — variants', 3),
        UI::progress(72, 'Storage used', 'accent'),
        UI::progress(38, 'CPU',           'success'),
        UI::progress(81, 'Memory',        'warning'),
        UI::progress(95, 'Disk',          'danger'),
        UI::progress(55, 'Network',       'info'),
        UI::divider(),
        UI::heading('Progress — sizes', 3),
        UI::progress(60, 'Small',  'accent', 'sm'),
        UI::progress(60, 'Medium', 'accent'),
        UI::progress(60, 'Large',  'accent', 'lg'),
        UI::divider(),
        UI::heading('Key → Value', 3),
        UI::grid(2, [
            UI::card('Inline layout',
                UI::kv([
                    'Status'    => 'Active',
                    'Role'      => 'Admin',
                    'Joined'    => 'Jan 12, 2024',
                    'Last seen' => '2 hours ago',
                ])
            ),
            UI::card('Stacked layout',
                UI::kv([
                    'Environment' => 'Production',
                    'Region'      => 'eu-west-1',
                    'PHP version' => '8.3.4',
                    'Framework'   => 'Sofy 1.0',
                ], 'stacked')
            ),
        ]),
        UI::divider(),
        UI::heading('Timeline', 3),
        UI::timeline([
            ['title' => 'User registered',         'time' => 'Just now',   'variant' => 'success', 'content' => 'Account created via OAuth (GitHub).'],
            ['title' => 'Email verified',           'time' => '5 min ago',  'variant' => 'accent'],
            ['title' => 'First login',              'time' => '12 min ago', 'variant' => 'info',    'content' => 'IP: 192.168.1.42 — Chrome 124 / macOS'],
            ['title' => 'Password reset requested', 'time' => 'Yesterday',  'variant' => 'warning', 'content' => 'Email sent to alice@example.com.'],
            ['title' => 'Account suspended',        'time' => '3 days ago', 'variant' => 'danger',  'content' => 'Reason: multiple failed login attempts.'],
        ]),
        UI::divider(),
        UI::heading('Accordion — native <details>, no JS', 3),
        UI::accordion([
            ['title' => 'What is Sofy?',                  'open' => true,
             'content' => 'A minimal PHP 8.3 MVC framework — routing, DI, ORM, and a zero-dependency UI kit in one package.'],
            ['title' => 'Do I need to know HTML or CSS?',
             'content' => 'No. Every element is a PHP method call. The framework generates all markup and inlines the stylesheet.'],
            ['title' => 'How do I add my own styles?',
             'content' => 'Use ->css(\'...\') on the Page object to inject extra styles after the default sheet.'],
            ['title' => 'Is there a dark mode?',
             'content' => 'The default theme is dark (moon palette). Override CSS variables via ->css() for a light variant.'],
        ]),
        UI::divider(),
        UI::heading('Empty state', 3),
        UI::grid(2, [
            UI::card(content: UI::emptyState('No results found', 'Try adjusting your search or filters.')),
            UI::card(content: UI::emptyState(
                'No users yet',
                'Invite your teammates to get started.',
                UI::button('+ Invite User', '/users/invite', 'primary', 'sm'),
                '👥'
            )),
        ]),
        UI::divider(),
        UI::heading('Table — real-world example', 3),
        $usersTable(),
        UI::divider(),
        UI::heading('Table — HTTP routes', 3),
        $routesTable(),
    ]);
};

$miscTab = static function (): string {
    $activity = [
        ['Alice Smith',   'Uploaded 3 files — 2 min ago', 'success'],
        ['Bob Jones',     'Left a comment on PR #42',      'accent'],
        ['Charlie Brown', 'Merged branch feature/auth',    'info'],
        ['Diana Prince',  'Opened issue #87',              'warning'],
    ];

    $feed = implode('', array_map(
        fn($item) => '<div style="display:flex;align-items:flex-start;gap:12px">'
            . UI::avatar($item[0], $item[2], 'sm')
            . '<div>'
            . '<div style="font-size:12px;font-weight:600;color:var(--text)">' . htmlspecialchars($item[0]) . '</div>'
            . '<div style="font-size:11px;color:var(--muted)">' . htmlspecialchars($item[1]) . '</div>'
            . '</div></div>',
        $activity
    ));

    return implode('', [
        UI::heading('Avatars — color variants', 3),
        '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">',
        UI::avatar('Alice Smith',   'accent'),
        UI::avatar('Bob Jones',     'success'),
        UI::avatar('Charlie Brown', 'warning'),
        UI::avatar('Diana Prince',  'danger'),
        UI::avatar('Eve Torres',    'info'),
        UI::avatar('Frank Ocean',   'muted'),
        '</div>',
        UI::heading('Avatars — sizes', 3),
        '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">',
        UI::avatar('Alice Smith', 'accent', 'sm'),
        UI::avatar('Alice Smith', 'accent'),
        UI::avatar('Alice Smith', 'accent', 'lg'),
        UI::avatar('Alice Smith', 'accent', 'xl'),
        '</div>',
        UI::divider(),
        UI::heading('Spinners — sizes & variants', 3),
        '<div style="display:flex;gap:24px;align-items:center;margin-bottom:20px">',
        UI::spinner('sm'),
        UI::spinner(),
        UI::spinner('lg'),
        UI::spinner('md', 'muted'),
        '</div>',
        UI::divider(),
        UI::heading('Avatar activity feed', 3),
        UI::card(content: '<div style="display:flex;flex-direction:column;gap:14px">' . $feed . '</div>'),
        UI::divider(),
        UI::heading('Scroll Area', 3),
        UI::text('Vertical — fixed max-height with styled thin scrollbar:', true),
        UI::scrollArea(
            UI::timeline([
                ['title' => 'Deploy #148 succeeded',      'time' => '1 min ago',  'variant' => 'success', 'content' => 'Branch main → production. 42 files changed.'],
                ['title' => 'PR #91 merged',              'time' => '18 min ago', 'variant' => 'accent',  'content' => 'feat: add ScrollArea component'],
                ['title' => 'CI failed on branch fix/auth','time' => '34 min ago','variant' => 'danger',  'content' => 'Tests: 2 failed, 118 passed.'],
                ['title' => 'Scheduled backup completed', 'time' => '1 hr ago',   'variant' => 'info'],
                ['title' => 'Deploy #147 succeeded',      'time' => '2 hr ago',   'variant' => 'success'],
                ['title' => 'PR #88 opened',              'time' => '3 hr ago',   'variant' => 'accent',  'content' => 'refactor: extract UI components to separate files'],
                ['title' => 'Alert: disk at 91%',         'time' => '5 hr ago',   'variant' => 'warning', 'content' => 'Server eu-west-1a. Consider cleanup.'],
                ['title' => 'User #204 banned',           'time' => '6 hr ago',   'variant' => 'danger',  'content' => 'Reason: spam. Actioned by admin@example.com.'],
            ]),
            '220px'
        ),
        UI::text('Horizontal — wide table inside a scroll container:', true),
        UI::scrollArea(
            UI::table(
                ['#', 'Service', 'Status', 'Latency', 'Uptime', 'Region', 'Last check', 'Incidents'],
                [
                    ['1', 'API Gateway',   'ok',      '12 ms',  '99.98%', 'eu-west-1',   '10s ago', '0'],
                    ['2', 'Auth Service',  'ok',      '8 ms',   '99.99%', 'us-east-1',   '10s ago', '0'],
                    ['3', 'Database',      'degraded','142 ms', '99.81%', 'eu-west-1',   '10s ago', '2'],
                    ['4', 'File Storage',  'ok',      '24 ms',  '100%',   'us-west-2',   '10s ago', '0'],
                    ['5', 'Email Worker',  'down',    '—',      '98.40%', 'eu-central-1','10s ago', '5'],
                ],
                [
                    '0',
                    '1',
                    fn($r) => UI::badge($r[2], match($r[2]) { 'ok' => 'success', 'degraded' => 'warning', default => 'danger' }),
                    '3', '4', '5', '6', '7',
                ]
            ),
            direction: 'horizontal'
        ),
    ]);
};

// ── Users table ───────────────────────────────────────────────────────────────

$usersTable = static function (): UI\Card {
    $users = [
        ['id' => 1, 'name' => 'Alice',   'email' => 'alice@example.com',   'role' => 'admin',  'status' => 'active'],
        ['id' => 2, 'name' => 'Bob',     'email' => 'bob@example.com',     'role' => 'user',   'status' => 'active'],
        ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'role' => 'user',   'status' => 'banned'],
        ['id' => 4, 'name' => 'Diana',   'email' => 'diana@example.com',   'role' => 'editor', 'status' => 'active'],
        ['id' => 5, 'name' => 'Eve',     'email' => 'eve@example.com',     'role' => 'user',   'status' => 'banned'],
    ];

    return UI::card('Users Table',
        UI::table(
            ['ID', 'Name', 'Email', 'Role', 'Status', 'Actions'],
            $users,
            [
                'id',
                fn($r) => '<div style="display:flex;align-items:center;gap:8px">'
                    . UI::avatar($r['name'], $r['status'] === 'active' ? 'success' : 'danger', 'sm')
                    . htmlspecialchars($r['name'])
                    . '</div>',
                'email',
                fn($r) => UI::badge($r['role'], match($r['role']) {
                    'admin'  => 'danger',
                    'editor' => 'warning',
                    default  => 'default',
                }),
                fn($r) => UI::badge($r['status'], $r['status'] === 'active' ? 'success' : 'danger'),
                fn($r) => '<div class="sofy-tbl-actions">'
                    . UI::button('Edit', "/users/{$r['id']}/edit", 'ghost', 'sm')
                    . UI::deleteButton("/users/{$r['id']}")
                    . '</div>',
            ]
        )
    )->action(UI::button('+ New User', '/users/create', 'primary', 'sm'));
};

$routesTable = static function (): UI\Card {
    return UI::card(content:
        UI::table(
            ['Method', 'URI', 'Name', 'Middleware'],
            [
                ['method' => 'GET',    'uri' => '/',           'name' => 'home',          'middleware' => '—'],
                ['method' => 'GET',    'uri' => '/users',      'name' => 'users.index',   'middleware' => 'auth'],
                ['method' => 'POST',   'uri' => '/users',      'name' => 'users.store',   'middleware' => 'auth'],
                ['method' => 'DELETE', 'uri' => '/users/{id}', 'name' => 'users.destroy', 'middleware' => 'auth'],
            ],
            [
                fn($r) => UI::badge($r['method'], match($r['method']) {
                    'GET'         => 'info',
                    'POST'        => 'success',
                    'PUT','PATCH' => 'warning',
                    'DELETE'      => 'danger',
                    default       => 'default',
                }),
                'uri', 'name', 'middleware',
            ]
        )
    );
};

// ── Overlays tab (Modal, Toast, Drawer, Tooltip) ──────────────────────────────

$overlaysTab = static function (): string {
    $modalContent = implode('', [
        UI::text('This is the modal body. You can put any component here.'),
        UI::alert('Everything inside is just PHP components.', 'info'),
        UI::kv(['Type' => 'Dialog element', 'JS' => 'showModal() API', 'Fallback' => 'CSS animation']),
    ]);
    $modalFooter = implode('', [
        UI::button('Cancel', '#', 'ghost'),
        UI::button('Confirm', '#', 'primary'),
    ]);
    $lgContent = UI::text('A large modal with more space for complex forms, tables, or rich content.');

    return implode('', [
        UI::heading('Modal (native <dialog>)', 3),
        UI::text('Triggered by a button, closed by ✕ or the Cancel button. No JS library needed.', true),
        '<div class="sofy-btn-group">',
        \Sofy\View\UI\Modal::trigger('demo-modal-md', 'Open Modal', 'primary'),
        \Sofy\View\UI\Modal::trigger('demo-modal-lg', 'Open Large Modal', 'ghost'),
        \Sofy\View\UI\Modal::trigger('demo-modal-sm', 'Open Small', 'ghost', 'sm'),
        '</div>',
        UI::modal('demo-modal-md', 'Confirm Action',   $modalContent, $modalFooter),
        UI::modal('demo-modal-lg', 'Large Modal',      $lgContent,    $modalFooter, 'lg'),
        UI::modal('demo-modal-sm', 'Delete item?',     UI::text('This cannot be undone.'), $modalFooter, 'sm'),

        UI::divider(),
        UI::heading('Toast Notifications', 3),
        UI::text('Rendered server-side, JS auto-dismisses. The four variants below are live:', true),
        UI::toast('File uploaded successfully!',          'success', 'Done',    5),
        UI::toast('Your session expires in 5 minutes.',  'warning', 'Warning', 8),
        UI::toast('Failed to connect to the server.',    'danger',  'Error',   0),
        UI::toast('Maintenance scheduled for Sunday.',   'info',    null,      6),

        UI::divider(),
        UI::heading('Drawer (slide-in panel)', 3),
        UI::text('Slides in from the right or left. Closed by backdrop click, ✕, or Escape.', true),
        '<div class="sofy-btn-group">',
        \Sofy\View\UI\Drawer::trigger('demo-drawer-r', 'Open Right Drawer', 'ghost'),
        \Sofy\View\UI\Drawer::trigger('demo-drawer-l', 'Open Left Drawer',  'ghost'),
        '</div>',
        UI::drawer('demo-drawer-r', 'Filters', implode('', [
            UI::form('#')->select('Status', 'status', ['all' => 'All', 'active' => 'Active', 'banned' => 'Banned'])->submit('Apply filters'),
        ]), implode('', [UI::button('Reset', '#', 'ghost', 'sm'), UI::button('Apply', '#', 'primary', 'sm')]),
            'right', '340px'),
        UI::drawer('demo-drawer-l', 'Navigation', implode('', [
            UI::kv(['Dashboard' => '/', 'Users' => '/users', 'Settings' => '/settings'], 'stacked'),
        ]), null, 'left', '260px'),

        UI::divider(),
        UI::heading('Tooltip (CSS-only)', 3),
        UI::text('Hover the elements below. No JavaScript, no libraries.', true),
        '<div class="sofy-btn-group" style="flex-wrap:wrap;gap:12px">',
        UI::tooltip(UI::button('Top',    '#', 'ghost', 'sm'), 'I appear above',  'top'),
        UI::tooltip(UI::button('Bottom', '#', 'ghost', 'sm'), 'I appear below',  'bottom'),
        UI::tooltip(UI::button('Right',  '#', 'ghost', 'sm'), 'I appear right',  'right'),
        UI::tooltip(UI::button('Left',   '#', 'ghost', 'sm'), 'I appear left',   'left'),
        UI::tooltip(UI::badge('active', 'success'), 'Last active 2 minutes ago'),
        UI::tooltip(UI::badge('1,240 users', 'accent'), 'Registered this month'),
        '</div>',
    ]);
};

// ── Charts tab ────────────────────────────────────────────────────────────────

$chartsTab = static function (): string {
    $monthly  = ['Jan' => 120, 'Feb' => 200, 'Mar' => 165, 'Apr' => 240, 'May' => 190, 'Jun' => 280];
    $weekly   = ['Mon' => 42,  'Tue' => 78,  'Wed' => 65,  'Thu' => 90,  'Fri' => 55,  'Sat' => 30, 'Sun' => 18];
    $traffic  = ['Direct' => 40, 'Organic' => 30, 'Social' => 20, 'Email' => 10];

    return implode('', [
        UI::heading('Bar Chart', 3),
        UI::card('Monthly signups', UI::chart($monthly, 'bar', 200)),

        UI::heading('Line Chart', 3),
        UI::card('Weekly active users', UI::chart($weekly, 'line', 200)),

        UI::grid(2, [
            UI::card('Traffic sources — Pie',   UI::chart($traffic, 'pie',   180)),
            UI::card('Traffic sources — Donut', UI::chart($traffic, 'donut', 180, 'Traffic')),
        ]),

        UI::heading('Custom colors', 3),
        UI::card('Revenue by product', UI::chart(
            ['Product A' => 85000, 'Product B' => 62000, 'Product C' => 43000, 'Product D' => 29000],
            'bar', 180, null,
            ['#7c9bbf', '#5a9e6f', '#c09848', '#c07848'],
        )),
    ]);
};

// ── DataTable tab ─────────────────────────────────────────────────────────────

$dataTableTab = static function (): string {
    $users = [
        ['id' => 1, 'name' => 'Alice Johnson',  'email' => 'alice@example.com',  'role' => 'Admin',  'status' => 'active'],
        ['id' => 2, 'name' => 'Bob Smith',       'email' => 'bob@example.com',    'role' => 'Editor', 'status' => 'active'],
        ['id' => 3, 'name' => 'Carol Williams',  'email' => 'carol@example.com',  'role' => 'Viewer', 'status' => 'inactive'],
        ['id' => 4, 'name' => 'Dave Brown',      'email' => 'dave@example.com',   'role' => 'Admin',  'status' => 'active'],
        ['id' => 5, 'name' => 'Eve Davis',       'email' => 'eve@example.com',    'role' => 'Editor', 'status' => 'banned'],
        ['id' => 6, 'name' => 'Frank Miller',    'email' => 'frank@example.com',  'role' => 'Viewer', 'status' => 'active'],
        ['id' => 7, 'name' => 'Grace Wilson',    'email' => 'grace@example.com',  'role' => 'Editor', 'status' => 'active'],
        ['id' => 8, 'name' => 'Henry Moore',     'email' => 'henry@example.com',  'role' => 'Admin',  'status' => 'inactive'],
        ['id' => 9, 'name' => 'Iris Taylor',     'email' => 'iris@example.com',   'role' => 'Viewer', 'status' => 'active'],
        ['id' => 10,'name' => 'Jack Anderson',   'email' => 'jack@example.com',   'role' => 'Editor', 'status' => 'active'],
        ['id' => 11,'name' => 'Karen Thomas',    'email' => 'karen@example.com',  'role' => 'Viewer', 'status' => 'banned'],
        ['id' => 12,'name' => 'Leo Jackson',     'email' => 'leo@example.com',    'role' => 'Admin',  'status' => 'active'],
    ];

    return implode('', [
        UI::heading('DataTable — search, sort, paginate', 3),
        UI::text('All operations are client-side — no page reloads. Click column headers to sort.', true),
        UI::dataTable(
            ['ID', 'Name', 'Email', 'Role', 'Status', ''],
            $users,
            [
                'id', 'name', 'email', 'role',
                fn($r) => UI::badge($r['status'], match($r['status']) {
                    'active'   => 'success',
                    'inactive' => 'default',
                    'banned'   => 'danger',
                    default    => 'default',
                }),
                fn($r) => UI::button('Edit', "/users/{$r['id']}/edit", 'ghost', 'sm'),
            ],
            perPage: 5,
            nosort: [5],
        ),
    ]);
};

// ── Layouts tab ───────────────────────────────────────────────────────────────

$layoutsTab = static function (): string {
    $sidebar = implode('', [
        UI::card('Navigation', UI::ul([
            'Dashboard', 'Users', 'Analytics', 'Settings', 'Help',
        ])),
        UI::card('Quick stats', UI::grid(1, [
            UI::stat('Online', 83,  '+4'),
            UI::stat('Pending', 7,  '-2'),
        ])),
    ]);

    $main = implode('', [
        UI::card('Main content area',
            UI::text('This is the main content panel. The sidebar is sticky and scrolls with the page. On mobile it collapses to full width above the content.'),
        ),
        UI::card('Recent activity',
            UI::timeline([
                ['title' => 'Deploy succeeded',    'time' => '2 min ago',  'variant' => 'success'],
                ['title' => 'PR #142 merged',      'time' => '18 min ago', 'variant' => 'accent'],
                ['title' => 'Build started',       'time' => '22 min ago', 'variant' => 'info'],
                ['title' => 'Tests passed (98%)',  'time' => '25 min ago', 'variant' => 'success'],
            ])
        ),
    ]);

    return implode('', [
        UI::heading('SidebarLayout', 3),
        UI::text('Two-column layout. Sidebar is sticky. Collapses to single column on mobile.', true),
        UI::sidebarLayout($sidebar, $main, '220px'),

        UI::divider(),
        UI::heading('Right sidebar', 3),
        UI::sidebarLayout(
            UI::card('Detail panel', UI::kv(['Version' => '2.4.1', 'Status' => 'Stable', 'License' => 'MIT'])),
            UI::card('Main area', UI::text('Sidebar position: right.')),
            '200px', 'right',
        ),
    ]);
};

// ── Audit Log tab ────────────────────────────────────────────────────────────

$auditTab = static function (): string {
    $fakeTrail = [
        ['event' => 'created', 'model_type' => 'App\Models\User', 'model_id' => '42', 'user_id' => 1,
         'old_values' => [], 'new_values' => ['name' => 'Alice', 'email' => 'alice@example.com'], 'created_at' => '2026-04-15 09:00:00'],
        ['event' => 'updated', 'model_type' => 'App\Models\User', 'model_id' => '42', 'user_id' => 1,
         'old_values' => ['name' => 'Alice'], 'new_values' => ['name' => 'Alice Smith'], 'created_at' => '2026-04-15 10:30:00'],
        ['event' => 'updated', 'model_type' => 'App\Models\User', 'model_id' => '42', 'user_id' => 2,
         'old_values' => ['email' => 'alice@example.com'], 'new_values' => ['email' => 'alice@corp.com'], 'created_at' => '2026-04-15 14:00:00'],
        ['event' => 'deleted', 'model_type' => 'App\Models\Post', 'model_id' => '7', 'user_id' => 1,
         'old_values' => ['title' => 'Draft post', 'status' => 'draft'], 'new_values' => [], 'created_at' => '2026-04-15 15:20:00'],
    ];

    $rows = array_map(static function (array $e): array {
        $badge = match ($e['event']) {
            'created' => UI::badge('created', 'success'),
            'updated' => UI::badge('updated', 'info'),
            'deleted' => UI::badge('deleted', 'danger'),
            default   => UI::badge($e['event']),
        };
        $old = $e['old_values'] ? implode(', ', array_map(
            fn($k, $v) => "$k: $v", array_keys($e['old_values']), $e['old_values']
        )) : '—';
        $new = $e['new_values'] ? implode(', ', array_map(
            fn($k, $v) => "$k: $v", array_keys($e['new_values']), $e['new_values']
        )) : '—';
        return ['event' => $badge, 'model' => basename(str_replace('\\', '/', $e['model_type'])) . ' #' . $e['model_id'],
                'old' => $old, 'new' => $new, 'user' => '#' . $e['user_id'], 'at' => $e['created_at']];
    }, $fakeTrail);

    return implode('', [
        UI::heading('Audit Log', 3),
        UI::alert('Add the Auditable trait to any model to automatically track every create, update, and delete.', 'info'),

        UI::heading('Example audit trail (simulated)', 4),
        UI::table(['Event', 'Model', 'Before', 'After', 'By', 'When'], $rows,
            ['event', 'model', 'old', 'new', 'user', 'at']),

        UI::divider(),
        UI::heading('Enable on a model', 4),
        UI::code(<<<'PHP'
use Sofy\Audit\Auditable;

class User extends Model
{
    use Auditable;

    // Exclude sensitive fields (optional)
    public array $auditExclude = ['password', 'remember_token'];
}
PHP, 'php'),

        UI::divider(),
        UI::heading('Query audit history', 4),
        UI::code(<<<'PHP'
use Sofy\Audit\AuditLog;

// All changes to User models
$entries = AuditLog::for(User::class);

// Changes to a specific record
$entries = AuditLog::for($user);       // same as AuditLog::for(User::class, $user->id)

// Most recent 20 changes across all models
$entries = AuditLog::recent(20);

// Changes made by the current user
$entries = AuditLog::forUser(Auth::id());

// Check if a field changed after loading
if ($user->wasChanged('email')) {
    Mail::to($user)->send(new EmailChangedMail($user));
}
PHP, 'php'),

        UI::divider(),
        UI::heading('Required migration', 4),
        UI::code(<<<'SQL'
CREATE TABLE audit_logs (
    id         INTEGER PRIMARY KEY AUTO_INCREMENT,
    model_type VARCHAR(255) NOT NULL,
    model_id   VARCHAR(64)  NOT NULL,
    event      VARCHAR(32)  NOT NULL,
    old_values TEXT,
    new_values TEXT,
    user_id    INTEGER      DEFAULT NULL,
    created_at DATETIME     NOT NULL,
    INDEX idx_model (model_type, model_id),
    INDEX idx_user  (user_id)
);
SQL, 'sql'),
    ]);
};

// ── HTMX tab ─────────────────────────────────────────────────────────────────

$htmxTab = static function (): string {
    return implode('', [
        UI::heading('HTMX Partial Rendering', 3),
        UI::alert('Add hx-* attributes to any component via ->hx(). Enable HTMX on the page with ->withHtmx().', 'info'),

        UI::heading('Enable HTMX on a page', 4),
        UI::code(<<<'PHP'
UI::page('Users')
    ->withHtmx()   // loads HTMX CDN in <head>
    ->nav('App', ['/users' => 'Users'])
    ->add(
        // Button triggers an HTMX GET request
        UI::button('Refresh list', '/users', 'ghost')
            ->hx('hx-get', '/users')
            ->hx('hx-target', '#user-list')
            ->hx('hx-swap', 'innerHTML'),

        UI::raw('<div id="user-list">'),
        UI::table(['ID', 'Name', 'Email'], $users, ['id', 'name', 'email']),
        UI::raw('</div>'),
    )
    ->response();
PHP, 'php'),

        UI::divider(),
        UI::heading('Return a partial from the controller', 4),
        UI::code(<<<'PHP'
use Sofy\Http\Htmx;

public function list(Request $request): Response
{
    $users = User::all();

    // HTMX sends HX-Request: true header
    if (Htmx::isRequest()) {
        return Htmx::partial(
            UI::table(['ID', 'Name', 'Email'], $users, ['id', 'name', 'email'])
        );
    }

    // Full page for normal browser requests
    return UI::page('Users')
        ->withHtmx()
        ->add(UI::table(['ID', 'Name', 'Email'], $users, ['id', 'name', 'email']))
        ->response();
}
PHP, 'php'),

        UI::divider(),
        UI::heading('HTMX response helpers', 4),
        UI::code(<<<'PHP'
// Redirect the browser after a form submit
Htmx::redirect('/dashboard');

// Trigger a full page refresh
Htmx::refresh();

// Trigger a client-side event
Htmx::triggerEvent('user:saved', ['id' => 42]);

// Override the swap target from server
Htmx::retarget('#flash-messages');

// Push a URL into browser history
Htmx::pushUrl('/users?page=2');
PHP, 'php'),

        UI::divider(),
        UI::heading('hx() works on any component', 4),
        UI::code(<<<'PHP'
// Button
UI::button('Delete', '#', 'danger', 'sm')
    ->hx('hx-delete', "/users/$id")
    ->hx('hx-target', "#row-$id")
    ->hx('hx-swap', 'outerHTML')
    ->hx('hx-confirm', 'Are you sure?');

// Multiple attrs at once
UI::button('Load more', '#')
    ->hxAttrs([
        'hx-get'      => '/users?page=2',
        'hx-target'   => '#user-list',
        'hx-swap'     => 'beforeend',
        'hx-indicator' => '#spinner',
    ]);
PHP, 'php'),
    ]);
};

// ── Feature Flags tab ────────────────────────────────────────────────────────

// Define some demo flags
Flag::define('new-dashboard',  true);
Flag::define('beta-charts',    Flag::percent(30));
Flag::define('admin-tools',    Flag::users([1, 2, 5]));
Flag::define('maintenance',    Flag::env('production'));
Flag::define('power-feature',  Flag::all(Flag::percent(50), Flag::users([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])));

$demoUser = (object) ['id' => 1, 'name' => 'Alice'];

$flagsTab = static function () use ($demoUser): string {
    $flags = [
        ['name' => 'new-dashboard',  'label' => 'New Dashboard',  'desc' => 'Static flag — always on'],
        ['name' => 'beta-charts',    'label' => 'Beta Charts',     'desc' => '30% percent rollout'],
        ['name' => 'admin-tools',    'label' => 'Admin Tools',     'desc' => 'Whitelist: users [1, 2, 5]'],
        ['name' => 'maintenance',    'label' => 'Maintenance',     'desc' => 'Env-based: production only'],
        ['name' => 'power-feature',  'label' => 'Power Feature',   'desc' => 'Combined: percent(50) AND users list'],
        ['name' => 'undefined-flag', 'label' => 'Undefined Flag',  'desc' => 'Not defined → always false'],
    ];

    $rows = array_map(static function (array $f) use ($demoUser): array {
        $global  = Flag::enabled($f['name']);
        $forUser = Flag::for($demoUser)->enabled($f['name']);
        return [
            'flag'    => $f['label'],
            'desc'    => $f['desc'],
            'global'  => $global  ? UI::badge('enabled', 'success') : UI::badge('disabled', 'default'),
            'forUser' => $forUser ? UI::badge('enabled', 'success') : UI::badge('disabled', 'default'),
        ];
    }, $flags);

    return implode('', [
        UI::heading('Feature Flags', 3),
        UI::alert('Flags let you enable/disable features per user, percentage, or environment — without redeployment.', 'info'),

        UI::table(
            ['Flag', 'Strategy', 'Global', 'For user #1'],
            $rows,
            ['flag', 'desc', 'global', 'forUser'],
        ),

        UI::divider(),
        UI::heading('Usage', 3),
        UI::code(<<<'PHP'
// Define flags at bootstrap
Flag::define('new-dashboard', true);
Flag::define('beta-charts',   Flag::percent(30));   // 30% rollout
Flag::define('admin-tools',   Flag::users([1, 2])); // whitelist
Flag::define('maintenance',   Flag::env('production'));

// Check anywhere
if (Flag::enabled('new-dashboard')) { ... }

// Per-user check
if (Flag::for($user)->enabled('beta-charts')) { ... }

// Combine conditions (AND)
Flag::define('power', Flag::all(Flag::percent(50), Flag::users([1])));

// Generate a flag class
// php sofy make:flag NewDashboard
PHP, 'php'),
    ]);
};

// ── Tags & More tab ───────────────────────────────────────────────────────────

$tagsTab = static function (): string {
    return implode('', [
        UI::heading('Tags / chips', 3),
        UI::text('Inline labels — optionally linked or removable.', true),
        '<div class="sofy-tags" style="margin-bottom:20px">',
        UI::tag('default'),
        UI::tag('success', 'success'),
        UI::tag('warning', 'warning'),
        UI::tag('danger',  'danger'),
        UI::tag('info',    'info'),
        UI::tag('accent',  'accent'),
        '</div>',
        UI::heading('Linked tag', 4),
        '<div class="sofy-tags" style="margin-bottom:20px">',
        UI::tag('PHP',        'accent',  '#'),
        UI::tag('Framework',  'info',    '#'),
        UI::tag('Open Source','success', '#'),
        '</div>',
        UI::heading('Removable tags', 4),
        '<div class="sofy-tags" style="margin-bottom:20px">',
        UI::tag('Remove me', 'accent',  null, true),
        UI::tag('PHP 8.3',   'info',    null, true),
        UI::tag('Sofy',      'success', null, true),
        '</div>',
        UI::code('UI::tag(\'label\', \'accent\')
UI::tag(\'link\', \'info\', href: \'/url\')
UI::tag(\'removable\', \'danger\', removable: true)
UI::tags([UI::tag(\'a\'), UI::tag(\'b\'), UI::tag(\'c\')])', 'php'),

        UI::divider(),
        UI::heading('Banners', 3),
        UI::text('Full-width announcement bars — dismissible or persistent.', true),
        UI::banner('System maintenance scheduled for Sunday 02:00–04:00 UTC.', 'warning'),
        UI::banner('Version 2.0 is out! Check the changelog.', 'success',
            UI::button('View changelog', '#', 'success', 'sm'), true),
        UI::banner('Two-factor authentication is now required for admin accounts.', 'danger',
            null, true),
        UI::banner('New components available: Tag, Banner, CopyButton.', 'info',
            UI::button('See demo', '#', 'ghost', 'sm')),
        UI::code('UI::banner(\'Message\', \'info\')
UI::banner(\'Dismissible\', \'warning\', dismissible: true)
UI::banner(\'With action\', \'success\', action: UI::button(\'Learn more\', \'#\', \'ghost\', \'sm\'))', 'php'),

        UI::divider(),
        UI::heading('Copy Button', 3),
        UI::text('Copies arbitrary text to the clipboard — useful next to code snippets.', true),
        '<div class="sofy-btn-group" style="margin-bottom:16px">',
        UI::copyButton('php sofy make:controller UserController', 'Copy command'),
        UI::copyButton('composer require sofy/framework', 'Copy', 'Copied!', 'md'),
        '</div>',
        UI::card('Typical usage — next to a code block',
            implode('', [
                '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">',
                UI::text('Install command', true),
                UI::copyButton('composer create-project sofy/sofy my-app'),
                '</div>',
                UI::code('composer create-project sofy/sofy my-app', 'bash'),
            ])
        ),
        UI::code('UI::copyButton(\'text to copy\')
UI::copyButton(\'text\', label: \'Copy\', copiedLabel: \'Copied!\', size: \'sm\')', 'php'),

        UI::divider(),
        UI::heading('Range slider', 3),
        UI::text('Built into the form builder — renders a styled range input with live value display.', true),
        UI::form('#')
            ->range('Volume', 'volume', min: 0, max: 100, value: 65)
            ->range('Threshold', 'threshold', min: 0, max: 10, value: 3)
            ->range('Priority', 'priority', min: 1, max: 5, value: 2),
        UI::code('UI::form(\'/save\')
    ->range(\'Volume\', \'volume\', min: 0, max: 100, value: 65)
    ->range(\'Priority\', \'priority\', min: 1, max: 5, value: 2)
    ->submit(\'Save\')', 'php'),
    ]);
};

$animationsTab = static function (): string {
    $modal = UI::modal(
        'anim-modal',
        'Smooth modal',
        UI::text('Scales in over 250ms and dips out over 150ms via the .t-modal transition. Click the backdrop or press Esc — both animate the close.')
    );
    $drawer = UI::drawer(
        'anim-drawer',
        'Sliding drawer',
        UI::text('The panel slides in with a cross-blur using the panel-reveal tokens.')
    );
    $avatars = UI::avatarGroup([
        UI::avatar('Alice Smith'),
        UI::avatar('Bob Jones',  'success'),
        UI::avatar('Carol Lee',  'warning'),
        UI::avatar('Dan Poe',    'info'),
        UI::avatar('Eve Ray',    'danger'),
    ]);
    $errorForm = UI::form('#', 'POST')
        ->email('Email', 'demo_email', 'not-an-email')
        ->withErrors(['demo_email' => 'Please enter a valid email address.'])
        ->submit('Submit');

    return implode('', [
        UI::heading('Overlays — Modal & Drawer', 3),
        UI::text('Modal scales up from center; the drawer slides in with a cross-blur. Both read from the transitions.dev tokens.', true),
        '<div class="sofy-btn-group">',
        \Sofy\View\UI\Modal::trigger('anim-modal', 'Open modal', 'primary'),
        \Sofy\View\UI\Drawer::trigger('anim-drawer', 'Open drawer', 'ghost'),
        '</div>',
        (string) $modal,
        (string) $drawer,

        UI::divider(),
        UI::heading('Panel reveal', 3),
        <<<'HTML'
<button class="sofy-btn sofy-btn-ghost sofy-btn-sm" onclick="var p=document.getElementById('anim-panel');p.dataset.open=(p.dataset.open==='true'?'false':'true')">Toggle panel</button>
<div style="overflow:hidden;margin-top:12px">
  <div id="anim-panel" class="t-panel-slide" data-open="false" style="--panel-translate-y:40px">
    <div class="sofy-card" style="margin:0"><div class="sofy-card-body">Revealed panel — slides up, fades and un-blurs on the same curve.</div></div>
  </div>
</div>
HTML,

        UI::divider(),
        UI::heading('Icon swap & text swap', 3),
        <<<'HTML'
<div class="sofy-btn-group">
  <button class="sofy-btn sofy-btn-ghost" onclick="var s=this.querySelector('.t-icon-swap');s.dataset.state=(s.dataset.state==='a'?'b':'a')">
    <span class="t-icon-swap" data-state="a"><span class="t-icon" data-icon="a">&#9654;</span><span class="t-icon" data-icon="b">&#10074;&#10074;</span></span>
    <span style="margin-left:8px">Play / Pause</span>
  </button>
  <button class="sofy-btn sofy-btn-ghost" onclick="var t=this.querySelector('.t-text-swap');sofySwapText(t,t.textContent==='Save'?'Saved ✓':'Save')"><span class="t-text-swap">Save</span></button>
</div>
HTML,

        UI::divider(),
        UI::heading('Number pop-in', 3),
        <<<'HTML'
<div style="font-size:30px;font-weight:700;color:var(--bright);margin-bottom:12px"><span id="anim-digits" class="t-digit-group is-animating"><span class="t-digit">1</span><span class="t-digit">2</span><span class="t-digit" data-stagger="1">.</span><span class="t-digit" data-stagger="2">3</span></span></div>
<button class="sofy-btn sofy-btn-ghost sofy-btn-sm" onclick="sofyDigits(document.getElementById('anim-digits'),(Math.random()*9999).toFixed(2))">Update number</button>
HTML,

        UI::divider(),
        UI::heading('Success check', 3),
        '<div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">',
        (string) UI::successCheck(true, 'anim-check'),
        '<button class="sofy-btn sofy-btn-ghost sofy-btn-sm" onclick="sofyShowCheck(document.getElementById(\'anim-check\'))">Replay</button>',
        '</div>',

        UI::divider(),
        UI::heading('Avatar group hover', 3),
        UI::text('Hover any avatar — neighbours lift with a power-falloff, then spring back on leave.', true),
        (string) $avatars,

        UI::divider(),
        UI::heading('Error-state shake', 3),
        UI::text('A field rendered with a validation error shakes on load, then clears as you type.', true),
        '<div style="max-width:360px">',
        (string) $errorForm,
        '</div>',
    ]);
};

// ── What's new (0.6–0.10) ─────────────────────────────────────────────────────

$whatsNewTab = static function (): string {
    $products = ['1' => 'SKU-001 — Wireless Router', '2' => 'SKU-002 — Ethernet Cable', '3' => 'SKU-003 — Network Switch'];

    $messages = [
        ['id' => 1, 'user_id' => 1, 'name' => 'Алиса', 'body' => 'Привет! Глянь новый компонент чата.', 'time' => '12:00', 'mine' => false],
        ['id' => 2, 'user_id' => 9, 'name' => 'Вы',    'body' => 'Огонь — и со звуком уведомления!',     'time' => '12:01', 'mine' => true],
    ];

    return implode('', [
        UI::heading('Цвет компонента — ->color()', 3),
        UI::grid(1, [UI::raw('<div class="sofy-btn-group">'
            . UI::button('Brand',  '#')->color('#7c5cff')
            . UI::button('Mint',   '#')->color('#10b981')
            . UI::button('Crimson','#')->color('crimson')
            . UI::button('Ghost',  '#', 'ghost')->color('#7c5cff')
            . '</div>')]),
        UI::raw('<div style="margin:10px 0">'
            . UI::badge('VIP')->color('#7c5cff')
            . ' ' . UI::badge('NEW')->color('#10b981')
            . ' ' . UI::tag('php')->color('#0a7')
            . ' ' . UI::tag('websocket')->color('#3b82f6') . '</div>'),
        UI::progress(72, 'Загрузка')->color('#7c5cff'),
        UI::alert('Любому компоненту можно задать цвет: hex, rgb(), var(--x) или имя. Значение санитизируется.', 'info')->color('#7c5cff'),

        UI::divider(),
        UI::heading('Searchable select — UI::combobox', 3),
        UI::form('#', 'POST')->combobox('Товар', 'product_id', $products, placeholder: 'Начните печатать…'),

        UI::divider(),
        UI::heading('Чат — UI::chat + UI::chatList', 3),
        UI::sidebarLayout(
            UI::card('Диалоги', UI::chatList([
                ['title' => 'Алиса', 'preview' => 'Привет! Глянь…', 'unread' => 2, 'href' => '#', 'active' => true],
                ['title' => 'Команда', 'preview' => 'Готово ✓', 'unread' => 0, 'href' => '#'],
            ])),
            UI::chat($messages, sendUrl: '#', pollUrl: '#', currentUserId: 9),
            width: '240px',
        ),

        UI::divider(),
        UI::heading('Браузерные уведомления со звуком', 3),
        UI::alert('Звук синтезируется через WebAudio — без аудио-файлов. Нажми кнопку:', 'info'),
        UI::raw('<button class="sofy-btn sofy-btn-primary" onclick="sofyNotify.show({title:\'Sofy\',body:\'Тестовое уведомление со звуком\'})">🔔 Уведомление + звук</button>'),
    ]);
};

// ── Page ──────────────────────────────────────────────────────────────────────

echo UI::page(__('ui.demo.title'))
    ->nav('Sofy', ['/' => __('ui.nav.home'), '/ui-demo' => __('ui.nav.ui'), '/docs' => __('ui.nav.docs')])
    ->themeToggle()
    ->localeSwitcher()
    ->header(__('ui.demo.title'),
        UI::button('GitHub', 'https://github.com/sofyphp/framework', 'ghost', 'sm')
    )
    ->add(
        UI::alert(__('ui.demo.subtitle'), 'info'),

        UI::hero(__('ui.demo.hero_title'), __('ui.demo.hero_sub'))
            ->action(
                UI::button(__('ui.demo.btn_get_started'), '/docs', 'primary', 'lg'),
                UI::button('GitHub', 'https://github.com/sofyphp/framework', 'ghost', 'lg'),
            ),

        UI::grid(4, [
            UI::stat(__('ui.demo.stat_comps'), '46+',  null, __('ui.demo.stat_comps_desc')),
            UI::stat(__('ui.demo.stat_forms'), '12',   null, __('ui.demo.stat_forms_desc')),
            UI::stat(__('ui.demo.stat_deps'),  '0',    null, __('ui.demo.stat_deps_desc')),
            UI::stat(__('ui.demo.stat_php'),   '8.3+', null, __('ui.demo.stat_php_desc')),
        ]),

        UI::tabs([
            '✨ Новое'                   => $whatsNewTab(),
            __('ui.demo.tab_buttons')   => $buttonsTab(),
            __('ui.demo.tab_badges')    => $badgesTab(),
            __('ui.demo.tab_tags')      => $tagsTab(),
            __('ui.demo.tab_content')   => $contentTab(),
            __('ui.demo.tab_stats')     => $statsTab(),
            __('ui.demo.tab_forms')     => $formsTab(),
            __('ui.demo.tab_cards')     => $cardsTab(),
            __('ui.demo.tab_nav')       => $navigationTab(),
            __('ui.demo.tab_data')      => $dataTab(),
            __('ui.demo.tab_overlays')  => $overlaysTab(),
            __('ui.demo.tab_animations')=> $animationsTab(),
            __('ui.demo.tab_charts')    => $chartsTab(),
            __('ui.demo.tab_datatable') => $dataTableTab(),
            __('ui.demo.tab_layouts')   => $layoutsTab(),
            __('ui.demo.tab_misc')      => $miscTab(),
            __('ui.demo.tab_htmx')      => $htmxTab(),
            __('ui.demo.tab_audit')     => $auditTab(),
            __('ui.demo.tab_flags')     => $flagsTab(),
        ]),

        UI::debugBar(),

        UI::commandPalette([
            ['label' => 'Home',             'url' => '/',          'category' => 'Navigation', 'icon' => '⌂'],
            ['label' => 'UI Demo',          'url' => '/ui-demo',   'category' => 'Navigation', 'icon' => '◈'],
            ['label' => 'Buttons',          'url' => '#',          'category' => 'Components', 'icon' => '▣'],
            ['label' => 'Forms',            'url' => '#',          'category' => 'Components', 'icon' => '⊞'],
            ['label' => 'Charts',           'url' => '#',          'category' => 'Components', 'icon' => '⬡'],
            ['label' => 'DataTable',        'url' => '#',          'category' => 'Components', 'icon' => '⊟'],
            ['label' => 'Modal',            'url' => '#',          'category' => 'Components', 'icon' => '◻'],
            ['label' => 'Debug Error',      'url' => '/debug-error','category' => 'Dev',        'icon' => '⚠'],
        ]),
    )
    ->render();
