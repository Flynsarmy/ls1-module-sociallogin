<?php
	class FlynsarmySocialLogin_Google_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
		public $info = array(
			'id' => 'Google',
			'name'=>'Google',
		);

		public function is_enabled()
		{
			return $this->get_config()->google_is_enabled ? true : false;
		}

		public function get_client()
		{
			require_once  dirname(__FILE__).'/../vendor/google-api-php-client/src/Google_Client.php';
			require_once dirname(__FILE__).'/../vendor/google-api-php-client/src/contrib/Google_Oauth2Service.php';
			$Config = $this->get_config();

			$client = new Google_Client();
			$client->setApplicationName('LemonStand Social Login');
			$client->setApprovalPrompt('auto');
			//$client->setAccessType('online');
			// Visit https://code.google.com/apis/console?api=plus to generate your
			// oauth2_client_id, oauth2_client_secret, and to register your oauth2_redirect_uri.
			$client->setClientId($Config->google_app_id);
			$client->setClientSecret($Config->google_secret);
			$client->setRedirectUri(urldecode(
				$this->get_callback_url( /*Phpr::$session->get('flynsarmysociallogin_options', array())*/ )
			));
			// $client->setDeveloperKey('insert_your_developer_key');

			Google_Client::$io->setOptions(array(
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 2,
			));

			return $client;
		}

		public function get_login_url($options = array())
		{
			Phpr::$session->set('flynsarmysociallogin_options', $options);

			$client = $this->get_client();
			$oauth2 = new Google_Oauth2Service($client);

			return $client->createAuthUrl();
		}

		public function login()
		{
			$code = Phpr::$request->getField('code', '');
			if ( empty($code) )
				return $this->set_error(array(
					'debug' => "An error occurred. 'code' GET variable not found.",
					'customer' => "An error occurred communicating with the authentication server. Could not log you in.",
				));

			$client = $this->get_client();
			$oauth2 = new Google_Oauth2Service($client);

			try {
				$client->authenticate($code);
			} catch (Exception $e) {
				return $this->set_error(array(
					'debug' => 'Error. Provider responded with: ' . $e->getMessage() . ". Code was: $code. Session is " . print_r(Phpr::$session->get('flynsarmysociallogin_options', array()), true),
					'customer' => $e->getMessage(),
				));
			}

			$user = $oauth2->userinfo->get();
			$response = array();

			//Move into Shop_Customer fields where possible
			$response['token'] = $user['id'];
			if ( !empty($user['email']) ) $response['email'] = filter_var($user['email'], FILTER_SANITIZE_EMAIL);
			if ( !empty($user['given_name']) ) $response['first_name'] = $user['given_name'];
			if ( !empty($user['family_name']) ) $response['last_name'] = $user['family_name'];

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
			$host_obj->add_form_custom_area('descs_google')->tab('Google');
			$host_obj->add_field('google_is_enabled', 'Allow users to sign on with Google?', 'full', db_bool)->tab('Google')->renderAs(frm_checkbox);
			$host_obj->add_field('google_app_id', 'Client ID', 'full', db_text)->tab('Google')->renderAs(frm_text);
			$host_obj->add_field('google_secret', 'Client Secret', 'full', db_text)->tab('Google')->renderAs(frm_text);
		}
	}