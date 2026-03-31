# Upanup Admin

## Overview

Custom module that provides admin UI enhancements, styling overrides, and admin redirect functionality for Upanup Drupal sites.

- **Package:** Upanup
- **Version:** 10.x-2.0
- **Drupal compatibility:** ^9.0 || ^10

---

## Requirements

- Drupal 10 or 11
- DNS record pointing to the server IP or domain (e.g. `name.admin.upanup.com`, `admin.domain.com`, `name.admin.domain.com`)
- Alternatively use a wildcard DNS record (e.g. `*.domain.com` or `*.admin.domain.com`)
- SSL certificate for the domains, can also be a wildcard

## Installation

1. Enable the module: `drush en upanup_admin`
   - Or through Drupal admin, navigate to ** Extend ** and enable `Upanup Admin`
2. Navigate to **Administration > Upanup Admin > Settings** to configure.
3. Ensure DNS and SSL are configured before enabling.

## Features

### Admin Redirect

An event subscriber (`CustomAdminRedirect`) fires on every request and can redirect anonymous users to or from the admin domain. Three redirect strategies are supported, configurable via the settings form:

- **Upanup Admin** — uses the `name.admin.upanup.com` subdomain pattern. Requires an **Admin Name**.
- **Admin Domain** — uses the `admin.domain.com` subdomain pattern. Optionally accepts a **Custom Admin Name** to override the `admin` prefix (e.g. `custom.domain.com`).
- **Admin Subdomain** — uses the `name.admin.domain.com` subdomain pattern. Requires an **Admin Name** and optionally a **Custom Admin Name** to override the `admin` prefix.

The redirect is only applied to anonymous users and targets login/logout routes (`user.login`, `user.logout`). If the `upanup_auth` or `samlauth` modules are present, login/logout routes are also included.

### Admin Styling

Custom CSS libraries are conditionally attached to every page:

- **`ck5`** — Style overrides for the CKEditor 5 editor.
- **`admin-toolbar`** — Style overrides for the Admin Toolbar, loaded only for users with the `access toolbar` permission.

SCSS source files are included alongside the compiled CSS under `libraries/`.

### Node Edit Form Override

Overrides the core `node_edit_form` Twig template with a custom version located in `templates/`, allowing layout customisation of the node edit page.

### Paragraph Preview Template

Registers a custom theme suggestion (`paragraph__content_row__preview`) applied to any paragraph rendered in the `preview` view mode, with a corresponding template at `templates/paragraph--content-row--preview.html.twig`.

---

## Configuration

Navigate to **Admin > Configuration > User Interface > Upanup Admin** (`/admin/upanup_admin`).

| Setting | Description |
|---|---|
| Enable admin redirect | Toggles the redirect behaviour on or off. |
| Admin Method | Selects the redirect strategy: **Upanup Admin**, **Admin Domain**, or **Admin Subdomain**. |
| Admin Name | The subdomain name prefix used when Admin Method is set to **Upanup Admin** or **Admin Subdomain** (e.g. `name` in `name.admin.upanup.com`). |
| Custom Admin Name | An optional custom subdomain segment used with **Admin Domain** or **Admin Subdomain**. Overrides the default `admin` segment (e.g. enter `myadmin` to get `myadmin.domain.com` or `name.myadmin.domain.com`). Leave blank to use the default `admin`. |

Requires the `administer upanup_admin` permission.

---

## Dependencies

- [Admin Toolbar](https://www.drupal.org/project/admin_toolbar) (`admin_toolbar:admin_toolbar`)
- [CKEditor 5](https://www.drupal.org/project/ckeditor5) (`ckeditor5:ckeditor5`)

---

## Permissions

| Permission | Description |
|---|---|
| `administer upanup_admin` | Access and save the Upanup Admin settings form. |

---

## Notes

- Update `.htaccess` to redirect only root domains to `www`.
- Add admin subdomains to the Shield module allowlist domains configuration.
