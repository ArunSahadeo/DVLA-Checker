<?php

/**
 * Plugin Name: DVLA Checker
 * Plugin URI: https://github.com/ArunSahadeo/dvla-checker
 * Description: Retrieves vehicle details for a registration number using the DVLA site.
 * Version: 0.0.1
 * Author: Arun Sahadeo
 * Author URI: https://github.com/ArunSahadeo
 * License: GPLv2 or later
 */

add_action('init', 'dvlacheck_register_post');

function dvlacheck_register_post()
{
    $labels = array(
        'name'               => _x( 'Vehicles', 'post type general name' ),
        'singular_name'      => _x( 'Vehicle', 'post type singular name' ),
        'edit_item'          => __( 'Vehicle' ),
        'all_items'          => __( 'All Vehicles' ),
        'view_item'          => __( 'View Vehicle' ),
        'search_items'       => __( 'Search Vehicles' ),
        'not_found'          => __( 'No vehicles found' ),
        'not_found_in_trash' => __( 'No vehicles found in the Trash' ), 
        'parent_item_colon'  => '',
        'menu_name'          => 'Vehicles'
    );

    $args = array(
            'label'             => __( 'Vehicles', 'text_domain' ),
            'description'       => __( 'Vehicle data retrieved from DVLA', '_text_domain' ),
            'labels'            => $labels,
            'supports'          => false,
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_in_admin_bar' => true,
            'can_export'        => true,
            'rewrite'           => false
    );

    register_post_type('vehicles', $args);
}

function customVehiclesColumns($columns)
{

    unset($columns['date']);
    $columns['title'] = '';

    $vehicleCols = array(
        'registration_number'   => __('Registration Number', wp_get_theme()),
        'manufacturer'          => __('Manufacturer', wp_get_theme()),
        'first_registration'    => __('Date of first registration', wp_get_theme()),
        'fuel_type'             => __('Fuel Type', wp_get_theme()),
    );

    return array_merge($columns, $vehicleCols);
}

add_filter('manage_vehicles_posts_columns', 'customVehiclesColumns');

function customVehiclesColumnEntries($column_name)
{

    global $post;

    if ( $column_name == 'registration_number' )
    {
        $registration_number = get_post_meta( $post->ID, 'registration_number', true );
        echo $registration_number;
    }

    if ( $column_name == 'manufacturer' )
    {
        $manufacturer = get_post_meta( $post->ID, 'manufacturer', true );
        echo $manufacturer;
    }

    if ( $column_name == 'first_registration' )
    {
        $first_registration = get_post_meta( $post->ID, 'first_registration', true );
        echo $first_registration;
    }

    if ( $column_name == 'fuel_type' )
    {
        $fuel_type = get_post_meta( $post->ID, 'fuel_type', true );
        echo $fuel_type;
    }
}

add_filter('manage_vehicles_posts_custom_column', 'customVehiclesColumnEntries');

function disableTitleSort($columns)
{
    unset($columns['title']);
    return $columns;
}

add_filter('manage_edit-vehicles_sortable_columns', 'disableTitleSort');

function customVehiclesInfoContainer()
{
    add_meta_box(
        'vehicle_registration_details_box',
        __( 'Vehicle Registration Details', 'vehicle_registration_details' ),
        'customVehicleHTML',
        'vehicles',
        'normal'
    );
}

add_action('add_meta_boxes', 'customVehiclesInfoContainer');

function customVehicleHTML($post)
{ ?>
        
        <?php 
            $vehicleProps = get_post_meta($post->ID);

            foreach ($vehicleProps as $title => $vehicleProp):
        ?>
        
        <?php if (stristr($title, '_edit') || stristr($title, '_wp')) continue; ?>

        <?php
            $title = ucwords($title);
            $title = str_replace('_', '&nbsp;', $title);
            $title = preg_replace('/Mot/', 'MOT', $title);
        ?>

        <h3><?= $title; ?></h3>
        <p><?= $vehicleProp[0]; ?></p>

        <?php endforeach; ?>
<?php
}

function dvlacheck_plugin_basename()
{
    return plugin_basename(__FILE__);
}

function dvlacheck_plugin_dir()
{
    return plugin_dir_path(__FILE__);
}

function dvlacheck_phantomjs()
{
    $path = exec('which phantomjs');

    if (defined('PHANTOMJS')) return PHANTOMJS;
    elseif (!empty($path)) return $path;
}

function dvlacheck_get_phantomjs_script()
{
    return realpath(dvlacheck_plugin_dir() . "dvla-checker.js");    
}

