<?php

/**
 * 
 * Generates an array of Time Slots using passed parameters
 * @param  [int] $duration      [minutes]
 * @param  [int] $break         [minutes]
 * @param  [str]     $start     [YYYY-mm-dd hh:mm]
 * @param  [str]     $end       [YYYY-mm-dd hh:mm]
 * @return [array]
 *
 * =======================================================
 * 
 * EXAMPLE:
 * 
 *   Service start time 10:00 AM , End Time 12:00 PM.
 *   Condition: each service time 30 min & 15 min break.
 * 
 * Input parameters would be:
 * 
 *   $duration = 30;
 *   $break    = 15;
 *   $start    = '2015-07-30 10:00';
 *   $end      = '2015-07-30 12:00';
 *
 *  Result :
 *  
 *    array(3) { 
 *      [0]=> string(17) "10:00AM - 10:30AM" 
 *      [1]=> string(17) "10:45AM - 11:15AM" 
 *      [2]=> string(17) "11:30AM - 12:00PM" 
 *    }
 * 
 */

function generateTimeSlots($duration, $break, $start, $end) {
    $start         = new DateTime($start);
    $end           = new DateTime($end);
    $interval      = new DateInterval("PT" . $duration . "M");
    $breakInterval = new DateInterval("PT" . $break . "M");
    
    for ($intStart = $start; $intStart < $end; $intStart->add($interval)->add($breakInterval)) {
        $endPeriod = clone $intStart;
        $endPeriod->add($interval);
        if ($endPeriod > $end) {
            $endPeriod = $end;
        }
        $periods[] = $intStart->format('H:iA') . ' - ' . $endPeriod->format('H:iA');
    }
    
    return $periods;
}

/**
 * 
 * Helper function to create a Time Slot
 * @param  [type] $sku      [description]
 * @param  [type] $name     [description]
 * @param  [type] $duration [description]
 * @param  [type] $break    [description]
 * @param  [str]  $start     [YYYY-mm-dd hh:mm]
 * @param  [str]  $end       [YYYY-mm-dd hh:mm]
 * @return [type]           [description]
 * 
 */
function create_timeslot_product($sku, $name, $duration, $break, $start, $end, $price, $stock = 1) {
  
  // Generate Time Slots:
  $time_slots = generateTimeSlots($duration, $break, $start, $end);

  // Generate Variations using the Time Slots:
  $variations = array();
  foreach($time_slots as $timeslot) {
    // Generate a variation for each time slot
    $variations[] = array(
      'stock' => $stock,    // How much Stock per time slot?
      'desc'  => $timeslot, // Description of time slot
      'price' => $price     // Price of slot
    );
  }

  // There needs to be a product attribute 'timeslot' in the DB for this to work!
  create_variable_woo_product($sku, $name, 'track_day', $variations, 'pa_timeslot');

}


// Register Products when WP loaded:
add_action( 'wp_loaded', 'register_products', 10, 1);

function register_products(){
  // called only after woocommerce has finished loading
  // Creates Time Slot product, if it doesn't exist yet
  create_timeslot_product('timeslot_test', 'Example Time Slot', 30, 15, '2015-08-30 10:00', '2015-08-30 12:00', 55, 1);
}
