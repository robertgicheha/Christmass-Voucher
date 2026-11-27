<?php
/**
 * Extra files & functions are hooked here.
 *
 * Displays all of the head element and everything up until the "site-content" div.
 *
 * @package Avada
 * @subpackage Core
 * @since 1.0
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

if ( ! defined( 'AVADA_VERSION' ) ) {
	define( 'AVADA_VERSION', '7.14.2' );
}

if ( ! defined( 'AVADA_MIN_PHP_VER_REQUIRED' ) ) {
	define( 'AVADA_MIN_PHP_VER_REQUIRED', '5.6' );
}

if ( ! defined( 'AVADA_MIN_WP_VER_REQUIRED' ) ) {
	define( 'AVADA_MIN_WP_VER_REQUIRED', '4.9' );
}

// Developer mode.
if ( ! defined( 'AVADA_DEV_MODE' ) ) {
	define( 'AVADA_DEV_MODE', false );
}


/**
 * Compatibility check.
 *
 * Check that the site meets the minimum requirements for the theme before proceeding.
 *
 * @since 6.0
 */
if ( version_compare( $GLOBALS['wp_version'], AVADA_MIN_WP_VER_REQUIRED, '<' ) || version_compare( PHP_VERSION, AVADA_MIN_PHP_VER_REQUIRED, '<' ) ) {
	require_once get_template_directory() . '/includes/bootstrap-compat.php';
	return;
}

/**
 * Bootstrap the theme.
 *
 * @since 6.0
 */
require_once get_template_directory() . '/includes/bootstrap.php';


// 1) Register jobID as a public query var
add_filter('query_vars', function ($vars) {
  $vars[] = 'jobID';
  return $vars;
});

// 2) Add pretty URL rewrite + flush once
add_action('init', function () {
  add_rewrite_rule(
    '^career-details/([^/]+)/?$',
    'index.php?pagename=career-details&jobID=$matches[1]',
    'top'
  );
  // flush_rewrite_rules();
});

// 3) Redirect legacy ?jobID= query URLs to pretty URLs
// add_action('template_redirect', function () {
//   if (is_page('career-details') && isset($_GET['jobID']) && $_GET['jobID'] !== '') {
//     $pretty = home_url('/career-details/' . rawurlencode($_GET['jobID']) . '/');
//     wp_redirect($pretty, 301);
//     exit;
//   }
// });

add_action('template_redirect', function () {
    if (is_404() && preg_match('#/thank-you/GEHP-\d{8}-[A-Z]{2}-[A-Z0-9]{6}/?$#', $_SERVER['REQUEST_URI'])) {
        $target = home_url('/we-do/');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $target .= '?' . $_SERVER['QUERY_STRING'];
        }
        wp_redirect($target, 301);
        exit;
    }
});

add_action('send_headers', function () {
    if (preg_match('#/thank-you/GEHP-\d{8}-[A-Z]{2}-[A-Z0-9]{6}/?$#', $_SERVER['REQUEST_URI'])) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
});

add_filter('wp_robots', function ($robots) {
    if (preg_match('#/thank-you/GEHP-\d{8}-[A-Z]{2}-[A-Z0-9]{6}/?$#', $_SERVER['REQUEST_URI'])) {
        $robots['noindex']   = true;
        $robots['nofollow']  = true;
    }
    return $robots;
});


/**
 * Employee Survey pretty URL support.
 *
 * Makes /employee-survey/{employeeNumber}/{surveyCode}/ resolve to the
 * "employee-survey" WordPress page, exposing both values as query vars.
 *
 * Matches:
 *   /employee-survey/EHSICT001/FIRSTDAY
 *   /employee-survey/EHSICT001/FIRSTMONTH
 */

// ── 1. Register the rewrite rule ───────────────────────────────────────────
add_action('init', function () {
    add_rewrite_rule(
        '^employee-survey/([^/]+)/([^/]+)/?$',
        'index.php?pagename=employee-survey&employee_number=$matches[1]&survey_code=$matches[2]',
        'top'
    );
});

