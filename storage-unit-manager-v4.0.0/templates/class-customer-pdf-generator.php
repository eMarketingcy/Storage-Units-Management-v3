<?php
// includes/class-customer-pdf-generator.php

if (!defined('ABSPATH')) exit;

class SUM_Customer_PDF_Generator {

    private $customer_db;

    public function __construct($customer_database) {
        $this->customer_db = $customer_database;
    }

    /**
     * Main function to generate the consolidated PDF invoice for a customer.
     */
    public function generate_invoice($customer_id) {
        $customer = $this->customer_db->get_customer($customer_id);
        $rentals = $this->customer_db->get_customer_rentals($customer_id);

        if (!$customer) {
            return false; // No customer found
        }

        // --- PRE-CALCULATE TOTALS FOR ALL RENTALS ---
        require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
        
        $grand_total_subtotal = 0;
        $processed_rentals = [];

        foreach ($rentals as $rental) {
            $monthly_price = floatval($rental['monthly_price'] ?? 0);
            $billing_result = null;

            if (!empty($rental['period_from']) && !empty($rental['period_until'])) {
                try {
                    $billing_result = calculate_billing_months(
                        $rental['period_from'], 
                        $rental['period_until'], 
                        ['monthly_price' => $monthly_price]
                    );
                    // Add calculated results to the rental item for use in the template
                    $rental['billing_result'] = $billing_result;
                    $rental['calculated_total'] = $billing_result['occupied_months'] * $monthly_price;
                } catch (Exception $e) {
                    // Fallback if calculator fails for an item
                    $rental['billing_result'] = null;
                    $rental['calculated_total'] = $monthly_price;
                }
            } else {
                $rental['billing_result'] = null;
                $rental['calculated_total'] = $monthly_price;
            }
            
            $grand_total_subtotal += $rental['calculated_total'];
            $processed_rentals[] = $rental;
        }

        // Generate the HTML for the invoice
        $html = $this->generate_invoice_html($customer, $processed_rentals, $grand_total_subtotal);
        
        // Create the physical PDF file using the robust, multi-library method
        return $this->create_pdf_file($html, $customer);
    }

