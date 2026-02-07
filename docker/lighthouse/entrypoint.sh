#!/bin/bash

# Entrypoint for Lighthouse CI
# Usage: docker run lighthouse <url> [options]

URL="${1:-https://example.com}"
OUTPUT_FORMAT="${2:-json}"

# Run Lighthouse with specific categories
lighthouse "$URL" \
    --chrome-flags="--headless --no-sandbox --disable-gpu --disable-dev-shm-usage" \
    --output="$OUTPUT_FORMAT" \
    --output-path=/lighthouse/report.json \
    --only-categories=performance,accessibility,best-practices,seo,pwa \
    --preset=desktop

# Output to stdout
cat /lighthouse/report.json
