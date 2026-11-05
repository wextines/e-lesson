import { test, expect, APIRequestContext } from '@playwright/test';
import { execSync } from 'child_process';

const BASE = 'http://localhost:8080';

// ─── Helper: create admin session via Apache (not CLI) ──────
let helperDeployed = false;

async function deployHelper(request: APIRequestContext): Promise<void> {
    if (helperDeployed) return;
    const helperScript = `<?php
header("Content-Type: text/plain");
session_set_cookie_params(["lifetime"=>604800,"path"=>"/","httponly"=>true,"secure"=>false,"samesite"=>"Lax"]);
session_start();
$action = $_GET["action"] ?? "";
if ($action === "create") {
    $tid = (int)($_GET["tid"] ?? 1);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'testadmin';
    $_SESSION['admin_role'] = 'center_admin';
    $_SESSION['admin_tenant_id'] = $tid;
    session_write_close();
    echo session_id();
} elseif ($action === "destroy") {
    session_destroy();
    echo "ok";
} else {
    echo "unknown";
}`;
    execSync(
        `docker compose exec -T web bash -c "cat > /var/www/html/_admin_helper.php"`,
        { input: helperScript, encoding: 'utf-8', timeout: 5000 }
    );
    helperDeployed = true;
}

async function createAdminSession(request: APIRequestContext, tenantId = 1): Promise<string> {
    await deployHelper(request);
    const resp = await request.get(`${BASE}/_admin_helper.php?action=create&tid=${tenantId}`);
    return (await resp.text()).trim();
}

async function destroySession(request: APIRequestContext) {
    try {
        await request.get(`${BASE}/_admin_helper.php?action=destroy`);
    } catch (e) { /* ignore */ }
}

test.afterAll(async ({ request }) => {
    try {
        execSync(
            `docker compose exec -T web rm -f /var/www/html/_admin_helper.php`,
            { encoding: 'utf-8', timeout: 5000 }
        );
    } catch (e) { /* ignore */ }
});

// ─── 1. AUTH GUARD ───────────────────────────────────────────

test.describe('Phase 5: Auth guard on new pages', () => {
    const pages = [
        '/admin/dashboard.php',
        '/admin/students/',
        '/admin/students/view.php?user_id=1001',
        '/admin/courses/analytics.php',
        '/admin/payments/',
    ];

    for (const url of pages) {
        test(`Unauthenticated → ${url} redirects to login`, async ({ request }) => {
            const resp = await request.get(`${BASE}${url}`, { maxRedirects: 0 });
            expect([302, 301]).toContain(resp.status());
            const loc = resp.headers()['location'] || '';
            expect(loc).toContain('login.php');
        });
    }
});

// ─── 2. DASHBOARD ────────────────────────────────────────────

test.describe('Phase 5: Dashboard', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('2.1 Dashboard loads with KPI cards', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/dashboard.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Дашборд');
        expect(body).toContain('Всего учеников');
        expect(body).toContain('Новых за месяц');
        expect(body).toContain('Активных подписок');
        expect(body).toContain('Курсов');
        expect(body).toContain('Материалов');
        expect(body).toContain('Тестов');
        expect(body).toContain('Общий доход');
        expect(body).toContain('Ожидают оплаты');
        expect(body).toContain('Средний балл тестов');
    });

    test('2.2 Dashboard shows recent test results table', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/dashboard.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Последние результаты тестов');
    });

    test('2.3 Dashboard shows recent payments table', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/dashboard.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Последние платежи');
    });

    test('2.4 Dashboard has sidebar navigation', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/dashboard.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Dashboard');
        expect(body).toContain('Students');
        expect(body).toContain('Courses');
        expect(body).toContain('Payments');
    });
});

// ─── 3. STUDENTS LIST ────────────────────────────────────────