// ── 2. Whitelist the query vars ────────────────────────────────────────────
add_filter('query_vars', function ($vars) {
    $vars[] = 'survey_code';
    $vars[] = 'employee_number';
    return $vars;
});

// ── 3. Stop WordPress canonical redirects from rewriting the URL ───────────
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    if (get_query_var('employee_number') !== '') {
        return false;
    }
    return $redirect_url;
}, 10, 2);

// ── 4. Auto-flush rewrite rules once after the rule is added/changed ──────
// Bump the version string any time you edit the rule above.
add_action('init', function () {
    $version = 'employee-survey-rule-v2';
    if (get_option('ehs_employee_rewrite_version') !== $version) {
        flush_rewrite_rules();
        update_option('ehs_employee_rewrite_version', $version);
    }
}, 20);

// ── 5. Expose the values to the front-end JS ──────────────────────────────
add_action('wp_head', function () {
    if (!is_page('employee-survey')) {
        return;
    }
    $emp = sanitize_text_field(get_query_var('employee_number'));
    $raw = sanitize_text_field(get_query_var('survey_code'));
    if ($emp === '' && $raw === '') {
        return;
    }

    // Used only to pick which survey tab opens; NAV always gets the raw code.
    $lower = strtolower($raw);
    $code  = (strpos($lower, '31') !== false || strpos($lower, 'month') !== false) ? 'day31' : 'day1';
    ?>
    <script>
      window.ehsSurveyData = {
        surveyCode: <?php echo wp_json_encode($code); ?>,
        rawSurveyCode: <?php echo wp_json_encode($raw); ?>,
        employeeNumber: <?php echo wp_json_encode($emp); ?>
      };
    </script>
    <?php
}, 1);

// ── 6. Links carry employee numbers: never cache, never index ─────────────
add_action('template_redirect', function () {
    if (is_page('employee-survey')) {
        nocache_headers();
    }
});
add_filter('wp_robots', function ($robots) {
    if (is_page('employee-survey')) {
        $robots['noindex'] = true;
    }
    return $robots;
});


/**
 * Christmas Voucher pretty URL support.
 *
 * Makes /christmas-voucher/{employeeNumber}/{documentNumber}/ resolve to the
 * "christmas-voucher" WordPress page, exposing both values as query vars.
 * The bare /christmas-voucher/ page is NOT viewable on its own (404).
 *
 * Matches (trailing slash optional):
 *   /christmas-voucher/MICCEX201/SL-000004/
 *   /christmas-voucher/EHSICT004/SL-000002
 *
 * The rule targets the page by ID (looked up by slug), so it works even if
 * the page sits under a parent page. Rewrite rules re-flush automatically
 * whenever the rule or the page ID changes.
 */

