<?php

	/***
	***	@account automatically approved
	***/
	add_action('um_post_registration_approved_hook', 'um_post_registration_approved_hook', 10, 2);
	function um_post_registration_approved_hook($user_id, $args){
		global $ultimatemember;
		$ultimatemember->user->approve();
	}
	
	/***
	***	@account needs email validation
	***/
	add_action('um_post_registration_checkmail_hook', 'um_post_registration_checkmail_hook', 10, 2);
	function um_post_registration_checkmail_hook($user_id, $args){
		global $ultimatemember;
		$ultimatemember->user->email_pending();
	}
	
	/***
	***	@account needs admin review
	***/
	add_action('um_post_registration_pending_hook', 'um_post_registration_pending_hook', 10, 2);
	function um_post_registration_pending_hook($user_id, $args){
		global $ultimatemember;
		$ultimatemember->user->pending();
	}
	
	/***
	***	@add user to wordpress
	***/
	add_action('um_add_user_frontend', 'um_add_user_frontend', 10);
	function um_add_user_frontend($args){
		global $ultimatemember;
		extract($args);

		if ( isset( $user_email ) && !isset($user_login) ) {
			$user_login = $user_email;
		}
		
		if ( isset( $username ) && !isset($args['user_login']) ) {
			$user_login = $username;
		}

		if ( isset( $username ) && is_email( $username ) ) {
			$user_email = $username;
		}

		if (!isset($user_password)){
			$user_password = $ultimatemember->validation->generate();
		}
		
		$unique_userID = $ultimatemember->query->count_users() + 1;
		
		if( !isset($user_email) ) {
			$user_email = 'nobody' . $unique_userID . '@' . get_bloginfo('name');
		}
		
		if ( !isset( $user_login ) ) {
			$user_login = 'user' . $unique_userID;
		}
		
		$creds['user_login'] = $user_login;
		$creds['user_password'] = $user_password;
		$creds['user_email'] = $user_email;
	
		$args['submitted'] = array_merge( $args['submitted'], $creds);
		$args = array_merge($args, $creds);
		
		do_action('um_before_new_user_register', $args);
		
		$user_id = wp_create_user( $user_login, $user_password, $user_email );
		
		do_action('um_after_new_user_register', $user_id, $args);
		
		return $user_id;
	}
	
	/***
	***	@after adding a new user
	***/
	add_action('um_after_new_user_register', 'um_after_new_user_register', 10, 2);
	function um_after_new_user_register($user_id, $args){
		global $ultimatemember;
		extract($args);
		
		um_fetch_user( $user_id );
		
		if ( !isset( $args['role'] ) ) {
			$role = um_get_option('default_role');
		}

		$ultimatemember->user->set_role( $role );
		
		$ultimatemember->user->set_registration_details( $args['submitted'] );
		
		$ultimatemember->user->set_plain_password( $args['user_password'] );
		
		do_action('um_post_registration_save', $user_id, $args);
				
		do_action('um_post_registration_listener', $user_id, $args);
		
		do_action('um_post_registration', $user_id, $args);

	}
	
	/***
	***	@Update user's profile after registration
	***/
	add_action('um_post_registration_save', 'um_post_registration_save', 10, 2);
	function um_post_registration_save($user_id, $args){
		global $ultimatemember;
	
		unset( $args['user_id'] );
		$args['_user_id'] = $user_id;
		$args['is_signup'] = 1;
		
		do_action('um_user_edit_profile', $args);
		
	}
	
	/***
	***	@post-registration admin listender
	***/
	add_action('um_post_registration_listener', 'um_post_registration_listener', 10, 2);
	function um_post_registration_listener($user_id, $args){
		global $ultimatemember;

		if ( um_user('status') != 'pending' ) {
			$ultimatemember->mail->send( um_admin_email(), 'notification_new_user' );
		} else {
			$ultimatemember->mail->send( um_admin_email(), 'notification_review' );
		}
	
	}
	
	/***
	***	@post-registration procedure
	***/
	add_action('um_post_registration', 'um_post_registration', 10, 2);
	function um_post_registration($user_id, $args){
		global $ultimatemember;
		extract($args);
		
		$status = um_user('status');
		
		do_action("um_post_registration_global_hook", $user_id, $args);

		do_action("um_post_registration_{$status}_hook", $user_id, $args);

		if ( !is_admin() ) {
		
			do_action("track_{$status}_user_registration");
			
			if ( $status == 'approved' ) {
				
				$ultimatemember->user->auto_login($user_id);
				if ( um_user('auto_approve_act') == 'redirect_url' && um_user('auto_approve_url') !== '' ) exit( wp_redirect( um_user('auto_approve_url') ) );
				if ( um_user('auto_approve_act') == 'redirect_profile' ) exit( wp_redirect( um_user_profile_url() ) );
			
			}

			if ( $status != 'approved' ) {
			
				if ( um_user( $status . '_action' ) == 'redirect_url' && um_user( $status . '_url' ) != '' ) {
					exit( wp_redirect( um_user( $status . '_url' ) ) );
				}
				
				if ( um_user( $status . '_action' ) == 'show_message' && um_user( $status . '_message' ) != '' ) {
					$url = $ultimatemember->permalinks->add_query( 'message', $status );
					$url = $ultimatemember->permalinks->add_query( 'uid', um_user('ID') );
					exit( wp_redirect( $url ) );
				}
				
			}

		}

	}
	
	/***
	***	@new user registration
	***/
	add_action('um_user_registration', 'um_user_registration', 10);
	function um_user_registration($args){
		global $ultimatemember;
		
		do_action('um_add_user_frontend', $args);

	}
	
	/***
	***	@form processing
	***/
	add_action('um_submit_form_register', 'um_submit_form_register', 10);
	function um_submit_form_register($args){
		global $ultimatemember;
	
		if ( !isset($ultimatemember->form->errors) ) do_action('um_user_registration', $args);

		do_action('um_user_registration_extra_hook', $args );
		
	}
	
	/***
	***	@Register user with predefined role in options
	***/
	add_action('um_after_register_fields', 'um_add_user_role');
	function um_add_user_role($args){
		
		global $ultimatemember;
		
		if ( isset( $args['custom_fields']['role_select'] ) || isset( $args['custom_fields']['role_radio'] ) ) return;
		
		if (isset($args['role']) && !empty($args['role'])){
		
			echo '<input type="hidden" name="role" id="role" value="'.$args['role'].'" />';
			
		} else {
		
			$default_role = um_get_option('default_role');
			echo '<input type="hidden" name="role" id="role" value="'.$default_role.'" />';
			
		}
		
	}
	
	/***
	***	@Show the submit button (highest priority)
	***/
	add_action('um_after_register_fields', 'um_add_submit_button_to_register', 1000);
	function um_add_submit_button_to_register($args){
		global $ultimatemember;
		
		// DO NOT add when reviewing user's details
		if ( isset( $ultimatemember->user->preview ) && $ultimatemember->user->preview == true && is_admin() ) return;
		
		$primary_btn_word = $args['primary_btn_word'];
		$primary_btn_word = apply_filters('um_register_form_button_one', $primary_btn_word);
		
		$secondary_btn_word = $args['secondary_btn_word'];
		$secondary_btn_word = apply_filters('um_register_form_button_two', $secondary_btn_word);
		
		?>
		
		<div class="um-col-alt">
		
			<?php if ( isset($args['secondary_btn']) && $args['secondary_btn'] != 0 ) { ?>
			
			<div class="um-left um-half"><input type="submit" value="<?php echo $primary_btn_word; ?>" class="um-button" /></div>
			<div class="um-right um-half"><a href="<?php echo um_get_core_page('login'); ?>" class="um-button um-alt"><?php echo $secondary_btn_word; ?></a></div>
			
			<?php } else { ?>
			
			<div class="um-center"><input type="submit" value="<?php echo $primary_btn_word; ?>" class="um-button" /></div>
			
			<?php } ?>
			
			<div class="um-clear"></div>
			
		</div>
	
		<?php
	}
	
	/***
	***	@Show Fields
	***/
	add_action('um_main_register_fields', 'um_add_register_fields', 100);
	function um_add_register_fields($args){
		global $ultimatemember;
		
		echo $ultimatemember->fields->display( 'register', $args );
		
	}