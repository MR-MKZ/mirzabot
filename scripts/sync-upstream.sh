#!/usr/bin/env bash
# Sync your customized fork with the upstream mirzabot source,
# and optionally mirror upstream GitHub Releases onto your fork
# using your customized branch (not vanilla upstream source).
#
# Code sync only (default — never publishes releases):
#   ./scripts/sync-upstream.sh
#   ./scripts/sync-upstream.sh --push
#
# Sync code + create any missing releases on your fork:
#   ./scripts/sync-upstream.sh --release
#   ./scripts/sync-upstream.sh --release --latest-only
#   ./scripts/sync-upstream.sh --release-only
#
# Env overrides:
#   UPSTREAM_REMOTE=upstream  ORIGIN_REMOTE=origin
#   CUSTOM_BRANCH=draft       MAIN_BRANCH=main
#   UPSTREAM_REPO=mahdiMGF2/mirzabot

set -euo pipefail

UPSTREAM_REMOTE="${UPSTREAM_REMOTE:-upstream}"
ORIGIN_REMOTE="${ORIGIN_REMOTE:-origin}"
CUSTOM_BRANCH="${CUSTOM_BRANCH:-draft}"
MAIN_BRANCH="${MAIN_BRANCH:-main}"
UPSTREAM_BRANCH="${UPSTREAM_BRANCH:-main}"
UPSTREAM_REPO="${UPSTREAM_REPO:-mahdiMGF2/mirzabot}"

DO_PUSH=0
DRY_RUN=0
PREPARE_RELEASE=0
SKIP_MAIN=0
DO_RELEASE=0
RELEASE_ONLY=0
LATEST_ONLY=0
RETARGET=0

RED=$'\033[31m'
GREEN=$'\033[32m'
YELLOW=$'\033[33m'
CYAN=$'\033[36m'
BOLD=$'\033[1m'
RESET=$'\033[0m'

info()  { printf '%s[INFO]%s %s\n' "$CYAN" "$RESET" "$*"; }
ok()    { printf '%s[OK]%s %s\n' "$GREEN" "$RESET" "$*"; }
warn()  { printf '%s[WARN]%s %s\n' "$YELLOW" "$RESET" "$*"; }
err()   { printf '%s[ERROR]%s %s\n' "$RED" "$RESET" "$*" >&2; }
die()   { err "$*"; exit 1; }

usage() {
    cat <<'EOF'
Sync customized fork with upstream, optionally mirroring releases.

Code sync (default — never publishes releases):
  ./scripts/sync-upstream.sh
  ./scripts/sync-upstream.sh --push
  ./scripts/sync-upstream.sh --dry-run

Release sync (creates missing GitHub releases on YOUR fork):
  ./scripts/sync-upstream.sh --release
  ./scripts/sync-upstream.sh --release --latest-only
  ./scripts/sync-upstream.sh --release-only

How release sync works:
  - Reads releases from the upstream GitHub repo
  - For each release missing on your fork, creates the same tag/title
  - GitHub tag is created at your CUSTOM branch HEAD (includes customizations)
  - Notes = upstream notes + custom-changes section
  - Existing fork releases are left alone (unless --retarget)

Options:
  -b, --branch NAME       Custom branch (default: draft)
  -m, --main NAME         Local main mirror branch (default: main)
      --upstream-branch   Upstream branch (default: main)
      --upstream-repo     Upstream GitHub repo (default: mahdiMGF2/mirzabot)
      --skip-main         Do not update local main
      --push              Push main + custom branch to origin after code sync
      --release           After code sync, mirror missing upstream releases
      --release-only      Only mirror releases (skip code merge)
      --latest-only       With --release: only sync the newest upstream release
      --retarget          Recreate existing fork releases onto current custom HEAD
      --prepare-release   Write RELEASE_NOTES_DRAFT.md (no publish)
      --dry-run           Show actions without changing git/GitHub
  -h, --help              Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        -b|--branch) CUSTOM_BRANCH="${2:?}"; shift 2 ;;
        -m|--main) MAIN_BRANCH="${2:?}"; shift 2 ;;
        --upstream-branch) UPSTREAM_BRANCH="${2:?}"; shift 2 ;;
        --upstream-repo) UPSTREAM_REPO="${2:?}"; shift 2 ;;
        --skip-main) SKIP_MAIN=1; shift ;;
        --push) DO_PUSH=1; shift ;;
        --release) DO_RELEASE=1; shift ;;
        --release-only) DO_RELEASE=1; RELEASE_ONLY=1; shift ;;
        --latest-only) LATEST_ONLY=1; shift ;;
        --retarget) RETARGET=1; shift ;;
        --prepare-release) PREPARE_RELEASE=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) die "Unknown option: $1 (use --help)" ;;
    esac
