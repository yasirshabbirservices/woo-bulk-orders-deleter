<?php

/**
 * Plugin Name: WooCommerce Bulk Delete Orders
 * Description: Delete all WooCommerce orders in batches with progress tracking
 * Version: 1.0.0
 * Author: Yasir Shabbir
 * Author URI: https://yasirshabbir.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce_Bulk_Delete_Orders
{

    private $batch_size = 50; // Process 50 orders at a time
    private $log_file;

    public function __construct()
    {
        $this->log_file = WP_CONTENT_DIR . '/wc-bulk-delete-log.txt';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_delete_orders_batch', array($this, 'delete_orders_batch'));
        add_action('wp_ajax_get_orders_count', array($this, 'get_orders_count'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_admin_menu()
    {
        // Add to WooCommerce menu if it exists, otherwise add to Tools menu
        if (class_exists('WooCommerce') || function_exists('WC')) {
            add_submenu_page(
                'woocommerce',
                'Bulk Delete Orders',
                'Bulk Delete Orders',
                'manage_woocommerce',
                'wc-bulk-delete-orders',
                array($this, 'admin_page')
            );
        } else {
            add_submenu_page(
                'tools.php',
                'Bulk Delete Orders',
                'Bulk Delete Orders',
                'manage_options',
                'wc-bulk-delete-orders',
                array($this, 'admin_page')
            );
        }
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_wc-bulk-delete-orders' && $hook !== 'tools_page_wc-bulk-delete-orders') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'wcBulkDelete', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_bulk_delete_nonce')
        ));
    }

    public function admin_page()
    {
?>
<div class="wrap yasir-bulk-delete-wrap">
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap');

    .yasir-bulk-delete-wrap {
        font-family: 'Lato', sans-serif;
        background: #121212;
        color: #ffffff;
        padding: 20px;
        border-radius: 3px;
        margin: 20px 0;
    }

    .yasir-header {
        background: linear-gradient(135deg, #1e1e1e, #2a2a2a);
        padding: 30px;
        border-radius: 3px;
        border: 1px solid #333333;
        margin-bottom: 30px;
        text-align: center;
    }

    .yasir-header h1 {
        color: #16e791;
        font-size: 2.5em;
        margin: 0 0 10px 0;
        font-weight: 700;
    }

    .yasir-header .subtitle {
        color: #e0e0e0;
        font-size: 1.1em;
        margin: 0;
    }

    .yasir-header .brand-link {
        color: #16e791;
        text-decoration: none;
        font-weight: 400;
    }

    .yasir-header .brand-link:hover {
        text-decoration: underline;
    }

    .yasir-card {
        background: #1e1e1e;
        border: 1px solid #333333;
        border-radius: 3px;
        padding: 30px;
        margin-bottom: 20px;
    }

    .yasir-card h2 {
        color: #16e791;
        margin-top: 0;
        font-size: 1.5em;
    }

    .yasir-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .yasir-stat-card {
        background: #2a2a2a;
        border: 1px solid #444444;
        border-radius: 3px;
        padding: 20px;
        text-align: center;
    }

    .yasir-stat-number {
        font-size: 2em;
        font-weight: 700;
        color: #16e791;
        margin: 0 0 5px 0;
    }

    .yasir-stat-label {
        color: #e0e0e0;
        font-size: 0.9em;
        margin: 0;
    }

    .yasir-button {
        background: #16e791;
        color: #121212;
        border: none;
        padding: 15px 30px;
        border-radius: 3px;
        font-size: 1.1em;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Lato', sans-serif;
    }

    .yasir-button:hover {
        background: #14d182;
        transform: translateY(-2px);
    }

    .yasir-button:disabled {
        background: #6c757d;
        color: #e0e0e0;
        cursor: not-allowed;
        transform: none;
    }

    .yasir-button.danger {
        background: #dc3545;
        color: #ffffff;
    }

    .yasir-button.danger:hover {
        background: #c82333;
    }

    .yasir-progress-container {
        margin: 20px 0;
        display: none;
    }

    .yasir-progress-bar {
        width: 100%;
        height: 20px;
        background: #2a2a2a;
        border-radius: 3px;
        overflow: hidden;
        border: 1px solid #444444;
    }

    .yasir-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #16e791, #14d182);
        width: 0%;
        transition: width 0.3s ease;
    }

    .yasir-progress-text {
        text-align: center;
        margin-top: 10px;
        color: #e0e0e0;
    }

    .yasir-log {
        background: #121212;
        border: 1px solid #333333;
        border-radius: 3px;
        padding: 20px;
        max-height: 300px;
        overflow-y: auto;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
        color: #e0e0e0;
        white-space: pre-wrap;
    }

    .yasir-warning {
        background: #ffc107;
        color: #121212;
        padding: 15px;
        border-radius: 3px;
        margin: 20px 0;
        font-weight: 700;
    }

    .yasir-success {
        background: #28a745;
        color: #ffffff;
        padding: 15px;
        border-radius: 3px;
        margin: 20px 0;
        font-weight: 700;
    }

    .yasir-error {
        background: #dc3545;
        color: #ffffff;
        padding: 15px;
        border-radius: 3px;
        margin: 20px 0;
        font-weight: 700;
    }
    </style>

    <div class="yasir-header">
        <h1>WooCommerce Bulk Delete Orders</h1>
        <p class="subtitle">Developed by <a href="https://yasirshabbir.com" class="brand-link" target="_blank">Yasir
                Shabbir</a></p>
    </div>

    <div class="yasir-card">
        <h2>Order Statistics</h2>
        <div class="yasir-stats">
            <div class="yasir-stat-card">
                <div class="yasir-stat-number" id="total-orders">Loading...</div>
                <div class="yasir-stat-label">Total Orders</div>
            </div>
            <div class="yasir-stat-card">
                <div class="yasir-stat-number" id="processed-orders">0</div>
                <div class="yasir-stat-label">Processed</div>
            </div>
            <div class="yasir-stat-card">
                <div class="yasir-stat-number" id="remaining-orders">Loading...</div>
                <div class="yasir-stat-label">Remaining</div>
            </div>
        </div>
    </div>

    <div class="yasir-card">
        <h2>Bulk Delete Operations</h2>
        <div class="yasir-warning">
            ⚠️ WARNING: This action will permanently delete ALL WooCommerce orders and cannot be undone. Please backup
            your database before proceeding.
        </div>

        <button id="start-delete" class="yasir-button danger">Delete All Orders</button>
        <button id="refresh-count" class="yasir-button">Refresh Count</button>

        <div class="yasir-progress-container" id="progress-container">
            <div class="yasir-progress-bar">
                <div class="yasir-progress-fill" id="progress-fill"></div>
            </div>
            <div class="yasir-progress-text" id="progress-text">0% Complete</div>
        </div>
    </div>

    <div class="yasir-card">
        <h2>Process Log</h2>
        <div class="yasir-log" id="process-log">Ready to start deletion process...</div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let totalOrders = 0;
        let processedOrders = 0;
        let isProcessing = false;

        // Get initial order count
        getOrdersCount();

        $('#refresh-count').on('click', function() {
            getOrdersCount();
        });

        $('#start-delete').on('click', function() {
            if (!confirm(
                    'Are you absolutely sure you want to delete ALL orders? This cannot be undone!')) {
                return;
            }

            if (!confirm(
                'Last chance! This will permanently delete all WooCommerce orders. Continue?')) {
                return;
            }

            startDeletion();
        });

        function getOrdersCount() {
            $.ajax({
                url: wcBulkDelete.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_orders_count',
                    nonce: wcBulkDelete.nonce
                },
                success: function(response) {
                    if (response.success) {
                        totalOrders = response.data.count;
                        $('#total-orders').text(totalOrders);
                        $('#remaining-orders').text(totalOrders - processedOrders);
                        logMessage('Total orders found: ' + totalOrders);
                    }
                }
            });
        }

        function startDeletion() {
            if (isProcessing) return;

            isProcessing = true;
            processedOrders = 0;

            $('#start-delete').prop('disabled', true);
            $('#progress-container').show();

            logMessage('Starting bulk deletion process...');
            processBatch();
        }

        function processBatch() {
            $.ajax({
                url: wcBulkDelete.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_orders_batch',
                    nonce: wcBulkDelete.nonce
                },
                success: function(response) {
                    if (response.success) {
                        processedOrders += response.data.deleted;
                        updateProgress();
                        logMessage('Batch processed: ' + response.data.deleted + ' orders deleted');

                        if (response.data.remaining > 0) {
                            // Continue processing
                            setTimeout(processBatch, 1000); // 1 second delay between batches
                        } else {
                            // All done
                            completeDeletion();
                        }
                    } else {
                        logMessage('Error: ' + response.data.message);
                        completeDeletion();
                    }
                },
                error: function() {
                    logMessage('AJAX error occurred');
                    completeDeletion();
                }
            });
        }

        function updateProgress() {
            const percentage = totalOrders > 0 ? Math.round((processedOrders / totalOrders) * 100) : 0;
            $('#progress-fill').css('width', percentage + '%');
            $('#progress-text').text(percentage + '% Complete (' + processedOrders + '/' + totalOrders + ')');
            $('#processed-orders').text(processedOrders);
            $('#remaining-orders').text(totalOrders - processedOrders);
        }

        function completeDeletion() {
            isProcessing = false;
            $('#start-delete').prop('disabled', false);

            if (processedOrders === totalOrders) {
                logMessage('✅ Deletion completed successfully! All orders have been removed.');
                showMessage('All orders have been successfully deleted!', 'success');
            } else {
                logMessage('⚠️ Deletion completed with some remaining orders.');
                showMessage('Deletion process completed. Check logs for details.', 'warning');
            }

            getOrdersCount();
        }

        function logMessage(message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = '[' + timestamp + '] ' + message + '\n';
            $('#process-log').append(logEntry);
            $('#process-log').scrollTop($('#process-log')[0].scrollHeight);
        }

        function showMessage(message, type) {
            const messageDiv = $('<div class="yasir-' + type + '">' + message + '</div>');
            $('.yasir-card').first().after(messageDiv);
            setTimeout(function() {
                messageDiv.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    });
    </script>
</div>
<?php
    }

    public function get_orders_count()
    {
        check_ajax_referer('wc_bulk_delete_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;

        // Count orders using legacy WordPress posts storage
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'shop_order'"
        );

        $this->log_message("Order count requested: " . $count);

        wp_send_json_success(array('count' => intval($count)));
    }

    public function delete_orders_batch()
    {
        check_ajax_referer('wc_bulk_delete_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;

        // Get a batch of order IDs
        $order_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'shop_order' 
                 LIMIT %d",
                $this->batch_size
            )
        );

        $deleted_count = 0;

        if (!empty($order_ids)) {
            foreach ($order_ids as $order_id) {
                // Delete order using WooCommerce function if available
                if (function_exists('wc_delete_order')) {
                    $result = wc_delete_order($order_id, true);
                    if ($result) {
                        $deleted_count++;
                    }
                } else {
                    // Fallback to WordPress function
                    $result = wp_delete_post($order_id, true);
                    if ($result) {
                        $deleted_count++;
                        // Also delete order meta
                        $wpdb->delete($wpdb->postmeta, array('post_id' => $order_id));
                    }
                }
            }
        }

        // Get remaining count
        $remaining = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'shop_order'"
        );

        $this->log_message("Batch processed: {$deleted_count} orders deleted, {$remaining} remaining");

        wp_send_json_success(array(
            'deleted' => $deleted_count,
            'remaining' => intval($remaining)
        ));
    }

    private function log_message($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Initialize the plugin
function init_woocommerce_bulk_delete_orders()
{
    if (class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        new WooCommerce_Bulk_Delete_Orders();
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>WooCommerce Bulk Delete Orders requires WooCommerce to be installed and active.</p></div>';
        });
    }
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', 'init_woocommerce_bulk_delete_orders');
?>