#!/usr/bin/env node

/**
 * Screenshot and Visual Testing
 * 
 * Usage: node screenshot.js <url> [viewport]
 * Output: Base64 encoded screenshot
 */

const puppeteer = require('puppeteer');
const fs = require('fs');

const url = process.argv[2] || 'https://example.com';
const viewportType = process.argv[3] || 'desktop'; // desktop, mobile, tablet

const viewports = {
    desktop: { width: 1920, height: 1080, deviceScaleFactor: 1 },
    mobile: { width: 375, height: 667, deviceScaleFactor: 2, isMobile: true },
    tablet: { width: 768, height: 1024, deviceScaleFactor: 2 },
    mobileL: { width: 414, height: 896, deviceScaleFactor: 3, isMobile: true }
};

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu'
        ]
    });

    try {
        const page = await browser.newPage();
        
        const viewport = viewports[viewportType] || viewports.desktop;
        await page.setViewport(viewport);
        
        // Set user agent for mobile
        if (viewport.isMobile) {
            await page.setUserAgent(
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
            );
        }
        
        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Wait for fonts and images
        await page.waitForTimeout(2000);
        
        // Take full page screenshot
        const screenshot = await page.screenshot({
            fullPage: true,
            encoding: 'base64',
            type: 'jpeg',
            quality: 80
        });
        
        // Get visual metrics
        const visualMetrics = await page.evaluate(() => {
            // Check for layout shifts
            const layoutShifts = performance.getEntriesByType('layout-shift');
            const cls = layoutShifts.reduce((sum, entry) => sum + entry.value, 0);
            
            // Check for largest contentful paint element
            const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
            const lcp = lcpEntries.length > 0 ? lcpEntries[lcpEntries.length - 1] : null;
            
            // Get element positions for analysis
            const heroImage = document.querySelector('img[src*="hero"], img[class*="hero"]');
            const aboveFold = Array.from(document.querySelectorAll('img, h1, h2')).filter(el => {
                const rect = el.getBoundingClientRect();
                return rect.top < window.innerHeight;
            });
            
            return {
                cls: cls,
                lcp: lcp ? {
                    element: lcp.element?.tagName || 'unknown',
                    loadTime: lcp.startTime,
                    url: lcp.url || null
                } : null,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                scrollHeight: document.documentElement.scrollHeight,
                heroImagePresent: heroImage !== null,
                aboveFoldElements: aboveFold.length
            };
        });
        
        const result = {
            url: url,
            viewport: viewportType,
            screenshot: {
                base64: screenshot,
                format: 'jpeg',
                size: Buffer.from(screenshot, 'base64').length
            },
            visualMetrics: visualMetrics,
            devicePixelRatio: viewport.deviceScaleFactor
        };
        
        console.log(JSON.stringify(result, null, 2));
        
    } catch (error) {
        console.error(JSON.stringify({
            error: true,
            message: error.message,
            stack: error.stack
        }));
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
