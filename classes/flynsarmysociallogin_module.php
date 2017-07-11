<?php
	class FlynsarmySocialLogin_Module extends Core_ModuleBase
	{
		protected static $debug = true;
		public $providers = null;

		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Social Login",
				"Allows users to register and login through Twitter, Facebook, Google and others",
				"Flynsarmy" );
		}

		public function listSettingsItems()
		{
			return array(
				array(
					'icon'=>'/modules/flynsarmysociallogin/resources/images/admin_icon.png',
					'title'=>'Social Login',
					'url'=>'/flynsarmysociallogin/settings',
					'description'=>'Allows users to register and login through Twitter, Facebook, Google and others',
					'sort_id'=>30,
					'section'=>'CMS'
				),
			);
		}

		public function register_access_points()
		{
			return array(
                'flynsarmysociallogin_provider_callback'=>'provider_callback',
				'flynsarmysociallogin_provider_login' => 'provider_login',
				'flynsarmysociallogin_associate_email'=>'associate_email',
			);
		}

		public function subscribeEvents()
		{
			Backend::$events->addEvent('shop:onExtendCustomerModel', $this, 'extend_customer_model');
			Backend::$events->addEvent('shop:onExtendCustomerPreviewToolbar', $this, 'extend_customer_toolbar');
			Backend::$events->addEvent('flynsarmysociallogin:show_customer_login_providers_modal', $this, 'show_customer_login_providers_modal');
			Backend::$events->addEvent('flynsarmysociallogin:on_disassociate_provider', $this, 'disassociate_provider');
		}

		public function extend_customer_model($model)
		{
			$model->add_relation('has_many', 'flynsarmysociallogin_customer_providers', array(
				'class_name'=>'FlynsarmySocialLogin_Customer_Provider',
				'foreign_key'=>'shop_customer_id',
				'order'=>'id',
				'delete' => true)
			);
			$model->define_multi_relation_column('flynsarmysociallogin_customer_providers', 'flynsarmysociallogin_customer_providers', 'Login Providers', '@provider_id')->defaultInvisible();
		}


		/**
		 * Disassociate provider in Customer Preview section of admin
		 */


		public function extend_customer_toolbar($controller, $customer)
		{
			$controller->renderPartial(dirname(__FILE__).'/../partials/_toolbar.htm', array(
				'customer'=>$customer
			));
		}

		public function show_customer_login_providers_modal($controller, $id, $include_container=true)
		{
			$customer = Shop_Customer::create()->find($id);
			$providers = $customer->flynsarmysociallogin_customer_providers;

			$controller->renderPartial(dirname(__FILE__).'/../partials/_login_providers_modal.htm', array(
				'customer' => $customer,
				'providers' => $providers,
				'include_container' => !empty($include_container),
			));
		}

		public function disassociate_provider($controller, $id)
		{
			$customer_provider = FlynsarmySocialLogin_Customer_Provider::create()->find(post('customer_provider_id'));

			if ( !$customer_provider )
				throw new Phpr_ApplicationException("Provider not found. Try re-opening the Login Providers modal.");

			$customer_provider->delete();

			$this->show_customer_login_providers_modal($controller, $id, false);
		}






		/**
		 * The confirmation URL a customer clicks when associating a provider with
		 * their LS account.
		 * @return Session flash message and redirect to homepage
		 */
		public function associate_email()
		{
			$_GET = Phpr::$request->get_fields;
			$config = FlynsarmySocialLogin_Configuration::create();
			$redirect_url = $config->email_confirmation_page_url('/');

			$confirmation_code = empty($_GET['confirm']) ? '' : $_GET['confirm'];
			if ( empty($confirmation_code) )
			{
				Phpr::$session->flash['error'] = "Invalid confirmation URL.";
				Phpr::$response->redirect($redirect_url);
				return;
			}

			$customer_provider = FlynsarmySocialLogin_Customer_Provider::find_by_confirmation_code($confirmation_code);

			if ( !$customer_provider )
			{
				Phpr::$session->flash['error'] = "Invalid confirmation code.";
				Phpr::$response->redirect(root_urL($redirect_url));
				return;
			}

			$customer_provider->is_enabled = 1;
			$customer_provider->save();
			$provider = $customer_provider->get_provider();

			Backend::$events->fireEvent('flynsarmysociallogin:onCustomerAssociated', $customer_provider->customer, $provider);

			//Log the customer in
			Phpr::$session->flash['success'] = 'Account successfully associated.';
			//Log the customer in
			try {
				Phpr::$frontend_security->customerLogin($customer_provider->shop_customer_id);
			} catch(Exception $e) {
				return $this->handle_error(array(
					'debug' => "associate_email(): Error while logging customer in: " . $e->getMessage(),
					'customer' => $e->getMessage()
				));
			}

			$redirect_url = $provider->get_success_redirect();
			header("Location: $redirect_url");
			exit;
		}

		/**
		 * Some providers require a special login page. Use a URL like:
		 * /flynsarmysociallogin_provider_login/?provider=PROVIDER_ID
		 */
		public function provider_login()
		{
			$provider_id = Phpr::$request->getField('provider', '');
			$provider = FlynsarmySocialLogin_ProviderHelper::get_provider($provider_id);
			if ( !$provider )
				return $this->handle_error(array(
					'debug' => "provider_login(): No provider of id '$provider_id' found or provider not enabled.",
					'customer' => "We were unable to determine who you were trying to log in with."
				));

			Phpr::$session->set('flynsarmysociallogin_options', Phpr::$request->get_fields);

			try {
				if ( !$provider->send_login_request() )
					return $this->handle_error($provider->get_error());
			} catch (Exception $e) {
				return $this->handle_error(array(
					'debug' => "provider_login(): Provider '$provider_id' error: " . $e->getMessage(),
					'customer' => $e->getMessage()
				));
			}
		}

		/**
		 * Handles login for a provider when it returns to our site with the relevant info
		 * @return void
		 */
		public function provider_callback()
		{
			$config = FlynsarmySocialLogin_Configuration::create();
			$provider_id = Phpr::$request->getField('hauth_done', '');
			$provider = FlynsarmySocialLogin_ProviderHelper::get_provider($provider_id);
			if ( !$provider )
				return $this->handle_error(array(
					'debug' => empty($provider_id) ?
						"provider_callback(): No hauth.done GET variable. Unable to determine provider" :
						"provider_callback(): No provider of id '$provider_id' found or provider not enabled.",
					'customer' => "We were unable to determine who you were trying to log in with."
				));

			// Save our options (including redirect URL) for later retrieval
			if ( !Phpr::$session->get('flynsarmysociallogin_options') )
				Phpr::$session->set('flynsarmysociallogin_options', Phpr::$request->get_fields);

			try {
				$user_data = $provider->login();
				if ( !is_array($user_data) )
					return $this->handle_error($provider->get_error());
			} catch ( Exception $e ) {
				return $this->handle_error(array(
					'debug' => "provider_callback(): Provider '$provider_id' error: " . $e->getMessage(),
					'customer' => $e->getMessage()
				));
			}

			$customer = $this->get_or_create_provider_customer( $provider, $user_data );

			//A customer wasn't found or created which means we're forcing emails
			//So redirect to forced email page
			if ( !$customer )
			{
				$user_data['provider_id'] = $provider->info['id'];
				Phpr::$session->set('flynsarmysociallogin_user_data', $user_data);
				Phpr::$response->redirect($config->email_confirmation_page_url('/'));
				return;
			}

			//Log the customer in
			try {
				Phpr::$frontend_security->customerLogin($customer->id);
			} catch(Exception $e) {
				return $this->handle_error(array(
					'debug' => "provider_callable(): Error while logging customer in: " . $e->getMessage(),
					'customer' => $e->getMessage()
				));
			}

			if ( !$provider->is_custom_success_redirect() && (!$customer->first_name || !$customer->last_name) )
			{
				$redirect_url = $config->name_confirmation_page_url( $provider->get_success_redirect() );
				Phpr::$response->redirect(root_url($redirect_url, true));
				return;
			}

			$redirect_url = $provider->get_success_redirect();

			header("Location: $redirect_url");
			exit;
		}

		/**
		 * Creates a customer for a given provider when an email is provided
		 */
		public function get_or_create_provider_customer( $provider, array $user_data )
		{
			$config = FlynsarmySocialLogin_Configuration::create();
			$customer = null;
			$insert_customer_provider = true;

			//Try to find an existing user with matching provider and token
			$customer_provider = FlynsarmySocialLogin_Customer_Provider::create()
				->where('provider_id=?', $provider->info['id'])
				->where('provider_token=?', $user_data['token'])
				->find();

			if ( $customer_provider )
			{
				//Customer has already associated but hasn't responded to the activation email
				if ( !$customer_provider->is_enabled )
					return false;

				$customer = Shop_Customer::create()->find( $customer_provider->shop_customer_id );
				//This account has a valid customer_provider attached. No need to waste DB calls rebuilding it
				if ( $customer )
					return $customer;
			}

			//Try to find a customer with this email if one was provided
			if ( !empty($user_data['email']) )
			{
				// Find the first registered customer with this email
				$customer = Shop_Customer::create()->find_registered_by_email($user_data['email']);
				// No registered customer found, find any customer with this email
				if ( !$customer )
					$customer = Shop_Customer::create()->find_by_email($user_data['email']);
				if ( $customer )
				{
					$this->set_provider_customer($customer, $user_data, $provider, true);
					return $customer;
				}
			}

			//If no email given and we're forcing emails, dont create an empty customer
			$email_confirmation_url = $config->email_confirmation_page_url('');
			if ( empty($user_data['email']) && !empty($email_confirmation_url) )
				return false;

			$customer = $this->create_new_customer($user_data);
			$this->set_provider_customer($customer, $user_data, $provider, true);

			Backend::$events->fireEvent('flynsarmysociallogin:onCustomerCreated', $customer, $provider);

			return $customer;
		}

		public function create_new_customer( array $user_data )
		{
			$config = FlynsarmySocialLogin_Configuration::create();

			//Existing customer not found, create one
			$customer = new Shop_Customer();
			$customer->disable_column_cache('front_end', false);
			$customer->init_columns_info('front_end');
			$customer->validation->focusPrefix = null;

			//If no email is provided, make the email field optional
			if ( empty($user_data['email']) )
				$customer->validation->getRule('email')->optional();
			if ( empty($user_data['first_name']) )
				$customer->validation->getRule('first_name')->optional();
			if ( empty($user_data['last_name']) )
				$customer->validation->getRule('last_name')->optional();

			$customer->generate_password();
			$shipping_params = Shop_ShippingParams::get();
			$customer->shipping_country_id = $shipping_params->default_shipping_country_id;
			$customer->shipping_state_id = $shipping_params->default_shipping_state_id;
			$customer->shipping_zip = $shipping_params->default_shipping_zip;
			$customer->shipping_city = $shipping_params->default_shipping_city;
			$customer->save($user_data);

			if ( $config->send_new_customer_password_email )
				$customer->send_registration_confirmation();

			return $customer;
		}

		public function set_provider_customer( $customer, $user_data, $provider, $is_enabled = true )
		{
			if ( !$customer || !$provider )
				return false;

			//Save the login provider info. Make sure it's only in the DB once
			Db_DbHelper::query("
				DELETE FROM flynsarmysociallogin_customer_providers
				WHERE shop_customer_id=:shop_customer_id AND provider_id=:provider_id",
				array(
					'shop_customer_id' => $customer->get_primary_key_value(),
					'provider_id' => $provider->info['id'],
				)
			);
			$customer_provider = new FlynsarmySocialLogin_Customer_Provider();
			$customer_provider->save(array(
				'shop_customer_id' => $customer->id,
				'provider_id' => $provider->info['id'],
				'provider_token' => $user_data['token'],
				'is_enabled' => $is_enabled ? 1 : 0,
			));

			return $customer_provider;
		}

		public function handle_error( array $messages )
		{
			if ( self::$debug )
				exit($messages['debug']);

			$config = FlynsarmySocialLogin_Configuration::create();
			$redirect_url = $config->error_page_url('login');
			Phpr::$session->flash['error'] = $messages['customer'];
			Phpr::$response->redirect(root_url($redirect_url));
		}
	}
?>