<?php
// includes/class-customer-email-handler.php

if (!defined('ABSPATH')) exit;

class SUM_Customer_Email_Handler {

    private $customer_db;
    private $pdf_generator;

    public function __construct($customer_database) {
        $this->customer_db = $customer_database;
        // The PDF generator is now passed in, or instantiated here. For simplicity:
        if (!class_exists('SUM_Customer_PDF_Generator')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-pdf-generator.php';
        }
        $this->pdf_generator = new SUM_Customer_PDF_Generator($this->customer_db);
    }

    public function send_full_invoice($customer_id) {
        $customer = $this->customer_db->get_customer($customer_id);
        if (!$customer || empty($customer['email'])) {
            return false;
        }

        $pdf_path = $this->pdf_generator->generate_invoice($customer_id);
        if (!$pdf_path) {
            return false;
        }

        $to = $customer['email'];
        $subject = 'Your Consolidated Storage Invoice';
        $body = '
            <p>Dear ' . esc_html($customer['full_name']) . ',</p>
            <p>Please find your consolidated invoice attached for all your rented storage units and pallets.</p>
            <p>Thank you for your business.</p>
        ';
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $attachments = [$pdf_path];

        // Send to customer
        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        // Send copy to admin
        $admin_email = get_option('admin_email');
        if ($admin_email && $admin_email !== $to) {
            wp_mail($admin_email, '[Admin Copy] ' . $subject, $body, $headers, $attachments);
        }

        // Clean up the generated PDF file
        @unlink($pdf_path);

        return $sent;
    }
}