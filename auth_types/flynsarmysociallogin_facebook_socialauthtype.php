<?php
	class FlynsarmySocialLogin_Facebook_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
		public $info = array(
			'id' => 'Facebook',
			'name'=>'Facebook',
		);

		public function is_enabled()
		{
			return $this->get_config()->facebook_is_enabled ? true : false;
		}

		public function get_client()
		{
			require_once  dirname(__FILE__).'/../vendor/facebook-php-sdk/src/facebook.php';
			Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER] = false;
			Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYHOST] = 2;
			$Config = $this->get_config();

			$client = new Facebook(array(
				'appId'  => $Config->facebook_app_id,
				'secret' => $Config->facebook_secret,
			));

			return $client;
		}

		public function get_login_url($options = array())
		{
			Phpr::$session->set('flynsarmysociallogin_options', $options);

			return $this->get_client()->getLoginUrl(array(
				'redirect_uri' => $this->get_callback_url($options),
				'scope' => array(
					'perms' => 'email',
				),
			));
		}

		public function login()
		{
			$client = $this->get_client();
			$user = $client->getUser();

			if ( !$user )
				return $this->set_error(array(
					'debug' => "login(): getUser() call failed to log us in.",
					'customer' => "An error occurred while attempting to log you in. Please try again later.",
				));

			try {
				// Proceed knowing you have a logged in user who's authenticated.
				$user_profile = $client->api('/me');
			} catch (FacebookApiException $e) {
				return $this->set_error(array(
					'debug' => "login(): Error grabbing user profile: ".$e->getMessage(),
					'customer' => "An error while attempting to log you in. Please try again.",
				));
			}

			$response = array();
			$response['token'] = $user_profile['id'];
			if ( !empty($user_profile['email']) ) $response['email'] = $user_profile['email'];
			if ( !empty($user_profile['first_name']) ) $response['first_name'] = $user_profile['first_name'];
			if ( !empty($user_profile['last_name']) ) $response['last_name'] = $user_profile['last_name'];

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
			$host_obj->add_form_custom_area('descs_facebook')->tab('Facebook');
			$host_obj->add_field('facebook_is_enabled', 'Allow users to sign on with Facebook?', 'full', db_bool)->tab('Facebook')->renderAs(frm_checkbox);
			$host_obj->add_field('facebook_app_id', 'App ID', 'full', db_text)->tab('Facebook')->renderAs(frm_text);
			$host_obj->add_field('facebook_secret', 'App Secret', 'full', db_text)->tab('Facebook')->renderAs(frm_text);
		}
	}