    /**
     * Tries to load TCPDF library if not already present.
     */
    private function load_tcpdf() {
        if (!class_exists('TCPDF')) {
            $tcpdf_path = SUM_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            }
        }
    }

    /**
     * Creates the PDF file using Dompdf, with fallbacks to TCPDF and HTML.
     */
    private function create_pdf_file($html, $customer_data) {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/invoices';
        if (!file_exists($pdf_dir)) { wp_mkdir_p($pdf_dir); }

        $pdf_filename = 'customer-invoice-' . $customer_data['id'] . '-' . date('Y-m-d-H-i-s') . '.pdf';
        $pdf_filepath = trailingslashit($pdf_dir) . $pdf_filename;

        if (function_exists('sum_load_dompdf') && sum_load_dompdf()) {
            if ($this->generate_dompdf($html, $pdf_filepath)) return $pdf_filepath;
        }

        $this->load_tcpdf();
        if (class_exists('TCPDF')) {
            if ($this->generate_tcpdf($html, $customer_data, $pdf_filepath)) return $pdf_filepath;
        }

        return $this->generate_html_pdf($html, $customer_data, $pdf_filepath);
    }

    /**
     * Generates PDF using Dompdf library.
     */
    private function generate_dompdf($html, $pdf_filepath) {
        try {
            $opts = new \Dompdf\Options();
            $opts->set('isRemoteEnabled', true);
            $opts->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($opts);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            return (false !== file_put_contents($pdf_filepath, $dompdf->output()));
        } catch (\Throwable $e) {
            error_log('SUM Dompdf error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates PDF using TCPDF library as a fallback.
     */
    private function generate_tcpdf($html, $customer_data, $pdf_filepath) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Storage Unit Manager');
            $pdf->SetAuthor($this->get_setting('company_name', 'Self Storage Cyprus'));
            $pdf->SetTitle('Invoice for ' . $customer_data['full_name']);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output($pdf_filepath, 'F');
            return (file_exists($pdf_filepath) && filesize($pdf_filepath) > 0);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Saves a plain HTML file as the last resort fallback.
     */
    private function generate_html_pdf($html, $customer_data, $pdf_filepath) {
        $html_filepath = str_replace('.pdf', '.html', $pdf_filepath);
        $pdf_content = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice for ' . esc_html($customer_data['full_name']) . '</title></head><body>' . $html . '</body></html>';
        return (false !== file_put_contents($html_filepath, $pdf_content)) ? $html_filepath : false;
    }

    /**
     * Helper function to get a specific setting from the database.
     */
    private function get_setting($key, $default = '') {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM {$wpdb->prefix}storage_settings WHERE setting_key = %s", $key));
        return $value !== null ? $value : $default;
    }

    /**
     * Generates the complete HTML for the invoice body.
     */
    private function generate_invoice_html($customer, $rentals, $grand_total_subtotal) {
        // Fetch all settings
        $company_logo = $this->get_setting('company_logo', '');
        $company_name = $this->get_setting('company_name', 'Self Storage Cyprus');
        $company_address = $this->get_setting('company_address', '');
        $company_phone = $this->get_setting('company_phone', '');
        $company_email = $this->get_setting('company_email', get_option('admin_email'));
        $vat_enabled = ($this->get_setting('vat_enabled', '0') === '1');
        $vat_rate = (float) $this->get_setting('vat_rate', '0');
        $company_vat = (string) $this->get_setting('company_vat', '');
        $currency_symbol = '€';
        
        // Calculate final totals with VAT
        $subtotal = (float) $grand_total_subtotal;
        $vat_amount = $vat_enabled ? round($subtotal * ($vat_rate / 100), 2) : 0.00;
        $grand_total = round($subtotal + $vat_amount, 2);

        // --- Build the line items for the billing table ---
        $billing_rows_html = '';
        if (!empty($rentals)) {
            foreach ($rentals as $rental) {
                $billing_rows_html .= $this->generate_rental_item_row($rental, $currency_symbol);
            }
        } else {
            $billing_rows_html = '<tr><td colspan="4">No active rentals found.</td></tr>';
        }

        $accent_color = '#f97316';
        $light_bg = '#fff7ed';
        $border_color = '#e5e7eb';

        // --- THE FULL HTML TEMPLATE ---
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; color: #333; font-size: 11px; line-height: 1.4; }
            .accent-color { color: ' . $accent_color . '; }
            .header-table { width: 100%; margin-bottom: 20px; } .header-table td { vertical-align: top; }
            .invoice-title { font-size: 28px; font-weight: bold; text-align: right; }
            .invoice-meta { background-color: ' . $light_bg . '; padding: 10px; border-left: 4px solid ' . $accent_color . '; margin-top: 10px; font-size: 10px; }
            .section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid ' . $border_color . '; }
            .customer-name { font-size: 16px; font-weight: bold; }
            .billing-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .billing-table th { background-color: #f1f5f9; padding: 10px; text-align: left; font-size: 11px; }
            .billing-table td { padding: 10px; border-bottom: 1px dashed #eee; font-size: 11px; }
            .totals-block { width: 40%; float: right; }
            .totals-table { width: 100%; text-align: right; }
            .totals-table td { padding: 5px 0; }
            .totals-table .total-row td { border-top: 2px solid ' . $accent_color . '; font-size: 14px; font-weight: bold; padding-top: 8px; }
            .invoice-footer { text-align: center; border-top: 1px solid ' . $border_color . '; padding-top: 15px; color: #6b7280; font-size: 10px; margin-top: 40px; }
        </style></head><body>
            <table class="header-table">
                <tr>
                    <td width="50%">' . ($company_logo ? '<img src="' . esc_attr($company_logo) . '" style="max-height: 50px;"><br><br>' : '') . '<strong>' . esc_html($company_name) . '</strong><br>' . nl2br(esc_html($company_address)) . '</td>
                    <td width="50%" style="text-align: right;">
                        <div class="invoice-title accent-color">CONSOLIDATED INVOICE</div>
                        <div class="invoice-meta"><strong>Invoice #:</strong> CUST-' . $customer['id'] . '-' . date('Ymd') . '<br><strong>Date:</strong> ' . date('M d, Y') . '</div>
                    </td>
                </tr>
            </table>
            <div class="section-title">Bill To</div>
            <div style="margin-bottom:20px;"><div class="customer-name">' . esc_html($customer['full_name']) . '</div>' . nl2br(esc_html($customer['full_address'])) . '<br>' . esc_html($customer['email']) . '</div>
            <div class="section-title">Billing Details</div>
            <table class="billing-table">
                <thead><tr><th style="width: 50%;">Item</th><th style="width: 15%; text-align: center;">Qty (Months)</th><th style="width: 15%; text-align: right;">Rate</th><th style="width: 20%; text-align: right;">Amount</th></tr></thead>
                <tbody>' . $billing_rows_html . '</tbody>
            </table>
            <div class="totals-block">
                <table class="totals-table" style="border-collapse: collapse;">
                    <tr><td>Subtotal (ex VAT):</td><td><strong>' . $currency_symbol . number_format($subtotal, 2) . '</strong></td></tr>
                    ' . ($vat_enabled ? '<tr><td>VAT (' . $vat_rate . '%):</td><td><strong>' . $currency_symbol . number_format($vat_amount, 2) . '</strong></td></tr>' : '') . '
                    <tr class="total-row"><td>TOTAL DUE:</td><td>' . $currency_symbol . number_format($grand_total, 2) . '</td></tr>
                </table>
            </div>
            <div style="clear: both;"></div>
            <div class="invoice-footer">Thank you for your business!</div>
        </body></html>';
    }

    /**
     * Helper to generate a single row in the billing table for a rental item.
     */
    private function generate_rental_item_row($rental, $currency_symbol) {
        return '
            <tr>
                <td>' . ucfirst($rental['type']) . ' Storage - ' . esc_html($rental['name']) . '</td>
                <td style="text-align: center;">' . esc_html($rental['calculated_months']) . '</td>
                <td style="text-align: right;">' . $currency_symbol . number_format($rental['monthly_price'], 2) . '</td>
                <td style="text-align: right;">' . $currency_symbol . number_format($rental['calculated_total'], 2) . '</td>
            </tr>';
    }
}