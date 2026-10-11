# Security policy

## Supported code

Security maintenance covers the current `master` branch of
[peycho/aowow](https://github.com/peycho/aowow). Historical tags, older commits
and independently modified installations have no separate support guarantee.
Include your exact commit and application revision even when reporting an older
installation; maintainers can assess whether the current code is affected.

## Report privately

Use [GitHub private vulnerability reporting](https://github.com/peycho/aowow/security/advisories/new).
The repository's **Security and quality → Advisories → Report a vulnerability**
form is the sole vulnerability-reporting channel. Do not put vulnerability
reports or exploit details in public Issues, pull requests or Discussions.

If the reporting button is unavailable, do not post the details publicly.
The repository owner must enable private vulnerability reporting before the
channel is available; adding this file does not enable it.

Include:

- A short summary and the affected commit/application revision.
- Relevant PHP/database versions and configuration prerequisites, including
  authentication, roles and optional integrations required to reproduce it.
- Reproduction steps or a minimal proof of concept using synthetic data.
- Expected behavior, observed behavior and the impact on affected users.
- Sanitized evidence and any proposed fix or mitigation.

Remove credentials, private keys, session/recovery tokens, personal data and
production database dumps. Reports should establish the code behavior without
including unrelated information about a hosted installation. Test on systems
you own or are authorized to assess.

## Coordinated disclosure

Maintainers assess reports and discuss reproduction, affected code, mitigation
and fixes privately through GitHub. Coordinate public disclosure there so a
reviewed fix or mitigation can accompany an advisory when appropriate. Reporter
credit can be agreed in the private report. No response or resolution deadline
is promised.

The [public security review](docs/aowow-security-review.md) records historical
findings and local validation. It is separate from this reporting policy and
does not certify the security of a particular deployed site.
