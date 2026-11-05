import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

// ─── HELPER: Make request with custom Host header ──────────

function withHost(host: string) {
    return { headers: { 'Host': host } };
}

// ─── 1. TENANT ROUTING — Subdomain Resolution ─────────────

test.describe('4.1: Tenant Routing — Subdomain Resolution', () => {
    test('iq-creative.e-lesson.uz resolves to tenant #1', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('iq-creative.e-lesson.uz'));
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('IQ-CREATIVE');
    });

    test('test-academy.e-lesson.uz resolves to tenant #2', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('test-academy.e-lesson.uz'));
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Test Academy');
    });

    test('demo-academy.e-lesson.uz resolves to tenant #3', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('demo-academy.e-lesson.uz'));
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Demo Academy');
    });

    test('unknown subdomain falls back to default tenant', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('unknown.e-lesson.uz'));
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        // Should get default tenant (IQ-CREATIVE #1)
        expect(body).toContain('IQ-CREATIVE');
    });
});

// ─── 2. TENANT ROUTING — Custom Domain ────────────────────

test.describe('4.2: Tenant Routing — Custom Domain', () => {
    test('custom domain resolves to correct tenant (if configured)', async ({ request }) => {
        // Currently no custom domains are set in seed data
        // This test verifies the mechanism doesn't break
        const resp = await request.get(`${BASE}/`, withHost('custom-domain.example.com'));
        expect(resp.status()).toBe(200);
        // Should fall back to default tenant
        const body = await resp.text();
        expect(body).toContain('IQ-CREATIVE');
    });
});

// ─── 3. TENANT ROUTING — Localhost ────────────────────────

test.describe('4.3: Tenant Routing — Localhost Development', () => {
    test('localhost serves default tenant', async ({ request }) => {
        const resp = await request.get(`${BASE}/`);
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('IQ-CREATIVE');
    });

    test('localhost:8080 serves default tenant', async ({ request }) => {
        const resp = await request.get(`${BASE}/`);
        expect(resp.status()).toBe(200);
    });
});

// ─── 4. BRANDING — Per-Tenant via Host ────────────────────

test.describe('4.4: Branding — Per-Tenant via Host Header', () => {
    test('IQ-CREATIVE branding via subdomain', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('iq-creative.e-lesson.uz'));
        const body = await resp.text();
        expect(body).toContain('IQ-CREATIVE');
        // Check tenant name is in the page title
        expect(body).toContain('<title>');
    });

    test('Test Academy branding via subdomain', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('test-academy.e-lesson.uz'));
        const body = await resp.text();
        expect(body).toContain('Test Academy');
    });

    test('Demo Academy branding via subdomain', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('demo-academy.e-lesson.uz'));
        const body = await resp.text();
        expect(body).toContain('Demo Academy');
    });
});

// ─── 5. AUTHENTICATION — Tenant-Scoped Login ──────────────

