<?php
if (!defined('ABSPATH')) exit;

class SUM_Customer_Email_Handler {

    private $customer_db;
    private $pdf_generator;

    public function __construct($customer_database) {
        $this->customer_db = $customer_database;

        if (!class_exists('SUM_Customer_PDF_Generator')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-pdf-generator.php';
        }
        $this->pdf_generator = new SUM_Customer_PDF_Generator($this->customer_db);

        add_shortcode('sum_logo', array($this, 'render_logo_shortcode'));
    }

    /* ---------- Shortcode: [sum_logo] ---------- */
    public function render_logo_shortcode($atts = [], $content = null) {
        $logo_url     = $this->get_setting('company_logo', '');
        $company_name = $this->get_setting('company_name', 'Your Company Name');

        if (empty($logo_url)) {
            return '<span style="font-size:24px;font-weight:700;color:#333;">' . esc_html($company_name) . '</span>';
        }
        return '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . '" width="150" style="max-width:150px;height:auto;border:0;display:block;margin:0 auto;" />';
    }

    /* ---------- Main: send consolidated customer invoice ---------- */
    public function send_full_invoice($customer_id) {
        $customer_id = (int) $customer_id;
        $customer    = $this->customer_db->get_customer($customer_id);

        if (!$customer || empty($customer['email'])) {
            error_log("SUM Customer Email: missing customer or email for ID {$customer_id}");
            return new WP_Error('sum_no_customer_email', 'Customer not found or has no email.');
        }

        // 1) Rentals + Totals - ONLY UNPAID
        // 1) Rentals + aggregates
$rentals  = (array) $this->customer_db->get_customer_rentals($customer_id, true);

// Names, sizes, invoice total (WITH billing months), status roll-up, period logic
$names = [];
$sizes = [];
$invoice_total = 0.0; // This is the ACTUAL invoice total (sum of all rental periods)
$status_rollup = 'paid'; // escalates to unpaid/overdue
$froms = [];
$untils = [];

// Load billing calculator
if (!function_exists('calculate_billing_months')) {
    require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
}

foreach ($rentals as $r) {
    $monthly_price = (float)($r['monthly_price'] ?? 0);

    // Calculate ACTUAL billing months for this rental
    $months_due = 1;
    if (!empty($r['period_from']) && !empty($r['period_until']) && function_exists('calculate_billing_months')) {
        try {
            $billing_result = calculate_billing_months(
                $r['period_from'],
                $r['period_until'],
                array('monthly_price' => $monthly_price)
            );
            $months_due = isset($billing_result['occupied_months']) ? (int)$billing_result['occupied_months'] : 1;
            if ($months_due < 1) $months_due = 1;
        } catch (Exception $e) {
            $months_due = 1;
        }
    }

    // Add to invoice total (monthly_price × months_due)
    $invoice_total += $monthly_price * $months_due;

    // names
    $label = $r['name'] ?: ('#' . ($r['id'] ?? ''));
    $names[] = $label;

    // sizes
    if (($r['type'] ?? '') === 'unit') {
        if (!empty($r['sqm'])) {
            $sizes[] = rtrim(rtrim((string)(float)$r['sqm'], '0'), '.') . ' m²';
        } else {
            $sizes[] = $label; // fallback
        }
    } else { // pallet
        $pt = $r['pallet_type'] ?: 'Pallet';
        $ht = ($r['charged_height'] !== null && $r['charged_height'] !== '') ? (rtrim(rtrim((string)(float)$r['charged_height'], '0'), '.') . 'm') : '';
        $sizes[] = $ht ? "{$pt} ({$ht})" : $pt;
    }

    // status roll-up
    $ps = strtolower($r['payment_status'] ?? '');
    if ($ps === 'overdue') {
        $status_rollup = 'overdue';
    } elseif ($ps === 'unpaid' && $status_rollup !== 'overdue') {
        $status_rollup = 'unpaid';
    }

    // periods
    if (!empty($r['period_from'])) $froms[] = $r['period_from'];
    if (!empty($r['period_until'])) $untils[] = $r['period_until'];
}

// VAT - invoice_total is WITHOUT VAT, payment page will add VAT
$vat_enabled = ($this->get_setting('vat_enabled', '0') === '1');
$vat_rate    = (float)$this->get_setting('vat_rate', '0');
$total_with_vat = $vat_enabled ? $invoice_total * (1 + ($vat_rate/100)) : $invoice_total;

// Unit / Size / Status final strings
$unit_names  = implode(', ', array_filter(array_map('trim', $names))) ?: '—';
$size_list   = implode(', ', array_filter(array_map('trim', $sizes))) ?: '—';
$status_text = ucfirst($status_rollup ?: 'paid');

// Period logic
$period_from_text = '—';
$period_until_text = '—';
if (count($rentals) === 1 && $froms && $untils) {
    $period_from_text  = date_i18n('Y-m-d', strtotime($froms[0]));
    $period_until_text = date_i18n('Y-m-d', strtotime($untils[0]));
} elseif (count($rentals) > 1) {
    $all_from_same  = $froms && count(array_unique($froms)) === 1;
    $all_until_same = $untils && count(array_unique($untils)) === 1;
    if ($all_from_same && $all_until_same) {
        $period_from_text  = date_i18n('Y-m-d', strtotime($froms[0]));
        $period_until_text = date_i18n('Y-m-d', strtotime($untils[0]));
    } else {
        $period_from_text = $period_until_text = 'Mixed';
    }
}

        // 2) Payment link (customer-wide) - ensure token exists
        $pay_page_id = (int) get_option('sum_payment_page_id');
        $payment_page_url = $pay_page_id ? get_permalink($pay_page_id) : home_url('/storage-payment/');
        $token = $this->customer_db->ensure_customer_payment_token($customer_id);

        $payment_link_raw = add_query_arg([
            'customer_id' => $customer_id,
            'token'       => $token,
        ], $payment_page_url);
        $payment_link = esc_url($payment_link_raw); // HTML-safe

        // Log for debugging
        error_log("SUM Customer: Payment link generated for customer {$customer_id}: {$payment_link}");

        // 3) Generate consolidated PDF (path)
        $pdf_path = null;
        if (method_exists($this->pdf_generator, 'generate_customer_invoice')) {
            // Build simple items for the PDF (one per rental)
            $items = [];
            foreach ($rentals as $r) {
                $type = $r['type'] === 'pallet' ? 'Pallet' : 'Unit';
                $name = $r['name'] ?: ('#' . ($r['id'] ?? ''));
                $label = "{$type} {$name}";
                if (!empty($r['period_from']) && !empty($r['period_until'])) {
                    $label .= sprintf(' (%s – %s)',
                        date_i18n('M j, Y', strtotime($r['period_from'])),
                        date_i18n('M j, Y', strtotime($r['period_until']))
                    );
                }
                $price = (float)($r['monthly_price'] ?? 0);
                $items[] = ['label' => $label, 'qty' => 1, 'price' => $price, 'amount' => $price];
            }
            $pdf_path = $this->pdf_generator->generate_customer_invoice($customer_id, $items);
        } elseif (method_exists($this->pdf_generator, 'generate_invoice')) {
            $pdf_path = $this->pdf_generator->generate_invoice($customer_id);
        } else {
            return new WP_Error('sum_pdf_method_missing', 'Customer PDF generator method not found.');
        }

        if (is_wp_error($pdf_path) || empty($pdf_path)) {
            return is_wp_error($pdf_path) ? $pdf_path : new WP_Error('sum_pdf_failed', 'Failed to generate PDF.');
        }

        $pdf_path = $this->normalize_attachment_path($pdf_path);
        if (!$pdf_path || !file_exists($pdf_path)) {
            return new WP_Error('sum_pdf_not_found', 'Generated PDF file not found.');
        }

        // 4) Templates + placeholders
        $subject_tmpl = $this->get_setting('invoice_email_subject', 'Your Storage Invoice');
        $body_tmpl    = $this->get_setting('invoice_email_body',
            '<p style="text-align:center;margin:0 0 16px 0;">[sum_logo]</p>
             <p>Dear {customer_name},</p>
             <p>Please find your invoice attached.</p>
             {payment_cta}
             <p><strong>Unit(s):</strong> {unit_name}<br>
                <strong>Size:</strong> {unit_size}<br>
                <strong>Status:</strong> {payment_status}</p>'
        );

        // Bullet-proof CTA HTML
        $cta_html = '
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
  <tr>
    <td align="center" style="padding:16px 0 24px 0;">
      <!--[if mso]>
      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . $payment_link . '"
        style="height:44px;v-text-anchor:middle;width:240px;" arcsize="10%" stroke="f" fillcolor="#f97316">
        <w:anchorlock/>
        <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">
          PAY INVOICE NOW
        </center>
      </v:roundrect>
      <![endif]-->
      <!--[if !mso]><!-- -->
      <a href="' . $payment_link . '" target="_blank"
         style="background:#f97316;border-radius:6px;color:#ffffff;display:inline-block;
                font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:44px;
                mso-line-height-rule:exactly;text-decoration:none;padding:0 20px;">
        PAY INVOICE NOW
      </a>
      <!--<![endif]-->
    </td>
  </tr>
</table>';

        $company_name  = $this->get_setting('company_name', 'Self Storage Cyprus');
        $company_phone = $this->get_setting('company_phone', '');
        $company_email = $this->get_setting('company_email', get_option('admin_email'));

        $placeholders = [
    '{customer_name}'  => $customer['full_name'] ?? '',
    '{payment_amount}' => number_format($invoice_total, 2),  // INVOICE TOTAL (ex-VAT) - payment page adds VAT
    '{monthly_price}'  => number_format($invoice_total, 2),  // INVOICE TOTAL (ex-VAT) - same as payment_amount

    '{unit_name}'      => $unit_names,                       // comma-separated
    '{unit_size}'      => $size_list,                        // comma-separated
    '{payment_status}' => $status_text,

    '{period_from}'    => $period_from_text,                 // exact or Mixed
    '{period_until}'   => $period_until_text,                // exact or Mixed

    '{payment_link}'   => $payment_link,                     // plain URL if your template still uses it
    '{payment_cta}'    => $cta_html,                         // bulletproof button block

    '{company_name}'   => $company_name,
    '{company_phone}'  => $company_phone,
    '{company_email}'  => $company_email,
];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject_tmpl);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body_tmpl);

        // Render logo & fix HTML; avoid auto <p> if template already uses tables
        $body = do_shortcode($body);
        $body = force_balance_tags($body);
        if (stripos($body, '<table') === false) {
            $body = wpautop($body);
        }

        // Create pending payment history record BEFORE sending invoice
        if (!class_exists('SUM_Payment_History')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-payment-history.php';
        }
        $payment_history = new SUM_Payment_History();

        $currency = strtoupper($this->get_setting('currency', 'EUR'));
        $customer_name = $customer['full_name'] ?? 'Unknown Customer';

        // Build items array with expected until dates
        $items_paid = array();
        foreach ($rentals as $rental) {
            $months_due = 1;
            $monthly_price = floatval($rental['monthly_price'] ?? 0);

            // Calculate expected until date after payment
            $expected_until = isset($rental['period_until']) ? $rental['period_until'] : date('Y-m-d');
            if (!empty($rental['period_from']) && !empty($rental['period_until'])) {
                if (!function_exists('calculate_billing_months')) {
                    require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
                }
                if (function_exists('calculate_billing_months')) {
                    try {
                        $billing_result = calculate_billing_months(
                            $rental['period_from'],
                            $rental['period_until'],
                            array('monthly_price' => $monthly_price)
                        );
                        $months_due = isset($billing_result['occupied_months']) ? (int)$billing_result['occupied_months'] : 1;
                        if ($months_due < 1) $months_due = 1;
                    } catch (Exception $e) {
                        $months_due = 1;
                    }
                }
                $expected_until = date('Y-m-d', strtotime($expected_until . ' +' . $months_due . ' months'));
            }

            $items_paid[] = array(
                'type' => $rental['type'],
                'name' => $rental['name'] ?? 'Unknown',
                'period_until' => $expected_until,
                'monthly_price' => $monthly_price
            );
        }

        $payment_history->create_pending_payment(
            $customer_id,
            $customer_name,
            $token,
            $currency,
            1, // Total months - use 1 as default, real calculation happens on payment
            $items_paid
        );
        error_log("SUM Customer: Created pending payment history for customer {$customer_id}, token {$token}");

        // 5) Send
        $to      = $customer['email'];
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent    = wp_mail($to, $subject, $body, $headers, [$pdf_path]);

        // Admin copy
        $admin_email = $this->get_setting('admin_email', get_option('admin_email'));
        if ($admin_email && strcasecmp($admin_email, $to) !== 0) {
            wp_mail($admin_email, '[Admin Copy] ' . $subject, $body, $headers, [$pdf_path]);
        }

        // Cleanup
        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir']) && strpos($pdf_path, $uploads['basedir']) === 0) {
            @unlink($pdf_path);
        }

        return $sent;
    }

    /* ---------- Helpers ---------- */

    private function get_setting($key, $default = '') {
        if ($this->customer_db && method_exists($this->customer_db, 'get_setting')) {
            $v = $this->customer_db->get_setting($key, null);
            if ($v !== null) return $v;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'storage_settings';
        $v = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM {$table} WHERE setting_key=%s", $key));
        return ($v !== null) ? $v : $default;
    }

    private function normalize_attachment_path($path_or_url) {
        if (!$path_or_url) return null;
        if (!filter_var($path_or_url, FILTER_VALIDATE_URL)) return $path_or_url;
        $uploads = wp_upload_dir();
        if (!empty($uploads['baseurl']) && strpos($path_or_url, $uploads['baseurl']) === 0) {
            return $uploads['basedir'] . substr($path_or_url, strlen($uploads['baseurl']));
        }
        error_log('SUM Customer Email: PDF URL not under uploads: ' . $path_or_url);
        return null;
    }
}
