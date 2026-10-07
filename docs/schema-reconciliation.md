# Historical schema reconciliation

Migration `1791331200_01.sql` brings historical application table definitions
into agreement with `setup/sql/01-db_structure.sql`. It uses the existing
`--update` command, locks, maintenance handling and migration journal.

The migration explicitly modifies 526 columns in 52 tables. It fixes defaults,
nullability, numeric types/ranges, string widths and legacy enum representations.
It also supplies the ordinary Chinese spell-name index when missing. It does
not drop tables, delete rows, rewrite account IDs or regenerate community content.

The initial schema now retains the historical `MEDIUMTEXT` capacity in 112
columns, including screenshot/video captions, and the 22-character Chinese
spell-rank width. This preserves existing long text and gives fresh installs
the same definitions. The account-description literal default uses syntax
accepted natively by MySQL and MariaDB. Fresh version metadata includes this
migration; older migration files and their checksums are unchanged.
The two declined-word string columns retain NULL support, matching the actual
generator's direct DBC copies and the extraction reader's empty-string behavior.

## Rehearse on a restored copy

Use an isolated application database restored from production and a copy of
the associated uploads. Keep the original backup unchanged. Provide the normal
update privileges, compatible world database, extracted inputs and writable
generated-output directories described in the [legacy upgrade prerequisites](changelog.md#legacy-database-upgrades).
PHP 8.5 is used for the regression suites.

From the checkout configured for that restored copy:

```sh
php8.5 aowow --update --datasrc=/path/to/wrath-extraction/ --debug --log=/path/to/upgrade.log
php8.5 aowow --validate-schema --debug --log=/path/to/schema-validation.log
```

The updater checks every affected table before the reconciliation file starts.
The checks run under the existing update lease and maintenance handling:

- Numeric values must fit the target range and signedness.
- Fields becoming required must contain no NULL values.
- Strings must fit their target character/byte capacity.
- Legacy numeric enum codes must be valid ordinals. The exclusion modes,
  audio formats and taxi-node types retain the meaning of codes 1 and 2.
- Existing enum labels must match the reviewed ordering and meaning. Rating
  labels are normalized to the spelling in the initial schema.
- Unexpected engines, character sets, collations, generated expressions,
  auto-increment changes and incompatible existing spell indexes are refused.

Diagnostics identify the affected table/column and reason, without printing
records, SQL or credentials. Failed data checks apply no statements from this
migration and create no running journal entry for it. Maintenance remains on;
earlier successful migrations remain recorded. Review the identified data on
the restored copy and preserve legitimate values when deciding how to resolve
the mismatch. After resolving a preflight failure, use the same update command.

During the ALTER statements, strict SQL mode prevents silent coercion if data
changes after preflight. The previous connection mode is restored afterward.
All affected definitions and the spell index are verified before the version
advances. A failure after SQL starts retains the incomplete journal and the
existing refusal to blindly replay partially applied SQL. Restore a consistent
copy or reconcile the journaled partial work before retrying.

After success, compare account/content counts and representative records,
ownership and parent links, favorites, profiles, permissions, password hashes
and upload references with the source. Verify login, content display and upload
files. Check the applied checksum, empty pending SQL/build tasks and schema
validation result before accepting the rehearsal.

## Regression coverage and limits

`tests/schema-reconciliation.php` imports a sanitized structure-only historical
fixture and synthetic community/game rows into a guarded disposable database.
It exercises the real `--update` entrypoint, exact agreement of all 108 reference
table definitions, fresh installation, long legacy text/captions, all four enum
conversions, numeric boundaries, NULL/enum/range/length rejection, concurrency,
maintenance restoration/retention and incomplete-journal replay refusal.
The declined-word coverage executes the actual generator INSERT statements with
NULL and Unicode strings under strict SQL mode.

Validation on 2026-10-07 passed the PHP and syntax-check groups with PHP 8.5.10,
and the complete SQL group on MySQL 8.0.46, MySQL 8.4.10 and MariaDB 10.6.28.
Each database run passed 47 reconciliation checks and 936 legacy-upgrade checks,
including all 53 applicable archived and 91 current migrations. Both the supplied
historical structure fixture and the complete legacy corpus finished with zero
differences across the 108 reference application tables.

The complete legacy regression also checks schema agreement after every archived
and current migration. The current reconciliation file contains the reviewed
definitions; it does not infer future repairs dynamically from a mutable SQL
dump. Future schema changes still need a migration and matching initial SQL.

Fixtures establish behavior on supported database engines. They do not establish
that actual production rows pass preflight or that a production upgrade has
completed. DBC-copy tables absent from the initial SQL remain separately
identified by the validator; their structures are not compared by that command.