done

# Release sync needs the custom branch on origin so --target resolves.
if [[ "$DO_RELEASE" -eq 1 ]]; then
    DO_PUSH=1
fi

run() {
    if [[ "$DRY_RUN" -eq 1 ]]; then
        printf '%s[DRY-RUN]%s %s\n' "$YELLOW" "$RESET" "$*"
        return 0
    fi
    "$@"
}

require_clean_worktree() {
    if [[ -n "$(git status --porcelain)" ]]; then
        die "Working tree is dirty. Commit or stash your changes first."
    fi
}

remote_exists() {
    git remote get-url "$1" >/dev/null 2>&1
}

branch_exists_local() {
    git show-ref --verify --quiet "refs/heads/$1"
}

require_gh() {
    command -v gh >/dev/null 2>&1 || die "'gh' CLI is required for --release. Install: https://cli.github.com/"
    if [[ "$DRY_RUN" -eq 0 ]]; then
        gh auth status >/dev/null 2>&1 || die "'gh' is not authenticated. Run: gh auth login"
    fi
}

json_query() {
    # Prefer jq; fall back to go-jq via python if needed
    if command -v jq >/dev/null 2>&1; then
        jq "$@"
    else
        die "'jq' is required for --release. Install jq."
    fi
}

origin_repo_slug() {
    local url slug
    url="$(git remote get-url "$ORIGIN_REMOTE")"
    slug="$(printf '%s' "$url" | sed -E 's#(git@github\.com:|https://github\.com/)##; s#\.git$##')"
    [[ -n "$slug" ]] || die "Could not parse GitHub repo from remote '${ORIGIN_REMOTE}' (${url})"
    printf '%s' "$slug"
}

latest_upstream_tag() {
    local tag
    tag="$(git describe --tags --abbrev=0 "${UPSTREAM_REMOTE}/${UPSTREAM_BRANCH}" 2>/dev/null || true)"
    if [[ -z "$tag" ]]; then
        tag="$(git tag -l --sort=-v:refname | head -n1 || true)"
    fi
    printf '%s' "$tag"
}

custom_changes_block() {
    local base_ref="$1"
    local log=""
    # Prefer comparing to the upstream tag object if present; else upstream/main
    if git rev-parse -q --verify "refs/tags/${base_ref}" >/dev/null; then
        log="$(git log --oneline "${base_ref}..${CUSTOM_BRANCH}" 2>/dev/null | head -n 40 || true)"
    else
        log="$(git log --oneline "${UPSTREAM_REMOTE}/${UPSTREAM_BRANCH}..${CUSTOM_BRANCH}" 2>/dev/null | head -n 40 || true)"
    fi
    if [[ -z "$log" ]]; then
        printf '(no extra commits listed — tree still includes whatever is on %s)\n' "$CUSTOM_BRANCH"
    else
        printf '%s\n' "$log"
    fi
}

