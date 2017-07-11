<?

	/**
	 * Represents the generic payment type.
	 * All other payment types must be derived from this class
	 */
	abstract class FlynsarmySocialLogin_SocialAuthTypeBase extends Core_XmlController
	{
		public $config;
		public $error = array(
			'debug' => 'No error given.',
			'customer' => 'An unknown error occurred. Please try again shortly.',
		);
		public $info = array(
			'id' => 'unknown',
			'name' => 'Unknown Provider',
		);

		abstract public function is_enabled();

		/*
		 * Handles login for the provider callback URL
		 * @return array($user_details) on success, false on failure
		 * $user_details should contain a field called 'token'
		 */
		abstract public function login();

		/**
		 * Returns the URL used to log in with this provider
		 *
		 * @param  array  $options
		 *
		 * @return string $url
		 */
		abstract public function get_login_url($options = array());

		/**
		 * Builds the payment type administration user interface
		 * For drop-down and radio fields you should also add methods returning
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 *
		 * @param $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		abstract public function build_config_ui($host_obj, $context = null);

		/**
		 * Returns a cached copy of the SocialLogin configuration model
		 */
		public function get_config()
		{
			if ( !$this->config )
				$this->config = FlynsarmySocialLogin_Configuration::create();

			return $this->config;
		}

		/**
		 * Perform an action after a $customer has been registered and signed in
		 */
		public function after_registration(Shop_Customer $customer)
		{

		}

        /**
         * The options used to generate the callback URL.
         *
         * @param array $options
         * @return array
         */
		public function get_callback_options($options = array())
        {
            $config = FlynsarmySocialLogin_Configuration::create();
            return array_merge(array(
                'hauth.done' => $this->info['id'],
                'success_redirect' => $config->success_page_url('/', true),
            ), (array)$options);
        }

		/**
		 * The URL on our site that OAuth requests will respond to with login details
		 * @param $redirect URL to redirect to on successful login
		 */
		public function get_callback_url($options = array())
		{
			$options = $this->get_callback_options($options);

			return root_url('/flynsarmysociallogin_provider_callback/?'.http_build_query($options), true);
		}

		public function is_custom_success_redirect()
		{
			$options = Phpr::$session->get('flynsarmysociallogin_options', array());

			return isset($options['success_redirect']);
		}

		/**
		 * Determine which page we'll redirect to on successful login.
		 *
		 * @return [type] [description]
		 */
		public function get_success_redirect()
		{
			$options = Phpr::$session->get('flynsarmysociallogin_options', array());
			// No custom URL. Either our session expired or none was set.
			if ( isset($options['success_redirect']) )
				$options['success_redirect'] = urldecode($options['success_redirect']);
			else
			{
				$config = FlynsarmySocialLogin_Configuration::create();
				$options['success_redirect'] = $config->success_page_url('/');
			}

			return $options['success_redirect'];
		}

		public function set_error(array $messages)
		{
			$this->error = $messages;
			return false;
		}

		public function get_error()
		{
			return $this->error;
		}
	}

?>