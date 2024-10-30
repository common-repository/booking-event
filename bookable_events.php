<?php
/*
	Plugin Name: Booking-events
	Plugin URI: 
	Description: This plugin allows you to create a bookable event using the custom fields. A post creator can make a post 'bookable' selecting the custom field 'bookable_event' (automatically created at the plugin activation) and setting a value for it, e.g. the 'yes' value.You can also limit the booking, for example at day of event. To do this setting custom field 'bookable_event' as data, for example, 2010-01-31  .
	The registered users can add/remove their participation to an event (represented by a post) by links at the bottom of the post.
	This plugin provides also a widget displaying the list of the last 'bookable events' added. Edited by Giovanni Caputo
	Version: 1.1.1
	Author: Francesca 'sefran' Secondo and edited by Giovanni Caputo
	Author URI: http://giovannicaputo.netsons.org
*/

/*
	Copyright 2009  Francesca Secondo  (email : sefran2 (at) gmail.com)
	 Giovanni Caputo  (email : giovannicaputo86 (at) gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
	Global vars for this plugin:
	- $bookable_key: name of the custom field used to make a post 'bookable'. This custom field have to be unique.
	- $participant_key: name of the custom field used to memorize the event participants' username. This custom field can be not unique.
	They are declared 'global' so the functions called by register_activation_hook() and register_deactivation_hook() have access to global variables.

	- $bookable: true if the post is 'bookable'.
	- $click_register: true if the user clicks on the registration link.
	- $click_unregister: true if the user clicks on the unregistration link.
	- $postid_clicked: post ID the user registers/unregisters for/from
*/

global $bookable_key;
global $participant_key;
$bookable_key = 'bookable_event';
$participant_key = 'participant'; 

$bookable;
$click_register = $_GET['click_register'];
$click_unregister = $_GET['click_unregister'];
$postid_clicked = $_GET['post'];


function utentiNonPermessi(){
$usr= array("");
 return $usr;
}
/*Aggiunte da Giovanni Caputo  */

function utentePuoRegistrarsi(){
    $loginRegister= utentiNonPermessi();
	$fs_current_user = wp_get_current_user();
	$meta_value = $fs_current_user->user_login;
	if (!empty($loginRegister)){
	   if (in_array( $meta_value, $loginRegister )) return false;
	}
	return true;
   
}

function untenteGiaPrenotato(){
   global $participant_key;
  global $bookable;
  global $postid_clicked;
   
  $mykey_values = get_post_custom_values($participant_key); 
  $num_partecipanti = count($mykey_values);
  $registrato=false;
  
  $fs_current_user = wp_get_current_user();
  $nomeUt = $fs_current_user->user_login;
		
  for ($i=0; $i<$num_partecipanti; $i++) {
     if ($mykey_values[$i] == $nomeUt) $registrato=true;

  }

 return $registrato;  
}


function visInviaMail(){
 $vis="";
 $user = wp_get_current_user();
 
 if ($user->user_level == 10){
	 $vis.="<div class=''><b>".__("Send mail to partecipants", "booking-event")."</b> <br /><form action='' method='post' >";
	 
	 $vis.=__("Subject", "booking-event"). ": <br /><input name='oggetto' type='text' value='".__("Subject", "booking-event"). "' size='50' maxlength='50' />  <br />";
	 $vis.=__("Message", "booking-event" ).":<BR /> <TEXTAREA NAME='msg' COLS=40 ROWS=6>".__("Message", "booking-event")."</TEXTAREA><br />";
	 $vis.="<input type='submit' value='". __("Send", "booking-event"). "'  name='mailPartepitants' />";
	 
	 $vis.=" <input type='hidden' name='postID' value='".get_the_ID()."'>";
	 $vis.="</form></div>";
 }
 return $vis;
}

