<?php
/**
 * Plugin Name:       Budget Planner Pro
 * Description:       A simple plugin for site administrators to manage budgets and export them to PDF.
 * Version:           1.5.6
 * Author:            Swimming Ideas
 * Author URI:        https://gemini.google.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       budget-planner-pro
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// =============================================================================
// Activation Hook: Create Database Tables
// =============================================================================

register_activation_hook(__FILE__, 'bpp_install_database');

function bpp_install_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for Accounts (the main sheet)
    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    $sql_accounts = "CREATE TABLE $table_accounts (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        budget_year year NOT NULL,
        account_title varchar(255) NOT NULL,
        account_number varchar(100) DEFAULT '' NOT NULL,
        location_name varchar(255) DEFAULT '' NOT NULL,
        year_end_estimate decimal(15,2) NOT NULL DEFAULT 0.00,
        proposed_budget decimal(15,2) NOT NULL DEFAULT 0.00,
        prepared_by varchar(255) NOT NULL,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for Line Items within each Account
    $table_items = $wpdb->prefix . 'bpp_line_items';
    $sql_items = "CREATE TABLE $table_items (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        account_id mediumint(9) NOT NULL,
        item_type varchar(20) NOT NULL DEFAULT 'standard',
        item_name varchar(255) NOT NULL,
        item_link varchar(255) DEFAULT '' NOT NULL,
        quantity int(11) NULL,
        price decimal(15,2) NULL,
        comment text,
        purchased_date date,
        is_approved tinyint(1) NOT NULL DEFAULT 0,
        weeks int(11) NULL,
        days int(11) NULL,
        hours decimal(10,2) NULL,
        rate decimal(15,2) NULL,
        staff_count int(11) NULL,
        PRIMARY KEY  (id),
        KEY account_id (account_id)
    ) $charset_collate;";
    
    // Table for Locations (for tag-like functionality)
    $table_locations = $wpdb->prefix . 'bpp_locations';
    $sql_locations = "CREATE TABLE $table_locations (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name (name)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_accounts);
    dbDelta($sql_items);
    dbDelta($sql_locations);
}


// =============================================================================
// Admin Menu Setup
// =============================================================================

add_action('admin_menu', 'bpp_add_admin_menu');

function bpp_add_admin_menu() {
    add_menu_page(
        'Budget Planner',
        'Budget Planner',
        'manage_options',
        'budget-planner',
        'bpp_admin_page_router',
        'dashicons-clipboard',
        6
    );
}

// =============================================================================
// Form & Data Handler
// =============================================================================

add_action('admin_init', 'bpp_handle_post_requests');
/**
 * Handles all POST and GET data processing (saves, updates, deletes, duplicates)
 * before the page headers are sent.
 */
function bpp_handle_post_requests() {
    // --- Handle PDF Generation first, as it stops all other output ---
    if (isset($_GET['action']) && $_GET['action'] == 'generate_pdf' && isset($_GET['page']) && $_GET['page'] == 'budget-planner' && current_user_can('manage_options')) {
        bpp_generate_pdf_handler(); // This function calls exit() and stops further execution.
    }
    
    // Check if we are on our plugin page and a form has been submitted to save/update
    if (isset($_POST['bpp_save_account_nonce']) && wp_verify_nonce($_POST['bpp_save_account_nonce'], 'bpp_save_account_action')) {
        bpp_handle_form_submission(); // This function ends with a redirect and exit.
    }

    // Check if we are duplicating an account
    if (isset($_POST['bpp_duplicate_account_nonce']) && wp_verify_nonce($_POST['bpp_duplicate_account_nonce'], 'bpp_duplicate_account_action')) {
        bpp_handle_duplication_submission(); // This function also ends with a redirect.
    }
    
    // Check if we are bulk duplicating accounts
    if (isset($_POST['bpp_duplicate_bulk_nonce']) && wp_verify_nonce($_POST['bpp_duplicate_bulk_nonce'], 'bpp_duplicate_bulk_action')) {
        bpp_handle_bulk_duplication_submission(); // This function also ends with a redirect.
    }
    
    // Check if we are bulk duplicating by location
    if (isset($_POST['bpp_duplicate_location_nonce']) && wp_verify_nonce($_POST['bpp_duplicate_location_nonce'], 'bpp_duplicate_location_action')) {
        bpp_handle_location_duplication_submission(); // This function also ends with a redirect.
    }
    
    // Handle deletion requests
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['account_id']) && isset($_GET['_wpnonce']) && isset($_GET['page']) && $_GET['page'] == 'budget-planner') {
        if (wp_verify_nonce($_GET['_wpnonce'], 'bpp_delete_account_' . $_GET['account_id'])) {
             global $wpdb;
             $account_id = intval($_GET['account_id']);
             $wpdb->delete($wpdb->prefix . 'bpp_accounts', ['id' => $account_id], ['%d']);
             $wpdb->delete($wpdb->prefix . 'bpp_line_items', ['account_id' => $account_id], ['%d']);
             
             // Redirect after deletion to remove query args from the URL and show a message.
             wp_redirect(admin_url('admin.php?page=budget-planner&message=2'));
             exit;
        }
    }
}


// =============================================================================
// Admin Page Router & Form Handling
// =============================================================================

