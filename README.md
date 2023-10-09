# mittwald deployer recipe collection

> [!IMPORTANT]
> This library is currently in an experimental state. We welcome any feedback and contributions.

This repository contains a set of mittwald-specific helper functions and recipes for [deployer](https://deployer.org/).

## Installation

In order to use this recipe collection, you need to install it via composer:

```bash
composer require --dev mittwald/deployer-recipes
```

## Usage

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
- Create and manage an SSH user that will be used for deployment
- Make sure that the app's system environment matches the one configured in `mittwald_app_dependencies`

## Configuration options

- `mittwald_app_id`: The ID of the mittwald application you want to deploy to. 
  This is the only required option, and should be set per host.
 
- `mittwald_app_dependencies`: A map of system dependencies that should be
  installed on the mittwald application. The recipe will make sure that the
  app's system environment matches the one configured here.
 
  The expected format is a map, using system package names as keys and semver
  compatible version constraints as values.

  Defaults to `["php" => "{{php_version}}", "composer" => "*"]`.

- `mittwald_domains` may be used to override the domains that should be
  configured. Defaults to `["{{domain}}"]`.
 
- `mittwald_domain_path_prefix` can be used to configure a prefix for the
  domain path. Defaults to `"/"`.

- `mittwald_ssh_key_contents` may contain an SSH public key that should be
  used for deployment. If not set, the `ssh_copy_id` variable will be used.