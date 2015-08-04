<?php

/**
 * Helper function that creates a Product where you can define:
 * @param  [str] $sku          
 * @param  [str] $title          
 * @param  array  $cats           
 * @param  array $variations     per
 * @param  [str] $variations_key (taxonomy term)        
 */

function create_variable_woo_product($sku, $title, $cats = array(), $variations, $variations_key) {
    // Check if Product already exists:
    if(!get_product_by_sku($sku)){
        // Add product
        global $wpdb, $logger, $vgh;
        $post = array(
          'post_title'  => $title, 
          'post_status' => "publish", 
          'post_name'   => sanitize_title($title), //name/slug
          'post_type'   => "product"
        );
        
        // Create product/post:
        $new_prod_id = wp_insert_post($post, $wp_error);
        
        // make product type be variable:
        $is_variable = wp_set_object_terms($new_prod_id, 'variable', 'product_type');
        if($is_variable instanceOf WP_Error){
            // Failed at creating term relationship
            var_dump($is_variable);
            die;
        }
        // add category to product:
        wp_set_object_terms($new_prod_id, $cats, 'product_cat');
        
        // ################### Add size attributes to main product: ####################
        // Array for setting attributes
        $var_keys = array();
        $total_tickets = 0;
        foreach ($variations as $variation) {
            $total_tickets+= (int)$variation["stock"];
            $var_keys[] = sanitize_title($variation['desc']);
            $term_insert = wp_insert_term( 
              $variation['desc'], // the term
              $variations_key, // the taxonomy
              array('slug' => sanitize_title($variation['desc']))
            );
        }
        wp_set_object_terms($new_prod_id, $var_keys, $variations_key);
        
        $thedata = Array($variations_key => Array('name' => $variations_key, 'value' => implode(' | ', $var_keys), 'is_visible' => '1', 'is_variation' => '1', 'is_taxonomy' => '1'));
        update_post_meta($new_prod_id, '_product_attributes', $thedata);
        
        // ########################## Done adding attributes to product #################
        // set product values:
        update_post_meta($new_prod_id, '_stock_status', ((int)$total_tickets > 0) ? 'instock' : 'outofstock');
        update_post_meta($new_prod_id, '_sku', $sku);
        update_post_meta($new_prod_id, '_stock', $total_tickets);
        update_post_meta($new_prod_id, '_visibility', 'visible');
        
        update_post_meta($new_prod_id, '_default_attributes', array());
        
        // ###################### Add Variation post types for sizes #############################
        $i = 1;
        
        $var_prices = array();
        
        // set IDs for product_variation posts:
        foreach ($variations as $variation) {
            $my_post = array('post_title' => 'Variation #' . $i . ' of ' . count($variations) . ' for product#' . $new_prod_id, 'post_name' => 'product-' . $new_prod_id . '-variation-' . $i, 'post_status' => 'publish', 'post_parent' => $new_prod_id,
             // post is a child post of product post
            'post_type' => 'product_variation',
             // set post type to product_variation
            'guid' => home_url() . '/?product_variation=product-' . $new_prod_id . '-variation-' . $i);
            
            // Insert ea. post/variation into database:
            $attID = wp_insert_post($my_post);
            
            // Create 2xl variation for ea product_variation:
            update_post_meta($attID, 'attribute_' . $variations_key, sanitize_title($variation['desc']));
            
            update_post_meta($attID, '_sale_price', (int)$variation["price"]);
            update_post_meta($attID, '_regular_price', (int)$variation["price"]);

            $var_prices[$i - 1]['id']            = $attID;
            $var_prices[$i - 1]['regular_price'] = sanitize_title($variation['price']);
            $var_prices[$i - 1]['sale_price']    = sanitize_title($variation['price']);
            
            // add size attributes to this variation:
            wp_set_object_terms($attID, $var_keys, 'pa_' . sanitize_title($variation['desc']));
            
            $thedata = Array($variations_key => Array('name' => $variations_key, 'value' => sanitize_title($variation['desc']), 'is_visible' => '1', 'is_variation' => '1', 'is_taxonomy' => '1'));
            update_post_meta($attID, '_product_attributes', $thedata);
            update_post_meta($attID, '_sku', mt_rand());
            update_post_meta($attID, '_stock_status', ((int)$variation["stock"] > 0) ? 'instock' : 'outofstock');
            update_post_meta($attID, '_manage_stock', 'yes');
            update_post_meta($attID, '_stock', $variation["stock"]);
            $i++;
        }
        
        $i = 0;
        foreach ($var_prices as $var_price) {
            $regular_prices[] = $var_price['regular_price'];
            $sale_prices[] = $var_price['sale_price'];
        }
        update_post_meta($new_prod_id, '_min_variation_price', min($sale_prices));
        update_post_meta($new_prod_id, '_max_variation_price', max($sale_prices));
        update_post_meta($new_prod_id, '_min_variation_regular_price', min($regular_prices));
        update_post_meta($new_prod_id, '_max_variation_regular_price', max($regular_prices));
        
        update_post_meta($new_prod_id, '_min_price_variation_id', $var_prices[array_search(min($sale_prices), $sale_prices) ]['id']);
        update_post_meta($new_prod_id, '_max_price_variation_id', $var_prices[array_search(max($sale_prices), $sale_prices) ]['id']);
        update_post_meta($new_prod_id, '_min_regular_price_variation_id', $var_prices[array_search(min($regular_prices), $regular_prices) ]['id']);
        update_post_meta($new_prod_id, '_max_regular_price_variation_id', $var_prices[array_search(max($regular_prices), $regular_prices) ]['id']);
        
    }

}

/**
 * Try to get product by SKU
 * @param  [type] $sku [description]
 * @return WP_Products if exists / false if it doesn't
 */
function get_product_by_sku( $sku ) {
  global $wpdb;
  $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
  if ( $product_id ) return new WC_Product( $product_id );
  return false;
}

// @param int $post_id - The id of the post that you are setting the attributes for
// @param array[] $attributes - This needs to be an array containing ALL your attributes so it can insert them in one go
function wcproduct_set_attributes($post_id, $attributes) {
    $i = 0;
    // Loop through the attributes array
    foreach ($attributes as $name => $value) {
        $product_attributes[$i] = array (
            'name' => htmlspecialchars( stripslashes( $name ) ), // set attribute name
            'value' => $value, // set attribute value
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 1,
            'is_taxonomy' => 0
        );
        $i++;
    }
    // Now update the post with its new attributes
    update_post_meta($post_id, '_product_attributes', $product_attributes);
}
