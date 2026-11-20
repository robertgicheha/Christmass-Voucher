<?php
/**
 * Plugin Name: EHS Proxy Plugin (GET + POST) + CV Upload/Preview (DigitalOcean Spaces)
 * Description: Proxies GET/POST requests from WordPress REST API to MIC/EHS portal
 *              and supports CV upload to DigitalOcean Spaces + browser preview (PDF.js).
 * Version: 1.3.0
 * Author: Executive Healthcare Solutions
 */
const REFCHECK_ORIGIN_SITE = 'micglobalrisks.com';

const REFCHECK_BACKEND_BASE = 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/reference-check.php';


if (!defined('CHRISTMAS_VOUCHER_FETCH_URL')) {
    define('CHRISTMAS_VOUCHER_FETCH_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/christmas-voucher/fetch-christmas-details.php');
}

if (!defined('CHRISTMAS_VOUCHER_SUBMIT_URL')) {
    define('CHRISTMAS_VOUCHER_SUBMIT_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/christmas-voucher/submit-christmas-details.php');
}

if (!defined('CHRISTMAS_VOUCHER_EDIT_URL')) {
    define('CHRISTMAS_VOUCHER_EDIT_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/christmas-voucher/edit-christmas-details.php');
}


if (!defined('REFCHECK_QUESTIONS_URL')) {
    define('REFCHECK_QUESTIONS_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/fetch-questions.php');
}

define('SURVEY_QUESTIONS_URL',     'https://portal.micglobalrisks.com:8082/portal/v1.0/api/employee_survey/fetch-questions.php');
define('SURVEY_SUBMIT_ANSWER_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/employee_survey/submit-answer.php');
define('SURVEY_SUBMIT_URL',        'https://portal.micglobalrisks.com:8082/portal/v1.0/api/employee_survey/submit-answers.php');
define('SURVEY_SEND_MAIL_URL',     'https://portal.micglobalrisks.com:8082/portal/v1.0/api/employee_survey/send-mail.php');

if (!defined('REFCHECK_SUBMIT_URL')) {
    define('REFCHECK_SUBMIT_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/submit-answers.php'); // confirm actual path
}

if (!defined('REFCHECK_SUBMIT_ANSWER_URL')) {
    define('REFCHECK_SUBMIT_ANSWER_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/submit-answer.php'); // confirm actual path
}

if (!defined('REFCHECK_SEND_MAIL_URL')) {
    define('REFCHECK_SEND_MAIL_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/send-mail.php'); // confirm actual path
}

if (!defined('REFCHECK_DECLINED_URL')) {
    define('REFCHECK_DECLINED_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/reference/reference-declined.php'); // confirm actual path
}


if (!defined('ABSPATH')) exit;

// ─── Single source of truth ───────────────────────────────────────────────────
// Both MIC and EHS share the SAME portal server.
// portal.executive-healthcare.com does NOT exist — do not define a separate URL for it.
if (!defined('EHS_PROXY_BASE_URL')) {
    define('EHS_PROXY_BASE_URL', 'https://portal.micglobalrisks.com:8082/portal/v1.0/api');
}
if (!defined('EHS_PROXY_TIMEOUT')) {
    define('EHS_PROXY_TIMEOUT', 15);
}

// ─── Confused-routing lists (centralised — one place to maintain) ─────────────
function ehs_proxy_confused_lists(): array
{
    return [
        'confused' => [
            'jnmalelu@executive-healthcare.com',
            'dwokubasu@executive-healthcare.com',
            'lgmuriithi@executive-healthcare.com',
            'jsang@mic-reinsurance.com',
        ],
        'confused_ehs' => [
            'mwwambugu@micglobalrisks.com',
            'apunjani@micglobalrisks.com',
            'afjanmohamed@cannonint.com',
        ],
    ];
}

// ─── Company detection ────────────────────────────────────────────────────────

/**
 * Detect which company database to target based on the live WordPress site URL.
 * This is the canonical detector — every other routing function calls this.
 *
 * Returns 'mic' or 'ehs'.
 */
function ehs_proxy_detect_company(): string
{
    $site_host = strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST));

    if (strpos($site_host, 'executive-healthcare.com') !== false) {
        return 'ehs';
    }

    if (
        strpos($site_host, 'micglobalrisks.com') !== false ||
        strpos($site_host, 'mic.local') !== false
    ) {
        return 'mic';
    }

    // Fallback to MIC for unknown hosts
    return 'mic';
}

/**
 * Detect company from an individual email address, with confused-list overrides.
 * Falls back to ehs_proxy_detect_company() when the email is ambiguous or absent.
 *
 * Returns 'mic' or 'ehs'.
 */
function ehs_proxy_detect_company_by_email(?string $email): string
{
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ehs_proxy_detect_company();
    }

    $lists  = ehs_proxy_confused_lists();
    $domain = substr(strrchr($email, '@'), 1);

    if (in_array($email, $lists['confused']))     return 'mic';
    if (in_array($email, $lists['confused_ehs'])) return 'ehs';

    if ($domain === 'micglobalrisks.com' || $domain === 'mic-reinsurance.com') return 'mic';
    if ($domain === 'executive-healthcare.com') return 'ehs';

    // Ambiguous domain — fall back to site-level detection
    return ehs_proxy_detect_company();
}

/**
 * Build the full portal URL for a given API path, scoped to the correct company.
 *
 * The shared portal at portal.micglobalrisks.com:8082 uses a `company` query
 * parameter to discriminate between MIC and EHS datasets.
 * If your portal uses a path prefix instead, switch to Option B below.
 */
