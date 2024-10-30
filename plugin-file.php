<?php
/*
Plugin Name: KIUD packaging for Woocommerce
Description: KIUD packaging for Woocommerce
Version: 1.6.0
Author: Kiud packaging
*/

function low_impack_enqueue_styles() {
  wp_enqueue_style( 'my-plugin-style', plugin_dir_url( __FILE__ ) . 'styles.css' );
}

function low_impack_display_checkout_shortcode_before_summary() {
  
  if ( get_option( 'show_before_summary_option_plugin' )) {
    echo do_shortcode( '[low-impack]' );
  }
}

function low_impack_display_checkout_shortcode_after_payment() {
  
  if ( get_option( 'show_after_summary_option_plugin' )) {
    echo do_shortcode( '[low-impack]' );
  }
}

function low_impack_myplugin_add_to_cart_action( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
  // Check if a specific product has been added to the cart
  $specific_product_id = get_option( 'low_impack_product_id' );

  if ( $product_id == $specific_product_id ) {
      // Modify the redirect URL
      add_filter( 'woocommerce_add_to_cart_redirect', 'low_impack_modify_cart_url' );
  }
}

function low_impack_modify_cart_url( $url ) {
  // Modify the URL as needed
  $checkout_url = wc_get_checkout_url();
  
  return $checkout_url;
}

function low_impack_create_product_on_install() {
  if ( !class_exists( 'WooCommerce' ) ) {
    return;
  }

  $product_id_1 = low_impack_create_product('KIUD pakend', 'KIUD pakend', 'karp.jpg');
  $product_id_2 = low_impack_create_product('KIUD pakend', 'KIUD pakend', 'kott_umbrik.jpg');
  $product_id_3 = low_impack_create_product('KIUD pakend', 'KIUD pakend', 'koik.jpg');

  update_option( 'low_impack_product_id', sanitize_text_field( $product_id_1 ) );

  return;
}
register_activation_hook( __FILE__, 'low_impack_create_product_on_install' );

function low_impack_create_product($product_name, $description, $file_name) {

  $product_data = array(
    'name' => $product_name,
    'type' => 'simple',
    'regular_price' => '3',
    'visibility' => 'hidden',
    'short_description' => '',
    'description' => $description,
    'status' => 'publish',
    'meta_input' => array(
    )  
  );

  $product_id = wp_insert_post( array(
    'post_title' => $product_data['name'],
    'post_content' => $product_data['description'],
    'post_excerpt' => $product_data['short_description'],
    'post_status' => $product_data['status'],
    'post_type' => 'product',
  ) );

  if ( $product_id ) {
    wp_set_object_terms( $product_id, 'simple', 'product_type' );
    update_post_meta( $product_id, '_regular_price', $product_data['regular_price'] );
    update_post_meta( $product_id, '_price', $product_data['regular_price'] );
    update_post_meta( $product_id, '_stock_status', 'instock' );
    update_post_meta($product_id, '_visibility', 'hidden');
    update_post_meta($product_id, '_stock_status', 'out_of_stock');

    $terms = array( 'exclude-from-search', 'exclude-from-catalog' );
    wp_set_post_terms( $product_id, $terms, 'product_visibility', false );

  }  

  $upload_dir = wp_upload_dir();
  $image_path = plugin_dir_path( __FILE__ ) . $file_name;
        
  // Upload the image to the WordPress media library
  $upload_dir = wp_upload_dir();
  $image_type = wp_check_filetype( basename( $image_path ), null );
  $image_data = file_get_contents( $image_path );

  $filename = basename( $image_path );

  if( wp_mkdir_p( $upload_dir['path'] ) ) {
    $file = $upload_dir['path'] . '/' . $filename;
  } else {
    $file = $upload_dir['basedir'] . '/' . $filename;
  }

  file_put_contents( $file, $image_data );

  $wp_filetype = wp_check_filetype( $filename, null );
  $attachment = array(
    'post_mime_type' => $wp_filetype['type'],
    'post_title' => sanitize_file_name( $filename ),
    'post_content' => '',
    'post_status' => 'hidden',
  );

  $attach_id = wp_insert_attachment( $attachment, $file );
  require_once( ABSPATH . 'wp-admin/includes/image.php' );
  $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
  wp_update_attachment_metadata( $attach_id, $attach_data );

  // Set the image as the product's featured image
  update_post_meta( $product_id, '_thumbnail_id', $attach_id );

  return $product_id;
}

class LowImpack {
  
  function __construct() {
    add_action( 'admin_menu', array( $this, 'low_impack_admin_menu' ) );
    add_shortcode( 'low-impack', array( $this, 'low_impack_shortcode' ) );
    add_action( 'woocommerce_add_to_cart', 'low_impack_myplugin_add_to_cart_action', 10, 6 );
    add_action( 'wp_enqueue_scripts', 'low_impack_enqueue_styles' );
    add_action( 'woocommerce_checkout_before_order_review', 'low_impack_display_checkout_shortcode_after_payment', 5 );
    add_action( 'woocommerce_review_order_before_payment', 'low_impack_display_checkout_shortcode_before_summary', 5 );
  }