function partecipantsList(){
  global $participant_key;
  global $bookable;
  global $postid_clicked;
  if ( $bookable && is_user_logged_in() && utentePuoRegistrarsi() ) {
	$mykey_values = get_post_custom_values($participant_key); 
				
	$num_partecipanti = count($mykey_values);
		
	if ($num_partecipanti) {
		$content=__("Registrered users for this event:", 'booking-event');
		
		$content=$content."<div class='listUt'>";
		
		for ($i=0; $i<$num_partecipanti; $i++) {
		   $nome=$mykey_values[$i];
		   $u=get_userdatabylogin($nome);
		   
		    if ($u!=null)  // se l 'utente non registrato
			   $vis=$u->display_name;
			else $vis=$nome;
		   
		   $avtr = get_avatar($u->ID); 
		   
		   $utdaStampare="<div class='usPrenotato'><div class='avatarPrenotato'>".$avtr." </div><div class='nomePrenotato'>".$vis."</div></div>";

			$content = "$content ".$utdaStampare;
		}
		$content=$content."</div><br />(".__("in total", 'booking-event')." ".$num_partecipanti.").";
	} else {
			$content="$content<br/>".__("No registered users for this event.", 'booking-event');
	 }
	 return $content;
  }
}


/*
	Internationalization
*/

$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( 'booking-event', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );

//load_plugin_textdomain( 'bookable-events', 'wp-content/plugins/bookable-events/lang' );


/* 
	Function to be run when the plugin is activated. 
	It adds the custom field 'bookable_event', in this way the post creator can select it (setting any value for it), making the post 'bookable'.
*/
function bookable_events_activate() {
	global $bookable_key;
	$bookable_value = '';
	$unique = true; // There can be only one custom field named 'bookable_event'
	
	$allposts = get_posts('numberposts=0&post_type=post&post_status=');
	
	foreach( $allposts as $postinfo ) {
		add_post_meta( $postinfo->ID, $bookable_key, $bookable_value, $unique );
    }
}

/* 
	Function to be run when the plugin is deactivated. 
	It removes all the custom fields added by the plugin ('bookable_event','participant'). It also makes unavailable the "Last bookable events" widget.
*/
function bookable_events_deactivate() {
	global $bookable_key;
	global $participant_key;

	$allposts = get_posts('numberposts=0&post_type=post&post_status=');
	
	foreach( $allposts as $postinfo ) {
		delete_post_meta( $postinfo->ID , $bookable_key );
		delete_post_meta( $postinfo->ID , $participant_key );
    }

	// Makes unavailable the "Last bookable events" widget
	unregister_sidebar_widget( __("Last bookable events", 'bookable-events') );
	// Removes the 'bookable_events_widget_control' callback
	unregister_widget_control( __("Last bookable events", 'bookable-events') );
}

/*
	Returns the xhtml registration link.
*/
function add_link_registration() {
	return __("If you want to register for this event, click on the following link after login.", 'booking-event')."<br/><b><a href='$PHP_SELF?click_register=true&post=".get_the_ID()."'>".__("Register!", 'booking-event')."</a></b>";
}

/*
	Returns the xhtml unregistration link.
*/
function add_link_unregistration() {
	return __("If you want to unregister from this event, click on the following link after login.", 'booking-event')."<br/><b><a href='$PHP_SELF?click_unregister=true&post=".get_the_ID()."'>".__("Unregister!", 'booking-event')."</a></b>";
}

/*
	Checks if a post is bookable.
*/
function is_bookable() {
	global $bookable_key;
	global $bookable;
	$meta = get_post_custom();
	$meta_book_event = $meta[$bookable_key][0];

	if ( $meta_book_event != '' ) return $bookable = true;
	else return $bookable = false;
}




/*check if date on bookable_event is passed from today */

function is_passed(){

   global $bookable_key;
	global $bookable;
	$meta = get_post_custom();
	$date = $meta[$bookable_key][0];
	
	if ( strtotime($date)==FALSE )  return false; 

   $todays_date =  time();
   
   
    $dateArr = explode("-",$date);

    $date1Int = mktime(0,0,0,$dateArr[1],$dateArr[2],$dateArr[0]) ;
     
   
   
   if (  $todays_date >= $date1Int ) { return true; } else { return false; } 
   
   
   
}





