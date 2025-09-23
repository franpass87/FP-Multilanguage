<?php
// Minimal WordPress function shims for unit testing.

global $wp_test_options;
if (! isset($wp_test_options)) {
    $wp_test_options = [];
}

global $wp_test_cache;
if (! isset($wp_test_cache)) {
    $wp_test_cache = [];
}

global $wp_test_transients;
if (! isset($wp_test_transients)) {
    $wp_test_transients = [];
}

global $wp_remote_post_calls;
if (! isset($wp_remote_post_calls)) {
    $wp_remote_post_calls = [];
}

global $wp_remote_post_failures;
if (! isset($wp_remote_post_failures)) {
    $wp_remote_post_failures = [];
}

global $wp_test_filters;
if (! isset($wp_test_filters)) {
    $wp_test_filters = [];
}

global $wp_test_actions;
if (! isset($wp_test_actions)) {
    $wp_test_actions = [];
}

global $wp_test_textdomains;
if (! isset($wp_test_textdomains)) {
    $wp_test_textdomains = [];
}

if (! function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_actions;

        if (! isset($wp_test_actions[$tag])) {
            $wp_test_actions[$tag] = [];
        }

        if (! isset($wp_test_actions[$tag][$priority])) {
            $wp_test_actions[$tag][$priority] = [];
        }

        $wp_test_actions[$tag][$priority][] = [
            'callback' => $callback,
            'accepted_args' => (int) $accepted_args,
        ];

        return true;
    }
}

if (! function_exists('do_action')) {
    function do_action($tag, ...$args)
    {
        global $wp_test_actions;

        if (! isset($wp_test_actions[$tag])) {
            return;
        }

        ksort($wp_test_actions[$tag]);

        foreach ($wp_test_actions[$tag] as $callbacks) {
            foreach ($callbacks as $callback) {
                $acceptedArgs = $callback['accepted_args'] > 0
                    ? $callback['accepted_args']
                    : 0;
                $callbackArgs = $acceptedArgs === 0
                    ? []
                    : array_slice($args, 0, $acceptedArgs);
                call_user_func_array($callback['callback'], $callbackArgs);
            }
        }
    }
}

