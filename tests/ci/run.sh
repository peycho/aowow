#!/usr/bin/env bash
# Run explicit suite entrypoints; fixture helpers must never be executed as standalone tests.
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."

case "${1:-}" in
  lint)
    find includes endpoints localization template setup tests -type f -name '*.php' -print0 |
      xargs -0 -n 1 php -l
    php -l setup/security.php.example
    php -l index.php
    php -l aowow
    php -l prQueue
    while IFS= read -r -d '' script; do
      node --check "$script"
    done < <(find static tests -type f \( -name '*.js' -o -name '*.mjs' \) -print0)
    bash -n tests/ci/run.sh
    python3 - <<'PY'
import ast
from pathlib import Path
for path in Path('tests').rglob('*.py'):
    ast.parse(path.read_text(), filename=str(path))
PY
    ;;
  php)
    # Check before loading the cache fixture, which deliberately supplies a global Memcached stand-in.
    php -r '
      if (class_exists("Memcached", false)) {
          fwrite(STDERR, "Cache fixture requires the native memcached extension disabled; use an isolated test PHP configuration.\n");
          exit(1);
      }
    '
    for suite in json talentcalc uitext guide-editor tokens error-log client-ip private-uploads guide-uploads \
                 expressions cache builds video turnstile retention community-pages redirects admin-boundary item-tabs; do
      php "tests/security-$suite.php"
    done
    php tests/security-csrf.php --http
    php tests/site-sounds.php
    php tests/site-sounds.php --cli
    php tests/setup-sounds.php
    php tests/setup-debug.php
    php tests/schema-validator.php
    php tests/maintenance-response.php
    php tests/setup-maps.php
    php tests/maps-picker.php
    php tests/external-links.php
    php tests/header-image.php
    php tests/retirement.php
    ;;
  javascript)
    node tests/security-turnstile.mjs
    node tests/site-sounds.mjs
    node tests/external-links.mjs
    php tests/retirement.php --fixtures | node tests/retirement.mjs
    php tests/security-json.php --fixtures | node tests/security-json.mjs
    php tests/security-talentcalc.php --fixtures | node tests/security-talentcalc.mjs
    php tests/security-item-tabs.php --fixtures | node tests/security-item-tabs.mjs
    php tests/setup-maps.php --fixtures | node tests/setup-maps.mjs
    php tests/maps-picker.php --fixtures | node tests/maps-picker.mjs
    node tests/security-private-uploads.mjs
    node tests/security-guide-uploads.mjs
    node tests/security-password-policy.mjs
    node tests/security-community.mjs
    php tests/security-community-pages.php --fixtures | node tests/security-community.mjs --pages
    php tests/security-redirects.php --fixtures | node tests/security-redirects.mjs
    ;;
  browser)
    # Standalone generated fixtures use the hosted runner's Chrome; PASS must come from executed DOM.
    browser="${AOWOW_TEST_CHROME:-google-chrome}"
    command -v "$browser" >/dev/null
    fixture_root="$(mktemp -d /tmp/aowow-security-browser-XXXXXX)"
    trap 'rm -rf -- "$fixture_root"' EXIT
    php tests/security-json.php --fixtures | node tests/security-json.mjs --browser > "$fixture_root/json.html"
    php tests/security-guide-editor.php --browser > "$fixture_root/guide-editor.html"
    php tests/security-csrf.php --browser > "$fixture_root/csrf.html"
    node tests/security-turnstile.mjs --browser > "$fixture_root/turnstile.html"
    php tests/header-image.php --browser > "$fixture_root/header-image.html"
    for fixture in json guide-editor csrf turnstile header-image; do
      timeout 45s "$browser" --headless --no-sandbox --disable-gpu --disable-dev-shm-usage \
        --disable-background-networking \
        --dump-dom "file://$fixture_root/$fixture.html" > "$fixture_root/$fixture.dom"
      python3 tests/ci/check-browser.py "$fixture_root/$fixture.dom"
    done
    php tests/retirement.php --fixtures > "$fixture_root/retirement-assets.json"
    node tests/retirement.mjs --browser < "$fixture_root/retirement-assets.json" > "$fixture_root/retirement.html"
    python3 tests/retirement-browser.py "$fixture_root/retirement.html" "$fixture_root/retirement-assets.json" "$browser"
    ;;
  sql)
    # These exact disposable databases are destructive fixtures; never load application credentials.
    export AOWOW_TEST_DB_HOST="${AOWOW_TEST_DB_HOST:-127.0.0.1}"
    export AOWOW_TEST_DB_PORT="${AOWOW_TEST_DB_PORT:-3306}"
    case "$AOWOW_TEST_DB_HOST" in
      127.0.0.1) ;;
      *) echo 'SQL CI requires an isolated MySQL fixture on 127.0.0.1.' >&2; exit 1 ;;
    esac
    # shellcheck disable=SC2016 # PHP variables must be passed literally.
    php -r '
      $db = new mysqli(getenv("AOWOW_TEST_DB_HOST"), "root", "", "", (int)getenv("AOWOW_TEST_DB_PORT"));
      foreach (["passwords", "screenshots", "updates", "resources", "legacy", "schema", "reconciliation"] as $suffix) {
          $name = "aowow_security_test_".$suffix;
          $db->query("CREATE DATABASE IF NOT EXISTS ".$name." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
          // The historical fixture has this collation; normalize reused fixture databases too.
          $db->query("ALTER DATABASE ".$name." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      }
    '
    # Recovery, activation and policy suites share tables and must remain sequential.
    for suite in password-recovery activation password-policy; do
      AOWOW_TEST_DATABASE=aowow_security_test_passwords php "tests/security-$suite.php"
    done
    AOWOW_TEST_DATABASE=aowow_security_test_screenshots php tests/security-screenshot-completion.php
    AOWOW_TEST_DATABASE=aowow_security_test_updates php tests/security-updates.php
    AOWOW_TEST_DATABASE=aowow_security_test_legacy php tests/legacy-updates.php
    AOWOW_TEST_DATABASE=aowow_security_test_schema php tests/schema-validator-sql.php
    AOWOW_TEST_DATABASE=aowow_security_test_reconciliation php tests/schema-reconciliation.php
    AOWOW_TEST_DATABASE=aowow_security_test_resources php tests/security-contributions.php
    ;;
  apache)
    # Exercise both hosting permission sets, with and without inherited negotiation.
    for override in 'Options FileInfo' All; do
      for negotiation in disabled enabled; do
        for entrypoint in static cgi; do
          fixture_root="$(mktemp -d /tmp/aowow-private-uploads-apache-XXXXXX)"
          container="aowow-security-apache-${fixture_root##*-}"
          cleanup() {
            docker rm --force "$container" >/dev/null 2>&1 || true
            rm -rf -- "$fixture_root"
          }
          trap cleanup EXIT
          fixture_args=(--allow-override "$override")
          if [[ "$negotiation" == enabled ]]; then
            fixture_args+=(--multiviews)
          fi
          if [[ "$entrypoint" == cgi ]]; then
            fixture_args+=(--cgi-entrypoint)
          fi
          echo "Apache overrides: $override; MultiViews: $negotiation; entrypoint: $entrypoint"
          python3 tests/security-private-uploads-apache.py --prepare "$fixture_root" "${fixture_args[@]}"
          docker run --detach --rm --name "$container" --publish 127.0.0.1::8080 \
            --read-only --cap-drop ALL --security-opt no-new-privileges --user 65534:65534 \
            --tmpfs /tmp:rw,nosuid,nodev --volume "$fixture_root:/fixture:ro" \
            --entrypoint httpd httpd:2.4 -f /fixture/httpd.conf -DFOREGROUND
          address="$(docker port "$container" 8080/tcp)"
          # Wait only for startup; assertion failures must not be hidden by retrying the test suite.
          ready=false
          for ((attempt=0; attempt<30; attempt++)); do
            if curl --fail --silent --output /dev/null "http://$address/crossdomain.xml"; then
              ready=true
              break
            fi
            sleep 1
          done
          if [[ "$ready" != true ]]; then
            docker logs "$container"
            echo 'Apache fixture did not become ready.' >&2
            exit 1
          fi
          python3 tests/security-private-uploads-apache.py --check "http://$address"
          cleanup
        done
      done
    done
    ;;
  *)
    echo 'Usage: bash tests/ci/run.sh {lint|php|javascript|browser|sql|apache}' >&2
    exit 1
    ;;
esac
