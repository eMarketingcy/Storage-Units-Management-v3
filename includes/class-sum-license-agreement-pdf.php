<?php
if (!defined('ABSPATH')) exit;

/**
 * Generate a Licence Agreement PDF from a sum_application post.
 * Uses Dompdf via sum_load_dompdf() already present in your main plugin.
 */
class SUM_License_Agreement_PDF {

    /**
     * Generate PDF file for a given application (CPT: sum_application).
     * @param int $post_id
     * @return array|WP_Error ['path'=>..., 'url'=>...]
     */
    public static function generate($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sum_application') {
            return new WP_Error('invalid', 'Invalid application.');
        }

        // --- Gather meta (matches your intake file) ---
        $m = function($k) use ($post_id) { return get_post_meta($post_id, $k, true); };
        $meta = [
            'personal_first' => $m('personal_first'),
            'personal_surname' => $m('personal_surname'),
            'home_address' => $m('home_address'),
            'district' => $m('district'),
            'post_code' => $m('post_code'),
            'home_tel' => $m('home_tel'),
            'mobile' => $m('mobile'),
            'email' => $m('email'),

            'has_company' => $m('has_company'),
            'company_name' => $m('company_name'),
            'company_reg' => $m('company_reg'),
            'company_address' => $m('company_address'),
            'company_district' => $m('company_district'),
            'company_post_code' => $m('company_post_code'),

            'unit_no' => $m('unit_no'),
            'unit_type' => $m('unit_type'),
            'unit_size' => $m('unit_size'),
            'storage_term' => $m('storage_term'),
            'period_from' => $m('period_from'),
            'period_to' => $m('period_to'),

            'monthly_ex_vat' => $m('monthly_ex_vat'),
            'monthly_inc_vat' => $m('monthly_inc_vat'),

            'reason' => $m('reason'),
            'alt_first' => $m('alt_first'),
            'alt_surname' => $m('alt_surname'),
            'alt_mobile' => $m('alt_mobile'),
            'alt_email' => $m('alt_email'),

            'accept_terms' => $m('accept_terms'),
            'sign_print_name' => $m('sign_print_name'),
            'sign_date' => $m('sign_date'),
        ];

        // --- Company header (you can tweak to pull from your settings if you prefer) ---
        $company = [
            'name' => 'Self Storage Cyprus',
            'address' => 'P.O. Box 40557, 6305 Larnaca, Cyprus',
            't' => '70000321',
            'm' => '99962333',
            'e' => 'info@selfstorage.cy',
            'w' => 'selfstorage.cy',
            'owner' => 'GEOGEO LIMITED t/a Self Storage Cyprus',
            'vat' => 'VAT Reg No: 10288789P',
            'tic' => 'TIC Reg No: HE288789',
        ];

        // --- Build HTML (Dompdf-friendly) ---
        $today = date_i18n('Y-m-d');
        $agree_yes = !empty($meta['accept_terms']) && $meta['accept_terms'] !== '0' ? 'Yes' : 'No';

