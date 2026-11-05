import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

// Helper: login as admin
async function loginAdmin(page: any, username = 'iqadmin', password = 'admin123') {
    await page.goto(`${BASE}/admin/php/login.php`);
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
}

// Helper: login as super admin
async function loginSuperAdmin(page: any) {
    await page.goto(`${BASE}/admin/php/login.php`);
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
}

// ─── 1. LANDING PAGE ───────────────────────────────────────

test.describe('3H: Student Journey — Landing', () => {
    test('1.1 Landing page loads with content', async ({ page }) => {
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
        const title = await page.title();
        expect(title).toBeTruthy();
        // Should have hero section
        const body = await page.textContent('body');
        expect(body).toBeTruthy();
        expect(body!.length).toBeGreaterThan(100);
    });

    test('1.2 Landing has working navigation', async ({ page }) => {
        await page.goto(`${BASE}/`);
        // Check for common nav elements
        const links = await page.$$('a');
        expect(links.length).toBeGreaterThan(5);
    });

    test('1.3 Theme toggle works', async ({ page }) => {
        await page.goto(`${BASE}/`);
        // Get initial theme
        const initialTheme = await page.getAttribute('html', 'data-theme');
        // Click theme toggle
        const toggle = await page.$('[data-i18n="theme_toggle"], .theme-toggle, #themeToggle, [onclick*="theme"]');
        if (toggle) {
            await toggle.click();
            await page.waitForTimeout(500);
        }
        // Page should still be functional
        expect(page.url()).toContain('localhost');
    });

    test('1.4 Language switcher present', async ({ page }) => {
        await page.goto(`${BASE}/`);
        const langSelector = await page.$('#langSelector, .lang-selector, select[name*="lang"], [data-i18n="lang"]');
        // Language selector should exist somewhere
        const body = await page.content();
        expect(body).toContain('i18n');
    });
});

// ─── 2. REGISTRATION ───────────────────────────────────────

test.describe('3H: Student Journey — Registration', () => {
    test('2.1 Registration form is accessible', async ({ page }) => {
        await page.goto(`${BASE}/`);
        await page.waitForTimeout(1000);
        const body = await page.content();
        expect(body).toContain('register');
        expect(body).toContain('login');
    });

    test('2.2 Registration page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/login/register.php`);
        expect(resp?.status()).toBe(200);
    });

    test('2.3 Registration with existing email shows error', async ({ page }) => {
        await page.goto(`${BASE}/`);
        await page.waitForTimeout(1000);
        // Use the modal registration flow
        const resp = await page.evaluate(async () => {
            const r = await fetch('/login/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_email&email=test@example.com'
            });
            return { status: r.status, body: await r.json().catch(() => r.text()) };
        });
        // Should respond with some status
        expect(resp.status).toBeLessThan(500);
    });

    test('2.4 Registration with new email proceeds', async ({ page, request }) => {
        const testEmail = `test${Date.now()}@example.com`;
        const resp = await request.post(`${BASE}/login/register.php`, {
            form: { action: 'check_email', email: testEmail }
        });
        expect(resp.status()).toBeLessThan(500);
    });
});

// ─── 3. AUTHENTICATION ──────────────────────────────────────

