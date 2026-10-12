# Troubleshooting

Run CLI commands from the checkout root using the site's PHP CLI binary.
Start with the symptom below; the [installation guide](installation.md) and
[configuration guide](configuration.md) provide the expected setup.

- **Setup fails:** rerun with `--debug` and `--log=<file>`. Inspect generator errors
  and pending work; consult the [update recovery details](changelog.md#sql-update-accounting-revision-66)
  before retrying an interrupted SQL migration.
- **Styles are missing:** check the configured static URL and SSL settings using
  `php aowow --configure`.
- **JavaScript errors:** disable server/proxy features that automatically rewrite
  or minify JavaScript and CSS, then check the original assets.
- **Screenshot uploads fail or directories are exposed:** verify PHP upload limits
  and the server's access rules; see the
  [Apache access rules](installation.md#apache-access-rules).
- **Custom entities are missing from search:** check the active locale and the
  corresponding world-database locale tables.
- **Book images are missing:** preserve case when extracting MPQs. Unix web servers
  require the image path to match the extracted filename.
- **Profiler searches appear incomplete:** character requests are queued and
  processed by `prQueue`; searches use AoWoW's cached profiles, which may be outdated.

## Before requesting support

Record the exact commit/application revision, command or affected URL, PHP and
database versions, and steps to reproduce. Sanitize diagnostics before sharing:
remove credentials, tokens, personal data and raw database dumps. Public sites
should keep `DEBUG=0`; use CLI `--debug` and `--log=<file>` for setup diagnostics.

Check existing issues and follow the [support guide](../SUPPORT.md) if the
problem remains. Report vulnerabilities privately through the
[security policy](../SECURITY.md).

Return to the [documentation index](README.md).
