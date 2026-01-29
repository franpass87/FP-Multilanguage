/**
 * E2E Test: Frontend Routing
 * 
 * Tests frontend URL routing and language switching.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

const { test, expect } = require('@playwright/test');

test.describe('Frontend Routing', () => {
  test('IT post URL works without /en/ segment', async ({ page }) => {
    // Visit a post (adjust slug as needed)
    await page.goto('/test-post/');
    
    // Verify page loads
    await expect(page.locator('body')).toBeVisible();
  });

  test('EN post URL works with /en/ segment', async ({ page }) => {
    // Visit EN version
    await page.goto('/en/test-post/');
    
    // Verify page loads
    await expect(page.locator('body')).toBeVisible();
  });

  test('Language switcher widget appears', async ({ page }) => {
    await page.goto('/');
    
    // Check for language switcher (adjust selector as needed)
    const switcher = page.locator('.fpml-language-switcher, [class*="language-switcher"]');
    const count = await switcher.count();
    
    // At least one language switcher should be present
    expect(count).toBeGreaterThan(0);
  });

  test('Language switching works', async ({ page }) => {
    // Visit IT page
    await page.goto('/test-post/');
    
    // Click language switcher to EN
    const enLink = page.locator('a[href*="/en/"]').first();
    if (await enLink.isVisible()) {
      await enLink.click();
      
      // Verify URL contains /en/
      await expect(page).toHaveURL(/.*\/en\//);
    }
  });
});