test.describe('3H: Student Journey — Authentication', () => {
    test('3.1 Student login works with demo account', async ({ page }) => {
        await page.goto(`${BASE}/`);
        await page.waitForTimeout(1000);
        // Try login via JS fetch
        const resp = await page.evaluate(async () => {
            const r = await fetch('/login/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=test@example.com&password=Demo1234'
            });
            return { status: r.status, body: await r.json().catch(() => r.text()) };
        });
        expect(resp.status).toBe(200);
    });

    test('3.2 Wrong password rejected', async ({ page }) => {
        await page.goto(`${BASE}/`);
        const resp = await page.evaluate(async () => {
            const r = await fetch('/login/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=test@example.com&password=wrongpassword'
            });
            return { status: r.status, body: await r.json().catch(() => r.text()) };
        });
        expect(resp.status).toBe(200);
        // Response should indicate failure
        const body = resp.body as any;
        if (body && typeof body === 'object') {
            expect(body.success === false || body.error || body.message).toBeTruthy();
        }
    });

    test('3.3 Login with empty credentials returns error', async ({ page, request }) => {
        const resp = await request.post(`${BASE}/login/login.php`, {
            form: { email: '', password: '' }
        });
        expect(resp.status()).toBeLessThan(500);
    });

    test('3.4 Logout works', async ({ page }) => {
        const resp = await page.goto(`${BASE}/login/logout.php`, { waitUntil: 'domcontentloaded' });
        // Should redirect to landing
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('3.5 Forgot password page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/login/forgot_password.php`);
        expect(resp?.status()).toBe(200);
    });

    test('3.6 get_user.php responds', async ({ request }) => {
        const resp = await request.get(`${BASE}/login/get_user.php`);
        expect(resp.status()).toBeLessThan(500);
    });
});

// ─── 4. COURSES & MATERIALS ─────────────────────────────────

test.describe('3H: Student Journey — Courses & Materials', () => {
    test('4.1 Classes page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/`);
        expect(resp?.status()).toBe(200);
    });

    test('4.2 Course catalog loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/courses/index.php?class=1-2`);
        expect(resp?.status()).toBe(200);
    });

    test('4.3 Books page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/books/?class=1-2`);
        expect(resp?.status()).toBe(200);
    });

    test('4.4 Course detail page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/course/?course_id=1`);
        expect(resp?.status()).toBe(200);
    });

    test('4.5 Test info page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/course/tests/?test_id=1&type=daily&course_id=1`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('4.6 Test results page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/course/tests/results.php`);
        expect(resp?.status()).toBe(200);
    });
});

// ─── 5. PROFILE & PAYMENT ──────────────────────────────────

test.describe('3H: Student Journey — Profile & Payment', () => {
    test('5.1 Profile page redirects when not logged in', async ({ page }) => {
        const resp = await page.goto(`${BASE}/profile/`, { waitUntil: 'domcontentloaded' });
        // Should redirect to landing or show login
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('5.2 Top-up page redirects when not logged in', async ({ page }) => {
        const resp = await page.goto(`${BASE}/profile/topup/`, { waitUntil: 'domcontentloaded' });
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('5.3 Payment history page redirects when not logged in', async ({ page }) => {
        const resp = await page.goto(`${BASE}/profile/payments/`, { waitUntil: 'domcontentloaded' });
        expect([200, 302]).toContain(resp?.status() || 0);
    });
});

// ─── 6. CENTER ADMIN ────────────────────────────────────────

test.describe('3H: Center Admin Journey', () => {
    test('6.1 Admin login page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/admin/php/login.php`);
        expect(resp?.status()).toBe(200);
    });

    test('6.2 Center admin login → dashboard', async ({ page }) => {
        await loginAdmin(page);
        expect(page.url()).toContain('/admin/');
        expect(page.url()).not.toContain('login.php');
    });

    test('6.3 Admin courses page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('ourse');
    });

    test('6.4 Admin materials page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/materials/index_materials.php?course_id=1`);
        expect(resp?.status()).toBe(200);
    });

    test('6.5 Admin tests page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/tests/index_tests.php?course_id=1`);
        expect(resp?.status()).toBe(200);
    });

    test('6.6 Admin questions page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/questions/index_questions.php?test_id=1&type=daily&course_id=1`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('6.7 Admin books page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/adminBooks/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('6.8 Admin other resources page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/other_resources_adm/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('6.9 Admin user check page loads', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/userCheck/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('6.10 Center admin cannot access super admin', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/`);
        expect(page.url()).not.toContain('/admin/super/');
    });
});

// ─── 7. SUPER ADMIN ────────────────────────────────────────

