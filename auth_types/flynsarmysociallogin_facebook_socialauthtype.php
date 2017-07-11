<?php
	class FlynsarmySocialLogin_Facebook_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
	    protected $client;

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
			require_once  dirname(__FILE__).'/../vendor/autoload.php';

			$Config = $this->get_config();

			return new Hybridauth\Provider\Facebook([
                /**
                 * Required: Callback URL
                 *
                 * The callback url is the location where a provider (Google in this case) will redirect the use once they
                 * authenticate and authorize your application. For this example we choose to come back to this same script.
                 *
                 * Note that Hybridauth provides an utility function `Hybridauth\HttpClient\Util::getCurrentUrl()` that can
                 * generate the current page url for you and you can use it for the callback.
                 */
                'callback' => $this->get_callback_url(array()),

                /**
                 * Required*: Application credentials
                 *
                 * A set of keys used by providers to identify your website (analogous to a login and password).
                 * To acquire these credentials you'll have to register an application on provider's site. In the case of Google
                 * for instance, you can refer to https://support.google.com/cloud/answer/6158849
                 *
                 * Application credentials are only required by providers using OAuth 1 and OAuth 2.
                 */
                'keys' => [
                    'id'     => $Config->facebook_app_id,
                    'secret' => $Config->facebook_secret,
                ],

                /**
                 * Optional: Custom Scope
                 *
                 * Providers using OAuth 2 will requires to know the scope of the authorization a user is going to give to your
                 * application. Hybridauth's adapters will request a limited scope by default, however you may specify a custom
                 * value to overwrite default ones.
                 */
                'scope' => 'email, user_about_me',
            ]);
		}

        public function get_login_url($options = array())
        {
            Phpr::$session->set('flynsarmysociallogin_options', $options);

            $options = array_merge(array(
                'provider' => $this->info['id'],
            ), (array)$options);

            return root_url('/flynsarmysociallogin_provider_login?' . http_build_query($options), true);
        }

        public function send_login_request()
        {
            $this->login();
        }

		public function login()
		{
		    $fb = $this->get_client();

		    try {
                $fb->authenticate();
            } catch (Exception $e) {
                return $this->set_error(array(
                    'debug' => "login(): " . 'Graph returned an error authenticating: ' . $e->getMessage(),
                    'customer' => "An error occurred while attempting to log you in. Please try again later.",
                ));
            }

            try {
                $user = $fb->getUserProfile();
            } catch (Exception $e) {
                return $this->set_error(array(
                    'debug' => "login(): " . 'Graph returned an error retrieving user profile details: ' . $e->getMessage(),
                    'customer' => "An error occurred while attempting to log you in. Please try again later.",
                ));
            }

            $response = array();
            $response['token'] = $user->identifier;
            if ( $user->email )
                $response['email'] = $user->email;
            if ( $user->firstName )
                $response['first_name'] = $user->firstName;
            if ( $user->lastName )
                $response['last_name'] = $user->lastName;

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