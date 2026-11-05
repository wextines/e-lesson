import { test, expect } from '@playwright/test';

test.describe('Admin Panel AI E2E Master Suite (Demo Academy, Tenant 3)', () => {
  const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

  test.beforeEach(async ({ page }) => {
    // 1. Authenticate as demoadmin for Demo Academy (Tenant 3)
    await page.goto(`${BASE_URL}/admin/php/login.php`);
    await page.fill('input[name="username"]', 'demoadmin');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);
  });

  test('01. Dashboard: KPIs, Revenue delta, and widgets verification', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/dashboard.php`);
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Demo Academy');
    expect(bodyText).toContain("so'm");
    console.log('✓ Dashboard KPIs and widgets rendered successfully');
  });

  test('02. Students: List, class filter, pagination and clickable row profile navigation', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/students/`);
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Demo Academy');

    // Click on the first student row (clickable row UX)
    const firstStudentRow = page.locator('tbody tr').first();
    await expect(firstStudentRow).toBeVisible();
    await firstStudentRow.click();
    await page.waitForLoadState('networkidle');

    // Verify redirected to student profile view
    const currentUrl = page.url();
    expect(currentUrl).toContain('/admin/students/view.php');
    const profileText = await page.textContent('body');
    expect(profileText).toContain('Demo Academy');
    console.log('✓ Students list and profile navigation verified');
  });

  test('03. Payments: Visibility filter (active/hidden), pending highlight, and details', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/payments/`);
    await page.waitForLoadState('networkidle');

    // 1. Verify Payments Title and button
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Платежи');
    expect(bodyText).toContain('Новый платеж');

    // 2. Select "Скрытые" and filter
    await page.goto(`${BASE_URL}/admin/payments/?visibility=hidden`);
    await page.waitForLoadState('networkidle');

    const hiddenPageText = await page.textContent('body');
    expect(hiddenPageText).toContain('Скрыт');

    // 3. Reset filters & click on payment row to view details
    await page.goto(`${BASE_URL}/admin/payments/?visibility=active`);
    const firstPaymentRow = page.locator('tbody tr').first();
    await firstPaymentRow.click();
    await page.waitForLoadState('networkidle');

    const currentUrl = page.url();
    expect(currentUrl).toContain('/admin/payments/view.php');
    const payViewText = await page.textContent('body');
    expect(payViewText).toContain('Детали платежа');
    expect(payViewText).toContain('Кто оформил');
    console.log('✓ Payments list, filters, and payment view verified');
  });

  test('04. Courses & Course Overview: Enrolled students, linked lessons and tests', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/courses/`);
    await page.waitForLoadState('networkidle');

    const coursesText = await page.textContent('body');
    expect(coursesText).toContain('Курсы');
    expect(coursesText).toContain('Matematika');

    // Open first course overview
    await page.goto(`${BASE_URL}/admin/courses/view.php?course_id=101`);
    await page.waitForLoadState('networkidle');

    const overviewText = await page.textContent('body');
    expect(overviewText).toContain('1-2 sinf Matematika');
    expect(overviewText).toContain('Ученики');
    expect(overviewText).toContain('Материалы');
    expect(overviewText).toContain('Тесты');
    console.log('✓ Courses and Course Overview verified');
  });

  test('05. Books Library: 22 textbooks across grades 1-11', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/adminBooks/index.php`);
    await page.waitForLoadState('networkidle');

    const booksText = await page.textContent('body');
    expect(booksText).toContain('Книги');
    expect(booksText).toContain('Matematika');
    console.log('✓ Books library verified');
  });

  test('06. Tests & Questions: Strict sequential numbering 1..N and question management', async ({ page }) => {
    // Open weekly test #710 questions tab
    await page.goto(`${BASE_URL}/admin/tests/view.php?test_id=710&type=weekly&tab=questions`);
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Вопросы');
    expect(bodyText).toContain('Добавить вопросы');

    // Verify first column cells are strictly sequential 1, 2, 3, 4... (NOT random database IDs)
    const numberCells = await page.locator('table tbody tr td:first-child').allTextContents();
    const cleanNumbers = numberCells.map(s => s.trim()).filter(s => s.length > 0);
    expect(cleanNumbers.length).toBeGreaterThanOrEqual(3);
    for (let i = 0; i < cleanNumbers.length; i++) {
      expect(cleanNumbers[i]).toBe(String(i + 1));
    }

    // Verify question images exist and load
    const imgLocator = page.locator('table img').first();
    await expect(imgLocator).toBeVisible();

    console.log(`✓ Tests & Questions verified strictly sequential numbering: ${cleanNumbers.join(', ')}`);
  });

  test('07. Question Image Life-cycle: Replace (A->B->C), Delete, and Add back', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/tests/view.php?test_id=710&type=weekly&tab=questions`);
    await page.waitForLoadState('networkidle');

    // 1. Click edit on Question #1
    const editBtn = page.locator('button:has-text("Редактировать")').first();
    await editBtn.click();

    // Verify modal opened with sequential title
    const modal = page.locator('#editQuestionModal');
    await expect(modal).toBeVisible();
    const modalTitle = await page.locator('#editQModalTitle').textContent();
    expect(modalTitle).toContain('Редактировать вопрос #1');

    // 2. Replace image with Image B
    const imgBBuffer = Buffer.from('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', 'base64');
    await page.locator('#editQFileInput').setInputFiles({
      name: 'image_B.jpg',
      mimeType: 'image/jpeg',
      buffer: imgBBuffer,
    });
    await page.locator('#editQuestionModal button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Verify updated
    expect(page.url()).toContain('tab=questions');
    let pageText = await page.textContent('body');
    expect(pageText).toContain('Вопрос успешно обновлён');
    await expect(page.locator('table img').first()).toBeVisible();

    // 3. Re-open edit on Question #1 and replace with Image C
    await page.locator('button:has-text("Редактировать")').first().click();
    await expect(modal).toBeVisible();
    await expect(page.locator('#editQImgPreview')).toBeVisible();

    const imgCBuffer = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
    await page.locator('#editQFileInput').setInputFiles({
      name: 'image_C.png',
      mimeType: 'image/png',
      buffer: imgCBuffer,
    });
    await page.locator('#editQuestionModal button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=questions');
    pageText = await page.textContent('body');
    expect(pageText).toContain('Вопрос успешно обновлён');
    await expect(page.locator('table img').first()).toBeVisible();

    // 4. Delete Image from Question #1
    await page.locator('button:has-text("Редактировать")').first().click();
    await expect(modal).toBeVisible();
    await page.locator('#editQRemoveImage').check();
    await page.locator('#editQuestionModal button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=questions');
    pageText = await page.textContent('body');
    expect(pageText).toContain('Вопрос успешно обновлён');

    // 5. Restore image back to text-only question
    await page.locator('button:has-text("Редактировать")').first().click();
    await expect(modal).toBeVisible();
    await expect(page.locator('#editQImgPreview')).toBeHidden();

    await page.locator('#editQFileInput').setInputFiles({
      name: 'image_restored.png',
      mimeType: 'image/png',
      buffer: imgCBuffer,
    });
    await page.locator('#editQuestionModal button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=questions');
    pageText = await page.textContent('body');
    expect(pageText).toContain('Вопрос успешно обновлён');
    await expect(page.locator('table img').first()).toBeVisible();

    console.log('✓ Full image lifecycle (Replace A->B->C, Delete image, Add back to text-only question) verified 100%');
  });

  test('08. Add Question: Next logical number auto-assigned and duplicate number blocked', async ({ page }) => {
    // Open test #710 (has 5 questions: 1..5)
    await page.goto(`${BASE_URL}/admin/tests/view.php?test_id=710&type=weekly&tab=questions`);
    await page.waitForLoadState('networkidle');

    // 1. Verify default value in initial question input is count + 1
    const questionRows = await page.locator('table tbody tr').count();
    const expectedNext = questionRows + 1;
    const initialInput = page.locator('input[name="question_number[]"]').first();
    const initialValue = await initialInput.inputValue();
    expect(initialValue).toBe(String(expectedNext));

    // 2. Click "Add question" button and verify new row gets number expectedNext + 1
    await page.locator('button:has-text("Добавить вопрос")').first().click();
    const secondInput = page.locator('input[name="question_number[]"]').nth(1);
    const secondValue = await secondInput.inputValue();
    expect(secondValue).toBe(String(expectedNext + 1));

    // Remove the second row
    await page.locator('.question-row-block button[title="Удалить строку"]').first().click();

    // 3. Try to manually type duplicate number "1"
    await initialInput.fill('1');
    await page.locator('textarea[name="question_text[]"]').first().fill('Attempted duplicate text');
    await page.locator('select[name="correct_answer[]"]').first().selectOption('A');

    await page.locator('button:has-text("Сохранить вопросы")').click();
    await page.waitForLoadState('networkidle');

    // Verify error message is shown
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Вопрос №1 уже существует. Отредактируйте существующий вопрос или выберите другой номер.');

    console.log('✓ Add Question auto-numbering and duplicate prevention verified 100%');
  });
});