build_release_notes() {
    local tag="$1"
    local upstream_body="$2"
    local custom_sha="$3"
    local origin_slug="$4"

    cat <<EOF
## Customized fork release

This release matches upstream tag \`${tag}\`, published from branch \`${CUSTOM_BRANCH}\` (\`${custom_sha}\`) **including local customizations**.

- Upstream release: https://github.com/${UPSTREAM_REPO}/releases/tag/${tag}
- Fork commit: https://github.com/${origin_slug}/commit/${custom_sha}

### Custom commits (on \`${CUSTOM_BRANCH}\`)
\`\`\`
$(custom_changes_block "$tag")
\`\`\`

---

### Upstream release notes

${upstream_body:-_(no upstream release body)_}
EOF
}

list_upstream_release_tags() {
    local limit=100
    [[ "$LATEST_ONLY" -eq 1 ]] && limit=1
    gh release list -R "$UPSTREAM_REPO" --limit "$limit" --json tagName,createdAt \
        | json_query -r 'sort_by(.createdAt) | reverse | .[].tagName'
}

fork_has_release() {
    local tag="$1" origin_slug="$2"
    gh release view "$tag" -R "$origin_slug" >/dev/null 2>&1
}

delete_fork_release_and_tag() {
    local tag="$1" origin_slug="$2"
    if fork_has_release "$tag" "$origin_slug"; then
        warn "Deleting existing fork release ${tag}..."
        run gh release delete "$tag" -R "$origin_slug" --yes --cleanup-tag
    else
        # Tag may exist without a release
        if [[ "$DRY_RUN" -eq 1 ]]; then
            info "Would delete remote tag ${tag} if present"
        else
            git push "$ORIGIN_REMOTE" ":refs/tags/${tag}" 2>/dev/null || true
        fi
    fi
}

create_or_sync_release() {
    local tag="$1"
    local origin_slug="$2"
    local custom_sha="$3"
    local upstream_body name is_prerelease notes_file
    local create_args=()

    info "── Release ${tag} ──"

    if fork_has_release "$tag" "$origin_slug"; then
        if [[ "$RETARGET" -eq 0 ]]; then
            ok "Already on fork: ${tag}"
            return 0
        fi
        delete_fork_release_and_tag "$tag" "$origin_slug"
    fi

    upstream_body="$(gh release view "$tag" -R "$UPSTREAM_REPO" --json body -q .body 2>/dev/null || true)"
    name="$(gh release view "$tag" -R "$UPSTREAM_REPO" --json name -q .name 2>/dev/null || printf '%s' "$tag")"
    is_prerelease="$(gh release view "$tag" -R "$UPSTREAM_REPO" --json isPrerelease -q .isPrerelease 2>/dev/null || echo false)"

    if [[ "$DRY_RUN" -eq 1 ]]; then
        info "Would create GitHub release ${tag} on ${origin_slug}"
        info "  title=${name} target=${custom_sha:0:7} (${CUSTOM_BRANCH}) prerelease=${is_prerelease}"
        return 0
    fi

    notes_file="$(mktemp)"
    build_release_notes "$tag" "$upstream_body" "$custom_sha" "$origin_slug" >"$notes_file"

    # Create tag ON GITHUB at the customized commit (do not push local upstream tags).
    create_args=(
        release create "$tag"
        -R "$origin_slug"
        --title "$name"
        --notes-file "$notes_file"
        --target "$custom_sha"
    )
    if [[ "$is_prerelease" == "true" ]]; then
        create_args+=(--prerelease)
    fi

    gh "${create_args[@]}"
    rm -f "$notes_file"
    ok "Published fork release ${tag} → ${custom_sha:0:7} (customized)"
}

sync_releases() {
    local origin_slug custom_sha tag count=0 created=0 skipped=0

    require_gh
    command -v jq >/dev/null 2>&1 || die "'jq' is required for --release."

    origin_slug="$(origin_repo_slug)"
    custom_sha="$(git rev-parse "${CUSTOM_BRANCH}")"

    echo
    info "Release sync: upstream=${UPSTREAM_REPO} → fork=${origin_slug}"
    info "New releases target ${CUSTOM_BRANCH} @ ${custom_sha:0:7} (includes your customizations)"
    if [[ "$LATEST_ONLY" -eq 1 ]]; then
        info "Mode: latest upstream release only"
    else
        info "Mode: all upstream releases (create any missing on fork)"
    fi
    echo

    while IFS= read -r tag; do
        [[ -n "$tag" ]] || continue
        count=$((count + 1))

        if fork_has_release "$tag" "$origin_slug" && [[ "$RETARGET" -eq 0 ]]; then
            ok "Already on fork: ${tag}"
            skipped=$((skipped + 1))
            continue
        fi

        create_or_sync_release "$tag" "$origin_slug" "$custom_sha"
        if fork_has_release "$tag" "$origin_slug" || [[ "$DRY_RUN" -eq 1 ]]; then
            # dry-run counts as would-create; real run verify loosely
            if [[ "$DRY_RUN" -eq 1 ]]; then
                created=$((created + 1))
            elif fork_has_release "$tag" "$origin_slug"; then
                created=$((created + 1))
            fi
        fi
    done < <(list_upstream_release_tags)

    echo
    ok "Release sync done. checked=${count} created/updated=${created} skipped_existing=${skipped}"
    warn "Backfilled releases all point at current ${CUSTOM_BRANCH} HEAD so packages include your customizations (not a rebuild of each historical upstream tree)."
}

prepare_release_notes() {
    local tag="$1"
    local outfile="RELEASE_NOTES_DRAFT.md"
    local based_on="${tag:-unknown}"
    local date_str
    date_str="$(date -u +%Y-%m-%d)"

    if [[ "$DRY_RUN" -eq 1 ]]; then
        info "Would write ${outfile}"
        return 0
    fi

    cat >"$outfile" <<EOF
# Release draft (${date_str})

Based on upstream: \`${based_on}\`
Custom branch: \`${CUSTOM_BRANCH}\` @ \`$(git rev-parse --short HEAD)\`

## Changes vs upstream
\`\`\`
$(git log --oneline "${UPSTREAM_REMOTE}/${UPSTREAM_BRANCH}..HEAD" 2>/dev/null | head -n 50 || echo "(none)")
\`\`\`
EOF
    ok "Wrote ${outfile} (not published)"
}

sync_code() {
    local UPSTREAM_REF COMMITS_TO_MERGE merge_status

    UPSTREAM_REF="${UPSTREAM_REMOTE}/${UPSTREAM_BRANCH}"
    git rev-parse --verify "$UPSTREAM_REF" >/dev/null 2>&1 \
        || die "Missing ${UPSTREAM_REF} after fetch."

    info "Current HEAD vs ${UPSTREAM_REF}: behind=$(git rev-list --count "HEAD..${UPSTREAM_REF}" 2>/dev/null || echo 0), ahead=$(git rev-list --count "${UPSTREAM_REF}..HEAD" 2>/dev/null || echo 0)"

    if [[ "$SKIP_MAIN" -eq 0 ]]; then
        if ! branch_exists_local "$MAIN_BRANCH"; then
            info "Creating local ${MAIN_BRANCH} from ${UPSTREAM_REF}"
            run git branch "$MAIN_BRANCH" "$UPSTREAM_REF"
        fi

        info "Updating ${MAIN_BRANCH} to match ${UPSTREAM_REF}..."
        run git checkout "$MAIN_BRANCH"
        if [[ "$DRY_RUN" -eq 0 ]]; then
            if git merge-base --is-ancestor "$MAIN_BRANCH" "$UPSTREAM_REF"; then
                git merge --ff-only "$UPSTREAM_REF"
                ok "${MAIN_BRANCH} fast-forwarded to ${UPSTREAM_REF}"
            elif git merge-base --is-ancestor "$UPSTREAM_REF" "$MAIN_BRANCH"; then
                warn "${MAIN_BRANCH} is ahead of upstream; leaving it as-is."
            else
                warn "${MAIN_BRANCH} diverged — resetting to ${UPSTREAM_REF} (custom work belongs on ${CUSTOM_BRANCH})."
                git reset --hard "$UPSTREAM_REF"
                ok "${MAIN_BRANCH} reset to ${UPSTREAM_REF}"
            fi
        else
            info "Would checkout ${MAIN_BRANCH} and fast-forward/reset to ${UPSTREAM_REF}"
        fi
    fi

    if ! branch_exists_local "$CUSTOM_BRANCH"; then
        info "Creating custom branch ${CUSTOM_BRANCH} from ${MAIN_BRANCH}"
        run git checkout -b "$CUSTOM_BRANCH" "$MAIN_BRANCH"
    else
        run git checkout "$CUSTOM_BRANCH"
    fi

    COMMITS_TO_MERGE="$(git rev-list --count "HEAD..${UPSTREAM_REF}" 2>/dev/null || echo 0)"
    if [[ "$COMMITS_TO_MERGE" -eq 0 ]]; then
        ok "Custom branch '${CUSTOM_BRANCH}' already contains ${UPSTREAM_REF}."
    else
        info "Merging ${UPSTREAM_REF} into ${CUSTOM_BRANCH} (${COMMITS_TO_MERGE} commit(s))..."
        if [[ "$DRY_RUN" -eq 1 ]]; then
            info "Would run: git merge --no-edit ${UPSTREAM_REF}"
        else
            set +e
            git merge --no-edit "$UPSTREAM_REF"
            merge_status=$?
            set -e
            if [[ "$merge_status" -ne 0 ]]; then
                err "Merge conflict while syncing upstream into '${CUSTOM_BRANCH}'."
                echo
                warn "Resolve conflicts, then:"
                echo "  git add -A && git commit"
                echo "  ./scripts/sync-upstream.sh --release"
                echo
                warn "To abort: git merge --abort"
                exit 1
            fi
            ok "Merged ${UPSTREAM_REF} into ${CUSTOM_BRANCH}"
        fi
    fi
}

# ── Main ─────────────────────────────────────────────────────

cd "$(git rev-parse --show-toplevel)"

[[ "$DRY_RUN" -eq 1 ]] && warn "Dry-run mode: no git/GitHub changes will be made."

remote_exists "$UPSTREAM_REMOTE" || die "Remote '${UPSTREAM_REMOTE}' not found. Add it with:
  git remote add upstream git@github.com:mahdiMGF2/mirzabot.git"

remote_exists "$ORIGIN_REMOTE" || die "Remote '${ORIGIN_REMOTE}' not found."

if [[ "$DRY_RUN" -eq 0 ]]; then
    require_clean_worktree
fi

START_BRANCH="$(git branch --show-current)"
info "Starting on branch: ${START_BRANCH}"

info "Fetching ${UPSTREAM_REMOTE} (branches + tags)..."
run git fetch "$UPSTREAM_REMOTE" --tags --prune

if [[ "$RELEASE_ONLY" -eq 0 ]]; then
    sync_code
else
    info "Release-only mode: skipping code merge"
    run git checkout "$CUSTOM_BRANCH"
fi

LATEST_TAG="$(latest_upstream_tag)"
info "Latest upstream tag: ${BOLD}${LATEST_TAG:-'(none)'}${RESET}"

if [[ "$PREPARE_RELEASE" -eq 1 ]]; then
    prepare_release_notes "$LATEST_TAG"
fi

if [[ "$DO_PUSH" -eq 1 ]]; then
    info "Pushing to ${ORIGIN_REMOTE}..."
    if [[ "$SKIP_MAIN" -eq 0 && "$RELEASE_ONLY" -eq 0 ]]; then
        run git push "$ORIGIN_REMOTE" "$MAIN_BRANCH"
    fi
    run git push "$ORIGIN_REMOTE" "$CUSTOM_BRANCH"
    ok "Pushed to ${ORIGIN_REMOTE}"
elif [[ "$DO_RELEASE" -eq 0 ]]; then
    info "Not pushing (pass --push to update ${ORIGIN_REMOTE})."
fi

if [[ "$DO_RELEASE" -eq 1 ]]; then
    sync_releases
else
    echo
    info "No releases published. To mirror upstream releases onto your customized fork:"
    echo "  ./scripts/sync-upstream.sh --release"
    echo "  ./scripts/sync-upstream.sh --release --latest-only"
fi

if [[ "$DRY_RUN" -eq 0 ]]; then
    git checkout "$CUSTOM_BRANCH" >/dev/null 2>&1 || true
fi

echo
if [[ "$DO_RELEASE" -eq 1 ]]; then
    ok "Finished on '${CUSTOM_BRANCH}' (code + release sync)."
else
    ok "Finished on '${CUSTOM_BRANCH}' (code sync only — no releases)."
fi