function dvlacheck_retrieve_data($registration)
{

  $phantomjs = dvlacheck_phantomjs();

  if (empty($phantomjs)) {
    error_log('The phantomjs binary was not found. Make sure it is in your PHP\'s PATH or set the PHANTOMJS constant to its path.');
    return;
  }

  $script = dvlacheck_get_phantomjs_script();
  
  if (!file_exists($script))
  {
    error_log("$script does not exist!");
    return;
  }


  $exec = $phantomjs . " --ignore-ssl-errors=yes $script '$registration'";
  
  $escaped_command = escapeshellcmd($exec);
  return shell_exec($escaped_command);
}

function dvlacheck_input()
{ ?>
    <form id="dvla-registration-lookup">
        <input type="text" name="reg_number" class="registration-input" />
        <button type="submit" class="dvla-submit">Check registration</button>
    </form>
<?php
}

add_shortcode('dvla_input', 'dvlacheck_input');

function dvlacheck_form_handler()
{
    $mappings = [
      'tax status' => 'taxStatus',
      'MOT status' => 'MOTStatus',
      'vehicle make' => 'vehicleMake',
      'first registration' => 'firstRegistration',
      'fuel type' => 'fuelType',
      'year of manufacture' => 'manufactureYear',
      'cylinder capacity' => 'cylinderCapacity',
      'emissions' => 'C02Emissions',
      'export marker' => 'exportMarker',
      'vehicle status' => 'vehicleStatus',
      'colour' => 'vehicleColour',
      'type approval' => 'typeApproval',
      'wheelplan' => 'wheelplan',
      'revenue weight' => 'revenueWeight'
    ];
  
    if(!isset($_POST['reg_number'])) return;

    $regNumber = $_POST['reg_number'];

    if ( !preg_match('/\s/', $regNumber) )
    {
        $regNumber = preg_replace('/^.{4}/', '$0 ', $regNumber); 
    }

    if ( strtoupper($_POST['reg_number']) != $_POST['reg_number'] )
    {
        $regNumber = strtoupper($regNumber);
    }

    $carDetails = [];

    $scriptResults = dvlacheck_retrieve_data($regNumber);

    if (!$scriptResults)
    {
        return;
    }

    $scriptResults = explode("\n", $scriptResults);

    foreach($scriptResults as $scriptResult)
    {
      $itemStatus = explode(":", $scriptResult)[1];

      foreach ($mapping as $substring => $key)
      {
        if (stristr($scriptResult, $substring))
        {
            $carDetails[$key] = $itemStatus;
            break;
        }
      }
    }

    $postOptions = [
        'post_type' => 'vehicles',
        'meta_input' => array(
            'registration_number' => $regNumber,
            'manufacturer' => $carDetails['manufacturer'] ?: 'N/A',
            'first_registration' => $carDetails['firstRegistration'] ?: 'N/A',
            'fuel_type' => $carDetails['fuelType'] ?: 'N/A',
            'tax_status' => $carDetails['taxStatus'] ?: 'N/A',
            'mot_status' => $carDetails['MOTStatus'] ?: 'N/A',
            'manufacture_year' => $carDetails['manufactureYear'] ?: 'N/A',
            'cylinder_capacity' => $carDetails['cylinderCapacity'] ?: 'N/A',
            'C02_emissions' => $carDetails['C02Emissions'] ?: 'N/A',
            'export_marker' => $carDetails['exportMarker'] ?: 'N/A',
            'vehicle_status' => $carDetails['vehicleStatus'] ?: 'N/A',
            'vehicle_colour' => $carDetails['vehicleColour'] ?: 'N/A',
            'type_approval' => $carDetails['typeApproval'] ?: 'N/A',
            'wheelplan' => $carDetails['wheelplan'] ?: 'N/A',
            'revenue_weight' => $carDetails['revenueWeight'] ?: 'N/A'
        )
    ];

    $args = array(
        'post_type' => 'vehicles',
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => 'registration_number',
                'value' => $regNumber
            )
        )
    );

    $vehicleQuery = new WP_Query($args);

    if ( $vehicleQuery->post_count < 1 )
    {
        wp_insert_post($postOptions);
        return;
    }

    $vehiclePostID = $vehicleQuery->posts[0]->ID;
    $postOptions['ID'] = $vehiclePostID; 

    wp_update_post($postOptions);

}

add_action('init', 'dvlacheck_form_handler');

function dvlacheck_form_styles()
{
    wp_enqueue_style('dvla_main', plugins_url('/css/dvla_main.css', __FILE__));
}

add_action('wp_enqueue_scripts', 'dvlacheck_form_styles');

function dvlacheck_scripts()
{
    wp_enqueue_script( 'dvlacheck_registration', plugins_url('/js/dvla_registration.js', __FILE__), array('dvlacheck_validation_methods') );
    wp_enqueue_script( 'dvlacheck_validation_methods', plugins_url('/js/dvla_validation_methods.js', __FILE__) );
    wp_add_inline_script('dvlacheck_validation_methods', 'var site_url = "' . site_url() . '";');
}

add_action('wp_enqueue_scripts', 'dvlacheck_scripts');
