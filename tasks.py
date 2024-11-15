from invoke import task
from shlex import quote
from colorama import Fore
import re


@task
def build(c):
    """
    Build the infrastructure
    """
    command = 'build'
    command += ' --build-arg PROJECT_NAME=%s' % c.project_name
    command += ' --build-arg USER_ID=%s' % c.user_id

    with Builder(c):
        for service in c.services_to_build_first:
            docker_compose(c, '%s %s' % (command, service))

        docker_compose(c, command)


@task
def up(c):
    """
    Build and start the infrastructure
    """
    build(c)

    docker_compose(c, 'up --remove-orphans --detach')


@task
def start(c):
    """
    Build and start the infrastructure, then install the application (composer, yarn, ...)
    """
    if c.dinghy:
        machine_running = c.run('dinghy status', hide=True).stdout
        if machine_running.splitlines()[0].strip() != 'VM: running':
            c.run('dinghy up --no-proxy')
            c.run('docker-machine ssh dinghy "echo \'nameserver 8.8.8.8\' | sudo tee -a /etc/resolv.conf && sudo /etc/init.d/docker restart"')

    stop_workers(c)
    up(c)
    install(c)
    migrate(c)
    start_workers(c)

    print(Fore.GREEN + 'You can now browse:')
    for domain in [c.root_domain] + c.extra_domains:
        print(Fore.YELLOW + "* https://" + domain)


@task
def install(c):
    """
    Install the application (composer, yarn, ...)
    """
    with Builder(c):
        docker_compose_run(c, 'composer install -n --prefer-dist --optimize-autoloader', no_deps=True)


@task
def migrate(c):
    """
    Migrate database schema
    """
    with Builder(c):
        docker_compose_run(c, 'php bin/console doctrine:database:create --if-not-exists')
        docker_compose_run(c, 'php bin/console doctrine:migration:migrate -n')


@task
def builder(c, user="app"):
    """
    Open a shell (bash) into a builder container
    """
    with Builder(c):
        docker_compose_run(c, 'bash', user=user)


@task
def logs(c):
    """
    Display infrastructure logs
    """
    docker_compose(c, 'logs -f --tail=150')


@task
def ps(c):
    """
    List containers status
    """
    docker_compose(c, 'ps --all')


@task
def stop(c):
    """
    Stop the infrastructure
    """
    docker_compose(c, 'stop')


@task
def start_workers(c):
    """
    Start the workers
    """
    workers = get_workers(c)

    if (len(workers) == 0):
        return

    c.start_workers = True
    c.run('docker update --restart=unless-stopped %s' % (' '.join(workers)), hide='both')
    docker_compose(c, 'up --remove-orphans --detach')


@task
def stop_workers(c):
    """
    Stop the workers
    """
    workers = get_workers(c)

    if (len(workers) == 0):
        return

    c.start_workers = False
    c.run('docker update --restart=no %s' % (' '.join(workers)), hide='both')
    c.run('docker stop %s' % (' '.join(workers)), hide='both')


@task
def destroy(c, force=False):
    """
    Clean the infrastructure (remove container, volume, networks)
    """

    if not force:
        ok = confirm_choice('Are you sure? This will permanently remove all containers, volumes, networks... created for this project.')
        if not ok:
            return

    with Builder(c):
        docker_compose(c, 'down --volumes --rmi=local')


def docker_compose_run(c, command_name, service="builder", user="app", no_deps=False, workdir=None, port_mapping=False):
    args = [
        'run',
        '--rm',
        '-u %s' % quote(user),
    ]

    if no_deps:
        args.append('--no-deps')

    if port_mapping:
        args.append('--service-ports')

    if workdir is not None:
        args.append('-w %s' % quote(workdir))

    docker_compose(c, '%s %s /bin/sh -c "exec %s"' % (
        ' '.join(args),
        quote(service),
        command_name
    ))


def docker_compose(c, command_name):
    domains = '`' + '`, `'.join([c.root_domain] + c.extra_domains) + '`'

    env = {
        'PROJECT_NAME': c.project_name,
        'PROJECT_DIRECTORY': c.project_directory,
        'PROJECT_ROOT_DOMAIN': c.root_domain,
        'PROJECT_DOMAINS': domains,
        'PROJECT_START_WORKERS': str(c.start_workers),
    }

    cmd = 'docker-compose -p %s %s %s' % (
        c.project_name,
        ' '.join('-f "' + c.root_dir + '/infrastructure/docker/' + file + '"' for file in c.docker_compose_files),
        command_name
    )

    c.run(cmd, pty=not c.power_shell, env=env)


def get_workers(c):
    """
    Find worker containers for the current project
    """
    cmd = c.run('docker ps -a --filter "label=docker-starter.worker.%s" --quiet' % c.project_name, hide='both')
    return list(filter(None, cmd.stdout.rsplit("\n")))


def confirm_choice(message):
    confirm = input('%s [y]es or [N]o: ' % message)

    return re.compile('^y').search(confirm)


class Builder:
    def __init__(self, c):
        self.c = c

    def __enter__(self):
        self.docker_compose_files = self.c.docker_compose_files
        self.c.docker_compose_files = ['docker-compose.builder.yml'] + self.docker_compose_files

    def __exit__(self, type, value, traceback):
        self.c.docker_compose_files = self.docker_compose_files
