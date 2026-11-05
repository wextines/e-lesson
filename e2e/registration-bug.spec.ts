import { test, expect, APIRequestContext } from '@playwright/test';
import { execSync } from 'child_process';

const BASE = 'http://localhost:8080';

async function apiGet(request: APIRequestContext, params: string): Promise<string> {
    const resp = await request.get(`${BASE}/_test_helper.php?${params}`);
    return (await resp.text()).trim();
}

async function userCount(request: APIRequestContext, email: string): Promise<number> {
    return parseInt(await apiGet(request, `action=count&email=${encodeURIComponent(email)}`));
}

async function cleanupUser(request: APIRequestContext, email: string) {
    await apiGet(request, `action=cleanup&email=${encodeURIComponent(email)}`);
}

async function createVerifiedSession(request: APIRequestContext, email: string): Promise<string> {
    const resp = await request.get(`${BASE}/_create_sess.php?e=${encodeURIComponent(email)}`);
    return (await resp.text()).trim();
}

test.beforeAll(async ({}) => {
    const helperScript = [
        '<?php',
        'header("Content-Type: text/plain");',
        'require "/var/www/html/php/config.php";',
        '$action = $_GET["action"] ?? "";',
        '$email = $_GET["email"] ?? "";',
        'switch ($action) {',
        '    case "count":',
        '        $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");',
        '        $s->execute([$email]);',
        '        echo $s->fetchColumn();',
        '        break;',
        '    case "cleanup":',
        '        $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);',
        '        echo "ok";',
        '        break;',
        '    default:',
        '        echo "unknown";',
        '}',
    ].join('\n');

    const sessionScript = [
        '<?php',
        'header("Content-Type: text/plain");',
        'session_set_cookie_params(["lifetime" => 604800, "path" => "/", "httponly" => true, "secure" => false, "samesite" => "Lax"]);',
        'session_start();',
        '$_SESSION["reg_first_name"] = "Test";',
        '$_SESSION["reg_last_name"] = "User";',
        '$_SESSION["reg_email"] = $_GET["e"] ?? "bad@example.com";',
        '$_SESSION["reg_email_verified"] = true;',
        'session_write_close();',
        'echo session_id();',
    ].join('\n');

    execSync(
        `docker compose exec -T web bash -c "cat > /var/www/html/_test_helper.php"`,
        { input: helperScript, encoding: 'utf-8', timeout: 5000 }
    );
    execSync(
        `docker compose exec -T web bash -c "cat > /var/www/html/_create_sess.php"`,
        { input: sessionScript, encoding: 'utf-8', timeout: 5000 }
    );
});

test.afterAll(async ({}) => {
    try {
        execSync(
            `docker compose exec -T web rm -f /var/www/html/_test_helper.php /var/www/html/_create_sess.php`,
            { encoding: 'utf-8', timeout: 5000 }
        );
    } catch (e) { /* ignore */ }
});

// ─── 1. NORMAL REGISTRATION FLOW ──────────────────────────────

test.describe('Registration: Normal Flow', () => {
    test('1.1 Full registration via UI completes successfully', async ({ page, request }) => {
        const timestamp = Date.now();
        const testEmail = `normflow${timestamp}@example.com`;
        await cleanupUser(request, testEmail);

        await page.goto(`${BASE}/`);
        await page.waitForLoadState('networkidle');

        await page.evaluate(() => (window as any).openModal());
        await page.waitForTimeout(500);

        await page.locator('#reg_first_name').fill('NormalUser');
        await page.locator('#reg_last_name').fill('FlowTest');
        await page.locator('#reg_email').fill(testEmail);
        await page.locator('#regStep1Form button[type="submit"]').click();
        await page.waitForTimeout(3000);

        await expect(page.locator('#reg_code')).toBeVisible({ timeout: 5000 });

        const cookies = await page.context().cookies();
        const sc = cookies.find(c => c.name === 'PHPSESSID');
        let code = '000000';
        if (sc) {
            try {
                const data = execSync(
                    `docker compose exec -T web cat /tmp/sess_${sc.value}`,
                    { encoding: 'utf-8', timeout: 5000 }
                );
                const m = data.match(/verification_code\|i:(\d+)/);
                if (m) code = m[1];
            } catch (e) { /* ignore */ }
        }

        await page.locator('#reg_code').fill(code);
        await page.locator('#regStep2Form button[type="submit"]').click();
        await page.waitForTimeout(3000);

        await page.locator('#reg_password').fill('Test1234');
        await page.locator('#reg_confirm_password').fill('Test1234');
        await page.locator('#regStep3Form button[type="submit"]').click();
        await page.waitForTimeout(3000);

        expect(await userCount(request, testEmail)).toBe(1);
        await cleanupUser(request, testEmail);
    });
});

