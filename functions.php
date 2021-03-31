// Waterfalls OSM Sync
// =============================================================================

function clear_waterfalls_from_db() {
  
  global $wpdb;

  $wpdb->query("DELETE FROM wp_posts WHERE post_type='waterfall'");
  $wpdb->query("DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT id FROM wp_posts);");
  $wpdb->query("DELETE FROM wp_term_relationships WHERE object_id NOT IN (SELECT id FROM wp_posts)");

}
// clear_waterfalls_from_db();



// if ( ! wp_next_scheduled( 'update_waterfall_list' ) ) {
//   wp_schedule_event( time(), 'weekly', 'update_waterfall_list' );
// }
add_action( 'update_waterfall_list', 'get_waterfalls_from_api' );
add_action( 'wp_ajax_nopriv_get_waterfalls_from_api', 'get_waterfalls_from_api' );
add_action( 'wp_ajax_get_waterfalls_from_api', 'get_waterfalls_from_api' );
function get_waterfalls_from_api() {

  $waterfalls = [];

  // Should return an array of objects
  $response = wp_remote_retrieve_body( wp_remote_get('https://overpass-api.de/api/interpreter?data=[out:json];node(38.83,8.00,41.32,9.87)["waterway"="waterfall"];out;') );

  // turn it into a PHP array from JSON string
  $results =  json_decode( $response, true ); //true to get an array instead of object

  // Either the API is down or something else spooky happened. Just be done.
  if( ! is_array( $results ) || empty( $results ) ){
    return false;
  }

  $waterfalls[] = $results['elements'];
	
		var_dump(  $results['elements'] );


  foreach( $waterfalls as $waterfall ){
    
    $waterfall_slug = slugify( $waterfall->tags->name . '-' . $waterfall->id );     

    $existing_waterfall = get_page_by_path( $waterfall_slug, 'OBJECT', 'waterfall' );

    if( $existing_waterfall === null  ){
      
      $inserted_waterfall = wp_insert_post( [
        'post_name' => $waterfall_slug,
        'post_title' => $waterfall_slug,
        'post_type' => 'waterfall',
        'post_status' => 'publish'
      ] );

      if( is_wp_error( $inserted_waterfall ) || $inserted_waterfall === 0 ) {
        // die('Could not insert waterfall: ' . $waterfall_slug);
        // error_log( 'Could not insert waterfall: ' . $waterfall_slug );
        continue;
      }

      // add meta fields
      $fillable = [
      	'wpcf-osm-id' => 'id',
        'wpcf-alt_name' => 'alt_name',
        'wpcf-lat' => 'lat',
        'wpcf-lon' => 'lon',
        'wpcf-height' => 'height',
      ];

      foreach( $fillable as $key => $id ) {
        update_field( $key, $waterfall->$id, $inserted_waterfall );
      }

      
    } else {
      
      $existing_waterfall_id = $existing_waterfall->ID;
      $exisiting_brewerey_timestamp = get_field('updated_at', $existing_waterfall_id);

      if( $waterfall->updated_at >= $exisiting_brewerey_timestamp ){

        $fillable = [
        'wpcf-id' => 'id',
        'wpcf-alt_name' => 'alt_name',
        'wpcf-lat' => 'lat',
        'wpcf-lon' => 'lon',
        'wpcf-height' => 'height',
        ];

        foreach( $fillable as $key => $id ){
          update_field( $waterfall->$id, $existing_waterfall_id);
        }

      }

    }

  }
  
  $current_page = $current_page + 1;
  wp_remote_post( admin_url('admin-ajax.php?action=get_waterfalls_from_api'), [
    'blocking' => false,
    'sslverify' => false, // we are sending this to ourselves, so trust it.
    'body' => [
      'current_page' => $current_page
    ]
  ] );
  
}


function slugify($text){

  // remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);

  // trim
  $text = trim($text, '-');

  // remove duplicate -
  $text = preg_replace('~-+~', '-', $text);

  // lowercase
  $text = strtolower($text);

  if (empty($text)) {
    return 'n-a';
  }

  return $text;
}