function ehs_proxy_build_company_url(string $path, string $company = null): string
{
    if ($company === null) {
        $company = ehs_proxy_detect_company();
    }

    $base = rtrim(EHS_PROXY_BASE_URL, '/');
    $path = ltrim($path, '/');

    // Option A — company as a query parameter (default):
    return "{$base}/{$path}?company={$company}";

    // Option B — company as a path prefix (uncomment if your portal uses this):
    // return "{$base}/{$company}/{$path}";
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function ehs_const_str(string $name): string
{
    if (defined($name)) {
        $v = constant($name);
        return is_string($v) ? $v : '';
    }
    return '';
}

/**
 * JWT decode — supports both Firebase JWT v5 and v6+.
 */
function ehs_proxy_get_user_email(WP_REST_Request $request): ?string
{
    $auth = $request->get_header('authorization');
    if (!empty($auth)) {
        try {
            $secret_key = 'kimiko';
            if (class_exists('\Firebase\JWT\Key')) {
                $token = \Firebase\JWT\JWT::decode(
                    $auth,
                    new \Firebase\JWT\Key($secret_key, 'HS256')
                );
            } else {
                $token = \Firebase\JWT\JWT::decode($auth, $secret_key, ['HS256']);
            }
            if (!empty($token->data->username)) {
                return $token->data->username;
            }
        } catch (Exception $e) {
            // Silent fail — unauthenticated request
        }
    }

    $json = $request->get_json_params();
    if (is_array($json) && !empty($json['email'])) {
        return $json['email'];
    }

    return null;
}

/**
 * Build target URL, stripping internal-only query params and appending the
 * correct company scope.
 */
function ehs_proxy_build_target_url(string $path, array $query_params, string $company = null): string
{
    unset($query_params['path'], $query_params['debug'], $query_params['rest_route']);

    $base = rtrim(EHS_PROXY_BASE_URL, '/');
    $url  = $base . '/' . ltrim($path, '/');

    // Merge remaining caller params with company discriminator
    $company = $company ?? ehs_proxy_detect_company();
    $query_params['company'] = $company;

    return add_query_arg($query_params, $url);
}

function ehs_proxy_sanitize_path(string $path)
{
    $path = ltrim(trim($path), '/');

    if (preg_match('#^(https?:)?//#i', $path)) {
        return new WP_Error('invalid_path', 'Invalid path.', ['status' => 400]);
    }
    if (strpos($path, '..') !== false) {
        return new WP_Error('invalid_path', 'Invalid path.', ['status' => 400]);
    }
    if (!preg_match('#^[a-zA-Z0-9/_\.-]+$#', $path)) {
        return new WP_Error('invalid_path', 'Invalid path characters.', ['status' => 400]);
    }

    return $path;
}

function ehs_proxy_forward_headers(WP_REST_Request $request): array
{
    $headers = ['Accept' => 'application/json'];

    $auth   = $request->get_header('authorization');
    $apiKey = $request->get_header('x-api-key');

    if ($auth)   $headers['Authorization'] = $auth;
    if ($apiKey) $headers['X-API-Key']     = $apiKey;

    return $headers;
}

function ehs_proxy_is_debug(WP_REST_Request $request): bool
{
    if (defined('EHS_PROXY_DEBUG') && EHS_PROXY_DEBUG) return true;
    $d = $request->get_param('debug');
    return ($d === '1' || $d === 1 || $d === true || $d === 'true');
}

// ─── CORS ─────────────────────────────────────────────────────────────────────

function ehs_proxy_add_cors_headers(WP_REST_Response $resp, WP_REST_Request $req): WP_REST_Response
{
    $resp->header('Access-Control-Allow-Origin', '*');
    $resp->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $resp->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-EHS-Proxy-Secret');
    $resp->header('Access-Control-Max-Age', '86400');
    return $resp;
}

function ehs_proxy_options_response(WP_REST_Request $request): WP_REST_Response
{
    return ehs_proxy_add_cors_headers(new WP_REST_Response(null, 204), $request);
}

// ─── Permissions ──────────────────────────────────────────────────────────────

function ehs_proxy_permission(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') return true;

    $route = (string) $request->get_route();
    if (strpos($route, '/fetch') !== false) return true;

    $secret = ehs_const_str('EHS_PROXY_SECRET');
    if ($secret !== '') {
        $sent = $request->get_header('x-ehs-proxy-secret');
        if (!is_string($sent) || $sent !== $secret) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
    }

    return true;
}

// ─── REST endpoint registration ───────────────────────────────────────────────

add_action('rest_api_init', function () {

    $namespaces = ['ehs_proxy/v1', 'mic_proxy/v1', 'proxy-plugin/v1'];

    foreach ($namespaces as $ns) {

        // ── Core proxy ──────────────────────────────────────────────
        register_rest_route($ns, '/fetch', [
            'methods'             => ['GET', 'OPTIONS'],
            'callback'            => 'ehs_proxy_handle_get',
            'permission_callback' => 'ehs_proxy_permission',
        ]);

        register_rest_route($ns, '/send', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_handle_post',
            'permission_callback' => 'ehs_proxy_permission',
        ]);

        // ── CV handling ─────────────────────────────────────────────
        register_rest_route($ns, '/sign-cv-upload', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_sign_cv_upload',
            'permission_callback' => 'ehs_proxy_permission',
        ]);

        register_rest_route($ns, '/cv-preview', [
            'methods'             => ['GET', 'OPTIONS'],
            'callback'            => 'ehs_proxy_cv_preview',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/cv-stream', [
            'methods'             => ['GET', 'OPTIONS'],
            'callback'            => 'ehs_proxy_cv_stream',
            'permission_callback' => '__return_true',
        ]);

        // ── Lookups ─────────────────────────────────────────────────
        register_rest_route($ns, '/departments', [
            'methods'             => 'GET',
            'callback'            => 'ehs_proxy_get_departments',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/salary-expectations', [
            'methods'             => 'GET',
            'callback'            => 'ehs_proxy_get_salary_expectations',
            'permission_callback' => '__return_true',
        ]);

        // ── Reference check (context / section submit) ──────────────
        register_rest_route($ns, '/context', [
            'methods'             => 'GET',
            'callback'            => 'refcheck_proxy_get_context',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/submit', [
            'methods'             => 'POST',
            'callback'            => 'refcheck_proxy_submit_section',
            'permission_callback' => '__return_true',
        ]);

        // ── Reference check (questions / answers / mail) ────────────
        register_rest_route($ns, '/fetch-questions', [
            'methods'             => ['GET', 'POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_reference_check',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/submit-answers', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_reference_submit',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/submit-answer', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_reference_submit_answer',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/send-mail', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_reference_send_mail',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/reference-declined', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_reference_declined',
            'permission_callback' => '__return_true',
        ]);

        // ── Onboarding survey ───────────────────────────────────────
        register_rest_route($ns, '/fetch-survey-questions', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_survey_questions',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/save-survey-answer', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_survey_save_answer',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/submit-survey', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_survey_submit',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/send-survey-mail', [
            'methods'             => ['POST', 'OPTIONS'],
            'callback'            => 'ehs_proxy_survey_send_mail',
            'permission_callback' => '__return_true',
        ]);

        // ── Christmas voucher (details / submit / edit) ─────────────
                register_rest_route($ns, '/fetch-christmas-details', [
                    'methods'             => ['POST', 'OPTIONS'],
                    'callback'            => 'ehs_proxy_christmas_voucher_fetch',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route($ns, '/submit-christmas-details', [
                    'methods'             => ['POST', 'OPTIONS'],
                    'callback'            => 'ehs_proxy_christmas_voucher_submit',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route($ns, '/edit-christmas-details', [
                    'methods'             => ['POST', 'OPTIONS'],
                    'callback'            => 'ehs_proxy_christmas_voucher_edit',
                    'permission_callback' => '__return_true',
                ]);
    }
});

// Intercept raw output for cv-preview / cv-stream routes
add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
    $route = (string) $request->get_route();

    if (strpos($route, '/cv-preview') === false && strpos($route, '/cv-stream') === false) {
        return $served;
    }

    if ($result instanceof WP_HTTP_Response) {
        $status  = $result->get_status();
        $headers = $result->get_headers();
        $data    = $result->get_data();

        if (!headers_sent()) {
            status_header($status ?: 200);
            foreach ((array)$headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }

        echo is_string($data) ? $data : '';
        return true;
    }

    if (!headers_sent()) {
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo is_string($result) ? $result : '';
    return true;
}, 10, 4);

// ─── GET proxy ────────────────────────────────────────────────────────────────

function ehs_proxy_handle_get(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $path = $request->get_param('path');
    if (empty($path)) {
        $resp = new WP_REST_Response(['error' => '"path" parameter is required.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $path = ehs_proxy_sanitize_path($path);
    if (is_wp_error($path)) {
        $resp = new WP_REST_Response(['error' => $path->get_error_message()], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Public paths (no auth token) — detect company from WordPress site URL.
    // Authenticated paths — detect company from the user's email domain.
    $public_prefixes = ['jobs/', 'jobs'];
    $is_public       = false;
    foreach ($public_prefixes as $pp) {
        if (strpos($path, $pp) === 0) {
            $is_public = true;
            break;
        }
    }

    $company    = $is_public
        ? ehs_proxy_detect_company()                                     // site-level signal
        : ehs_proxy_detect_company_by_email(ehs_proxy_get_user_email($request)); // email signal

    $target_url  = ehs_proxy_build_target_url($path, $request->get_query_params(), $company);
    $debug       = ehs_proxy_is_debug($request);
    $headers_out = ehs_proxy_forward_headers($request);

    $remote = wp_remote_get($target_url, [
        'headers' => $headers_out,
        'timeout' => EHS_PROXY_TIMEOUT,
    ]);

    if (is_wp_error($remote)) {
        $resp = new WP_REST_Response([
            'error'   => true,
            'type'    => 'wp_error',
            'message' => $remote->get_error_message(),
            'data'    => $remote->get_error_data(),
            'debug'   => $debug ? ['target_url' => $target_url] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status  = wp_remote_retrieve_response_code($remote);
    $body    = wp_remote_retrieve_body($remote);
    $headers = wp_remote_retrieve_headers($remote);
    $decoded = json_decode($body, true);
    $is_json = (json_last_error() === JSON_ERROR_NONE);

    $resp_body = $is_json
        ? ['status' => $status, 'body' => $decoded]
        : ['status' => $status, 'body_raw' => $body];

    if ($debug) {
        $resp_body['debug'] = [
            'target_url'     => $target_url,
            'company'        => $company,
            'sent_headers'   => $headers_out,
            'remote_headers' => $headers,
        ];
    }

    $resp = new WP_REST_Response($resp_body, $status ?: 200);
    return ehs_proxy_add_cors_headers($resp, $request);
}


// ─── Christmas voucher ───────────────────────────────────────────────────────
// Page: /christmas-voucher/{employeeNumber}/{documentNumber}/
// Portal endpoints read a JSON body; the site host goes along as _originSite so
// the portal picks MIC or EHS NAV.

function ehs_proxy_christmas_voucher_code($value, int $max = 40): string
{
    return is_scalar($value) ? substr(preg_replace('/[^A-Za-z0-9\-_.]/', '', trim((string)$value)), 0, $max) : '';
}

/**
 * POST $payload to a portal Christmas voucher endpoint and relay the reply.
 * $allowed lists the success fields passed through to the page; error text comes from NAV.
 */
function ehs_proxy_christmas_voucher_relay(WP_REST_Request $request, string $url, array $payload, string $label, string $fallback, array $allowed, int $timeout)
{
    $debug = ehs_proxy_is_debug($request);

    $payload['_originSite'] = strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST));

    $remote = wp_remote_post($url, [
        'timeout' => $timeout,
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('CHRISTMAS_VOUCHER_INTERNAL_SECRET') ? CHRISTMAS_VOUCHER_INTERNAL_SECRET : '-m@_a/8n1Xdmz?T:',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/' . $label . '] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $fallback,
            'debug'   => $debug ? ['target_url' => $url, 'wp_error' => $remote->get_error_message()] : null,
        ], 502);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    // Strip any PHP warnings/notices prepended before the JSON
    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        error_log('❌ [ehs_proxy/' . $label . '] HTTP ' . $status . ' — unparseable body — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => $fallback,
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 502);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Backend/NAV returned a structured error — surface NAV's own message.
    // A 401 means the shared secret differs between WordPress and the portal: not the employee's concern.
    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('❌ [ehs_proxy/' . $label . '] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $status === 401 ? $fallback : ($decoded['message'] ?? $fallback),
            'debug'   => $debug ? ['target_url' => $url, 'body_raw' => $body] : null,
        ], ($status >= 400 && $status !== 401) ? $status : 502);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $out          = array_intersect_key($decoded, array_flip($allowed));
    $out['error'] = false;
    if ($debug) {
        $out['debug'] = ['target_url' => $url];
    }

    return ehs_proxy_add_cors_headers(new WP_REST_Response($out, 200), $request);
}

/** GET|POST /wp-json/ehs_proxy/v1/christmas-voucher-details  (employeeNumber, documentNumber) */
function ehs_proxy_christmas_voucher_fetch(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    // fetchChristmasVoucherDetails(employeeNumber : Code[40]; documentNumber : Code[40]) : Text
    $json           = $request->get_json_params() ?: [];
    $employeeNumber = ehs_proxy_christmas_voucher_code($json['employeeNumber'] ?? $request->get_param('employeeNumber'));
    $documentNumber = ehs_proxy_christmas_voucher_code($json['documentNumber'] ?? $request->get_param('documentNumber'));

    if ($employeeNumber === '' || $documentNumber === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'This voucher link is incomplete.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    return ehs_proxy_christmas_voucher_relay($request, CHRISTMAS_VOUCHER_FETCH_URL, [
        'employeeNumber' => $employeeNumber,
        'documentNumber' => $documentNumber,
    ], 'christmas-voucher-details', 'The voucher service is temporarily unavailable.',
        ['message', 'employee', 'supermarkets', 'optionSelected', 'data'], EHS_PROXY_TIMEOUT);
}

/** Shared by submit and edit: both save the choice with updateChristmasVoucherDetails. */
function ehs_proxy_christmas_voucher_save(WP_REST_Request $request, string $url, string $label)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    // updateChristmasVoucherDetails(employeeNumber : Code[40]; documentNumber : Code[40]; optionSelected : Code[30]) : Text
    $json            = $request->get_json_params() ?: [];
    $employeeNumber  = ehs_proxy_christmas_voucher_code($json['employeeNumber']  ?? '');
    $documentNumber  = ehs_proxy_christmas_voucher_code($json['documentNumber']  ?? '');
    $supermarketCode = ehs_proxy_christmas_voucher_code($json['supermarketCode'] ?? '', 30);

    if ($employeeNumber === '' || $documentNumber === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'This voucher link is incomplete.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
    if ($supermarketCode === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Please choose a supermarket.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    return ehs_proxy_christmas_voucher_relay($request, $url, [
        'employeeNumber'  => $employeeNumber,
        'documentNumber'  => $documentNumber,
        'supermarketCode' => $supermarketCode,
    ], $label, 'We could not save your choice right now. Please try again.',
        ['message', 'optionSelected', 'supermarket', 'data'], 30); // two NAV calls server-side: verify, then save
}

/** POST /wp-json/ehs_proxy/v1/christmas-voucher-submit */
function ehs_proxy_christmas_voucher_submit(WP_REST_Request $request)
{
    return ehs_proxy_christmas_voucher_save($request, CHRISTMAS_VOUCHER_SUBMIT_URL, 'christmas-voucher-submit');
}

/** POST /wp-json/ehs_proxy/v1/christmas-voucher-edit */
function ehs_proxy_christmas_voucher_edit(WP_REST_Request $request)
{
    return ehs_proxy_christmas_voucher_save($request, CHRISTMAS_VOUCHER_EDIT_URL, 'christmas-voucher-edit');
}



// ─── Reference check ─────────────────────────────────────────────────────────
function ehs_proxy_reference_check(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);

    // fetchReferenceQuestions(applicationNumber : Code[30]; refereeNumber : Integer) : Text
    $appNumber     = sanitize_text_field($request->get_param('appNumber'));
    $refereeNumber = $request->get_param('refereeNumber');

    if (empty($appNumber) || $refereeNumber === null || $refereeNumber === '') {
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => 'Missing appNumber or refereeNumber.',
            'data'    => null,
        ], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    if (!ctype_digit((string)$refereeNumber)) {
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => 'refereeNumber must be an integer.',
            'data'    => null,
        ], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
    $refereeNumber = absint($refereeNumber);

    $origin     = $_SERVER['HTTP_HOST'] ?? '';
    $target_url = REFCHECK_QUESTIONS_URL
        . '?appNumber='     . urlencode($appNumber)
        . '&refereeNumber=' . urlencode($refereeNumber)
        . '&_originSite='   . urlencode($origin);

    $remote = wp_remote_get($target_url, [
        'timeout' => EHS_PROXY_TIMEOUT,
        'headers' => [
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('REFCHECK_INTERNAL_SECRET') ? REFCHECK_INTERNAL_SECRET : 'EHS_REFCHECK_SECRET_2026',
        ],
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/fetch-questions] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'data'    => null,
            'debug'   => $debug ? ['target_url' => $target_url] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    // Strip any PHP warnings/notices prepended before the JSON — mirrors departments.php handling
    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    // Body genuinely couldn't be parsed as JSON — nothing to relay from NAV,
    // so fall back to a generic message. This is now separate from the
    // status>=500 check below, since a 500 with a valid NAV error body
    // should still show NAV's own message, not this generic fallback.
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('❌ [ehs_proxy/fetch-questions] HTTP ' . $status . ' — unparseable body — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Reference check service temporarily unavailable.',
            'data'         => null,
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 200); // 200 so JS catch sees it as a non-network error — mirrors departments.php
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Backend/NAV returned a structured error (bad appNumber, no records found,
    // NAV SOAP failure, etc.) — always surface NAV's own message when present,
    // whether the HTTP status is 4xx, 5xx, or the body just sets error=true.
    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('❌ [ehs_proxy/fetch-questions] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'data'    => null,
            'debug'   => $debug ? ['target_url' => $target_url, 'body_raw' => $body] : null,
        ], $status ?: 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // fetch-questions.php already splits NAV's flat array into applicant + questions —
    // just relay it through, same pattern as departments.php relaying its list.
      $resp = new WP_REST_Response([
        'error'         => false,
        'message'       => 'Success',
        'appNumber'     => $decoded['appNumber']     ?? $appNumber,
        'refereeNumber' => $decoded['refereeNumber'] ?? $refereeNumber,
        'referee'       => $decoded['referee']       ?? null,
        'applicant'     => $decoded['applicant']     ?? null,
        'questions'     => $decoded['questions']     ?? [],
        'debug'         => $debug ? ['target_url' => $target_url, 'question_count' => count($decoded['questions'] ?? [])] : null,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}
 

function ehs_proxy_reference_submit_answer(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $json  = $request->get_json_params();

    if (empty($json)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Empty request body'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $appNumber       = sanitize_text_field($json['appNumber']       ?? '');
    $refereeNumber   = $json['refereeNumber'] ?? '';
    $questionNumber  = isset($json['questionNumber']) ? (int)$json['questionNumber'] : 0;
    $subQuestionCode = (isset($json['subQuestionCode']) && $json['subQuestionCode'] !== '' && $json['subQuestionCode'] !== null)
        ? sanitize_text_field($json['subQuestionCode'])
        : null;
    $response = isset($json['response']) ? sanitize_textarea_field($json['response']) : '';

    if (empty($appNumber) || $refereeNumber === '' || $refereeNumber === null || !ctype_digit((string)$refereeNumber) || $questionNumber <= 0) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Missing appNumber, refereeNumber, or questionNumber.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $payload = [
        'appNumber'       => $appNumber,
        'refereeNumber'   => absint($refereeNumber),
        'questionNumber'  => $questionNumber,
        'subQuestionCode' => $subQuestionCode,
        'response'        => $response,
        '_originSite'     => strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST)),
    ];

    $remote = wp_remote_post(REFCHECK_SUBMIT_ANSWER_URL, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('REFCHECK_INTERNAL_SECRET') ? REFCHECK_INTERNAL_SECRET : 'EHS_REFCHECK_SECRET_2026',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/submit-answer] Q' . $questionNumber . ($subQuestionCode ? '/' . $subQuestionCode : '') . ' — ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'debug'   => $debug ? ['target_url' => REFCHECK_SUBMIT_ANSWER_URL] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    // Only treat it as unparseable if decoding genuinely failed — a valid
    // JSON error body on a 500 should still be forwarded, not masked.
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('❌ [ehs_proxy/submit-answer] Q' . $questionNumber . ' HTTP ' . $status . ' — unparseable body — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Could not save this answer right now.',
            'debug_status' => $status,
            'debug_body'   => substr($body, 0, 500),
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('❌ [ehs_proxy/submit-answer] Q' . $questionNumber . ' HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'debug'   => ['target_url' => REFCHECK_SUBMIT_ANSWER_URL, 'body_raw' => $body],
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response([
        'error'           => false,
        'message'         => 'Saved',
        'questionNumber'  => $questionNumber,
        'subQuestionCode' => $subQuestionCode,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}


function ehs_proxy_reference_submit(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $json  = $request->get_json_params();

    if (empty($json)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Empty request body'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $appNumber     = sanitize_text_field($json['appNumber']     ?? '');
    $refereeNumber = $json['refereeNumber'] ?? '';
    $answers       = $json['answers'] ?? [];

    if (empty($appNumber) || $refereeNumber === '' || $refereeNumber === null || !ctype_digit((string)$refereeNumber)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Missing appNumber or refereeNumber.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    if (!is_array($answers) || count($answers) === 0) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'No answers provided.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Sanitise each row — questionNumber must be a positive int; subQuestionCode
    // passes through as null unless it's one of Q4's A/B/C/D attribute codes.
    $clean_answers = [];
    foreach ($answers as $a) {
        $qn  = isset($a['questionNumber']) ? (int)$a['questionNumber'] : 0;
        $sub = (isset($a['subQuestionCode']) && $a['subQuestionCode'] !== '' && $a['subQuestionCode'] !== null)
            ? sanitize_text_field($a['subQuestionCode'])
            : null;
        $res = isset($a['response']) ? sanitize_textarea_field($a['response']) : '';
        if ($qn <= 0) continue;
        $clean_answers[] = ['questionNumber' => $qn, 'subQuestionCode' => $sub, 'response' => $res];
    }

    if (empty($clean_answers)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'No valid answers to submit.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $payload = [
        'appNumber'     => $appNumber,
        'refereeNumber' => absint($refereeNumber),
        'answers'       => $clean_answers,
        '_originSite'   => strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST)),
    ];

    $remote = wp_remote_post(REFCHECK_SUBMIT_URL, [
        'timeout' => 30, // 12 sequential NAV calls server-side — give it room
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('REFCHECK_INTERNAL_SECRET') ? REFCHECK_INTERNAL_SECRET : 'EHS_REFCHECK_SECRET_2026',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/submit-answers] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'debug'   => $debug ? ['target_url' => REFCHECK_SUBMIT_URL] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    if (json_last_error() !== JSON_ERROR_NONE || $status >= 500) {
        error_log('❌ [ehs_proxy/submit-answers] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Reference check submission service temporarily unavailable.',
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Includes NAV's 207 partial-failure case
    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('⚠️ [ehs_proxy/submit-answers] partial/failed submission HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'results' => $decoded['results'] ?? [],
            'debug'   => $debug ? ['target_url' => REFCHECK_SUBMIT_URL, 'body_raw' => $body] : null,
        ], $status ?: 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response([
        'error'   => false,
        'message' => 'Success',
        'results' => $decoded['results'] ?? [],
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}

function ehs_proxy_reference_send_mail(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $json  = $request->get_json_params();

    if (empty($json)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Empty request body'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $appNumber     = sanitize_text_field($json['appNumber']     ?? '');
    $refereeNumber = $json['refereeNumber'] ?? '';

    if (empty($appNumber) || $refereeNumber === '' || $refereeNumber === null || !ctype_digit((string)$refereeNumber)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Missing appNumber or refereeNumber.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $payload = [
        'appNumber'     => $appNumber,
        'refereeNumber' => absint($refereeNumber),
        '_originSite'   => strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST)),
    ];

    $remote = wp_remote_post(REFCHECK_SEND_MAIL_URL, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('REFCHECK_INTERNAL_SECRET') ? REFCHECK_INTERNAL_SECRET : 'EHS_REFCHECK_SECRET_2026',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/send-mail] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'debug'   => $debug ? ['target_url' => REFCHECK_SEND_MAIL_URL] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('❌ [ehs_proxy/send-mail] HTTP ' . $status . ' — unparseable body — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Could not send confirmation mail right now.',
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('❌ [ehs_proxy/send-mail] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'debug'   => $debug ? ['target_url' => REFCHECK_SEND_MAIL_URL, 'body_raw' => $body] : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response([
        'error'   => false,
        'message' => 'Success',
        'result'  => $decoded['result'] ?? null,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}

function ehs_proxy_reference_declined(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $json  = $request->get_json_params();

    if (empty($json)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Empty request body'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $appNumber     = sanitize_text_field($json['appNumber']     ?? '');
    $refereeNumber = $json['refereeNumber'] ?? '';
    $reason        = sanitize_text_field($json['reason']        ?? '');

    if (empty($appNumber) || $refereeNumber === '' || $refereeNumber === null || !ctype_digit((string)$refereeNumber)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Missing appNumber or refereeNumber.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $validReasons = [
        "I don't know this individual and don't wish to proceed",
        "I don't wish to proceed with the reference check",
    ];

    if (!in_array($reason, $validReasons, true)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Invalid or missing reason.'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $payload = [
        'appNumber'     => $appNumber,
        'refereeNumber' => absint($refereeNumber),
        'reason'        => $reason,
        '_originSite'   => strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST)),
    ];

    $remote = wp_remote_post(REFCHECK_DECLINED_URL, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('REFCHECK_INTERNAL_SECRET') ? REFCHECK_INTERNAL_SECRET : 'EHS_REFCHECK_SECRET_2026',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/reference-declined] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'debug'   => $debug ? ['target_url' => REFCHECK_DECLINED_URL] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('❌ [ehs_proxy/reference-declined] HTTP ' . $status . ' — unparseable body — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Could not notify HR team right now.',
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    if ($status >= 400 || !empty($decoded['error'])) {
        error_log('❌ [ehs_proxy/reference-declined] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'debug'   => $debug ? ['target_url' => REFCHECK_DECLINED_URL, 'body_raw' => $body] : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response([
        'error'   => false,
        'message' => 'Success',
        'result'  => $decoded['result'] ?? null,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}


function ehs_proxy_survey_id($v, $max = 30)
{
    return substr(preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$v), 0, $max);
}

function ehs_proxy_survey_throttle($bucket, $limit, $window = 300)
{
    $k = 'ehs_sv_' . md5($bucket . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $n = (int)get_transient($k);
    if ($n >= $limit) return false;
    set_transient($k, $n + 1, $window);
    return true;
}

function ehs_proxy_survey_unavailable(WP_REST_Request $request, $why)
{
    error_log('❌ [survey] ' . $why);
    $out = ['error' => true, 'message' => 'The survey service is unavailable. Please try again shortly.'];
    if (defined('EHS_SURVEY_DEBUG') && EHS_SURVEY_DEBUG) {
        $out['why'] = $why;   // TEMPORARY: remove after testing
    }
    return ehs_proxy_survey_reply($request, $out, 503);
}

function ehs_proxy_survey_reply(WP_REST_Request $request, array $body, $status = 200)
{
    return ehs_proxy_add_cors_headers(new WP_REST_Response($body, $status), $request);
}

/** POST to the backend and relay ONLY whitelisted fields. Error text comes from NAV. */
function ehs_proxy_survey_relay(WP_REST_Request $request, $url, array $payload, $timeout, array $allowed)
{
    $token = defined('SURVEY_INTERNAL_SECRET') ? SURVEY_INTERNAL_SECRET : '';
    if ($token === '') {
        return ehs_proxy_survey_unavailable($request, 'SURVEY_INTERNAL_SECRET is not defined in wp-config.php');
    }

    $payload['_originSite'] = strtolower(wp_parse_url(get_site_url(), PHP_URL_HOST));

    $remote = wp_remote_post($url, [
        'timeout' => $timeout,
        'headers' => [
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json',
            'X-Internal-Token' => $token,
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($remote)) {
        return ehs_proxy_survey_unavailable($request, 'request failed: ' . $remote->get_error_message());
    }

    $status  = wp_remote_retrieve_response_code($remote);
    $body    = wp_remote_retrieve_body($remote);
    $start   = strpos($body, '{');   // strip any PHP warnings printed before the JSON
    $decoded = json_decode($start !== false ? substr($body, $start) : $body, true);

    if (!is_array($decoded) || in_array($status, [401, 403, 404, 500, 503], true)) {
        return ehs_proxy_survey_unavailable($request, 'backend HTTP ' . $status . ' @ ' . $url . ' : ' . substr($body, 0, 300));
    }

    if ($status >= 400 || !empty($decoded['error'])) {
        $msg = (string)($decoded['message'] ?? '');
        $out = ['error' => true, 'message' => $msg !== '' ? $msg : 'The survey service is unavailable. Please try again shortly.']; 
        if (!empty($decoded['alreadySubmitted'])) {
            $out['alreadySubmitted'] = true;
        }  // NAV's message
        if (!empty($decoded['results']) && is_array($decoded['results'])) {
            $out['results'] = array_map(function ($r) {
                return array_intersect_key((array)$r, array_flip(['questionCode', 'error', 'message']));
            }, $decoded['results']);
        }
        return ehs_proxy_survey_reply($request, $out, $status === 207 ? 207 : 422);
    }

    $out          = array_intersect_key($decoded, array_flip($allowed));
    $out['error'] = false;
    return ehs_proxy_survey_reply($request, $out, 200);
}

function ehs_proxy_survey_questions(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }
    if (!ehs_proxy_survey_throttle('q', 30)) {
        return ehs_proxy_survey_reply($request, ['error' => true, 'message' => 'Too many requests. Please wait a few minutes.'], 429);
    }
    $j = $request->get_json_params() ?: [];
    return ehs_proxy_survey_relay($request, SURVEY_QUESTIONS_URL, [
        'employeeNumber' => ehs_proxy_survey_id($j['employeeNumber'] ?? ''),
        'surveyCode'     => ehs_proxy_survey_id($j['surveyCode'] ?? ''),
        ], EHS_PROXY_TIMEOUT, ['message', 'alreadySubmitted', 'respondent', 'sections', 'openQuestions', 'savedAnswers']);
}

function ehs_proxy_survey_save_answer(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }
    if (!ehs_proxy_survey_throttle('a', 300)) {
        return ehs_proxy_survey_reply($request, ['error' => true, 'message' => 'Too many requests. Please wait a few minutes.'], 429);
    }
    $j = $request->get_json_params() ?: [];
    return ehs_proxy_survey_relay($request, SURVEY_SUBMIT_ANSWER_URL, [
        'employeeNumber' => ehs_proxy_survey_id($j['employeeNumber'] ?? ''),
        'questionCode'   => ehs_proxy_survey_id($j['questionCode'] ?? '', 20),
        'answerOption'   => max(0, min(5, (int)($j['answerOption'] ?? 0))),
        'answerText'     => sanitize_textarea_field($j['answerText'] ?? ''),
    ], 20, []);
}

function ehs_proxy_survey_submit(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }
    if (!ehs_proxy_survey_throttle('s', 20)) {
        return ehs_proxy_survey_reply($request, ['error' => true, 'message' => 'Too many requests. Please wait a few minutes.'], 429);
    }
    $j = $request->get_json_params() ?: [];

    $clean = [];
    foreach ((array)($j['answers'] ?? []) as $a) {
        $a = is_array($a) ? $a : [];
        $clean[] = [
            'questionCode' => ehs_proxy_survey_id($a['questionCode'] ?? '', 20),
            'answerOption' => max(0, min(5, (int)($a['answerOption'] ?? 0))),
            'answerText'   => sanitize_textarea_field($a['answerText'] ?? ''),
        ];
    }

    // An empty list is valid: every answer may already be saved.
    return ehs_proxy_survey_relay($request, SURVEY_SUBMIT_URL, [
        'employeeNumber' => ehs_proxy_survey_id($j['employeeNumber'] ?? ''),
        'surveyCode'     => ehs_proxy_survey_id($j['surveyCode'] ?? ''),
        'answers'        => $clean,
        'complete'       => !empty($j['complete']),
    ], 60, ['results']);
}

function ehs_proxy_survey_send_mail(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }
    if (!ehs_proxy_survey_throttle('m', 10)) {
        return ehs_proxy_survey_reply($request, ['error' => true, 'message' => 'Too many requests. Please wait a few minutes.'], 429);
    }
    $j = $request->get_json_params() ?: [];
    return ehs_proxy_survey_relay($request, SURVEY_SEND_MAIL_URL, [
        'employeeNumber' => ehs_proxy_survey_id($j['employeeNumber'] ?? ''),
        'surveyCode'     => ehs_proxy_survey_id($j['surveyCode'] ?? ''),
    ], 20, ['message']);
}



function ehs_proxy_get_departments(WP_REST_Request $request): WP_REST_Response
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }
 
    $debug = ehs_proxy_is_debug($request);
 
    // $target_url = 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/jobs/departments.php';
    $origin     = $_SERVER['HTTP_HOST'] ?? '';
    $target_url = 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/jobs/departments.php'
            . '?_originSite=' . urlencode($origin);
 
    $remote = wp_remote_get($target_url, [
        'timeout' => EHS_PROXY_TIMEOUT,
        'headers' => [
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('EHS_PORTAL_SECRET') ? EHS_PORTAL_SECRET : 'EHS_DEPTS_SECRET_2026',
        ],
    ]);
 
    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/departments] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'data'    => [],
            'debug'   => $debug ? ['target_url' => $target_url] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
 
    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);
 
    // Strip any PHP warnings/notices prepended before the JSON — mirrors verify-member.php handling
    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);
 
    if (json_last_error() !== JSON_ERROR_NONE || $status >= 500) {
        error_log('❌ [ehs_proxy/departments] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Department service temporarily unavailable.',
            'data'         => [],
            'debug_status' => $debug ? $status : null,
            'debug_body'   => $debug ? substr($body, 0, 500) : null,
        ], 200); // 200 so JS catch sees it as a non-network error — mirrors verify-member
        return ehs_proxy_add_cors_headers($resp, $request);
    }
 
    // Portal returned a structured error (e.g. 401 Unauthorized)
    if ($status >= 400) {
        error_log('❌ [ehs_proxy/departments] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'data'    => [],
            'debug'   => $debug ? ['target_url' => $target_url, 'body_raw' => $body] : null,
        ], $status);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
 
    // departments.php always returns { error, message, data: [...] }
    // Normalise defensively in case shape ever changes
    $list = null;
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $list = $decoded['data'];
    } elseif (isset($decoded['departments']) && is_array($decoded['departments'])) {
        $list = $decoded['departments'];
    } elseif (is_array($decoded) && !isset($decoded['error'])) {
        $list = $decoded;
    }
 
    if (!is_array($list)) {
        error_log('❌ [ehs_proxy/departments] Unexpected shape: ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => 'Unexpected response shape from department service.',
            'data'    => [],
            'debug'   => $debug ? ['decoded' => $decoded] : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
 
    $resp = new WP_REST_Response([
        'error'   => false,
        'message' => 'Success',
        'data'    => $list,
        'debug'   => $debug ? ['target_url' => $target_url, 'dept_count' => count($list)] : null,
    ], 200);
 
    return ehs_proxy_add_cors_headers($resp, $request);
}

function ehs_proxy_get_salary_expectations(WP_REST_Request $request): WP_REST_Response
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $origin = $_SERVER['HTTP_HOST'] ?? '';

    // city: 1 = Kenya, 2 = Uganda, 3 = Tanzania
    $city = (int)$request->get_param('city');
    if (!in_array($city, [1, 2, 3], true)) {
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => 'Invalid or missing city parameter.',
            'data'    => [],
        ], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $target_url = 'https://portal.micglobalrisks.com:8082/portal/v1.0/api/jobs/salary-expectations.php'
            . '?city=' . urlencode((string)$city)
            . '&_originSite=' . urlencode($origin);

    $remote = wp_remote_get($target_url, [
        'timeout' => EHS_PROXY_TIMEOUT,
        'headers' => [
            'Accept'           => 'application/json',
            'X-Internal-Token' => defined('EHS_PORTAL_SECRET') ? EHS_PORTAL_SECRET : 'EHS_DEPTS_SECRET_2026',
        ],
    ]);

    if (is_wp_error($remote)) {
        error_log('❌ [ehs_proxy/salary-expectations] ' . $remote->get_error_message());
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $remote->get_error_message(),
            'data'    => [],
            'debug'   => $debug ? ['target_url' => $target_url] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    // Strip any PHP warnings/notices prepended before the JSON
    $json_start = strpos($body, '{');
    $clean_body = ($json_start !== false) ? substr($body, $json_start) : $body;
    $decoded    = json_decode($clean_body, true);

    if (json_last_error() !== JSON_ERROR_NONE || $status >= 500) {
        error_log('❌ [ehs_proxy/salary-expectations] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'        => true,
            'message'      => 'Salary expectations service temporarily unavailable.',
            'data'         => [],
            'debug_status' => $status,                              // ✅ always included — just an HTTP code, not sensitive
            'debug_body'   => $debug ? substr($body, 0, 500) : null, // raw body still gated behind debug flag
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
        }

    if ($status >= 400) {
        error_log('❌ [ehs_proxy/salary-expectations] HTTP ' . $status . ' — ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => $decoded['message'] ?? ('Portal returned HTTP ' . $status),
            'data'    => [],
            'debug'   => $debug ? ['target_url' => $target_url, 'body_raw' => $body] : null,
        ], $status);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // salary-expectations.php always returns { error, message, data: [...] }
    $list = null;
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $list = $decoded['data'];
    } elseif (is_array($decoded) && !isset($decoded['error'])) {
        $list = $decoded;
    }

    if (!is_array($list)) {
        error_log('❌ [ehs_proxy/salary-expectations] Unexpected shape: ' . substr($body, 0, 300));
        $resp = new WP_REST_Response([
            'error'   => true,
            'message' => 'Unexpected response shape from salary expectations service.',
            'data'    => [],
            'debug'   => $debug ? ['decoded' => $decoded] : null,
        ], 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response([
        'error'   => false,
        'message' => 'Success',
        'data'    => $list,
        'debug'   => $debug ? ['target_url' => $target_url, 'band_count' => count($list)] : null,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}


// ─── POST proxy ───────────────────────────────────────────────────────────────

function ehs_proxy_handle_post(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $debug = ehs_proxy_is_debug($request);
    $path  = $request->get_param('path');
    $json  = $request->get_json_params();

    if (empty($path) && is_array($json) && !empty($json['path'])) {
        $path = $json['path'];
        unset($json['path']);
    }

    if (empty($path)) {
        $resp = new WP_REST_Response(['error' => '"path" parameter is required (query or JSON body).'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $path = ehs_proxy_sanitize_path($path);
    if (is_wp_error($path)) {
        $resp = new WP_REST_Response(['error' => $path->get_error_message()], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    // Job application submissions get dedicated handling
    if (strpos($path, 'job') !== false && strpos($path, 'apply') !== false) {
        $result = ehs_proxy_handle_job_application($request, $json);
        $resp   = new WP_REST_Response($result, 200);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $email      = ehs_proxy_get_user_email($request);
    $company    = ehs_proxy_detect_company_by_email($email);
    $target_url = ehs_proxy_build_target_url($path, $request->get_query_params(), $company);

    $headers_out                  = ehs_proxy_forward_headers($request);
    $headers_out['Content-Type']  = 'application/json; charset=utf-8';

    $raw_body = $request->get_body();
    $body_out = (is_array($json) && !empty($json)) ? wp_json_encode($json) : $raw_body;

    $remote = wp_remote_request($target_url, [
        'method'  => 'POST',
        'headers' => $headers_out,
        'timeout' => EHS_PROXY_TIMEOUT,
        'body'    => $body_out,
    ]);

    if (is_wp_error($remote)) {
        $resp = new WP_REST_Response([
            'error'   => true,
            'type'    => 'wp_error',
            'message' => $remote->get_error_message(),
            'data'    => $remote->get_error_data(),
            'debug'   => $debug ? ['target_url' => $target_url] : null,
        ], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status  = wp_remote_retrieve_response_code($remote);
    $body    = wp_remote_retrieve_body($remote);
    $headers = wp_remote_retrieve_headers($remote);
    $decoded = json_decode($body, true);
    $is_json = (json_last_error() === JSON_ERROR_NONE);

    $resp_body = $is_json
        ? ['status' => $status, 'body' => $decoded]
        : ['status' => $status, 'body_raw' => $body];

    if ($debug) {
        $resp_body['debug'] = [
            'target_url'        => $target_url,
            'company'           => $company,
            'sent_headers'      => $headers_out,
            'remote_headers'    => $headers,
            'sent_body_preview' => is_string($body_out) ? substr($body_out, 0, 1000) : null,
        ];
    }

    $resp = new WP_REST_Response($resp_body, $status ?: 200);
    return ehs_proxy_add_cors_headers($resp, $request);
}

// ─── Job application handler ──────────────────────────────────────────────────

function ehs_proxy_handle_job_application(WP_REST_Request $request, array $json): array
{
    // Guard: must have at minimum an email to route correctly
    if (empty($json)) {
        return ['error' => true, 'message' => 'Empty request body'];
    }

    error_log('📋 Job Application Submission Received: ' . json_encode(array_keys($json)));

    try {
        $applicant_email = isset($json['eMail']) ? trim($json['eMail']) : '';

        // FIX: if no applicant email, fall back to site-level company so the
        // submission still goes somewhere rather than silently mis-routing.
        if (empty($applicant_email)) {
            error_log('⚠️  No applicant email in payload — falling back to site-level company');
        }
        //BEFORE
        //$company    = ehs_proxy_detect_company_by_email($applicant_email ?: null);

		//AFTER
        $req_number = isset($json['reqNumber']) ? strtoupper(trim($json['reqNumber'])) : '';
	    if (str_starts_with($req_number, 'MIC')) {
        $company = 'mic';
           } elseif (str_starts_with($req_number, 'EHS')) {
           $company = 'ehs';
           } else {
          $company = ehs_proxy_detect_company_by_email($applicant_email ?: null);
        }

        error_log('🏢 Routing decision: reqNumber=' . $req_number . ' → company=' . $company);

        $target_url = rtrim(EHS_PROXY_BASE_URL, '/') . '/jobs/apply?company=' . $company;

        error_log('📤 Forwarding job application to: ' . $target_url);
        error_log('📧 Applicant: ' . $applicant_email . ' → company: ' . $company);

        $headers_out = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
        ];

        $auth = $request->get_header('authorization');
        if ($auth) $headers_out['Authorization'] = $auth;

        $remote = wp_remote_request($target_url, [
            'method'  => 'POST',
            'headers' => $headers_out,
            'timeout' => 30,
            'body'    => json_encode($json),
        ]);

        if (is_wp_error($remote)) {
            error_log('❌ WP Remote Error: ' . $remote->get_error_message());
            return [
                'error'   => true,
                'message' => 'Failed to submit application: ' . $remote->get_error_message(),
            ];
        }

        $status  = wp_remote_retrieve_response_code($remote);
        $body    = wp_remote_retrieve_body($remote);
        $decoded = json_decode($body, true);

        error_log('✅ API Response (HTTP ' . $status . '): ' . substr($body, 0, 500));

        if ($status >= 400) {
            return [
                'error'   => true,
                'message' => isset($decoded['message']) ? $decoded['message'] : 'API returned HTTP ' . $status,
                'status'  => $status,
            ];
        }

        return $decoded; 
    } catch (Exception $e) {
        error_log('❌ Job Application Error: ' . $e->getMessage());
        return [
            'error'   => true,
            'message' => 'Failed to submit application: ' . $e->getMessage(),
        ];
    }
}

/* ============================================================
   CV Upload + Preview (DigitalOcean Spaces)
   ============================================================ */

function ehs_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ehs_b64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) $data .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($data, '-_', '+/'));
}

function ehs_proxy_sign_cv_upload(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $space     = ehs_const_str('DO_SPACE_NAME');
    $region    = ehs_const_str('DO_SPACE_REGION');
    $accessKey = ehs_const_str('DO_SPACES_KEY');
    $secretKey = ehs_const_str('DO_SPACES_SECRET');

    if ($space === '' || $region === '' || $accessKey === '' || $secretKey === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Spaces config missing'], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $json        = $request->get_json_params();
    $filename    = isset($json['filename'])    ? trim((string)$json['filename'])    : '';
    $contentType = isset($json['contentType']) ? trim((string)$json['contentType']) : '';
    $reqNumber   = isset($json['reqNumber'])   ? trim((string)$json['reqNumber'])   : 'unknown';

    if ($filename === '' || $contentType === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'filename/contentType required'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }
    if ($contentType !== 'application/pdf') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Only PDF allowed'], 400);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $safeReq  = preg_replace('/[^A-Za-z0-9_-]/', '_', $reqNumber);
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    if (!preg_match('/\.pdf$/i', $safeName)) $safeName .= '.pdf';

    $unique    = bin2hex(random_bytes(8));
    $objectKey = "JobApplications/{$safeReq}/cv_{$unique}_{$safeName}";

    $expires      = 300;
    $host         = "{$space}.{$region}.digitaloceanspaces.com";
    $endpoint     = "https://{$host}";
    $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($objectKey));

    $amzDate         = gmdate('Ymd\THis\Z');
    $dateStamp       = gmdate('Ymd');
    $service         = 's3';
    $algorithm       = 'AWS4-HMAC-SHA256';
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $signedHeaders   = 'content-type;host';

    $query = [
        'X-Amz-Algorithm'     => $algorithm,
        'X-Amz-Credential'    => $accessKey . '/' . $credentialScope,
        'X-Amz-Date'          => $amzDate,
        'X-Amz-Expires'       => (string)$expires,
        'X-Amz-SignedHeaders' => $signedHeaders,
    ];
    ksort($query);
    $canonicalQueryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $canonicalHeaders = 'content-type:' . $contentType . "\n" . 'host:' . $host . "\n";

    $canonicalRequest =
        "PUT\n" .
        $canonicalUri . "\n" .
        $canonicalQueryString . "\n" .
        $canonicalHeaders . "\n" .
        $signedHeaders . "\n" .
        'UNSIGNED-PAYLOAD';

    $stringToSign =
        $algorithm . "\n" .
        $amzDate . "\n" .
        $credentialScope . "\n" .
        hash('sha256', $canonicalRequest);

    $kDate     = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion   = hash_hmac('sha256', $region, $kDate, true);
    $kService  = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning  = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $uploadUrl = $endpoint . $canonicalUri . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;
    $fileUrl   = $endpoint . '/' . $objectKey;

    $viewerUrl = null;
    $cvSecret  = ehs_const_str('EHS_CV_SIGN_SECRET');
    if ($cvSecret !== '') {
        $k         = ehs_b64url_encode($objectKey);
        $sig       = hash_hmac('sha256', $k, $cvSecret);
        $viewerUrl = home_url('/?rest_route=/ehs_proxy/v1/cv-preview&k=' . rawurlencode($k) . '&sig=' . rawurlencode($sig));
    }

    $resp = new WP_REST_Response([
        'error'            => false,
        'uploadUrl'        => $uploadUrl,
        'fileUrl'          => $fileUrl,
        'key'              => $objectKey,
        'viewerUrl'        => $viewerUrl,
        'expiresInSeconds' => $expires,
    ], 200);

    return ehs_proxy_add_cors_headers($resp, $request);
}

function ehs_verify_signed_key(WP_REST_Request $request, &$objectKeyOut = null)
{
    $k   = (string)$request->get_param('k');
    $sig = (string)$request->get_param('sig');

    if ($k === '' || $sig === '') {
        return new WP_Error('bad_request', 'Missing k or sig.', ['status' => 400]);
    }

    $cvSecret = ehs_const_str('EHS_CV_SIGN_SECRET');
    if ($cvSecret === '') {
        return new WP_Error('config', 'EHS_CV_SIGN_SECRET is not set.', ['status' => 500]);
    }

    $expected = hash_hmac('sha256', $k, $cvSecret);
    if (!hash_equals($expected, $sig)) {
        return new WP_Error('forbidden', 'Invalid signature.', ['status' => 403]);
    }

    $decoded = ehs_b64url_decode($k);
    $decoded = is_string($decoded) ? trim($decoded) : '';

    if ($decoded === '' || stripos($decoded, 'JobApplications/') !== 0) {
        return new WP_Error('forbidden', 'Invalid key path.', ['status' => 403]);
    }

    $objectKeyOut = $decoded;
    return true;
}

function ehs_proxy_cv_preview(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $objectKey = null;
    $ok = ehs_verify_signed_key($request, $objectKey);
    if (is_wp_error($ok)) {
        $status = (int)($ok->get_error_data()['status'] ?? 400);
        $resp   = new WP_REST_Response(['error' => true, 'message' => $ok->get_error_message()], $status);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $k         = rawurlencode((string)$request->get_param('k'));
    $sig       = rawurlencode((string)$request->get_param('sig'));
    $streamUrl = home_url('/?rest_route=/ehs_proxy/v1/cv-stream&k=' . $k . '&sig=' . $sig);

    $pdfjs       = plugins_url('assets/pdfjs/pdf.min.js', __FILE__);
    $pdfjsWorker = plugins_url('assets/pdfjs/pdf.worker.min.js', __FILE__);

    $html = '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>CV Preview</title>
<style>
  body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f6f6f6;color:#222}
  .bar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #ddd;padding:10px;display:grid;grid-template-columns:1fr 1fr 1fr;align-items:center;gap:10px;}
  .bar-left{justify-self:start;}
  .bar-center{justify-self:center;display:flex;gap:10px;align-items:center;}
  .bar-right{justify-self:end;}
  .btn{padding:8px 12px;border:1px solid #35428b;background:#35428b;color:#fff;border-radius:8px;cursor:pointer;font-weight:700}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .meta{font-size:13px;color:#555;}
  .wrap{max-width:980px;margin:0 auto;padding:14px}
  canvas{width:100%;background:#fff;box-shadow:0 10px 25px rgba(0,0,0,.08);border-radius:10px}
  .hint{font-size:12px;color:#666;margin-top:10px;line-height:1.4}
</style>
<script src="' . $pdfjs . '"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = "' . $pdfjsWorker . '";</script>
</head>
<body>
  <div class="bar">
    <div class="bar-left"><button class="btn" id="printBtn" type="button">Print</button></div>
    <div class="bar-center">
      <button class="btn" id="prevBtn">Prev</button>
      <button class="btn" id="nextBtn">Next</button>
    </div>
    <div class="bar-right meta">Page <span id="pageNum">1</span> / <span id="pageCount">?</span></div>
  </div>
  <div class="wrap">
    <canvas id="cvCanvas"></canvas>
    <div class="hint">Download controls are disabled in this preview.</div>
  </div>
<script>
(function(){
  document.addEventListener("contextmenu",function(e){e.preventDefault();});
  async function main(){
    if(!window.pdfjsLib){
      document.body.innerHTML="<div style=\'padding:18px\'>PDF viewer unavailable.</div>";
      return;
    }
    const streamUrl=' . json_encode($streamUrl) . ';
    const canvas=document.getElementById("cvCanvas");
    const ctx=canvas.getContext("2d");
    let pdfDoc=null,pageNum=1;
    async function loadPdf(){
      const res=await fetch(streamUrl,{cache:"no-store"});
      if(!res.ok)throw new Error("Could not load CV (HTTP "+res.status+")");
      const buf=await res.arrayBuffer();
      pdfDoc=await pdfjsLib.getDocument({data:buf}).promise;
      document.getElementById("pageCount").textContent=pdfDoc.numPages;
      await renderPage(pageNum);
    }
    async function renderPage(num){
      const page=await pdfDoc.getPage(num);
      const viewport=page.getViewport({scale:1.6});
      canvas.height=viewport.height;canvas.width=viewport.width;
      await page.render({canvasContext:ctx,viewport}).promise;
      document.getElementById("pageNum").textContent=num;
      document.getElementById("prevBtn").disabled=(num<=1);
      document.getElementById("nextBtn").disabled=(num>=pdfDoc.numPages);
    }
    async function printPdf(){
      const res=await fetch(streamUrl,{cache:"no-store"});
      if(!res.ok)throw new Error("Could not load CV for printing");
      const blob=await res.blob();
      const url=URL.createObjectURL(blob);
      const iframe=document.createElement("iframe");
      Object.assign(iframe.style,{position:"fixed",right:"0",bottom:"0",width:"0",height:"0",border:"0"});
      iframe.src=url;document.body.appendChild(iframe);
      iframe.onload=function(){
        try{iframe.contentWindow.focus();iframe.contentWindow.print();}
        finally{setTimeout(function(){URL.revokeObjectURL(url);iframe.remove();},2000);}
      };
    }
    document.getElementById("printBtn").addEventListener("click",async function(){try{await printPdf();}catch(e){alert("Print failed: "+String(e.message||e));}});
    document.getElementById("prevBtn").addEventListener("click",async function(){if(!pdfDoc||pageNum<=1)return;pageNum--;await renderPage(pageNum);});
    document.getElementById("nextBtn").addEventListener("click",async function(){if(!pdfDoc||pageNum>=pdfDoc.numPages)return;pageNum++;await renderPage(pageNum);});
    try{await loadPdf();}catch(e){document.body.innerHTML="<div style=\'padding:18px\'>Failed to load CV preview.<br/>"+String(e.message)+"</div>";}
  }
  main();
})();
</script>
</body>
</html>';

    $resp = new WP_REST_Response($html, 200);
    $resp->header('Content-Type', 'text/html; charset=utf-8');
    $resp->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $resp->header('Pragma', 'no-cache');
    return $resp;
}

function ehs_proxy_cv_stream(WP_REST_Request $request)
{
    if ($request->get_method() === 'OPTIONS') {
        return ehs_proxy_options_response($request);
    }

    $objectKey = null;
    $ok = ehs_verify_signed_key($request, $objectKey);
    if (is_wp_error($ok)) {
        $status = (int)($ok->get_error_data()['status'] ?? 400);
        $resp   = new WP_REST_Response(['error' => true, 'message' => $ok->get_error_message()], $status);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $space     = ehs_const_str('DO_SPACE_NAME');
    $region    = ehs_const_str('DO_SPACE_REGION');
    $accessKey = ehs_const_str('DO_SPACES_KEY');
    $secretKey = ehs_const_str('DO_SPACES_SECRET');

    if ($space === '' || $region === '' || $accessKey === '' || $secretKey === '') {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Spaces config missing'], 500);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $expires         = 60;
    $service         = 's3';
    $algorithm       = 'AWS4-HMAC-SHA256';
    $amzDate         = gmdate('Ymd\THis\Z');
    $dateStamp       = gmdate('Ymd');
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $signedHeaders   = 'host';

    $host         = "{$space}.{$region}.digitaloceanspaces.com";
    $endpoint     = "https://{$host}";
    $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($objectKey));

    $query = [
        'X-Amz-Algorithm'     => $algorithm,
        'X-Amz-Credential'    => $accessKey . '/' . $credentialScope,
        'X-Amz-Date'          => $amzDate,
        'X-Amz-Expires'       => (string)$expires,
        'X-Amz-SignedHeaders' => $signedHeaders,
    ];
    ksort($query);
    $canonicalQueryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $canonicalRequest =
        "GET\n" .
        $canonicalUri . "\n" .
        $canonicalQueryString . "\n" .
        'host:' . $host . "\n\n" .
        $signedHeaders . "\n" .
        'UNSIGNED-PAYLOAD';

    $stringToSign =
        $algorithm . "\n" .
        $amzDate . "\n" .
        $credentialScope . "\n" .
        hash('sha256', $canonicalRequest);

    $kDate     = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion   = hash_hmac('sha256', $region, $kDate, true);
    $kService  = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning  = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $signedGet = $endpoint . $canonicalUri . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;

    $remote = wp_remote_get($signedGet, [
        'timeout' => 20,
        'headers' => ['Accept' => 'application/pdf'],
    ]);

    if (is_wp_error($remote)) {
        $resp = new WP_REST_Response(['error' => true, 'message' => $remote->get_error_message()], 502);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $status = wp_remote_retrieve_response_code($remote);
    $body   = wp_remote_retrieve_body($remote);

    if ($status >= 400) {
        $resp = new WP_REST_Response(['error' => true, 'message' => 'Spaces returned HTTP ' . $status], 502);
        return ehs_proxy_add_cors_headers($resp, $request);
    }

    $resp = new WP_REST_Response($body, 200);
    $resp->header('Content-Type', 'application/pdf');
    $resp->header('Content-Disposition', 'inline');
    $resp->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $resp->header('Pragma', 'no-cache');
    $resp->header('X-Content-Type-Options', 'nosniff');
    $resp->header('X-Download-Options', 'noopen');
    return $resp;
}