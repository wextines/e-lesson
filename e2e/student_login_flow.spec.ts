import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';
const DEMO_ACADEMY_BASE = 'http://demo-academy.e-lesson.uz:8080';

test.describe('Student Authentication and Routing Flow', () => {

    test('1. Unauthenticated /profile/ redirects to /login/', async ({ request }) => {
        const res = await request.get(`${BASE}/profile/`, { maxRedirects: 0 });
        expect(res.status()).toBe(302);
        expect(res.headers()['location']).toBe('/login/');
    });

    test('2. /login/ loads with 200 OK and IQ-CREATIVE branding on localhost', async ({ page }) => {
        const res = await page.goto(`${BASE}/login/`);
        expect(res?.status()).toBe(200);
        
        await expect(page.locator('h1')).toContainText('Вход в кабинет');
        await expect(page.locator('header')).toContainText('IQ-CREATIVE');
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
        await expect(page.locator('button#submitBtn')).toBeVisible();
    });

    test('3. /login (without trailing slash) redirects to /login/', async ({ page }) => {
        await page.goto(`${BASE}/login`);
        expect(page.url()).toBe(`${BASE}/login/`);
        await expect(page.locator('h1')).toContainText('Вход в кабинет');
    });

    test('4. Complete login flow on localhost:8080 → /profile/', async ({ page }) => {
        await page.goto(`${BASE}/login/`);
        
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        
        await page.waitForURL(`${BASE}/profile/`);
        expect(page.url()).toBe(`${BASE}/profile/`);
        
        // Verify student cabinet dashboard loaded for Akmal
        await expect(page.locator('header')).toContainText('Akmal');
        await expect(page.getByRole('heading', { name: 'Мои курсы' })).toBeVisible();
    });

    test('5. Tenant #3 /login/ loads Demo Academy branding', async ({ page }) => {
        const res = await page.goto(`${DEMO_ACADEMY_BASE}/login/`);
        expect(res?.status()).toBe(200);
        
        await expect(page.locator('header')).toContainText('Demo Academy');
        await expect(page.locator('footer')).toContainText('Demo Academy');
    });

    test('6. Complete login flow on Demo Academy → /profile/', async ({ page }) => {
        await page.goto(`${DEMO_ACADEMY_BASE}/login/`);
        
        await page.fill('input#email', 'gulnoza.xolmatova2108@demo.uz');
        await page.fill('input#password', 'demo_pass');
        await page.click('button#submitBtn');
        
        await page.waitForURL(`${DEMO_ACADEMY_BASE}/profile/`);
        expect(page.url()).toBe(`${DEMO_ACADEMY_BASE}/profile/`);
        
        // Verify student cabinet dashboard loaded in Tenant #3 context
        await expect(page.locator('header')).toContainText('Gulnoza');
        await expect(page.locator('aside')).toContainText('Demo Academy');
    });

    test('7. Authenticated user accessing /login/ redirects to /profile/', async ({ page }) => {
        // First log in
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        await page.waitForURL(`${BASE}/profile/`);
        
        // Now try visiting /login/ again
        await page.goto(`${BASE}/login/`);
        expect(page.url()).toBe(`${BASE}/profile/`);
    });

    test('8. Clean URLs work without .php extension', async ({ page }) => {
        // Log in
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        await page.waitForURL(`${BASE}/profile/`);
        
        // Test /profile/courses
        const coursesRes = await page.goto(`${BASE}/profile/courses`);
        expect(coursesRes?.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('Мои курсы');
        
        // Test /profile/tests
        const testsRes = await page.goto(`${BASE}/profile/tests`);
        expect(testsRes?.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('Тесты');
        
        // Test /profile/settings
        const settingsRes = await page.goto(`${BASE}/profile/settings`);
        expect(settingsRes?.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('Профиль');
    });

    test('9. Legacy redirects continue to function', async ({ page }) => {
        // Log in
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'Demo1234');
        await page.click('button#submitBtn');
        await page.waitForURL(`${BASE}/profile/`);
        
        await page.goto(`${BASE}/profile/topup/`);
        expect(page.url()).toContain('/profile/payments.php');
        
        await page.goto(`${BASE}/profile/payments/`);
        expect(page.url()).toContain('/profile/payments.php');
        
        await page.goto(`${BASE}/profile/edit/`);
        expect(page.url()).toContain('/profile/settings.php');
    });

    test('10. Invalid credentials show clear error message', async ({ page }) => {
        await page.goto(`${BASE}/login/`);
        await page.fill('input#email', 'demo@example.local');
        await page.fill('input#password', 'IncorrectPassword123');
        await page.click('button#submitBtn');
        
        await expect(page.locator('#errorMessage')).toBeVisible();
        await expect(page.locator('#errorMessage')).toContainText('Неверный email или пароль!');
        // Ensure email input value is preserved
        expect(await page.inputValue('input#email')).toBe('demo@example.local');
    });
});
