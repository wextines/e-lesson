import { test, expect } from '@playwright/test';
import { spawn, ChildProcess } from 'child_process';

test.describe('React Landing Page & /api/tenant-landing.php Integration', () => {
  let previewProc: ChildProcess;
  const PREVIEW_URL = 'http://localhost:4173';

  test.beforeAll(async () => {
    // Start Vite preview on port 4173
    previewProc = spawn('npx', ['vite', 'preview', '--port', '4173'], {
      cwd: '/Users/macbook/Documents/iq-creative-demo/project',
      stdio: 'pipe',
    });
    // Wait for server to start
    await new Promise((r) => setTimeout(r, 1500));
  });

  test.afterAll(async () => {
    if (previewProc) {
      previewProc.kill();
    }
  });

  test('01. Live API directly returns Demo Academy branding and courses', async ({ page }) => {
    const baseURL = process.env.BASE_URL || 'http://localhost:8080';
    const response = await page.request.get(`${baseURL}/api/tenant-landing.php`, {
      headers: {
        Host: 'demo-academy.e-lesson.uz',
      },
    });
    expect(response.status()).toBe(200);
    const json = await response.json();
    expect(json.success).toBe(true);
    expect(json.tenant.name).toBe('Demo Academy');
    expect(json.tenant.tenant_id).toBe(3);
    expect(json.courses.length).toBeGreaterThan(0);
    expect(json.courses[0].title).toBeTruthy();
  });

  test('02. React App correctly renders dynamic courses and tenant info', async ({ page }) => {
    // Intercept API with Demo Academy payload
    await page.route('**/api/tenant-landing.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          tenant: {
            tenant_id: 3,
            name: 'Demo Academy',
            slug: 'demo-academy',
            logo: null,
            primary_color: '#0e6bdd',
            secondary_color: '#96ace9',
            accent_color: '#f9627b',
            email: 'info@demo-academy.uz',
            phone: '+998902222222',
            default_language: 'ru',
          },
          courses: [
            {
              course_id: 101,
              subject: 'Matematika',
              title: "1-2 sinf Matematika: Boshlang'ich arifmetika",
              description: '1-2 sinflar uchun mantiqiy misol va masalalar',
              price: 300000,
              classLevel: '1-2',
            },
            {
              course_id: 103,
              subject: 'Ingliz tili',
              title: "1-2 sinf Kids English: Alfavit va so'zlashuv",
              description: 'Bolalar uchun qiziqarli ingliz tili darslari',
              price: 320000,
              classLevel: '1-2',
            },
            {
              course_id: 110,
              subject: 'Ingliz tili',
              title: '5-6 sinf Pre-Intermediate Intensive',
              description: "O'rta bosqich ingliz tili va eshitib tushunish",
              price: 450000,
              classLevel: '5-6',
            },
          ],
        }),
      });
    });

    await page.goto(PREVIEW_URL);
    await page.waitForLoadState('networkidle');

    // 1. Verify Dynamic Tenant Branding in Header & Footer
    const header = await page.locator('header').textContent();
    expect(header).toContain('Demo Academy');

    const footer = await page.locator('footer').textContent();
    expect(footer).toContain('Demo Academy');
    expect(footer).toContain('+998902222222');
    expect(footer).toContain('info@demo-academy.uz');

    // 2. Verify Dynamic Courses
    const featuredTitle = await page.locator('#courses h3').first().textContent();
    expect(featuredTitle).toContain("1-2 sinf Matematika: Boshlang'ich arifmetika");

    const courseRows = await page.locator('#courses .border-b').count();
    expect(courseRows).toBe(3);

    // 3. Verify CTA form options
    const options = await page.locator('#cta-course option').allTextContents();
    expect(options.some((o) => o.includes("1-2 sinf Matematika"))).toBe(true);

    // 4. Test Course selection button interactions
    const enrollButtons = page.locator('#courses button:has-text("Записаться")');
    await enrollButtons.nth(2).click();
    await expect(page.locator('#cta-course')).toHaveValue('103');

    // 5. Test Language switching to UZ
    await page.locator('header button:has-text("UZ")').first().click();
    const uzHeroText = await page.locator('main').textContent();
    expect(uzHeroText).toContain('Sinov darsiga yozilish');
  });

  test('03. React App gracefully handles empty courses and error states', async ({ page }) => {
    // 1. Empty courses test
    await page.route('**/api/tenant-landing.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          tenant: { tenant_id: 3, name: 'Demo Academy', slug: 'demo-academy', default_language: 'ru' },
          courses: [],
        }),
      });
    });

    await page.goto(PREVIEW_URL);
    await page.waitForLoadState('networkidle');
    const emptyText = await page.locator('#courses').textContent();
    expect(emptyText).toContain('В данный момент доступных курсов нет');

    // 2. Error state test
    await page.route('**/api/tenant-landing.php', async (route) => {
      await route.abort();
    });

    await page.goto(PREVIEW_URL);
    await page.waitForLoadState('networkidle');
    const errorText = await page.locator('#courses').textContent();
    expect(errorText).toContain('Не удалось загрузить список курсов');
  });
});
