<?php
if ( ! defined('ABSPATH') ) exit;

class SUM_Admin_Frontend_Report {
    private $per_page = 20;

    public function __construct() {
        add_shortcode('sum_admin_report', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        // Only enqueue on pages that contain the shortcode
        if (!is_singular()) return;
        global $post;
        if (!isset($post->post_content)) return;
        if ( has_shortcode($post->post_content, 'sum_admin_report') ) {
            wp_register_style('sum-admin-frontend-report', false);
            wp_enqueue_style('sum-admin-frontend-report');
            $css = '
            .sum-afr-wrap { font: 14px/1.4 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; }
            .sum-afr-tabs { display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
            .sum-afr-tab { padding:.5rem .75rem; border:1px solid #ddd; border-radius:8px; background:#fff; cursor:pointer; text-decoration:none; }
            .sum-afr-tab.active { background:#0073aa; color:#fff; border-color:#0073aa; }
            .sum-afr-bar { display:flex; gap:.5rem; align-items:center; margin-bottom:.75rem; flex-wrap:wrap; }
            .sum-afr-bar input[type="text"] { padding:.4rem .5rem; border:1px solid #ccc; border-radius:6px; }
            .sum-afr-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e5e5e5; }
            .sum-afr-table th, .sum-afr-table td { padding:.55rem .6rem; border-bottom:1px solid #eee; text-align:left; }
            .sum-afr-table th { background:#f8f8f8; position:sticky; top:0; z-index:1; }
            .sum-afr-pager { display:flex; gap:.5rem; align-items:center; margin-top:.75rem; flex-wrap:wrap; }
            .sum-afr-btn { padding:.45rem .7rem; border:1px solid #ddd; border-radius:6px; background:#f7f7f7; text-decoration:none; display:inline-block; }
            .sum-afr-btn[disabled] { opacity:.5; pointer-events:none; }
            .sum-afr-empty { padding:1rem; color:#777; }
            ';
            wp_add_inline_style('sum-admin-frontend-report', $css);
        }
    }

    public function render_shortcode($atts) {
        if ( ! is_user_logged_in() || ! current_user_can('manage_options') ) {
            return '<div class="sum-afr-wrap">Access denied.</div>';
        }

        // Tabs: units|pallets|customers|payments
        $tab   = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'units';
        $q     = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $page  = max(1, intval($_GET['pg'] ?? 1));
        $export = isset($_GET['export']) && $_GET['export'] === 'csv';
        $nonce = $_GET['_sum_afr'] ?? '';

        $valid_tabs = ['units','pallets','customers','payments'];
        if (!in_array($tab, $valid_tabs, true)) $tab = 'units';

        // Nonce for CSV export
        if ($export) {
            if (!wp_verify_nonce($nonce, 'sum_afr_export')) {
                return '<div class="sum-afr-wrap">Invalid export nonce.</div>';
            }
        }

        global $wpdb;
        $px = $wpdb->prefix;

        // Table names (adjust if your tables differ)
        $tbl_units     = "{$px}storage_units";
        $tbl_pallets   = "{$px}storage_pallets";
        $tbl_customers = "{$px}storage_customers";
        $tbl_history   = "{$px}storage_payment_history";

        // Dispatch per tab
        switch ($tab) {
            case 'units':
                $res = $this->fetch_units($wpdb, $tbl_units, $tbl_customers, $q, $page, $this->per_page, $export);
                break;

            case 'pallets':
                $res = $this->fetch_pallets($wpdb, $tbl_pallets, $tbl_customers, $q, $page, $this->per_page, $export);
                break;

            case 'customers':
                $res = $this->fetch_customers($wpdb, $tbl_customers, $q, $page, $this->per_page, $export);
                break;

            case 'payments':
                $res = $this->fetch_payments($wpdb, $tbl_history, $tbl_customers, $q, $page, $this->per_page, $export);
                break;
        }

        // CSV export stream
        if ($export) {
            $this->stream_csv($res['rows'], $res['csv_headers'], "sum-{$tab}-export.csv");
            exit; // important
        }

        // Build UI
        $base_url = remove_query_arg(['pg','export','_sum_afr']);
        $tabs_html = $this->tabs_html($base_url, $tab);
        $search_html = $this->search_html($base_url, $tab, $q);
        $table_html = $this->table_html($res['cols'], $res['rows']);
        $pager_html = $this->pager_html($base_url, $tab, $q, $page, $this->per_page, $res['total']);
        $export_url = esc_url(add_query_arg([
            'tab' => $tab,
            'q'   => $q,
            'export' => 'csv',
            '_sum_afr' => wp_create_nonce('sum_afr_export')
        ], $base_url));

        ob_start();
        ?>
        <div class="sum-afr-wrap">
            <div class="sum-afr-tabs">
                <?php echo $tabs_html; ?>
            </div>
            <div class="sum-afr-bar">
                <?php echo $search_html; ?>
                <a class="sum-afr-btn" href="<?php echo $export_url; ?>">Export CSV</a>
            </div>
            <?php echo $table_html; ?>
            <?php echo $pager_html; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function tabs_html($base_url, $active) {
        $tabs = [
            'units'     => 'Units',
            'pallets'   => 'Pallets',
            'customers' => 'Customers',
            'payments'  => 'Payments',
        ];
        $out = '';
        foreach ($tabs as $slug => $label) {
            $url = esc_url(add_query_arg(['tab' => $slug], $base_url));
            $cls = 'sum-afr-tab' . ($slug === $active ? ' active' : '');
            $out .= "<a class='{$cls}' href='{$url}'>{$label}</a>";
        }
        return $out;
    }

    private function search_html($base_url, $tab, $q) {
        $action = esc_url(add_query_arg(['tab' => $tab], $base_url));
        $q_esc = esc_attr($q);
        return "
        <form method='get' action='{$action}' class='sum-afr-search' style='display:flex; gap:.5rem; align-items:center;'>
            ".$this->hidden_query_vars_except(['q'])."
            <input type='text' name='q' value='{$q_esc}' placeholder='Search...' />
            <button class='sum-afr-btn' type='submit'>Search</button>
        </form>";
    }

    private function hidden_query_vars_except($except_keys = []) {
        // keep other query vars (e.g., tab), except $except_keys
        $html = '';
        foreach ($_GET as $k => $v) {
            if (in_array($k, $except_keys, true)) continue;
            if ($k === 'export' || $k === '_sum_afr' || $k === 'pg') continue;
            $k2 = esc_attr($k);
            $v2 = esc_attr(wp_unslash($v));
            $html .= "<input type='hidden' name='{$k2}' value='{$v2}' />";
        }
        return $html;
    }

    private function table_html($cols, $rows) {
        if (empty($rows)) {
            return '<div class="sum-afr-empty">No records found.</div>';
        }
        $thead = '<tr>';
        foreach ($cols as $col) $thead .= '<th>'.esc_html($col).'</th>';
        $thead .= '</tr>';

        $tbody = '';
        foreach ($rows as $r) {
            $tbody .= '<tr>';
            foreach ($cols as $key => $label) {
                // If $cols is associative, use key; if numeric, use label as key
                $field = is_string($key) ? $key : $label;
                $val = isset($r[$field]) ? $r[$field] : '';
                $tbody .= '<td>'.wp_kses_post($val).'</td>';
            }
            $tbody .= '</tr>';
        }

        return '<table class="sum-afr-table"><thead>'.$thead.'</thead><tbody>'.$tbody.'</tbody></table>';
    }

    private function pager_html($base_url, $tab, $q, $page, $per_page, $total) {
        if ($total <= $per_page) return '';
        $pages = max(1, (int)ceil($total / $per_page));
        $prev = max(1, $page - 1);
        $next = min($pages, $page + 1);

        $u_prev = esc_url(add_query_arg(['tab'=>$tab, 'q'=>$q, 'pg'=>$prev], $base_url));
        $u_next = esc_url(add_query_arg(['tab'=>$tab, 'q'=>$q, 'pg'=>$next], $base_url));

        return '<div class="sum-afr-pager">
            <a class="sum-afr-btn" '.($page<=1?'disabled':'href="'.$u_prev.'"').'>Prev</a>
            <span>Page '.$page.' of '.$pages.' ('.$total.' results)</span>
            <a class="sum-afr-btn" '.($page>=$pages?'disabled':'href="'.$u_next.'"').'>Next</a>
        </div>';
    }

    private function stream_csv($rows, $headers, $filename) {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            $line = [];
            foreach ($headers as $key) {
                $line[] = isset($r[$key]) ? wp_strip_all_tags($r[$key]) : '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }

    /** ------- Data fetchers ------- **/

    private function like($q) {
        global $wpdb;
        return '%' . $wpdb->esc_like($q) . '%';
    }

    private function fetch_units($wpdb, $tbl_units, $tbl_customers, $q, $page, $per_page, $export) {
        $offset = ($page - 1) * $per_page;
        $where = 'WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $where .= " AND (u.unit_name LIKE %s OR u.size LIKE %s OR u.sqm LIKE %s OR u.payment_status LIKE %s OR c.full_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)";
            $like = $this->like($q);
            $params = array_merge($params, array_fill(0, 7, $like));
        }

        // Count
        $sql_count = "
            SELECT COUNT(*) 
            FROM {$tbl_units} u
            LEFT JOIN {$tbl_customers} c ON c.id = u.customer_id
            $where
        ";
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

        // Data
        $sql_data = "
            SELECT u.id, u.unit_name, u.size, u.sqm, u.monthly_price, u.period_from, u.period_until, u.payment_status,
                   c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
            FROM {$tbl_units} u
            LEFT JOIN {$tbl_customers} c ON c.id = u.customer_id
            $where
            ORDER BY u.id DESC
            " . ( $export ? "" : $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset) );

        $rows = $wpdb->get_results( $wpdb->prepare($sql_data, $params), ARRAY_A );

        $cols = [
            'id' => 'ID',
            'unit_name' => 'Unit',
            'size' => 'Size',
            'sqm' => 'SQM',
            'monthly_price' => 'Monthly €',
            'period_from' => 'From',
            'period_until' => 'Until',
            'payment_status' => 'Payment',
            'customer_name' => 'Customer',
            'customer_email' => 'Email',
            'customer_phone' => 'Phone',
        ];

        return [
            'cols' => $cols,
            'csv_headers' => array_keys($cols),
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private function fetch_pallets($wpdb, $tbl_pallets, $tbl_customers, $q, $page, $per_page, $export) {
        $offset = ($page - 1) * $per_page;
        $where = 'WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $where .= " AND (p.pallet_name LIKE %s OR p.pallet_type LIKE %s OR p.payment_status LIKE %s OR c.full_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)";
            $like = $this->like($q);
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $sql_count = "SELECT COUNT(*) FROM {$tbl_pallets} p LEFT JOIN {$tbl_customers} c ON c.id = p.customer_id $where";
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

        $sql_data = "
            SELECT p.id, p.pallet_name, p.pallet_type, p.monthly_price, p.period_from, p.period_until, p.payment_status,
                   c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
            FROM {$tbl_pallets} p
            LEFT JOIN {$tbl_customers} c ON c.id = p.customer_id
            $where
            ORDER BY p.id DESC
            " . ( $export ? "" : $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset) );

        $rows = $wpdb->get_results( $wpdb->prepare($sql_data, $params), ARRAY_A );

        $cols = [
            'id' => 'ID',
            'pallet_name' => 'Pallet',
            'pallet_type' => 'Type',
            'monthly_price' => 'Monthly €',
            'period_from' => 'From',
            'period_until' => 'Until',
            'payment_status' => 'Payment',
            'customer_name' => 'Customer',
            'customer_email' => 'Email',
            'customer_phone' => 'Phone',
        ];

        return [
            'cols' => $cols,
            'csv_headers' => array_keys($cols),
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private function fetch_customers($wpdb, $tbl_customers, $q, $page, $per_page, $export) {
        $offset = ($page - 1) * $per_page;
        $where = 'WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $where .= " AND (full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR whatsapp LIKE %s)";
            $like = $this->like($q);
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql_count = "SELECT COUNT(*) FROM {$tbl_customers} $where";
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

        $sql_data = "
            SELECT id, full_name, email, phone, whatsapp, full_address, created_at
            FROM {$tbl_customers}
            $where
            ORDER BY id DESC
            " . ( $export ? "" : $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset) );

        $rows = $wpdb->get_results( $wpdb->prepare($sql_data, $params), ARRAY_A );

        $cols = [
            'id' => 'ID',
            'full_name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'whatsapp' => 'WhatsApp',
            'full_address' => 'Address',
            'created_at' => 'Created',
        ];

        return [
            'cols' => $cols,
            'csv_headers' => array_keys($cols),
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private function fetch_payments($wpdb, $tbl_history, $tbl_customers, $q, $page, $per_page, $export) {
        $offset = ($page - 1) * $per_page;
        $where = 'WHERE 1=1';
        $params = [];

        // Example payment history schema: id, customer_id, entity_type, entity_id, transaction_id, amount, currency, months, created_at
        if ($q !== '') {
            $where .= " AND (h.transaction_id LIKE %s OR h.amount LIKE %s OR c.full_name LIKE %s OR c.email LIKE %s)";
            $like = $this->like($q);
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql_count = "
            SELECT COUNT(*)
            FROM {$tbl_history} h
            LEFT JOIN {$tbl_customers} c ON c.id = h.customer_id
            $where
        ";
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

        $sql_data = "
            SELECT h.id, h.transaction_id, h.amount, h.currency, h.months, h.entity_type, h.entity_id, h.created_at,
                   c.full_name AS customer_name, c.email AS customer_email
            FROM {$tbl_history} h
            LEFT JOIN {$tbl_customers} c ON c.id = h.customer_id
            $where
            ORDER BY h.id DESC
            " . ( $export ? "" : $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset) );

        $rows = $wpdb->get_results( $wpdb->prepare($sql_data, $params), ARRAY_A );

        $cols = [
            'id' => 'ID',
            'transaction_id' => 'Transaction',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'months' => 'Months',
            'entity_type' => 'Type',
            'entity_id' => 'Entity ID',
            'customer_name' => 'Customer',
            'customer_email' => 'Email',
            'created_at' => 'Date',
        ];

        return [
            'cols' => $cols,
            'csv_headers' => array_keys($cols),
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
