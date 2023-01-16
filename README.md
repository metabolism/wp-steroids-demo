## Overview

Bedrock is a modern WordPress stack that helps you get started with the best development tools and project structure.

This is a modified version using [Timber](https://fr.wordpress.org/plugins/timber-library/) and [WP Steroid](https://github.com/wearemetabolism/wp-steroids) 

## Features

- Better folder structure
- YML configuration
- Dependency management with [Composer](https://getcomposer.org)
- Easy WordPress configuration with environment specific files
- Environment variables with [Dotenv](https://github.com/vlucas/phpdotenv)
- Autoloader for mu-plugins (use regular plugins as mu-plugins)
- Enhanced security (separated web root and secure passwords with [wp-password-bcrypt](https://github.com/roots/wp-password-bcrypt))

## Requirements

- PHP >= 7.4
- Composer - [Install](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

## Installation

1. Update environment variables in the `.env` file. Wrap values that may contain non-alphanumeric characters with quotes, or they may be incorrectly parsed.

- Database variables
  - `DB_NAME` - Database name
  - `DB_USER` - Database user
  - `DB_PASSWORD` - Database password
  - `DB_HOST` - Database host
  - Optionally, you can define `DATABASE_URL` for using a DSN instead of using the variables above (e.g. `mysql://user:password@127.0.0.1:3306/db_name`)
- `WP_ENV` - Set to environment (`development`, `staging`, `production`)
- `WP_HOME` - Full URL to WordPress home (https://example.com)
- `WP_SITEURL` - Full URL to WordPress including subdirectory (https://example.com/wp)
- `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`
  - Generate with [wp-cli-dotenv-command](https://github.com/aaemnnosttv/wp-cli-dotenv-command)
  - Generate with [our WordPress salts generator](https://roots.io/salts.html)

2. Install vendor
   ```sh
   $ composer install
   ```
3. Set the document root on your webserver to Bedrock's `public` folder: `/path/to/site/public/`
4. Access WordPress admin at `https://example.com/edition/wp-admin/`

## Configuration

Edit `/config/app.yml` to edit WordPress configuration ( custom post type, custom taxonomy and so much more )

## Theming

1. Install vendor
   ```sh
   $ npm install
   ```
2. Edit twig files in `/templates`
3. Edit site configuration in `/src/Site.php`
4. Edit page context in `/public/app/themes/timber`
5. Run dev server
```sh
$ npm run dev-server
   ```

## Building enterprise solutions with WordPress ?

You may want to try the [WordPress Bundle](https://github.com/wearemetabolism/wordpress-bundle) for Symfony.