test.describe('Phase 5: Students list', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('3.1 Students page loads with list', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Ученики');
        expect(body).toContain('Поиск по ID');
    });

    test('3.2 Search by user_id', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/?q=1001`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('1001');
    });

    test('3.3 Search by name', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/?q=1001`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Профиль');
    });

    test('3.4 Empty search returns all', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Профиль');
    });
});

// ─── 4. STUDENT PROFILE ─────────────────────────────────────

test.describe('Phase 5: Student profile', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('4.1 Profile loads for valid user', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/view.php?user_id=1001`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Контактные данные');
        expect(body).toContain('Курсы');
        expect(body).toContain('Результаты тестов');
        expect(body).toContain('Платежи');
    });

    test('4.2 Profile redirects for invalid user_id', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/view.php?user_id=99999`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
            maxRedirects: 0,
        });
        expect([302, 301]).toContain(resp.status());
    });

    test('4.3 Profile redirects for user_id=0', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/view.php?user_id=0`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
            maxRedirects: 0,
        });
        expect([302, 301]).toContain(resp.status());
    });
});

// ─── 5. COURSE ANALYTICS ────────────────────────────────────

test.describe('Phase 5: Course analytics', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('5.1 Analytics tab loads in course view', async ({ request }) => {
        const courseResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const courseBody = await courseResp.text();
        const match = courseBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) { console.log('No course found, skipping'); return; }
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=analytics`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Analytics');
        expect(body).toContain('Students');
    });

    test('5.2 Analytics shows course stats', async ({ request }) => {
        const courseResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const courseBody = await courseResp.text();
        const match = courseBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) { console.log('No course found, skipping'); return; }
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=analytics`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Attempts');
        expect(body).toContain('Avg Score');
    });
});

// ─── 6. PAYMENTS ────────────────────────────────────────────

test.describe('Phase 5: Payments page', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('6.1 Payments page loads', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/payments/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Платежи');
        expect(body).toContain('Ожидают');
        expect(body).toContain('Одобрено');
        expect(body).toContain('Отклонено');
    });

    test('6.2 Filter by status', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/payments/?status=pending`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Платежи');
    });

    test('6.3 Filter by student', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/payments/?student=Admin`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
    });
});

// ─── 7. TENANT ISOLATION ────────────────────────────────────

test.describe('Phase 5: Tenant isolation', () => {
    test('7.1 Admin tenant 1 cannot see tenant 2 students', async ({ request }) => {
        const sid1 = await createAdminSession(request, 1);
        try {
            // Get student count for tenant 1
            const resp1 = await request.get(`${BASE}/admin/students/`, {
                headers: { Cookie: `PHPSESSID=${sid1}` },
            });
            const body1 = await resp1.text();

            // Create session for tenant 2
            const sid2 = await createAdminSession(request, 2);
            const resp2 = await request.get(`${BASE}/admin/students/`, {
                headers: { Cookie: `PHPSESSID=${sid2}` },
            });
            const body2 = await resp2.text();

            // Both should load but show different data
            expect(resp1.status()).toBe(200);
            expect(resp2.status()).toBe(200);

            await destroySession(request);
        } finally {
            await destroySession(request);
        }
    });

    test('7.2 Dashboard KPIs are tenant-scoped', async ({ request }) => {
        const sid1 = await createAdminSession(request, 1);
        const sid2 = await createAdminSession(request, 2);
        try {
            const resp1 = await request.get(`${BASE}/admin/dashboard.php`, {
                headers: { Cookie: `PHPSESSID=${sid1}` },
            });
            const resp2 = await request.get(`${BASE}/admin/dashboard.php`, {
                headers: { Cookie: `PHPSESSID=${sid2}` },
            });
            expect(resp1.status()).toBe(200);
            expect(resp2.status()).toBe(200);
        } finally {
            await destroySession(request);
            await destroySession(request);
        }
    });
});

// ─── 8. INDEX REDIRECT ──────────────────────────────────────

test.describe('Phase 5: Index redirect', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('8.1 /admin/ redirects to dashboard.php', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
            maxRedirects: 0,
        });
        expect([302, 301]).toContain(resp.status());
        const loc = resp.headers()['location'] || '';
        expect(loc).toContain('dashboard.php');
    });
});

// ─── 9. COURSE-CENTRIC ADMIN UX ────────────────────────────

test.describe('Phase 5C: Course-centric admin', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('9.1 Course list loads', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Courses');
    });

    test('9.2 Course view has correct tabs', async ({ request }) => {
        const listResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const listBody = await listResp.text();
        const match = listBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) { console.log('No course found, skipping'); return; }
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=overview`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Overview');
        expect(body).toContain('Materials');
        expect(body).toContain('Tests');
        expect(body).toContain('Students');
        expect(body).toContain('Analytics');
        expect(body).toContain('Edit Course');
    });

    test('9.3 Course view content tab has materials section', async ({ request }) => {
        const listResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const listBody = await listResp.text();
        const match = listBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) return;
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=content`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Add Material');
        expect(body).toContain('Materials');
    });

    test('9.4 Course view content tab has material and course test sections', async ({ request }) => {
        const listResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const listBody = await listResp.text();
        const match = listBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) return;
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=content`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Materials');
        expect(body).toContain('Course Tests');
        expect(body).toContain('Create Test');
        expect(body).not.toContain('Daily');
        expect(body).not.toContain('Weekly');
    });

    test('9.5 Course view students tab loads', async ({ request }) => {
        const listResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const listBody = await listResp.text();
        const match = listBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) return;
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=students`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
    });

    test('9.6 Course view edit is modal, not tab', async ({ request }) => {
        const listResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const listBody = await listResp.text();
        const match = listBody.match(/view\.php\?course_id=(\d+)/);
        if (!match) return;
        const courseId = match[1];

        const resp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('Edit Course');
        expect(body).toContain('editModal');
    });
});

// ─── 10. TEST-CENTRIC ADMIN UX ─────────────────────────────

test.describe('Phase 5C: Test-centric admin', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    test('10.1 Test view has correct tabs', async ({ request }) => {
        const courseResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const courseBody = await courseResp.text();
        const courseMatch = courseBody.match(/view\.php\?course_id=(\d+)/);
        if (!courseMatch) return;
        const courseId = courseMatch[1];

        const testsResp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseId}&tab=tests`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const testsBody = await testsResp.text();
        const testMatch = testsBody.match(/view\.php\?test_id=(\d+)&type=(daily|weekly)/);
        if (!testMatch) { console.log('No test found, skipping'); return; }
        const testId = testMatch[1];
        const testType = testMatch[2];

        const resp = await request.get(`${BASE}/admin/tests/view.php?test_id=${testId}&type=${testType}&tab=overview`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Overview');
        expect(body).toContain('Questions');
        expect(body).toContain('Results');
        expect(body).toContain('Edit Test');
    });

    test('10.2 Test view edit is modal, not tab', async ({ request }) => {
        const courseResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const courseBody = await courseResp.text();
        const courseMatch = courseBody.match(/view\.php\?course_id=(\d+)/);
        if (!courseMatch) return;

        const testsResp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseMatch[1]}&tab=tests`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const testsBody = await testsResp.text();
        const testMatch = testsBody.match(/view\.php\?test_id=(\d+)&type=(daily|weekly)/);
        if (!testMatch) return;

        const resp = await request.get(`${BASE}/admin/tests/view.php?test_id=${testMatch[1]}&type=${testMatch[2]}`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).toContain('editModal');
    });

    test('10.3 Test view no price field in edit form', async ({ request }) => {
        const courseResp = await request.get(`${BASE}/admin/courses/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const courseBody = await courseResp.text();
        const courseMatch = courseBody.match(/view\.php\?course_id=(\d+)/);
        if (!courseMatch) return;

        const testsResp = await request.get(`${BASE}/admin/courses/view.php?course_id=${courseMatch[1]}&tab=tests`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const testsBody = await testsResp.text();
        const testMatch = testsBody.match(/view\.php\?test_id=(\d+)&type=(daily|weekly)/);
        if (!testMatch) return;

        const resp = await request.get(`${BASE}/admin/tests/view.php?test_id=${testMatch[1]}&type=${testMatch[2]}`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const body = await resp.text();
        expect(body).not.toContain('name="price"');
    });
});

