import { test, expect } from '@playwright/test';

// Focused suite for P0-3: the target course-access/payment state machine
// (php/payment_service.php, php/access_service.php, admin/courses/view.php's
// enroll_student action, admin/students/view.php's grant_access/renew_subscription).
//
// Runs against Tenant 2 (Test Academy / testadmin), the tenant this project's other
// suites already use for automated-testing purposes. Each run creates a fresh,
// timestamp-unique student so repeated runs never collide, matching
// e2e/admin_student_creation.spec.ts's established convention.
//
// Note on scope: this project's admin UI does not yet display raw `transactions` rows
// (that's a later financial-reporting feature) and there is no DB client available to
// this test runner (only @playwright/test — consistent with every other spec file in
// this repo). So these tests verify everything observable through the real admin/student
// UI (status, "Оплачено до" date, access granted/denied). The exact transaction
// amount/payment_date/created_by values were verified manually via a direct read-only DB
// query during implementation (see the task report) — automating that specific assertion
// would require adding a DB client dependency or a new admin "transactions" UI, neither of
// which is in scope here.

test.describe('Payment State Machine (P0-3)', () => {
  const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
  const COURSE_ID = 15; // "Mechanics", Test Academy, subscription_days = 30
  const runId = Date.now();

  async function loginAdmin(page, username: string) {
    await page.goto(`${BASE_URL}/admin/php/login.php`);
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/**');
  }

  async function createStudent(page, email: string) {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Добавить студента")');
    await page.fill('input[name="first_name"]', 'PSM');
    await page.fill('input[name="last_name"]', `Test${runId}`);
    await page.fill('input[name="email"]', email);
    await page.selectOption('select[name="class"]', '7');
    await page.fill('input[name="password"]', 'TestPass123');
    await page.click('button[type="submit"]:has-text("Сохранить")');
    await page.waitForLoadState('networkidle');

    await page.goto(`${BASE_URL}/admin/students/?q=${encodeURIComponent(email)}`);
    await page.waitForLoadState('networkidle');
    const href = await page.locator('tr', { hasText: `Test${runId}` }).first().evaluate((row) => {
      return (row as HTMLElement).getAttribute('onclick') || '';
    });
    const match = href.match(/user_id=(\d+)/);
    if (!match) throw new Error('Could not resolve new student user_id from row onclick');
    return parseInt(match[1], 10);
  }

  async function enroll(page, userId: number) {
    await page.goto(`${BASE_URL}/admin/courses/view.php?course_id=${COURSE_ID}`);
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf, userId }) => {
      await fetch(`/admin/courses/view.php?course_id=15`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'enroll_student', csrf_token: csrf, user_id: String(userId) }),
      });
    }, { csrf, userId });
  }

  async function getSubscriptionId(page, userId: number): Promise<string> {
    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.waitForLoadState('networkidle');
    const subId = await page.evaluate(() => {
      const form = document.querySelector('form[id^="grant-form-"], form[id^="renew-form-"]');
      return form ? (form.querySelector('input[name="subscription_id"]') as HTMLInputElement)?.value : null;
    });
    if (!subId) throw new Error('Could not find subscription_id on student profile page');
    return subId;
  }

  async function grantAccess(page, subscriptionId: string, nextPaymentDate: string, amount = '100000') {
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf, subscriptionId, nextPaymentDate, amount }) => {
      await fetch(location.pathname + location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          grant_access: '1', csrf_token: csrf, subscription_id: subscriptionId,
          amount, payment_method: 'cash', payment_date: '2026-09-05', next_payment_date: nextPaymentDate,
        }),
      });
    }, { csrf, subscriptionId, nextPaymentDate, amount });
  }

  async function renew(page, subscriptionId: string, amount = '50000') {
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf, subscriptionId, amount }) => {
      await fetch(location.pathname + location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          renew_subscription: '1', csrf_token: csrf, subscription_id: subscriptionId,
          amount, payment_method: 'card', payment_date: '2026-09-25',
        }),
      });
    }, { csrf, subscriptionId, amount });
  }

  test('01. Enrollment creates a pending subscription with no access and no transaction', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-enroll-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);

    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('Ожидает оплаты');
    expect(tabText).not.toContain('Активна');

    // Student has no real access yet.
    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/login/`);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'TestPass123');
    await page.evaluate(() => document.querySelector('form')?.requestSubmit());
    await page.waitForTimeout(500);
    await page.goto(`${BASE_URL}/profile/course.php?course_id=${COURSE_ID}`);
    expect(page.url()).toContain('courses.php');
    expect(page.url()).toContain('error=no_access');
  });

  test('02. Выдать доступ activates access with exactly the selected next_payment_date', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-grant-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);

    await grantAccess(page, subId, '2026-11-15');

    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('Активна');
    expect(tabText).toContain('15.11.2026'); // exactly the entered next_payment_date, not auto-computed

    // Student now has real access.
    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/login/`);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'TestPass123');
    await page.evaluate(() => document.querySelector('form')?.requestSubmit());
    await page.waitForTimeout(500);
    await page.goto(`${BASE_URL}/profile/course.php?course_id=${COURSE_ID}`);
    expect(page.url()).not.toContain('error=no_access');
  });

  test('03. Продлить курс advances next_payment_date from the CURRENT planned date, not from today', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-renew-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);
    await grantAccess(page, subId, '2026-10-01'); // matches the task's own worked example

    await renew(page, subId); // payment_date 2026-09-25, but must advance from 2026-10-01 + 30 days

    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('31.10.2026'); // 2026-10-01 + 30 days, NOT 2026-10-25 (from payment_date)
  });

  test('04. Overdue active subscription still grants access, and admin sees it flagged overdue', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-overdue-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);
    // Grant access with a next_payment_date already in the past — reachable purely
    // through the real admin flow (no DB manipulation needed).
    await grantAccess(page, subId, '2020-01-01');

    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('Активна');
    expect(tabText).toMatch(/Просрочено на \d+ дн\./);

    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/login/`);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'TestPass123');
    await page.evaluate(() => document.querySelector('form')?.requestSubmit());
    await page.waitForTimeout(500);
    await page.goto(`${BASE_URL}/profile/course.php?course_id=${COURSE_ID}`);
    expect(page.url()).not.toContain('error=no_access');
  });

  test('05. Cross-tenant subscription mutation is rejected', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-tenant-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);
    await grantAccess(page, subId, '2026-11-01');

    // A different tenant's admin attempts to renew this subscription.
    await loginAdmin(page, 'iqadmin');
    await page.goto(`${BASE_URL}/admin/students/`);
    const csrf2 = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf2, subId }) => {
      await fetch('/admin/students/view.php?user_id=1001', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          renew_subscription: '1', csrf_token: csrf2, subscription_id: subId,
          amount: '999999', payment_method: 'cash', payment_date: '2026-09-05',
        }),
      });
    }, { csrf2, subId });

    // Confirm from Test Academy's own side that nothing changed.
    await loginAdmin(page, 'testadmin');
    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('01.11.2026'); // unchanged from the original grant
    expect(tabText).not.toContain('01.10.2026');
  });

  test('06. Unauthorized: invalid CSRF token is rejected, no state change', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-csrf-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);

    const status = await page.evaluate(async ({ subId }) => {
      const r = await fetch(location.pathname + location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          grant_access: '1', csrf_token: 'not-a-real-token', subscription_id: subId,
          amount: '1', payment_method: 'cash', payment_date: '2026-09-05', next_payment_date: '2026-11-01',
        }),
        redirect: 'manual',
      });
      return r.status;
    }, { subId });
    expect(status).toBe(403);

    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('Ожидает оплаты'); // still pending, untouched
  });

  // P0-4: the legacy student-facing receipt-upload flow (profile/payments.php, left
  // untouched) reconciled with admin/payments/view.php's acceptance path, which now
  // calls php/payment_service.php instead of mutating access_expires_at directly.
  // The online receipt flow is opt-in per center (admin/settings/ → allow_receipt_uploads,
  // default '0' so offline-only centers aren't shown a queue they never use). Tests 07/08
  // drive the student upload form, so they must switch it on for the test tenant first —
  // through the real settings form, the same way an admin would — and switch it back off
  // afterwards so the tenant is left exactly as it was found.
  async function setReceiptUploads(page, enabled: boolean) {
    await loginAdmin(page, 'testadmin');
    await page.goto(`${BASE_URL}/admin/settings/`);
    const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.evaluate(async ({ csrf, enabled }) => {
      const body: Record<string, string> = { csrf_token: csrf, update_payment_settings: '1' };
      if (enabled) body.allow_receipt_uploads = '1';
      await fetch('/admin/settings/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(body),
      });
    }, { csrf, enabled });
  }

  async function uploadReceipt(page, courseId: number, amount: string) {
    await page.goto(`${BASE_URL}/profile/payments.php`);
    await page.evaluate((cid) => (window as any).openPaymentModal(cid), courseId);
    await page.selectOption('#modal-course-id', String(courseId));
    await page.fill('#modal-amount', amount);
    const jpegBuffer = Buffer.from('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', 'base64');
    await page.locator('input[name="receipt"]').setInputFiles({ name: 'receipt.jpg', mimeType: 'image/jpeg', buffer: jpegBuffer });
    await page.click('#payment-modal button[type="submit"]');
    await page.waitForLoadState('networkidle');
  }

  test('07. Legacy receipt acceptance: first payment reconciles through PaymentService', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-legacy-first-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);

    await setReceiptUploads(page, true);
    try {
    // Student uploads a receipt for this course via the untouched student-facing flow.
    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/login/`);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'TestPass123');
    await page.evaluate(() => document.querySelector('form')?.requestSubmit());
    await page.waitForTimeout(500);
    await uploadReceipt(page, COURSE_ID, '100000');

    // Admin approves it — this is a first payment (subscription is still 'pending'), so
    // the reconciled accept flow must require next_payment_date.
    await loginAdmin(page, 'testadmin');
    await page.goto(`${BASE_URL}/admin/payments/?student=${encodeURIComponent('PSM')}&status=pending`);
    await page.waitForLoadState('networkidle');
    const row = page.locator('tr', { hasText: `Test${runId}` }).first();
    await row.click();
    await page.waitForLoadState('networkidle');

    await page.selectOption('#paymentStatusSelect', 'accepted');
    await page.fill('#nextPaymentDateInput', '2026-12-01');
    await page.fill('#paymentCommentInput', 'e2e first payment');
    await page.click('#statusChangeForm button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('успешно одобрен');

    // Student now has real access. Note: /admin/php/logout.php only clears the admin
    // session keys, not the student's (isLoggedIn() reads a separate key) — the
    // student's own session from uploadReceipt() is still valid in this same browser
    // context, so no re-login is needed or possible here (the login page would just
    // redirect straight back to the dashboard for an already-authenticated student).
    await page.goto(`${BASE_URL}/profile/course.php?course_id=${COURSE_ID}`);
    expect(page.url()).not.toContain('error=no_access');
    } finally {
      await setReceiptUploads(page, false);
    }
  });

  test('08. Legacy receipt acceptance: renewal on an already-active subscription needs no extra input', async ({ page }) => {
    await loginAdmin(page, 'testadmin');
    const email = `psm-legacy-renew-${runId}@example.test`;
    const userId = await createStudent(page, email);
    await enroll(page, userId);
    const subId = await getSubscriptionId(page, userId);
    await grantAccess(page, subId, '2026-11-01'); // now active

    await setReceiptUploads(page, true);
    try {
    await page.goto(`${BASE_URL}/admin/php/logout.php`);
    await page.goto(`${BASE_URL}/login/`);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', 'TestPass123');
    await page.evaluate(() => document.querySelector('form')?.requestSubmit());
    await page.waitForTimeout(500);
    await uploadReceipt(page, COURSE_ID, '50000');

    await loginAdmin(page, 'testadmin');
    await page.goto(`${BASE_URL}/admin/payments/?student=${encodeURIComponent('PSM')}&status=pending`);
    await page.waitForLoadState('networkidle');
    const row = page.locator('tr', { hasText: `Test${runId}` }).first();
    await row.click();
    await page.waitForLoadState('networkidle');

    // No next_payment_date field should be required/shown — this subscription is
    // already active, so it's a renewal (advances automatically).
    await expect(page.locator('#nextPaymentDateGroup')).toHaveCount(0);

    await page.selectOption('#paymentStatusSelect', 'accepted');
    await page.click('#statusChangeForm button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('успешно одобрен');

    // Access remains granted, and the subscription's paid-through date advanced past
    // the original grant's next_payment_date (2026-11-01 + 30 days = 2026-12-01).
    await page.goto(`${BASE_URL}/admin/students/view.php?user_id=${userId}`);
    await page.evaluate(() => (window as any).switchProfileTab('courses'));
    const tabText = await page.locator('#tab-content-courses').innerText();
    expect(tabText).toContain('Активна');
    expect(tabText).toContain('01.12.2026');
    } finally {
      await setReceiptUploads(page, false);
    }
  });
});
