<?php
// Paste everything below this line into the active theme's functions.php.

/**
 * Christmas Voucher pretty URL.
 *
 * Serves {theme}/christmas-voucher/index.html for
 * /christmas-voucher/{employeeNumber}/{documentNumber}/
 * The page reads both values from its own URL.
 */
add_action('init', function () {
    add_rewrite_rule(
        '^christmas-voucher/([^/]+)/([^/]+)/?$',
        'index.php?christmas_voucher_employee=$matches[1]&christmas_voucher_document=$matches[2]',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'christmas_voucher_employee';
    $vars[] = 'christmas_voucher_document';
    return $vars;
});

// Uses its own option so it does not fight the reference-check flush.
add_action('init', function () {
    $version = 'christmas-voucher-rule-v1';
    if (get_option('christmas_voucher_rewrite_version') !== $version) {
        flush_rewrite_rules();
        update_option('christmas_voucher_rewrite_version', $version);
    }
}, 20);

// Priority 1 runs before WordPress can canonical-redirect the URL.
add_action('template_redirect', function () {
    if (get_query_var('christmas_voucher_employee') === '' || get_query_var('christmas_voucher_document') === '') {
        return;
    }

    // Executive Healthcare uses index-ehs.html when it is uploaded; otherwise index.html.
    $dir  = get_stylesheet_directory() . '/christmas-voucher/';
    $host = strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST));
    $page = $dir . 'index.html';
    if (strpos($host, 'executive-healthcare') !== false && is_readable($dir . 'index-ehs.html')) {
        $page = $dir . 'index-ehs.html';
    }
    if (!is_readable($page)) {
        error_log('[christmas-voucher] Page file not found: ' . $page);
        return;
    }

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer'); // keeps the personal link out of third-party logs

    // Images load from the media library by absolute URL, so the page is sent as is.
    readfile($page);
    exit;
}, 1);
