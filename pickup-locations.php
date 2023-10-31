<?php

/*
Plugin Name:       Pickup Location Chooser
Plugin URI:        https://github.com/horowity/woocommerce_pickup_locations/
Description:       Adds the option to choose pickup location from a list of locations in WooCommerce.
Version:           0.2
Requires at least: 5.2
Requires PHP:      7.2
Author:            Yehezkel Horowitz
Author URI:        https://github.com/horowity/
Update URI:        https://github.com/horowity/woocommerce_pickup_locations/
Text Domain:       pickip-locations-plugin
Domain Path:       /languages
Woo: 43993:6907775deadbeef6907775deadbeef495
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


/*
Pickup Location Chooser is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Pickup Location Chooser is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Pickup Location Chooser. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
*/

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPINC' ) ) {
   exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

   function pickup_location_shipping_method() {
       if ( ! class_exists( 'Pickup_Location_Shipping_Method' ) ) {

           class Pickup_Location_Shipping_Method extends WC_Shipping_Method {
               /**
                * Constructor for your shipping class
                *
                * @access public
                * @return void
                */
               public function __construct( $instance_id = 0 ) {
				    $this->id = 'pickup_location';
                    $this->instance_id  = absint( $instance_id );
                    $this->method_title       = __( hebrev('מקום איסוף'), 'pickup_location' );  
                    $this->method_description = __( hebrev('בחר רשימת מקומות איסוף'), 'pickup_location' ); 
					
					$this->title = hebrev('מקום איסוף');


                   $this->supports              = array(
                       'shipping-zones',
                       'instance-settings',
                       'instance-settings-modal',
                   );


                   $this->init();
              }

               /**
                * Init your settings
                *
                * @access public
                * @return void
                */
               public function init() {
                 // Load the settings API
                 $this->init_form_fields();
                 $this->init_settings();
				 $this->enabled = true;
				 $this->title = $this->get_option('title');
                 
                 // Save settings in admin if you have any defined
                 add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

               }

               /**
                * Define settings field for this shipping
                * @return void
                */
              public function init_form_fields() {

                 $this->instance_form_fields = array(

                    'title' => array(
                        'title' => __( hebrev('כותרת'), 'title'),
                        'type' => 'text',
                        'description' => __(hebrev('כותרת זו תוצג למשתמש בבחירת צורת המשלוח'), 'pickup_location'),
                        'default' => __(hebrev('איסוף עצמי מ'), 'pickup_location')
                    ),
                    'locations' => array(
                        'title' => __(hebrev('מיקומים'), 'pickup_location'),
                        'type' => 'textarea',
                        'description' => __(hebrev('רשימת נקודות האיסוף, השתמש בנקודותיים כדי לתת את פרטי נקודת האיסוף'), 'pickup_location')
                    ),
					'cost' => array(
                        'title' => __(hebrev('עלות'), 'pickup_location'),
                        'type' => 'text',
                        'description' => __(hebrev('עלות המשלוח'), 'pickup_location'),
						'default' => '0'
                    ),
                 );
              }


               /**
                * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
                *
                * @access public
                * @param mixed $package
                * @return void
                */

               public function calculate_shipping( $package = array() ) {
                   $this->add_rate( array(
                       'id' => $this->id,
                       'label'   => $this->title,
                       'cost' => $this->get_option('cost')
                   ) );
               }
           }
       }
   }
   add_action( 'woocommerce_shipping_init', 'pickup_location_shipping_method' );

   function add_pickup_location_shipping_method( $methods ) {
       $methods['pickup_location'] = 'Pickup_Location_Shipping_Method';
       return $methods;
   }

   add_filter( 'woocommerce_shipping_methods', 'add_pickup_location_shipping_method' );

}

