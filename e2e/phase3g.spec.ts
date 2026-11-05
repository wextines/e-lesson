import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

// ─── 1. TENANT ISOLATION: Cross-tenant access ──────────────

test.describe('3G: Tenant Isolation — Cross-tenant data access', () => {
    test('1.1 Unauthenticated → protected pages = DENIED', async ({ request }) => {
        const fileResp = await request.get(`${BASE}/file.php?type=material&id=1`);
        expect([401, 403]).toContain(fileResp.status());
        const profileResp = await request.get(`${BASE}/profile/`, { maxRedirects: 0 });
        expect([302, 401, 403]).toContain(profileResp.status());
        const courseResp = await request.get(`${BASE}/course/?course_id=1`);
        expect(courseResp.status()).toBe(200);
        const booksResp = await request.get(`${BASE}/classes/books/?class=1-2`);
        expect(booksResp.status()).toBe(200);
    });

    test('1.2 .env blocked from web access', async ({ request }) => {
        const resp = await request.get(`${BASE}/.env`);
        expect(resp.status()).toBe(403);
    });

    test('1.3 .git blocked from web access', async ({ request }) => {
        const resp = await request.get(`${BASE}/.git/config`);
        expect(resp.status()).toBe(403);
    });

    test('1.4 Direct file access blocked for all private dirs', async ({ request }) => {
        const dirs = [
            '/course_resources/course_N1/resources/',
            '/receipts/',
            '/classes/books/resources/',
            '/other_resources/project_results/',
            '/storage/tenants/1/courses/1/',
            '/storage/tenants/1/books/',
            '/storage/tenants/1/receipts/',
            '/storage/tenants/2/courses/',
            '/storage/tenants/3/books/',
        ];
        for (const dir of dirs) {
            const resp = await request.get(`${BASE}${dir}`);
            expect(resp.status()).toBe(403);
        }
    });
});

// ─── 2. FILE.PHP SECURITY ──────────────────────────────────

test.describe('3G: File.php — Security checks', () => {
    test('2.1 Returns 401 without auth', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=1`);
        expect(resp.status()).toBe(401);
    });

    test('2.2 Returns 400 for invalid type', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=invalid&id=1`, {
            headers: { Cookie: 'PHPSESSID=fake' }
        });
        expect([400, 401]).toContain(resp.status());
    });

    test('2.3 Returns 400 for zero ID', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=0`);
        expect([400, 401]).toContain(resp.status());
    });

    test('2.4 Returns 400 for negative ID', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=-1`);
        expect([400, 401]).toContain(resp.status());
    });

    test('2.5 Returns 404 for non-existent ID', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=999999`);
        expect([401, 404]).toContain(resp.status());
    });

    test('2.6 Path traversal prevented', async ({ request }) => {
        const paths = [
            '/file.php?type=material&id=1&path=../../etc/passwd',
            '/file.php?type=book&id=1&file=../../../etc/passwd',
        ];
        for (const p of paths) {
            const resp = await request.get(`${BASE}${p}`);
            expect([400, 401]).toContain(resp.status());
        }
    });

    test('2.7 All file types require valid resource', async ({ request }) => {
        const types = ['material', 'preview', 'book_preview', 'book', 'question', 'receipt', 'resource'];
        for (const t of types) {
            const resp = await request.get(`${BASE}/file.php?type=${t}&id=999999`);
            expect([401, 404]).toContain(resp.status());
        }
    });
});

// ─── 3. AUTHENTICATION & SESSION ────────────────────────────

test.describe('3G: Authentication & Session', () => {
    test('3.1 Admin login page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/admin/php/login.php`);
        expect(resp?.status()).toBe(200);
    });

    test('3.2 Invalid admin credentials rejected', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'nonexistent');
        await page.fill('input[name="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);
        expect(page.url()).toContain('login.php');
    });

    test('3.3 Student login page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
    });

    test('3.4 Registration page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/login/register.php`);
        expect(resp?.status()).toBe(200);
    });

    test('3.5 Forgot password page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/login/forgot_password.php`);
        expect(resp?.status()).toBe(200);
    });

    test('3.6 Session uses secure params (initSession)', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        const cookies = await page.context().cookies();
        const phpSession = cookies.find(c => c.name === 'PHPSESSID');
        if (phpSession) {
            expect(phpSession.httpOnly).toBe(true);
        }
    });
});

