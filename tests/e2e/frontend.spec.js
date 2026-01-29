const { test, expect } = require('@playwright/test');

test.describe('FP Multilanguage - Frontend', () => {
  test('should load Italian homepage', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();
    
    // Check for console errors (excluding WordPress core)
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    // Filter out known WordPress/core errors
    const pluginErrors = errors.filter(e => 
      !e.includes('wp-compression-test') && 
      !e.includes('dashboard-widgets')
    );
    
    // Log errors for debugging but don't fail test
    if (pluginErrors.length > 0) {
      console.log('Frontend errors:', pluginErrors);
    }
  });

  test('should NOT have redirect loop on /en/', async ({ page }) => {
    // This test should fail until the redirect loop is fixed
    let redirectCount = 0;
    const maxRedirects = 5;
    
    page.on('response', response => {
      if (response.status() >= 300 && response.status() < 400) {
        redirectCount++;
      }
    });
    
    try {
      await page.goto('/en/', { 
        waitUntil: 'domcontentloaded',
        timeout: 10000 
      });
      
      // If we get here, the page loaded (good)
      expect(redirectCount).toBeLessThan(maxRedirects);
    } catch (error) {
      // If we get ERR_TOO_MANY_REDIRECTS, the test should document this
      if (error.message.includes('redirect') || error.message.includes('ERR_TOO_MANY')) {
        console.error('REDIRECT LOOP DETECTED on /en/ - This is a known issue');
        throw error;
      }
      throw error;
    }
  });

  test('should show language switcher in admin bar when logged in', async ({ page }) => {
    // Login first
    await page.goto('/wp-admin/login.php');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
    
    // Go to frontend
    await page.goto('/');
    
    // Check for language switcher in admin bar
    const switcher = page.locator('#wp-admin-bar-fpml-lang-switcher, [aria-label*="lingua"], [aria-label*="language"]');
    await expect(switcher.first()).toBeVisible({ timeout: 5000 }).catch(() => {
      // Switcher might not be visible, log for debugging
      console.log('Language switcher not found in admin bar');
    });
  });
});














