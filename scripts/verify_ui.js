#!/usr/bin/env node
/**
 * UI verification utility (CLAUDE.md Rule #9).
 *
 * Usage: node scripts/verify_ui.js <url_path> [username] [password]
 *   e.g. node scripts/verify_ui.js /admin/groups/view.php?id=1
 *   e.g. node scripts/verify_ui.js /admin/groups/index.php dilnoza_teacher teacher123
 *
 * Logs into Demo Academy as demoadmin by default (or as the given
 * username/password), loads the given path at both a desktop (1280x800) and
 * a mobile (390x844) viewport, and fails (non-zero exit code) if it finds:
 *   - any browser console error
 *   - any uncaught page exception
 *   - any HTTP response >= 400
 *   - horizontal overflow at the 390px mobile viewport
 *
 * Screenshots are saved to scratch/verify_desktop.png and scratch/verify_mobile.png.
 */

const path = require('path');
const fs = require('fs');
const { chromium } = require('@playwright/test');

const BASE_URL = 'http://demo-academy.e-lesson.uz:8080';
const DEFAULT_USERNAME = 'demoadmin';
const DEFAULT_PASSWORD = 'admin123';
const SCRATCH_DIR = path.join(__dirname, '..', 'scratch');

async function login(page, username, password) {
    await page.goto(`${BASE_URL}/admin/php/login.php`);
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
}

async function checkViewport(browser, { urlPath, username, password, width, height, screenshotName, checkOverflow }) {
    const errors = [];
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            errors.push(`[console.error] ${msg.text()}`);
        }
    });
    page.on('pageerror', (err) => {
        errors.push(`[pageerror] ${err.message}`);
    });
    page.on('response', (response) => {
        const status = response.status();
        if (status >= 400) {
            errors.push(`[http ${status}] ${response.url()}`);
        }
    });

    await login(page, username, password);

    const target = urlPath.startsWith('http') ? urlPath : `${BASE_URL}${urlPath}`;
    await page.goto(target, { waitUntil: 'networkidle' });

    if (checkOverflow) {
        const overflow = await page.evaluate(() => {
            const doc = document.documentElement;
            return {
                scrollWidth: doc.scrollWidth,
                clientWidth: doc.clientWidth,
            };
        });
        if (overflow.scrollWidth > overflow.clientWidth) {
            errors.push(
                `[overflow] horizontal overflow at ${width}px: scrollWidth=${overflow.scrollWidth} > clientWidth=${overflow.clientWidth}`
            );
        }
    }

    if (!fs.existsSync(SCRATCH_DIR)) {
        fs.mkdirSync(SCRATCH_DIR, { recursive: true });
    }
    await page.screenshot({ path: path.join(SCRATCH_DIR, screenshotName), fullPage: true });

    await context.close();
    return errors;
}

async function main() {
    const urlPath = process.argv[2];
    const username = process.argv[3] || DEFAULT_USERNAME;
    const password = process.argv[4] || DEFAULT_PASSWORD;
    if (!urlPath) {
        console.error('Usage: node scripts/verify_ui.js <url_path> [username] [password]');
        process.exit(1);
    }

    const browser = await chromium.launch({ headless: true });
    let allErrors = [];

    try {
        const desktopErrors = await checkViewport(browser, {
            urlPath,
            username,
            password,
            width: 1280,
            height: 800,
            screenshotName: 'verify_desktop.png',
            checkOverflow: false,
        });
        allErrors = allErrors.concat(desktopErrors.map((e) => `(desktop) ${e}`));

        const mobileErrors = await checkViewport(browser, {
            urlPath,
            username,
            password,
            width: 390,
            height: 844,
            screenshotName: 'verify_mobile.png',
            checkOverflow: true,
        });
        allErrors = allErrors.concat(mobileErrors.map((e) => `(mobile) ${e}`));
    } finally {
        await browser.close();
    }

    if (allErrors.length > 0) {
        console.error('\x1b[31m%s\x1b[0m', 'UI CHECKS FAILED:');
        for (const e of allErrors) {
            console.error(`  - ${e}`);
        }
        process.exit(1);
    }

    console.log('\x1b[32m%s\x1b[0m', 'ALL UI CHECKS PASSED');
    console.log(`Screenshots: ${path.join(SCRATCH_DIR, 'verify_desktop.png')}, ${path.join(SCRATCH_DIR, 'verify_mobile.png')}`);
}

main().catch((err) => {
    console.error('UI CHECKS FAILED (script error):', err);
    process.exit(1);
});
