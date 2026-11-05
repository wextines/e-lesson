import { test, expect } from '@playwright/test';

// Focused suite for P0-2: reliable admin-side manual student creation
// (admin/students/index.php + php/student_service.php).
//
// Runs against Tenant 2 (Test Academy / testadmin), the tenant this project's other
// suites already use for automated-testing purposes (see e2e/phase3g.spec.ts Group 7,
// e2e/student_login_flow.spec.ts). Each test uses a timestamp-unique email so repeated
// runs never collide with a prior run's data, matching how the existing suites already
// operate in this tenant.

test.describe('Admin Student Creation (P0-2)', () => {
  const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
  const runId = Date.now();

  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/php/login.php`);
    await page.fill('input[name="username"]', 'testadmin');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/**');
  });

  test('01. Admin can create a student with valid fields, zero enrollment side effects', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Добавить студента")');

    const email = `e2e-student-${runId}@example.test`;
    await page.fill('input[name="first_name"]', 'E2E');
    await page.fill('input[name="last_name"]', `Student${runId}`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="phone"]', '901234567');
    await page.selectOption('select[name="class"]', '7');
    await page.fill('input[name="password"]', 'TestPass123');
    await page.click('button[type="submit"]:has-text("Сохранить")');

    await page.waitForLoadState('networkidle');
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Данные обновлены');
    expect(bodyText).toContain(`Student${runId}`);
    expect(bodyText).toContain('+998901234567');

    // The new student's row must show 0 enrolled courses — creation must not
    // auto-enroll, auto-pay, or grant any access.
    const row = page.locator('tr', { hasText: `Student${runId}` });
    await expect(row).toBeVisible();
    const rowText = await row.textContent();
    // "КУРСОВ" column: the row's course-count badge must read 0.
    expect(rowText).toMatch(/\b0\b/);
  });

  test('02. Duplicate email is rejected, no second account is created', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    const email = `e2e-dup-${runId}@example.test`;

    async function submitOnce() {
      await page.click('button:has-text("Добавить студента")');
      await page.fill('input[name="first_name"]', 'Dup');
      await page.fill('input[name="last_name"]', `Test${runId}`);
      await page.fill('input[name="email"]', email);
      await page.selectOption('select[name="class"]', '4');
      await page.fill('input[name="password"]', 'TestPass123');
      await page.click('button[type="submit"]:has-text("Сохранить")');
      await page.waitForLoadState('networkidle');
    }

    await submitOnce();
    let bodyText = await page.textContent('body');
    expect(bodyText).toContain('Данные обновлены');

    await submitOnce();
    bodyText = await page.textContent('body');
    expect(bodyText).toContain('уже существует');

    // Only one row for this email should exist in the list.
    const rows = page.locator('tr', { hasText: `Dup Test${runId}` });
    await expect(rows).toHaveCount(1);
  });

  test('03. Server rejects an out-of-range class value even bypassing the <select>', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    const email = `e2e-badclass-${runId}@example.test`;
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();

    const status = await page.evaluate(async ({ csrf, email }) => {
      const r = await fetch('/admin/students/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'add_student',
          csrf_token: csrf,
          first_name: 'BadClass',
          last_name: 'Test',
          email,
          class: '99',
          password: 'TestPass123',
        }),
        redirect: 'manual',
      });
      return r.status;
    }, { csrf, email });

    expect(status).toBe(0); // opaque redirect (302) — request was processed, not a hard error page

    await page.goto(`${BASE_URL}/admin/students/?q=${encodeURIComponent(email)}`);
    await page.waitForLoadState('networkidle');
    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain(email);
    expect(bodyText).not.toContain('BadClass');
  });

  test('04. A spoofed tenant_id field is ignored — student lands in the admin\'s real tenant', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    const email = `e2e-tenantspoof-${runId}@example.test`;
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();

    await page.evaluate(async ({ csrf, email }) => {
      await fetch('/admin/students/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'add_student',
          csrf_token: csrf,
          tenant_id: '1', // attempt to spoof a DIFFERENT tenant than testadmin's own (2)
          first_name: 'TenantSpoof',
          last_name: 'Test',
          email,
          class: '6',
          password: 'TestPass123',
        }),
        redirect: 'manual',
      });
    }, { csrf, email });

    // Visible in Test Academy's own student list (testadmin's real tenant) ...
    await page.goto(`${BASE_URL}/admin/students/?q=${encodeURIComponent(email)}`);
    await page.waitForLoadState('networkidle');
    let bodyText = await page.textContent('body');
    expect(bodyText).toContain('TenantSpoof');

    // ... and NOT visible to a different tenant's admin (iqadmin, Tenant 1).
    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/admin/php/login.php`);
    await page.fill('input[name="username"]', 'iqadmin');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/**');

    await page.goto(`${BASE_URL}/admin/students/?q=${encodeURIComponent(email)}`);
    await page.waitForLoadState('networkidle');
    bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('TenantSpoof');
  });

  test('05. Missing/invalid CSRF token is rejected with 403, no row created', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    const email = `e2e-csrf-${runId}@example.test`;

    const status = await page.evaluate(async ({ email }) => {
      const r = await fetch('/admin/students/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'add_student',
          csrf_token: 'not-a-real-token',
          first_name: 'Csrf',
          last_name: 'Test',
          email,
          class: '3',
          password: 'TestPass123',
        }),
        redirect: 'manual',
      });
      return r.status;
    }, { email });

    expect(status).toBe(403);

    await page.goto(`${BASE_URL}/admin/students/?q=${encodeURIComponent(email)}`);
    await page.waitForLoadState('networkidle');
    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain(email);
  });
});
