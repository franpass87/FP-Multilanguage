/**
 * E2E Test: Translation Workflow
 * 
 * Tests the complete translation workflow from IT post creation to EN post generation.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

const { test, expect } = require('@playwright/test');

test.describe('Translation Workflow', () => {
  let adminPage;
  let testPostId;
  let testPostSlug;

  test.beforeAll(async ({ browser }) => {
    adminPage = await browser.newPage();
    // Login as admin (adjust credentials as needed)
    await adminPage.goto('/wp-admin');
    await adminPage.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
    await adminPage.fill('#user_pass', process.env.WP_ADMIN_PASS || 'admin');
    await adminPage.click('#wp-submit');
    await adminPage.waitForURL('**/wp-admin/**');
  });

  test('Create Italian post and verify translation', async () => {
    // Navigate to create new post
    await adminPage.goto('/wp-admin/post-new.php');
    
    // Fill post title
    const title = `Test Post IT ${Date.now()}`;
    await adminPage.fill('.editor-post-title__input', title);
    
    // Fill post content
    const content = 'Questo è un post di test in italiano per verificare la traduzione automatica.';
    await adminPage.fill('.block-editor-default-block-appender__content', content);
    
    // Publish post
    await adminPage.click('button:has-text("Pubblica")');
    await adminPage.waitForSelector('.notice-success');
    
    // Get post ID from URL
    const url = adminPage.url();
    const match = url.match(/post=(\d+)/);
    testPostId = match ? match[1] : null;
    
    expect(testPostId).toBeTruthy();
  });

  test('Verify translation metabox appears', async () => {
    if (!testPostId) {
      test.skip();
      return;
    }
    
    await adminPage.goto(`/wp-admin/post.php?post=${testPostId}&action=edit`);
    
    // Check for translation metabox
    const metabox = adminPage.locator('#fpml-translation-metabox');
    await expect(metabox).toBeVisible();
  });

  test('Trigger translation and verify EN post creation', async () => {
    if (!testPostId) {
      test.skip();
      return;
    }
    
    await adminPage.goto(`/wp-admin/post.php?post=${testPostId}&action=edit`);
    
    // Click translate button
    const translateButton = adminPage.locator('button:has-text("Traduci")');
    if (await translateButton.isVisible()) {
      await translateButton.click();
      
      // Wait for translation to complete (adjust timeout as needed)
      await adminPage.waitForSelector('.fpml-translation-status.completed', { timeout: 60000 });
      
      // Verify EN post exists
      const enPostLink = adminPage.locator('a[href*="/wp-admin/post.php?post="]');
      await expect(enPostLink).toBeVisible();
    }
  });

  test('Verify EN post URL structure', async ({ page }) => {
    if (!testPostId) {
      test.skip();
      return;
    }
    
    // Get IT post slug
    await adminPage.goto(`/wp-admin/post.php?post=${testPostId}&action=edit`);
    const permalink = await adminPage.locator('#sample-permalink a').getAttribute('href');
    testPostSlug = permalink ? permalink.split('/').filter(Boolean).pop() : null;
    
    if (testPostSlug) {
      // Visit EN version
      await page.goto(`/en/${testPostSlug}/`);
      
      // Verify page loads
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test.afterAll(async () => {
    if (adminPage) {
      await adminPage.close();
    }
  });
});