function bpp_admin_page_router() {
    // Check for FPDF library and show a persistent notice if it's missing.
    $fpdf_path = plugin_dir_path(__FILE__) . 'fpdf.php';
    $font_path = plugin_dir_path(__FILE__) . 'font/';
    if (!file_exists($fpdf_path) || !is_dir($font_path)) {
        echo '<div class="notice notice-error"><p><strong>Budget Planner Pro Action Required:</strong> The FPDF library is incomplete. PDF generation will not work. Please <a href="http://www.fpdf.org/en/download.php" target="_blank">download the latest version of FPDF</a>, and place both the `fpdf.php` file and the `font` folder inside the `wp-content/plugins/budget-planner/` directory.</p></div>';
    }
    
    // --- Handle PDF Generation has been moved to bpp_handle_post_requests() on admin_init ---

    // --- Data processing has been moved to bpp_handle_post_requests() on admin_init ---

    // --- Page Routing & Display ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    echo '<div class="wrap">';

    // Display success messages based on URL parameter
    if (isset($_GET['message'])) {
        if ($_GET['message'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Account saved successfully.</p></div>';
        }
        if ($_GET['message'] == '2') {
             echo '<div class="notice notice-success is-dismissible"><p>Account deleted successfully.</p></div>';
        }
        if ($_GET['message'] == '3') {
             echo '<div class="notice notice-success is-dismissible"><p>Account duplicated successfully.</p></div>';
        }
        if ($_GET['message'] == '4') {
             echo '<div class="notice notice-success is-dismissible"><p>All accounts for the selected year have been duplicated successfully.</p></div>';
        }
        if ($_GET['message'] == '5') {
             echo '<div class="notice notice-success is-dismissible"><p>Accounts duplicated to the new location successfully.</p></div>';
        }
    }
    
    echo '<h1>Budget Planner <a href="?page=budget-planner&action=add" class="page-title-action">Add New Account</a></h1>';

    switch ($action) {
        case 'add':
        case 'edit':
            bpp_render_add_edit_form();
            break;
        case 'duplicate':
            bpp_render_duplicate_form();
            break;
        default:
            bpp_render_list_table();
            break;
    }
    echo '</div>';
}

function bpp_handle_form_submission() {
    global $wpdb;
    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    $table_items = $wpdb->prefix . 'bpp_line_items';
    $table_locations = $wpdb->prefix . 'bpp_locations';
    
    $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
    
    $location_name = sanitize_text_field($_POST['location_name']);

    $account_data = [
        'budget_year' => intval($_POST['budget_year']),
        'account_title' => sanitize_text_field($_POST['account_title']),
        'account_number' => sanitize_text_field($_POST['account_number']),
        'location_name' => $location_name,
        'year_end_estimate' => floatval($_POST['year_end_estimate']),
        'proposed_budget' => floatval($_POST['proposed_budget']),
        'prepared_by' => sanitize_text_field($_POST['prepared_by']),
    ];

    if ($account_id > 0) {
        // Update existing account
        $wpdb->update($table_accounts, $account_data, ['id' => $account_id]);
    } else {
        // Insert new account
        $account_data['created_at'] = current_time('mysql');
        if (false === $wpdb->insert($table_accounts, $account_data)) {
            wp_die('Error: The new account could not be saved to the database. Please go back and try again.');
        }
        $account_id = $wpdb->insert_id;
    }

    if (empty($account_id)) {
        wp_die('Error: Could not retrieve a valid Account ID after saving. Line items cannot be processed.');
    }
    
    if (!empty($location_name)) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_locations WHERE name = %s", $location_name));
        if ($exists == 0) {
            $wpdb->insert($table_locations, ['name' => $location_name], ['%s']);
        }
    }

    // --- NEW SAFE SAVE LOGIC FOR LINE ITEMS ---
    $existing_item_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_items WHERE account_id = %d", $account_id));
    $submitted_item_ids = [];

    // Handle standard items
    if (isset($_POST['line_items']) && is_array($_POST['line_items'])) {
        foreach ($_POST['line_items'] as $item_key => $item_data) {
            if (empty($item_data['item_name'])) continue;

            $item_id = (is_numeric($item_key)) ? intval($item_key) : 0;
            
            $data = [
                'account_id' => $account_id, 'item_type' => 'standard',
                'item_name' => sanitize_text_field($item_data['item_name']),
                'item_link' => esc_url_raw($item_data['item_link']),
                'quantity' => intval($item_data['quantity']),
                'price' => floatval($item_data['price']),
                'comment' => sanitize_textarea_field($item_data['comment']),
                'purchased_date' => !empty($item_data['purchased_date']) ? sanitize_text_field($item_data['purchased_date']) : null,
                'is_approved' => intval($item_data['is_approved']),
            ];

            if ($item_id > 0 && in_array($item_id, $existing_item_ids)) {
                $wpdb->update($table_items, $data, ['id' => $item_id]);
                $submitted_item_ids[] = $item_id;
            } else {
                $wpdb->insert($table_items, $data);
                $new_id = $wpdb->insert_id;
                if ($new_id) { $submitted_item_ids[] = $new_id; }
            }
        }
    }
    
    // Handle staffing items
    if (isset($_POST['staff_items']) && is_array($_POST['staff_items'])) {
        foreach ($_POST['staff_items'] as $item_key => $item_data) {
            if (empty($item_data['item_name'])) continue;

            $item_id = (is_numeric($item_key)) ? intval($item_key) : 0;

            $data = [
                'account_id' => $account_id, 'item_type' => 'staffing',
                'item_name' => sanitize_text_field($item_data['item_name']),
                'comment' => sanitize_textarea_field($item_data['comment']),
                'weeks' => intval($item_data['weeks']),
                'days' => intval($item_data['days']),
                'hours' => floatval($item_data['hours']),
                'rate' => floatval($item_data['rate']),
                'staff_count' => intval($item_data['staff_count']),
                'is_approved' => intval($item_data['is_approved']),
            ];

            if ($item_id > 0 && in_array($item_id, $existing_item_ids)) {
                $wpdb->update($table_items, $data, ['id' => $item_id]);
                $submitted_item_ids[] = $item_id;
            } else {
                $wpdb->insert($table_items, $data);
                $new_id = $wpdb->insert_id;
                if ($new_id) { $submitted_item_ids[] = $new_id; }
            }
        }
    }

    // Delete items that were present in the DB but not submitted in the form
    $items_to_delete = array_diff($existing_item_ids, $submitted_item_ids);
    if (!empty($items_to_delete)) {
        foreach ($items_to_delete as $delete_id) {
            $wpdb->delete($table_items, ['id' => intval($delete_id)], ['%d']);
        }
    }

    wp_redirect(admin_url('admin.php?page=budget-planner&message=1'));
    exit;
}

function bpp_handle_duplication_submission() {
    global $wpdb;
    $original_account_id = isset($_POST['original_account_id']) ? intval($_POST['original_account_id']) : 0;
    $new_budget_year = isset($_POST['new_budget_year']) ? intval($_POST['new_budget_year']) : 0;

    if (!$original_account_id || !$new_budget_year) {
        wp_die('Missing information for duplication.');
    }

    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    $table_items = $wpdb->prefix . 'bpp_line_items';

    // 1. Get the original account data
    $original_account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_accounts WHERE id = %d", $original_account_id), ARRAY_A);
    if (!$original_account) {
        wp_die('Original account not found.');
    }

    // 2. Prepare new account data
    $new_account_data = $original_account;
    unset($new_account_data['id']); // Remove ID to create a new entry
    $new_account_data['budget_year'] = $new_budget_year;
    $new_account_data['year_end_estimate'] = 0.00; // Reset for the new year
    $new_account_data['created_at'] = current_time('mysql');

    // 3. Insert the new account and get its ID
    $wpdb->insert($table_accounts, $new_account_data);
    $new_account_id = $wpdb->insert_id;

    // 4. Get original line items
    $original_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_items WHERE account_id = %d", $original_account_id), ARRAY_A);

    // 5. Duplicate line items for the new account
    if ($original_items) {
        foreach ($original_items as $item) {
            $new_item_data = $item;
            unset($new_item_data['id']); // Remove original item ID
            $new_item_data['account_id'] = $new_account_id;
            $new_item_data['purchased_date'] = null; // Reset purchase date
            $new_item_data['is_approved'] = 0; // Reset approval status
            $wpdb->insert($table_items, $new_item_data);
        }
    }

    // Redirect to the list page with a success message
    wp_redirect(admin_url('admin.php?page=budget-planner&message=3'));
    exit;
}

