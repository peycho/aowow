# Configuration

Complete the [installation and update steps](installation.md) before enabling
optional features. Keep private values in deployment-owned `config/security.php`
and `config/config.php`; use Site Configuration for the database settings
listed below. Browser configuration requires the
[operator access policy](installation.md#11-restrict-diagnosticsconfiguration-and-deploy-the-legacy-deny-policy).
CLI configuration remains available through `php aowow --configure`.

- [Mail delivery](#mail-delivery)
- [Linked header image](#linked-header-image)
- [Site sounds](#site-sounds)
- [Cloudflare Turnstile](#cloudflare-turnstile)
- [General feedback](#general-feedback)
- [Missing screenshots](#missing-screenshots)
- [Search goodies](#search-goodies)
- [Profiler](#profiler)
- [External navigation](#external-navigation)
- [Tooltips and widgets](#tooltips-and-widgets)

## Mail delivery

Optional SMTP delivery is configured privately through `AOWOW_MAIL` in
`config/security.php`; host, port, username, password, encryption, sender and
timeouts are supported. Install the locked dependency with `composer install`.
PHP mail remains the default. See the [SMTP/Gmail configuration example](changelog.md#smtp-mail).

## Linked header image

The optional 468×60 linked header image appears on pages other than the homepage.
Run `php aowow --update`, then set `HEADER_IMAGE_URL` and `HEADER_IMAGE_LINK`
under Site Configuration → Site and enable `HEADER_IMAGE_ENABLE` (default Disabled).
Both URLs must be absolute HTTP/HTTPS URLs; the destination opens in a new tab.
Changes need no asset rebuild. See the [header image details](changelog.md#optional-header-image).

## Site sounds

`SOUNDS_ENABLE` under Site Configuration → Site controls sound menus and URLs,
sound search results, related tabs and playback. It defaults to Enabled. Install
it with `php aowow --update`; disable it for installations without extracted
audio. Changes take effect on subsequent requests without rebuilding assets.
NPC dialogue text remains available, and the setting does not change extraction
or setup generators. See the [sound configuration details](changelog.md#optional-site-sounds).

## Cloudflare Turnstile

Optional Cloudflare Turnstile protects registration, login, password/username
recovery, activation email resend and general feedback. All six switches default
to Disabled. Install them with `php aowow --update`, then configure the widget
keys in the existing private `config/security.php`:

```php
define('AOWOW_TURNSTILE_SITE_KEY', 'YOUR_SITE_KEY');
define('AOWOW_TURNSTILE_SECRET_KEY', 'YOUR_SECRET_KEY');
```

Authorize the hostname from `HOST_URL` in the Cloudflare widget (without a scheme,
path or port), using separate widgets/keys for testing and production. Keep the
secret out of database settings and version control. PHP needs cURL with
asynchronous DNS, outbound HTTPS access to `challenges.cloudflare.com` and a
working CA trust store; browsers need
access to the Turnstile script and frames. If the host sets a Content Security
Policy, allow that origin in `script-src` and `frame-src` as described in
[Cloudflare's CSP guidance](https://developers.cloudflare.com/turnstile/reference/content-security-policy/).

Enable the independent switches under Site Configuration:

| Setting | Category | Protected submission |
| --- | --- | --- |
| `turnstile_registration_enable` | Account | Registration |
| `turnstile_login_enable` | Account | Login |
| `turnstile_password_recovery_enable` | Account | Password recovery email |
| `turnstile_username_recovery_enable` | Account | Username recovery email |
| `turnstile_resend_enable` | Account | Activation email resend |
| `turnstile_feedback_enable` | Site | General feedback, when Feedback is enabled |

The update installs feedback token handling with one `globaljs` build. Later
toggles need no rebuild. Enabled protection rejects missing/invalid keys,
verification failures and provider timeouts with a retry message; it never
silently bypasses verification or enables maintenance. Only protected form
submissions call Siteverify, with a five-second maximum timeout. Widgets load
when a protected form is shown; ordinary pages do not call Siteverify. Existing
attempt limits, CSRF, activation/recovery links, comments, uploads and content
reporting retain their behavior. See the
[Turnstile integration details](changelog.md#cloudflare-turnstile).

## General feedback

`feedback_enable` under Site Configuration → Site controls the Feedback link,
general contact form and general submissions to `?contactus`. It defaults to
Enabled. When disabled, general requests return HTTP 404; reports about comments,
screenshots, videos and other content remain available. Install the setting and
client guards with `php aowow --update`; subsequent toggles need no rebuild or
additional database queries. Authored email links remain available. See the
[feedback switch details](changelog.md#feedback-switch).
Anonymous and signed-in feedback share independent IP/site request quotas and a
permanent retained-storage cap, whether its CAPTCHA is enabled or disabled.
Existing feedback is preserved and included in the initial storage baseline;
keep permanent budget rows when pruning. See
[feedback limits and rollout](changelog.md#general-feedback-budgets-revision-72).

## Missing screenshots

`missing_screenshots_enable` under Site Configuration → Site defaults to Disabled.
It hides Tools → Utilities → Missing Screenshots and rejects `?missing-screenshots`
before generating the listing. Install the setting with `php aowow --update`;
toggling it needs no rebuild. See the
[missing screenshots switch](changelog.md#missing-screenshots-switch).

## Search goodies

The independent `searchplugins_enable` and `searchbox_enable` settings under
Site Configuration → Site control the corresponding Goodies menu entries and
page URLs. Both default to Enabled. Apply new settings to an existing installation
with `php aowow --update`; toggling them needs no asset rebuild. See the
[goodies switch details](changelog.md#goodies-switches).

## Profiler

The database setting `profiler_enable` controls profiler URLs and navigation.
Set it to Disabled in Site Configuration → Profiler to hide Characters, Guilds,
Arena Teams, profile creation and profiler help, and reject their direct URLs.
The checks use configuration already loaded for the request; they add no database
queries or character-database availability checks. See the
[profiler switch details](changelog.md#profiler-switch-and-navigation).

## External navigation

Configure Community-menu links and the homepage GitHub link through
`$AoWoWconf['externalLinks']` in `config/config.php`. Entries support an `enabled`
boolean and an absolute HTTP/HTTPS `url`; disabled, empty or invalid entries are
hidden. See the [complete configuration example](changelog.md#external-navigation-configuration).

## Tooltips and widgets

Local tooltips and widgets remain supported. The Flash model viewer is retired.
See the [widget example and upgrade notes](changelog.md#wowhead-integration-and-viewer-retirement-revision-69).


See [troubleshooting](troubleshooting.md) for common problems or return to the
[documentation index](README.md).
