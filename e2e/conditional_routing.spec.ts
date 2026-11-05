import { test, expect } from '@playwright/test';

test.describe('Conditional Root Routing for Multi-Tenancy', () => {
  const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

  test('01. HTTP Request with Demo Academy host returns React landing HTML', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/`, {
      headers: { Host: 'demo-academy.e-lesson.uz' },
    });
    expect(res.status()).toBe(200);
    const html = await res.text();
    // Verify React index.html markers
    expect(html).toContain('<div id="root"></div>');
    expect(html).toContain('/assets/index-');
    expect(html).not.toContain('class="main-nav"');
  });

  test('02. HTTP Request with IQ-CREATIVE host returns untouched legacy PHP landing', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/`, {
      headers: { Host: 'iq-creative.e-lesson.uz' },
    });
    expect(res.status()).toBe(200);
    const html = await res.text();
    // Verify legacy markers
    expect(html).toContain('class="main-nav"');
    expect(html).toContain('IQ-CREATIVE');
    expect(html).not.toContain('<div id="root"></div>');
  });

  test('03. HTTP Request with Test Academy host returns untouched legacy PHP landing', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/`, {
      headers: { Host: 'test-academy.e-lesson.uz' },
    });
    expect(res.status()).toBe(200);
    const html = await res.text();
    // Verify legacy markers
    expect(html).toContain('class="main-nav"');
    expect(html).toContain('Test Academy');
    expect(html).not.toContain('<div id="root"></div>');
  });

  test('04. Browser UI: Default root URL renders untouched legacy landing', async ({ page }) => {
    await page.goto(`${BASE_URL}/`);
    await page.waitForLoadState('networkidle');

    // Default tenant is IQ-CREATIVE (Tenant #1)
    const rootDiv = page.locator('#root');
    await expect(rootDiv).toHaveCount(0);

    const navText = await page.locator('.main-nav').textContent();
    expect(navText).toContain('IQ-CREATIVE');
    expect(navText).toMatch(/(Shaxsiy kabinet|Личный кабинет)/);
  });

  test('05. Browser UI: Demo Academy session renders full React App with dynamic courses', async ({ page }) => {
    // Set Tenant #3 session cookie
    await page.goto(`${BASE_URL}/_test_create_session.php?tenant_id=3`);
    await page.waitForTimeout(300);

    // Navigate to root with active Tenant #3 session
    await page.goto(`${BASE_URL}/`);
    await page.waitForLoadState('networkidle');

    // 1. Verify React root is mounted and visible
    const rootDiv = page.locator('#root');
    await expect(rootDiv).toBeVisible();

    // 2. Verify dynamic Demo Academy branding in Header & Footer
    const header = await page.locator('header').textContent();
    expect(header).toContain('Demo Academy');

    const footer = await page.locator('footer').textContent();
    expect(footer).toContain('Demo Academy');
    expect(footer).toContain('+998902222222');

    // 3. Verify dynamic courses loaded via /api/tenant-landing.php
    const coursesSection = page.locator('#courses');
    await expect(coursesSection).toBeVisible();
    const coursesText = await coursesSection.textContent();
    expect(coursesText).toContain('1-2 sinf Matematika');
  });

  test('06. Core routes remain intact and functional', async ({ page }) => {
    // Admin login route
    const adminRes = await page.goto(`${BASE_URL}/admin/`);
    expect(adminRes?.status()).toBe(200);
    expect(page.url()).toContain('/admin/php/login.php');

    // Login endpoint
    const loginRes = await page.request.post(`${BASE_URL}/login/login.php`, {
      form: { email: '', password: '' },
    });
    expect(loginRes.status()).toBe(200);
    const loginJson = await loginRes.json();
    expect(loginJson.error).toBeTruthy();

    // File security route
    const fileRes = await page.request.get(`${BASE_URL}/file.php?type=question&id=999999`);
    expect([400, 401, 403, 404, 500]).toContain(fileRes.status());
  });
});