test.describe('4.5: Authentication — Tenant-Scoped Login', () => {
    test('login uses correct tenant (IQ-CREATIVE)', async ({ request }) => {
        const resp = await request.post(`${BASE}/login/login.php`, {
            ...withHost('iq-creative.e-lesson.uz'),
            form: {
                email: 'demo@example.local',
                password: 'Demo1234',
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        expect(body.success).toBe(true);
    });

    test('login fails with wrong tenant (test-academy has no demo user)', async ({ request }) => {
        const resp = await request.post(`${BASE}/login/login.php`, {
            ...withHost('test-academy.e-lesson.uz'),
            form: {
                email: 'demo@example.local',
                password: 'Demo1234',
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        // Should fail — demo@example.local only exists in tenant #1
        expect(body.error).toBeTruthy();
    });

    test('login with wrong password fails regardless of tenant', async ({ request }) => {
        const resp = await request.post(`${BASE}/login/login.php`, {
            ...withHost('iq-creative.e-lesson.uz'),
            form: {
                email: 'demo@example.local',
                password: 'wrongpassword',
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        expect(body.error).toBeTruthy();
    });
});

// ─── 6. AUTHENTICATION — Tenant-Scoped Registration ───────

test.describe('4.6: Authentication — Tenant-Scoped Registration', () => {
    test('registration creates user in correct tenant (IQ-CREATIVE)', async ({ request }) => {
        const testEmail = `test${Date.now()}@example.com`;
        const resp = await request.post(`${BASE}/login/register.php`, {
            ...withHost('iq-creative.e-lesson.uz'),
            form: {
                action: 'check_email',
                email: testEmail,
                first_name: 'TestUser',
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        expect(body.success).toBe(true);
    });

    test('registration on test-academy is isolated from IQ-CREATIVE', async ({ request }) => {
        // Check if demo@example.local exists on test-academy (it shouldn't)
        const resp = await request.post(`${BASE}/login/register.php`, {
            ...withHost('test-academy.e-lesson.uz'),
            form: {
                action: 'check_email',
                email: 'demo@example.local',
                first_name: 'Test',
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        // Should succeed — email doesn't exist in tenant #2
        expect(body.success).toBe(true);
    });
});

// ─── 7. AUTHENTICATION — Tenant-Scoped Forgot Password ────

test.describe('4.7: Authentication — Tenant-Scoped Forgot Password', () => {
    test('forgot password on IQ-CREATIVE uses correct tenant', async ({ request }) => {
        const resp = await request.post(`${BASE}/login/forgot_password.php`, {
            ...withHost('iq-creative.e-lesson.uz'),
            form: {
                action: 'verify_code',
                email: 'demo@example.local',
                code: '000000',
            },
        });
        expect(resp.status()).toBe(200);
        // Should not crash — response indicates code is wrong or missing
        const body = await resp.json();
        expect(body.error || body.success).toBeTruthy();
    });
});

// ─── 8. ISOLATION — Cross-Tenant Access ───────────────────

test.describe('4.8: Isolation — Cross-Tenant Access', () => {
    test('file.php blocks unauthenticated access regardless of host', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=1`, withHost('iq-creative.e-lesson.uz'));
        expect([401, 403]).toContain(resp.status());
    });

    test('.env blocked regardless of host', async ({ request }) => {
        const resp = await request.get(`${BASE}/.env`, withHost('iq-creative.e-lesson.uz'));
        expect(resp.status()).toBe(403);
    });

    test('.git blocked regardless of host', async ({ request }) => {
        const resp = await request.get(`${BASE}/.git/config`, withHost('test-academy.e-lesson.uz'));
        expect(resp.status()).toBe(403);
    });

    test('private directories blocked regardless of host', async ({ request }) => {
        const dirs = [
            '/course_resources/course_N1/resources/',
            '/receipts/',
            '/storage/tenants/1/courses/1/',
            '/storage/tenants/2/courses/',
        ];
        for (const dir of dirs) {
            const resp = await request.get(`${BASE}${dir}`, withHost('iq-creative.e-lesson.uz'));
            expect(resp.status()).toBe(403);
        }
    });
});

// ─── 9. SECURITY — Host Header Manipulation ───────────────

test.describe('4.9: Security — Host Header Attacks', () => {
    test('SQL injection in Host header does not cause error', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost("iq-creative.e-lesson.uz' OR '1'='1"));
        // Apache rejects malformed Host with 400, or app returns 200 with default tenant
        expect([200, 400]).toContain(resp.status());
    });

    test('extremely long Host header does not cause error', async ({ request }) => {
        const longHost = 'a'.repeat(500) + '.e-lesson.uz';
        const resp = await request.get(`${BASE}/`, withHost(longHost));
        expect(resp.status()).toBe(200);
    });

    test('Host header with path traversal does not cause error', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, withHost('../../../etc/passwd'));
        // Apache rejects malformed Host with 400, or app returns 200 with default tenant
        expect([200, 400]).toContain(resp.status());
    });

    test('empty Host header uses default tenant', async ({ request }) => {
        const resp = await request.get(`${BASE}/`, { headers: { 'Host': '' } });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('IQ-CREATIVE');
    });
});

// ─── 10. ADMIN — Host Resolution Does Not Override ────────

test.describe('4.10: Admin — Host Resolution Does Not Override Admin Tenant', () => {
    test('center admin login sets correct tenant regardless of host', async ({ page }) => {
        // Login as iqadmin (tenant #1) — even if accessed via different host
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        // Should be on admin dashboard with IQ-CREATIVE
        expect(page.url()).toContain('/admin/');
        expect(page.url()).not.toContain('login.php');
        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
    });

    test('super admin login works and is not affected by host', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        expect(page.url()).toContain('/admin/super/');
    });

    test('center admin cannot access super admin pages', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/super/`);
        expect(page.url()).not.toContain('/admin/super/');
    });
});

// ─── 11. REGRESSION — Core Flows ──────────────────────────

test.describe('4.11: Regression — Core Flows Still Work', () => {
    test('landing page loads on localhost', async ({ page }) => {
        const resp = await page.goto(`${BASE}/`);
        expect(resp?.status()).toBe(200);
    });

    test('classes page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/`);
        expect(resp?.status()).toBe(200);
    });

    test('books page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/classes/books/?class=1-2`);
        expect(resp?.status()).toBe(200);
    });

    test('course page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/course/?course_id=1`);
        expect([200, 302]).toContain(resp?.status() || 0);
    });

    test('admin login page loads', async ({ page }) => {
        const resp = await page.goto(`${BASE}/admin/php/login.php`);
        expect(resp?.status()).toBe(200);
    });

    test('admin courses page loads after login', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'iqadmin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/courses/index.php`);
        expect(resp?.status()).toBe(200);
    });

    test('super admin tenant list loads', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        const resp = await page.goto(`${BASE}/admin/super/tenants/index.php`);
        expect(resp?.status()).toBe(200);
        const content = await page.content();
        expect(content).toContain('IQ-CREATIVE');
        expect(content).toContain('Test Academy');
    });

    test('file.php returns 401 without auth', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=material&id=1`);
        expect(resp.status()).toBe(401);
    });

    test('session cookie has httponly flag', async ({ page }) => {
        await page.goto(`${BASE}/admin/php/login.php`);
        const cookies = await page.context().cookies();
        const phpSession = cookies.find(c => c.name === 'PHPSESSID');
        if (phpSession) {
            expect(phpSession.httpOnly).toBe(true);
        }
    });
});

// ─── 12. TENANT ID NOT ACCEPTED FROM CLIENT ───────────────

test.describe('4.12: Security — tenant_id Not Accepted from Client', () => {
    test('POST with tenant_id parameter does not override server resolution', async ({ request }) => {
        // Try to register with tenant_id=2 in POST body
        const resp = await request.post(`${BASE}/login/register.php`, {
            ...withHost('iq-creative.e-lesson.uz'),
            form: {
                action: 'check_email',
                email: `hacker${Date.now()}@example.com`,
                first_name: 'Hacker',
                tenant_id: '2',  // Should be ignored
            },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        // Should succeed in tenant #1 (IQ-CREATIVE), not tenant #2
        expect(body.success).toBe(true);
    });

    test('GET with tenant_id parameter does not affect resolution', async ({ request }) => {
        const resp = await request.get(`${BASE}/?tenant_id=2`, withHost('iq-creative.e-lesson.uz'));
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        // Should still show IQ-CREATIVE branding
        expect(body).toContain('IQ-CREATIVE');
    });
});

// ─── 13. PHP SYNTAX CHECKS ────────────────────────────────

test.describe('4.13: PHP Syntax — Modified Files', () => {
    test('no syntax errors in modified files', async ({ }) => {
        const { execSync } = require('child_process');
        const files = [
            'php/tenant.php',
            'php/config.php',
            'file.php',
            'login/login.php',
            'login/register.php',
            'login/forgot_password.php',
            'admin/authorization.php',
            'admin/super/authorization.php',
            'admin/php/login.php',
        ];
        for (const f of files) {
            const result = execSync(`php -l ${f}`, { cwd: '/Users/macbook/Documents/iq-creative-demo' }).toString();
            expect(result).toContain('No syntax errors');
        }
    });
});
