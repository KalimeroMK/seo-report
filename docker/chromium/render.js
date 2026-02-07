#!/usr/bin/env node

/**
 * JavaScript Rendering Analysis
 * 
 * Usage: node render.js <url>
 * Output: JSON with render info
 */

const puppeteer = require('puppeteer');

const url = process.argv[2] || 'https://example.com';

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
        
        // Enable JavaScript console logging
        const logs = [];
        page.on('console', msg => logs.push({
            type: msg.type(),
            text: msg.text()
        }));
        
        page.on('pageerror', error => logs.push({
            type: 'pageerror',
            text: error.message
        }));

        // Track network requests
        const requests = [];
        const failedRequests = [];
        
        await page.setRequestInterception(true);
        page.on('request', request => {
            requests.push({
                url: request.url(),
                method: request.method(),
                resourceType: request.resourceType()
            });
            request.continue();
        });
        
        page.on('requestfailed', request => {
            failedRequests.push({
                url: request.url(),
                method: request.method(),
                resourceType: request.resourceType(),
                error: request.failure().errorText
            });
        });

        // Measure render time
        const startTime = Date.now();
        
        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        const renderTime = Date.now() - startTime;
        
        // Wait for specific elements to check hydration
        await page.waitForTimeout(2000);
        
        // Check for client-side rendered content
        const pageInfo = await page.evaluate(() => {
            const scripts = Array.from(document.querySelectorAll('script'));
            const hasReact = scripts.some(s => s.src && s.src.includes('react'));
            const hasVue = scripts.some(s => s.src && s.src.includes('vue'));
            const hasAngular = scripts.some(s => s.src && s.src.includes('angular'));
            const hasNext = scripts.some(s => s.src && s.src.includes('next'));
            const hasNuxt = scripts.some(s => s.src && s.src.includes('nuxt'));
            
            return {
                title: document.title,
                metaDescription: document.querySelector('meta[name="description"]')?.content || null,
                h1Count: document.querySelectorAll('h1').length,
                h1Text: Array.from(document.querySelectorAll('h1')).map(h => h.textContent.trim()),
                hasClientSideRendering: !!window.__INITIAL_STATE__ || !!window.__DATA__ || !!window.__NUXT__,
                framework: hasReact ? 'React' : hasVue ? 'Vue' : hasAngular ? 'Angular' : hasNext ? 'Next.js' : hasNuxt ? 'Nuxt.js' : 'Unknown',
                scriptCount: scripts.length,
                inlineScriptCount: scripts.filter(s => !s.src).length,
                externalScriptCount: scripts.filter(s => s.src).length,
                domNodeCount: document.querySelectorAll('*').length,
                hasHydration: document.querySelector('[data-server-rendered]') !== null ||
                              document.querySelector('#__next') !== null ||
                              document.querySelector('#__nuxt') !== null ||
                              document.querySelector('#app[data-v-app]') !== null
            };
        });
        
        // Get performance metrics
        const performanceMetrics = await page.evaluate(() => {
            const timing = performance.timing;
            const navigation = performance.getEntriesByType('navigation')[0];
            
            return {
                domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
                loadComplete: timing.loadEventEnd - timing.navigationStart,
                firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime || null,
                firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime || null,
                domInteractive: timing.domInteractive - timing.navigationStart,
                ttfb: timing.responseStart - timing.navigationStart,
                resources: performance.getEntriesByType('resource').length
            };
        });

        const result = {
            url: url,
            renderTime: renderTime,
            pageInfo: pageInfo,
            performance: performanceMetrics,
            network: {
                totalRequests: requests.length,
                failedRequests: failedRequests.length,
                requestsByType: requests.reduce((acc, r) => {
                    acc[r.resourceType] = (acc[r.resourceType] || 0) + 1;
                    return acc;
                }, {})
            },
            console: {
                errors: logs.filter(l => l.type === 'error' || l.type === 'pageerror'),
                warnings: logs.filter(l => l.type === 'warn'),
                total: logs.length
            },
            seo: {
                titleRendered: pageInfo.title !== '',
                h1Rendered: pageInfo.h1Count > 0,
                metaRendered: pageInfo.metaDescription !== null,
                clientSideFramework: pageInfo.framework,
                hasHydration: pageInfo.hasHydration
            }
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
