---
name: aowow-upstream-sync
description: Check or incorporate changes from Sarjuuk/aowow into this AoWoW fork. Use when asked for a Sarjuuk sync check, upstream review, or upstream integration, preserving original contributor credit and intentional fork behavior.
---

# Sarjuuk upstream synchronization

Read [the command procedure](../../upstream-sync.md) before starting.
Use its commands and review record format; this skill defines how to execute
the user's request. The source is https://github.com/Sarjuuk/aowow.

## Determine the requested scope

- **Check/review:** fetch and inspect current upstream history, then report
  candidate commits and recommendations. Leave application files, branches and
  commits unchanged. Do not import changes merely because they are newer.
- **Apply/incorporate/sync:** perform the review, integrate compatible changes
  locally on a separate branch, resolve conflicts, validate and report results.
  Honor any narrower commit selection or integration method the user specified.

Use existing session authorization. Do not ask again for routine fetches,
reviews, conflict fixes or local integration already requested. A request to
create or change this procedure does not itself request an upstream sync.

## Fetch and establish the comparison

1. Inspect repository instructions, status, current branch, remotes and recent
   fork history. Use the user's intended base, or the current branch when none
   is specified; record its full SHA. Preserve unrelated and untracked work.
2. Verify the Sarjuuk remote URL and current default branch. Fetch fresh history
   for every check and pin its full tip SHA. Keep the fork's `origin` unchanged.
   Do not use cached GitHub listings as proof of the latest revision.
3. If permissions, unfinished work or a shallow checkout prevent a reliable
   comparison, use an isolated checkout under `/tmp` when possible. Do not
   silently stash, reset or delete user work. If fetching is unavailable, report
   the limitation and label any analysis of existing refs as potentially stale.
4. Compare ancestry, patch equivalence, source-SHA cherry-pick trailers and
   previous sync records. Inspect all candidate diffs in dependency order,
   including merge resolutions; a `git cherry` plus sign alone is insufficient.

## Review compatibility and report the check

Record each candidate as incorporate, already present, adapt or defer, with
its source SHA, subject and reason. Inspect prerequisites, migrations,
dependencies, generated assets and affected callers. Check relevant fork
changes in history and the changelog, particularly serialization/rendering,
setup/update accounting, locale handling, supplemented Wrath-format maps,
access rules and PHP 8.5 checks.

For a check-only request, stop after reporting the fork base and fetched
upstream tip, candidate decisions, conflict/compatibility findings, and the
recommended method and validation. When there are no new changes, report that
with the compared revisions. Do not create a changelog entry for an unapplied
check. Distinguish inspection from executed tests and deployed verification.

## Integrate when requested

1. Start an integration branch from the recorded fork base in a clean checkout
   or isolated worktree. Review every imported change; preserve intentional
   fork behavior rather than choosing a conflict side wholesale.
2. For selected commits, use `git cherry-pick -x` in dependency order. Preserve
   original authors, author dates and coauthor trailers, verify source-SHA
   provenance after conflicts, and retain differing original committer credit
   as described in the command procedure. Do not override Git identity to
   impersonate contributors or reset authorship.
3. Use a normal merge when accepting the complete reviewed range or preserving
   exact original committer fields and commit objects. Do not squash imports.
   Put additional adaptation fixes in separate integrator-authored commits.
4. Review the complete result and run applicable repository checks on PHP 8.5
   using [the test guide](../../../tests/README.md). For shared utility or
   rendering changes, run the full relevant test groups and add focused
   regressions for affected consumers. Record failures and checks not run.
5. Record source-to-result SHAs, decisions, adaptations, validation and required
   operational follow-up in the changelog or authorized integration PR. Report
   the branch and results to the user. Do not claim completion with unresolved
   conflicts or failing applicable checks.

Push, PR publication, merging into the maintained branch, database updates,
asset regeneration on a deployed installation and deployment are separate
actions: perform them only when authorized by the user's request or session.
