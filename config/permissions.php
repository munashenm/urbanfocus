<?php

return [
    'permissions' => [
        'products.view' => ['label' => 'View products', 'group' => 'Products'],
        'products.create' => ['label' => 'Create products', 'group' => 'Products'],
        'products.edit' => ['label' => 'Edit products', 'group' => 'Products'],
        'products.delete' => ['label' => 'Delete products', 'group' => 'Products'],
        'orders.view' => ['label' => 'View orders', 'group' => 'Orders'],
        'orders.edit' => ['label' => 'Edit orders', 'group' => 'Orders'],
        'orders.refund' => ['label' => 'Refund orders', 'group' => 'Orders'],
        'customers.view' => ['label' => 'View customers', 'group' => 'Customers'],
        'customers.edit' => ['label' => 'Edit customers', 'group' => 'Customers'],
        'rfqs.view' => ['label' => 'View RFQs', 'group' => 'Sales'],
        'rfqs.create' => ['label' => 'Create RFQs', 'group' => 'Sales'],
        'rfqs.edit' => ['label' => 'Edit RFQs', 'group' => 'Sales'],
        'quotations.create' => ['label' => 'Create quotations', 'group' => 'Sales'],
        'invoices.view' => ['label' => 'View invoices', 'group' => 'Finance'],
        'reports.view' => ['label' => 'View reports', 'group' => 'Reports'],
        'settings.manage' => ['label' => 'Manage settings', 'group' => 'Settings'],
        'users.manage' => ['label' => 'Manage users', 'group' => 'Users'],
        'roles.manage' => ['label' => 'Manage roles', 'group' => 'Users'],
        'audit_logs.view' => ['label' => 'View audit logs', 'group' => 'Security'],
    ],

    'roles' => [
        'super-admin' => [
            'label' => 'Super Admin',
            'description' => 'Full access to everything.',
            'permissions' => ['*'],
            'system' => true,
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'Manage products, orders, customers, RFQs, and reports.',
            'permissions' => [
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'orders.view', 'orders.edit', 'orders.refund',
                'customers.view', 'customers.edit',
                'rfqs.view', 'rfqs.create', 'rfqs.edit', 'quotations.create',
                'invoices.view', 'reports.view',
            ],
            'system' => true,
        ],
        'sales' => [
            'label' => 'Sales',
            'description' => 'Manage RFQs, quotations, customers, and orders.',
            'permissions' => [
                'orders.view', 'orders.edit',
                'customers.view', 'customers.edit',
                'rfqs.view', 'rfqs.create', 'rfqs.edit', 'quotations.create',
                'invoices.view',
            ],
            'system' => true,
        ],
        'inventory-manager' => [
            'label' => 'Inventory Manager',
            'description' => 'Manage stock, products, categories, and brands.',
            'permissions' => [
                'products.view', 'products.create', 'products.edit', 'products.delete',
            ],
            'system' => true,
        ],
        'finance' => [
            'label' => 'Finance',
            'description' => 'View orders, payments, invoices, and reports.',
            'permissions' => [
                'orders.view', 'invoices.view', 'reports.view',
            ],
            'system' => true,
        ],
        'support' => [
            'label' => 'Support',
            'description' => 'View customers and orders, update support notes.',
            'permissions' => [
                'orders.view', 'orders.edit',
                'customers.view',
            ],
            'system' => true,
        ],
        'viewer' => [
            'label' => 'Viewer',
            'description' => 'Read-only access.',
            'permissions' => [
                'products.view', 'orders.view', 'customers.view',
                'rfqs.view', 'invoices.view', 'reports.view',
            ],
            'system' => true,
        ],
    ],

    'login' => [
        'max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('ADMIN_LOGIN_LOCKOUT_MINUTES', 15),
    ],
];