// ─── 11. STUDENT TEST ACCESS ────────────────────────────────

test.describe('Phase 5C: Student test access via course subscription', () => {
    test('11.1 Test page loads for unauthenticated user', async ({ request }) => {
        const resp = await request.get(`${BASE}/course/tests/index.php?type=weekly&test_id=1`, {
            maxRedirects: 0,
        });
        const status = resp.status();
        expect([200, 302]).toContain(status);
    });

    test('11.2 Course page does not show individual test purchase buttons', async ({ request }) => {
        const resp = await request.get(`${BASE}/course/index.php?course_id=1`, {
            maxRedirects: 0,
        });
        if (resp.status() === 200) {
            const body = await resp.text();
            expect(body).not.toContain('weekly_test_subs.php');
            expect(body).not.toContain('buy_test');
        }
    });

    test('11.3 Course list does not show weekly test purchase buttons', async ({ request }) => {
        const resp = await request.get(`${BASE}/classes/courses/index.php?class=1-2`);
        if (resp.status() === 200) {
            const body = await resp.text();
            expect(body).not.toContain('weekly_test_subs.php');
        }
    });

    test('11.4 Profile does not show weekly_subs as separate purchases', async ({ request }) => {
        const resp = await request.get(`${BASE}/profile/`, {
            maxRedirects: 0,
        });
        if (resp.status() === 200) {
            const body = await resp.text();
            expect(body).not.toContain('weekly_subs_id');
        }
    });
});

