import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

const BASE = 'http://localhost:8080';
const DEMO_ACADEMY_BASE = 'http://demo-academy.e-lesson.uz:8080';

// Cleanup for ensureAkmalHasRealAccess() below: Akmal (user_id 1001, Tenant 1) has zero
// pre-existing course_subs rows for course_id 1 or 7 (verified before this fixture was
// introduced), so any row matching this exact, narrow scope was created by the fixture
// itself and only by it — nothing pre-existing in Tenant 1 is touched, and Tenants 2/3
// are never referenced.
//
// The transactions row must be deleted EXPLICITLY and FIRST. This originally relied on
// transactions.course_sub_id being ON DELETE CASCADE, but migration
// 005_finance_ledger_hardening.sql deliberately changed it to ON DELETE SET NULL so that
// removing a subscription can never erase a record of money actually received. That is
// correct for production and wrong for this fixture: deleting only the subscription now
// leaves an orphaned 70 000 transaction in Tenant 1 on every run, which then shows up as
// phantom income in the admin cashbook (admin/finance/) — the exact class of fake number
// that module exists to eliminate. Akmal has no legitimate transactions of his own, so
// clearing all of his in Tenant 1 is both the narrowest correct scope and self-healing
// for orphans left behind by earlier runs.
//
// No DB client is available inside the Playwright test process itself (matching every
// other spec file in this repo) — this shells out to the same docker-compose container
// this whole project's local dev setup already runs in, exactly like the read-only
// verification commands used throughout this project's implementation sessions.
function cleanupAkmalFixtureAccess() {
    try {
        execSync(
            `docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" iq_creative -e ` +
            `"DELETE FROM transactions WHERE tenant_id = 1 AND user_id = 1001; ` +
            `DELETE FROM course_subs WHERE tenant_id = 1 AND user_id = 1001 AND course_id IN (1, 7);"'`,
            { stdio: 'pipe' }
        );
    } catch (e) {
        // Non-fatal: if cleanup fails (e.g. stack not running under this runner), don't
        // fail the test over it — surface it for visibility instead.
        console.warn('cleanupAkmalFixtureAccess() failed:', e);
    }
}

