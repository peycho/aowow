# Incorporating upstream AoWoW changes

Use this procedure to review and incorporate changes from
[Sarjuuk/aowow](https://github.com/Sarjuuk/aowow), the original project source.
Repository [agent instructions](../AGENTS.md) route requests such as
"check Sarjuuk changes" or "sync check with Sarjuuk" to the
[upstream-sync skill](skills/aowow-upstream-sync/SKILL.md).
A check follows steps 1 and 2 and reports candidate decisions without importing
changes. A request to incorporate or sync also follows the integration steps.

Run the commands from the checkout root. Examples integrate into `dev`; choose
the intended local base branch before starting. This is a source-code review
procedure; database updates and deployment are separate steps.

## 1. Prepare and fetch the current upstream history

Inspect `git status --short`, including untracked files. Checks can inspect
history without switching branches or changing application files. Integration
requires a clean checkout or isolated worktree; preserve unfinished work before
switching branches. Do not include unrelated files in an upstream-sync commit.

Inspect remotes, then add `upstream` once if it does not already exist:

```sh
git remote -v
git remote add upstream https://github.com/Sarjuuk/aowow.git
```

If `upstream` already exists, verify its URL instead of replacing it. Keep
`origin` pointing to the maintained fork. Fetching does not merge source changes:

```sh
git remote get-url upstream
git fetch --prune upstream
git rev-parse --is-shallow-repository
```

If the checkout is shallow, obtain the missing history with
`git fetch --unshallow upstream` before comparing ancestry. Upstream currently
uses `master`; if its default branch changes, use the new branch below.

Pin the fork base and exact upstream revision being reviewed without switching
branches:

```sh
AOWOW_SYNC_BASE=dev
AOWOW_SYNC_START="$(git rev-parse "$AOWOW_SYNC_BASE")"
AOWOW_SYNC_TIP="$(git rev-parse upstream/master)"
git show --no-patch --format=fuller "$AOWOW_SYNC_TIP"
```

Use a current local base branch, incorporating any outstanding fork changes
before starting. Retain these variables in the same shell throughout the review.
Record the full start and upstream SHAs; a cached GitHub page is not evidence of
the latest tip. Fetch again at the start of each new review.

## 2. Identify and review candidates

```sh
git log --reverse --topo-order --format='%h %ad %an %s' --date=short "${AOWOW_SYNC_START}..${AOWOW_SYNC_TIP}"
git cherry -v "$AOWOW_SYNC_START" "$AOWOW_SYNC_TIP"
git log "$AOWOW_SYNC_START" --fixed-strings --grep='cherry picked from commit' --format='%H%n%B'
git diff --stat "${AOWOW_SYNC_START}...${AOWOW_SYNC_TIP}"
```

The log lists commits absent by ancestry. In this argument order, `git cherry`
reports upstream commits: `-` means an equivalent patch is already present in the
fork, and `+` means no equivalent patch was found. This is a patch comparison,
not proof that a change is needed. Adapted cherry-picks can appear as `+` again;
check source-SHA trailers and previous review records too.
[Git's comparison rules](https://git-scm.com/docs/git-cherry) explain this distinction.

Read each candidate's complete diff and its dependencies, oldest first:

```sh
AOWOW_UPSTREAM_COMMIT=replace-with-full-reviewed-commit-sha
git show --format=fuller --stat "$AOWOW_UPSTREAM_COMMIT"
git show "$AOWOW_UPSTREAM_COMMIT"
```

Record a decision for every candidate: incorporate, already present, adapt or
defer, with a reason. Check prerequisite commits, schema migrations, dependencies,
generated assets and affected consumers. Upstream merges must also be reviewed;
they can contain conflict resolutions beyond the individual parent commits.

## 3. Choose an integration method that preserves credit

For an authorized import, create a review branch from the recorded fork base:

```sh
AOWOW_SYNC_BRANCH="upstream-sync/$(date +%Y%m%d-%H%M%S)"
git switch -c "$AOWOW_SYNC_BRANCH" "$AOWOW_SYNC_START"
```

### Selected commits: cherry-pick with provenance

Apply reviewed commits individually in dependency order:

```sh
git cherry-pick -x "$AOWOW_UPSTREAM_COMMIT"
git show --no-patch --format=fuller HEAD
git log -1 --format=%B
```

Cherry-picking preserves the original author and author date while creating a
new commit with the integrator as committer. `-x` adds the original commit SHA to
the message, linking back to its complete author/committer history. Preserve
existing attribution and `Co-authored-by` trailers. When the upstream committer
differs from its author, also retain that credit in an `Original-committer: Name
<email>` trailer using the upstream metadata; add it with `git commit --amend`
without `--reset-author`.

Verify the source-SHA trailer after every pick, particularly after conflicts;
add a missing `(cherry picked from commit FULL_SHA)` line with the same amend
command. [Git's cherry-pick documentation](https://git-scm.com/docs/git-cherry-pick)
describes `-x`, conflict recovery and merge-parent selection.

### Complete upstream range: normal merge

Choose this instead of cherry-picking when accepting the complete reviewed range,
or when the original committer fields and commit hashes must remain exact. On a
fresh integration branch created from `AOWOW_SYNC_START`, run:

```sh
git merge --no-ff --no-commit "$AOWOW_SYNC_TIP"
```

Review the staged merge, resolve conflicts and run validation before `git commit`.
This retains the upstream commit objects, including original author, committer,
dates and signatures; the new merge commit records the integrator. The result
must retain the fork's intended behavior.
[Git's merge documentation](https://git-scm.com/docs/git-merge) explains the history-preserving merge.

Do not squash imports into a replacement commit or impersonate the upstream
committer through Git identity overrides. Avoid copying files and committing
them as entirely new work. For selective imports of upstream merges, prefer
their ordinary constituent commits; use `cherry-pick -m` only after reviewing
both parents and explicitly choosing the correct mainline.

## 4. Resolve conflicts and preserve the fork's behavior

```sh
git status --short
git diff --name-only --diff-filter=U
```

Resolve each conflicting file by reviewing both implementations. Stage only the
resolved paths with `git add -- path/to/resolved-file`. For a cherry-pick, finish
with `git cherry-pick --continue`; to abandon the current pick, use
`git cherry-pick --abort`. For a merge, use `git merge --abort` to abandon it.
Inspect an empty cherry-pick before using `git cherry-pick --skip`, and record
why the change was already present or intentionally omitted.

Check shared serialization and rendering callers, setup/update accounting,
language handling, PHP 8.5 CI, access rules and the fork's existing map behavior.
Keep intentional local fixes while incorporating compatible upstream behavior.
Make additional adaptation fixes as separate commits under the integrator's
identity, referring to the upstream SHA and explaining the resulting behavior.

## 5. Review and validate

Review the complete branch diff, including conflict resolutions:

```sh
git diff --check
git diff --check "$AOWOW_SYNC_START"
git diff --stat "$AOWOW_SYNC_START"
git diff "$AOWOW_SYNC_START"
git log --format=fuller "${AOWOW_SYNC_START}..HEAD"
```

Use PHP 8.5 and the prerequisites in the [test guide](../tests/README.md).
Run each applicable check and stop on a failure:

```sh
bash tests/ci/run.sh lint
bash tests/ci/run.sh php
bash tests/ci/run.sh javascript
bash tests/ci/run.sh browser
bash tests/ci/run.sh sql
bash tests/ci/run.sh apache
```

SQL checks use disposable fixture databases, not application credentials.
For changes to shared code, run the full relevant groups and add focused
regressions for affected consumers. Check generated JavaScript execution and
localized labels when those paths change. Record passed checks and anything
not exercised; local fixtures do not establish deployment acceptance.

## 6. Record the review and integrate without squashing

Add a dated upstream-sync entry to [the changelog](changelog.md), or include the
same record in the integration PR:

```text
Reviewed on: YYYY-MM-DD
Source: https://github.com/Sarjuuk/aowow
Upstream branch and tip: master / FULL_SHA
Fork base branch and start: dev / FULL_SHA
Method: cherry-pick -x / normal merge
Commits: SOURCE_SHA -> RESULT_SHA; decision and reason
Adaptations/conflicts: affected behavior and resolution
Validation: commands and results; checks not run
Operational follow-up: migrations or asset builds required
```

Once the branch is ready for review and publication has been requested, push it
to the fork:

```sh
git push -u origin "$AOWOW_SYNC_BRANCH"
```

The repository's [CI workflow](../.github/workflows/security-tests.yml) runs on
affected pushes and tests PHP 8.5. Review its results, then integrate through a
normal merge that preserves the imported commits, rather than a squash or rebase
merge. This keeps authorship and provenance available in the final branch.

Keep source integration separate from applying `php aowow --update` or rebuilding
operator-provided assets on a deployed installation. Record the required follow-up
using the [existing update guidance](../README.md#9-apply-updates-with-verified-migration-accounting).
On the next sync, fetch again and compare against the newly integrated fork tip;
review records and cherry-pick trailers explain previously adapted or deferred
commits that patch-equivalence checks alone cannot classify.
