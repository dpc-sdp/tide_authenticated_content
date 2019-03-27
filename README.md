# Authenticated Content Module

* Exposes New Custom API Endpoints for register, login, reset and forgot
password.
* Adds "site" field to user to present the correct Front end login/reset URLs.
* Adds a module configurations option to set Backend users.
* Updates activation and reset password emails with the Frontend URLs of the
users site.
* Adds a "user_authentication_block" paragraph that presents a login form on the
Frontend which directs users to the
selected page.
* Adds a "Restricted Content" term vocabulary used as groups for protecting
content.

## Register
* Accepts standard user fields, name, email, password + customer fields
"field_"...
* Frontend users are assigned the site they register on.
* Site configuration **must** be set to "guest" or "guest with admin approval"
registrations, otherwise requests will be
rejected.

# Configuration

## Drupal Config

**Enable Authenticated Content Field on Landing Page**

`/admin/structure/types/manage/landing_page/form-display`
 - Drag up "Authenticated Content" to enable

**Add Authenticated Content Login Paragraph to Landing Page**

`admin/structure/types/manage/landing_page/fields/node.landing_page.field_landing_page_component`
 - Enable "Authenticated Content"

**Create and install private key**

```bash
openssl genrsa -out /tmp/private.key 2048 && cat /tmp/private.key
```
paste key here: `/admin/config/system/keys/add`


**Set key for JWT Issuer**

`/admin/config/system/jwt`

- Algorithm: RSASSA-PKCS1-v1_5 using SHA-256 (RS256)
- Key: <From Above>

## Configuration Options
The following config options exist and can be exported for your site:
backend_user_roles is a list of Drupal Roles that are considered "Backend"
roles. Users who use the password reset feature who are not in one of these
roles will have the URL in their reset email switched to the Front End url
defined on the site defined in the tide_site module for the current Drupal
installation.

    backend_user_roles: 
      - "administrator"
      - "editor"
      - "approver"

auto_apply_user_roles is similar to backend_user_roles. Roles defined in this
list will be automatically assigned to new users registered via the API.

    auto_apply_user_roles:
      - "member"

default_site_id is the default site ID to use if no other site is defined
against individual users. This works together with backend_user_roles to define
the Front End url to use on the outgoing password reset email.

    default_site_id: 1

block_be_user_registration is a boolean. If it's set to 1, the ability for users
to register via the Drupal interface will be blocked. This is to allow the site
Drupal settings to be set to Allow user registrations via the API, whilst
blocking registration for the CMS.

    block_be_user_registration: 1

protect_jsonapi_user_route is a boolean. Usees the jsonapi_user_route value to
protect specific json api routes.

    protect_jsonapi_user_route: 1

jsonapi_user_route is an array of strings. Add the routes that you need to
protect, eg `/api/v1/user/user` route will be protected from external access.

    jsonapi_user_route:
      - "/api/v1/user/user"


# Usage - Content Admin

**Add Term**

/admin/structure/taxonomy/manage/authenticated_content/add
 - Set Name
 - Set permissions

**Add Authenticated Content**

/node/add/landing_page
 - Title: ...
 - Protect Content: < name of term above >
 
Publish Page


**Add Authenticated Content - Login Page**

/node/add/landing_page
 - Title: ...
 - Protect Content: Leave Blank (leave it open to public)
 - Body
   - Add **Authenticated Content**
   - Set Next Page: < Authenticated Content Page Title >
   
Publish Page

# Usage (API Authentication)

## Register

POST: /api/v1/user/register

```json
{
    "mail": "jason+13@portable.com.au",
    "pass": "tester-13"
}
```

**Success status 200** 
```json
{
    "message": "User account requested"
}
```

**Failed status 400** 
```json
{
    "message": "User Registration Failed"
} 
```

**Error status 500** 
HTML/Text Error

## Login Request 

POST /api/v1/user/login?_format=json
```json
{
    "name": "user@example.com",
    "pass": "tester2"
}
```
**Status Code: 200**
```json
{
    "current_user": {
        "uid": "6105",
        "name": "<email>@<domain>"
    },
    "csrf_token": "pl-6J8a832zq2fP6IHNShBgeWrT0hmqoW7tfGUrCYEs",
    "logout_token": "4OlOTkjv-DHqzrc6amms1lZCMybNQjRODPlhH-YY8vE",
    "auth_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1Ni...cSsJ-i3j1EsHSKo6O_A"
}
```

**Failed Status: 400**

```json
{
    "message": "Sorry, unrecognized username or password."
}
```

## Password Reset (Request)

POST: /api/v1/user/request_reset

```json
{
    "mail": "user@example.com"
}
```
OR
```json
{
   "name": "username"
}
```

**Success: 200**
```json
{
    "message":"Forgot password email has been sent."
}
```
**Failed: 400**
```json
{
    "message":"Forgot reset failed."
}
```

## Password Reset

POST: /api/v1/user/reset_password

```json
{
    "id":6111,
    "time":1545219066,
    "hash":"IThqJHTa1ZqJbdLWRjKfPgeI9-wVlNtpkPgXf7Mx3qA",
    "pass":"a new password"
}
```

**Success: 200**
```json
{
    "message":"Forgot password email has been sent."
}
```
**Failed: 400**
```json
{
  "message":"Password Reset Failed",
}
```


# TODOs

- TODO: implement flood control
- TODO: respect site config for allowing user registrations
- TODO: Replace hard-coded link expiry
- TODO: remove @skipped once the module is extracted to its own repo.
- TODO: Replace hard-corded alpha.vic.gov.au domain with users site
- TODO: Only replace url for frontend users
- TODO: Replace backend login link
(http://content-vicgovau.docker.amazee.io/user) with the frontend-link the user 
    registered on (eg a custom landing page)
