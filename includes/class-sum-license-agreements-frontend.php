<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend (admin-only) viewer for License Agreement submissions saved as CPT 'sum_application'.
 * Shortcode: [sum_license_agreements]
 */
class SUM_License_Agreements_Frontend {
    private $per_page = 12;

    public function __construct() {
        add_shortcode('sum_license_agreements', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Optional secure download for attachments (admin-only)
        add_action('wp_ajax_sum_download_intake_media', [$this, 'ajax_download_media']);
        add_action('wp_ajax_sum_confirm_license_agreement', [$this, 'ajax_confirm_agreement']);

    }

    public function enqueue_assets() {
        if (!is_singular()) return;
        global $post;
        if (empty($post) || !has_shortcode($post->post_content, 'sum_license_agreements')) return;

        wp_register_style('sum-la-cpt', false);
        wp_enqueue_style('sum-la-cpt');
        wp_add_inline_script('sum-la-cpt-js', "
            jQuery(function($){
              $(document).on('click','.sum-la-confirm',function(e){
                e.preventDefault();
                var \$btn = $(this);
                if (\$btn.prop('disabled')) return;
                var id = parseInt(\$btn.data('id'),10);
                if (!id) return;
                \$btn.prop('disabled',true).text('Processing...');
                $.post(SUM_LA.ajax_url, {
                  action: 'sum_confirm_license_agreement',
                  nonce: SUM_LA.nonce,
                  post_id: id
                }).done(function(res){
                  if (res && res.success) {
                    \$btn.replaceWith('<span class=\"sum-btn\" style=\"background:#d1fae5;border-color:#10b981;\">Confirmed on '+(res.data.confirmed_at||'now')+'</span>');
                    if (res.data.pdf_url) {
                      $('<a class=\"sum-btn\" style=\"margin-left:8px;\" target=\"_blank\" rel=\"noopener\">Download PDF</a>').attr('href', res.data.pdf_url).insertAfter('.sum-la-card[data-id=\"'+id+'\"] .sum-la-section-title:first');
                    }
                    alert(res.data.message || 'Confirmation email sent.');
                  } else {
                    alert((res && res.data && res.data.message) ? res.data.message : 'Failed.');
                    \$btn.prop('disabled',false).text('Confirm & Send PDF');
                  }
                }).fail(function(){
                  alert('AJAX error.');
                  \$btn.prop('disabled',false).text('Confirm & Send PDF');
                });
              });
            });
            ");
    }

    public function render_shortcode($atts) {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return '<div class="sum-la-wrap">Access denied.</div>';
        }

        $q   = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $pg  = max(1, intval($_GET['pg'] ?? 1));
        $res = $this->query_submissions($q, $pg, $this->per_page);

        ob_start(); ?>
        <div class="sum-la-wrap">
            <div class="sum-la-bar">
                <form method="get">
                    <?php echo $this->keep_query_vars_except(['q','pg']); ?>
                    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search name, email, unit, phone…">
                    <button class="sum-btn" type="submit">Search</button>
                </form>
            </div>

            <?php if (empty($res['posts'])): ?>
                <div class="sum-la-empty">No License Agreement submissions found.</div>
            <?php else: ?>
                <?php foreach ($res['posts'] as $p): 
                    $meta = $this->get_meta_map($p->ID);
                    $id_doc = (int) get_post_meta($p->ID, 'id_document_attachment_id', true);
                    $proof  = (int) get_post_meta($p->ID, 'proof_address_attachment_id', true);
                    $files  = array_filter([
                        $id_doc ? ['id'=>$id_doc,'label'=>'ID / Passport'] : null,
                        $proof  ? ['id'=>$proof, 'label'=>'Utility Bill'] : null,
                    ]);
                ?>
                    <div class="sum-la-card" data-id="<?php echo (int)$p->ID; ?>">

                        <div class="sum-la-section-title">Self Storage Licence Agreement</div>
                        <div class="sum-la-meta">
                            <div><span class="sum-la-label">Submission ID:</span> <?php echo (int)$p->ID; ?></div>
                            <div><span class="sum-la-label">Date:</span> <?php echo esc_html(get_the_date('Y-m-d H:i', $p)); ?></div>
                            <div><span class="sum-la-label">Customer:</span> <?php echo esc_html(trim(($meta['personal_first']??'').' '.($meta['personal_surname']??''))); ?></div>
                            <div><span class="sum-la-label">Email:</span> <?php echo esc_html($meta['email'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Phone:</span> <?php echo esc_html($meta['mobile'] ?? $meta['home_tel'] ?? ''); ?></div>
                        </div>

                        <div class="sum-la-section-title">Personal Details</div>
                        <div class="sum-la-meta">
                            <div><span class="sum-la-label">Address:</span> <?php echo esc_html($meta['home_address'] ?? ''); ?></div>
                            <div><span class="sum-la-label">District:</span> <?php echo esc_html($meta['district'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Post Code:</span> <?php echo esc_html($meta['post_code'] ?? ''); ?></div>
                        </div>

                        <?php if (!empty($meta['has_company'])): ?>
                            <div class="sum-la-section-title">Company Details</div>
                            <div class="sum-la-meta">
                                <div><span class="sum-la-label">Name:</span> <?php echo esc_html($meta['company_name'] ?? ''); ?></div>
                                <div><span class="sum-la-label">Reg No:</span> <?php echo esc_html($meta['company_reg'] ?? ''); ?></div>
                                <div><span class="sum-la-label">Address:</span> <?php echo esc_html($meta['company_address'] ?? ''); ?></div>
                                <div><span class="sum-la-label">District:</span> <?php echo esc_html($meta['company_district'] ?? ''); ?></div>
                                <div><span class="sum-la-label">Post Code:</span> <?php echo esc_html($meta['company_post_code'] ?? ''); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="sum-la-section-title">Unit Details</div>
                        <div class="sum-la-meta">
                            <div><span class="sum-la-label">Unit:</span> <?php echo esc_html($meta['unit_no'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Type:</span> <?php echo esc_html($meta['unit_type'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Size:</span> <?php echo esc_html($meta['unit_size'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Term:</span> <?php echo esc_html($meta['storage_term'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Period:</span> <?php echo esc_html(($meta['period_from'] ?? '').' → '.($meta['period_to'] ?? '')); ?></div>
                            <div><span class="sum-la-label">Monthly (ex VAT):</span> <?php echo esc_html($meta['monthly_ex_vat'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Monthly (inc VAT):</span> <?php echo esc_html($meta['monthly_inc_vat'] ?? ''); ?></div>
                        </div>

                        <?php if (!empty($meta['reason']) || !empty($meta['alt_first'])): ?>
                            <div class="sum-la-section-title">Other</div>
                            <div class="sum-la-meta">
                                <?php if (!empty($meta['reason'])): ?>
                                    <div><span class="sum-la-label">Reason:</span> <?php echo esc_html($meta['reason']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meta['alt_first'])): ?>
                                    <div><span class="sum-la-label">Alt Contact:</span> <?php echo esc_html(trim(($meta['alt_first']??'').' '.($meta['alt_surname']??''))); ?></div>
                                    <div><span class="sum-la-label">Alt Mobile:</span> <?php echo esc_html($meta['alt_mobile'] ?? ''); ?></div>
                                    <div><span class="sum-la-label">Alt Email:</span> <?php echo esc_html($meta['alt_email'] ?? ''); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="sum-la-section-title">Acceptance</div>
                        <div class="sum-la-agreement">
                            <div><span class="sum-la-label">Accepted Terms:</span> <?php echo !empty($meta['accept_terms']) ? 'Yes' : 'No'; ?></div>
                            <div><span class="sum-la-label">Signature (Print Name):</span> <?php echo esc_html($meta['sign_print_name'] ?? ''); ?></div>
                            <div><span class="sum-la-label">Signature Date:</span> <?php echo esc_html($meta['sign_date'] ?? ''); ?></div>
                        </div>

                        <?php if (!empty($files)): ?>
                            <div class="sum-la-section-title">Uploaded Files</div>
                            <div class="sum-la-files">
                                <?php foreach ($files as $f):
                                    $aid = $f['id'];
                                    $url = wp_get_attachment_url($aid);
                                    $mime = get_post_mime_type($aid) ?: '';
                                    $name = get_the_title($aid);
                                    $dl  = wp_nonce_url(admin_url('admin-ajax.php?action=sum_download_intake_media&aid='.$aid), 'sum_download_intake_media_'.$aid);
                                ?>
                                    <div class="sum-la-file">
                                        <?php if (strpos($mime,'image/') === 0 && $url): ?>
                                            <img class="sum-la-thumb" src="<?php echo esc_url($url); ?>" alt="">
                                        <?php elseif ($mime === 'application/pdf' && $url): ?>
                                            <div style="margin-bottom:6px;"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Open PDF</a></div>
                                        <?php endif; ?>
                                        <div><span class="sum-la-label"><?php echo esc_html($f['label']); ?>:</span> <?php echo esc_html($name); ?></div>
                                        <div><a class="sum-btn" href="<?php echo esc_url($dl); ?>">Secure Download</a></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php
$confirmed_at = get_post_meta($p->ID, 'agreement_confirmed_at', true);
$pdf_url      = get_post_meta($p->ID, 'agreement_pdf_url', true);
?>
<div style="margin-top:10px;">
  <?php if ($confirmed_at): ?>
    <span class="sum-btn" style="background:#d1fae5;border-color:#10b981;">Confirmed on <?php echo esc_html($confirmed_at); ?></span>
    <?php if ($pdf_url): ?>
      <a class="sum-btn" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener">Download PDF</a>
    <?php endif; ?>
  <?php else: ?>
    <button type="button" class="sum-btn sum-la-confirm" data-id="<?php echo (int)$p->ID; ?>">Confirm &amp; Send PDF</button>
  <?php endif; ?>
</div>

                    </div>
                <?php endforeach; ?>

                <?php echo $this->pager_html($res['total_pages'], $pg, $q); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Query CPT sum_application with optional search across title+meta */
    private function query_submissions($q, $pg, $per_page) {
        $meta_query = [];
        $args = [
            'post_type'      => 'sum_application',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $pg,
            'orderby'        => 'date',
            'order'          => 'DESC',
            's'              => $q ?: '',
        ];

        if ($q !== '') {
            $like = $q;
            $meta_query = [
                'relation' => 'OR',
                ['key'=>'email',         'value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'mobile',        'value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'home_tel',      'value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'unit_no',       'value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'personal_first','value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'personal_surname','value'=>$like,'compare'=>'LIKE'],
                ['key'=>'company_name',  'value'=>$like, 'compare'=>'LIKE'],
                ['key'=>'company_reg',   'value'=>$like, 'compare'=>'LIKE'],
            ];
            $args['meta_query'] = $meta_query;
        }

        $qobj = new WP_Query($args);

        return [
            'posts'       => $qobj->posts,
            'total'       => (int)$qobj->found_posts,
            'total_pages' => (int)$qobj->max_num_pages,
        ];
    }

    private function get_meta_map($post_id) {
        $keys = [
            'personal_first','personal_surname','home_address','district','post_code','home_tel','mobile','email',
            'has_company','company_name','company_reg','company_address','company_district','company_post_code',
            'unit_no','unit_type','unit_size','storage_term','period_from','period_to',
            'reason','alt_first','alt_surname','alt_mobile','alt_email',
            'ctx_unit_id',
            'accept_terms','sign_print_name','sign_date',
            'monthly_ex_vat','monthly_inc_vat',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = get_post_meta($post_id, $k, true);
        }
        // normalize toggles
        if (!empty($out['has_company']) && $out['has_company'] !== '0') $out['has_company'] = '1';
        return $out;
    }

    private function keep_query_vars_except($except_keys = []) {
        $html = '';
        foreach ($_GET as $k => $v) {
            if (in_array($k, $except_keys, true)) continue;
            $html .= '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(wp_unslash($v)).'">';
        }
        return $html;
    }

    private function pager_html($total_pages, $pg, $q) {
        if ($total_pages <= 1) return '';
        $prev = max(1, $pg - 1);
        $next = min($total_pages, $pg + 1);
        $u_prev = esc_url(add_query_arg(['pg'=>$prev,'q'=>$q]));
        $u_next = esc_url(add_query_arg(['pg'=>$next,'q'=>$q]));
        return '<div class="sum-la-pager">
            <a class="sum-btn" '.($pg<=1?'disabled':'href="'.$u_prev.'"').'>Prev</a>
            <span>Page '.$pg.' of '.$total_pages.'</span>
            <a class="sum-btn" '.($pg>=$total_pages?'disabled':'href="'.$u_next.'"').'>Next</a>
        </div>';
    }

    /** Secure (admin-only) download of an attachment by ID */
    public function ajax_download_media() {
        if (!current_user_can('manage_options')) wp_die('No permission', 403);
        $aid = isset($_GET['aid']) ? absint($_GET['aid']) : 0;
        check_admin_referer('sum_download_intake_media_'.$aid);
        if (!$aid) wp_die('Missing attachment id', 400);

        $path = get_attached_file($aid);
        if (!$path || !file_exists($path)) wp_die('File missing', 404);

        $name = basename($path);
        $mime = get_post_mime_type($aid) ?: 'application/octet-stream';
        nocache_headers();
        header('Content-Type: '.$mime);
        header('Content-Disposition: attachment; filename="'.sanitize_file_name($name).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }
    
    public function ajax_confirm_agreement() {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'No permission'], 403);
    }
    check_ajax_referer('sum_la_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'sum_application') {
        wp_send_json_error(['message' => 'Invalid submission.'], 400);
    }

    // Build PDF
    if ( ! class_exists('SUM_License_Agreement_PDF') ) {
        wp_send_json_error(['message' => 'PDF generator is missing.'], 500);
    }
    $pdf = SUM_License_Agreement_PDF::generate($post_id);
    if ( is_wp_error($pdf) ) {
        wp_send_json_error(['message' => $pdf->get_error_message()], 500);
    }

    // Email details
    $to   = get_post_meta($post_id, 'email', true);
    if (!is_email($to)) {
        wp_send_json_error(['message' => 'Customer email is invalid or missing.'], 400);
    }

    $subject = 'Your Self Storage Licence Agreement – Confirmed';
    $body    = wpautop(
        "Dear Customer,\n\n" .
        "Your Self Storage Licence Agreement has been confirmed. Please find the attached PDF for your records.\n\n" .
        "If you have any questions, reply to this email.\n\n" .
        "Kind regards,\nSelf Storage Cyprus"
    );
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sent = wp_mail($to, $subject, $body, $headers, [$pdf['path']]);
    if (!$sent) {
        wp_send_json_error(['message' => 'Failed to send email.'], 500);
    }

    // Mark as confirmed
    $confirmed_at = current_time('mysql');
    update_post_meta($post_id, 'agreement_confirmed_at', $confirmed_at);
    update_post_meta($post_id, 'agreement_pdf_url', $pdf['url']);

    wp_send_json_success([
        'message' => 'Confirmation email sent.',
        'pdf_url' => $pdf['url'],
        'confirmed_at' => $confirmed_at,
    ]);
}

}
