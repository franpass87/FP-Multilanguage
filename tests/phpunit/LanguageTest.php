<?php
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $_SERVER = array();
    }

    public function test_get_current_url_preserves_port_information(): void {
        $_SERVER['HTTP_HOST']   = 'example.com:8080';
        $_SERVER['REQUEST_URI'] = '/path/page?foo=bar';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'http://example.com:8080/path/page?foo=bar',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_falls_back_to_home_url_when_host_missing(): void {
        $_SERVER['REQUEST_URI'] = '/current/page';
        $_SERVER['HTTPS']       = 'on';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'https://example.com/current/page',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_uses_site_scheme_when_not_ssl(): void {
        $_SERVER['REQUEST_URI'] = '/current/page';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'https://example.com/current/page',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_uses_forwarded_header_parameters(): void {
        $_SERVER['REQUEST_URI']  = '/behind/proxy';
        $_SERVER['HTTP_FORWARDED'] = 'for=192.0.2.60;proto="HTTPS";host="forwarded.example.com:8443"';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'https://forwarded.example.com:8443/behind/proxy',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_falls_back_to_forwarded_host_when_x_headers_missing(): void {
        $_SERVER['REQUEST_URI']   = '/proxy/path';
        $_SERVER['HTTP_FORWARDED'] = 'proto=http;host=internal.example';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'http://internal.example/proxy/path',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_uses_first_forwarded_entry_with_host_information(): void {
        $_SERVER['REQUEST_URI']    = '/proxy/forwarded';
        $_SERVER['HTTP_FORWARDED'] = 'for=198.51.100.17;by=10.0.0.1, for=203.0.113.43;proto=https;host=forwarded.example:4443';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'https://forwarded.example:4443/proxy/forwarded',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_does_not_mix_forwarded_entries(): void {
        $_SERVER['REQUEST_URI']    = '/proxy/mismatch';
        $_SERVER['HTTP_FORWARDED'] = 'proto=https, host=internal.example';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'http://internal.example/proxy/mismatch',
            $method->invoke( $language )
        );
    }

    public function test_get_current_url_appends_forwarded_port_to_forwarded_host(): void {
        $_SERVER['REQUEST_URI']          = '/proxy/forwarded-port';
        $_SERVER['HTTP_FORWARDED']       = 'for=198.51.100.17;host=forwarded.example';
        $_SERVER['HTTP_X_FORWARDED_PORT'] = '8443';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'get_current_url' );
        $method->setAccessible( true );

        $this->assertSame(
            'https://forwarded.example:8443/proxy/forwarded-port',
            $method->invoke( $language )
        );
    }

    public function test_sanitize_host_allows_ipv6_notation_and_strips_noise(): void {
        $_SERVER['HTTPS'] = 'on';

        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $host = "[2001:db8::1]:8443\r\n";

        $this->assertSame('[2001:db8::1]:8443', $method->invoke( $language, $host ));
    }

    public function test_sanitize_host_preserves_underscore_separators(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'dev_site.local',
            $method->invoke( $language, 'dev_site.local' )
        );
    }

    public function test_sanitize_host_discards_invalid_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com:bad' )
        );
    }

    public function test_sanitize_host_discards_out_of_range_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com:65536' )
        );
    }

    public function test_sanitize_host_preserves_highest_valid_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com:65535',
            $method->invoke( $language, 'example.com:65535' )
        );
    }

    public function test_sanitize_host_discards_invalid_ipv6_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            '[2001:db8::1]',
            $method->invoke( $language, '[2001:db8::1]:bad' )
        );
    }

    public function test_sanitize_host_discards_out_of_range_ipv6_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            '[2001:db8::1]',
            $method->invoke( $language, '[2001:db8::1]:99999' )
        );
    }

    public function test_sanitize_host_preserves_highest_valid_ipv6_port_suffix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            '[2001:db8::1]:65535',
            $method->invoke( $language, '[2001:db8::1]:65535' )
        );
    }

    public function test_sanitize_host_preserves_ipv6_zone_identifier(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            '[fe80::1%25eth0]',
            $method->invoke( $language, '[fe80::1%25eth0]' )
        );
    }

    public function test_sanitize_host_strips_percent_from_hostname(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com%25' )
        );
    }

    public function test_sanitize_host_discards_data_after_encoded_null_byte(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com%00.evil.com' )
        );
    }

    public function test_sanitize_host_decodes_encoded_path_delimiters(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com%2F..%2Fadmin' )
        );
    }

    public function test_sanitize_host_decodes_double_encoded_path_delimiters(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'example.com%252F..%252Fadmin' )
        );
    }

    public function test_sanitize_host_decodes_encoded_scheme_and_query_separators(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com:8443',
            $method->invoke( $language, 'https%3A%2F%2Fexample.com%3A8443%3Ffoo%3Dbar' )
        );
    }

    public function test_sanitize_host_decodes_double_encoded_scheme_and_query_separators(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com:8443',
            $method->invoke( $language, 'https%253A%252F%252Fexample.com%253A8443%253Ffoo%253Dbar' )
        );
    }

    public function test_sanitize_host_discards_scheme_and_userinfo_from_header(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com:8080',
            $method->invoke( $language, 'https://user:pass@example.com:8080/path' )
        );
    }

    public function test_sanitize_host_handles_schemeless_authority_in_header(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, '//example.com/segment' )
        );
    }

    public function test_sanitize_host_removes_scheme_only_prefix(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_host_header' );
        $method->setAccessible( true );

        $this->assertSame(
            'example.com',
            $method->invoke( $language, 'http://example.com' )
        );
    }

    public function test_sanitize_request_uri_normalizes_without_clobbering_query(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_request_uri' );
        $method->setAccessible( true );

        $uri = "//current/page?foo=bar#section";

        $this->assertSame('/current/page?foo=bar#section', $method->invoke( $language, $uri ));
    }

    public function test_sanitize_request_uri_strips_scheme_and_host_from_absolute_uri(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_request_uri' );
        $method->setAccessible( true );

        $uri = 'https://malicious.test/path/page?foo=bar#section';

        $this->assertSame('/path/page?foo=bar#section', $method->invoke( $language, $uri ));
    }

    public function test_sanitize_request_uri_trims_extraneous_whitespace(): void {
        $language = ( new ReflectionClass( FPML_Language::class ) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod( FPML_Language::class, 'sanitize_request_uri' );
        $method->setAccessible( true );

        $uri = "   /current/page?foo=bar   ";

        $this->assertSame('/current/page?foo=bar', $method->invoke( $language, $uri ));
    }
}
