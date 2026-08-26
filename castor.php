<?php

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;
use function docker\about;
use function docker\build;
use function docker\docker_compose_run;
use function docker\up;
use function docker\workers_start;
use function docker\workers_stop;

guard_min_version('v1.5.0');

import(__DIR__ . '/.castor');

/**
 * @return array{project_name: string, root_domain: string}
 */
function create_default_variables(): array
{
    $projectName = 'async-messenger-mercure';
    $tld = 'test';

    return [
        'project_name' => $projectName,
        'root_domain' => "{$projectName}.{$tld}",
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    workers_stop();
    build();
    install();
    up(profiles: ['default']); // We can't start worker now, they are not installed
    migrate();
    workers_start();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    io()->title('Installing the application');

    io()->section('Installing PHP dependencies');
    docker_compose_run(['composer', 'install', '-n', '--prefer-dist', '--optimize-autoloader']);

    if (is_file(variable('root_dir') . '/importmap.php')) {
        io()->section('Installing importmap');
        docker_compose_run(['bin/console', 'importmap:install']);
    }

    qa\install();
}

#[AsTask(description: 'Clears the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    io()->title('Clearing the application cache');

    docker_compose_run(['rm', '-rf', 'var/cache/']);
    // On the very first run, the vendor does not exist yet
    if (is_dir(variable('root_dir') . '/vendor')) {
        docker_compose_run(['bin/console', 'cache:warmup'], c: context()->withAllowFailure());
    }
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    docker_compose_run(['bin/console', 'doctrine:database:create', '--if-not-exists']);
    docker_compose_run(['bin/console', 'doctrine:migration:migrate', '-n', '--allow-no-migration', '--all-or-nothing']);
}

#[AsTask(description: 'Loads fixtures', namespace: 'app:db', aliases: ['fixtures'])]
function fixtures(): void
{
    io()->title('Loads fixtures');

    docker_compose_run(['bin/console', 'doctrine:fixture:load', '-n']);
}
