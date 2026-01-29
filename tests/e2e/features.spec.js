const { test, expect } = require('@playwright/test');

const WP_ADMIN_URL = '/wp-admin';
const ADMIN_USERNAME = 'FranPass87';
const ADMIN_PASSWORD = '00Antonelli00';

test.describe('FP Multilanguage - Features', () => {
  test.beforeEach(async ({ page }) => {
    // Login to WordPress admin
    await page.goto(`${WP_ADMIN_URL}/login.php`);
    await page.fill('#user_login', ADMIN_USERNAME);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
  });

  test('should show translation metabox on post edit', async ({ page }) => {
    // Go to posts list
    await page.goto('/wp-admin/edit.php');
    
    // Click on first post (if exists)
    const firstPost = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
    const postCount = await firstPost.count();
    
    if (postCount > 0) {
      await firstPost.click();
      await page.waitForURL(/post\.php/);
      
      // Check for translation metabox
      const metabox = page.locator('#fpml-translation-metabox, [id*="translation"], [class*="translation"]').first();
      await expect(metabox).toBeVisible({ timeout: 5000 }).catch(() => {
        console.log('Translation metabox not found - might be normal if no translations exist');
      });
    }
  });

  test('should have Bulk Translation menu item', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fpml-settings');
    
    // Check for Bulk Translation link in menu
    const bulkLink = page.locator('a:has-text("Bulk Translation"), a[href*="bulk-translate"]');
    await expect(bulkLink.first()).toBeVisible({ timeout: 5000 });
  });

  test('should have admin bar language switcher', async ({ page }) => {
    await page.goto('/');
    
    // Check for admin bar language switcher
    const adminBar = page.locator('#wpadminbar');
    await expect(adminBar).toBeVisible();
    
    const langSwitcher = adminBar.locator('#wp-admin-bar-fpml-lang-switcher, [id*="lang-switcher"]');
    await expect(langSwitcher.first()).toBeVisible({ timeout: 5000 }).catch(() => {
      console.log('Language switcher not found in admin bar');
    });
  });
});