function bpp_handle_bulk_duplication_submission() {
    global $wpdb;
    $source_year = isset($_POST['source_year']) ? intval($_POST['source_year']) : 0;
    $new_year = isset($_POST['new_year']) ? intval($_POST['new_year']) : 0;

    if (!$source_year || !$new_year || $source_year == $new_year) {
        wp_die('Invalid source or destination year provided for bulk duplication.');
    }

    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    $table_items = $wpdb->prefix . 'bpp_line_items';

    // Get all accounts from the source year
    $source_accounts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_accounts WHERE budget_year = %d", $source_year), ARRAY_A);

    if (empty($source_accounts)) {
        wp_redirect(admin_url('admin.php?page=budget-planner&message=error_no_accounts_found'));
        exit;
    }

    foreach ($source_accounts as $original_account) {
        $original_account_id = $original_account['id'];
        
        // 1. Prepare new account data
        $new_account_data = $original_account;
        unset($new_account_data['id']);
        $new_account_data['budget_year'] = $new_year;
        $new_account_data['year_end_estimate'] = 0.00;
        $new_account_data['created_at'] = current_time('mysql');

        // 2. Insert the new account
        $wpdb->insert($table_accounts, $new_account_data);
        $new_account_id = $wpdb->insert_id;

        // 3. Get original line items
        $original_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_items WHERE account_id = %d", $original_account_id), ARRAY_A);

    // 4. Duplicate line items
        if ($original_items) {
            foreach ($original_items as $item) {
                $new_item_data = $item;
                unset($new_item_data['id']);
                $new_item_data['account_id'] = $new_account_id;
                $new_item_data['purchased_date'] = null;
                $new_item_data['is_approved'] = 0;
                $wpdb->insert($table_items, $new_item_data);
            }
        }
    }

    wp_redirect(admin_url('admin.php?page=budget-planner&message=4'));
    exit;
}

function bpp_handle_location_duplication_submission() {
    global $wpdb;
    $source_year = isset($_POST['source_year']) ? intval($_POST['source_year']) : 0;
    $source_location = isset($_POST['source_location']) ? sanitize_text_field($_POST['source_location']) : '';
    $dest_location = isset($_POST['dest_location']) ? sanitize_text_field($_POST['dest_location']) : '';

    if (!$source_year || !$source_location || !$dest_location || $source_location === $dest_location) {
        wp_die('Invalid parameters for location duplication. Please ensure source and destination locations are different.');
    }

    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    $table_items = $wpdb->prefix . 'bpp_line_items';
    $table_locations = $wpdb->prefix . 'bpp_locations';
    
    // Add the new destination location to the locations table if it doesn't exist
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_locations WHERE name = %s", $dest_location));
    if ($exists == 0) {
        $wpdb->insert($table_locations, ['name' => $dest_location], ['%s']);
    }

    // Get all accounts from the source year and location
    $source_accounts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_accounts WHERE budget_year = %d AND location_name = %s", $source_year, $source_location), ARRAY_A);

    if (empty($source_accounts)) {
        wp_redirect(admin_url('admin.php?page=budget-planner&message=error_no_accounts_found'));
        exit;
    }

    foreach ($source_accounts as $original_account) {
        $original_account_id = $original_account['id'];
        
        $new_account_data = $original_account;
        unset($new_account_data['id']);
        $new_account_data['location_name'] = $dest_location;
        $new_account_data['year_end_estimate'] = 0.00;
        $new_account_data['created_at'] = current_time('mysql');

        $wpdb->insert($table_accounts, $new_account_data);
        $new_account_id = $wpdb->insert_id;

        $original_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_items WHERE account_id = %d", $original_account_id), ARRAY_A);

        if ($original_items) {
            foreach ($original_items as $item) {
                $new_item_data = $item;
                unset($new_item_data['id']);
                $new_item_data['account_id'] = $new_account_id;
                $new_item_data['purchased_date'] = null;
                $new_item_data['is_approved'] = 0;
                $wpdb->insert($table_items, $new_item_data);
            }
        }
    }

    wp_redirect(admin_url('admin.php?page=budget-planner&message=5'));
    exit;
}


// =============================================================================
// Admin Page Renderers
// =============================================================================

