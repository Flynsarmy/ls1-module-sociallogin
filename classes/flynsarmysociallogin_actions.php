<?php

	/**
	 * @has_documentable_methods
	 */
	class FlynsarmySocialLogin_Actions extends Cms_ActionScope
	{
		public function on_disassociate()
		{
			// Ensure a customer provider ID was supplied
			$customer_provider_id = post('customer_provider_id');
			if ( !intval($customer_provider_id) )
			{
				Phpr::$session->flash['error'] = "Unable to determine login provider.";
				return;
			}

			// Ensure customer provider exists
			$customer_provider = FlynsarmySocialLogin_Customer_Provider::create()->find( $customer_provider_id );
			if ( !$customer_provider )
			{
				Phpr::$session->flash['error'] = "Login provider not found.";
				return;
			}

			// Determine if they have the credentials to do this
			// Backend users can disassociate any provider
			if ( !Phpr::$security->getUser() )
			{
				// Not a backend user - ensure the provider we're disassociating
				// belongs to us
				$customer = Phpr::$frontend_security->getUser();

				if ( !$customer || $customer_provider->shop_customer_id != $customer->id  )
				{
					Phpr::$session->flash['error'] = "You do not have permission to do this.";
					return;
				}

			}

			$provider = $customer_provider->get_provider();
			$customer_provider->delete();

			if ( post('flash_disassociated') )
				Phpr::$session->flash['success'] = post('flash_disassociated', '');
			else
				Phpr::$session->flash['success'] = $provider->info['name'] . " successfully disassociated from your account.";
		}

		public function on_email_confirmation()
		{
			//Make sure all the data we need is available
			$module = Core_ModuleManager::findById('flynsarmysociallogin');
			$user_data = Phpr::$session->get('flynsarmysociallogin_user_data', array());
			if ( empty($user_data) )
			{
				Phpr::$session->flash['error'] = "Unable to determine login provider.";
				return;
			}
			$provider = FlynsarmySocialLogin_ProviderHelper::get_provider( $user_data['provider_id'] );
			if ( empty($provider) )
			{
				Phpr::$session->flash['error'] = "Unable to determine login provider.";
				return;
			}

			//Make sure the provider isn't already attached to anyone
			//$customer_provider = Db_DbHelper::object("
			//	SELECT * FROM flynsarmysociallogin_customer_providers
			//	WHERE provider_id=:provider_id AND provider_token=:provider_token",
			//	array(
			//		'provider_id' => $provider->info['id'],
			//		'provider_token' => $user_data['token'],
			//	)
			//);
			//if ( $customer_provider )
			//{
			//	Phpr::$session->flash['error'] = "Please log in via the login page.";
			//	return;
			//}

			if ( post('flynsarmysocialmedia_email_confirmation') )
			{
				$validation = new Phpr_Validation();
				$validation->add('email', 'Email')->fn('trim')->fn('mb_strtolower')->required()->Email('Please provide valid email address.');
				if (!$validation->validate($_POST))
					$validation->throwException();

				$customer = Shop_Customer::create()->find_by_email(post('email'));

				// A customer doesn't already exist for this email account.
				// Create one.
				if ( !$customer )
				{
					//Non-existing customers must have a first/last name
					$validation = new Phpr_Validation();
					$validation->add('first_name', 'First Name')->fn('trim')->required("Please specify a first name");
					$validation->add('last_name', 'Last Name')->fn('trim')->required("Please specify a last name");
					if ( post('password') || post('confirm_password') )
					{
						$validation->add('password', 'Password')->fn('trim')->required();
						$validation->add('confirm_password', 'Password Confirmation')->fn('trim')->matches('password', 'Password and confirmation password do not match.');
					}
					if (!$validation->validate($_POST))
						$validation->throwException();

					if ( post('first_name') )
						$user_data['first_name'] = post('first_name');
					if ( post('last_name') )
						$user_data['last_name'] = post('last_name');

					$customer = $module->create_new_customer(array_merge(array(
						'email' => post('email'),
					), $user_data));
					// $module->set_provider_customer($customer, $user_data, $provider, true);
					// Phpr::$session->remove('flynsarmysociallogin_user_data');

					// if (post('flash'))
					// 	Phpr::$session->flash['success'] = post('flash');

					// if ( post('redirect') )
					// 	Phpr::$response->redirect( post('redirect') );
				}
				// Existing customer: Set first and last name if they're provided
				else
				{
					if ( post('first_name') )
						$customer->first_name = post('first_name');
					if ( post('last_name') )
						$customer->last_name = post('last_name');

					if ( post('first_name') || post('last_name') )
						$customer->save();
				}

				//Attach the new provider and require teh customer validate their email
				//address
				$customer_provider = $module->set_provider_customer(
					$customer,
					$user_data,
					$provider,
					false
				);

				$template = System_EmailTemplate::create()->find_by_code('flynsarmysociallogin_associate_provider');
				if ( !$template )
				{
					Phpr::$session->flash['error'] = "Error, email template not found.";
					return;
				}

				$url = root_url(
					"flynsarmysociallogin_associate_email?confirm=".
						$customer_provider->id.
						$customer_provider->shop_customer_id.
						$customer_provider->provider_token,
					true
				);
				$message = $customer->set_customer_email_vars($template->content);
				$message = str_replace('{flynsarmysociallogin_provider_name}', $provider->info['name'], $message);
				$message = str_replace('{flynsarmysociallogin_associate_url}', $url, $message);
				$template->send_to_customer($customer, $message);
				Phpr::$session->remove('flynsarmysociallogin_user_data');

				if ( post('flash_associated') )
					Phpr::$session->flash['success'] = sprintf(post('flash_associated', ''), post('email'));
				else
					Phpr::$session->flash['success'] = $provider->info['name'] . " successfully associated with your account. An email confirmation has been sent to ".post('email');

				if ( post('redirect_associated') )
					Phpr::$response->redirect( post('redirect_associated') );
				return;
			}
		}
	}
?>