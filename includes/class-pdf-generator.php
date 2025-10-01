<?php
/**
 * PDF Invoice Generator for Storage Unit Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class SUM_PDF_Generator {
    /** @var string 'dompdf' or 'tcpdf' */
    private $pdf_engine = 'tcpdf';

    /** @var object|null Settings/DB wrapper (same source as pallet PDF generator) */
    private $database;

    public function __construct($database = null) {
        $this->database = $database;

       

        $this->load_pdf_engine();
    }

    /* ----------------------------------------------------------------------
     * ENGINES
     * -------------------------------------------------------------------- */

    private function load_pdf_engine() {
        // Prefer Dompdf if available or autoload path is defined
        $has_dompdf = class_exists('\Dompdf\Dompdf');
        if (!$has_dompdf && defined('SUM_DOMPDF_AUTO') && file_exists(SUM_DOMPDF_AUTO)) {
            require_once SUM_DOMPDF_AUTO;
            $has_dompdf = class_exists('\Dompdf\Dompdf');
        }
        if ($has_dompdf) {
            $this->pdf_engine = 'dompdf';
            return;
        }
        // Fallback to TCPDF
        $this->pdf_engine = 'tcpdf';
        $this->load_tcpdf();
    }

    private function load_tcpdf() {
        if (!class_exists('TCPDF')) {
            $tcpdf_paths = array(
                ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php',
                ABSPATH . 'wp-includes/tcpdf/tcpdf.php',
                (defined('SUM_PLUGIN_PATH') ? SUM_PLUGIN_PATH : plugin_dir_path(__FILE__)) . 'lib/tcpdf/tcpdf.php'
            );
            foreach ($tcpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }
    }

    /* ----------------------------------------------------------------------
     * HELPERS (Company, Currency, Customer source same as pallet)
     * -------------------------------------------------------------------- */

    private function get_company_details() {
        return [
            'name'        => $this->database ? $this->database->get_setting('company_name',  'Self Storage Cyprus')            : 'Self Storage Cyprus',
            'address'     => $this->database ? $this->database->get_setting('company_address','')                              : '',
            'phone'       => $this->database ? $this->database->get_setting('company_phone',  '')                              : '',
            'email'       => $this->database ? $this->database->get_setting('company_email',  get_option('admin_email'))       : get_option('admin_email'),
            'logo'        => $this->database ? $this->database->get_setting('company_logo',   '')                              : '',
            'vat'         => $this->database ? $this->database->get_setting('company_vat',    '')                              : '',
            'vat_enabled' => ($this->database ? $this->database->get_setting('vat_enabled','0') : '0') === '1',
            'vat_rate'    => (float)($this->database ? $this->database->get_setting('vat_rate','0') : '0'),
            'currency'    => $this->database ? $this->database->get_setting('currency',   'EUR')                                : 'EUR',
        ];
    }

    private function currency_symbol($code = null) {
        if (!$code) {
            $code = $this->database ? $this->database->get_setting('currency', 'EUR') : 'EUR';
        }
        if (function_exists('get_woocommerce_currency_symbol')) {
            return get_woocommerce_currency_symbol($code);
        }
        switch ($code) {
            case 'USD': return '$';
            case 'GBP': return '£';
            case 'EUR':
            default:    return '€';
        }
    }

    /**
     * ✅ The ONLY source of customer info for PDFs:
     * same as pallet generator (CSSC module) or fallback to storage_customers table.
     */
    private function fetch_customer_for_invoice($customer_id) {
        global $wpdb;

        // Prefer your CSSC module the pallets use
        if ($this->database && method_exists($this->database, 'get_customer_by_id')) {
            $row = $this->database->get_customer_by_id((int)$customer_id);
        } else {
            $table = $wpdb->prefix . 'storage_customers';
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $customer_id),
                ARRAY_A
            );
        }

        if (!$row) {
            return new WP_Error('sum_customer_not_found', 'Customer not found for ID: ' . (int)$customer_id);
        }

        // Normalize keys used by templates
        $defaults = [
            'id'              => 0,
            'full_name'       => '',
            'email'           => '',
            'phone'           => '',
            'whatsapp'        => '',
            'company_name'    => '',
            'vat'             => '',
            'tax_id'          => '',
            'address'         => '',
            'city'            => '',
            'postal_code'     => '',
            'country'         => '',
            'secondary_name'  => '',
            'secondary_phone' => '',
            'secondary_email' => '',
        ];
        $row = array_merge($defaults, $row);

        // Build “full” conveniences for the legacy HTML
        $row['name']        = $row['full_name'];
        $row['full_phone']  = $row['phone'] ?: $row['whatsapp'];
        $row['full_email']  = $row['email'];

        return $row;
    }

    /* ----------------------------------------------------------------------
     * PUBLIC: CUSTOMER INVOICE (AJAX entry kept the same signature)
     * -------------------------------------------------------------------- */

    /**
     * Legacy-compatible entry point used by your AJAX handler.
     * Accepts either customer ID or an array (with ['id']).
     * Only change vs. old file: we ALWAYS re-fetch customer from pallet’s source.
     *
     * @param int|array $customer_data_or_id
     * @param array     $unpaid_items Each row: ['label','qty','price','amount'] (flexible)
     * @return string|false URL to the generated PDF (kept old behavior)
     */
    public function generate_invoice($customer_data_or_id, $unpaid_items) {
        // Resolve customer ID
        $customer_id = 0;
        if (is_numeric($customer_data_or_id)) {
            $customer_id = (int)$customer_data_or_id;
        } elseif (is_array($customer_data_or_id) && !empty($customer_data_or_id['id'])) {
            $customer_id = (int)$customer_data_or_id['id'];
        }
        if ($customer_id <= 0 || empty($unpaid_items)) {
            return false;
        }

        // ✅ Fetch from the SAME source as pallet generator
        $customer = $this->fetch_customer_for_invoice($customer_id);
        if (is_wp_error($customer)) {
            error_log('SUM PDF: ' . $customer->get_error_message());
            return false;
        }

        // Calculate totals
        $grand_total = 0.0;
        foreach ($unpaid_items as $item) {
            $grand_total += isset($item['amount']) ? (float)$item['amount'] : 0.0;
        }

        // Build HTML (multi-item customer invoice)
        $html = $this->generate_multi_invoice_html($customer, $unpaid_items, $grand_total);

        // Build a pseudo unit_data only for filename/meta (unchanged behavior)
        $unit_data = [
            'id'                     => $customer['id'],
            'unit_name'              => ($customer['name'] ?: 'customer-' . $customer['id']) . '-INV',
            'full_name'   => $customer['name'] ?: '',
            'email'  => $customer['full_email'] ?: '',
            'phone'  => $customer['full_phone'] ?: '',
        ];

        // Create PDF
        $pdf_path = $this->create_pdf_file($html, $unit_data);
        error_log('SUM PDF: Final invoice generation attempt result: ' . ($pdf_path ? 'SUCCESS' : 'FAILURE'));

        // Old method returned a URL
        return $pdf_path ? trailingslashit(wp_upload_dir()['baseurl']) . 'invoices/' . basename($pdf_path) : false;
    }

    /* ----------------------------------------------------------------------
     * PUBLIC: UNIT INVOICE (kept same; only bugfixes: no self-instantiation)
     * -------------------------------------------------------------------- */

    /**
     * Unit invoice path (unchanged in output & design).
     * $unit_data must include at least: id, unit_name, monthly_price, period_from, period_until,
     * and contact fields primary_contact_* as you used before.
     */
