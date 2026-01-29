/**
 * E2E Test: Admin Dashboard
 * 
 * Tests the admin dashboard functionality.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

const { test, expect } = require('@playwright/test');

test.describe('Admin Dashboard', () => {
  let adminPage;

  test.beforeAll(async ({ browser }) => {
    adminPage = await browser.newPage();
    await adminPage.goto('/wp-admin');
    await adminPage.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
    await adminPage.fill('#user_pass', process.env.WP_ADMIN_PASS || 'admin');
    await adminPage.click('#wp-submit');
    await adminPage.waitForURL('**/wp-admin/**');
  });

  test('Access FP Multilanguage menu', async () => {
    await adminPage.goto('/wp-admin');
    
    // Check for FP Multilanguage menu item
    const menuItem = adminPage.locator('#toplevel_page_fpml-dashboard');
    await expect(menuItem).toBeVisible();
  });

  test('View dashboard statistics', async () => {
    await adminPage.goto('/wp-admin/admin.php?page=fpml-dashboard');
    
    // Check for statistics sections
    await expect(adminPage.locator('.fpml-stats')).toBeVisible();
  });

  test('Navigate to settings page', async () => {
    await adminPage.goto('/wp-admin/admin.php?page=fpml-dashboard');
    
    // Click settings link
    const settingsLink = adminPage.locator('a[href*="page=fpml-settings"]');
    if (await settingsLink.isVisible()) {
      await settingsLink.click();
      await expect(adminPage).toHaveURL(/.*page=fpml-settings/);
    }
  });

  test('Access bulk translator', async () => {
    await adminPage.goto('/wp-admin/admin.php?page=fpml-bulk-translator');
    
    // Verify bulk translator page loads
    await expect(adminPage.locator('body')).toBeVisible();
  });

  test.afterAll(async () => {
    if (adminPage) {
      await adminPage.close();
    }
  });
});














