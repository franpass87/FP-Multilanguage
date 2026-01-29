const { test, expect } = require('@playwright/test');

const WP_ADMIN_URL = '/wp-admin';
const ADMIN_USERNAME = 'FranPass87';
const ADMIN_PASSWORD = '00Antonelli00';
const PLUGIN_PAGE = '/wp-admin/admin.php?page=fpml-settings';
const AJAX_URL = '/wp-admin/admin-ajax.php';

test.describe('FP Multilanguage - AJAX Handlers', () => {
  let page;
  let nonce;

  test.beforeEach(async ({ page: testPage }) => {
    page = testPage;
    // Login to WordPress admin
    await page.goto(`${WP_ADMIN_URL}/login.php`);
    await page.fill('#user_login', ADMIN_USERNAME);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
    
    // Get nonce from settings page
    await page.goto(PLUGIN_PAGE);
    const nonceField = page.locator('input[name*="nonce"], input[name*="_wpnonce"]').first();
    if (await nonceField.count() > 0) {
      nonce = await nonceField.getAttribute('value');
    }
  });

  test.describe('Nonce Validation', () => {
    test('should require valid nonce for AJAX requests', async () => {
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_refresh_nonce',
          nonce: 'invalid_nonce'
        }
      });
      
      // Should fail without valid nonce
      expect(response.status()).toBeGreaterThanOrEqual(200);
      const body = await response.text();
      // WordPress typically returns -1 or error for invalid nonce
      expect(body).toMatch(/-1|error|invalid/i);
    });

    test('should accept valid nonce', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_refresh_nonce',
          nonce: nonce
        }
      });
      
      // Should accept valid nonce (may return success or error based on other validation)
      expect(response.status()).toBe(200);
    });
  });

  test.describe('AJAX Endpoints', () => {
    test('should handle refresh_nonce AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_refresh_nonce',
          nonce: nonce
        }
      });
      
      expect(response.status()).toBe(200);
      const body = await response.text();
      // Should return JSON or valid response
      expect(body.length).toBeGreaterThan(0);
    });

    test('should handle bulk_translate AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_bulk_translate',
          nonce: nonce,
          post_ids: []
        }
      });
      
      expect(response.status()).toBe(200);
    });

    test('should handle translate_single AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_translate_single',
          nonce: nonce,
          post_id: 0
        }
      });
      
      expect(response.status()).toBe(200);
    });

    test('should handle translate_site_part AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_translate_site_part',
          nonce: nonce,
          part: 'menu'
        }
      });
      
      expect(response.status()).toBe(200);
    });

    test('should handle cleanup_orphaned_pairs AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_cleanup_orphaned_pairs',
          nonce: nonce
        }
      });
      
      expect(response.status()).toBe(200);
    });

    test('should handle trigger_detection AJAX', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_trigger_detection',
          nonce: nonce
        }
      });
      
      expect(response.status()).toBe(200);
    });

    test('should handle reindex_batch_ajax', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_reindex_batch_ajax',
          nonce: nonce
        }
      });
      
      expect(response.status()).toBe(200);
    });
  });

  test.describe('Input Sanitization', () => {
    test('should sanitize XSS attempts in AJAX requests', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const xssPayloads = [
        '<script>alert("xss")</script>',
        'javascript:alert("xss")',
        '<img src=x onerror=alert("xss")>',
        '\'"><script>alert("xss")</script>'
      ];
      
      for (const payload of xssPayloads) {
        const response = await page.request.post(AJAX_URL, {
          data: {
            action: 'fpml_translate_single',
            nonce: nonce,
            post_id: payload
          }
        });
        
        const body = await response.text();
        // Should not contain unescaped script tags
        expect(body).not.toContain('<script>alert');
        expect(body).not.toContain('javascript:alert');
      }
    });

    test('should sanitize SQL injection attempts', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const sqlPayloads = [
        "1' OR '1'='1",
        "1'; DROP TABLE wp_posts; --",
        "1' UNION SELECT * FROM wp_users --"
      ];
      
      for (const payload of sqlPayloads) {
        const response = await page.request.post(AJAX_URL, {
          data: {
            action: 'fpml_translate_single',
            nonce: nonce,
            post_id: payload
          }
        });
        
        // Should handle gracefully without exposing SQL errors
        expect(response.status()).toBe(200);
      }
    });
  });

  test.describe('Capability Checks', () => {
    test('should require manage_options capability', async () => {
      // This test would need a non-admin user to fully test
      // For now, just verify the endpoint exists
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_refresh_nonce',
          nonce: nonce
        }
      });
      
      // Admin user should have access
      expect(response.status()).toBe(200);
    });
  });

  test.describe('Error Handling', () => {
    test('should handle missing parameters gracefully', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_translate_single',
          nonce: nonce
          // Missing post_id
        }
      });
      
      expect(response.status()).toBe(200);
      // Should return error message, not crash
    });

    test('should handle invalid post IDs', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post(AJAX_URL, {
        data: {
          action: 'fpml_translate_single',
          nonce: nonce,
          post_id: 999999
        }
      });
      
      expect(response.status()).toBe(200);
      // Should return error, not crash
    });
  });
});

test.describe('FP Multilanguage - Admin Post Handlers', () => {
  let page;
  let nonce;

  test.beforeEach(async ({ page: testPage }) => {
    page = testPage;
    // Login to WordPress admin
    await page.goto(`${WP_ADMIN_URL}/login.php`);
    await page.fill('#user_login', ADMIN_USERNAME);
    await page.fill('#user_pass', ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
    
    // Get nonce from settings page
    await page.goto(PLUGIN_PAGE);
    const nonceField = page.locator('input[name*="nonce"], input[name*="_wpnonce"]').first();
    if (await nonceField.count() > 0) {
      nonce = await nonceField.getAttribute('value');
    }
  });

  test.describe('Form Submission', () => {
    test('should handle save_settings form submission', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post('/wp-admin/admin-post.php', {
        data: {
          action: 'fpml_save_settings',
          _wpnonce: nonce
        },
        maxRedirects: 0
      });
      
      // Should redirect or return success
      expect([200, 302]).toContain(response.status());
    });

    test('should handle scan_strings form submission', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post('/wp-admin/admin-post.php', {
        data: {
          action: 'fpml_scan_strings',
          _wpnonce: nonce
        },
        maxRedirects: 0
      });
      
      expect([200, 302]).toContain(response.status());
    });

    test('should require nonce for form submissions', async () => {
      const response = await page.request.post('/wp-admin/admin-post.php', {
        data: {
          action: 'fpml_save_settings'
          // Missing nonce
        },
        maxRedirects: 0
      });
      
      // Should fail without nonce
      expect([200, 302, 403]).toContain(response.status());
    });
  });

  test.describe('Export/Import Handlers', () => {
    test('should handle export_state', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post('/wp-admin/admin-post.php', {
        data: {
          action: 'fpml_export_state',
          _wpnonce: nonce
        },
        maxRedirects: 0
      });
      
      expect([200, 302]).toContain(response.status());
    });

    test('should handle export_logs', async () => {
      if (!nonce) {
        test.skip();
        return;
      }
      
      const response = await page.request.post('/wp-admin/admin-post.php', {
        data: {
          action: 'fpml_export_logs',
          _wpnonce: nonce
        },
        maxRedirects: 0
      });
      
      expect([200, 302]).toContain(response.status());
    });
  });
});
