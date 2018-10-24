<?php

declare(strict_types=1);

namespace App\Middleware;

use PDO;
use PDOException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Expressive\Authentication\Exception as AuthenticationException;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Exception\ExceptionInterface as AclExceptionInterface;

class AclMiddlewareFactory
{
    /**
     * @var array|null
     */
    private $authentication;

    /**
     * @var array|null
     */
    private $authorization;

    public function __invoke(ContainerInterface $container): MiddlewareInterface
    {
        $this->authentication = $container->get('config')['authentication'] ?? null;
        $this->authorization = $container->get('config')['authorization'] ?? null;

        $acl = new Acl();

        $this->injectUsersRoles($acl);

        /*
         * Add resource "logs"
         */
        $acl->addResource('logs');

        /*
         * Add resource "public".
         * Add READ access for everyone to directory "public".
         */
        if (is_dir('data/public') && is_readable('data/public')) {
            $acl->addResource('directory.public');
            $acl->allow(
                null,
                'directory.public',
                [AclMiddleware::PERM_READ]
            );
        }
        /*
         * Add role "role".
         * Add resource directory "role".
         * Add READ access for each role to its directory.
         */
        if (is_dir('data/roles') && is_readable('data/roles')) {
            $roles = glob('data/roles/*', GLOB_ONLYDIR);
            foreach ($roles as $r) {
                $role = basename($r);

                if (!$acl->hasRole($role)) {
                    $acl->addRole($role);
                }

                $acl->addResource('directory.roles.'.$role);
                $acl->allow(
                    $role,
                    'directory.roles.'.$role,
                    [AclMiddleware::PERM_READ]
                );
            }
        }
        /*
         * Add role "username".
         * Add resource directory "username".
         * Add READ, WRITE, DELETE access for each user on its directory.
         */
        if (is_dir('data/users') && is_readable('data/users')) {
            $users = glob('data/users/*', GLOB_ONLYDIR);
            foreach ($users as $u) {
                $user = basename($u);

                if (!$acl->hasRole($user)) {
                    $acl->addRole($user);
                }

                $acl->addResource('directory.users.'.$user);
                $acl->allow(
                    $user,
                    'directory.users.'.$user,
                    [AclMiddleware::PERM_READ, AclMiddleware::PERM_WRITE, AclMiddleware::PERM_DELETE]
                );
            }
        }

        $this->injectConfigRoles($acl, $this->authorization['roles'] ?? []);
        $this->injectConfigResources($acl, $this->authorization['resources'] ?? []);
        $this->injectConfigPermissions($acl, $this->authorization['allow'] ?? [], 'allow');
        $this->injectConfigPermissions($acl, $this->authorization['deny'] ?? [], 'deny');

        return new AclMiddleware($acl);
    }

    /**
     * Add User and Role from database.
     */
    private function injectUsersRoles(Acl $acl): void
    {
        $config = $this->authentication['pdo'] ?? null;

        if (is_null($config)) {
            return;
        }

        if (!isset($config['dsn'])) {
            throw new AuthenticationException\InvalidConfigException(
                'The PDO DSN value is missing in the configuration'
            );
        }
        if (!isset($config['table'])) {
            throw new AuthenticationException\InvalidConfigException(
                'The PDO table name is missing in the configuration'
            );
        }
        if (!isset($config['field']['identity'])) {
            throw new AuthenticationException\InvalidConfigException(
                'The PDO identity field is missing in the configuration'
            );
        }
        if (!isset($config['field']['password'])) {
            throw new AuthenticationException\InvalidConfigException(
                'The PDO password field is missing in the configuration'
            );
        }

        $pdo = new PDO(
            $config['dsn'],
            $config['username'] ?? null,
            $config['password'] ?? null
        );

        $sqlUser = sprintf(
            'SELECT %s FROM %s',
            $config['field']['identity'],
            $config['table']
        );
        $stmtUser = $pdo->prepare($sqlUser);

        if (false === $stmtUser) {
            throw new AuthenticationException\RuntimeException(
                'An error occurred when preparing to fetch user details from '.
                'the repository; please verify your configuration'
            );
        }
        $stmtUser->execute();

        foreach ($stmtUser->fetchAll(PDO::FETCH_NUM) as $user) {
            if (!isset($config['sql_get_roles'])) {
                $acl->addRole($user);
            } else {
                if (false === strpos($config['sql_get_roles'], ':identity')) {
                    throw new AuthenticationException\InvalidConfigException(
                        'The sql_get_roles configuration setting must include an :identity parameter'
                    );
                }

                try {
                    $stmtRoles = $pdo->prepare($config['sql_get_roles']);
                } catch (PDOException $e) {
                    throw new AuthenticationException\RuntimeException(sprintf(
                        'Error preparing retrieval of user roles: %s',
                        $e->getMessage()
                    ));
                }
                if (false === $stmtRoles) {
                    throw new AuthenticationException\RuntimeException(sprintf(
                        'Error preparing retrieval of user roles: unknown error'
                    ));
                }
                $stmtRoles->bindParam(':identity', $user[0]);

                if (!$stmtRoles->execute()) {
                    $acl->addRole($user[0], []);
                }

                $roles = [];
                foreach ($stmtRoles->fetchAll(PDO::FETCH_NUM) as $role) {
                    $roles[] = $role[0];

                    if (!$acl->hasRole($role[0])) {
                        $acl->addRole($role[0]);
                    }
                }

                $acl->addRole($user[0], $roles);
            }
        }
    }

    /**
     * Add Role from config `authorization`.
     */
    private function injectConfigRoles(Acl $acl, array $roles): void
    {
        foreach ($roles as $role => $parents) {
            foreach ($parents as $parent) {
                if (!$acl->hasRole($parent)) {
                    try {
                        $acl->addRole($parent);
                    } catch (AclExceptionInterface $e) {
                        throw new AuthenticationException\InvalidConfigException($e->getMessage(), $e->getCode(), $e);
                    }
                }
            }

            try {
                $acl->addRole($role, $parents);
            } catch (AclExceptionInterface $e) {
                throw new AuthenticationException\InvalidConfigException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Add Resource from config `authorization`.
     */
    private function injectConfigResources(Acl $acl, array $resources): void
    {
        foreach ($resources as $resource) {
            try {
                $acl->addResource($resource);
            } catch (AclExceptionInterface $e) {
                throw new AuthenticationException\InvalidConfigException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Add Allow/Deny permission from config `authorization`.
     */
    private function injectConfigPermissions(Acl $acl, array $permissions, string $type): void
    {
        if (!in_array($type, ['allow', 'deny'], true)) {
            throw new AuthenticationException\InvalidConfigException(sprintf(
                'Invalid permission type "%s" provided in configuration; must be one of "allow" or "deny"',
                $type
            ));
        }
        foreach ($permissions as $role => $resources) {
            try {
                if (is_array($resources)) {
                    foreach ($resources as $key => $value) {
                        if (is_numeric($key)) {
                            $acl->$type($role, $value);
                        } else {
                            $acl->$type($role, $key, $value);
                        }
                    }
                } else {
                    $acl->$type($role, $resources);
                }
            } catch (AclExceptionInterface $e) {
                throw new AuthenticationException\InvalidConfigException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}
