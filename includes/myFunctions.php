<?php
function wire_connector_activation(){
	global $wp_version;

	if ( version_compare($wp_version, '4.1', '<')){
		wp_die('This plugin requires WordPress Version 4.1 or higher.');
	}

}

function wc_register_settings(){
	register_setting('wc_setting_group', 'wc_options', 'wc_sanitize_options');
}


/*function add_javascript(){
	wp_enqueue_script( 'wc_wire_javascript', plugins_url('js/wc_wire_javascript.js', __FILE__ ), array('jquery'), null, true );
}

function add_bootstrap(){
	wp_enqueue_script( 'wc_wire_bootstrap', plugins_url('css/wc_wire_bootstrap.css', __FILE__ ), array('bootstrap'), null, true);
}*/

function wc_main_page(){
	?>
		<div class="wrap">
			<h1 style="text-align:center;">Wire Connector</h1>
		</div>
	<?php
}

function csv_pull_wpse_212972() {
  global $wpdb;
  $file = 'wire_csv'; // ?? not defined in original code
  $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_referrals;",ARRAY_A);

  if (empty($results)) {
    return;
  }

  $csv_output = '"'.implode('";"',array_keys($results[0])).'";'."\n";;

  foreach ($results as $row) {
    $csv_output .= '"'.implode('";"',$row).'";'."\n";
  }
  $csv_output .= "\n";

  $filename = $file."_".date("Y-m-d_H-i",time());
  header("Content-type: application/vnd.ms-excel");
  header("Content-disposition: csv" . date("Y-m-d") . ".csv");
  header( "Content-disposition: filename=".$filename.".csv");
  print $csv_output;
  exit;
}

function wpse_79898_test() {

    echo admin_url('admin-ajax.php?action=csv_pull');
}