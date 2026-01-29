const { test, expect } = require('@playwright/test');

const WP_ADMIN_URL = '/wp-admin';
const ADMIN_USERNAME = 'FranPass87';
const ADMIN_PASSWORD = '00Antonelli00';
const PLUGIN_PAGE = '/wp-admin/admin.php?page=fpml-settings';

test.describe('FP Multilanguage - Admin Pages', () => {
  test.beforeEach(async ({ page }) => {
    // Login to WordPress admin
    await page.goto(`${WP_ADMIN_URL}/login.php`);
    await page.fill('#user_login', ADMIN_USERNAME);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
  });

  test('should load Dashboard tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=dashboard`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
    await expect(page.locator('.nav-tab-wrapper')).toContainText('Dashboard');
    
    // Check for console errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    // Filter out WordPress core errors (wp-compression-test, dashboard-widgets)
    const pluginErrors = errors.filter(e => 
      !e.includes('wp-compression-test') && 
      !e.includes('dashboard-widgets') &&
      !e.includes('admin-ajax.php')
    );
    
    expect(pluginErrors.length).toBe(0);
  });

  test('should load General tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=general`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
    await expect(page.locator('.nav-tab-wrapper')).toContainText('Generale');
    
    // Check form elements exist
    await expect(page.locator('input[name*="openai_api_key"]')).toBeVisible();
    await expect(page.locator('button:has-text("Test Connessione")')).toBeVisible();
  });

  test('should load Content tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=content`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Strings tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=strings`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Glossary tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=glossary`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load SEO tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=seo`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Export/Import tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=export`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Compatibility tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Diagnostics tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=diagnostics`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should load Translations tab', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=translations`);
    await expect(page.locator('h1')).toContainText('FP Multilanguage');
  });

  test('should have nonce in forms', async ({ page }) => {
    await page.goto(`${PLUGIN_PAGE}&tab=general`);
    
    // Check for nonce fields
    const nonceFields = await page.locator('input[name*="nonce"], input[name*="_wpnonce"]').count();
    expect(nonceFields).toBeGreaterThan(0);
  });

  test('should not have CSS 404 errors', async ({ page }) => {
    const errors = [];
    page.on('response', response => {
      if (response.status() === 404 && response.url().includes('admin.css')) {
        errors.push(response.url());
      }
    });
    
    await page.goto(`${PLUGIN_PAGE}&tab=dashboard`);
    await page.waitForTimeout(2000);
    
    expect(errors.length).toBe(0);
  });
});














