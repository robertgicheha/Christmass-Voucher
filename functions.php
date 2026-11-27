<?php

/**
 * Theme Functions
 *
 * @package Betheme
 * @author Muffin group
 * @link https://muffingroup.com
 */

define('MFN_THEME_VERSION', '28.5.1');

// theme related filters

add_filter('widget_text', 'do_shortcode');

add_filter('the_excerpt', 'shortcode_unautop');
add_filter('the_excerpt', 'do_shortcode');

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
 * Matches:
 *   /christmas-voucher/MICCEX201/SL-000004/
 */

// ── 1. Register the rewrite rule ───────────────────────────────────────────
add_action('init', function () {
    add_rewrite_rule(
        '^christmas-voucher/([^/]+)/([^/]+)/?$',
        'index.php?pagename=christmas-voucher&christmas_voucher_employee=$matches[1]&christmas_voucher_document=$matches[2]',
        'top'
    );
});

// ── 2. Whitelist the query vars ────────────────────────────────────────────
add_filter('query_vars', function ($vars) {
    $vars[] = 'christmas_voucher_employee';
    $vars[] = 'christmas_voucher_document';
    return $vars;
});

// ── 3. Auto-flush once (own option, bumped to v2 for the new rule) ────────
add_action('init', function () {
    $version = 'christmas-voucher-rule-v2';
    if (get_option('christmas_voucher_rewrite_version') !== $version) {
        flush_rewrite_rules();
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
 */

add_action('init', function () {
    add_rewrite_rule(
        '^reference-check/([^/]+)/([^/]+)/?$',
        'index.php?pagename=reference-check&app_number=$matches[1]&referee_code=$matches[2]',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'app_number';
    $vars[] = 'referee_code';
    return $vars;
});

add_action('init', function () {
    $version = 'refcheck-rule-v1';
    if (get_option('ehs_rewrite_flushed_version') !== $version) {
        flush_rewrite_rules();
        update_option('ehs_rewrite_flushed_version', $version);
    }
}, 20);

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
}, 1);

/**
 * Reference Check: load the page script from the proxy plugin assets.
 */
add_action('wp_enqueue_scripts', function () {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/reference-check') !== 0) return;

    wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js', [], null, true);
    wp_enqueue_script(
        'rc-js',
        plugins_url('proxy_plugin/assets/reference-check.js'),
        ['toastify-js'],
        filemtime(WP_PLUGIN_DIR . '/proxy_plugin/assets/reference-check.js'),
        true
    );
});

/**
 * White Label
 * IMPORTANT: We recommend the use of Child Theme to change this
 */

defined('WHITE_LABEL') or define('WHITE_LABEL', false);

/**
 * textdomain
 */

function mfn_load_theme_textdomain() {
	// WPML: use action to allow String Translation to modify text
	load_theme_textdomain('betheme', get_template_directory() .'/languages'); // frontend
	load_theme_textdomain('mfn-opts', get_template_directory() .'/languages'); // admin panel
}
add_action('after_setup_theme', 'mfn_load_theme_textdomain');

/**
 * theme options
 */

require_once(get_theme_file_path('/muffin-options/theme-options.php'));

/**
 * theme functions
 */

$theme_disable = mfn_opts_get('theme-disable');

require_once(get_theme_file_path('/functions/modules/class-mfn-dynamic-data.php'));

require_once(get_theme_file_path('/functions/theme-functions.php'));
require_once(get_theme_file_path('/functions/theme-head.php'));
require_once(get_theme_file_path('/visual-builder/classes/front-template-view.php'));

/**
 * Global settings
 * */

function mfn_global() {

  global $mfn_global;

  $header_global = mfn_template_part_ID('header');
  $header_global = apply_filters( 'wpml_object_id', $header_global, get_post_type($header_global), TRUE );

  $footer_global = mfn_template_part_ID('footer');
  $footer_global = apply_filters( 'wpml_object_id', $footer_global , get_post_type($footer_global), TRUE );

  $sidebar_id = mfn_global_sidemenu_id();
  $sidebar_id = apply_filters( 'wpml_object_id', $sidebar_id , get_post_type($sidebar_id), TRUE );

  $mfn_front_tmpl = new Mfn_Template_View();

  $mfn_global = array(

  	'header' => $header_global,
  	'footer' => $footer_global,
  	'cart' => mfn_endpoint_tmpl('cart'),
  	'checkout' => mfn_endpoint_tmpl('checkout'),
  	'thank_you' => mfn_endpoint_tmpl('thanks'),
		'sidemenu' => $sidebar_id,
		'search' => get_option('mfn_search_template_used'),

		'singular_template' => $mfn_front_tmpl->get_singular_template(),
		'archive_template' => $mfn_front_tmpl->get_archive_template(),

  	/*'single_product' => mfn_single_product_tmpl(),
  	'shop_archive' => mfn_shop_archive_tmpl(),
  	'single_post' => mfn_single_post_ID( 'single-post' ),
  	'single_portfolio' => mfn_single_post_ID( 'single-portfolio' ),
  	//'blog' => mfn_archive_template_id('blog'),
  	'portfolio' => mfn_archive_template_id('portfolio')*/

  );

}
add_action( 'wp', 'mfn_global' );

if( is_admin() || apply_filters('is_bebuilder_demo', false)  ) {
	require_once(get_theme_file_path('/functions/admin/class-mfn-api.php'));
}

//Carrer Page
add_action('init', function () {
    add_rewrite_rule(
        '^career-details/([^/]+)/?$',
        'index.php?pagename=career-details',
        'top'
    );
});

// menu

require_once(get_theme_file_path('/functions/theme-menu.php'));

if (! isset($theme_disable['mega-menu'])) {
	require_once(get_theme_file_path('/functions/theme-mega-menu.php'));
}

// builder

require_once(get_theme_file_path('/functions/builder/class-mfn-builder.php'));

// post types

$post_types_disable = mfn_opts_get('post-type-disable');

require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type.php'));

if (! isset($theme_disable['custom-icons'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-icons.php'));
}
if (! isset($post_types_disable['template'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-template.php'));
}
if (! isset($post_types_disable['client'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-client.php'));
}
if (! isset($post_types_disable['offer'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-offer.php'));
}
if (! isset($post_types_disable['portfolio'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-portfolio.php'));
}
if (! isset($post_types_disable['slide'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-slide.php'));
}
if (! isset($post_types_disable['testimonial'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-testimonial.php'));
}

if (! isset($post_types_disable['layout'])) {
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-layout.php'));
}

if(function_exists('is_woocommerce')){
	require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-product.php'));
}

require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-page.php'));
require_once(get_theme_file_path('/functions/post-types/class-mfn-post-type-post.php'));

// includes

require_once(get_theme_file_path('/includes/content-post.php'));
require_once(get_theme_file_path('/includes/content-portfolio.php'));

// shortcodes

require_once(get_theme_file_path('/functions/theme-shortcodes.php'));

// hooks

require_once(get_theme_file_path('/functions/theme-hooks.php'));

// sidebars

require_once(get_theme_file_path('/functions/theme-sidebars.php'));

// widgets

require_once(get_theme_file_path('/functions/widgets/class-mfn-widgets.php'));

// TinyMCE

require_once(get_theme_file_path('/functions/tinymce/tinymce.php'));

// plugins

require_once(get_theme_file_path('/functions/class-mfn-love.php'));
require_once(get_theme_file_path('/functions/plugins/visual-composer.php'));
require_once(get_theme_file_path('/functions/plugins/elementor/class-mfn-elementor.php'));

// gdpr

require_once(get_theme_file_path('/functions/modules/class-mfn-gdpr.php'));

// popup

require_once(get_theme_file_path('/functions/modules/class-mfn-popup.php'));

// sidemenu

require_once(get_theme_file_path('/functions/modules/class-mfn-sidemenu.php'));

// svg sanitizer

if( empty($theme_disable['svg-allow']) && ! class_exists('enshrined\svgSanitize\Sanitizer') ){
	require_once(get_theme_file_path('/functions/modules/svg-sanitizer/load.php'));
}

// conditional logic

require_once(get_theme_file_path('/visual-builder/classes/conditional-logic.php'));

// WooCommerce functions

if (function_exists('is_woocommerce')) {
	require_once(get_theme_file_path('/functions/theme-woocommerce.php'));
}

// pagination

require_once(get_theme_file_path('/functions/modules/class-mfn-query-pagination.php'));

// dashboard

if ( is_admin() || apply_filters('is_bebuilder_demo', false) ) {

	$bebuilder_access = apply_filters('bebuilder_access', false);

	require_once(get_theme_file_path('/functions/admin/class-mfn-helper.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-update.php'));

	require_once(get_theme_file_path('/functions/admin/class-mfn-dashboard.php'));
	$mfn_dashboard = new Mfn_Dashboard();

	require_once(get_theme_file_path('/functions/importer/class-mfn-importer-helper.php'));

	require_once(get_theme_file_path('/functions/admin/setup/class-mfn-setup.php'));
	require_once(get_theme_file_path('/functions/importer/class-mfn-importer.php'));

	require_once(get_theme_file_path('/functions/admin/tgm/class-mfn-tgmpa.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-plugins.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-status.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-support.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-changelog.php'));
	require_once(get_theme_file_path('/functions/admin/class-mfn-tools.php'));

	if( $bebuilder_access ){
		require_once(get_theme_file_path('/visual-builder/visual-builder.php'));
	}

}

/**
 * @deprecated 21.0.5
 * Below constants are deprecated and will be removed soon
 * Please check if you use these constants in your Child Theme
 */

define('THEME_DIR', get_template_directory());
define('THEME_URI', get_template_directory_uri());

define('THEME_NAME', 'betheme');
define('THEME_VERSION', MFN_THEME_VERSION);

define('LIBS_DIR', get_template_directory() .'/functions');
define('LIBS_URI', get_template_directory() .'/functions');