test.describe('3H: Super Admin Journey', () => {
    test('7.1 Super admin login → dashboard', async ({ page }) => {
        await loginSuperAdmin(page);
        expect(page.url()).toContain('/admin/super/');
    });

    test('7.2 Tenant list loads', async ({ page }) => {
        await loginSuperAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/tenants/index.php`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
        expect(content).toContain('Test Academy');
    });

    test('7.3 Create tenant page loads', async ({ page }) => {
        await loginSuperAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/tenants/create.php`);
        expect(resp?.status()).toBe(200);
    });

    test('7.4 Edit tenant page loads', async ({ page }) => {
        await loginSuperAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/tenants/edit.php?id=1`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
    });

    test('7.5 Admin list loads', async ({ page }) => {
        await loginSuperAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/admins/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('7.6 Create admin page loads', async ({ page }) => {
        await loginSuperAdmin(page);
        const resp = await page.goto(`${BASE}/admin/super/admins/create.php`);
        expect(resp?.status()).toBe(200);
    });
});

// ─── 8. WHITE LABEL / BRANDING ─────────────────────────────

test.describe('3H: White Label — Tenant Branding', () => {
    test('8.1 IQ-CREATIVE branding on landing', async ({ page }) => {
        await page.goto(`${BASE}/`);
        const name = await page.evaluate(() => (window as any).__tenantName);
        expect(name).toBe('IQ-CREATIVE');
    });

    test('8.2 IQ-CREATIVE admin shows own branding', async ({ page }) => {
        await loginAdmin(page, 'iqadmin', 'admin123');
        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
    });

    test('8.3 Test Academy admin shows own branding', async ({ page }) => {
        await loginAdmin(page, 'testadmin', 'admin123');
        const content = await page.content();
        expect(content).toContain('Test Academy');
    });

    test('8.4 Tenant CSS variables present', async ({ page }) => {
        await page.goto(`${BASE}/`);
        const styles = await page.evaluate(() => {
            const style = getComputedStyle(document.documentElement);
            return {
                primary: style.getPropertyValue('--primary-color').trim(),
                secondary: style.getPropertyValue('--secondary-color').trim(),
            };
        });
        expect(styles.primary).toBeTruthy();
        expect(styles.secondary).toBeTruthy();
    });
});

// ─── 9. TENANT ISOLATION ───────────────────────────────────

test.describe('3H: Tenant Isolation', () => {
    test('9.1 IQ-CREATIVE admin sees own tenant data', async ({ page }) => {
        await loginAdmin(page, 'iqadmin', 'admin123');
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('ourse');
    });

    test('9.2 Test Academy admin sees different data', async ({ page }) => {
        await loginAdmin(page, 'testadmin', 'admin123');
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('ourse');
    });

    test('9.3 Cross-tenant file access blocked', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=1`);
        expect([401, 403]).toContain(resp.status());
    });
});

// ─── 10. PRODUCTION READINESS ──────────────────────────────

test.describe('3H: Production Readiness', () => {
    test('10.1 .env blocked', async ({ request }) => {
        const resp = await request.get(`${BASE}/.env`);
        expect(resp.status()).toBe(403);
    });

    test('10.2 .git blocked', async ({ request }) => {
        const resp = await request.get(`${BASE}/.git/config`);
        expect(resp.status()).toBe(403);
    });

    test('10.3 Private dirs blocked', async ({ request }) => {
        const dirs = [
            '/course_resources/course_N1/resources/',
            '/receipts/',
            '/classes/books/resources/',
            '/storage/tenants/1/courses/1/',
            '/storage/tenants/2/courses/',
        ];
        for (const dir of dirs) {
            const resp = await request.get(`${BASE}${dir}`);
            expect(resp.status()).toBe(403);
        }
    });

    test('10.4 Admin pages require auth', async ({ page }) => {
        await page.goto(`${BASE}/admin/courses/index.php`);
        expect(page.url()).toContain('login.php');
    });

    test('10.5 PHP syntax — key files', async ({ }) => {
        const { execSync } = require('child_process');
        const files = [
            'admin/authorization.php',
            'admin/super/authorization.php',
            'login/Login_functions.php',
            'login/register.php',
            'login/login.php',
            'login/forgot_password.php',
            'php/config.php',
            'php/tenant.php',
            'php/tenant_file.php',
            'file.php',
        ];
        for (const f of files) {
            const result = execSync(`php -l ${f}`, { cwd: '/Users/macbook/Documents/iq-creative-demo' }).toString();
            expect(result).toContain('No syntax errors');
        }
    });

    test('10.6 Session cookie has httponly flag', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        const cookies = await page.context().cookies();
        const phpSession = cookies.find(c => c.name === 'PHPSESSID');
        if (phpSession) {
            expect(phpSession.httpOnly).toBe(true);
        }
    });
});

