#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RUNNER="$ROOT/deploy/demo/deploy-demo-cpanel-remote.sh"
RELEASE_SHA="1234567890abcdef1234567890abcdef12345678"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

cat > "$TMP/fake-curl" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
url="${@: -1}"
count_file="${FAKE_CURL_COUNT_FILE:?}"
count=0
[[ -f "$count_file" ]] && count="$(cat "$count_file")"
count=$((count + 1))
printf '%s' "$count" > "$count_file"
release="${FAKE_RELEASE_SHA:?}"
short="${release:0:12}"
current=0
case "${FAKE_SCENARIO:?}" in
  stale_then_fresh) (( count >= 3 )) && current=1 ;;
  always_stale) current=0 ;;
  wrong_student_route) (( count >= 3 )) && current=1 ;;
  *) exit 64 ;;
esac
served_release="oldoldoldoldoldoldoldoldoldoldoldoldoldoldol"
served_short="${served_release:0:12}"
if (( current )); then
  served_release="$release"
  served_short="$short"
fi
badge="<span data-testid=\"modrik-web-release-badge\">MODRIK deployed release: $served_release Build $served_short</span>"
if [[ "$url" == */student ]]; then
  if [[ "${FAKE_SCENARIO}" == "wrong_student_route" && $current -eq 1 ]]; then
    printf '%s' "$badge<div data-testid=\"modrik-landing-page\"></div>"
  else
    printf '%s' "$badge<div data-testid=\"modrik-student-portal\" class=\"auth-shell\"></div>"
  fi
else
  printf '%s' "$badge<div data-testid=\"modrik-landing-page\"></div><a data-testid=\"modrik-student-portal-entry\" href=\"/student\">Student</a>"
fi
SH
chmod +x "$TMP/fake-curl"

# Source exactly the production runner. Its main() guard prevents deployment side effects.
source "$RUNNER"

run_case() {
  local scenario="$1" expected="$2" actual
  : > "$TMP/count"
  export FAKE_CURL_COUNT_FILE="$TMP/count"
  export FAKE_RELEASE_SHA="$RELEASE_SHA"
  export FAKE_SCENARIO="$scenario"
  export MODRIK_CURL_BIN="$TMP/fake-curl"
  export MODRIK_WEB_RELEASE_VERIFY_ATTEMPTS=3
  export MODRIK_WEB_RELEASE_VERIFY_DELAY_SECONDS=0

  if wait_for_portal_release "https://demo.invalid" "$RELEASE_SHA" >/dev/null 2>&1; then actual=0; else actual=1; fi
  [[ "$actual" == "$expected" ]] || {
    echo "case $scenario expected exit-class $expected got $actual" >&2
    exit 1
  }
}

run_case stale_then_fresh 0
run_case always_stale 1
run_case wrong_student_route 1

echo "Portal restart propagation regression passed."