function bpp_render_list_table() {
    global $wpdb;
    
    $table_accounts = $wpdb->prefix . 'bpp_accounts';
    
    // --- Sorting ---
    $sortable_columns = ['account_title', 'budget_year', 'location_name', 'proposed_budget', 'account_number'];
    $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $sortable_columns) ? $_GET['orderby'] : 'account_number';
    $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'ASC';
    
    // --- Filtering ---
    $where_clauses = [];
    $filter_year = !empty($_GET['filter_year']) ? intval($_GET['filter_year']) : '';
    $filter_location = !empty($_GET['filter_location']) ? sanitize_text_field($_GET['filter_location']) : '';

    if ($filter_year) {
        $where_clauses[] = $wpdb->prepare("budget_year = %d", $filter_year);
    }
    if ($filter_location) {
        $where_clauses[] = $wpdb->prepare("location_name = %s", $filter_location);
    }
    
    $sql = "SELECT * FROM $table_accounts";
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    $sql .= " ORDER BY " . esc_sql($orderby) . " " . esc_sql($order);

    $accounts = $wpdb->get_results($sql);
    
    // Data for filter dropdowns
    $years = $wpdb->get_col("SELECT DISTINCT budget_year FROM $table_accounts ORDER BY budget_year DESC");
    $locations = $wpdb->get_col("SELECT DISTINCT name FROM {$wpdb->prefix}bpp_locations ORDER BY name ASC");

    ?>
    <div style="margin: 20px 0; padding: 15px; background-color: #fff; border: 1px solid #ccd0d4; border-radius: 4px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <h4>Tools</h4>
             <form method="POST" style="margin-top: 10px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <input type="hidden" name="page" value="budget-planner" />
                <?php wp_nonce_field('bpp_duplicate_bulk_action', 'bpp_duplicate_bulk_nonce'); ?>
                <strong>Duplicate Entire Year:</strong><br>
                <select name="source_year" required>
                    <option value="">Select Source Year</option>
                    <?php foreach($years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                    <?php endforeach; ?>
                </select>
                to
                <input type="number" name="new_year" placeholder="New Year (e.g., <?php echo date('Y') + 1; ?>)" required>
                <button type="submit" class="button-primary">Duplicate Year</button>
            </form>
            
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="page" value="budget-planner" />
                <?php wp_nonce_field('bpp_duplicate_location_action', 'bpp_duplicate_location_nonce'); ?>
                <strong>Duplicate by Location for Same Year:</strong><br>
                From Year:
                <select name="source_year" required>
                    <option value="">Select Year</option>
                    <?php foreach($years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                    <?php endforeach; ?>
                </select>
                <br>
                From Location:
                <select name="source_location" required>
                     <option value="">Select Source Location</option>
                    <?php foreach($locations as $location): ?>
                    <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html($location); ?></option>
                    <?php endforeach; ?>
                </select>
                <br>
                To Location:
                <input list="locations-list" name="dest_location" class="regular-text" placeholder="Type new or select existing" required>
                <datalist id="locations-list">
                    <?php foreach ($locations as $location) : ?>
                        <option value="<?php echo esc_attr($location); ?>">
                    <?php endforeach; ?>
                </datalist>
                <button type="submit" class="button-primary">Duplicate Location</button>
            </form>
            <hr>
            <form method="GET">
                <input type="hidden" name="page" value="budget-planner" />
                <strong>Filter Accounts:</strong><br>
                <select name="filter_year">
                    <option value="">All Years</option>
                    <?php foreach($years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>" <?php selected($filter_year, $year); ?>><?php echo esc_html($year); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_location">
                     <option value="">All Locations</option>
                    <?php foreach($locations as $location): ?>
                    <option value="<?php echo esc_attr($location); ?>" <?php selected($filter_location, $location); ?>><?php echo esc_html($location); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="?page=budget-planner" class="button">Clear</a>
            </form>
        </div>
        <div>
            <h4>Bulk Export</h4>
             <form method="GET" style="margin-top: 10px;">
                <input type="hidden" name="page" value="budget-planner" />
                <input type="hidden" name="action" value="generate_pdf" />
                <input type="hidden" name="account_id" value="all" />
                <strong>Generate Bulk PDF for Year:</strong><br>
                <select name="budget_year" required>
                    <option value="">Select Year to Export</option>
                     <?php foreach($years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button-primary">Generate Bulk PDF</button>
            </form>
        </div>
    </div>

    <div style="max-height: 75vh; overflow-y: auto;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php
                    $columns = [
                        'account_title' => 'Account Title',
                        'account_number' => 'Account Number',
                        'location_name' => 'Location',
                        'budget_year' => 'Budget Year',
                        'proposed_budget' => 'Proposed Budget'
                    ];
                    foreach ($columns as $slug => $title) {
                        $current_order = ($orderby === $slug && $order === 'ASC') ? 'DESC' : 'ASC';
                        $sort_link = "?page=budget-planner&orderby={$slug}&order={$current_order}";
                        if ($filter_year) $sort_link .= "&filter_year={$filter_year}";
                        if ($filter_location) $sort_link .= "&filter_location={$filter_location}";
                        
                        $aria_sort = ($orderby === $slug) ? ($order === 'ASC' ? 'ascending' : 'descending') : 'none';

                        echo '<th scope="col" class="manage-column sortable ' . ($order === 'ASC' ? 'asc' : 'desc') . '" aria-sort="' . $aria_sort . '">';
                        echo '<a href="' . esc_url($sort_link) . '">';
                        echo '<span>' . esc_html($title) . '</span>';
                        echo '<span class="sorting-indicator"></span>';
                        echo '</a></th>';
                    }
                    ?>
                    <th scope="col">Prepared By</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accounts) : ?>
                    <?php foreach ($accounts as $account) : ?>
                        <tr>
                            <td class="title column-title has-row-actions column-primary">
                                <strong><a href="?page=budget-planner&action=edit&account_id=<?php echo $account->id; ?>"><?php echo esc_html($account->account_title); ?></a></strong>
                            </td>
                            <td><?php echo esc_html($account->account_number); ?></td>
                            <td><?php echo isset($account->location_name) ? esc_html($account->location_name) : ''; ?></td>
                            <td><?php echo esc_html($account->budget_year); ?></td>
                            <td>$<?php echo number_format($account->proposed_budget, 2); ?></td>
                            <td><?php echo esc_html($account->prepared_by); ?></td>
                            <td>
                                <a href="?page=budget-planner&action=edit&account_id=<?php echo $account->id; ?>" class="button">Edit</a>
                                <a href="?page=budget-planner&action=duplicate&account_id=<?php echo $account->id; ?>" class="button">Duplicate</a>
                                <a href="?page=budget-planner&action=generate_pdf&account_id=<?php echo $account->id; ?>" class="button" target="_blank">View PDF</a>
                                <a href="<?php echo wp_nonce_url('?page=budget-planner&action=delete&account_id=' . $account->id, 'bpp_delete_account_' . $account->id); ?>" class="button button-danger" onclick="return confirm('Are you sure you want to delete this account and all its items?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">No accounts found matching your criteria. <a href="?page=budget-planner">Clear filters</a> or <a href="?page=budget-planner&action=add">add a new account</a>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function bpp_render_add_edit_form() {
    global $wpdb;
    $account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
    $account = null;
    $line_items = [];
    $staff_items = [];

    if ($account_id > 0) {
        $account = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_accounts WHERE id = %d", $account_id));
        $all_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_line_items WHERE account_id = %d ORDER BY id ASC", $account_id));
        // Separate items by type
        foreach ($all_items as $item) {
            if ($item->item_type === 'staffing') {
                $staff_items[] = $item;
            } else {
                $line_items[] = $item;
            }
        }
    }
    
    // Get all unique locations for the datalist
    $locations = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}bpp_locations ORDER BY name ASC");
    
    // Default values for new form
    $budget_year = $account ? $account->budget_year : date('Y') + 1;
    $account_title = $account ? $account->account_title : '';
    $account_number = $account ? $account->account_number : '';
    $location_name = $account ? $account->location_name : '';
    $year_end_estimate = $account ? $account->year_end_estimate : '0.00';
    $proposed_budget = $account ? $account->proposed_budget : '0.00';
    $prepared_by = $account ? $account->prepared_by : wp_get_current_user()->display_name;
    ?>
    <form method="post">
        <input type="hidden" name="account_id" value="<?php echo $account_id; ?>">
        <?php wp_nonce_field('bpp_save_account_action', 'bpp_save_account_nonce'); ?>

        <h2><?php echo $account_id ? 'Edit Account' : 'Add New Account'; ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="budget_year">Budget Year</label></th>
                    <td><input name="budget_year" type="number" id="budget_year" value="<?php echo esc_attr($budget_year); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="account_title">Account Title</label></th>
                    <td><input name="account_title" type="text" id="account_title" value="<?php echo esc_attr($account_title); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="account_number">Account Number</label></th>
                    <td><input name="account_number" type="text" id="account_number" value="<?php echo esc_attr($account_number); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_name">Location Name</label></th>
                    <td>
                        <input list="locations-list" name="location_name" id="location_name" value="<?php echo esc_attr($location_name); ?>" class="regular-text" placeholder="Type to search or add new">
                        <datalist id="locations-list">
                            <?php foreach ($locations as $location) : ?>
                                <option value="<?php echo esc_attr($location->name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="year_end_estimate">Year End Estimate</label></th>
                    <td><input name="year_end_estimate" type="number" step="0.01" id="year_end_estimate" value="<?php echo esc_attr($year_end_estimate); ?>" class="regular-text"></td>
                </tr>
                 <tr>
                    <th scope="row"><label for="proposed_budget">Proposed Budget Amount for Next Year</label></th>
                    <td>
                        <input name="proposed_budget" type="number" step="0.01" id="proposed_budget" value="<?php echo esc_attr($proposed_budget); ?>" class="regular-text">
                        <span style="margin-left: 10px; vertical-align: middle;">
                            <strong>Total Item Cost:</strong> <span id="line_item_cost_sum">$0.00</span>
                        </span>
                        <button type="button" id="use_item_sum_button" class="button" style="margin-left: 5px; vertical-align: middle;">Use Item Sum</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="prepared_by">Prepared By</label></th>
                    <td><input name="prepared_by" type="text" id="prepared_by" value="<?php echo esc_attr($prepared_by); ?>" class="regular-text" required></td>
                </tr>
            </tbody>
        </table>

        <h2 style="margin-top: 40px;">Standard Line Items</h2>
        <table id="line-items-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Item (Name & Link)</th>
                    <th style="width: 5%;">Qty</th>
                    <th style="width: 10%;">Price</th>
                    <th style="width: 10%;">Cost</th>
                    <th style="width: 25%;">Comment</th>
                    <th style="width: 10%;">Purchased Date</th>
                    <th style="width: 10%;">Approval</th>
                    <th style="width: 5%;">Action</th>
                </tr>
            </thead>
            <tbody id="line-items-container">
                <?php if (!empty($line_items)) : foreach ($line_items as $item) : ?>
                    <tr class="line-item-row">
                        <td>
                            <input type="hidden" name="line_items[<?php echo esc_attr($item->id); ?>][id]" value="<?php echo esc_attr($item->id); ?>">
                            <input type="text" name="line_items[<?php echo esc_attr($item->id); ?>][item_name]" placeholder="Item Name" value="<?php echo esc_attr($item->item_name); ?>" style="width:100%;" required>
                            <input type="url" name="line_items[<?php echo esc_attr($item->id); ?>][item_link]" placeholder="https://example.com" value="<?php echo esc_attr($item->item_link); ?>" style="width:100%;">
                        </td>
                        <td><input type="number" name="line_items[<?php echo esc_attr($item->id); ?>][quantity]" class="quantity" value="<?php echo esc_attr($item->quantity); ?>" style="width:100%;"></td>
                        <td><input type="number" step="0.01" name="line_items[<?php echo esc_attr($item->id); ?>][price]" class="price" value="<?php echo esc_attr($item->price); ?>" style="width:100%;"></td>
                        <td><span class="cost">$<?php echo number_format($item->quantity * $item->price, 2); ?></span></td>
                        <td><textarea name="line_items[<?php echo esc_attr($item->id); ?>][comment]" style="width:100%;"><?php echo esc_textarea($item->comment); ?></textarea></td>
                        <td><input type="date" name="line_items[<?php echo esc_attr($item->id); ?>][purchased_date]" value="<?php echo esc_attr($item->purchased_date); ?>"></td>
                        <td>
                            <select name="line_items[<?php echo esc_attr($item->id); ?>][is_approved]">
                                <option value="1" <?php selected($item->is_approved, 1); ?>>Approved</option>
                                <option value="0" <?php selected($item->is_approved, 0); ?>>Not Approved</option>
                            </select>
                        </td>
                        <td><button type="button" class="button button-danger remove-line-item">X</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                <!-- Placeholder for new items -->
                 <tr class="line-item-row" <?php if (!empty($line_items)) echo 'style="display:none;"';?>>
                    <td>
                        <input type="text" name="line_items[new_std_1][item_name]" placeholder="Item Name" style="width:100%;">
                        <input type="url" name="line_items[new_std_1][item_link]" placeholder="https://example.com" style="width:100%;">
                    </td>
                    <td><input type="number" name="line_items[new_std_1][quantity]" class="quantity" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="line_items[new_std_1][price]" class="price" value="0.00" style="width:100%;"></td>
                    <td><span class="cost">$0.00</span></td>
                    <td><textarea name="line_items[new_std_1][comment]" style="width:100%;"></textarea></td>
                    <td><input type="date" name="line_items[new_std_1][purchased_date]" value=""></td>
                    <td>
                        <select name="line_items[new_std_1][is_approved]">
                            <option value="1">Approved</option>
                            <option value="0" selected>Not Approved</option>
                        </select>
                    </td>
                    <td><button type="button" class="button button-danger remove-line-item">X</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" id="add-line-item" class="button" style="margin-top: 10px;">+ Add Item</button>

        <h2 style="margin-top: 40px;">Staffing Costs</h2>
        <table id="staff-items-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Position / Role</th>
                    <th style="width: 7%;">Weeks</th>
                    <th style="width: 7%;">Days/Wk</th>
                    <th style="width: 7%;">Hours/Day</th>
                    <th style="width: 7%;"># Staff</th>
                    <th style="width: 10%;">$/Hour Rate</th>
                    <th style="width: 10%;">Cost</th>
                    <th style="width: 17%;">Comment</th>
                    <th style="width: 10%;">Approval</th>
                    <th style="width: 5%;">Action</th>
                </tr>
            </thead>
            <tbody id="staff-items-container">
                <?php if (!empty($staff_items)) : foreach ($staff_items as $item) : ?>
                    <tr class="staff-item-row">
                        <td>
                            <input type="hidden" name="staff_items[<?php echo esc_attr($item->id); ?>][id]" value="<?php echo esc_attr($item->id); ?>">
                            <input type="text" name="staff_items[<?php echo esc_attr($item->id); ?>][item_name]" placeholder="e.g., Lifeguard" value="<?php echo esc_attr($item->item_name); ?>" style="width:100%;">
                        </td>
                        <td><input type="number" name="staff_items[<?php echo esc_attr($item->id); ?>][weeks]" class="staff-weeks" value="<?php echo esc_attr($item->weeks); ?>" style="width:100%;"></td>
                        <td><input type="number" name="staff_items[<?php echo esc_attr($item->id); ?>][days]" class="staff-days" value="<?php echo esc_attr($item->days); ?>" style="width:100%;"></td>
                        <td><input type="number" step="0.01" name="staff_items[<?php echo esc_attr($item->id); ?>][hours]" class="staff-hours" value="<?php echo esc_attr($item->hours); ?>" style="width:100%;"></td>
                        <td><input type="number" name="staff_items[<?php echo esc_attr($item->id); ?>][staff_count]" class="staff-count" value="<?php echo esc_attr($item->staff_count); ?>" style="width:100%;"></td>
                        <td><input type="number" step="0.01" name="staff_items[<?php echo esc_attr($item->id); ?>][rate]" class="staff-rate" value="<?php echo esc_attr($item->rate); ?>" style="width:100%;"></td>
                        <td><span class="staff-cost">$0.00</span></td>
                        <td><textarea name="staff_items[<?php echo esc_attr($item->id); ?>][comment]" style="width:100%;"><?php echo esc_textarea($item->comment); ?></textarea></td>
                        <td>
                            <select name="staff_items[<?php echo esc_attr($item->id); ?>][is_approved]">
                                <option value="1" <?php selected($item->is_approved, 1); ?>>Approved</option>
                                <option value="0" <?php selected($item->is_approved, 0); ?>>Not Approved</option>
                            </select>
                        </td>
                        <td><button type="button" class="button button-danger remove-staff-item">X</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                 <tr class="staff-item-row" <?php if (!empty($staff_items)) echo 'style="display:none;"';?>>
                    <td><input type="text" name="staff_items[new_staff_1][item_name]" placeholder="e.g., Lifeguard" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[new_staff_1][weeks]" class="staff-weeks" value="1" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[new_staff_1][days]" class="staff-days" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="staff_items[new_staff_1][hours]" class="staff-hours" value="1" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[new_staff_1][staff_count]" class="staff-count" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="staff_items[new_staff_1][rate]" class="staff-rate" value="0.00" style="width:100%;"></td>
                    <td><span class="staff-cost">$0.00</span></td>
                    <td><textarea name="staff_items[new_staff_1][comment]" style="width:100%;"></textarea></td>
                    <td>
                        <select name="staff_items[new_staff_1][is_approved]">
                            <option value="1">Approved</option>
                            <option value="0" selected>Not Approved</option>
                        </select>
                    </td>
                    <td><button type="button" class="button button-danger remove-staff-item">X</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" id="add-staff-item" class="button" style="margin-top: 10px;">+ Add Staffing Cost</button>
        
        <?php submit_button('Save Account'); ?>
    </form>
    
    <!-- JavaScript for dynamic rows and calculations -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const proposedBudgetInput = document.getElementById('proposed_budget');
            const costSumSpan = document.getElementById('line_item_cost_sum');
            const useSumButton = document.getElementById('use_item_sum_button');

            const formatCurrency = (number) => {
                 return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
            };

            const updateTotalCostSum = () => {
                let totalCost = 0;
                // Sum standard items
                document.querySelectorAll('.line-item-row').forEach(row => {
                    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                    const price = parseFloat(row.querySelector('.price').value) || 0;
                    totalCost += quantity * price;
                });
                // Sum staffing items
                 document.querySelectorAll('.staff-item-row').forEach(row => {
                    const weeks = parseFloat(row.querySelector('.staff-weeks').value) || 0;
                    const days = parseFloat(row.querySelector('.staff-days').value) || 0;
                    const hours = parseFloat(row.querySelector('.staff-hours').value) || 0;
                    const count = parseFloat(row.querySelector('.staff-count').value) || 0;
                    const rate = parseFloat(row.querySelector('.staff-rate').value) || 0;
                    totalCost += weeks * days * hours * count * rate;
                });

                costSumSpan.textContent = formatCurrency(totalCost);
                return totalCost;
            };

            // --- Standard Item Logic ---
            const standardContainer = document.getElementById('line-items-container');
            const addStandardButton = document.getElementById('add-line-item');

            const updateStandardCost = (row) => {
                const quantity = row.querySelector('.quantity').value || 0;
                const price = row.querySelector('.price').value || 0;
                const cost = parseFloat(quantity) * parseFloat(price);
                row.querySelector('.cost').textContent = formatCurrency(cost);
                updateTotalCostSum();
            };

            const addStandardRowListeners = (row) => {
                 row.querySelector('.remove-line-item').addEventListener('click', function() {
                    const row = this.closest('tr');
                    row.style.display = 'none';
                    // Clear the name on ALL form elements in the row to prevent submission
                    row.querySelectorAll('input, select, textarea').forEach(el => {
                        el.name = '';
                    });
                    updateTotalCostSum();
                });

                row.querySelectorAll('.quantity, .price').forEach(input => {
                    input.addEventListener('input', () => updateStandardCost(row));
                });
            };
            
            standardContainer.querySelectorAll('tr').forEach(row => addStandardRowListeners(row));

            addStandardButton.addEventListener('click', function() {
                const placeholder = standardContainer.querySelector('tr[style*="display:none"]');
                if(placeholder) {
                    placeholder.style.display = '';
                    const firstInput = placeholder.querySelector('input[type=text]');
                    // Restore name if it was cleared
                    if(!firstInput.name) {
                        const newIndex = 's' + new Date().getTime();
                        placeholder.querySelectorAll('[name]').forEach(el => {
                            el.name = el.name.replace(/\[new_std_\d+\]/, `[${newIndex}]`);
                        });
                    }
                    return;
                }

                const newIndex = 's' + new Date().getTime(); // Unique index
                const newRow = document.createElement('tr');
                newRow.classList.add('line-item-row');
                newRow.innerHTML = `
                    <td>
                        <input type="text" name="line_items[${newIndex}][item_name]" placeholder="Item Name" style="width:100%;">
                        <input type="url" name="line_items[${newIndex}][item_link]" placeholder="https://example.com" style="width:100%;">
                    </td>
                    <td><input type="number" name="line_items[${newIndex}][quantity]" class="quantity" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="line_items[${newIndex}][price]" class="price" value="0.00" style="width:100%;"></td>
                    <td><span class="cost">$0.00</span></td>
                    <td><textarea name="line_items[${newIndex}][comment]" style="width:100%;"></textarea></td>
                    <td><input type="date" name="line_items[${newIndex}][purchased_date]"></td>
                    <td>
                        <select name="line_items[${newIndex}][is_approved]">
                            <option value="1">Approved</option>
                            <option value="0" selected>Not Approved</option>
                        </select>
                    </td>
                    <td><button type="button" class="button button-danger remove-line-item">X</button></td>
                `;
                standardContainer.appendChild(newRow);
                addStandardRowListeners(newRow);
                updateTotalCostSum();
            });


            // --- Staffing Item Logic ---
            const staffContainer = document.getElementById('staff-items-container');
            const addStaffButton = document.getElementById('add-staff-item');

            const updateStaffCost = (row) => {
                const weeks = parseFloat(row.querySelector('.staff-weeks').value) || 0;
                const days = parseFloat(row.querySelector('.staff-days').value) || 0;
                const hours = parseFloat(row.querySelector('.staff-hours').value) || 0;
                const count = parseFloat(row.querySelector('.staff-count').value) || 0;
                const rate = parseFloat(row.querySelector('.staff-rate').value) || 0;
                const cost = weeks * days * hours * count * rate;
                row.querySelector('.staff-cost').textContent = formatCurrency(cost);
                updateTotalCostSum();
            };
            
            const addStaffRowListeners = (row) => {
                row.querySelector('.remove-staff-item').addEventListener('click', function() {
                    const row = this.closest('tr');
                    row.style.display = 'none';
                    // Clear the name on ALL form elements in the row to prevent submission
                    row.querySelectorAll('input, select, textarea').forEach(el => {
                        el.name = '';
                    });
                    updateTotalCostSum();
                });
                const inputs = ['.staff-weeks', '.staff-days', '.staff-hours', '.staff-count', '.staff-rate'];
                row.querySelectorAll(inputs.join(', ')).forEach(input => {
                    input.addEventListener('input', () => updateStaffCost(row));
                });
            };

            staffContainer.querySelectorAll('tr').forEach(row => {
                addStaffRowListeners(row);
                updateStaffCost(row); // Also calculate initial cost
            });

            addStaffButton.addEventListener('click', function() {
                 const placeholder = staffContainer.querySelector('tr[style*="display:none"]');
                if(placeholder) {
                    placeholder.style.display = '';
                     const firstInput = placeholder.querySelector('input[type=text]');
                    if(!firstInput.name) {
                        const newIndex = 't' + new Date().getTime();
                        placeholder.querySelectorAll('[name]').forEach(el => {
                            el.name = el.name.replace(/\[new_staff_\d+\]/, `[${newIndex}]`);
                        });
                    }
                    return;
                }

                const newIndex = 't' + new Date().getTime(); // Unique index
                const newRow = document.createElement('tr');
                newRow.classList.add('staff-item-row');
                newRow.innerHTML = `
                    <td><input type="text" name="staff_items[${newIndex}][item_name]" placeholder="e.g., Lifeguard" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[${newIndex}][weeks]" class="staff-weeks" value="1" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[${newIndex}][days]" class="staff-days" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="staff_items[${newIndex}][hours]" class="staff-hours" value="1" style="width:100%;"></td>
                    <td><input type="number" name="staff_items[${newIndex}][staff_count]" class="staff-count" value="1" style="width:100%;"></td>
                    <td><input type="number" step="0.01" name="staff_items[${newIndex}][rate]" class="staff-rate" value="0.00" style="width:100%;"></td>
                    <td><span class="staff-cost">$0.00</span></td>
                    <td><textarea name="staff_items[${newIndex}][comment]" style="width:100%;"></textarea></td>
                    <td>
                        <select name="staff_items[${newIndex}][is_approved]">
                            <option value="1">Approved</option>
                            <option value="0" selected>Not Approved</option>
                        </select>
                    </td>
                    <td><button type="button" class="button button-danger remove-staff-item">X</button></td>
                `;
                staffContainer.appendChild(newRow);
                addStaffRowListeners(newRow);
                updateTotalCostSum();
            });

            // --- General ---
            useSumButton.addEventListener('click', function() {
                const totalCost = updateTotalCostSum(); // Recalculate to be sure
                proposedBudgetInput.value = totalCost.toFixed(2);
            });

            // Initial calculation on page load
            updateTotalCostSum();
        });
    </script>
    <?php
}