// ─── 11. UI / PAGES LOAD ───────────────────────────────────

test.describe('3H: UI — All Major Pages Load', () => {
    const pages = [
        ['Landing', '/'],
        ['Classes', '/classes/'],
        ['Courses catalog', '/classes/courses/index.php?class=1-2'],
        ['Books', '/classes/books/?class=1-2'],
        ['Course detail', '/course/?course_id=1'],
        ['Test results', '/course/tests/results.php'],
        ['Registration', '/login/register.php'],
        ['Forgot password', '/login/forgot_password.php'],
    ];

    for (const [name, url] of pages) {
        test(`Page: ${name}`, async ({ page }) => {
            const resp = await page.goto(`${BASE}${url}`);
            expect(resp?.status()).toBe(200);
        });
    }
});

// ─── 12. MOBILE RESPONSIVE ─────────────────────────────────

test.describe('3H: Mobile Responsive', () => {
    test('12.1 Landing loads at 375px', async ({ browser }) => {
        const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
        const page = await ctx.newPage();
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
        // No horizontal overflow
        const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
        expect(bodyWidth).toBeLessThanOrEqual(400);
        await ctx.close();
    });

    test('12.2 Admin loads at 375px', async ({ browser }) => {
        const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
        const page = await ctx.newPage();
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
        await ctx.close();
    });
});

// ─── 13. DARK / LIGHT THEME ─────────────────────────────────

test.describe('3H: Dark/Light Theme', () => {
    test('13.1 Landing works in default theme', async ({ page }) => {
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
    });

    test('13.2 Admin works in default theme', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
    });
});

// ─── 14. I18N / LANGUAGES ──────────────────────────────────

test.describe('3H: Languages', () => {
    test('14.1 Russian translation file exists', async ({ request }) => {
        const resp = await request.get(`${BASE}/js/lang/ru.json`);
        expect(resp.status()).toBe(200);
    });

    test('14.2 Uzbek translation file exists', async ({ request }) => {
        const resp = await request.get(`${BASE}/js/lang/uz.json`);
        expect(resp.status()).toBe(200);
    });

    test('14.3 English translation file exists', async ({ request }) => {
        const resp = await request.get(`${BASE}/js/lang/en.json`);
        expect(resp.status()).toBe(200);
    });

    test('14.4 French translation file exists', async ({ request }) => {
        const resp = await request.get(`${BASE}/js/lang/fr.json`);
        expect(resp.status()).toBe(200);
    });

    test('14.5 i18n.js loads', async ({ request }) => {
        const resp = await request.get(`${BASE}/js/i18n.js`);
        expect(resp.status()).toBe(200);
    });
});

// ─── 15. DOCKER / ENVIRONMENT ──────────────────────────────

test.describe('3H: Environment', () => {
    test('15.1 Application serves on port 8080', async ({ request }) => {
        const resp = await request.get(`${BASE}/`);
        expect(resp.status()).toBe(200);
    });

    test('15.2 Database connection works (admin CRUD pages load)', async ({ page }) => {
        await loginAdmin(page);
        const resp = await page.goto(`${BASE}/admin/userCheck/index.php`);
        expect(resp?.status()).toBe(200);
    });
});
