<?php
/*
Plugin Name: Reposter Reloaded
Plugin URI: http://wordpress.org/extend/plugins/reposter-reloaded/
Description: Reposter Reloaded recycles your posts on a schedule of your choosing. Reposter Reloaded will find the oldest post in a specific category, and re-date it with the current date and time. It does this on the schedule of your own choosing or randomly, which can be as frequently as every 6 hours or as spread out as 21 days. Based on an idea from <a href="http://www.gurugazette.com/Reposter/">Kathy Burns-Millyard</a>.
Version: 0.1
Author: Frank Kugler
Author URI: http://www.webarbeit.net/
License: GPL3
*/
?>
<?php
// wp_schedule_event
function more_reccurences() {
 return array('15minutes' => array('interval' => 900, 'display' => 'Four times a hour'),);
}
add_filter('cron_schedules', 'more_reccurences');
// wp_schedule_event

add_action('admin_menu', 'KBM_Reposter_menu');
add_action('KBM_Reposter_event', 'KBM_Reposter_hourly_event');
register_activation_hook(__FILE__, 'KBM_Reposter_activation');
register_deactivation_hook(__FILE__, 'KBM_Reposter_deactivation');

function KBM_Reposter_activation() {
 wp_schedule_event(time(), '15minutes', 'KBM_Reposter_event');
 $kbm_Reposter_Configuration = array();
 $kbm_Reposter_Catagory_ID_Array = get_all_category_ids();
 foreach($kbm_Reposter_Catagory_ID_Array as $kbm_Reposter_CatagoryID) {
  $kbm_Reposter_Configuration[get_cat_name($kbm_Reposter_CatagoryID) . '_Next'] = -1;
  $kbm_Reposter_Configuration[get_cat_name($kbm_Reposter_CatagoryID) . '_Delay'] = -1;
 }
 update_option('kbm_Reposter_Configuration', maybe_serialize($kbm_Reposter_Configuration));
}

function KBM_Reposter_deactivation() {
 wp_clear_scheduled_hook('KBM_Reposter_event');
 delete_option('kbm_Reposter_Configuration');
}

function KBM_Reposter_menu() {
 add_options_page('Reposter Options', 'Reposter Reloaded', 8, __FILE__, 'KBM_Reposter_options_page');
}

