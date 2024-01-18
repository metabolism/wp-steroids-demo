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

## Server requirements

- PHP >= 7.4, 8.2 recommended,  with GD ( jpeg, webp ), pdo_mysql, mysqli
- Curl, Git, Zip, Composer 2
- Node 16
- Mysql >= 5.7 or Maria DB >= 10.4
- Nginx or Apache with mod_rewrite module

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
- `ACF_PRO_KEY` - Your ACF Licence key
- `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`
    - Generate with [WordPress salts generator](https://roots.io/salts.html)

2. Install vendor
   ```sh
   $ composer install
   ```

3. Build sources
   ```sh
   $ npm install && npm run build
   ```

4. Set the document root on your webserver to Bedrock's `public` folder: `/path/to/site/public/`

5. Access WordPress admin at `https://example.com/edition/wp-admin/`


## Development

1. Edit `/config/app.yml` to edit WordPress configuration ( custom post type, custom taxonomy and so much more )
1. Edit twig files in `/templates`
2. Add specific site functions in `/src/Site.php`
3. Edit page context in `/src/Controller`
4. Run dev server do rebuild sources
   ```sh
   $ npm run dev-server
   ```

## Docker

Project is shipped with docker files samples.

Please feel free to update WP_HOME and WP_SITEURL in `docker-compose.yml` and server_name in `.docker/nginx/default.conf`

Run project using

```sh
 docker-compose build --build-arg ACF_PRO_KEY=your_licence_key
 docker-compose up -d
```

Access WordPress admin at `http://localhost:8000`