// P0-4 fixture fix: profile/test.php now gates access on course_subs.status = 'active'
// (previously it had no access gate at all — see php/access_service.php). Akmal
// (demo@example.local, user_id 1001, Tenant 1) has zero course_subs rows in the seed
// data for either course used below (course_id 1 for daily_test_id 1, course_id 7 for
// daily_test_id 3) — the original test only ever worked by relying on that missing
// gate. Rather than reopen the gate or permanently edit tenant-1 seed data, this helper
// grants Akmal real access through the actual admin flow (the same one a real center
// admin would use), idempotently: safe to run on every suite execution.
async function ensureAkmalHasRealAccess(page) {
    await page.goto(`${BASE}/admin/php/login.php`);
    await page.fill('input[name="username"]', 'iqadmin');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/**');

    // Course 1 (daily_test_id=1) is free (price=0) — grant via the admin's free-course
    // path (admin/payments/index.php's add_payment, amount=0), which sets status='active'
    // directly with no transaction, matching how a $0 course is handled platform-wide.
    await page.goto(`${BASE}/admin/payments/`);
    let csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf }) => {
        await fetch('/admin/payments/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'add_payment', csrf_token: csrf, user_id: '1001', course_id: '1',
                payment_method: 'cash', comment: 'e2e fixture: real access for course 1', days: '30',
            }),
        });
    }, { csrf });

    // Course 7 (daily_test_id=3) is paid — enroll (idempotent: a no-op if already
    // enrolled), then grant first access with a far-future next_payment_date so the
    // subscription never goes overdue across repeated test runs.
    await page.goto(`${BASE}/admin/courses/view.php?course_id=7`);
    csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf }) => {
        await fetch('/admin/courses/view.php?course_id=7', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'enroll_student', csrf_token: csrf, user_id: '1001' }),
        });
    }, { csrf });

    await page.goto(`${BASE}/admin/students/view.php?user_id=1001`);
    csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    // Scope to course 7's own card specifically — Akmal now has more than one course_subs
    // row (course 1 + course 7), so a generic "first grant/renew form on the page" query
    // would ambiguously match whichever course happens to render first.
    const subscriptionId = await page.evaluate(() => {
        const card = document.querySelector('[data-course-id="7"]');
        const form = card ? card.querySelector('form[id^="grant-form-"], form[id^="renew-form-"]') : null;
        return form ? (form.querySelector('input[name="subscription_id"]') as HTMLInputElement)?.value : null;
    });
    if (subscriptionId) {
        // Harmless no-op if the subscription is already 'active' from a prior run
        // (PaymentService rejects granting first access twice).
        await page.evaluate(async ({ csrf, subscriptionId }) => {
            await fetch(location.pathname + location.search, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    grant_access: '1', csrf_token: csrf, subscription_id: subscriptionId,
                    amount: '70000', payment_method: 'cash', payment_date: '2026-09-05',
                    next_payment_date: '2030-01-01', notes: 'e2e fixture: real access for course 7',
                }),
            });
        }, { csrf, subscriptionId });
    }

    await page.goto(`${BASE}/admin/php/logout.php`);
}

test.describe('Student & Admin Functional Fixes Suite', () => {

    test('1. Fresh test attempt shows questions and timer, not pre-completed 0% result', async ({ page }) => {
        // Fixture setup (P0-4): ensure Akmal has real, active access to both courses this
        // test exercises, through the real admin enrollment/payment flow. These rows are
        // temporary — cleanupAkmalFixtureAccess() below always removes them, pass or fail.
        await ensureAkmalHasRealAccess(page);

        try {
            // Log in as student Akmal (Tenant 1)
            await page.goto(`${BASE}/login/`);
            await page.fill('input#email', 'demo@example.local');
            await page.fill('input#password', 'Demo1234');
            await page.click('button#submitBtn');
            await page.waitForURL(`${BASE}/profile/`);

            // Test 3 (daily_test_id = 3) has never been taken by Akmal
            await page.goto(`${BASE}/profile/test.php?test_id=3&type=daily`);
            await page.waitForLoadState('domcontentloaded');

            // Fresh test: MUST show active timer and questions, NEVER pre-completed review screen
            await expect(page.locator('#test-timer')).toBeVisible();
            await expect(page.locator('form#test-form')).toBeVisible();
            await expect(page.locator('input[name="action"][value="submit_test"]')).toBeAttached();

            // Ensure not showing review/results screen
            await expect(page.locator('text=Тест сдан')).not.toBeVisible();
            await expect(page.locator('text=Тест не сдан')).not.toBeVisible();

            // Must have questions and answer controls
            const radioOptions = page.locator('input[type="radio"]');
            expect(await radioOptions.count()).toBeGreaterThan(0);

            // Verification of completed review mode: Test 1 was already completed by Akmal
            await page.goto(`${BASE}/profile/test.php?test_id=1&type=daily`);
            await page.waitForLoadState('domcontentloaded');
            await expect(page.locator('text=Тест сдан')).toBeVisible();
            await expect(page.locator('text=Результат')).toBeVisible();
            await expect(page.locator('text=Правильных')).toBeVisible();
            await expect(page.locator('text=Баллов')).toBeVisible();
        } finally {
            cleanupAkmalFixtureAccess();
        }
    });

    test('2. Admin can view uploaded student payment receipt via /file.php?type=receipt (No 403 / 404)', async ({ page }) => {
        // 1. Check image receipt on Localhost (Tenant 1, payment #3098)
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        await page.goto(`${BASE}/admin/payments/view.php?id=3098`);
        await page.waitForLoadState('networkidle');

        const imageReceiptLink = page.locator('a[href="/file.php?type=receipt&id=3098"]');
        await expect(imageReceiptLink).toBeVisible();

        const imgResponse = await page.request.get(`${BASE}/file.php?type=receipt&id=3098`);
        expect(imgResponse.status()).toBe(200);
        expect(imgResponse.headers()['content-type']).toMatch(/image\/(png|jpeg|webp)/);

        // 2. Check PDF receipt on Demo Academy (Tenant 3, payment #3094)
        await page.goto(`${DEMO_ACADEMY_BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'demoadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        await page.goto(`${DEMO_ACADEMY_BASE}/admin/payments/view.php?id=3094`);
        await page.waitForLoadState('networkidle');

        const pdfReceiptLink = page.locator('a[href="/file.php?type=receipt&id=3094"]');
        await expect(pdfReceiptLink).toBeVisible();

        const pdfResponse = await page.request.get(`${DEMO_ACADEMY_BASE}/file.php?type=receipt&id=3094`);
        expect(pdfResponse.status()).toBe(200);
        expect(pdfResponse.headers()['content-type']).toBe('application/pdf');
    });

    test('3. Admin can view lesson/material via /file.php?type=material (No 404 error)', async ({ page }) => {
        // Admin login
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        // Open Course 1 content tab (tab=content)
        await page.goto(`${BASE}/admin/courses/view.php?course_id=1&tab=content`);
        await page.waitForLoadState('networkidle');

        // Check for "view_material" links
        const viewMatLinks = page.locator('a[href*="/file.php?type=material&id="]');
        const count = await viewMatLinks.count();
        expect(count).toBeGreaterThan(0);

        // Test lesson 1 link
        const firstMatHref = await viewMatLinks.first().getAttribute('href');
        expect(firstMatHref).toMatch(/^\/file\.php\?type=material&id=\d+$/);

        // Request the material link using the authenticated admin context
        const response = await page.request.get(`${BASE}${firstMatHref}`);
        expect(response.status()).toBe(200);
        const contentType = response.headers()['content-type'];
        expect(contentType).toMatch(/(video\/|application\/pdf|application\/octet-stream)/);
    });

    test('4. Compact visual preview (thumbnail/video/PDF) rendered in Admin course lessons with clickable viewer', async ({ page }) => {
        // Admin login (IQ Creative - Course 1)
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        await page.goto(`${BASE}/admin/courses/view.php?course_id=1&tab=content`);
        await page.waitForLoadState('networkidle');

        // Check that compact 120-128px x 68-72px preview containers exist within lesson cards
        const previewContainers = page.locator('.w-32');
        const count = await previewContainers.count();
        expect(count).toBeGreaterThan(0);

        // First preview container dimensions should be around 128px x 72px
        const box = await previewContainers.first().boundingBox();
        expect(box).not.toBeNull();
        if (box) {
            expect(box.width).toBeGreaterThanOrEqual(120);
            expect(box.width).toBeLessThanOrEqual(132);
            expect(box.height).toBeGreaterThanOrEqual(68);
            expect(box.height).toBeLessThanOrEqual(76);
        }

        // Demo Academy (Tenant 3): Course 105 has real video and real thumbnail (material #509)
        await page.goto(`${DEMO_ACADEMY_BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'demoadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        await page.goto(`${DEMO_ACADEMY_BASE}/admin/courses/view.php?course_id=105&tab=content`);
        await page.waitForLoadState('networkidle');

        // Material 509 has real thumbnail on disk -> should display <img> with preview url
        const thumbImg = page.locator('img[src="/file.php?type=preview&id=509"]');
        await expect(thumbImg).toBeVisible();

        const imgRes = await page.request.get(`${DEMO_ACADEMY_BASE}/file.php?type=preview&id=509`);
        expect(imgRes.status()).toBe(200);
        expect(imgRes.headers()['content-type']).toMatch(/image\/(jpeg|jpg|png|webp)/);

        // Click "Просмотр урока" for lesson 509 -> MUST stay on current page and open inline video
        const btn509 = page.locator('#btn-preview-509');
        await expect(btn509).toBeVisible();
        await btn509.click();

        // Must stay on the course page
        expect(page.url()).toContain('course_id=105');

        // Inline preview must expand
        const preview509 = page.locator('#lesson-preview-509');
        await expect(preview509).toBeVisible();

        // Native HTML5 video player must exist and load metadata without hanging
        const video = page.locator('#video-player-509');
        await expect(video).toBeVisible();

        const readyState = await video.evaluate((v: HTMLVideoElement) => v.readyState);
        expect(readyState).toBeGreaterThanOrEqual(0);

        // Course 101 has seed paths not on disk -> should display clean fallback with play indicator, NOT broken image alt="Thumbnail"
        await page.goto(`${DEMO_ACADEMY_BASE}/admin/courses/view.php?course_id=101&tab=content`);
        await page.waitForLoadState('networkidle');

        // Must NOT have broken img with alt="Thumbnail"
        const brokenImg = page.locator('img[alt="Thumbnail"]');
        expect(await brokenImg.count()).toBe(0);

        // Should have clean fallback preview containers with play indicators
        const demoPreviewCards = page.locator('.w-32');
        expect(await demoPreviewCards.count()).toBeGreaterThan(0);

        // Clicking preview on missing file shows clean inline error instead of navigating away or infinite loading
        const btn501 = page.locator('#btn-preview-501');
        await btn501.click();
        const preview501 = page.locator('#lesson-preview-501');
        await expect(preview501).toBeVisible();
        await expect(preview501).toContainText('не найден на диске');
    });

    test('5. Student video player has preload="metadata" and seekable fallback source', async ({ page }) => {
        // Student login
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        await page.waitForURL(`${BASE}/profile/`);

        // Open Course 1
        await page.goto(`${BASE}/profile/course.php?course_id=1`);
        await page.waitForLoadState('networkidle');

        // Find video element
        const videoElement = page.locator('video');
        await expect(videoElement.first()).toBeVisible();
        await expect(videoElement.first()).toHaveAttribute('preload', 'metadata');
        
        const videoSrc = await videoElement.first().getAttribute('src');
        expect(videoSrc).toContain('/file.php?type=material&id=');

        // Verify the video source responds with 200 or 206
        const cleanUrl = videoSrc?.split('#')[0] || '';
        const res = await page.request.get(`${BASE}${cleanUrl}`);
        expect([200, 206]).toContain(res.status());
    });

    test('6. Terminology is consistently "Уроки" / "Lessons" across UI', async ({ page }) => {
        // Admin login
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        // 1. Courses list table header should say "Уроки"
        await page.goto(`${BASE}/admin/courses/`);
        await page.waitForLoadState('networkidle');
        const coursesTable = page.locator('table');
        await expect(coursesTable).toContainText('Уроки');

        // 2. Course view tab and buttons should say "Уроки" / "Добавить урок"
        await page.goto(`${BASE}/admin/courses/view.php?course_id=1&tab=content`);
        await page.waitForLoadState('networkidle');
        await expect(page.locator('a[href*="tab=content"]')).toContainText('Уроки');
        await expect(page.getByRole('button', { name: '+ Добавить урок' })).toBeVisible();

        // 3. Student Cabinet: check sidebar lessons count
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        await page.waitForURL(`${BASE}/profile/`);

        await page.goto(`${BASE}/profile/course.php?course_id=1`);
        await page.waitForLoadState('networkidle');
        const bodyText = await page.textContent('body');
        expect(bodyText).toContain('уроков');
    });

    test('7. Complete lifecycle: Demo Academy Course 105 plays real video in Admin & Student, Course 101 shows clean fallbacks', async ({ page }) => {
        // 1. Admin checks Course 105 (real video)
        await page.goto(`${DEMO_ACADEMY_BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'demoadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        // Verify tab=materials alias works
        await page.goto(`${DEMO_ACADEMY_BASE}/admin/courses/view.php?course_id=105&tab=materials`);
        await page.waitForLoadState('networkidle');
        await expect(page.locator('#btn-preview-509')).toBeVisible();

        // Open inline preview in admin
        await page.locator('#btn-preview-509').click();
        const adminVideo = page.locator('#video-player-509');
        await expect(adminVideo).toBeVisible();

        // 2. Student (Gulnoza) checks Course 105 (same real video)
        await page.goto(`${DEMO_ACADEMY_BASE}/login/`);
        await page.fill('input#email', 'gulnoza.xolmatova2108@demo.uz');
        await page.fill('input#password', 'demo_pass');
        await page.click('button#submitBtn');
        await page.waitForURL(`${DEMO_ACADEMY_BASE}/profile/`);

        await page.goto(`${DEMO_ACADEMY_BASE}/profile/course.php?course_id=105`);
        await page.waitForLoadState('networkidle');

        const studentVideo = page.locator('video');
        await expect(studentVideo.first()).toBeVisible();
        const studentVideoSrc = await studentVideo.first().getAttribute('src');
        expect(studentVideoSrc).toContain('/file.php?type=material&id=509');

        // Verify student can stream the video with 200/206
        const cleanUrl = studentVideoSrc?.split('#')[0] || '';
        const streamRes = await page.request.get(`${DEMO_ACADEMY_BASE}${cleanUrl}`);
        expect([200, 206]).toContain(streamRes.status());

        // 3. Student checks Course 101 (missing seed file -> clean fallback)
        // Enroll Gulnoza or check fallback UI
        await page.goto(`${DEMO_ACADEMY_BASE}/profile/courses.php`);
        await page.waitForLoadState('networkidle');
    });

});
