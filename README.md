# Project Setup with DDEV

## Prerequisites
Before getting started, ensure that you have the following installed on your system:

- **Docker**: You must have Docker installed and running.
- **DDEV**: We have configured a `DDEV` `config.yaml` file for this project. If you haven’t installed DDEV yet, follow the official installation guide: [DDEV Installation Guide](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/).

## Setting Up the Project

Once **DDEV** is installed and **Docker** is running, navigate to the project directory and run the following command:

```sh
ddev config
```

When prompted, use the following responses:
- **Project name**: `corp-boydbros-com` *(hit enter)*
- **Docroot Location**: `public_html` *(hit enter)*
- **Project Type**: `php` *(hit enter)*

The prompt will now confirm:

```sh
Configuration complete. You may now run 'ddev start'.
```

## Starting the Development Environment

Run the following command:

```sh
ddev start
```

Once DDEV is booted and running, the terminal will display a message like this:

```sh
Your project can be reached at: https://corp-boydbros-com.ddev.site/
```

## Importing the Database

Before accessing the site, you will likely need to import the database. Fortunately, **DDEV** comes with **Sequelace** pre-installed.

While still in your terminal, import the database using the following command:

```sh
ddev import-db -f /absolute/path/to/file/database_boydbros-com_2-12-25_backup.sql
```

This will import the database. Once completed, the website should be ready to load.

## Accessing the Admin Panel

To access the admin panel, append `/admin.php` to the home URL:

```
https://corp-boydbros-com.ddev.site/admin.php
```

Log in using the credentials provided to you.

## Using phpMyAdmin

phpMyAdmin is no longer built into DDEV. To install and use it, run the following commands:

```sh
ddev get ddev/ddev-phpmyadmin
```

Then, launch phpMyAdmin with:

```sh
ddev phpmyadmin
```

---

Your project is now fully set up and running on DDEV! 🚀

