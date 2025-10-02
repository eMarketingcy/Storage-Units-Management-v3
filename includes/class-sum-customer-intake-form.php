<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Unit-aware Customer Intake Form (modern UI/UX, mobile-first)
 * Shortcode: [sum_customer_intake_form unit_id="123"]
 */
class SUM_Customer_Intake_Form {
    const CPT          = 'sum_application';
    const NONCE        = 'sum_intake_nonce';
    const ACTION       = 'sum_save_intake';
    const QV           = 'sum_token';
    const EXPIRES_DAYS = 14;
    const VAT_RATE     = 0.19; // 19%

    public static function boot() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_shortcode('sum_customer_intake_form', [__CLASS__, 'render_shortcode']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_submit']);
        add_action('admin_post_nopriv_' . self::ACTION, [__CLASS__, 'handle_submit']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_sum_send_intake_link', [__CLASS__, 'handle_send_link']);

        add_filter('query_vars', function($vars){ $vars[] = self::QV; return $vars; });
    }

    public static function register_cpt() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Storage Applications',
                'singular_name' => 'Storage Application'
            ],
            'public' => false,
            'show_ui' => true,
            'menu_position' => 26,
            'supports' => ['title'],
        ]);
    }

    /** Enqueue external CSS/JS from /assets */
    public static function enqueue_assets() {
        $base = plugin_dir_url(dirname(__FILE__)); // points to plugin root/
        $path = plugin_dir_path(dirname(__FILE__)); // filesystem path to plugin root/

        $css_file = 'assets/sum-intake.css';
        $js_file  = 'assets/sum-intake.js';

        $css_ver = file_exists($path . $css_file) ? filemtime($path . $css_file) : '1.0.0';
        $js_ver  = file_exists($path . $js_file)  ? filemtime($path . $js_file)  : '1.0.0';

        wp_enqueue_style('sum-intake', $base . $css_file, [], $css_ver);
        wp_enqueue_script('sum-intake', $base . $js_file, [], $js_ver, true);
    }

    /** ---------- Fetchers (adapt via filters if schema differs) ---------- */
    protected static function fetch_unit($unit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'storage_units';
        $unit  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $unit_id), ARRAY_A);
        return apply_filters('sum_intake_fetch_unit', $unit, $unit_id);
    }
    protected static function fetch_customer($customer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'storage_customers';
        $c     = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $customer_id), ARRAY_A);
        return apply_filters('sum_intake_fetch_customer', $c, $customer_id);
    }

    /** ---------- Signed link utils ---------- */
    protected static function make_token($unit_id, $customer_email, $expires_ts) {
        $data = $unit_id . '|' . strtolower(trim((string)$customer_email)) . '|' . $expires_ts;
        return hash_hmac('sha256', $data, wp_salt('auth'));
    }
    protected static function verify_token($token, $unit_id, $customer_email, $expires_ts) {
        if (time() > (int)$expires_ts) return false;
        return hash_equals(self::make_token($unit_id, $customer_email, $expires_ts), $token);
    }
    public static function share_url($page_id, $unit_id, $customer_email) {
        $expires_ts = time() + DAY_IN_SECONDS * self::EXPIRES_DAYS;
        $token = self::make_token($unit_id, $customer_email, $expires_ts);
        return add_query_arg([
            'unit_id' => (int)$unit_id,
            self::QV  => base64_encode(json_encode(['u'=>$unit_id,'e'=>$expires_ts,'m'=>rawurlencode($customer_email),'t'=>$token])),
        ], get_permalink($page_id));
    }

    /** ---------- Shortcode ---------- */
    public static function render_shortcode($atts=[]) {
        $atts = shortcode_atts(['unit_id' => 0], $atts, 'sum_customer_intake_form');
        $unit_id = (int)($atts['unit_id'] ?: ($_GET['unit_id'] ?? 0));

        // Signed token support (public prefill)
        $token_ok = false; $prefill_customer_email='';
        if (!empty($_GET[self::QV])) {
            $payload = json_decode(base64_decode(sanitize_text_field($_GET[self::QV])), true);
            if (is_array($payload) && isset($payload['u'],$payload['e'],$payload['m'],$payload['t'])) {
                $prefill_customer_email = rawurldecode($payload['m']);
                $token_ok = self::verify_token($payload['t'], (int)$payload['u'], $prefill_customer_email, (int)$payload['e']);
                if ($token_ok) $unit_id = (int)$payload['u'];
            }
        }

        $unit = $unit_id ? self::fetch_unit($unit_id) : null;
        $customer = null;
        if ($unit && !empty($unit['customer_id'])) {
            $customer = self::fetch_customer((int)$unit['customer_id']);
        }

        // Prefill: split full_name into first/surname (basic split on last space)
        $first = ''; $last = '';
        if (!empty($customer['full_name'])) {
            $parts = preg_split('/\s+/', trim($customer['full_name']));
            if ($parts) {
                $last  = array_pop($parts);
                $first = implode(' ', $parts);
                if ($first === '') { $first = $last; $last=''; }
            }
        }

        $monthly_ex  = isset($unit['monthly_price']) ? floatval($unit['monthly_price']) : 0.0;
        $monthly_inc = $monthly_ex > 0 ? round($monthly_ex * (1 + self::VAT_RATE), 2) : 0.0;

        $d = [
            // Personal (required)
            'personal_first'  => $first,
            'personal_surname'=> $last,
            'home_address'    => $customer['address']   ?? '',
            'district'        => $customer['district']  ?? '',
            'post_code'       => $customer['post_code'] ?? '',
            'home_tel'        => '',
            'mobile'          => $customer['phone']     ?? '',
            'email'           => $customer['email']     ?? ($token_ok ? $prefill_customer_email : ''),

            // Business (toggle)
            'company_name'    => '',
            'company_reg'     => '',
            'company_address' => '',
            'company_district'=> '',
            'company_post_code'=> '',

            // Unit (pre-filled)
            'unit_no'         => $unit ? ($unit['unit_name'] ?? $unit['id']) : '',
            'unit_type'       => get_bloginfo('name'),
            'unit_size'       => $unit['size'] ?? ($unit['sqm'] ?? ''),
            'storage_term'    => '1',
            'period_from'     => '',
            'period_to'       => '',

            // Reason & alternative contact (toggle)
            'reason'          => '',
            'alt_first'       => '',
            'alt_surname'     => '',
            'alt_mobile'      => '',
            'alt_email'       => '',

            // Price (info only)
            'monthly_ex_vat'  => $monthly_ex,
            'monthly_inc_vat' => $monthly_inc,

            // Acceptance
            'sign_print_name' => '',
            'sign_date'       => current_time('Y-m-d'),
            'accept_terms'    => '',
        ];

        $action = esc_url( admin_url('admin-post.php') );
        $nonce = wp_create_nonce(self::NONCE);

        // Thank-you toast
        if (!empty($_GET['sum_submitted'])) {
            echo '<div class="sum-toast" role="status" aria-live="polite">Thanks! Your application was submitted.</div>';
        }

        ob_start(); ?>
        <form class="sum-card sum-form" method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" novalidate>
            <div class="sum-header">
                <div class="sum-title">SELF STORAGE LICENCE AGREEMENT</div>
                <div class="sum-subtitle"><?php echo esc_html(get_bloginfo('name')); ?></div>
            </div>

            <input type="hidden" name="action" value="<?php echo self::ACTION; ?>">
            <input type="hidden" name="sum_nonce" value="<?php echo $nonce; ?>">
            <input type="hidden" name="ctx_unit_id" value="<?php echo esc_attr($unit_id); ?>">

            <!-- PERSONAL STORAGE -->
            <section class="sum-section">
                <h3 class="sum-h">PERSONAL STORAGE</h3>

                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="personal_first">First Name <span class="req">*</span></label>
                        <input class="sum-input" id="personal_first" type="text" name="personal_first" value="<?php echo esc_attr($d['personal_first']); ?>" required>
                    </div>
                    <div class="sum-field">
                        <label for="personal_surname">Surname <span class="req">*</span></label>
                        <input class="sum-input" id="personal_surname" type="text" name="personal_surname" value="<?php echo esc_attr($d['personal_surname']); ?>" required>
                    </div>
                </div>

                <div class="sum-grid">
                    <div class="sum-field sum-col-12">
                        <label for="home_address">Home Address <span class="req">*</span></label>
                        <input class="sum-input" id="home_address" type="text" name="home_address" value="<?php echo esc_attr($d['home_address']); ?>" required>
                    </div>
                </div>

                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="district">District <span class="req">*</span></label>
                        <input class="sum-input" id="district" type="text" name="district" value="<?php echo esc_attr($d['district']); ?>" required>
                    </div>
                    <div class="sum-field">
                        <label for="post_code">Post Code <span class="req">*</span></label>
                        <input class="sum-input" id="post_code" type="text" name="post_code" value="<?php echo esc_attr($d['post_code']); ?>" required>
                    </div>
                    <div class="sum-field">
                        <label for="home_tel">Home Tel No <span class="req">*</span></label>
                        <input class="sum-input" id="home_tel" type="tel" name="home_tel" value="<?php echo esc_attr($d['home_tel']); ?>" required>
                    </div>
                </div>

                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="mobile">Mobile <span class="req">*</span></label>
                        <input class="sum-input" id="mobile" type="tel" name="mobile" value="<?php echo esc_attr($d['mobile']); ?>" required>
                    </div>
                    <div class="sum-field">
                        <label for="email">Email <span class="req">*</span></label>
                        <input class="sum-input" id="email" type="email" name="email" value="<?php echo esc_attr($d['email']); ?>" required>
                    </div>
                </div>

                <!-- Uploads (required) -->
                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="id_document">ID / Passport (PDF/JPG/PNG) <span class="req">*</span></label>
                        <input class="sum-input sum-file" id="id_document" type="file" name="id_document" accept="application/pdf,image/*" required>
                        <span class="sum-file-hint" aria-hidden="true">Choose file…</span>
                    </div>
                    <div class="sum-field">
                        <label for="proof_address">Utility Bill (Proof of Address) <span class="req">*</span></label>
                        <input class="sum-input sum-file" id="proof_address" type="file" name="proof_address" accept="application/pdf,image/*" required>
                        <span class="sum-file-hint" aria-hidden="true">Choose file…</span>
                    </div>
                </div>
            </section>

            <!-- BUSINESS STORAGE (toggle) -->
            <section class="sum-section">
                <div class="sum-toggle">
                    <input type="checkbox" class="sum-switch" id="has_company" name="has_company" value="1" aria-controls="business_block" aria-expanded="false">
                    <label for="has_company">I have a company</label>
                </div>

                <div id="business_block" class="sum-hidden" role="region" aria-labelledby="has_company">
                    <h3 class="sum-h">BUSINESS STORAGE</h3>
                    <div class="sum-grid">
                        <div class="sum-field">
                            <label for="company_name">Company Name</label>
                            <input class="sum-input" id="company_name" type="text" name="company_name" value="<?php echo esc_attr($d['company_name']); ?>">
                        </div>
                        <div class="sum-field">
                            <label for="company_reg">Co Reg No</label>
                            <input class="sum-input" id="company_reg" type="text" name="company_reg" value="<?php echo esc_attr($d['company_reg']); ?>">
                        </div>
                        <div class="sum-field sum-col-12">
                            <label for="company_address">Company Address</label>
                            <input class="sum-input" id="company_address" type="text" name="company_address" value="<?php echo esc_attr($d['company_address']); ?>">
                        </div>
                    </div>
                    <div class="sum-grid">
                        <div class="sum-field">
                            <label for="company_district">District</label>
                            <input class="sum-input" id="company_district" type="text" name="company_district" value="<?php echo esc_attr($d['company_district']); ?>">
                        </div>
                        <div class="sum-field">
                            <label for="company_post_code">Post Code</label>
                            <input class="sum-input" id="company_post_code" type="text" name="company_post_code" value="<?php echo esc_attr($d['company_post_code']); ?>">
                        </div>
                    </div>
                </div>
            </section>

            <!-- SELF STORAGE UNIT -->
            <section class="sum-section">
                <h3 class="sum-h">SELF STORAGE UNIT</h3>
                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="unit_no">Unit No</label>
                        <input class="sum-input" id="unit_no" type="text" name="unit_no" value="<?php echo esc_attr($d['unit_no']); ?>" readonly>
                    </div>
                    <div class="sum-field">
                        <label for="unit_type">Unit Type</label>
                        <input class="sum-input" id="unit_type" type="text" name="unit_type" value="<?php echo esc_attr($d['unit_type']); ?>" readonly>
                    </div>
                    <div class="sum-field">
                        <label for="unit_size">Unit Size</label>
                        <input class="sum-input" id="unit_size" type="text" name="unit_size" value="<?php echo esc_attr($d['unit_size']); ?>" readonly>
                    </div>
                </div>
                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="storage_term">Storage Term (month/s)</label>
                        <input class="sum-input" id="storage_term" type="number" min="1" step="1" name="storage_term" value="<?php echo esc_attr($d['storage_term']); ?>">
                    </div>
                    <div class="sum-field">
                        <label for="period_from">Storage Period From</label>
                        <input class="sum-input" id="period_from" type="date" name="period_from" value="<?php echo esc_attr($d['period_from']); ?>">
                    </div>
                    <div class="sum-field">
                        <label for="period_to">to</label>
                        <input class="sum-input" id="period_to" type="date" name="period_to" value="<?php echo esc_attr($d['period_to']); ?>">
                    </div>
                </div>
                <p class="sum-help">Note: Unit sizes are approximate; ensure size is correct before signing.</p>
            </section>

            <!-- ALTERNATIVE CONTACT (toggle) -->
            <section class="sum-section">
                <div class="sum-toggle">
                    <input type="checkbox" class="sum-switch" id="has_alt_contact" name="has_alt_contact" value="1" aria-controls="alt_block" aria-expanded="false">
                    <label for="has_alt_contact">Add alternative contact person</label>
                </div>

                <div id="alt_block" class="sum-hidden" role="region" aria-labelledby="has_alt_contact">
                    <h3 class="sum-h">ALTERNATIVE CONTACT</h3>
                    <div class="sum-grid">
                        <div class="sum-field">
                            <label for="alt_first">First Name</label>
                            <input class="sum-input" id="alt_first" type="text" name="alt_first" value="<?php echo esc_attr($d['alt_first']); ?>">
                        </div>
                        <div class="sum-field">
                            <label for="alt_surname">Surname</label>
                            <input class="sum-input" id="alt_surname" type="text" name="alt_surname" value="<?php echo esc_attr($d['alt_surname']); ?>">
                        </div>
                    </div>
                    <div class="sum-grid">
                        <div class="sum-field">
                            <label for="alt_mobile">Alternative Mobile</label>
                            <input class="sum-input" id="alt_mobile" type="tel" name="alt_mobile" value="<?php echo esc_attr($d['alt_mobile']); ?>">
                        </div>
                        <div class="sum-field">
                            <label for="alt_email">Alternative Email</label>
                            <input class="sum-input" id="alt_email" type="email" name="alt_email" value="<?php echo esc_attr($d['alt_email']); ?>">
                        </div>
                    </div>
                </div>
            </section>

            <!-- PRICE & PAYMENT (informative only) -->
            <section class="sum-section">
                <h3 class="sum-h">PRICE &amp; PAYMENT</h3>
                <div class="sum-price">
                    <div class="row"><strong>Monthly price (excl. VAT):</strong> <span>€<?php echo number_format($d['monthly_ex_vat'], 2); ?></span></div>
                    <div class="row"><strong>VAT (19%):</strong> <span>€<?php echo number_format($d['monthly_ex_vat'] * self::VAT_RATE, 2); ?></span></div>
                    <div class="row"><strong>Monthly price (incl. VAT):</strong> <span>€<?php echo number_format($d['monthly_inc_vat'], 2); ?></span></div>
                </div>
                <p class="sum-help">Storage fees are paid monthly in advance. Payment methods: Credit Card (in person), PayPal, Revolut/Quick Pay (+357 97 640422), Cash, Cheque. Bank Transfer & Monthly Direct Debit also accepted.</p>
            </section>

            <!-- SELECTED POINTS & ACCEPTANCE -->
            <section class="sum-section">
                <h3 class="sum-h">SELECTED POINTS</h3>
                <p class="sum-help">
                    By signing I confirm I have read and agree to be bound by GEOGEO Limited t/a Self Storage Cyprus Terms
                    (<a class="sum-link" href="https://selfstorage.cy/self-storage-terms-conditions/" target="_blank" rel="noopener">see website terms</a>).
                    Key points include: storage fees paid in advance; no hazardous/illegal items; access during posted hours; you are responsible for securing the unit; goods stored at your risk; minimum 15 days’ notice to terminate.
                </p>
                <div class="sum-grid">
                    <div class="sum-field">
                        <label for="sign_print_name">Print Name <span class="req">*</span></label>
                        <input class="sum-input" id="sign_print_name" type="text" name="sign_print_name" value="<?php echo esc_attr($d['sign_print_name']); ?>" required>
                    </div>
                    <div class="sum-field">
                        <label for="sign_date">Date <span class="req">*</span></label>
                        <input class="sum-input" id="sign_date" type="date" name="sign_date" value="<?php echo esc_attr($d['sign_date']); ?>" required>
                    </div>
                </div>
                <label class="sum-check">
                    <input type="checkbox" name="accept_terms" value="1" <?php checked($d['accept_terms'],'1'); ?> required>
                    I have read and agree to be bound by the Self Storage Licence Agreement terms and conditions.
                </label>
            </section>

            <div class="sum-actions">
                <button class="sum-btn" type="submit">Submit Application</button>
            </div>

            <!-- Hidden, just for saving informative values if needed -->
            <input type="hidden" name="monthly_ex_vat" value="<?php echo esc_attr($d['monthly_ex_vat']); ?>">
            <input type="hidden" name="monthly_inc_vat" value="<?php echo esc_attr($d['monthly_inc_vat']); ?>">
        </form>
        <?php
        return ob_get_clean();
    }

    /** ---------- Handle submit ---------- */
    public static function handle_submit() {
        if ( ! isset($_POST['sum_nonce']) || ! wp_verify_nonce($_POST['sum_nonce'], self::NONCE) ) {
            wp_die('Security check failed');
        }

        // Server-side required validations
        $req = ['personal_first','personal_surname','home_address','district','post_code','home_tel','mobile','email'];
        foreach ($req as $key) {
            if (empty($_POST[$key])) wp_die('Missing required field: '.esc_html($key));
        }
        if (empty($_FILES['id_document']['name']) || empty($_FILES['proof_address']['name'])) {
            wp_die('ID/Passport and Utility Bill uploads are required.');
        }

        $f = function($key){ return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; };

        $title = trim($f('personal_first') . ' ' . $f('personal_surname'));
        if ($title === '') $title = 'Storage Application ' . current_time('Y-m-d H:i');

        $post_id = wp_insert_post([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'post_title' => $title,
        ]);
        if (is_wp_error($post_id)) wp_die('Could not save application.');

        // Save scalar fields
        $fields = [
            // Personal
            'personal_first','personal_surname','home_address','district','post_code','home_tel','mobile','email',
            // Toggles
            'has_company','has_alt_contact',
            // Business
            'company_name','company_reg','company_address','company_district','company_post_code',
            // Unit
            'unit_no','unit_type','unit_size','storage_term','period_from','period_to',
            // Reason & alt contact
            'reason','alt_first','alt_surname','alt_mobile','alt_email',
            // Context
            'ctx_unit_id',
            // Acceptance
            'accept_terms','sign_print_name','sign_date',
            // Price (informative)
            'monthly_ex_vat','monthly_inc_vat',
        ];
        foreach ($fields as $key) {
            $val = $f($key);
            if (in_array($key, ['has_company','has_alt_contact','accept_terms'], true)) {
                $val = $val ? '1' : '0';
            }
            update_post_meta($post_id, $key, $val);
        }

        // Handle uploads safely
        $id_doc_id  = self::save_upload_to_media('id_document', $post_id);
        $proof_id   = self::save_upload_to_media('proof_address', $post_id);
        if (!$id_doc_id || !$proof_id) {
            wp_delete_post($post_id, true);
            wp_die('Upload failed. Please try again.');
        }
        update_post_meta($post_id, 'id_document_attachment_id', (int)$id_doc_id);
        update_post_meta($post_id, 'proof_address_attachment_id', (int)$proof_id);

        wp_safe_redirect( add_query_arg(['sum_submitted'=>'1'], wp_get_referer() ?: home_url()) );
        exit;
    }

    protected static function save_upload_to_media($field, $post_id) {
        if (empty($_FILES[$field]['name'])) return 0;

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = [
            'test_form' => false,
            'mimes' => [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'
            ],
        ];
        $file = wp_handle_upload($_FILES[$field], $overrides);
        if (isset($file['error'])) return 0;

        $attachment = [
            'post_mime_type' => $file['type'],
            'post_title'     => sanitize_file_name(basename($file['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $file['file'], $post_id);
        if (is_wp_error($attach_id) || !$attach_id) return 0;

        $attach_data = wp_generate_attachment_metadata($attach_id, $file['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /** ---------- Admin: Send Intake Link ---------- */
    public static function admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Send Intake Link',
            'Send Intake Link',
            'manage_options',
            'sum-send-intake',
            [__CLASS__, 'render_send_link_page']
        );
    }

    public static function render_send_link_page() {
        if ( ! current_user_can('manage_options') ) return;
        $sent = isset($_GET['sent']) ? intval($_GET['sent']) : 0;
        $pref_unit = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
        ?>
        <div class="wrap">
            <h1>Send Intake Link</h1>
            <?php if ($sent) echo '<div class="notice notice-success"><p>Link sent.</p></div>'; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sum_send_intake'); ?>
                <input type="hidden" name="action" value="sum_send_intake_link">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="unit_id">Unit ID</label></th>
                        <td><input name="unit_id" id="unit_id" type="number" class="regular-text" required value="<?php echo esc_attr($pref_unit); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="page_id">Page with shortcode</label></th>
                        <td>
                            <input name="page_id" id="page_id" type="number" class="regular-text" required>
                            <p class="description">Enter the Page/Post ID where you placed <code>[sum_customer_intake_form]</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Customer Email (optional)</label></th>
                        <td><input name="email" id="email" type="email" class="regular-text">
                            <p class="description">If empty, we’ll use the email from the unit’s assigned customer.</p></td>
                    </tr>
                </table>
                <?php submit_button('Send Link'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_send_link() {
        if ( ! current_user_can('manage_options') ) wp_die('Nope.');
        check_admin_referer('sum_send_intake');
        $unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $email   = sanitize_email($_POST['email'] ?? '');

        if (!$unit_id || !$page_id) wp_die('Missing unit_id or page_id');

        $unit = self::fetch_unit($unit_id);
        if (empty($email) && $unit && !empty($unit['customer_id'])) {
            $c = self::fetch_customer((int)$unit['customer_id']);
            if ($c && !empty($c['email'])) $email = $c['email'];
        }
        if (empty($email)) wp_die('No email found.');

        $url = self::share_url($page_id, $unit_id, $email);

        $subject = sprintf('Self Storage Intake Form – Unit %s', $unit['unit_name'] ?? $unit_id);
        $body = "Hello,\n\nPlease complete your Self Storage Licence Agreement details here:\n\n{$url}\n\nThis link expires in ".self::EXPIRES_DAYS." days.\n\nThank you.";
        wp_mail($email, $subject, $body);

        wp_safe_redirect( add_query_arg(['page'=>'sum-send-intake','sent'=>1], admin_url('edit.php?post_type=' . self::CPT)) );
        exit;
    }
}
SUM_Customer_Intake_Form::boot();
