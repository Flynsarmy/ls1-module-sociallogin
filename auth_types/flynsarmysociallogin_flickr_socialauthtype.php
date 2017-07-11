<?php
	class FlynsarmySocialLogin_Flickr_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
		public $info = array(
			'id' => 'Flickr',
			'name'=>'Flickr',
		);

		public function is_enabled()
		{
			return $this->get_config()->flickr_is_enabled ? true : false;
		}

		public function get_client()
		{
			require_once  dirname(__FILE__).'/../vendor/php-oauth-api/httpclient-2012-10-05/http.php';
			require_once  dirname(__FILE__).'/../vendor/php-oauth-api/oauth-api-2012-11-19/oauth_client.php';
			$Config = $this->get_config();

			$client = new oauth_client_class;
			$client->session_started = true;
			$client->server = 'Flickr';
			$client->redirect_uri = $this->get_callback_url();
			$client->client_id = $Config->flickr_app_id;
			$client->client_secret = $Config->flickr_secret;

			return $client;
		}

		public function get_login_url($options = array())
		{
			return $this->get_callback_url($options);
		}

		public function login()
		{
			$_GET = Phpr::$request->get_fields;

			$client = $this->get_client();

			if(($success = $client->Initialize()))
			{
				if(($success = $client->Process()))
				{
					if(strlen($client->access_token))
					{
						$success = $client->CallAPI(
							'http://api.flickr.com/services/rest/',
							'GET', array(
								'method'=>'flickr.test.login',
								'format'=>'json',
								'nojsoncallback'=>'1'
							), array('FailOnAccessError'=>true), $user);
					}
				}
				$success = $client->Finalize($success);
			}
			if($client->exit)
				exit;

			$display_name = array('');
			if ( !empty($user->display_name) )
				$display_name = explode(' ', $user->display_name, 2);
			if ( sizeof($display_name) != 2 ) $display_name[] = '';

			$response = array();

			//Move into Shop_Customer fields where possible
			$response['token'] = $user->user->id;

			//if ( !empty($user->email) ) $response['email'] = filter_var($user->email, FILTER_SANITIZE_EMAIL);
			//$response['first_name'] = $display_name[0];
			//$response['last_name'] = $display_name[1];

			return $response;
		}

		/**
		 * Builds the social login administration user interface
		 * For drop-down and radio fields you should also add methods returning
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 *
		 * @param $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host_obj, $context = null)
		{
			$host_obj->add_form_custom_area('descs_flickr')->tab('Flickr');
			$host_obj->add_field('flickr_is_enabled', 'Allow users to sign on with Flickr?', 'full', db_bool)->tab('Flickr')->renderAs(frm_checkbox);
			$host_obj->add_field('flickr_app_id', 'Key', 'full', db_text)->tab('Flickr')->renderAs(frm_text);
			$host_obj->add_field('flickr_secret', 'Secret', 'full', db_text)->tab('Flickr')->renderAs(frm_text);
		}
	}