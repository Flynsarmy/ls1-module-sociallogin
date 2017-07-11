<?php
	class FlynsarmySocialLogin_Twitter_SocialAuthType extends FlynsarmySocialLogin_SocialAuthTypeBase
	{
		public $info = array(
			'id' => 'Twitter',
			'name'=>'Twitter',
		);

		public function is_enabled()
		{
			return $this->get_config()->twitter_is_enabled ? true : false;
		}

		public function get_login_url($options = array())
		{
			$options = array_merge(array(
				'provider' => $this->info['id'],
			), (array)$options);

			return root_url('/flynsarmysociallogin_provider_login?' . http_build_query($options), true);
		}

		public function send_login_request()
		{
			require_once dirname(__FILE__).'/../vendor/twitteroauth/twitteroauth.php';
			$Config = $this->get_config();

			/* Build TwitterOAuth object with client credentials. */
			$client = new TwitterOAuth($Config->twitter_app_id, $Config->twitter_secret);

			/* Get temporary credentials. */
			try
			{
				$request_token = $client->getRequestToken();
			}
			catch (Exception $ex)
			{
				return $this->set_error(array(
					'debug' => "Failed to retrieve Twitter request token. Check your API credentials",
					'customer' => "Could not connect to Twitter. Refresh the page or try again later."
				));
			}

			Phpr::$session->set('oauth_token', $request_token['oauth_token']);
			Phpr::$session->set('oauth_token_secret', $request_token['oauth_token_secret']);

			switch ($client->http_code) {
				case 200:
					/* Build authorize URL and redirect user to Twitter. */
					$url = $client->getAuthorizeURL(Phpr::$session->get('oauth_token'));
					header('Location: ' . $url);
					exit;
				default:
					return $this->set_error(array(
						'debug' => "Failed to retrieve Twitter request token. HTTP response " . $client->http_code,
						'customer' => "Could not connect to Twitter. Refresh the page or try again later."
					));
			}
		}

		public function login()
		{
			/* If the oauth_token is old redirect to the connect page. */
			if (isset($_REQUEST['oauth_token']) && Phpr::$session->get('oauth_token') !== $_REQUEST['oauth_token'])
				return $this->set_error(array(
					'debug' => "login(): oauth_token is old.",
					'customer' => "Your login session has expired. Please try again."
				));

			require_once dirname(__FILE__).'/../vendor/twitteroauth/twitteroauth.php';
			$Config = $this->get_config();

			/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
			$connection = new TwitterOAuth($Config->twitter_app_id, $Config->twitter_secret, Phpr::$session->get('oauth_token', ''), Phpr::$session->get('oauth_token_secret'));

			/* Request access tokens from twitter */
			$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
			/* $access_token will look like this:
			array(4) [
				oauth_token => '#########-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
				oauth_token_secret => 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
				user_id => '#########',
				screen_name => 'flynsarmy'
			]
			*/


			/* Save the access tokens. Normally these would be saved in a database for future use. */
			//Phpr::$session->set('access_token', $access_token);

			/* Remove no longer needed request tokens */
			Phpr::$session->remove('oauth_token');
			Phpr::$session->remove('oauth_token_secret');

			/* If HTTP response is 200 continue otherwise send to connect page to retry */
			if ($connection->http_code != 200)
				return $this->set_error(array(
					'debug' => "login(): HTTP response code was ".$connection->http_code." when trying to get access token.",
					'customer' => "",
				));

			$screen_name = explode(' ', $access_token['screen_name'], 2);
			$first_name = reset($screen_name);
			$last_name = sizeof($screen_name) > 1 ? end($screen_name) : '';

			return array(
				//Use their twitter user id as the unique identifier so if they
				//revoke the token and relogin we won't create a duplicate user
				'token' => $access_token['user_id'],
				'first_name' => $first_name,
				'last_name' => $last_name,
			);
		}

		public function after_registration(Shop_Customer $customer)
		{
			$Config = $this->get_config();
			if ( $Config->twitter_registration_redirect )
				Phpr::$response->redirect( $Config->twitter_registration_redirect );
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
			$host_obj->add_form_custom_area('descs_twitter')->tab('Twitter');
			$host_obj->add_field('twitter_is_enabled', 'Allow users to sign on with Twitter?', 'full', db_bool)->tab('Twitter')->renderAs(frm_checkbox);
			$host_obj->add_field('twitter_app_id', 'Consumer Key', 'full', db_text)->tab('Twitter')->renderAs(frm_text);
			$host_obj->add_field('twitter_secret', 'Consumer Secret', 'full', db_text)->tab('Twitter')->renderAs(frm_text);
			//$host_obj->add_field('twitter_registration_redirect', 'Page to redirect to on registration', 'full', db_text)->tab('Twitter')->renderAs(frm_dropdown)->comment("Twitter doesn't provide an email address or name so we need to redirect to a page where the user provides this information.");
		}

		public function get_twitter_registration_redirect_options($keyValue=-1)
		{
			$return = array();
			$pages = Db_DbHelper::queryArray('SELECT url, title FROM pages ORDER BY title');
			foreach ( $pages as $page )
				$return[ $page['url'] ] = $page['title'] . ' ('.$page['url'].')';
			array_unshift($return, '');

			return $return;
		}
	}