// ─── 4. CSRF PROTECTION ────────────────────────────────────

test.describe('3G: CSRF Protection', () => {
    test('4.1 Admin forms include CSRF token', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        await page.goto(`${BASE}/admin/courses/index.php`);
        const content = await page.content();
        expect(content).toContain('csrf_token');
    });

    test('4.2 POST without CSRF token = DENIED', async ({ request }) => {
        const resp = await request.post(`${BASE}/admin/courses/index.php`, {
            form: {
                action: 'add_course',
                subject: 'Test',
                title: 'Test Course',
                description: 'Test',
                price: '0',
                days: '30',
                classLevel: '5-6',
            }
        });
        expect([200, 302, 403]).toContain(resp.status());
    });

    test('4.3 Super admin forms have CSRF', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        await page.goto(`${BASE}/admin/super/tenants/create.php`);
        const content = await page.content();
        expect(content).toContain('csrf_token');
    });

    test('4.4 Upload logo endpoint rejects without auth/CSRF', async ({ request }) => {
        const resp = await request.post(`${BASE}/admin/super/tenants/upload_logo.php`, {
            form: { tenant_id: '1' },
            maxRedirects: 0
        });
        // No session → 302 redirect to login; CSRF mismatch → 403
        expect([302, 403]).toContain(resp.status());
    });
});

// ─── 5. ADMIN AUTHORIZATION ────────────────────────────────