function bpp_render_duplicate_form() {
    global $wpdb;
    $account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
    
    if (!$account_id) {
        echo '<div class="notice notice-error"><p>No account specified for duplication.</p></div>';
        return;
    }

    $account = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_accounts WHERE id = %d", $account_id));

    if (!$account) {
        echo '<div class="notice notice-error"><p>The specified account could not be found.</p></div>';
        return;
    }

    $new_year_suggestion = date('Y') + 1;
    ?>
    <h2>Duplicate Account</h2>
    <p>You are about to duplicate the following account. Please specify the new budget year for the copy.</p>
    
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">Original Account Title</th>
                <td><?php echo esc_html($account->account_title); ?></td>
            </tr>
            <tr>
                <th scope="row">Original Budget Year</th>
                <td><?php echo esc_html($account->budget_year); ?></td>
            </tr>
        </tbody>
    </table>

    <form method="post" style="margin-top: 20px;">
        <input type="hidden" name="original_account_id" value="<?php echo esc_attr($account_id); ?>">
        <?php wp_nonce_field('bpp_duplicate_account_action', 'bpp_duplicate_account_nonce'); ?>
        
        <table class="form-table">
             <tbody>
                <tr>
                    <th scope="row"><label for="new_budget_year"><strong>New Budget Year</strong></label></th>
                    <td>
                        <input name="new_budget_year" type="number" id="new_budget_year" value="<?php echo esc_attr($new_year_suggestion); ?>" class="regular-text" required>
                        <p class="description">All line items will be copied, but their "Purchased Date" and "Approval" status will be reset.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button('Confirm & Duplicate Account'); ?>
    </form>
    <?php
}


