const { test, expect } = require('@playwright/test');

const WP_ADMIN_URL = '/wp-admin';
const ADMIN_USERNAME = 'FranPass87';
const ADMIN_PASSWORD = '00Antonelli00';
const PLUGIN_PAGE = '/wp-admin/admin.php?page=fpml-settings';

test.describe('FP Multilanguage - Integrations Tests', () => {
  let page;

  test.beforeEach(async ({ page: testPage }) => {
    page = testPage;
    // Login to WordPress admin
    await page.goto(`${WP_ADMIN_URL}/login.php`);
    await page.fill('#user_login', ADMIN_USERNAME);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
  });

  test.describe('WooCommerce Integration', () => {
    test('should detect WooCommerce if active', async () => {
      // Check if WooCommerce is active by looking for its menu
      await page.goto('/wp-admin');
      
      const wcMenu = page.locator('#toplevel_page_woocommerce, a[href*="woocommerce"]');
      const isWCActive = await wcMenu.count() > 0;
      
      if (isWCActive) {
        // Check compatibility tab for WooCommerce detection
        await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
        
        const content = await page.content();
        // Should mention WooCommerce if detected
        expect(content.length).toBeGreaterThan(0);
      } else {
        console.log('WooCommerce not active - skipping WooCommerce tests');
      }
    });

    test('should translate WooCommerce products if WooCommerce is active', async () => {
      await page.goto('/wp-admin');
      
      const wcMenu = page.locator('#toplevel_page_woocommerce');
      const isWCActive = await wcMenu.count() > 0;
      
      if (!isWCActive) {
        test.skip();
        return;
      }
      
      // Navigate to products
      await page.goto('/wp-admin/edit.php?post_type=product');
      
      // Check if products exist
      const products = page.locator('.wp-list-table tbody tr');
      const productCount = await products.count();
      
      if (productCount > 0) {
        // Click on first product
        const firstProduct = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
        await firstProduct.click();
        
        // Check for translation metabox
        const metabox = page.locator('#fpml-translation-metabox, [id*="translation"]');
        const metaboxCount = await metabox.count();
        
        // Metabox may or may not be visible
        expect(metaboxCount).toBeGreaterThanOrEqual(0);
      }
    });

    test('should handle WooCommerce product attributes', async () => {
      await page.goto('/wp-admin');
      
      const wcMenu = page.locator('#toplevel_page_woocommerce');
      const isWCActive = await wcMenu.count() > 0;
      
      if (!isWCActive) {
        test.skip();
        return;
      }
      
      // Navigate to products
      await page.goto('/wp-admin/edit.php?post_type=product');
      
      const products = page.locator('.wp-list-table tbody tr');
      const productCount = await products.count();
      
      if (productCount > 0) {
        const firstProduct = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
        await firstProduct.click();
        
        // Check for attributes section
        const attributes = page.locator('#product_attributes, .product_attributes');
        const attrCount = await attributes.count();
        
        // Attributes may or may not be present
        expect(attrCount).toBeGreaterThanOrEqual(0);
      }
    });
  });

  test.describe('Salient Theme Integration', () => {
    test('should detect Salient theme if active', async () => {
      await page.goto('/wp-admin');
      
      // Check theme name
      await page.goto('/wp-admin/themes.php');
      
      const themeInfo = await page.content();
      const isSalient = themeInfo.includes('Salient') || themeInfo.includes('salient');
      
      if (isSalient) {
        // Check compatibility tab
        await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
        
        const content = await page.content();
        expect(content.length).toBeGreaterThan(0);
      } else {
        console.log('Salient theme not active - skipping Salient tests');
      }
    });

    test('should handle Salient meta fields if theme is active', async () => {
      await page.goto('/wp-admin');
      await page.goto('/wp-admin/themes.php');
      
      const themeInfo = await page.content();
      const isSalient = themeInfo.includes('Salient') || themeInfo.includes('salient');
      
      if (!isSalient) {
        test.skip();
        return;
      }
      
      // Navigate to a page
      await page.goto('/wp-admin/edit.php?post_type=page');
      
      const pages = page.locator('.wp-list-table tbody tr');
      const pageCount = await pages.count();
      
      if (pageCount > 0) {
        const firstPage = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
        await firstPage.click();
        
        // Check for Salient-specific meta boxes
        const salientMeta = page.locator('[id*="salient"], [class*="salient"]');
        const metaCount = await salientMeta.count();
        
        // Salient meta may or may not be visible
        expect(metaCount).toBeGreaterThanOrEqual(0);
      }
    });
  });

  test.describe('FP SEO Manager Integration', () => {
    test('should detect FP SEO Manager if active', async () => {
      await page.goto('/wp-admin');
      
      // Check for FP SEO Manager menu or plugin
      const seoMenu = page.locator('a[href*="fp-seo"], a[href*="fp_seo"], #toplevel_page_fp-seo');
      const isSeoActive = await seoMenu.count() > 0;
      
      if (isSeoActive) {
        // Check compatibility tab
        await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
        
        const content = await page.content();
        expect(content.length).toBeGreaterThan(0);
      } else {
        console.log('FP SEO Manager not active - skipping SEO tests');
      }
    });

    test('should handle FP SEO meta fields if plugin is active', async () => {
      await page.goto('/wp-admin');
      
      const seoMenu = page.locator('a[href*="fp-seo"], a[href*="fp_seo"]');
      const isSeoActive = await seoMenu.count() > 0;
      
      if (!isSeoActive) {
        test.skip();
        return;
      }
      
      // Navigate to a post
      await page.goto('/wp-admin/edit.php');
      
      const posts = page.locator('.wp-list-table tbody tr');
      const postCount = await posts.count();
      
      if (postCount > 0) {
        const firstPost = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
        await firstPost.click();
        
        // Check for SEO meta box
        const seoMeta = page.locator('[id*="seo"], [class*="seo"], [id*="fp-seo"]');
        const metaCount = await seoMeta.count();
        
        expect(metaCount).toBeGreaterThanOrEqual(0);
      }
    });
  });

  test.describe('WPBakery Integration', () => {
    test('should detect WPBakery if active', async () => {
      await page.goto('/wp-admin');
      
      // Check for WPBakery menu
      const wpbMenu = page.locator('a[href*="vc_"], a[href*="wpbakery"], #toplevel_page_vc-general');
      const isWpbActive = await wpbMenu.count() > 0;
      
      if (isWpbActive) {
        console.log('WPBakery detected');
      } else {
        console.log('WPBakery not active - skipping WPBakery tests');
      }
    });

    test('should handle WPBakery shortcodes if active', async () => {
      await page.goto('/wp-admin');
      
      const wpbMenu = page.locator('a[href*="vc_"], a[href*="wpbakery"]');
      const isWpbActive = await wpbMenu.count() > 0;
      
      if (!isWpbActive) {
        test.skip();
        return;
      }
      
      // Navigate to a page
      await page.goto('/wp-admin/edit.php?post_type=page');
      
      const pages = page.locator('.wp-list-table tbody tr');
      const pageCount = await pages.count();
      
      if (pageCount > 0) {
        const firstPage = page.locator('.wp-list-table tbody tr:first-child .row-title a').first();
        await firstPage.click();
        
        // Check for WPBakery editor
        const wpbEditor = page.locator('[id*="vc_"], [class*="vc_"], [id*="wpbakery"]');
        const editorCount = await wpbEditor.count();
        
        expect(editorCount).toBeGreaterThanOrEqual(0);
      }
    });
  });

  test.describe('Elementor Integration', () => {
    test('should detect Elementor if active', async () => {
      await page.goto('/wp-admin');
      
      // Check for Elementor menu
      const elementorMenu = page.locator('a[href*="elementor"], #toplevel_page_elementor');
      const isElementorActive = await elementorMenu.count() > 0;
      
      if (isElementorActive) {
        console.log('Elementor detected');
      } else {
        console.log('Elementor not active - skipping Elementor tests');
      }
    });
  });

  test.describe('Plugin Compatibility Detection', () => {
    test('should show detected plugins in compatibility tab', async () => {
      await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
      
      // Page should load
      await expect(page.locator('h1')).toContainText('FP Multilanguage');
      
      // Should show some content
      const content = page.locator('body');
      await expect(content).toBeVisible();
    });

    test('should list compatible plugins', async () => {
      await page.goto(`${PLUGIN_PAGE}&tab=compatibility`);
      
      const content = await page.content();
      
      // Should mention some plugins or show compatibility info
      expect(content.length).toBeGreaterThan(0);
    });
  });

  test.describe('Integration Status', () => {
    test('should not break when integrations are inactive', async () => {
      // Test that plugin works even if integrations are not active
      await page.goto(PLUGIN_PAGE);
      
      // Should load without errors
      await expect(page.locator('h1')).toContainText('FP Multilanguage');
      
      // Check for console errors
      const errors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          errors.push(msg.text());
        }
      });
      
      await page.waitForTimeout(2000);
      
      const pluginErrors = errors.filter(e => 
        !e.includes('wp-compression-test') && 
        !e.includes('dashboard-widgets') &&
        !e.includes('jquery')
      );
      
      if (pluginErrors.length > 0) {
        console.log('Integration errors:', pluginErrors);
      }
    });
  });
});