/*
	Adds the registration/unregistration links at the bottom of the 'bookable' post. Moreover, it adds the 'bookable event' label at the post beginning.
*/
function add_links_to_post( $content = '' ) {
	  
	  if ( is_bookable() && !post_password_required() && utentePuoRegistrarsi()  )
       {
	     if (!isset($_GET['click_register']) && !isset($_GET['click_unregister'])){
		    $content = "<div class='redtext'>".__("bookable event", 'booking-event')."</div>$content<br/><div class='event'>";
			
		    if ( !untenteGiaPrenotato()  && !is_passed()  ){
		        $content.=add_link_registration()."<br/>";
		
		    }
		    if ( untenteGiaPrenotato() && !is_passed() ){
		        $content.=add_link_unregistration()."<br/>";
		     }

     		 $content.= partecipantsList()."<br />".visInviaMail()."</div>";
			 
		 }
		 
	     
       }else{
	      
	   }
	   
	   
	return $content;
}

/*
	Function to be run when the user clicks on the registration link. 
	It checks the user is logged and, if so, adds it to the event participants.
*/
function register_to_event( $content = '' ) {
	global $participant_key;
	global $bookable;
	global $postid_clicked;
	if ( $bookable && is_user_logged_in() && utentePuoRegistrarsi() ) {
		$fs_current_user = wp_get_current_user();
		$mykey_values = get_post_custom_values($participant_key);
		$meta_value = $fs_current_user->user_login;
		$unique = false;
			
		$user_in_array = in_array( $meta_value, (array)$mykey_values );
		
		if ( !$user_in_array && ( get_the_ID() == $postid_clicked ) ) {
			$content = "$content<br/><div class='mgs_t1'>$fs_current_user->user_login, ".__("thank you for the registration!", 'booking-event')."</div><br/>";
			
             				
			
			 
			add_post_meta($postid_clicked, $participant_key, $meta_value, $unique);	
			
			$num_partecipanti = count($mykey_values);
			 $num_partecipanti++;
			@wp_mail( get_option('admin_email'), __("Booking Event", "booking-event").  html_entity_decode(get_the_title(get_the_ID())). " Tot: ". $num_partecipanti." ".__("User", "booking-event"). __("User", "booking-event") . $meta_value. __("is booking at event", "booking-event"). html_entity_decodeml_entity_decode(get_the_title(get_the_ID())), $headers);

			
			$content = "<div class='redtext'>".__("bookable event", 'booking-event')."</div>$content<br/><div class='event'>";
			
		    if ( !untenteGiaPrenotato() ){
		        $content.=add_link_registration()."<br/>";
		
		    }
		    if ( untenteGiaPrenotato() ){
		        $content.=add_link_unregistration()."<br/>";
		     }
			
			
			$content.= "<div class='event'>".partecipantsList()."</div>".visInviaMail()."</div><br /><br/>";
					

		} elseif ( $user_in_array && ( get_the_ID() == $postid_clicked ) ) {
			$content = "$content<br/><div class='mgs_t1'>$fs_current_user->user_login, ".__("you are already registered!", 'booking-event')."</div><br/>";
		}

	} elseif ( ( $bookable && !is_user_logged_in() ) && ( get_the_ID() == $postid_clicked ) ) {
		$content = "$content<br/><div class='mgs_t1'>".__("You must be logged in to register for this event!", 'booking-event')."</div><br/>";
	}
	return $content;
}

