# Symfony Playground "two"

![GitHub workflow](https://github.com/elkuku/symfony-playground-two/actions/workflows/tests.yml/badge.svg)

![Screenshot_20231110_130931](https://github.com/elkuku/symfony-playground-two/assets/33978/23f73524-bf02-4af6-8c05-5da4ed8fdb7a)

## What's this??
An opinionated [Symfony](https://symfony.com) project template including:

* Symfony 7.*
* Docker compose file for PostgreSQL
* `dev` login form <br/> `prod` Social login with Google, GitLab, GitHub and [more](https://github.com/knpuniversity/oauth2-client-bundle#step-1-download-the-client-library)
* Asset mapper
* Bootstrap

## Installation
Clone the repo then use the
```shell
bin/install
```
command or execute the script content manually.
   
## Usage
```shell
symfony console user-admin
```
Create an administer user accounts.
```shell
bin/start
```
```shell
bin/stop
```
start and stop the environment.

## Testing

```shell
make tests
```

----

Happy coding `=;)`