function KBM_Reposter_hourly_event() {
 $kbm_Reposter_Configuration = maybe_unserialize(get_option('kbm_Reposter_Configuration'));
 $kbm_Reposter_Catagory_ID_Array = get_all_category_ids();
 foreach($kbm_Reposter_Catagory_ID_Array as $kbm_Reposter_Catagory_ID) {
  $kbm_Reposter_Catagory_Name = get_cat_name($kbm_Reposter_Catagory_ID);
  $kbm_Reposter_Delay_Name = $kbm_Reposter_Catagory_Name . '_Delay';
  if ($kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name] > 0) {
   $kbm_Reposter_Next_Name = $kbm_Reposter_Catagory_Name . '_Next';
   if (time() > $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name]) {
    $kbm_Reposter_Current_Time = time();
    while ($kbm_Reposter_Current_Time > $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name]) {
     if (($kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name]) == 1) {
      $kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name] = rand(43200, 172800);
      $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name] = $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name]+$kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name];
      $kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name] = 1;
     } else {
      $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name] = $kbm_Reposter_Configuration[$kbm_Reposter_Next_Name]+$kbm_Reposter_Configuration[$kbm_Reposter_Delay_Name];
     }
     }
     KBM_Reposter_move_oldest_post ($kbm_Reposter_Catagory_ID);
    }
   }
  }
  update_option('kbm_Reposter_Configuration', maybe_serialize($kbm_Reposter_Configuration));
 }

 function KBM_Reposter_move_oldest_post($kbm_Reposter_Catagory_ID) {
  $kbm_Reposter_Get_Post_Arguments = array('type' => 'post', 'numberposts' => 1, 'category' => $kbm_Reposter_Catagory_ID, 'status' => 'publish', 'orderby' => 'post_date', 'order' => 'ASC');
  $kbm_Reposter_Old_Posts = get_posts($kbm_Reposter_Get_Post_Arguments);
  if ($kbm_Reposter_Old_Posts) {
   foreach($kbm_Reposter_Old_Posts as $kbm_Reposter_Oldest_Post) {
    $kbm_Reposter_Oldest_Post->post_date = date_i18n('Y-m-d H:i:s');
    wp_update_post($kbm_Reposter_Oldest_Post);
   }
  }
 }
 
 function KBM_Reposter_options_page() {
  $kbm_Reposter_Options_Updated = false;
  $kbm_Reposter_Configuration = maybe_unserialize(get_option('kbm_Reposter_Configuration'));
  $kbm_Reposter_Catagory_ID_Array = get_all_category_ids();
  foreach($kbm_Reposter_Catagory_ID_Array as $kbm_Reposter_Catagory_ID) {
   if ($_REQUEST['kbm_Reposter_run_' . $kbm_Reposter_Catagory_ID]) {
    KBM_Reposter_move_oldest_post($kbm_Reposter_Catagory_ID);
   }
   $kbm_Reposter_Catagory_Name = get_cat_name($kbm_Reposter_Catagory_ID);
   $kbm_Reposter_Form_Name = 'kbm_Reposter_catagory_' . $kbm_Reposter_Catagory_ID;
   if ($_REQUEST["$kbm_Reposter_Form_Name"]) {
    if ($kbm_Reposter_Configuration["$kbm_Reposter_Catagory_Name" . '_Delay'] != $_REQUEST["$kbm_Reposter_Form_Name"]) {
     $kbm_Reposter_Options_Updated = true;
     if (($_REQUEST["$kbm_Reposter_Form_Name"]) == 1) {
      $_REQUEST["$kbm_Reposter_Form_Name"] = rand(43200, 172800);
      $kbm_Reposter_Configuration["$kbm_Reposter_Catagory_Name" . '_Next'] = time() +$_REQUEST["$kbm_Reposter_Form_Name"];
      $kbm_Reposter_Configuration["$kbm_Reposter_Catagory_Name" . '_Delay'] = 1;
     } else {
      $kbm_Reposter_Configuration["$kbm_Reposter_Catagory_Name" . '_Next'] = time() +$_REQUEST["$kbm_Reposter_Form_Name"];
      $kbm_Reposter_Configuration["$kbm_Reposter_Catagory_Name" . '_Delay'] = $_REQUEST["$kbm_Reposter_Form_Name"];
     }
    }
   }
  }
  update_option('kbm_Reposter_Configuration', maybe_serialize($kbm_Reposter_Configuration));
  if ($kbm_Reposter_Options_Updated == true) {
   echo '<div id="message" class="updated fade"><p><strong>Notice: ' . 'Options updated.' . '</strong></p></div>' . "\n";
  }
?>
<div class="wrap">
  <?php screen_icon();?>
  <h2>Reposter Reloaded</h2>
  <br class="clear" />
  <div id="poststuff" class="ui-sortable meta-box-sortables">
    <div id="secure_wp_win_settings" class="postbox" >
      <div class="handlediv" title="<?php _e('Click to toggle'); ?>"><br/>
      </div>
      <h3>Configuration</h3>
      <div class="inside">
        <p>
        	<?php
					$time_format = get_option('time_format');
  				echo ('<abbr title="Coordinated Universal Time">UTC</abbr> time is <code>'.date_i18n( $time_format, false, 'gmt').'</code>');
  				echo ('<br />');
  				echo ('Local time is <code>'.date_i18n($time_format).'</code>');
					?>
				</p>
  			<form action="" method="post">
				<?php wp_nonce_field ( 'update-options' ) ; ?>
          <table class="form-table">
            <tr valign="top">
              <th scope="row">Category</th>
              <td>Action</td>
              <td>Scheduled?</td>
              <td>Run Now?</td>
            </tr>
<?php
$kbm_Reposter_Catagory_ID_Array = get_all_category_ids();
foreach($kbm_Reposter_Catagory_ID_Array as $kbm_Reposter_Catagory_ID) {
 $kbm_Reposter_Catagory_Name = get_cat_name($kbm_Reposter_Catagory_ID);
 $kbm_Reposter_Catagory_Delay = $kbm_Reposter_Configuration[$kbm_Reposter_Catagory_Name . '_Delay'];
 echo '<tr valign="top"><th scope="row">' . $kbm_Reposter_Catagory_Name . '</th><td>' . "\n";
 echo '<select id=\'kbm_Reposter_catagory_' . $kbm_Reposter_Catagory_ID . '\' name=\'kbm_Reposter_catagory_' . $kbm_Reposter_Catagory_ID . '\' >' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay < 1) {
  echo 'selected="selected" ';
 }
 echo 'value="-1">Not Processed</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 21600) {
  echo 'selected="selected" ';
 }
 echo 'value="021600">Six Hours</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 43200) {
  echo 'selected="selected" ';
 }
 echo 'value="043200">Twelve Hours</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 86400) {
  echo 'selected="selected" ';
 }
 echo 'value="086400">Twenty Four Hours</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 172800) {
  echo 'selected="selected" ';
 }
 echo 'value="172800">Two Days</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 259200) {
  echo 'selected="selected" ';
 }
 echo 'value="259200">Three Days</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 432000) {
  echo 'selected="selected" ';
 }
 echo 'value="432000">Five Days</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 604800) {
  echo 'selected="selected" ';
 }
 echo 'value="604800">Seven Days</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 864000) {
  echo 'selected="selected" ';
 }
 echo 'value="864000">Ten Days</option>' . "\n";
 echo '<option ';
 if ($kbm_Reposter_Catagory_Delay == 1) {
  echo 'selected="selected" ';
 }
 echo 'value="1">Randomly 12-48 Hours</option>' . "\n";
 echo '</select>' . "\n";
 echo '</td>' . "\n";
 if ($kbm_Reposter_Catagory_Delay > 0) {
  if ($kbm_Reposter_Configuration[$kbm_Reposter_Catagory_Name . '_Next'] > 0) {
   echo '<td>Next: ' . date('Y-m-d H:i:s', $kbm_Reposter_Configuration[$kbm_Reposter_Catagory_Name . '_Next']) . ' (UTC time)</td>' . "\n";
  }
 } else {
  echo '<td> Not Scheduled </td>' . "\n";
 }
 echo '<td>Run Now <input type=\'checkbox\' id=\'kbm_Reposter_run_' . $kbm_Reposter_Catagory_ID . '\' name=\'kbm_Reposter_run_' . $kbm_Reposter_Catagory_ID . '\' /></td>' . "\n";
 echo '</tr>' . "\n";
}
?> 
          </table>
          <input type="hidden" name="action" value="update" />
          <input type="hidden" name="page_options" value=""/>
          <p class="submit">
            <input type="submit" name="Submit" value="Save Changes" />
          </p>
        </form>
      </div>
    </div>
    <div id="poststuff" class="ui-sortable meta-box-sortables">
      <div id="secure_wp_win_about" class="postbox" >
        <div class="handlediv" title="<?php _e('Click to toggle'); ?>"><br/>
        </div>
        <h3>About the plugin</h3>
        <div class="inside">
          <p> Reposter Reloaded recycles your posts on a schedule of your choosing. Reposter Reloaded will find the oldest post in a specific category, and re-date it with the current date and time. It does this on the schedule of your own choosing or randomly, which can be as frequently as every 6 hours or as spread out as 21 days.<br />
            Based on an idea from <a href="http://www.gurugazette.com/Reposter/">Kathy Burns-Millyard</a>. </p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
}
?>