/*
	Function to be run when the user clicks on the unregistration link.
	It checks the user is logged and, if so, removes it from the event participants.
*/
function unregister_from_event( $content = '' ) {
	global $participant_key;
	global $bookable;
	global $postid_clicked;

	if ( $bookable && is_user_logged_in() && utentePuoRegistrarsi()) {
		$fs_current_user = wp_get_current_user();
		$mykey_values = get_post_custom_values($participant_key);
		$meta_value = $fs_current_user->user_login;
			
		$user_in_array = in_array( $meta_value, (array)$mykey_values );
		if ( !$user_in_array && ( get_the_ID() == $postid_clicked ) ) {
			$content = "$content<br/><div class='mgs_t1'>$fs_current_user->user_login, ".__("you have never been registered for this event!", 'booking-event')."</div><br/>";
		} elseif ( $user_in_array && ( get_the_ID() == $postid_clicked ) ) {
			delete_post_meta($postid_clicked, $participant_key, $meta_value);
			
			
			$num_partecipanti = count($mykey_values);
			 $num_partecipanti--;
			@wp_mail( get_option('admin_email'), __("Cancel Booking", "booking-event"). html_entity_decode(get_the_title(get_the_ID())). __("Tot", "booking-event").  ": ". $num_partecipanti.   __("Users", "booking-event"), __("User", "booking-event"). $meta_value. __(" have cancelled booking for event ", "booking-event").html_entity_decode(get_the_title(get_the_ID())), $headers);
			
			
			$content = "<div class='redtext'>".__("bookable event", 'booking-event')."</div>$content<br/><div class='event'>";
			
		    if ( !untenteGiaPrenotato() ){
		        $content.=add_link_registration()."<br/>";
		
		    }
		    if ( untenteGiaPrenotato() ){
		        $content.=add_link_unregistration()."<br/>";
		     }
			$content.= "<div class='event'>".partecipantsList()."</div>".visInviaMail()."</div><br /><br/>";
			
			$content = "$content<br/><br/>";
		}
	
	} elseif ( ( $bookable && !is_user_logged_in() ) && ( get_the_ID() == $postid_clicked ) ) {
		$content = "$content<br/><div class='mgs_t1'>".__("You must be logged in to unregister from this event!", 'booking-event')."</div><br/>";
	}
	return $content;
}

function my_css() {
	if ( !defined('WP_PLUGIN_URL') ) $my_css_file = get_bloginfo( 'url' )."/wp-content/plugins/bookable-events/bookable_events.css";
	else $my_css_file = WP_PLUGIN_URL.'/bookable-events/bookable_events.css';
	echo "<link type='text/css' rel='stylesheet' href='$my_css_file' />";
}

/*
	Registers the "Last bookable events" widget, in this way it will be included in the widgets palette. 
	Moreover, it adds the output of the 'bookable_events_widget_control' function to the admin interface as an inline popup.
*/
function bookable_events_widget_init() {
	$widget_options = array( 'classname' => 'widget_recent_bookable_events' );
	register_sidebar_widget(__("Last bookable events", 'booking-event'), 'bookable_events_widget', $widget_options);   
	register_widget_control(__("Last bookable events", 'booking-event'),'bookable_events_widget_control', 250,350);    
}







/*
	Outputs the content of the "Last bookable events" widget, that is the titles of 'bookable post'. 
*/
function bookable_events_widget($args) {
	global $bookable_key;
	global $participant_key;

	extract($args);
	$options = get_option('widget_recent_bookable_events');

	$title = empty($options['title']) ? __("Last bookable events", 'booking-event') : apply_filters('widget_title', $options['title']);
	if ( !$number = (int) $options['number'] )
		$number = 5;
	else if ( $number < 1 )
		$number = 1;
	else if ( $number > 10 )
		$number = 10;

    echo $before_widget;
    echo $before_title . $title . $after_title;

	$allposts = get_posts('numberposts=0&post_type=post&post_status=');

	echo "<ul>";
	$count = 0;
	foreach( $allposts as $postinfo ) {
		//if ( post_password_required( $postinfo->ID ) ) {
			$meta = get_post_custom($postinfo->ID);
			$meta_book_event = $meta[$bookable_key][0];
		
			if ( $meta_book_event != '' ) {
				if ( !post_password_required( $postinfo->ID ) ) {
					$participants_values = get_post_custom_values($participant_key, $postinfo->ID);
					$num_partecipanti = count($participants_values);
					if ($num_partecipanti) {
						$participants = $participants_values[0];
						for ($i=1; $i<$num_partecipanti; $i++) $participants = $participants.", ".$participants_values[$i];
						$participants = $participants.".";
					} else $participants = __("No registered users", 'booking-event');
				} else $participants = __("Protected post", 'booking-event');

				echo "<li><a href=".get_permalink($postinfo->ID)." title=\"$participants\">".$postinfo->post_title."</a></li>";
				$count++;
			}
			if ( $count > $number-1 ) break;
		//}
	}
	echo "</ul>";
	
	echo $after_widget; 
}

