#!/usr/bin/env bash
set -euo pipefail

# -------------------------------------------------------------------
# deploy.sh
# Tag & trigger build for SatuSehat KYC API (Klinik Keluarga)
#
# Usage:
#   ./deploy.sh                          # -> staging, version=dev
#   ./deploy.sh --version 1.0.0          # -> staging, version=1.0.0
#   ./deploy.sh --version 1.0.0 --prod   # -> production, version=1.0.0 (+prod)
# -------------------------------------------------------------------

SERVICE="kyc"
VERSION="dev"
IS_PROD="false"
FORCE_PUSH="false"
REMOTE="origin"

print_help() {
  cat <<EOF
Usage: $0 [--version <semver|dev>] [--prod] [--force] [--remote <origin>] [--help]

Options:
  --version <v>      Version string, e.g. 1.0.0 or dev (default: dev)
  --prod             Mark as production build (adds +prod suffix on tag)
  --force            Force replace existing tag
  --remote <r>       Git remote to push tag to (default: origin)
  --help             Show this help
EOF
}

# --- parse args ---
while [[ $# -gt 0 ]]; do
  case "$1" in
    --service) SERVICE="${2:-}"; shift 2 ;;
    --version) VERSION="${2:-}"; shift 2 ;;
    --prod)    IS_PROD="true"; shift 1 ;;
    --force)   FORCE_PUSH="true"; shift 1 ;;
    --remote)  REMOTE="${2:-origin}"; shift 2 ;;
    --help|-h) print_help; exit 0 ;;
    *) echo "Unknown arg: $1"; print_help; exit 1 ;;
  esac
done

# --- validation ---
if [[ "${IS_PROD}" == "true" && "${VERSION}" == "dev" ]]; then
  echo "❌ Cannot deploy PROD with version 'dev'. Provide a release version (e.g., 1.0.0)."
  exit 1
fi
if [[ "${VERSION}" != "dev" && ! "${VERSION}" =~ ^[0-9]+(\.[0-9]+){1,2}(-[A-Za-z0-9\.]+)?$ ]]; then
  echo "❌ Invalid version: '${VERSION}'. Expected semver-like '1.0.0' (optional -suffix)."
  exit 1
fi

# --- compose tag ---
BASE_TAG="${SERVICE}-${VERSION}"
TAG_NAME="${BASE_TAG}"
if [[ "${IS_PROD}" == "true" ]]; then
  TAG_NAME="${BASE_TAG}+prod"
fi

echo ""
echo "=============================================="
if [[ "${IS_PROD}" == "true" ]]; then
  echo "  BUILD INTENT: PRODUCTION"
else
  echo "  BUILD INTENT: STAGING"
fi
echo "  Service: ${SERVICE}"
echo "  Version: ${VERSION}"
echo "  Tag:     ${TAG_NAME}"
echo "  Remote:  ${REMOTE}"
echo "=============================================="
echo ""

# --- prod confirmation ---
if [[ "${IS_PROD}" == "true" && "${FORCE_PUSH:-false}" != "true" ]]; then
  read -rp "Type EXACTLY 'DEPLOY PROD' to continue: " CONFIRM
  [[ "${CONFIRM}" == "DEPLOY PROD" ]] || { echo "Confirmation mismatch. Aborting."; exit 1; }
fi

# --- replace if exists locally ---
if git rev-parse "${TAG_NAME}" >/dev/null 2>&1; then
  echo "🔁 Local tag exists. Replacing it..."
  git tag -d "${TAG_NAME}" >/dev/null 2>&1 || true
fi

# --- create & push tag ---
git tag "${TAG_NAME}"
git push "${REMOTE}" "${TAG_NAME}" --force

echo ""
echo "✅ Pushed tag: ${TAG_NAME} to ${REMOTE}"
echo "⏩ Action will build and push the image to registry.medisy.id"
echo ""
