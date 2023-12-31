<?php 
/*
 * Plugin Name:       MB Woo Product Categories Sync
 * Description:       This plguin for syncronize product categories from client database
 * Version:           0.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            CanSoft
 * Author URI:        https://cansoft.com/
*/

defined( 'ABSPATH' ) or die( 'Unauthorized Access' );

//WORDPRESS HOOK FOR ADD A CRON JOB EVERY 2 Min
function mb_product_categories_cron_schedules($schedules){
    if(!isset($schedules['every_twelve_hours'])){
        $schedules['every_twelve_hours'] = array(
            'interval' => 12*60*60, // Every 12 hours
            'display' => __('Every 12 hours'));
    }
    return $schedules;
}
add_filter('cron_schedules','mb_product_categories_cron_schedules');


/**
 * Get all categories from client database 
 */
function get_all_categories_from_client_database(){

    $url = 'https://modern.cansoft.com/tables/ICSEGV.php'; 
    $arguments = array(
        'method' => 'GET'
    );

    $response = wp_remote_get( $url, $arguments );
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: {$error_message}";
    } else {
        $all_product_categories = json_decode(wp_remote_retrieve_body($response));

        $all_ready_to_input_cat = [];

        foreach($all_product_categories as $all_product_categorie){
            $all_ready_to_input_cat[] = array(
                'name' => ucwords(strtolower(trim($all_product_categorie->DESC))),
                'meta' => array(
                    'segment' => trim($all_product_categorie->SEGMENT),
                    'segval' => trim($all_product_categorie->SEGVAL),
                )
            );
        }

        return $all_ready_to_input_cat;
    }
}


/**
 * Add a menu in wordpress product menu
 */
function mb_add_custom_product_categories(){

    //add sub menu in product menu for sync product categories
    add_submenu_page(
        'mb_syncs',
        'Sync Categories',
        'Sync Categories',
        'manage_options',
        'sync-categories',
        'mb_product_categories_sync'
    );
}
add_action('admin_menu', 'mb_add_custom_product_categories', 999);



//This is callback function  for add_submenu_page()
function mb_product_categories_sync(){
    ?>
        <div class="wrap">
            <form method="POST">
                <?php 
                    submit_button( 'Sync Product Categories', 'primary', 'mb-submit-product-categories-sync' );

                    submit_button( 'Start Cron From Now', 'primary', 'mb-submit-start-cron' );
                ?>
            </form>

            <?php 

            // echo "<pre>";
            // print_r(get_all_categories_from_client_database());
            // echo "</pre>";

            // <?php 
                // Get all product categories
                // $categories = get_terms( array(
                //     'taxonomy'   => 'product_cat', // Taxonomy name for product categories
                //     'hide_empty' => false, // Include categories with no products
                // ) );

                // // Loop through each category
                // foreach ( $categories as $category ) {
                //     $category_id = $category->term_id;

                //     // Get meta values for the category
                //     $meta_values = get_term_meta( $category_id );

                //     // Output category name and meta values
                //     echo 'Category: ' . $category->name . '<br>';
                //     echo 'Meta Values:<br>';
                //     foreach ( $meta_values as $meta_key => $meta_value ) {
                //         echo $meta_key . ': ' . implode( ', ', $meta_value ) . '<br>';
                //     }

                //     echo '<br>';
                // }
            ?>



        </div>
    <?php
    
    //It work after click Products sync button
    if(isset($_POST['mb-submit-product-categories-sync'])){

        // Create the categories
        $categories_data = get_all_categories_from_client_database(); 

        foreach ($categories_data as $category_data) {
            // Create the category
            $category_id = wp_insert_term(
                $category_data['name'], // Category name
                'product_cat', // Taxonomy name for product categories
            );

            // Check if the category was created successfully
            if (!is_wp_error($category_id)) {
                $term_id = $category_id['term_id'];

                // Add custom meta to the category
                foreach ($category_data['meta'] as $meta_key => $meta_value) {
                    add_term_meta($term_id, $meta_key, $meta_value, true);
                }
            } else {
                // Error handling if category creation fails
                $error_message = $category_id->get_error_message();
                error_log('Category creation failed: ' . $error_message);
            }
        }

        wp_redirect(admin_url('edit.php?post_type=product&page=sync-categories'));
        exit();
    }

    //It work when Click Strt cron  button
    if(isset($_POST['mb-submit-start-cron'])){
        if (!wp_next_scheduled('mb_product_categories_add_with_cron')) {
            wp_schedule_event(time(), 'every_twelve_hours', 'mb_product_categories_add_with_cron');
        }

        wp_redirect(admin_url('edit.php?post_type=product&page=sync-categories'));
        exit();
    }


}

//For clear cron schedule
function woo_apis_plugin_deactivation(){
    wp_clear_scheduled_hook('mb_product_categories_add_with_cron');
}
register_deactivation_hook(__FILE__, 'woo_apis_plugin_deactivation');



//This happend when caron job is runnning
function mb_run_cron_for_add_categories(){
    // Create the categories
    $categories_data = get_all_categories_from_client_database(); 

    foreach ($categories_data as $category_data) {
        // Create the category
        $category_id = wp_insert_term(
            $category_data['name'], // Category name
            'product_cat', // Taxonomy name for product categories
        );

        // Check if the category was created successfully
        if (!is_wp_error($category_id)) {
            $term_id = $category_id['term_id'];

            // Add custom meta to the category
            foreach ($category_data['meta'] as $meta_key => $meta_value) {
                add_term_meta($term_id, $meta_key, $meta_value, true);
            }
        } else {
            // Error handling if category creation fails
            $error_message = $category_id->get_error_message();
            error_log('Category creation failed: ' . $error_message);
        }
    }
}
add_action('mb_product_categories_add_with_cron', 'mb_run_cron_for_add_categories');
