# mittwald Deployer Recipe Collection

<p align="center">
    <a href="#installation">⚒️ Installation instructions</a> |
    <a href="#usage">🙆 Usage</a> |
    <a href="#configuration-options">📖 Configuration options</a> |
    <a href="#ci-usage-examples">🤖 CI usage examples</a>
</p>

---

> [!IMPORTANT]
> This library is currently in an beta state. We welcome any feedback and contributions.

This repository contains a set of mittwald-specific helper functions and
recipes for [Deployer](https://deployer.org/).

## Installation

In order to use this recipe collection, you need to install it via
[Composer](https://getcomposer.org):

```bash
composer require --dev mittwald/deployer-recipes
```

## Usage

> [!NOTE]
> Find configuration examples for common CI/CD tools like Github Actions and Gitlab CI at the bottom of this document.

This recipe needs a [mittwald API token](https://developer.mittwald.de/docs/v2/api/intro/) to work. It can be either provided via the `MITTWALD_API_TOKEN` environment variable, or by setting the `mittwald_token` value in your Deployer configuration.

```
$ export MITTWALD_API_TOKEN=...
```

In order to use the recipes provided by this library, you need to include them in your `deploy.php` file:

```php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/mittwald/deployer-recipes/recipes/deploy.php';
```

To enable the automatic deployment for a host, set the `mittwald_app_id` variable to the ID of the mittwald application
you want to deploy to. In this case, the hostname becomes irrelevant, as the recipe will automatically determine the
correct hostname for the SSH connection:

```php
host('mittwald')
    ->set('public_path', '/')
    ->set('mittwald_app_id', '<uuid>')
    ->set('mittwald_app_dependencies', [
        'php'      => '{{php_version}}',
        'gm'       => '*',
        'composer' => '*',
    ]);
```

Alternatively, you can also use the `mittwald_app` shorthand function for the same effect:

```php
mittwald_app('<uuid>')
    ->set('public_path', '/')
    ->set('mittwald_app_dependencies', [
        'php'      => '{{php_version}}',
        'gm'       => '*',
        'composer' => '*',
    ]);
```

## What does it do?

This recipe automates the provisioning of PHP applications on the mittwald cloud platform. You will only need to provide
the recipe with the ID of the mittwald application you want to deploy to, and the recipe will take care of the rest by
automatically determining the correct deployment path, database credentials, and so on.

More precisely, the recipe will:

- Look up the deployment directory for the given application ID and set it as the `deploy_path` for deployer.
- Correctly configure a virtual host for the domain configured in `domain`
- Look up the necessary SSH connection data, create and manage an SSH user for
  the deployment, and configure the deployer host accordingly.
- Make sure that the app's system environment matches the one configured in `mittwald_app_dependencies`

## Configuration options

- `mittwald_app_id`: The ID of the mittwald application you want to deploy to. This is the only required option, and should be set per host.
 
- `mittwald_app_dependencies`: A map of system dependencies that should be installed on the mittwald application. The recipe will make sure that the app's system environment matches the one configured here.
 
  The expected format is a map, using system package names as keys and semver compatible version constraints as values.

  Defaults to `["php" => "{{php_version}}", "composer" => "*"]`.

- `mittwald_domains` may be used to override the domains that should be configured. Defaults to `["{{domain}}"]`.
 
- `mittwald_domain_path_prefix` can be used to configure a prefix for the domain path. Defaults to `"/"`.

- `mittwald_ssh_public_key` and `mittwald_ssh_private_key` may contain an SSH public/private key pair that should be used for deployment. If not set, the `ssh_copy_id` variable will be used.

## CI usage examples

### Github actions

To use this recipe in a Github actions workflow, you should first configure the following secrets in your repository settings:

- `MITTWALD_API_TOKEN` should contain your mittwald API token
- `MITTWALD_APP_ID` should contain the ID of the mittwald application you want to deploy to.
- `MITTWALD_SSH_PRIVATE_KEY` should contain the private key of the SSH key pair that should be used for deployment.
- `MITTWALD_SSH_PUBLIC_KEY` should contain the public key of the SSH key pair that should be used for deployment.

Then, you can use the following workflow to deploy your application:

```yaml
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-suggest
        
      - name: Deploy SSH keys
        env:
          MITTWALD_SSH_PRIVATE_KEY: ${{ secrets.MITTWALD_SSH_PRIVATE_KEY }}
          MITTWALD_SSH_PUBLIC_KEY: ${{ secrets.MITTWALD_SSH_PUBLIC_KEY }}
        run: |
          mkdir -p .mw-deploy
          echo "${MITTWALD_SSH_PRIVATE_KEY}" > .mw-deploy/id_rsa
          echo "${MITTWALD_SSH_PUBLIC_KEY}" > .mw-deploy/id_rsa.pub
          chmod 600 .mw-deploy/id_rsa*

      - name: Run deployer
        run: |
          ./vendor/bin/dep deploy \
            -o mittwald_app_id={{ secrets.MITTWALD_APP_ID }} \
            -o mittwald_ssh_public_key_file=.mw-deploy/id_rsa.pub \
            -o mittwald_ssh_private_key_file=.mw-deploy/id_rsa
        env:
          MITTWALD_API_TOKEN: ${{ secrets.MITTWALD_API_TOKEN }}
```

## Gitlab CI

This Gitlab CI workflow uses the same repository variables as the Github actions example above:

```yaml
deploy:
  image: php:8.2-cli
  stage: deploy
  before_script:
    - wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
    - apt-get update && apt-get install -y git openssh-client
    - mkdir -p .mw-deploy
    - echo "$MITTWALD_SSH_PRIVATE_KEY" > .mw-deploy/id_rsa
    - echo "$MITTWALD_SSH_PUBLIC_KEY" > .mw-deploy/id_rsa.pub
    - chmod 600 .mw-deploy/id_rsa*
  script:
    - ./vendor/bin/dep deploy \
        -o mittwald_app_id=$MITTWALD_APP_ID \
        -o mittwald_ssh_public_key_file=.mw-deploy/id_rsa.pub \
        -o mittwald_ssh_private_key_file=.mw-deploy/id_rsa
  environment:
    name: production
```
