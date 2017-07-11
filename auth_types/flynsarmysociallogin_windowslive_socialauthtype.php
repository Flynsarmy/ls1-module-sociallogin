<?php
	class FlynsarmySocialLogin_WindowsLive_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
		public $info = array(
			'id' => 'Windows_Live',
			'name'=>'Windows Live',
		);

		public function is_enabled()
		{
			return $this->get_config()->windowslive_is_enabled ? true : false;
		}

		public function get_client()
		{
			require_once  dirname(__FILE__).'/../vendor/php-oauth-api/httpclient-2013-02-20/http.php';
			require_once  dirname(__FILE__).'/../vendor/php-oauth-api/oauth-api-2013-06-05/oauth_client.php';
			$Config = $this->get_config();

			$client = new oauth_client_class;
			$client->session_started = true;
			$client->debug = 1;
			$client->debug_http = 1;
			$client->server = 'Microsoft';
			$client->redirect_uri = $this->get_callback_url();
			$client->client_id = $Config->windowslive_app_id;
			$client->client_secret = $Config->windowslive_secret;
			$client->scope = 'wl.basic wl.emails';

			return $client;
		}

		public function get_login_url($options = array())
		{
			return urldecode($this->get_callback_url($options));
		}

		public function login()
		{
			$_GET = Phpr::$request->get_fields;

			$client = $this->get_client();

			if(($success = $client->Initialize()))
			{
				if(($success = $client->Process()))
				{
					if(strlen($client->authorization_error))
					{
						$client->error = $client->authorization_error;
						$success = false;
					}
					elseif(strlen($client->access_token))
					{
						$success = $client->CallAPI(
							'https://apis.live.net/v5.0/me',
							'GET', array(), array('FailOnAccessError'=>true), $user);
					}
				}
				$success = $client->Finalize($success);
			}
			if($client->exit)
				exit;

			$response = array();

			//Move into Shop_Customer fields where possible
			$response['token'] = $user->id;
			if ( !empty($user->emails->account) ) $response['email'] = filter_var($user->emails->account, FILTER_SANITIZE_EMAIL);
			if ( !empty($user->emails->personal) ) $response['email'] = filter_var($user->emails->personal, FILTER_SANITIZE_EMAIL);
			if ( !empty($user->emails->business) ) $response['email'] = filter_var($user->emails->business, FILTER_SANITIZE_EMAIL);
			if ( !empty($user->emails->preferred) ) $response['email'] = filter_var($user->emails->preferred, FILTER_SANITIZE_EMAIL);
			if ( !empty($user->first_name) ) $response['first_name'] = $user->first_name;
			if ( !empty($user->last_name) ) $response['last_name'] = $user->last_name;

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
			$host_obj->add_form_custom_area('descs_windowslive')->tab('Live');
			$host_obj->add_field('windowslive_is_enabled', 'Allow users to sign on with Windows Live?', 'full', db_bool)->tab('Live')->renderAs(frm_checkbox);
			$host_obj->add_field('windowslive_app_id', 'Client ID', 'full', db_text)->tab('Live')->renderAs(frm_text);
			$host_obj->add_field('windowslive_secret', 'Client Secret', 'full', db_text)->tab('Live')->renderAs(frm_text);
		}
	}