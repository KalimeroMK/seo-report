#!/bin/bash

# Wappalyzer Technology Detection Entrypoint
# Usage: docker run wappalyzer <url>

URL="${1:-https://example.com}"

# Run Wappalyzer with JSON output
wappalyzer "$URL" \
    --pretty \
    --no-interactive \
    --chunk-size=1 \
    --max-depth=1 \
    --max-urls=1 \
    --headers='User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.0'