        $esc = function($v){ return esc_html($v ?? ''); };

        ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Self Storage Licence Agreement</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; }
    .wrap { padding: 28px; }
    .head { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px; }
    .head h1 { margin: 0 0 4px; font-size: 20px; }
    .muted { color: #666; }
    .two { display: table; width: 100%; table-layout: fixed; }
    .two > div { display: table-cell; vertical-align: top; }
    .left { padding-right: 12px; }
    .right { padding-left: 12px; text-align: right; }
    .section { margin: 16px 0 10px; }
    .section h2 { font-size: 14px; margin: 0 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid th, .grid td { border: 1px solid #e5e5e5; padding: 6px 8px; }
    .grid th { background: #f7f7f7; text-align: left; }
    .meta { margin: 4px 0; }
    .box { border: 1px solid #eee; background: #fafafa; padding: 8px; }
    .sign-row { margin-top: 20px; display: table; width: 100%; table-layout: fixed; }
    .sign-col { display: table-cell; vertical-align: top; padding-right: 16px; }
    .small { font-size: 11px; }
    .bank { font-size: 12px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div class="two">
            <div class="left">
                <h1>SELF STORAGE LICENCE AGREEMENT</h1>
                <div class="muted"><?= $esc($company['owner']); ?></div>
                <div class="muted"><?= $esc($company['vat']); ?> / <?= $esc($company['tic']); ?></div>
            </div>
            <div class="right small">
                <div><strong><?= $esc($company['name']); ?></strong></div>
                <div><?= $esc($company['address']); ?></div>
                <div>t: <?= $esc($company['t']); ?> &nbsp; m: <?= $esc($company['m']); ?></div>
                <div>e: <?= $esc($company['e']); ?> &nbsp; w: <?= $esc($company['w']); ?></div>
                <div><strong>Date:</strong> <?= esc_html($today); ?></div>
                <div><strong>Submission ID:</strong> <?= (int)$post_id; ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Personal Storage</h2>
        <table class="grid">
            <tr><th style="width:25%">Name</th><td><?= $esc($meta['personal_first']); ?></td>
                <th style="width:25%">Surname</th><td><?= $esc($meta['personal_surname']); ?></td></tr>
            <tr><th>Home Address</th><td colspan="3"><?= $esc($meta['home_address']); ?></td></tr>
            <tr><th>District</th><td><?= $esc($meta['district']); ?></td>
                <th>Post Code</th><td><?= $esc($meta['post_code']); ?></td></tr>
            <tr><th>Home Tel</th><td><?= $esc($meta['home_tel']); ?></td>
                <th>Mobile</th><td><?= $esc($meta['mobile']); ?></td></tr>
            <tr><th>Email</th><td colspan="3"><?= $esc($meta['email']); ?></td></tr>
            <?php if (!empty($meta['alt_first']) || !empty($meta['alt_email'])): ?>
            <tr><th>Alt. Contact Name</th><td><?= $esc(trim(($meta['alt_first']??'').' '.($meta['alt_surname']??''))); ?></td>
                <th>Alt. Mobile</th><td><?= $esc($meta['alt_mobile']); ?></td></tr>
            <tr><th>Alt. Email</th><td colspan="3"><?= $esc($meta['alt_email']); ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($meta['reason'])): ?>
            <tr><th>Reason For Storage</th><td colspan="3"><?= $esc($meta['reason']); ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if (!empty($meta['has_company']) && $meta['has_company'] !== '0'): ?>
    <div class="section">
        <h2>Business Storage</h2>
        <table class="grid">
            <tr><th style="width:25%">Company Name</th><td><?= $esc($meta['company_name']); ?></td>
                <th style="width:25%">Co Reg No</th><td><?= $esc($meta['company_reg']); ?></td></tr>
            <tr><th>Company Address</th><td colspan="3"><?= $esc($meta['company_address']); ?></td></tr>
            <tr><th>District</th><td><?= $esc($meta['company_district']); ?></td>
                <th>Post Code</th><td><?= $esc($meta['company_post_code']); ?></td></tr>
        </table>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>Self Storage Unit</h2>
        <table class="grid">
            <tr><th style="width:25%">Unit No</th><td><?= $esc($meta['unit_no']); ?></td>
                <th style="width:25%">Unit Type</th><td><?= $esc($meta['unit_type']); ?></td></tr>
            <tr><th>Unit Size</th><td><?= $esc($meta['unit_size']); ?></td>
                <th>Storage Term</th><td><?= $esc($meta['storage_term']); ?></td></tr>
            <tr><th>Period From</th><td><?= $esc($meta['period_from']); ?></td>
                <th>To</th><td><?= $esc($meta['period_to']); ?></td></tr>
        </table>
        <div class="small muted" style="margin-top:6px;">
            Please note: unit sizes are approximate; by signing, you agree to the actual unit provided.
        </div>
    </div>

    <div class="section">
        <h2>Price & Payment</h2>
        <table class="grid">
            <tr>
                <th style="width:50%">Monthly (excl. VAT)</th><td><?= $esc($meta['monthly_ex_vat']); ?></td>
            </tr>
            <tr>
                <th>Monthly (incl. VAT)</th><td><?= $esc($meta['monthly_inc_vat']); ?></td>
            </tr>
        </table>
        <div class="box bank" style="margin-top:6px;">
            <strong>Payment Methods:</strong> Credit Card (in person), PayPal online, Revolut/Quick Pay (00357 97640422), Cash or Cheque, Bank Transfer, Monthly Direct Debit.
            <br><strong>Bank Details:</strong> Acc Name: GEOGEO LIMITED — IBAN: CY18002001950000357016074069 — BIC: BCYPCY2N
        </div>
    </div>

    <div class="section">
        <h2>Acceptance</h2>
        <table class="grid">
            <tr><th style="width:40%">Accepted Terms</th><td><?= $esc($agree_yes); ?></td></tr>
            <tr><th>Print Name</th><td><?= $esc($meta['sign_print_name']); ?></td></tr>
            <tr><th>Signature Date</th><td><?= $esc($meta['sign_date']); ?></td></tr>
        </table>
        <div class="small muted" style="margin-top:6px;">
            By signing this contract, the Customer confirms agreement to the Terms & Conditions provided by GEOGEO Limited t/a Self Storage Cyprus.
        </div>
    </div>

    <div class="sign-row">
        <div class="sign-col">
            <div class="small"><strong>The Customer / The Storer</strong></div>
            <div class="box" style="height:46px;"></div>
            <div class="small">Print Name: <?= $esc($meta['sign_print_name']); ?></div>
            <div class="small">Date: <?= $esc($meta['sign_date']); ?></div>
        </div>
        <div class="sign-col">
            <div class="small"><strong>Self Storage Cyprus / The Owner</strong></div>
            <div class="box" style="height:46px;"></div>
            <div class="small">Print Name: <?= $esc($company['name']); ?></div>
            <div class="small">Date: <?= esc_html($today); ?></div>
        </div>
    </div>
</div>
</body>
</html>
<?php
        $html = ob_get_clean();

        // --- Prepare filesystem path ---
        $uploads = wp_upload_dir();
        $base_dir = trailingslashit($uploads['basedir']) . 'sum/agreements/' . $post_id;
        $base_url = trailingslashit($uploads['baseurl']) . 'sum/agreements/' . $post_id;
        wp_mkdir_p($base_dir);
        $filename = 'Licence-Agreement-' . $post_id . '.pdf';
        $path = trailingslashit($base_dir) . $filename;
        $url  = trailingslashit($base_url) . $filename;

        // --- Render with Dompdf ---
        if (!function_exists('sum_load_dompdf') || !sum_load_dompdf()) {
            return new WP_Error('dompdf', 'PDF engine (Dompdf) not available.');
        }

        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();
            file_put_contents($path, $output);
        } catch (\Throwable $e) {
            return new WP_Error('pdf', 'Failed to generate PDF: ' . $e->getMessage());
        }

        if (!file_exists($path)) {
            return new WP_Error('pdf', 'PDF not written to disk.');
        }

        return ['path' => $path, 'url' => $url];
    }
}
