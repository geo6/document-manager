# Document Manager

## Install

    composer create-project geo6/document-manager

## Authentication

To enable authentication, add the following in your configuration file (usually `config/autoload/local.php`) :

    'authentication' => [
        'pdo' => [
            'dsn' => 'pgsql:host=localhost;port=5432;dbname=...',
            'username' => '...',
            'password' => '...',
            'table' => '...',
            'field' => [
                'identity' => '...',
                'password' => '...',
            ],
            'sql_get_roles' => '...',
            'sql_get_details' => '...',
        ],
    ],

See <https://docs.zendframework.com/zend-expressive-authentication/v1/user-repository/#pdo-configuration> for more information !

Then you can create directory structure like `data/roles/<rolename>` and/or `data/users/<username>`.

By default, 

- each user has `read` and `delete` permission on its own directory (`data/users/<username>`),
- each user has `read` permission on the directory (`data/roles/<rolename>`) of each role he has

## Permissions

If you want to change the default permissions, add the following in your configuration file (usually `config/autoload/local.php`) :

    'authorization' => [
        'roles' => [
        ],
        'resources' => [
        ],
        'allow' => [
            '<rolename>|<username>' => [
                '<resource>' => ['<permission>', ...],
            ]
        ],
        'deny' => [
            '<rolename>|<username>' => [
                '<resource>' => ['<permission>', ...],
            ]
        ],
    ],

See <https://docs.zendframework.com/zend-permissions-acl/usage/> for more information about ACL !

`<resource>` can be :

- `directory.public`
- `directory.roles.<rolename>`
- `directory.roles.<username>`

`<permission>` can be :

- `read`
- `delete`
- `write`