/*
	Display and process recent bookable events widget options form.
*/
function bookable_events_widget_control() {

	$options = $newoptions = get_option('widget_recent_bookable_events');

	if ( isset($_POST["recent-bookable-events-submit"]) ) {
		$newoptions['title'] = strip_tags(stripslashes($_POST["recent-bookable-events-title"]));
		$newoptions['number'] = (int) $_POST["recent-bookable-events-number"];
	}

	if ( $options != $newoptions ) {
		$options = $newoptions;
		update_option('widget_recent_bookable_events', $options);
	}

	$title = attribute_escape($options['title']);

	if ( !$number = (int) $options['number'] ) $number = 5;
	

	echo "<p><label for='recent-bookable-events-title'>".__("Title", 'bookable-events')."<input class='widefat' id='recent-bookable-events-title' name='recent-bookable-events-title' type='text' value=\"$title\" /></label></p>
			<p>
				<label for='recent-bookable-events-number'>".__("Number of posts to show", 'booking-event')."<input style='width: 25px; text-align: center;' id='recent-bookable-events-number' name='recent-bookable-events-number' type='text' value=\"$number\" /></label>
				<br />
				<small>".__("(at most 10)", 'booking-event')."</small>
			</p>
			<input type='hidden' id='recent-bookable-events-submit' name='recent-bookable-events-submit' value='1' />";
}

/* 
	Registration of the plugin function 'bookable_events_activate' to be run when the plugin is activated. 
*/
register_activation_hook( __FILE__, 'bookable_events_activate' );

/* 
	Registration of the plugin function 'bookable_events_deactivate' to be run when the plugin is deactivated. 
*/
register_deactivation_hook( __FILE__, 'bookable_events_deactivate' );


/*
	Loads my css 
*/
add_action( 'wp_head', 'my_css' );
//add_action( 'wp_print_styles', 'my_css');

/* 
	Addition of the filter function 'add_links_to_post' to the 'the_content' (so the post content is modified as it is displayed in the browser screen).
	The registration/unregistration links are added to a 'bookable' post. Moreover, the 'bookable event' label is added at the post beginning.
*/
add_filter( 'the_content', 'add_links_to_post' );

/* 
	Addition of the filter function 'register_to_event' to the 'the_content' if the user clicks on the registration link.
*/
if ($click_register) {
	add_filter( 'the_content', 'register_to_event' );
}

/* 
	Addition of the filter function 'unregister_from_event' to the 'the_content' if the user clicks on the unregistration link.
*/
if ($click_unregister) {
	add_filter( 'the_content', 'unregister_from_event' );
}


   add_action( 'init', 'inviaMail' );


function inviaMail(){

  if (isset($_POST['mailPartepitants'])){
   global $participant_key;
    
   $idPost=$_POST['postID'];
      
  $mykey_values = get_post_custom_values($participant_key, $idPost); 
  $num_partecipanti = count($mykey_values);
  
  
  $fs_current_user = wp_get_current_user();
  $nomeUt = $fs_current_user->user_login;
		
  for ($i=0; $i<$num_partecipanti; $i++) {
     //if ($mykey_values[$i] == $nomeUt)  // utente attuale 
	  
	 $nome=$mykey_values[$i];
	 $u=get_userdatabylogin($nome);
	 if ($u!=null){  // se l 'utente non registrato (xk� aggiunto custum filed manualemente)
	     $mail=$u->user_email;
        if ($mail!=null)
		wp_mail( $mail, $_POST['oggetto'], $_POST['msg'], $headers );
	 }	
		
		
  }
  
}

}


/*
	Hooks the 'bookable_events_widget_init' function to widgets_init, so this function will be run when WordPress loads the list of widgets.
*/
add_action('widgets_init', 'bookable_events_widget_init');

?>