// =============================================================================
// PDF Generation
// =============================================================================

function bpp_generate_pdf_handler() {
    $fpdf_path = plugin_dir_path(__FILE__) . 'fpdf.php';
    if (!file_exists($fpdf_path)) {
        // The admin notice in bpp_admin_page_router is the primary warning.
        // This wp_die is a fallback if someone tries to access the URL directly.
        wp_die("FPDF library not found. Please follow the installation instructions.");
    }
    require_once($fpdf_path);

    global $wpdb;
    $account_id_param = $_GET['account_id'] ?? '0';
    $budget_year_param = $_GET['budget_year'] ?? '0';

    $accounts_to_process = [];

    if ($account_id_param === 'all' && $budget_year_param) {
        // Bulk export for a year
        $year = intval($budget_year_param);
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_accounts WHERE budget_year = %d ORDER BY account_number ASC", $year));
        if ($results) {
            $accounts_to_process = $results;
        }
    } else {
        // Single account export
        $id = intval($account_id_param);
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_accounts WHERE id = %d", $id));
        if ($result) {
            $accounts_to_process[] = $result;
        }
    }
    
    if (empty($accounts_to_process)) {
         wp_die("No account found to generate PDF.");
    }

    // Custom PDF Class with Header and Footer
    class PDF extends FPDF {
        function Header() {
            // This space is intentionally left blank to allow for a custom title per page.
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Helvetica','I',8);
            $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
        }

        // FPDF extension to calculate number of lines for a MultiCell
        function NbLines($w, $txt) {
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0)
                $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r", '', $txt);
            $nb = strlen($s);
            if ($nb > 0 and $s[$nb - 1] == "\n")
                $nb--;
            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $nl = 1;
            while ($i < $nb) {
                $c = $s[$i];
                if ($c == "\n") {
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                    continue;
                }
                if ($c == ' ')
                    $sep = $i;
                $l += $cw[$c];
                if ($l > $wmax) {
                    if ($sep == -1) {
                        if ($i == $j)
                            $i++;
                    } else
                        $i = $sep + 1;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                } else
                    $i++;
            }
            return $nl;
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();

    foreach ($accounts_to_process as $account) {
        $pdf->AddPage();
        
        // Page Title
        $pdf->SetFont('Helvetica','B',14);
        $pdf->Cell(0, 10, 'Budget: ' . $account->budget_year, 0, 1, 'C');
        $pdf->Ln(5);

        // Account Details
		$pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(40, 8, 'Account Number: ', 0, 0);
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(55, 8, $account->account_number, 0, 0);

        $pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(35, 8, 'Account Title: ', 0, 0);
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(60, 8, $account->account_title, 0, 1);
        
        $pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(40, 8, 'Year End Estimate: ', 0, 0);
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(55, 8, '$' . number_format($account->year_end_estimate, 2), 0, 0);

        $pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(35, 8, 'Proposed Budget: ', 0, 0);
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(60, 8, '$' . number_format($account->proposed_budget, 2), 0, 1);
        
        $pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(40, 8, 'Location Name: ', 0, 0);
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(55, 8, $account->location_name, 0, 1);
        $pdf->Ln(5);
        
        // Line Items Data
        $all_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bpp_line_items WHERE account_id = %d ORDER BY id ASC", $account->id));
        $standard_items = [];
        $staffing_items = [];
        foreach ($all_items as $item) {
            if ($item->item_type === 'staffing') {
                $staffing_items[] = $item;
            } else {
                $standard_items[] = $item;
            }
        }
        $grand_total_cost = 0;

        // Standard Items Table
        if (!empty($standard_items)) {
            $pdf->SetFont('Helvetica','B',12);
            $pdf->Cell(0, 10, 'Standard Items', 0, 1);
            $pdf->SetFont('Helvetica','B',10);
            $pdf->SetFillColor(230,230,230);
            $pdf->Cell(50, 7, 'Item', 1, 0, 'L', true);
            $pdf->Cell(15, 7, 'Qty', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Price', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Cost', 1, 0, 'C', true);
            $pdf->Cell(75, 7, 'Details', 1, 1, 'L', true);

            $pdf->SetFont('Helvetica','',9);
            foreach($standard_items as $item){
                $cost = $item->quantity * $item->price;
                $grand_total_cost += $cost;
                $line_height = 6;
                $item_text = $item->item_name . ($item->item_link ? "\n(" . $item->item_link . ")" : '');
                $details_text = "Approval: " . ($item->is_approved ? 'Yes' : 'No') . ($item->purchased_date && $item->purchased_date != '0000-00-00' ? "\nPurchased: " . date("m/d/Y", strtotime($item->purchased_date)) : '') . ($item->comment ? "\nComment: " . $item->comment : '');
                $item_lines = $pdf->NbLines(50, $item_text);
                $details_lines = $pdf->NbLines(75, $details_text);
                $row_height = max(1, $item_lines, $details_lines) * $line_height;
                $x_start = $pdf->GetX();
                $y_start = $pdf->GetY();
                $pdf->Cell(50, $row_height, '', 1, 0); $pdf->Cell(15, $row_height, '', 1, 0); $pdf->Cell(25, $row_height, '', 1, 0); $pdf->Cell(25, $row_height, '', 1, 0); $pdf->Cell(75, $row_height, '', 1, 1);
                $pdf->SetXY($x_start, $y_start);
                $pdf->MultiCell(50, $line_height, $item_text, 0, 'L');
                $pdf->SetXY($x_start + 50, $y_start);
                $pdf->Cell(15, $line_height, $item->quantity, 0, 0, 'C');
                $pdf->SetXY($x_start + 65, $y_start);
                $pdf->Cell(25, $line_height, '$' . number_format($item->price, 2), 0, 0, 'R');
                $pdf->SetXY($x_start + 90, $y_start);
                $pdf->Cell(25, $line_height, '$' . number_format($cost, 2), 0, 0, 'R');
                $pdf->SetXY($x_start + 115, $y_start);
                $pdf->MultiCell(75, $line_height, $details_text, 0, 'L');
                $pdf->SetY($y_start + $row_height);
            }
            $pdf->Ln(5);
        }
        
        // Staffing Items Table
        if (!empty($staffing_items)) {
            $pdf->SetFont('Helvetica','B',12);
            $pdf->Cell(0, 10, 'Staffing Costs', 0, 1);
            $pdf->SetFont('Helvetica','B',10);
            $pdf->SetFillColor(230,230,230);
            $pdf->Cell(45, 7, 'Position/Role', 1, 0, 'L', true);
            $pdf->Cell(15, 7, 'Weeks', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'Days/Wk', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'Hrs/Day', 1, 0, 'C', true);
            $pdf->Cell(15, 7, '# Staff', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Rate', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Cost', 1, 0, 'C', true);
            $pdf->Cell(40, 7, 'Details', 1, 1, 'L', true);

            $pdf->SetFont('Helvetica','',9);
            foreach($staffing_items as $item){
                $cost = $item->weeks * $item->days * $item->hours * $item->rate * $item->staff_count;
                $grand_total_cost += $cost;
                $line_height = 6;
                $item_text = $item->item_name;
                $details_text = ($item->comment ? "Comment: " . $item->comment . "\n" : '') . "Approval: " . ($item->is_approved ? 'Yes' : 'No');
                $item_lines = $pdf->NbLines(45, $item_text);
                $details_lines = $pdf->NbLines(40, $details_text);
                $row_height = max(1, $item_lines, $details_lines) * $line_height;
                $x_start = $pdf->GetX();
                $y_start = $pdf->GetY();
                $pdf->Cell(45, $row_height, '', 1, 0); $pdf->Cell(15, $row_height, '', 1, 0); $pdf->Cell(15, $row_height, '', 1, 0); $pdf->Cell(15, $row_height, '', 1, 0); $pdf->Cell(15, $row_height, '', 1, 0); $pdf->Cell(20, $row_height, '', 1, 0); $pdf->Cell(25, $row_height, '', 1, 0); $pdf->Cell(40, $row_height, '', 1, 1);
                $pdf->SetXY($x_start, $y_start);
                $pdf->MultiCell(45, $line_height, $item_text, 0, 'L');
                $pdf->SetXY($x_start + 45, $y_start);
                $pdf->Cell(15, $line_height, $item->weeks, 0, 0, 'C');
                $pdf->SetXY($x_start + 60, $y_start);
                $pdf->Cell(15, $line_height, $item->days, 0, 0, 'C');
                $pdf->SetXY($x_start + 75, $y_start);
                $pdf->Cell(15, $line_height, $item->hours, 0, 0, 'C');
                $pdf->SetXY($x_start + 90, $y_start);
                $pdf->Cell(15, $line_height, $item->staff_count, 0, 0, 'C');
                $pdf->SetXY($x_start + 105, $y_start);
                $pdf->Cell(20, $line_height, '$' . number_format($item->rate, 2), 0, 0, 'R');
                $pdf->SetXY($x_start + 125, $y_start);
                $pdf->Cell(25, $line_height, '$' . number_format($cost, 2), 0, 0, 'R');
                $pdf->SetXY($x_start + 150, $y_start);
                $pdf->MultiCell(40, $line_height, $details_text, 0, 'L');
                $pdf->SetY($y_start + $row_height);
            }
            $pdf->Ln(5);
        }

        // Grand Total
        $pdf->SetFont('Helvetica','B',12);
        $pdf->Cell(165, 8, 'Total Estimated Cost for Account', 1, 0, 'R', true);
        $pdf->Cell(25, 8, '$' . number_format($grand_total_cost, 2), 1, 1, 'R', true);

        // Footer Information
        $pdf->Ln(20);
        $pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(35, 8, 'Prepared By: ', 0, 0, 'L');
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(60, 8, $account->prepared_by, 0, 0, 'L');
        
		$pdf->SetFont('Helvetica','B',10);
        $pdf->Cell(35, 8, 'Approved By: ', 0, 0, 'L');
		$pdf->SetFont('Helvetica','',10);
        $pdf->Cell(60, 8, '___________________________', 0, 1, 'L');
    }

    $pdf->Output('D', 'budget-report-' . date('Y-m-d') . '.pdf');
    exit;
}