// ─── 2. DUPLICATE EMAIL ───────────────────────────────────────

test.describe('Registration: Duplicate Email', () => {
    test('2.1 Duplicate email returns clear error', async ({ request }) => {
        const timestamp = Date.now();
        const testEmail = `dup${timestamp}@example.com`;
        await cleanupUser(request, testEmail);

        const sid1 = await createVerifiedSession(request, testEmail);
        expect(sid1).toBeTruthy();

        const createResp = await request.post(`${BASE}/login/register.php`, {
            form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
            headers: { Cookie: `PHPSESSID=${sid1}` },
        });
        const createBody = await createResp.json();
        expect(createBody.success).toBe(true);
        expect(await userCount(request, testEmail)).toBe(1);

        const sid2 = await createVerifiedSession(request, testEmail);
        expect(sid2).toBeTruthy();

        const dupResp = await request.post(`${BASE}/login/register.php`, {
            form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
            headers: { Cookie: `PHPSESSID=${sid2}` },
        });
        const dupBody = await dupResp.json();
        expect(dupBody.error).toContain('allaqachon');
        expect(await userCount(request, testEmail)).toBe(1);

        await cleanupUser(request, testEmail);
    });
});

// ─── 3. DOUBLE-SUBMIT RACE CONDITION ──────────────────────────

test.describe('Registration: Double-Submit Race Condition', () => {
    test('3.1 Two concurrent create_account — both succeed, one user', async ({ request }) => {
        const timestamp = Date.now();
        const testEmail = `race${timestamp}@example.com`;
        await cleanupUser(request, testEmail);

        const sid1 = await createVerifiedSession(request, testEmail);
        const sid2 = await createVerifiedSession(request, testEmail);
        expect(sid1).toBeTruthy();
        expect(sid2).toBeTruthy();

        const [resp1, resp2] = await Promise.all([
            request.post(`${BASE}/login/register.php`, {
                form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
                headers: { Cookie: `PHPSESSID=${sid1}` },
            }),
            request.post(`${BASE}/login/register.php`, {
                form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
                headers: { Cookie: `PHPSESSID=${sid2}` },
            }),
        ]);

        const body1 = await resp1.json();
        const body2 = await resp2.json();

        console.log('Response 1:', JSON.stringify(body1));
        console.log('Response 2:', JSON.stringify(body2));

        expect(resp1.status()).toBe(200);
        expect(resp2.status()).toBe(200);
        expect(body1.success || body2.success).toBe(true);
        expect(await userCount(request, testEmail)).toBe(1);

        await cleanupUser(request, testEmail);
    });

    test('3.2 After double-submit, user exists and is consistent', async ({ request }) => {
        const timestamp = Date.now();
        const testEmail = `race2${timestamp}@example.com`;
        await cleanupUser(request, testEmail);

        const sid1 = await createVerifiedSession(request, testEmail);
        const sid2 = await createVerifiedSession(request, testEmail);

        await Promise.all([
            request.post(`${BASE}/login/register.php`, {
                form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
                headers: { Cookie: `PHPSESSID=${sid1}` },
            }),
            request.post(`${BASE}/login/register.php`, {
                form: { action: 'create_account', password: 'Test1234', confirm_password: 'Test1234' },
                headers: { Cookie: `PHPSESSID=${sid2}` },
            }),
        ]);

        expect(await userCount(request, testEmail)).toBe(1);

        await cleanupUser(request, testEmail);
    });
});
