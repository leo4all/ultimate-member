<?php

class UM_Rewrite {

	function __construct() {
		
		add_filter('query_vars', array(&$this, 'query_vars'), 10, 1 );
		
		add_action('init', array(&$this, 'rewrite_rules'), 100000000 );
		
		add_action('template_redirect', array(&$this, 'redirect_author_page'), 9999 );
		
		add_action('template_redirect', array(&$this, 'locate_user_profile'), 9999 );
		
	}
	
	/***
	***	@modify global query vars
	***/
	function query_vars($public_query_vars) {
		$public_query_vars[] = 'um_user';
		$public_query_vars[] = 'um_tab';
		$public_query_vars[] = 'profiletab';
		$public_query_vars[] = 'subnav';
		return $public_query_vars;
	}
	
	/***
	***	@setup rewrite rules
	***/
	function rewrite_rules(){

		global $ultimatemember;
		
		if ( isset( $ultimatemember->permalinks->core['user'] ) ) {
		
			$user_page_id = $ultimatemember->permalinks->core['user'];
			$account_page_id = $ultimatemember->permalinks->core['account'];
			
			$user = get_post($user_page_id);
			$user_slug = $user->post_name;

			$account = get_post($account_page_id);
			$account_slug = $account->post_name;
			
			add_rewrite_rule(
				'^'.$user_slug.'/([^/]*)$',
				'index.php?page_id='.$user_page_id.'&um_user=$matches[1]',
				'top'
			);
			
			add_rewrite_rule(
				'^'.$account_slug.'/([^/]*)$',
				'index.php?page_id='.$account_page_id.'&um_tab=$matches[1]',
				'top'
			);
			
			if ( !get_option('um_flush_rules') ) {
				flush_rewrite_rules( true );
				update_option('um_flush_rules', true);
			}

		}
		
	}
	
	/***
	***	@author page to user profile redirect
	***/
	function redirect_author_page() {
		if ( um_get_option('author_redirect') && is_author() ) {
			$id = get_query_var( 'author' );
			um_fetch_user( $id );
			exit( wp_redirect( um_user_profile_url() ) );
		}
	}
		
	/***
	***	@locate/display a profile
	***/
	function locate_user_profile() {
		global $post, $ultimatemember;
		
		if ( um_queried_user() && um_is_core_page('user') ) {
		
			if ( um_get_option('permalink_base') == 'user_login' ) {
				
				$user_id = username_exists( um_queried_user() );
				
				// Try nice name
				if ( !$user_id ) {
					$slug = um_queried_user();
					$slug = str_replace('.','-',$slug);
					$the_user = get_user_by( 'slug', $slug );
					if ( isset( $the_user->ID ) ){
						$user_id = $the_user->ID;
					}
				}
				
			}
			
			if ( um_get_option('permalink_base') == 'user_id' ) {
				$user_id = $ultimatemember->user->user_exists_by_id( um_queried_user() );

			}
			
			if ( um_get_option('permalink_base') == 'name' ) {
				$user_id = $ultimatemember->user->user_exists_by_name( um_queried_user() );

			}
			
			/** USER EXISTS SET USER AND CONTINUE **/
			
			if ( $user_id ) {
				
				um_set_requested_user( $user_id );
				
			} else {

				exit( wp_redirect( um_get_core_page('user') ) );
				
			}
			
		} else if ( um_is_core_page('user') ) { // just base64_decode
		
			if ( is_user_logged_in() ) { // just redirect to their profile
				
				$query = $ultimatemember->permalinks->get_query_array();
				
				$url = um_user_profile_url();
				
				if ( $query ) {
					foreach( $query as $key => $val ) {
						$url = add_query_arg($key, $val, $url);
					}
				}
				
				exit( wp_redirect( $url ) );
			}
			
		}

	}

}