// ── 0. Helpers: resolve the page ID and build the rule target ─────────────
if (!function_exists('christmas_voucher_page_id')) {
    function christmas_voucher_page_id() {
        static $id = null;
        if ($id === null) {
            $pages = get_posts([
                'post_type'      => 'page',
                'name'           => 'christmas-voucher',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            $id = $pages ? (int) $pages[0] : 0;
        }
        return $id;
    }
}

if (!function_exists('christmas_voucher_rule_target')) {
    function christmas_voucher_rule_target() {
        $vars    = '&christmas_voucher_employee=$matches[1]&christmas_voucher_document=$matches[2]';
        $page_id = christmas_voucher_page_id();
        return $page_id
            ? 'index.php?page_id=' . $page_id . $vars
            : 'index.php?pagename=christmas-voucher' . $vars;
    }
}

// ── 1. Register the rewrite rule ───────────────────────────────────────────
add_action('init', function () {
    add_rewrite_rule(
        '^christmas-voucher/([^/]+)/([^/]+)/?$',
        christmas_voucher_rule_target(),
        'top'
    );
});

// ── 2. Whitelist the query vars ────────────────────────────────────────────
add_filter('query_vars', function ($vars) {
    $vars[] = 'christmas_voucher_employee';
    $vars[] = 'christmas_voucher_document';
    return $vars;
});

// ── 3. Auto-flush whenever the rule (or the page ID) changes ──────────────
add_action('init', function () {
    $version = 'christmas-voucher-' . md5(christmas_voucher_rule_target());
    if (get_option('christmas_voucher_rewrite_version') !== $version) {
        flush_rewrite_rules(false);
        update_option('christmas_voucher_rewrite_version', $version);
    }
}, 20);

// ── 4. Stop WP canonical-redirecting the deep URL back to /christmas-voucher/ ──
add_filter('redirect_canonical', function ($redirect_url) {
    if (get_query_var('christmas_voucher_employee') !== '' && get_query_var('christmas_voucher_document') !== '') {
        return false;
    }
    return $redirect_url;
});

// ── 5. Block the bare page; add privacy headers on valid voucher URLs ──────
add_action('template_redirect', function () {
    if (!is_page('christmas-voucher')) {
        return;
    }

    $employee = get_query_var('christmas_voucher_employee');
    $document = get_query_var('christmas_voucher_document');

    if ($employee === '' || $document === '') {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        include get_query_template('404');
        exit;
    }

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer'); // keeps the personal link out of third-party logs
}, 1);

// ── 6. Feed employee/document numbers to the front-end JS as a fallback ───
add_action('wp_footer', function () {
    if (!is_page('christmas-voucher')) {
        return;
    }
    $employee = get_query_var('christmas_voucher_employee');
    $document = get_query_var('christmas_voucher_document');
    if ($employee === '' || $document === '') {
        return;
    }
    ?>
    <script>
      window.christmasVoucherData = {
        employeeNumber: <?php echo wp_json_encode($employee); ?>,
        documentNumber: <?php echo wp_json_encode($document); ?>
      };
    </script>
    <?php
}, 1);




/**
 * Reference Check pretty URL support.
 *
 * Makes /reference-check/{appNumber}/{refereeCode}/ resolve to the
 * "reference-check" WordPress page, exposing appNumber/refereeCode as
 * query vars the page template can read.
 *
 * Matches:
 *   /reference-check/APP-00000020/2/
 *   /reference-check/APP-00000020/4/
 *   /reference-check/APP-00000056/7/
 */

// ── 1. Register the rewrite rule ───────────────────────────────────────────
add_action('init', function () {
    // /reference-check/APP-00000020/2/  ->  page=reference-check&app_number=APP-00000020&referee_code=2
    add_rewrite_rule(
        '^reference-check/([^/]+)/([^/]+)/?$',
        'index.php?pagename=reference-check&app_number=$matches[1]&referee_code=$matches[2]',
        'top'
    );
});

// ── 2. Whitelist the query vars so WP_Query doesn't strip them ────────────
add_filter('query_vars', function ($vars) {
    $vars[] = 'app_number';
    $vars[] = 'referee_code';
    return $vars;
});

// ── 3. Auto-flush rewrite rules once after this rule is added/changed ─────
// Avoids relying on someone remembering to click Save in Permalinks.
// Bump the version string any time you edit the rule above.
add_action('init', function () {
    $version = 'refcheck-rule-v1';
    if (get_option('ehs_rewrite_flushed_version') !== $version) {
        flush_rewrite_rules();
        update_option('ehs_rewrite_flushed_version', $version);
    }
}, 20); // after the rule registration above

// ── 4. Feed appNumber/refereeCode to the front-end JS as a reliable fallback ──
// Covers cases where pathname parsing on the client is ever ambiguous
// (e.g. behind a caching layer that rewrites the visible URL).
add_action('wp_footer', function () {
    if (!is_page('reference-check')) {
        return;
    }
    $app = get_query_var('app_number');
    $ref = get_query_var('referee_code');
    if ($app === '' && $ref === '') {
        return;
    }
    ?>
    <script>
      window.rcRefCheckData = {
        appNumber: <?php echo wp_json_encode($app); ?>,
        refereeCode: <?php echo wp_json_encode($ref); ?>
      };
    </script>
    <?php
}, 1); // priority 1 = runs before your inline wizard script if it's also enqueued in the footer