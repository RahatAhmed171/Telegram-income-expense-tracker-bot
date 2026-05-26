<?php
/*
Plugin Name: Finance Sync
Description: Receive finance transactions from Laravel.
Version: 1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Create Finance Transactions Table
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    'finance_sync_create_table'
);

function finance_sync_create_table()
{
    global $wpdb;

    $table_name =
        $wpdb->prefix . 'finance_transactions';

    $charset_collate =
        $wpdb->get_charset_collate();

    $sql = "
    CREATE TABLE $table_name (

        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

        telegram_user_id BIGINT NULL,

        type VARCHAR(50) NOT NULL,

        amount DECIMAL(10,2) NOT NULL,

        category VARCHAR(255) NULL,

        note TEXT NULL,

        transaction_date DATE NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id)

    ) $charset_collate;
    ";

    require_once(
        ABSPATH .
        'wp-admin/includes/upgrade.php'
    );

    dbDelta($sql);
}
/*
|--------------------------------------------------------------------------
| Register REST API Route
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {

    register_rest_route(
        'finance/v1',
        '/transaction',
        [
            'methods' => 'POST',

            'callback' => 'finance_sync_store_transaction',

            'permission_callback' => '__return_true'
        ]
    );
});

/*
|--------------------------------------------------------------------------
| Store Transaction
|--------------------------------------------------------------------------
*/

function finance_sync_store_transaction($request)
{
    global $wpdb;

    $table_name =
        $wpdb->prefix . 'finance_transactions';

    $data = $request->get_json_params();

    $wpdb->insert(
        $table_name,
        [

            'telegram_user_id' =>
                $data['telegram_user_id'] ?? null,

            'type' =>
                $data['type'] ?? null,

            'amount' =>
                $data['amount'] ?? 0,

            'category' =>
                $data['category'] ?? null,

            'note' =>
                $data['note'] ?? null,

            'transaction_date' =>
                $data['transaction_date'] ?? null
        ]
    );

    return [
        'success' => true,
        'message' => 'Transaction stored successfully'
    ];
}