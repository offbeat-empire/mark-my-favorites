<?php
/*
Plugin Name: Mark My Favorites 
Description: Allow logged-in users to favorite posts, comments, custom post types, links, BuddyPress activity updates and comments, and bbPress topics and replies.
Version: 0.6
Author: Jennifer M. Dodd
Author URI: http://uncommoncontent.com/
Textdomain: mark-my-favorites
*/

/*
	Copyright 2012 Jennifer M. Dodd <jmdodd@gmail.com>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, see <http://www.gnu.org/licenses/>.
*/


if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! class_exists( 'UCC_Mark_My_Favorites' ) ) {
class UCC_Mark_My_Favorites {
	public static $instance;
	public static $version;
	public static $favorite_text;
	public static $unfavorite_text;

	public function __construct() {
		self::$instance = $this;
		$this->version = '2014070105';

		$this->favorite_text = apply_filters( 'ucc_mmf_favorite_text', __( 'Favorite', 'mark-my-favorites' ) );
		$this->unfavorite_text = apply_filters( 'ucc_mmf_unfavorite_text', __( 'Remove Favorite', 'mark-my-favorites' ) );

		// Front-end display.
		if ( ( ( is_admin() && defined('DOING_AJAX') && DOING_AJAX ) || ! is_admin() ) && is_user_logged_in() && apply_filters( 'ucc_mmf_auto_append', true ) ) {
			// WordPress compat.
			add_filter( 'the_content', array( $this, 'the_content' ), 15, 4 );
			add_filter( 'the_excerpt', array( $this, 'the_excerpt' ), 999, 1 );
			add_filter( 'comment_text', array( $this, 'comment_text' ), 15, 4 );

			// BuddyPress compat.
			add_action('bp_activity_add_user_favorite',array($this, 'add_activity_favorite'), 11, 2);
			add_action('bp_activity_remove_user_favorite',array($this, 'remove_activity_favorite'), 11, 2);
			add_filter('bp_get_activity_is_favorite',array($this,'activity_favorites_filter'));

			// bbPress compat.
			add_action( 'bbp_theme_after_topic_content', array( &$this, 'bbp_theme_after_content' ) );
			add_action( 'bbp_theme_after_reply_content', array( &$this, 'bbp_theme_after_content' ));

			// Front-end enqueues.
       			add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		}

		// Admin-side display.
		if ( is_admin() && apply_filters( 'ucc_mmf_auto_append_admin', true ) ) {
			// Maybe later.
		}

		// Regular form callbacks.
		add_action( 'wp', array( &$this, 'do_favorite' ) );

		// AJAX callbacks.
		add_action( 'wp_ajax_ucc_mmf_favorite', array( &$this, 'do_favorite' ) );
	}

	// WordPress included object types compat.
	public function the_content( $text ) {
		global $current_user, $post;

		if ( ! is_single() )
			return $text;

		if ( in_array( $post->post_type, apply_filters( 'ucc_mmf_the_content_post_type', array( 'post' ) ) ) ) {
			$user_id = $current_user->ID;
			$object_id = $post->ID;
			$object_ref = 20;

			$relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
			$meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

			if ( $meta_value > 0 )
				$mode = 'delete';
			else
				$mode = 'add';

			$form = $this->get_form( $mode, $object_id, $object_ref );
			return "{$text}\n\n<div class='ucc-mmf-container'>{$form}</div>";
		}

		return $text;
	}

	public function the_excerpt( $text ) {
                global $current_user, $post;

                if ( in_array( $post->post_type, apply_filters( 'ucc_mmf_the_content_post_type', array( 'post' ) ) ) ) {
                        $user_id = $current_user->ID;
                        $object_id = $post->ID;
                        $object_ref = 20;

                        $relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
                        $meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

                        if ( $meta_value > 0 )
                                $mode = 'delete';
                        else
                                $mode = 'add';

                        $form = $this->get_form( $mode, $object_id, $object_ref );
                        return "{$text}\n\n<div class='ucc-mmf-container'>{$form}</div>";
		}

                return $text;
        }

	public function comment_text( $text ) {
		global $current_user, $comment;
		$user_id = $current_user->ID;
		$object_id = $comment->comment_ID;
		$object_ref = 10;

		$relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
		$meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

		if ( $meta_value > 0 )
			$mode = 'delete';
		else
			$mode = 'add';

		$form = $this->get_form( $mode, $object_id, $object_ref );
		return "{$text}\n\n<div class='ucc-mmf-container'>{$form}</div>";
	}

	// bbPress compat.
	public function bbp_theme_after_content() {
		global $current_user;

		if ( bbp_is_topic() )
			$post = get_post( bbp_get_topic_id() );
		else
			$post = get_post( bbp_get_reply_id() );
		if ( in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) ) ) {
			$user_id = $current_user->ID;
			$object_id = $post->ID;
			$object_ref = 20;

			$relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
			$meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

			if ( $meta_value > 0 )
				$mode = 'delete';
			else
				$mode = 'add';

			$form = $this->get_form( $mode, $object_id, $object_ref );
			echo "\n\n<div class='ucc-mmf-container'>{$form}</div>";
		}

		return;
	}

	// BuddyPress Activity Stream compat.
	public function bp_activity_entry_meta() {
		global $current_user, $activities_template;
		$user_id = $current_user->ID;
		$activity = $activities_template->activity;

		// Make sure the activity's object_id is still valid.
		switch( $activity->type ) {
			case 'activity_update':
				$object_id = $activity->id;
				$object_ref = 100;
				break;
			case 'activity_comment':
				break;
			case 'new_blog_post':
				$object_id = $activity->secondary_item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == 'post' ) {
					// Awesome.
				} else {
					return;	
				}
				$object_ref = 20;
				break;
			case 'new_blog_comment':
				$object_id = $activity->secondary_item_id;
				$comment = get_comment( $object_id );
				if ( ! $comment )
					return;
				$object_ref = 10;
				break;
			case 'new_forum_topic':
			case 'new_forum_post':

			case 'bbp_topic_create':
				$object_id = $activity->item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == bbp_get_topic_post_type( $post->ID ) ) {
					// Awesome.
				} else {
					return;
				}
				$object_ref = 20;
				break;
			case 'bbp_reply_create':
				$object_id = $activity->item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == bbp_get_reply_post_type( $post->ID ) ) {
					// Awesome.
				} else {
					return;
				}
				$object_ref = 20;
				break;
			default:
				$object_id = null;
				$object_ref = null;
		}

		if ( empty( $object_id ) || empty( $object_ref ) )
			return;

		$relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
		$meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

		if ( $meta_value > 0 )
			$mode = 'delete';
		else
			$mode = 'add';

		$form = $this->get_form( $mode, $object_id, $object_ref );
		echo "<span class='ucc-mmf-container'>{$form}</span>";
	}

	// bbPress compat.

	public function get_form( $mode = 'add', $object_id, $object_ref ) {
		if ( empty( $object_id ) || empty( $object_ref ) )
			return false;

		if ( ! $object_id = absint( $object_id ) )
			return false;

		if ( ! $object_ref = absint( $object_ref ) )
			return false;

		global $ucc_uof_object_ref;

		$nonce = wp_create_nonce( '_ucc_mmf_nonce' );
		$count = get_metadata( $ucc_uof_object_ref[$object_ref], $object_id, '_ucc_mmf_votes', true );
		if ( $count === false )
			$count = 0;

		$favorite_text = $this->favorite_text;
		$unfavorite_text = $this->unfavorite_text;

		$count_text = apply_filters( 'ucc_mmf_favorite_count', sprintf( _n( '+ %d person', '+ %d people', $count, 'mark-my-favorites' ), $count ), $count );
		$default_user_input = apply_filters( 'ucc_mmf_favorite_default_input', "<input type='submit' name='ucc_mmf_favorite' value='{$favorite_text}' class='ucc-mmf-favorite fav button bp-secondary-action' /> {$count_text}", $favorite_text, $count_text );
		$selected_user_input = apply_filters( 'ucc_mmf_favorite_selected_input', "<input type='submit' name='ucc_mmf_favorite' value='{$unfavorite_text}' class='ucc-mmf-favorite unfav button bp-secondary-action' /> {$count_text}", $unfavorite_text, $count_text );
		if ( $mode == 'delete' )
			$user_input = $selected_user_input;
		else
			$user_input = $default_user_input;

		$form = "<form action='' method='post'>{$user_input}<input type='hidden' name='ucc_mmf_object_id' value='{$object_id}' class='ucc-mmf-object-id' /><input type='hidden' name='ucc_mmf_object_ref' value='{$object_ref}' class='ucc-mmf-object-ref' /><input type='hidden' name='ucc_mmf_nonce' value='{$nonce}' class='ucc-mmf-nonce' />";
		if ( $mode == 'add' )
			$form .= "<input type='hidden' name='ucc_mmf_mode' value='add' class='ucc-mmf-mode' />";
		else
			$form .= "<input type='hidden' name='ucc_mmf_mode' value='delete' class='ucc-mmf-mode' />";
		$form .= '</form>';

		return apply_filters( 'ucc_mmf_get_form', $form, $mode, $object_id, $object_ref );
	}

	public function get_link( $mode = 'add', $object_id, $object_ref ) {
		if ( empty( $object_id ) || empty( $object_ref ) )
			return false;

		if ( ! $object_id = absint( $object_id ) )
			return false;

		if ( ! $object_ref = absint( $object_ref ) )
			return false;

		global $ucc_uof_object_ref;

		$nonce = wp_create_nonce( '_ucc_mmf_nonce' );
		$count = get_metadata( $ucc_uof_object_ref[$object_ref], $object_id, '_ucc_mmf_votes', true );
		if ( $count === false )
			$count = 0;

		$favorite_text = $this->favorite_text;
		$unfavorite_text = $this->unfavorite_text;
		if ( $mode == 'delete' )
			$favorite_text = $unfavorite_text;

		$count_text = apply_filters( 'ucc_mmf_favorite_count', sprintf( _n( '+ %d person', '+ %d people', $count, 'mark-my-favorites' ), $count ), $count );

		$link = "?ucc_mmf_object_id={$object_id}&amp;ucc_mmf_object_ref={$object_ref}&amp;ucc_mmf_nonce={$nonce}";
		if ( $mode == 'add' )
			$link .= '&amp;ucc_mmf_mode=add';
		else
			$link .= '&amp;ucc_mmf_mode=delete';

		return apply_filters( 'ucc_mmf_get_link', $link, $mode, $object_id, $object_ref );
	}

	public function enqueue_scripts() {
		$nonce = wp_create_nonce( '_ucc_mmf_nonce' );
		wp_enqueue_script( 'ucc-mmf-favorite', plugins_url(). '/mark-my-favorites/includes/js/favorite.js', array( 'jquery' ), $this->version );
		wp_localize_script( 'ucc-mmf-favorite', 'ucc_mmf', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => $nonce ) );
		wp_enqueue_style( 'ucc-mmf-favorite', plugins_url() . '/mark-my-favorites/includes/css/favorite.css', false, $this->version );
	}

	//pass activity favorites on to the activity's object
	public function do_activity_favorite($activity_id, $user_id, $mode){
		global $mmf_needs_tick;
		//do nothing if Buddypress is not installed
		if(!function_exists('bp_activity_get_specific')) return;
		if($mmf_skip_tick === true) return;

		$activity_data = $this->get_object_id_from_activity_id($activity_id);
		extract($activity_data);

		if ( empty( $object_id ) || empty( $object_ref ) )
			return;

		$this->tick_favorite($mode,$user_id, $object_id, $object_ref);
		
	}

	function get_object_id_from_activity_id($activity_id){
		$activities = bp_activity_get_specific(array('activity_ids'=>$activity_id));
		$activity = $activities['activities'][0];

		// Make sure the activity's object_id is still valid.
		switch( $activity->type ) {
			case 'activity_update':
				$object_id = $activity->id;
				$object_ref = 100;
				break;
			case 'activity_comment':
				break;
			case 'new_blog_post':
				$object_id = $activity->secondary_item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == 'post' ) {
					// Awesome.
				} else {
					return;	
				}
				$object_ref = 20;
				break;
			case 'new_blog_comment':
				$object_id = $activity->secondary_item_id;
				$comment = get_comment( $object_id );
				if ( ! $comment )
					return;
				$object_ref = 10;
				break;
			case 'new_forum_topic':
			case 'new_forum_post':

			case 'bbp_topic_create':
				$object_id = $activity->item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == bbp_get_topic_post_type( $post->ID ) ) {
					// Awesome.
				} else {
					return;
				}
				$object_ref = 20;
				break;
			case 'bbp_reply_create':
				$object_id = $activity->item_id;
				$post = get_post( $object_id );
				if ( $post && $post->post_type == bbp_get_reply_post_type( $post->ID ) ) {
					// Awesome.
				} else {
					return;
				}
				$object_ref = 20;
				break;
			default:
				$object_id = null;
				$object_ref = null;
		}
		return array('object_id'=> $object_id, 'object_ref'=>$object_ref);
	}

	function add_activity_favorite($activity_id, $user_id){
		$this->do_activity_favorite($activity_id, $user_id, 'add');
	}
	function remove_activity_favorite($activity_id, $user_id){
		$this->do_activity_favorite($activity_id, $user_id, 'delete');
	}


	// Stacked to avoid return() versus exit() until the end.
	public function do_favorite() {
		$user_id = ucc_uof_get_user_id();
		$result = '';

		// Logged in.
		if ( is_user_logged_in() ) {
			if ( isset( $_REQUEST['ucc_mmf_nonce'] ) && isset( $_REQUEST['ucc_mmf_object_id'] ) && isset( $_REQUEST['ucc_mmf_object_ref'] ) ) {
				$nonce = $_REQUEST['ucc_mmf_nonce'];
				$object_id = $_REQUEST['ucc_mmf_object_id'];
				$object_ref = $_REQUEST['ucc_mmf_object_ref'];
				if ( wp_verify_nonce( $nonce, '_ucc_mmf_nonce' ) && ( $object_id = absint( $object_id ) ) && ( $object_ref = absint( $object_ref ) ) ) {
					$mode = ( isset( $_REQUEST['ucc_mmf_mode'] ) && $_REQUEST['ucc_mmf_mode'] == 'delete' ) ? 'delete' : 'add';
					$this->tick_favorite( $mode, $user_id, $object_id, $object_ref );

					if ( $mode == 'add' )
						$form = $this->get_form( 'delete', $object_id, $object_ref );
					else
						$form = $this->get_form( 'add', $object_id, $object_ref );
				}

				if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {
					$result = json_encode( array( 'newform' => $form ) );
					echo $result;
					die();
				} else {
					return $result;
				}

				// Failed all checks.
				if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) { 
					exit();
				} else {
					return;
				}
			}
		}
	}

	public function activity_favorites_filter(){
		global $activities_template;
		global $current_user;
		$vars = $this->get_object_id_from_activity_id($activities_template->activity->id);
		extract($vars);

		$relationship = ucc_uof_get_relationship($current_user->user_ID, 0, $object_id, $object_ref );
		$meta_value = get_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );

		if ( $meta_value > 0 )
			return true;
		else
			return false;


	}

	public function tick_favorite( $mode = 'add', $user_id = 0, $object_id = 0, $object_ref = 0 ) {
		global $wpdb;

		if ( ! $user_id = absint( $user_id ) )
			return false;

		if ( ! $object_id = absint( $object_id ) )
			return false;

		if ( ! $object_ref = absint( $object_ref ) )
			return false;

		global $ucc_uof_object_ref;
		if ( ! array_key_exists( $object_ref, $ucc_uof_object_ref ) )
			return false;

		switch ( $object_ref ) {
			case 10:
				$object = get_comment( $object_id );
				break;
			case 20:
				$object = get_post( $object_id );
				break;
			case 30:
				$object = get_user( $object_id );
				break;
			case 100:
				if ( function_exists( 'bp_activity_get_specific' ) )
					$object = bp_activity_get_specific( array( 'activity_ids' => $object_id ) );
				break;
			default:
				$object = false;
		}
		if ( ! $object )
			return false;

		$relationship = ucc_uof_get_relationship( $user_id, 0, $object_id, $object_ref );
		if ( empty( $relationship ) )
			$relationship = ucc_uof_add_relationship( $user_id, 0, $object_id, $object_ref );

		// Add user_object_meta.
		if ( $mode == 'delete' ) {
			update_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', false );
		} else {
			update_metadata( 'uof_user_object', $relationship, '_ucc_mmf_vote', true );
		}

		// Get object count.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->uof_user_object AS t1, $wpdb->uof_user_objectmeta AS t2
			WHERE t1.relationship_id = t2.uof_user_object_id
				AND t1.object_id = %d 
				AND t1.object_ref = %d
				AND t2.meta_key = %s
				AND t2.meta_value = 1",
			$object_id, $object_ref, '_ucc_mmf_vote' );
		$count = $wpdb->get_var( $sql );
		update_metadata( $ucc_uof_object_ref[$object_ref], $object_id, '_ucc_mmf_votes', $count );

		return $count;
	}
} }


function ucc_mmf_init() {
	// Only load if User Object Framework is present.
	if ( function_exists( 'ucc_uof_object_reference' ) ) {
		// User permissions.
		global $current_user;
		get_currentuserinfo();

		if ( is_user_logged_in() && apply_filters( 'ucc_mmf_user_can', true, $current_user->user_ID ) ) {
			// Ensure that native favorite does not run on AJAX queries.
			//add_filter( 'bp_activity_can_favorite', '__return_false' );

			load_plugin_textdomain( 'mark-my-favorites', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			new UCC_Mark_My_Favorites;
		}
	}
}

add_action( 'plugins_loaded', 'ucc_mmf_init', 15 );