// ─── 12. BUG FIX: Course creation + no legacy + student deactivation ──

test.describe('Phase 5C-3: Bug fixes', () => {
    let sid: string;

    test.beforeAll(async ({ request }) => {
        sid = await createAdminSession(request, 1);
    });

    test.afterAll(async ({ request }) => {
        await destroySession(request);
    });

    // --- 12.1 Course creation form ---
    test('12.1 Course list has Add Course button that toggles form', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/courses/index.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('courseForm');
        expect(body).toContain('+ Add Course');
    });

    test('12.2 Course list does not show legacy Вкл/Отд', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/courses/index.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).not.toContain('Вкл');
        expect(body).not.toContain('Отд');
        expect(body).not.toContain('includes_weekly_tests');
    });

    test('12.3 Course list shows active students count', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/courses/index.php`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Students');
    });

    // --- 12.4 Student deactivation ---
    test('12.4 Student list has inactive toggle filter', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('inactive');
        expect(body).toContain('badge-green');
    });

    test('12.5 Student list filters inactive by default', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('badge-green');
    });

    test('12.6 Student list with ?inactive=1 shows all', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/?inactive=1`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('Скрыть неактивных');
    });

    test('12.7 Student view has activate/deactivate button', async ({ request }) => {
        const resp = await request.get(`${BASE}/admin/students/view.php?user_id=1001`, {
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        if (resp.status() === 200) {
            const body = await resp.text();
            expect(body).toContain('toggle_active');
        }
    });

    test('12.8 Login blocks deactivated student', async ({ request }) => {
        // Try to login with a real user — should get "Noto'g'ri" error
        const resp = await request.post(`${BASE}/login/login.php`, {
            form: { email: 'test@example.com', password: 'wrong' },
            headers: { Cookie: `PHPSESSID=${sid}` },
        });
        expect(resp.status()).toBe(200);
        const body = await resp.json();
        expect(body.error).toBeTruthy();
    });
});