if (! function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        global $wp_test_options;

        return $wp_test_options[$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option($name, $value)
    {
        global $wp_test_options;
        $wp_test_options[$name] = $value;

        return true;
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (! is_array($args)) {
            $args = [];
        }

        if (! is_array($defaults)) {
            $defaults = [];
        }

        return array_merge($defaults, $args);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return trim(filter_var((string) $value, FILTER_SANITIZE_SPECIAL_CHARS));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value)
    {
        return trim(filter_var((string) $value, FILTER_SANITIZE_SPECIAL_CHARS));
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post($value)
    {
        return trim((string) $value);
    }
}


if (! function_exists('add_query_arg')) {
    function add_query_arg(...$args)
    {
        $params = [];
        $url = '';

        if (count($args) === 2 && is_array($args[0])) {
            $params = $args[0];
            $url = $args[1];
        } elseif (count($args) === 3) {
            $params[$args[0]] = $args[1];
            $url = $args[2];
        } else {
            return 'https://example.com';
        }

        $url = $url ?: 'https://example.com';
        $parts = parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        foreach ($params as $k => $v) {
            $query[$k] = $v;
        }
        $parts['query'] = http_build_query($query);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'example.com';
        $path = $parts['path'] ?? '';
        $queryString = $parts['query'] ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $queryString;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = [])
    {
        global $wp_remote_post_calls, $wp_remote_post_failures;
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        if (! isset($wp_remote_post_calls[$host])) {
            $wp_remote_post_calls[$host] = 0;
        }
        $wp_remote_post_calls[$host]++;

        if (isset($wp_remote_post_failures[$host]) && $wp_remote_post_failures[$host] > 0) {
            $wp_remote_post_failures[$host]--;
            if ($wp_remote_post_failures[$host] === 0) {
                unset($wp_remote_post_failures[$host]);
            }

            return [
                'response' => ['code' => 500],
                'body' => json_encode(['error' => 'Simulated failure']),
            ];
        }

        $body = $args['body'] ?? '';
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
        } else {
            $decoded = $body;
        }

        $text = '';
        if (is_array($decoded)) {
            if (isset($decoded['q'])) {
                $text = $decoded['q'];
            } elseif (isset($decoded['text'])) {
                $text = $decoded['text'];
            }
        }

        $responseBody = [];
        if (str_contains($url, 'googleapis')) {
            $responseBody = [
                'data' => [
                    'translations' => [
                        ['translatedText' => 'google:' . $text],
                    ],
                ],
            ];
        } elseif (str_contains($url, 'deepl')) {
            $responseBody = [
                'translations' => [
                    ['text' => 'deepl:' . $text],
                ],
            ];
        } else {
            $responseBody = ['translations' => [['text' => $text]]];
        }

        return [
            'response' => ['code' => 200],
            'body' => json_encode($responseBody),
        ];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return $response['response']['code'] ?? 0;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return $response['body'] ?? '';
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($value)
    {
        return false;
    }
}

if (! function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '')
    {
        global $wp_test_cache;
        if (isset($wp_test_cache[$group][$key])) {
            return $wp_test_cache[$group][$key];
        }

        return false;
    }
}

if (! function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $expire = 0)
    {
        global $wp_test_cache;
        $wp_test_cache[$group][$key] = $value;

        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient($key)
    {
        global $wp_test_transients;

        if (isset($wp_test_transients[$key]) && $wp_test_transients[$key]['expires'] > time()) {
            return $wp_test_transients[$key]['value'];
        }

        return false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient($key, $value, $expiration)
    {
        global $wp_test_transients;
        $wp_test_transients[$key] = [
            'value' => $value,
            'expires' => time() + (int) $expiration,
        ];

        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_filters;

        if (! isset($wp_test_filters[$tag])) {
            $wp_test_filters[$tag] = [];
        }

        if (! isset($wp_test_filters[$tag][$priority])) {
            $wp_test_filters[$tag][$priority] = [];
        }

        $wp_test_filters[$tag][$priority][] = [
            'callback' => $callback,
            'accepted_args' => (int) $accepted_args,
        ];

        return true;
    }
}

if (! function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false, $locale = null)
    {
        global $wp_test_textdomains;

        $wp_test_textdomains[] = [
            'domain' => $domain,
            'deprecated' => $deprecated,
            'plugin_rel_path' => $plugin_rel_path,
            'locale' => $locale,
        ];

        return true;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        global $wp_test_filters;

        $args = func_get_args();
        $value = $args[1] ?? null;

        if (! isset($wp_test_filters[$tag])) {
            return $value;
        }

        ksort($wp_test_filters[$tag]);

        foreach ($wp_test_filters[$tag] as $callbacks) {
            foreach ($callbacks as $callback) {
                $args[1] = $value;
                $acceptedArgs = $callback['accepted_args'] > 0
                    ? $callback['accepted_args']
                    : 0;
                $callbackArgs = $acceptedArgs === 0
                    ? []
                    : array_slice($args, 1, $acceptedArgs);
                $value = call_user_func_array($callback['callback'], $callbackArgs);
            }
        }

        return $value;
    }
}

if (! function_exists('checked')) {
    function checked($checked, $current = true)
    {
        if ((bool) $checked === (bool) $current) {
            echo 'checked="checked"';
        }
    }
}

if (! function_exists('__')) {
    function __($text)
    {
        return $text;
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_html_e')) {
    function esc_html_e($text)
    {
        echo esc_html($text);
    }
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text)
    {
        return strip_tags((string) $text);
    }
}

if (! function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55)
    {
        $words = preg_split('/\s+/', trim((string) $text));
        if (count($words) <= $num_words) {
            return trim((string) $text);
        }

        return implode(' ', array_slice($words, 0, $num_words)) . '...';
    }
}

if (! function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce($action)
    {
        return md5($action . 'nonce');
    }
}

if (! function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued')
    {
        return false;
    }
}

if (! function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle)
    {
        return true;
    }
}

if (! function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n)
    {
        global $wp_localized_scripts;
        $wp_localized_scripts[$handle] = [$object_name => $l10n];

        return true;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        return true;
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null)
    {
        throw new RuntimeException('wp_send_json_error called: ' . json_encode($data));
    }
}

if (! function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null)
    {
        return $data;
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true)
    {
        return true;
    }
}

if (! function_exists('is_admin')) {
    function is_admin()
    {
        return false;
    }
}

if (! function_exists('determine_locale')) {
    function determine_locale()
    {
        return 'en_US';
    }
}

if (! function_exists('get_query_var')) {
    function get_query_var($var, $default = '')
    {
        return $default;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        return [];
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value)
    {
        return true;
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action)
    {
        return true;
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink($post)
    {
        return 'https://example.com/post/' . (is_object($post) ? $post->ID : $post);
    }
}

if (! function_exists('is_singular')) {
    function is_singular()
    {
        return false;
    }
}

if (! function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return null;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}