  function low_impack_admin_menu() {
    add_options_page( 'KIUD-packaging', 'KIUD-packaging', 'manage_options', 'KIUD-packaging', array( $this, 'low_impack_settings_page' ) );
  }

  function low_impack_settings_page() {
    if ( !current_user_can( 'manage_options' ) ) {
      wp_die( 'You do not have sufficient permissions to access this page.' );
    }

    if ( isset( $_POST['low_impack_product_id'] ) ) {
      update_option( 'low_impack_product_id', sanitize_text_field( $_POST['low_impack_product_id'] ) );
      update_option( 'low_impack_show_plugin', sanitize_text_field( $_POST['low_impack_show_plugin'] ) );
      update_option( 'show_before_summary_option_plugin', sanitize_text_field( $_POST['show_before_summary_option_plugin'] ) );
      update_option( 'show_after_summary_option_plugin', sanitize_text_field( $_POST['show_after_summary_option_plugin'] ) );
    }

    $product_id = get_option( 'low_impack_product_id' );
    $show_plugin = get_option( 'low_impack_show_plugin' );
    $show_before_summary_payment_option = get_option( 'show_before_summary_option_plugin' );
    $show_after_summary_option_plugin = get_option( 'show_after_summary_option_plugin' );

    ?>
    <div class="wrap-low-impack-settings">
      <h1>KIUD packaging</h1>
      <form method="post">
        <table class="form-table">
          <tr>
            <th scope="row">Product ID</th>
            <td><input type="text" name="low_impack_product_id" value="<?php echo esc_attr( $product_id ); ?>"></td>
          </tr>
          <tr>
            <th scope="row">Kuva plugin</th>
            <td><input type="checkbox" name="low_impack_show_plugin" value="1" <?php checked( $show_plugin, 1 ); ?>></td>
          </tr>
          <tr>
            <th scope="row">Aseta plugin ennem ostukorvi kokkuvõte lehe sektsiooni</th>
            <td><input type="checkbox" name="show_before_summary_option_plugin" value="1" <?php checked( $show_before_summary_payment_option, 1 ); ?>></td>
          </tr>
          <tr>
            <th scope="row">Aseta plugin peale ostukorvi kokkuvõte lehe sektsiooni</th>
            <td><input type="checkbox" name="show_after_summary_option_plugin" value="1" <?php checked( $show_after_summary_option_plugin, 1 ); ?>></td>
          </tr>
        </table>
        <p class="submit">
          <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
      </form>
    </div>
    <?php
  }

  function low_impack_shortcode() {
    $url = get_option( 'low_impack_url' );
    $product_id = get_option( 'low_impack_product_id' );
    $show_plugin = get_option( 'low_impack_show_plugin' );
    $image_id = get_post_thumbnail_id( $product_id );
    $image_url = wp_get_attachment_url( $image_id );

    if ( !$show_plugin ) {
      return '';
    }

    $product = wc_get_product($product_id );
    if ( !$product ) {
      return '<p>Product not found</p>';
    }

    $in_cart = false;

    $cart_items = WC()->cart->get_cart();

    foreach ( $cart_items as $cart_item ) {
        if ( $cart_item['product_id'] == $product_id ) {
            $in_cart = true;
            break;
        }
    }
    
    global $woocommerce;
    ob_start();
    ?>
      <div class="wrap-low-impack-plugin">
        <p class="title-low-impack-plugin"><?php _e( 'VALI KESKKONNA-SÕBRALIK RINGLUSPAKEND', 'low_impack_choose' );?></p>
        <p class="description-low-impack-plugin"><?php _e( 'KIUD pakendid on valmistatud tekstiilijäätmetest ja on korduvkasutatavad. Nii säästame loodust ja vähendame pakendiprügi. Pandiraha saad tagasi, kui viid tühja pakendi TANGO tagastuskasti.', 'low_impack_save' );?> <a target="_blank" class="low-impack-plugin-more-info" href="<?php echo esc_url( 'https://kiud.io/et/i-received-a-pack-tango-return-stations-maps/' ); ?>"> <?php _e( 'Siin lisainfo', 'low_impack_more_info' );?></a>.</p>
        <img class="product_image-low-impack-plugin" src=<?php echo esc_url( $image_url ); ?> >
 
          <form method="post"> </form>
          <form id="add-to-cart" class="add-to-cart-form-low-impack-plugin" method="post" action="">
            <input form="add-to-cart" type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>"/>
            
            <?php  if ( $in_cart ) { ?>
                <input form="add-to-cart" disabled type="submit" class="button add-to-cart-button-low-impack-plugin" value="<?php _e( 'Aitäh! Ootame pakendit tagasi!', 'low_impack_wait' );?>"/>
            <?php  } else { ?>
                <input form="add-to-cart" type="submit" class="add-to-cart-button-low-impack-plugin" value="<?php _e( 'Lisa pandiga pakend 3 €!', 'low_impack_package' );?> "/>
            <?php  } ?>
            
          </form>
      </div>  
    <?php

    return ob_get_clean();

    }
}

new LowImpack();
