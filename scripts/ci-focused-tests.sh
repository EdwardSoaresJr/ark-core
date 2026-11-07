#!/usr/bin/env bash
# Test files relevant to a change, for pull-request CI (the full suite runs nightly: .github/workflows/regression.yml).
#
#   scripts/ci-focused-tests.sh <base-ref>        one test file per line on stdout
#
# Picks: changed test files; tests that name a changed PHP class; tests that name a changed view (dot name) or
# component (<x-...>). Changes whose reach a name search cannot follow (test harness, dependencies, migrations,
# routes, config, bootstrap) are listed on stderr as "broad: <file>" so CI can recommend the full-regression label.
set -euo pipefail

base="${1:?usage: scripts/ci-focused-tests.sh <base-ref>}"
cd "$(git rev-parse --show-toplevel)"

found="$(mktemp)"
trap 'rm -f "$found"' EXIT

tests_naming() { # tests_naming <grep options...>
    grep -rl --include='*Test.php' "$@" tests 2>/dev/null >> "$found" || true
}

git diff --name-only --diff-filter=ACMR "$base"...HEAD | while IFS= read -r file; do
    case "$file" in
        tests/Pest.php|tests/TestCase.php|tests/bootstrap.php|tests/Support/*|phpunit*.xml|composer.json|composer.lock|bootstrap/*|database/migrations/*|routes/*|config/*)
            echo "broad: $file" >&2
            ;;
    esac
    case "$file" in
        tests/*Test.php)
            if [ -f "$file" ]; then echo "$file" >> "$found"; fi
            ;;
        app/*.php|database/*.php)
            tests_naming -w -e "$(basename "$file" .php)"
            ;;
        resources/views/*.blade.php)
            view="${file#resources/views/}"
            view="${view%.blade.php}"
            dotted="$(printf '%s' "$view" | tr '/' '.')"
            tests_naming -F -e "'$dotted'" -e "\"$dotted\""
            case "$dotted" in
                components.*) tests_naming -F -e "<x-${dotted#components.}" ;;
            esac
            ;;
    esac
done

sort -u "$found"
