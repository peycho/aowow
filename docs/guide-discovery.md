# Guide discovery

Guide lists provide localized topic introductions and links to categories that
contain public guides with an available approved article. Existing editorial
articles take precedence over the default introduction. Later list pages retain
topic navigation without repeating the introduction. Manual SEO descriptions
remain authoritative.

Database detail pages show up to six guides that link to that record. Public
guide pages show other guides with shared database references, ordered by the
number of shared references and then guide ID. These links are ordinary initial
HTML links and remain available without JavaScript. They use the guide's authored
language and existing canonical slug or numeric route.

Only references rendered as local database links by the safe article renderer
are indexed. Repeated references count once; escaped examples, code/preformatted
examples, external destinations and invalid IDs are excluded. No guide body is
scanned by ordinary detail-page requests.

## Publication and maintenance

The reference index does not grant access. Every lookup joins the current guide
publication state and approved article revision. Draft references remain hidden;
when a published guide has pending edits, its previous approved references remain
active and the related-link title uses the existing `Guide #ID` fallback. Archived
or missing guides disappear immediately, including from cached database pages.
Explicit guide revisions and unpublished previews do not show related-guide links.

Approval indexes the exact revision before making it public. A failed index write
leaves publication unchanged. Index replacements use a transaction so a failed
batch retains the previous reference set. The existing editor, moderation rights
and guide ownership continue to control publication.

Deploy the PHP, localization and templates together during normal maintenance,
then run the normal upgrade:

```sh
php aowow --update
```

Migration `1792742400_01.sql` creates `aowow_guide_references` and schedules the
`guidereferences` build to backfill existing public revisions. Fresh installations
include the same table and build task. Invalidate application page caches using
the normal deployment procedure so cached templates acquire the live lookup.
No generated JavaScript rebuild is required for this change.

After direct SQL imports or edits to approved article bodies, rebuild the index:

```sh
php aowow --build=guidereferences
```

This build processes guide IDs in batches of 100 and replaces each public
revision's index transactionally. It does not publish guides or modify their
article text. Old index entries cannot make an archived, missing or unapproved
revision discoverable because live lookups recheck the guide and article rows.

## Validation

The PHP, SQL and browser gates cover localized introductions, editorial/manual
override priority, safe link extraction, approval/rejection, pending revisions,
locale matching, bounded results, transaction rollback and withdrawal from a
serialized page cache. Browser fixtures check long titles and navigation at phone,
tablet and desktop widths. See the [test guide](../tests/README.md).

Repository checks and local acceptance do not establish deployed discovery or
search-engine indexing. Verify representative guide categories, database detail
pages and public guides after deployment, including scripts-disabled views.
