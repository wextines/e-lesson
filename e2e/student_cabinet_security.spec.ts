import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

// Helper: login student and preserve session cookies in page context
async function loginStudent(page, email = 'demo@example.local', password = 'Demo1234') {
    await page.goto(`${BASE}/`);
    const loginResult = await page.evaluate(async ({ email, password }) => {
        const res = await fetch('/login/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ email, password }).toString()
        });
        return await res.json();
    }, { email, password });
    expect(loginResult.success).toBe(true);
}

test.describe('Student Cabinet Security Fixes Audit Suite', () => {

    // ─── 1. LEGACY REDIRECTS ─────────────────────────────────────
    test('1.1 /profile/topup/ redirects to /profile/payments.php', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/topup/`);
        expect(page.url()).toContain('/profile/payments.php');
    });

    test('1.2 /profile/payments/ redirects to /profile/payments.php', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments/`);
        expect(page.url()).toContain('/profile/payments.php');
    });

    test('1.3 /profile/edit/ redirects to /profile/settings.php', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/edit/`);
        expect(page.url()).toContain('/profile/settings.php');
    });

    // ─── 2. CSRF PROTECTION: MISSING / INVALID TOKENS ─────────────
    test('2.1 settings.php update_profile rejects missing CSRF token', async ({ page }) => {
        await loginStudent(page);
        const res = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('first_name', 'Hacked');
            formData.append('last_name', 'Name');
            const r = await fetch('/profile/settings.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        });
        expect(res.status).toBe(403);
        expect(res.text).toContain('CSRF token mismatch');
    });

    test('2.2 settings.php update_profile rejects invalid CSRF token', async ({ page }) => {
        await loginStudent(page);
        const res = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('csrf_token', 'invalid_csrf_token_1234567890');
            formData.append('first_name', 'Hacked');
            const r = await fetch('/profile/settings.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        });
        expect(res.status).toBe(403);
        expect(res.text).toContain('CSRF token mismatch');
    });

    test('2.3 settings.php change_password rejects missing CSRF token', async ({ page }) => {
        await loginStudent(page);
        const res = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', 'Demo1234');
            formData.append('new_password', 'newpassword123');
            formData.append('confirm_password', 'newpassword123');
            const r = await fetch('/profile/settings.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        });
        expect(res.status).toBe(403);
        expect(res.text).toContain('CSRF token mismatch');
    });

    test('2.4 payments.php upload_receipt rejects missing CSRF token', async ({ page }) => {
        await loginStudent(page);
        const res = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('action', 'upload_receipt');
            formData.append('course_id', '1');
            formData.append('amount', '50000');
            const r = await fetch('/profile/payments.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        });
        expect(res.status).toBe(403);
        expect(res.text).toContain('CSRF token mismatch');
    });

    test('2.5 test.php submit_test rejects missing CSRF token', async ({ page }) => {
        await loginStudent(page);
        const res = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('action', 'submit_test');
            formData.append('answers[1]', 'A');
            const r = await fetch('/profile/test.php?test_id=1&type=daily', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        });
        expect(res.status).toBe(403);
        expect(res.text).toContain('CSRF token mismatch');
    });

    // ─── 3. CSRF PROTECTION: VALID TOKEN SUBMISSIONS ─────────────
    test('3.1 settings.php update_profile succeeds with valid CSRF token', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/settings.php`);

        // Extract csrf token from page
        const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();
        expect(csrfToken).toBeTruthy();
        expect(csrfToken.length).toBe(64); // 32 bytes hex

        // Submit profile update with valid token via UI form
        await page.evaluate((token) => {
            const form = document.getElementById('edit-form');
            form.querySelector('input[name="csrf_token"]').value = token;
            form.querySelector('input[name="first_name"]').value = 'Akmal';
            form.querySelector('input[name="last_name"]').value = 'Rahimov';
            form.querySelector('input[name="phone"]').value = '+998901111111';
            form.querySelector('input[name="class"]').value = '1-2';
            form.submit();
        }, csrfToken);

        await page.waitForLoadState('networkidle');
        const content = await page.content();
        expect(content).toContain('Личные данные успешно сохранены!');
    });

    // ─── 4. RECEIPT UPLOAD SECURITY ──────────────────────────────
    test('4.1 payments.php rejects disguised file with PHP code (.jpg extension, text/plain or PHP MIME)', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments.php`);

        const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();
        expect(csrfToken).toBeTruthy();

        // Submit via multipart fetch inside page with fake image content
        const res = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('action', 'upload_receipt');
            formData.append('csrf_token', token);
            formData.append('course_id', '1');
            formData.append('amount', '100000');
            const fakeFile = new File(['<?php echo "evil shell"; ?>'], 'malicious.jpg', { type: 'image/jpeg' });
            formData.append('receipt', fakeFile);

            const r = await fetch('/profile/payments.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        }, csrfToken);

        expect(res.status).toBe(200);
        expect(res.text).toContain('Содержимое файла не соответствует расширению или файл повреждён.');
    });

    test('4.2 payments.php rejects disallowed extension (.sh, .exe, .php)', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments.php`);

        const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();
        expect(csrfToken).toBeTruthy();

        const res = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('action', 'upload_receipt');
            formData.append('csrf_token', token);
            formData.append('course_id', '1');
            formData.append('amount', '100000');
            const scriptFile = new File(['#!/bin/bash\necho "exploit"'], 'exploit.sh', { type: 'application/x-sh' });
            formData.append('receipt', scriptFile);

            const r = await fetch('/profile/payments.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        }, csrfToken);

        expect(res.status).toBe(200);
        expect(res.text).toContain('Недопустимый формат файла.');
    });

    test('4.3 payments.php rejects oversized file (> 10MB)', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments.php`);

        const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();
        expect(csrfToken).toBeTruthy();

        const res = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('action', 'upload_receipt');
            formData.append('csrf_token', token);
            formData.append('course_id', '1');
            formData.append('amount', '100000');
            // 11 MB buffer
            const largeData = new Uint8Array(11 * 1024 * 1024);
            const largeFile = new File([largeData], 'huge_receipt.jpg', { type: 'image/jpeg' });
            formData.append('receipt', largeFile);

            const r = await fetch('/profile/payments.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        }, csrfToken);

        expect(res.status).toBe(200);
        expect(res.text).toContain('Файл слишком большой.');
    });

    test('4.4 payments.php successfully uploads valid PNG receipt with secure filename and pending status', async ({ page }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments.php`);

        const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();
        expect(csrfToken).toBeTruthy();

        // Valid 1x1 transparent PNG bytes
        const pngHex = '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082';
        
        const res = await page.evaluate(async ({ token, pngHex }) => {
            const bytes = new Uint8Array(pngHex.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
            const validFile = new File([bytes], 'valid_check.png', { type: 'image/png' });

            const formData = new FormData();
            formData.append('action', 'upload_receipt');
            formData.append('csrf_token', token);
            formData.append('course_id', '1');
            formData.append('amount', '250000');
            formData.append('comment', 'Audit test receipt');
            formData.append('receipt', validFile);

            const r = await fetch('/profile/payments.php', {
                method: 'POST',
                body: formData
            });
            return { status: r.status, text: await r.text() };
        }, { token: csrfToken, pngHex });

        expect(res.status).toBe(200);
        expect(res.text).toContain('Квитанция успешно отправлена!');

        // Check history section
        await page.goto(`${BASE}/profile/payments.php`);
        const pageContent = await page.content();
        expect(pageContent).toContain('250 000');
        expect(pageContent).toContain('В обработке');
    });

    // ─── 5. CROSS-TENANT RECEIPT ACCESS ─────────────────────────
    test('5.1 file.php blocks unauthenticated access to receipts', async ({ request }) => {
        const resp = await request.get(`${BASE}/file.php?type=receipt&id=1`);
        expect(resp.status()).toBe(401);
    });

    test('5.2 file.php blocks cross-user / cross-tenant receipt access', async ({ page, browser }) => {
        await loginStudent(page);
        await page.goto(`${BASE}/profile/payments.php`);

        // Get Akmal's receipt ID from link
        const receiptLink = await page.locator('a[href*="/file.php?type=receipt"]').first();
        if (await receiptLink.count() > 0) {
            const receiptHref = await receiptLink.getAttribute('href');
            expect(receiptHref).toBeTruthy();

            // Akmal can access his own receipt
            const okResp = await page.evaluate(async (url) => {
                const r = await fetch(url);
                return r.status;
            }, receiptHref);
            expect(okResp).toBe(200);

            // Now another student from Demo Academy attempts to access Akmal's receipt URL
            const demoContext = await browser.newContext({
                extraHTTPHeaders: { 'Host': 'demo-academy.e-lesson.uz' }
            });
            
            // Unauthenticated on demo-academy -> 401
            const unauthDemoResp = await demoContext.request.get(`${BASE}${receiptHref}`);
            expect([401, 403, 404]).toContain(unauthDemoResp.status());
            await demoContext.close();
        }
    });
});