public function generate_invoice_pdf($unit_data) {
    // 1. Get the monthly price from the unit data
    $monthly_price = (float)($unit_data['monthly_price'] ?? 0);

    // 2. Set default values for the billing period
    $billing_result = null;
    $months_due     = 1;
    $total_amount   = $monthly_price;

    // 3. If rental dates are set, use the billing calculator to get the correct values
    if (!empty($unit_data['period_from']) && !empty($unit_data['period_until'])) {
        // Ensure the calculator file is loaded
        if (!function_exists('calculate_billing_months') && defined('SUM_PLUGIN_PATH')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
        }

        if (function_exists('calculate_billing_months')) {
            try {
                $billing_result = calculate_billing_months(
                    $unit_data['period_from'],
                    $unit_data['period_until'],
                    ['monthly_price' => $monthly_price]
                );
                // Get the calculated number of months
                $months_due   = (int)$billing_result['occupied_months'];
                // Calculate the final total amount
                $total_amount = $monthly_price * $months_due;
            } catch (\Throwable $e) {
                error_log('SUM PDF Billing Calculator Error: ' . $e->getMessage());
                // If calculator fails, fall back to defaults
            }
        }
    }

    // 4. Load the PDF engine (Dompdf or TCPDF)
    $this->load_pdf_engine();

    // 5. Generate the HTML template, passing in all the calculated values
    $html = $this->generate_invoice_html($unit_data, $total_amount, $monthly_price, $months_due, $billing_result);

    // 6. Create and return the path to the final PDF file
    return $this->create_pdf_file($html, $unit_data);
}
    /* ----------------------------------------------------------------------
     * FILE CREATION (unchanged behavior)
     * -------------------------------------------------------------------- */

    private function create_pdf_file($html, $unit_data) {
        $upload_dir = wp_upload_dir();
        $pdf_dir    = trailingslashit($upload_dir['basedir']) . 'invoices';

        if (!file_exists($pdf_dir)) { wp_mkdir_p($pdf_dir); }
        if (!is_writable($pdf_dir)) { @chmod($pdf_dir, 0755); }

        $slug        = preg_replace('/[^A-Za-z0-9\-\_\.]+/', '-', $unit_data['unit_name']);
        $pdf_filename = 'invoice-' . $slug . '-' . date('Y-m-d-H-i-s') . '.pdf';
        $pdf_filepath = trailingslashit($pdf_dir) . $pdf_filename;

        // 1) Dompdf
        if (function_exists('sum_load_dompdf') && sum_load_dompdf()) {
            $ok = $this->generate_dompdf($html, $pdf_filepath);
            if ($ok) return $pdf_filepath;
        }

        // 2) TCPDF
        if (class_exists('TCPDF')) {
            $ok = $this->generate_tcpdf($html, $unit_data, $pdf_filepath);
            if ($ok) return $ok;
        }

        // 3) HTML fallback
        return $this->generate_html_pdf($html, $unit_data, $pdf_filepath);
    }

    private function generate_dompdf($html, $pdf_filepath) {
        try {
            $opts = new \Dompdf\Options();
            $opts->set('isRemoteEnabled', true);
            $opts->set('defaultMediaType', 'print');
            $opts->set('isHtml5ParserEnabled', true);
            $opts->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($opts);
            $dompdf->setPaper('A4', 'portrait');

            $html_doc = '<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
            $dompdf->loadHtml($html_doc);
            $dompdf->render();

            $output = $dompdf->output();
            return (false !== file_put_contents($pdf_filepath, $output));
        } catch (\Throwable $e) {
            error_log('SUM Dompdf error: ' . $e->getMessage());
            return false;
        }
    }

    private function generate_tcpdf($html, $unit_data, $pdf_filepath) {
        try {
            error_log("SUM PDF: Starting TCPDF generation");

            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            $company = $this->get_company_details();

            $pdf->SetCreator('Storage Unit Manager');
            $pdf->SetAuthor($company['name']);
            $pdf->SetTitle('Invoice - ' . (isset($unit_data['unit_name']) ? $unit_data['unit_name'] : ''));
            $pdf->SetSubject('Invoice');

            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);

            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);

            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Output($pdf_filepath, 'F');

            if (file_exists($pdf_filepath)) {
                $file_size = filesize($pdf_filepath);
                error_log("SUM PDF: File created successfully, size: {$file_size} bytes");
                return $file_size > 0 ? $pdf_filepath : false;
            }

            error_log("SUM PDF: File was not created");
            return false;

        } catch (\Throwable $e) {
            error_log('SUM PDF: TCPDF generation error: ' . $e->getMessage());
            return $this->generate_html_pdf($html, $unit_data, $pdf_filepath);
        }
    }

    private function generate_html_pdf($html, $unit_data, $pdf_filepath) {
        error_log("SUM PDF: Using HTML fallback method");

        $pdf_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - ' . esc_html(isset($unit_data['unit_name']) ? $unit_data['unit_name'] : '') . '</title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>' . $html . '</body>
</html>';

        $write_result = file_put_contents($pdf_filepath, $pdf_content);

        if ($write_result !== false) {
            error_log("SUM PDF: HTML fallback file created, size: {$write_result} bytes");
            return (file_exists($pdf_filepath) && filesize($pdf_filepath) > 0) ? $pdf_filepath : false;
        }

        error_log("SUM PDF: Failed to write HTML fallback file");
        return false;
    }

    /* ----------------------------------------------------------------------
     * HTML RENDERERS (kept same visual style)
     * -------------------------------------------------------------------- */

    /**
     * Unit invoice HTML (kept same structure/design you used)
     */
    private function generate_invoice_html($unit_data, $total_amount, $monthly_price, $months_due, $billing_result = null) {
        $c = $this->get_company_details();
        $symbol = $this->currency_symbol($c['currency']);

        // VAT calc
        $subtotal   = (float)$total_amount;
        $vat_amount = $c['vat_enabled'] ? round($subtotal * ($c['vat_rate'] / 100), 2) : 0.00;
        $grand_total = round($subtotal + $vat_amount, 2);

        $accent_color = '#f97316';
        $light_bg     = '#fff7ed';
        $border_color = '#e5e7eb';

        $breakdown_rows = $this->generate_simple_billing_row($unit_data, $months_due, $monthly_price, $symbol);

        $sqm = isset($unit_data['sqm']) && $unit_data['sqm'] !== '' ? esc_html($unit_data['sqm']) . ' m²' : 'N/A';
        $size = isset($unit_data['size']) ? esc_html($unit_data['size']) : 'Standard';

        $html = '
<style>
body { font-family: Arial, sans-serif; color: #333; font-size: 11px; line-height: 1.4; }
.accent-color { color: ' . $accent_color . '; }
.bg-accent { background-color: ' . $accent_color . '; color: white; }
.bg-light { background-color: ' . $light_bg . '; }
.header-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
.header-table td { vertical-align: top; padding: 0; }
.invoice-title { font-size: 28px; font-weight: bold; text-align: right; margin-bottom: 15px; }
.invoice-meta { background-color: ' . $light_bg . '; padding: 10px; border-left: 4px solid ' . $accent_color . '; margin-top: 10px; font-size: 10px; }
.section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid ' . $border_color . '; }
.customer-info { margin-bottom: 20px; padding: 10px 0; font-size: 12px; }
.customer-name { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
.pallet-details-bar { background-color: ' . $accent_color . '; color: white; padding: 15px; margin-bottom: 20px; }
.pallet-name { font-size: 18px; font-weight: bold; margin: 0; }
.pallet-specs { font-size: 11px; margin-top: 5px; }
.billing-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.billing-table th { background-color: #f1f5f9; color: #333; padding: 10px; text-align: left; font-size: 11px; border-bottom: 1px solid ' . $border_color . '; }
.billing-table td { padding: 10px; border-bottom: 1px dashed #eee; font-size: 11px; }
.amount-cell { text-align: right; font-weight: bold; }
.totals-block { width: 40%; float: right; margin-top: 10px; }
.totals-table { width: 100%; border-collapse: collapse; text-align: right; }
.totals-table td { padding: 5px 0; font-size: 11px; }
.totals-table .total-row td { border-top: 2px solid ' . $accent_color . '; font-size: 14px; font-weight: bold; padding-top: 8px; }
.invoice-footer { text-align: center; border-top: 1px solid ' . $border_color . '; padding-top: 15px; color: #6b7280; font-size: 10px; margin-top: 60px; }
.logo { max-height: 50px; margin-bottom: 10px; }
</style>

<table class="header-table">
<tr>
    <td width="50%">' .
        ($c['logo'] ? '<img src="'.esc_url($c['logo']).'" class="logo"><br>' : '') . '
        <div class="company-name" style="font-weight:600;">' . esc_html($c['name']) . '</div>
        <div style="font-size: 10px; color: #6b7280;">' . nl2br(esc_html($c['address'])) . '<br>' .
            ($c['phone'] ? 'Phone: ' . esc_html($c['phone']) . '<br>' : '') . 'Email: ' . esc_html($c['email']) . '
        </div>
    </td>
    <td width="50%" style="text-align: right;">
        <div class="invoice-title">UNIT INVOICE</div>
        <div class="invoice-meta">
            <strong>Invoice #:</strong> INV-' . (int)$unit_data['id'] . '-' . date('Ymd') . '<br>
            <strong>Date:</strong> ' . date('M d, Y') . '<br>
            <strong>Due Date:</strong> ' . date('M d, Y', strtotime('+30 days')) . '
        </div>
    </td>
</tr>
</table>

<div class="section-title">Bill To</div>
<div class="customer-info">
    <div class="customer-name">' . esc_html($unit_data['full_name'] ?? 'N/A') . '</div>
    <div> ' . esc_html($unit_data['full_address'] ?? 'N/A') . '</div>
    <div>Phone: ' . esc_html($unit_data['phone'] ?? 'N/A') . '</div>
    <div>Email: ' . esc_html($unit_data['email'] ?? 'N/A') . '</div>
</div>

<div class="pallet-details-bar">
    <div class="pallet-name">Unit ' . esc_html($unit_data['unit_name']) . '</div>
    <div class="pallet-specs">Size: ' . $size . ' | Area: ' . $sqm . ' | Monthly Rate: ' . $symbol . number_format($monthly_price, 2) . '</div>
</div>

<div class="section-title">Billing Details</div>
<table class="billing-table">
                   <thead><tr><th style="width: 50%;">Item</th><th style="width: 15%; text-align: center;">Qty (Months)</th><th style="width: 15%; text-align: right;">Rate</th><th style="width: 20%; text-align: right;">Amount</th></tr></thead>
    <tbody>' . $breakdown_rows . '</tbody>
</table>

<div class="totals-block">
<table class="totals-table">
    <tr>
        <td>Subtotal (ex VAT):</td>
        <td><strong>' . $symbol . number_format($subtotal,2) . '</strong></td>
    </tr>' .
    ($c['vat_enabled'] ? '
    <tr>
        <td>VAT (' . number_format($c['vat_rate'],2) . '%):</td>
        <td><strong>' . $symbol . number_format($vat_amount,2) . '</strong></td>
    </tr>' : '') . '
    <tr class="total-row">
        <td>Total (incl. VAT)</td>
        <td>' . $symbol . number_format($grand_total,2) . '</td>
    </tr>
</table>
</div>

<div style="clear: both;"></div>

<div class="invoice-footer">
    <p>VAT / Tax ID: ' . esc_html($c['vat']) . '</p>
    <p>Thank you for choosing <span class="accent-color">' . esc_html($c['name']) . '</span>. Payment is due within 30 days of invoice date.</p>
</div>';

        return $html;
    }

    /**
     * Customer invoice HTML (multi-item) – same look & feel
     */
    private function generate_multi_invoice_html($customer, $unpaid_items, $grand_total) {
        $c = $this->get_company_details();
        $symbol = $this->currency_symbol($c['currency']);

        // VAT calc
        $subtotal_ex_vat     = (float)$grand_total;
        $vat_amount          = $c['vat_enabled'] ? round($subtotal_ex_vat * ($c['vat_rate'] / 100), 2) : 0.00;
        $grand_total_inc_vat = round($subtotal_ex_vat + $vat_amount, 2);

        // Build breakdown rows
        $rows = '';
        foreach ($unpaid_items as $item) {
            $label  = isset($item['label'])  ? $item['label']  : ($item['name'] ?? 'Item');
            $qty    = isset($item['qty'])    ? (float)$item['qty'] : 1.0;
            $price  = isset($item['price'])  ? (float)$item['price'] : (isset($item['amount']) ? (float)$item['amount'] : 0.0);
            $amount = isset($item['amount']) ? (float)$item['amount'] : ($qty * $price);

            $rows .= '
            <tr>
                <td><div class="month-label">' . esc_html($label) . '</div></td>
                <td><div class="days-info">' . esc_html(rtrim(rtrim(number_format($qty,2),'0'),'.')) . '</div></td>
                <td>' . $symbol . number_format($price, 2) . '</td>
                <td class="amount-cell">' . $symbol . number_format($amount, 2) . '</td>
            </tr>';
        }

        $html = '
<style>
.header-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
.header-table td { vertical-align: top; padding: 0; }
.invoice-title { font-size: 28px; font-weight: bold; text-align: right; margin-bottom: 15px; }
.invoice-meta { background-color: #fff7ed; padding: 10px; border-left: 4px solid #f97316; margin-top: 10px; font-size: 10px; }
.section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #e5e7eb; }
.customer-info { margin-bottom: 20px; padding: 10px 0; font-size: 12px; }
.customer-name { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
.billing-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.billing-table th { background-color: #f1f5f9; color: #333; padding: 10px; text-align: left; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
.billing-table td { padding: 10px; border-bottom: 1px dashed #eee; font-size: 11px; }
.amount-cell { text-align: right; font-weight: bold; }
.total-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
.total-table td { padding:8px; border:1px solid #e5e7eb; }
.footer-highlight { color:#f97316; }
.logo { max-height: 50px; margin-bottom: 10px; }
</style>

<div class="header-section">
    <table class="header-table">
        <tr>
            <td width="50%">' .
                ($c['logo'] ? '<img src="'.esc_url($c['logo']).'" class="logo"><br>' : '') . '
                <div class="company-name" style="font-weight:600;">' . esc_html($c['name']) . '</div>
                <div class="company-details" style="font-size:10px;color:#6b7280;">' .
                    nl2br(esc_html($c['address'])) . '<br>' .
                    ($c['phone'] ? 'Phone: ' . esc_html($c['phone']) . '<br>' : '') . '
                    Email: ' . esc_html($c['email']) . '
                </div>
            </td>
            <td width="50%" style="text-align:right;">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <strong>Invoice #:</strong> INV-' . (int)$customer['id'] . '-' . date('Ymd') . '<br>
                    <strong>Date:</strong> ' . date('M d, Y') . '<br>
                    <strong>Due Date:</strong> ' . date('M d, Y', strtotime('+30 days')) . '
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section-title">Bill To</div>
<div class="customer-info">
    <div class="customer-name">' . esc_html($customer['full_name'] ?: 'N/A') . '</div>' .
    ($customer['phone'] ? '<div>Phone: ' . esc_html($customer['phone']) . '</div>' : '') .
    ($customer['email'] ? '<div>Email: ' . esc_html($customer['email']) . '</div>' : '') .
    ($customer['address'] ? '<div>' . esc_html($customer['address']) . '</div>' : '') .
    (trim(($customer['city'] ?? '') . ' ' . ($customer['postal_code'] ?? '')) ? '<div>' . esc_html(trim(($customer['city'] ?? '') . ' ' . ($customer['postal_code'] ?? ''))) . '</div>' : '') .
    ($customer['country'] ? '<div>' . esc_html($customer['country']) . '</div>' : '') .
    ($customer['vat'] ? '<div>VAT: ' . esc_html($customer['vat']) . '</div>' : '') .
    ($customer['tax_id'] ? '<div>Tax ID: ' . esc_html($customer['tax_id']) . '</div>' : '') .
    (($customer['secondary_name'] || $customer['secondary_phone'] || $customer['secondary_email'])
        ? '<div style="margin-top:6px;"><strong>Secondary Contact</strong><br>' .
           esc_html($customer['secondary_name']) . '<br>' .
           ($customer['secondary_phone'] ? 'Phone: ' . esc_html($customer['secondary_phone']) . '<br>' : '') .
           ($customer['secondary_email'] ? 'Email: ' . esc_html($customer['secondary_email']) : '') .
           '</div>' : ''
    ) . '
</div>

<div class="section-title">Billing Details (Unpaid Items)</div>
<table class="billing-table">
    <thead>
        <tr>
            <th>Item / Description</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>' . $rows . '</tbody>
</table>

<div class="section-title">Totals</div>
<table class="total-table">
    <tr>
        <td>Subtotal (ex VAT)</td>
        <td style="text-align:right;"><strong>' . $symbol . number_format($subtotal_ex_vat,2) . '</strong></td>
    </tr>' .
    ($c['vat_enabled'] ? '
    <tr>
        <td>VAT (' . number_format($c['vat_rate'],2) . '%)</td>
        <td style="text-align:right;"><strong>' . $symbol . number_format($vat_amount,2) . '</strong></td>
    </tr>' : '') . '
    <tr>
        <td style="background:#f9fafb;"><strong>Total (incl. VAT)</strong></td>
        <td style="text-align:right;background:#f9fafb;"><strong>' . $symbol . number_format($grand_total_inc_vat,2) . '</strong></td>
    </tr>
</table>

<div class="invoice-footer" style="text-align:center;color:#6b7280;font-size:10px;margin-top:40px;">
    <p>Thank you for choosing <span class="footer-highlight">' . esc_html($c['name']) . '</span></p>
    <p>For questions about this invoice, please contact us at ' . esc_html($c['email']) . '</p>
</div>';

        return $html;
    }

    private function generate_modern_billing_breakdown($billing_result, $monthly_price, $symbol) {
        if (empty($billing_result['months']) || !is_array($billing_result['months'])) {
            return $this->generate_simple_billing_row([], 1, $monthly_price, $symbol);
        }
        $out = '';
        foreach ($billing_result['months'] as $month) {
            if (!empty($month['occupied_days'])) {
                $amount = $monthly_price; // any occupied month is billed full
                $out .= '
                <tr>
                    <td><div class="month-label">' . esc_html($month['label']) . '</div></td>
                    <td><div class="days-info">' . (int)$month['occupied_days'] . ' / ' . (int)$month['days_in_month'] . '</div></td>
                    <td>' . $symbol . number_format($monthly_price,2) . '</td>
                    <td class="amount-cell">' . $symbol . number_format($amount,2) . '</td>
                </tr>';
            }
        }
        return $out ?: $this->generate_simple_billing_row([], 1, $monthly_price, $symbol);
    }

    private function generate_simple_billing_row($unit_data, $months_due, $monthly_price, $symbol) {
        $total = $monthly_price * max(1, (int)$months_due);
        $label = 'Storage Unit Rental' . (!empty($unit_data['unit_name']) ? ' - ' . esc_html($unit_data['unit_name']) : '');
        return '
        <tr>
            <td><div class="month-label">' . $label . '</div></td>
            <td><div class="days-info">' . (int)$months_due . '</div></td>
            <td>' . $symbol . number_format($monthly_price,2) . '</td>
            <td class="amount-cell">' . $symbol . number_format($total,2) . '</td>
        </tr>';
    }
}
