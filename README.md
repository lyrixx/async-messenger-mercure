# Async with Messenger & Mercure

[light.webm](https://github.com/user-attachments/assets/a7c46f6b-101b-4a81-9ed2-2ff60af4c92b)

This project is a demo for [a talk I'll give at SymfonyLive Paris 2025](https://s.lyrixx.info/async).

It's all about async processing with Symfony Messenger and Mercure: upload a
CSV file, it gets imported in the background by a Messenger worker, and
progress is pushed live to the browser through Mercure — no polling involved.

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * Bash
 * [Castor](https://github.com/jolicode/castor#installation)

#### Castor

Once `castor` is installed, in order to improve your usage of `castor` scripts, you
can install console autocompletion script.

If you are using bash:

```bash
castor completion | sudo tee /etc/bash_completion.d/castor
```

If you are using something else, please refer to your shell documentation. You
may need to use `castor completion > /to/somewhere`.

`castor` supports completion for `bash`, `zsh` & `fish` shells.

### Docker environment

The Docker infrastructure provides a web stack with:
 - FrankenPHP (service `frontend`), embedding the Mercure hub
 - A Messenger consumer (`worker_messenger`) processing the async queue
 - PostgreSQL 16
 - Traefik, terminating HTTPS
 - A `builder` container with Composer and the PHP tooling (no Node/Yarn — assets
   are handled by Symfony AssetMapper)

### Domain configuration (first time only)

Before running the application for the first time, ensure your domain names
point the IP of your Docker daemon by editing your `/etc/hosts` file.

This IP is probably `127.0.0.1` unless you run Docker in a special VM (like docker-machine for example).

> [!NOTE]
> The router binds port 80 and 443, that's why it will work with `127.0.0.1`

```
echo '127.0.0.1 async-messenger-mercure.test' | sudo tee -a /etc/hosts
```

### Starting the stack

Launch the stack by running this command:

```bash
castor start
```

> [!NOTE]
> the first start of the stack should take a few minutes.

The site is now accessible at the hostnames you have configured over HTTPS
(you may need to accept self-signed SSL certificate if you do not have `mkcert`
installed on your computer - see below).

### SSL certificates

HTTPS is supported out of the box. SSL certificates are not versioned and will
be generated the first time you start the infrastructure (`castor start`) or if
you run `castor docker:generate-certificates`.

If you have `mkcert` installed on your computer, it will be used to generate
locally trusted certificates. See [`mkcert` documentation](https://github.com/FiloSottile/mkcert#installation)
to understand how to install it. Do not forget to install CA root from `mkcert`
by running `mkcert -install`.

If you don't have `mkcert`, then self-signed certificates will instead be
generated with `openssl`. You can configure [infrastructure/docker/services/router/openssl.cnf](infrastructure/docker/services/router/openssl.cnf)
to tweak certificates.

You can run `castor docker:generate-certificates --force` to recreate new certificates
if some were already generated. Remember to restart the infrastructure to make
use of the new certificates with `castor up` or `castor start`.

### Builder

Having some composer or other modifications to make on the project? Start the
builder which will give you access to a container with all these tools
available:

```bash
castor builder
```

### Useful commands

```bash
castor start                   # build + install + up + migrate + start workers
castor stop                    # stop the stack
castor logs [--service=...]    # tail logs (frontend, worker_messenger, postgres, ...)
castor pg                      # psql shell on the app database
castor qa                      # coding standards + static analysis + tests
```

Run `castor` with no arguments to list every available task.
