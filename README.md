# Async with Messenger & Mercure

This project is a demo for a talk I (will) give. If you find this projet,
**please do not share it** until I remove this sentence 😂. It's about doing
async task with Messenger, and giving feedback in real time with Mercure

![See the demo](https://github.com/lyrixx/async-messenger-mercure/blob/master/async.gif)

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * pipenv (see [these instructions](https://pipenv.readthedocs.io/en/latest/install/) for how to install)

Install and run `pipenv` to install the required tools:

```bash
pipenv install
```

You can configure your current shell to be able to use Invoke commands directly
(without having to prefix everything by `pipenv run`)

```bash
pipenv shell
```

### Starting the stack

Launch the stack by running this command:

```bash
inv start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at the hostnames your have configured over HTTPS
(you may need to accept self-signed SSL certificate).

### Other tasks

Checkout `inv -l` to have the list of available Invoke tasks.