// Output dropdown location list
add_action('woocommerce_after_shipping_rate', 'output_dropdown_locations_list', 10, 2);
function output_dropdown_locations_list( $shipping_rate, $index )  {
	$chosen_shipping_rate_id = WC()->session->get('chosen_shipping_methods')[0];

	if ( $shipping_rate->id === $chosen_shipping_rate_id ) {
		$option_key = 'woocommerce_' . $shipping_rate->method_id . '_' . $shipping_rate->instance_id . '_settings';
		$settings   = get_option($option_key);

		if ( isset($settings['locations']) ) :
			$locations_list = explode("\n", str_replace("\r", "", $settings['locations']) );
		?>
		<select id="locationlist" name="locationlist">
			<option value="" data-details=""><?php _e(hebrev('בחר את מקום האיסוף'), "pickup_location"); ?></option>
		<?php foreach( $locations_list as $key => $location ) {
			list($location_short, $location_description) = explode(":", $location, 2);
			echo '<option value="'.$location_short.'" data-details="'.$location_description.'">'.$location_short.'</option>';
		} ?>
		</select>
		<script>
		jQuery(function($){
			var label = '<?php echo $shipping_rate->label; ?>';
			$(document.body).on('change', 'select#locationlist', function(){
				$(this).parent().find('label').text(label+': '+$(this).find("option:selected").data('details'));
			});
		});
		</script>
		<?php
		endif;
	}
}

// Pickup location Validation
add_action( 'woocommerce_checkout_process', 'validate_pickup_location' );
function validate_pickup_location() {
	$chosen_shipping_rate_id = WC()->session->get('chosen_shipping_methods')[0];

	if ( false !== strpos( $chosen_shipping_rate_id, 'pickup_location' )
	&& isset($_POST['locationlist']) && empty($_POST['locationlist']) ) {
	   wc_add_notice( __( hebrev('בחר את מקום האיסוף'), 'pickup_location' ), 'error' );
	}
}

// Save chosen pickup location as order meta
add_action( 'woocommerce_checkout_create_order', 'save_pickup_locations_to_order', 10, 2 );
function save_pickup_locations_to_order( $order, $data ) {
	if ( isset($_POST['locationlist']) && ! empty($_POST['locationlist']) ) {
		$order->update_meta_data('pickup_location', esc_attr($_POST['locationlist']) );
	}
}

// Save chosen pickup location as order shipping item meta
add_action( 'woocommerce_checkout_create_order_shipping_item', 'save_pickup_locations_to_order_item_shipping', 10, 4 );
function save_pickup_locations_to_order_item_shipping( $item, $package_key, $package, $order ) {
	if ( isset($_POST['locationlist']) && ! empty($_POST['locationlist']) ) {
		$item->update_meta_data('_pickup_location', esc_attr($_POST['locationlist']) );
	}
}

// Admin: Change location order shipping item displayed meta key label to something readable
add_filter('woocommerce_order_item_display_meta_key', 'filter_order_item_displayed_meta_key', 20, 3 );
function filter_order_item_displayed_meta_key( $displayed_key, $meta, $item ) {
	// Change displayed meta key label for specific order item meta key
	if( $item->get_type() === 'shipping' && $meta->key === '_pickup_location' ) {
		$displayed_key = __(hebrev('איסוף מ'), "pickup_location");
	}
	return $displayed_key;
}

// Customer: Display location below shipping method on orders and email notifications
add_filter( 'woocommerce_get_order_item_totals', 'display_pickup_location_on_order_item_totals', 10, 3 );
function display_pickup_location_on_order_item_totals( $total_rows, $order, $tax_display ){
	$chosen_location   = $order->get_meta('pickup_location'); // Get pickup location
	$new_total_rows = array(); // Initializing

	if( empty($chosen_location) )
		return $total_rows; // Exit

	// Loop through total rows
	foreach( $total_rows as $key => $value ){
		if( 'shipping' == $key ) {
			$new_total_rows['pickup_location'] = array(
				'label' => __(hebrev('איסוף מ'), "pickup_location") . ':',
				'value' => $chosen_location,
			);
		} else {
			$new_total_rows[$key] = $value;
		}
	}
	return $new_total_rows;
}

?>