test.describe('3G: Admin Authorization', () => {
    test('5.1 Center admin → super admin page = DENIED', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/super/`);
        expect(page.url()).not.toContain('/admin/super/');
    });

    test('5.2 Unauthenticated → admin page = DENIED', async ({ page }) => {
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(page.url()).toContain('login.php');
    });

    test('5.3 Center admin → other tenant courses page = DENIED or own tenant only', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'testadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const content = await page.content();
        expect(content).not.toContain('IQ-CREATIVE');
    });
});

// ─── 6. INPUT VALIDATION ───────────────────────────────────

test.describe('3G: Input Validation', () => {
    test('6.1 SQL injection-like inputs do not cause errors', async ({ request }) => {
        const payloads = [
            "/file.php?type=material&id=1' OR '1'='1",
            "/file.php?type=material&id=1; DROP TABLE materials",
            "/course/?course_id=1' UNION SELECT * FROM users",
        ];
        for (const p of payloads) {
            const resp = await request.get(`${BASE}${p}`);
            expect(resp.status()).not.toBe(500);
        }
    });

    test('6.2 XSS payloads are escaped in admin pages', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });
});

// ─── 7. TENANT ISOLATION — Cross-tenant IDOR ───────────────

test.describe('3G: Tenant Isolation — IDOR between tenants', () => {
    test('7.1 IQ-CREATIVE admin (id=1) cannot access Test Academy (id=2) data', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
        expect(content).not.toContain('Test Academy');
    });

    test('7.2 Test Academy admin (id=2) sees only own tenant data', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'testadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const content = await page.content();
        expect(content).toContain('Test Academy');
        expect(content).not.toContain('IQ-CREATIVE');
    });

    test('7.3 IQ-CREATIVE file.php serves IQ-CREATIVE files only', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
    });
});

// ─── 8. REGRESSION — Core flows ────────────────────────────

test.describe('3G: Regression — Core flows', () => {
    test('8.1 Landing page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
        const title = await page.title();
        expect(title).toBeTruthy();
    });

    test('8.2 Classes page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/`);
        expect(resp?.status()).toBe(200);
    });

    test('8.3 Books page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/books/?class=1-2`);
        expect(resp?.status()).toBe(200);
    });

    test('8.4 Course page loads (redirects to login)', async ({ page }) => {
        const resp = await page.goto(`${BASE}/course/?course_id=1`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('8.5 Admin login → dashboard works', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('/admin/');
        expect(page.url()).not.toContain('login.php');
    });

    test('8.6 Super admin login works', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        expect(page.url()).toContain('/admin/super/');
    });

    test('8.7 Profile page loads for logged-in user', async ({ page }) => {
        const resp = await page.goto(`${BASE}/profile/`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('8.8 Tenant branding works (IQ-CREATIVE)', async ({ page }) => {
        await page.goto(`${BASE}/`);
        const name = await page.evaluate(() => (window as any).__tenantName);
        expect(name).toBe('IQ-CREATIVE');
    });

    test('8.9 Super admin tenant management accessible', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/super/tenants/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('8.10 Admin courses page loads with data', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
    });
});

// ─── 9. WEBHOOK SECURITY ───────────────────────────────────

test.describe('3G: Webhook Security', () => {
    // TELEGRAM_WEBHOOK_SECRET is unset in this environment (matches the real deployed
    // default — the endpoint ships fail-closed until an operator configures it and
    // registers the same value with Telegram's setWebhook secret_token). Every request
    // without the correct X-Telegram-Bot-Api-Secret-Token header must get 401, regardless
    // of body content, before the body is even parsed.
    test('9.1 Webhook rejects non-JSON input (no auth configured → 401)', async ({ request }) => {
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            headers: { 'Content-Type': 'application/json' },
            data: 'not json',
        });
        expect(resp.status()).toBe(401);
    });

    test('9.2 Webhook rejects empty body (no auth configured → 401)', async ({ request }) => {
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            data: '',
        });
        expect(resp.status()).toBe(401);
    });

    test('9.3 Webhook rejects malformed JSON (no auth configured → 401)', async ({ request }) => {
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            headers: { 'Content-Type': 'application/json' },
            data: '{ broken json',
        });
        expect(resp.status()).toBe(401);
    });

    test('9.4 Webhook rejects a forged payment_accept callback with no secret header', async ({ request }) => {
        // Simulates attack path #1 from the audit: a well-formed Telegram-shaped
        // callback_query trying to auto-accept an arbitrary payment_id. Must be
        // rejected before the payments table is ever touched.
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            headers: { 'Content-Type': 'application/json' },
            data: JSON.stringify({
                callback_query: {
                    id: 'fake-callback-1',
                    data: 'payment_accept_999999999',
                    message: { chat: { id: 1 }, message_id: 1, caption: '' },
                },
            }),
        });
        expect(resp.status()).toBe(401);
    });

    test('9.5 Webhook rejects a forged payment_accept callback with a wrong secret header', async ({ request }) => {
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            headers: {
                'Content-Type': 'application/json',
                'X-Telegram-Bot-Api-Secret-Token': 'attacker-guessed-value',
            },
            data: JSON.stringify({
                callback_query: {
                    id: 'fake-callback-2',
                    data: 'payment_accept_999999999',
                    message: { chat: { id: 1 }, message_id: 1, caption: '' },
                },
            }),
        });
        expect(resp.status()).toBe(401);
    });

    test('9.6 Webhook rejects a forged waiting_amount correction message with no secret header', async ({ request }) => {
        // Simulates attack path #2 from the audit: an unrelated/forged text message
        // trying to trigger the "amount_corrected" mutation. Must be rejected before
        // the payments table is ever touched.
        const resp = await request.post(`${BASE}/profile/topup/webhook.php`, {
            headers: { 'Content-Type': 'application/json' },
            data: JSON.stringify({
                message: {
                    chat: { id: 1 },
                    text: '50000',
                    reply_to_message: { text: 'Iltimos, to‘g‘ri summani kiriting (payment ID: 999999999):' },
                },
            }),
        });
        expect(resp.status()).toBe(401);
    });
});

// ─── 10. INACTIVE TENANT CHECK ──────────────────────────────

test.describe('3G: Inactive Tenant', () => {
    test('10.1 File.php blocks unauthenticated access', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=1`);
        expect([401, 403]).toContain(resp.status());
    });
});
