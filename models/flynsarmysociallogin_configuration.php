<?

	class FlynsarmySocialLogin_Configuration extends Core_Configuration_Model
	{
		public static $self;
		public $record_code = 'flynsarmysociallogin_configuration';
		protected $pages = array();

		public $belongs_to = array(
			'success_page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'success_page_id'),
			'email_confirmation_page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'email_configuration_page_id'),
			'name_confirmation_page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'name_confirmation_page_id'),
			'error_page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'error_page_id'),
		);

		public function __construct()
		{
			parent::__construct();
		}

		public static function create()
		{
			if ( !self::$self )
			{
				self::$self = new self();
				self::$self = self::$self->load();
			}

			return self::$self;
		}

		protected function build_form()
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_relation_column('success_page', 'success_page', 'Successful login redirect (Most commonly Home page)', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Successful login redirect (Most commonly Home page)');
			$this->add_field('success_page', 'Successful login redirect (Most commonly Home page)', 'full')->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm');
			// $this->add_field('success_page', 'Successful login redirect (Most commonly Home page)', 'full', db_varchar)->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->comment("Redirect to this page on successful login.")->validation()->required();
			$this->add_form_custom_area('missing_provider_info')->tab('Settings');

			$this->define_relation_column('email_confirmation_page', 'email_confirmation_page', 'Email confirmation page (See marketplace page)', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Email confirmation page (See marketplace page)');
			$this->add_field('email_confirmation_page', 'Email confirmation page (See marketplace page)', 'full')->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment("Redirect to this page on successful login if a customers email couldn't be retrieved by the login provider.  It is recommended you add the email confirmation page specified on the marketplace page.");
			// $this->add_field('email_confirmation_page', 'Email confirmation page (See marketplace page)', 'full', db_varchar)->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->comment("Redirect to this page on successful login if a customers email couldn't be retrieved by the login provider.  It is recommended you add the email confirmation page specified on the marketplace page.")->validation()->required();

			$this->define_relation_column('name_confirmation_page', 'name_confirmation_page', 'Name confirmation page (Most commonly Profile page)', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Name confirmation page (Most commonly Profile page)');
			$this->add_field('name_confirmation_page', 'Name confirmation page (Most commonly Profile page)', 'full')->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment("Redirect to this page on successful login if a customers name couldn't be retrieved by the login provider. Usually you'll want this to redirect to the profile page but you could also just add first and last name fields to checkout instead. Email confirmation page overrides this if no email is present. Don't use the email confirmation page specified on the marketplace page.");
			// $this->add_field('name_confirmation_page', 'Name confirmation page (Most commonly Profile page)', 'full', db_varchar)->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->comment("Redirect to this page on successful login if a customers name couldn't be retrieved by the login provider. Usually you'll want this to redirect to the profile page but you could also just add first and last name fields to checkout instead. Email confirmation page overrides this if no email is present. Don't use the email confirmation page specified on the marketplace page.")->validation()->required();

			$this->define_relation_column('error_page', 'error_page', 'Error page (Most commonly Login page)', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Error page (Most commonly Login page)');
			$this->add_field('error_page', 'Error page (Most commonly Login page)', 'full')->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment("Redirect to this page if an error occurred during login.");
			// $this->add_field('error_page', 'Error page (Most commonly Login page)', 'full', db_varchar)->tab('Settings')->renderAs(frm_dropdown)->optionsMethod('get_page_options')->comment("Redirect to this page if an error occurred during login.")->validation()->required();

			$this->add_field('send_new_customer_password_email', 'Send newly created customers password emails?', 'full', db_bool)->tab('Settings')->renderAs(frm_checkbox)->comment("When a visitor clicks their social login button a LemonStand customer for them if one doesn't already exist. This option will email the customer their automatically generated LemonStand account password allowing them to sign in with both the social button and an email/pw.");

			$providers = FlynsarmySocialLogin_ProviderHelper::get_providers();
			foreach ($providers as $class_name)
			{
				$obj = new $class_name();
				$obj->build_config_ui($this);
			}
		}

		public function success_page_url($default, $add_host_name_and_protocol = false)
		{
			return self::page_url_proxiable($this, 'success_page', $default, $add_host_name_and_protocol);
		}

		public function email_confirmation_page_url($default, $add_host_name_and_protocol = false)
		{
			return self::page_url_proxiable($this, 'email_confirmation_page', $default, $add_host_name_and_protocol);
		}

		public function name_confirmation_page_url($default, $add_host_name_and_protocol = false)
		{
			return self::page_url_proxiable($this, 'name_confirmation_page', $default, $add_host_name_and_protocol);
		}

		public function error_page_url($default, $add_host_name_and_protocol = false)
		{
			return self::page_url_proxiable($this, 'error_page', $default, $add_host_name_and_protocol);
		}

		public static function page_url_proxiable($proxy, $field, $default, $add_host_name_and_protocol = false)
		{
			// Theming is enabled - grab the selected field for the active theme
			if ( Cms_Theme::is_theming_enabled() )
				$page_url = Cms_PageReference::get_page_url($proxy, $field, $proxy->page_url);
			// Theming isn't enabled - check we have the field set and use it if we do
			else
				$page_url = $proxy->$field ? $proxy->$field : $default;

			return root_url(
				$page_url ? $page_url : $default,
				$add_host_name_and_protocol
			);
		}

		public function get_page_options($keyValue=-1)
		{
			$return = array(
				'' => 'Please Select'
			);
			$pages = Db_DbHelper::queryArray('SELECT url, title FROM pages ORDER BY title');
			foreach ( $pages as $page )
				$return[ $page['url'] ] = $page['title'] . ' ('.$page['url'].')';
			array_unshift($return, '');

			return $return;
		}

		public function add_field($code, $title, $side = 'full', $type = db_text, $options = array())
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$column = $this->define_column($code, $title);
			if(($type==db_date || $type==db_datetime) && array_key_exists('dateFormat', $options))
				$column->dateFormat($options['dateFormat']);
			if(($type==db_datetime || $type==db_time) && array_key_exists('timeFormat', $options))
				$column->timeFormat($options['timeFormat']);
			$column->validation();

			$form_field = $this->add_form_field($code, $side);
			$this->added_fields[$code] = $form_field->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state');

			return $form_field;
		}

		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$method_name = "get_{$db_name}_options";

			if ( method_exists($this, $method_name) )
				return $this->$method_name($current_key_value);

			$providers = FlynsarmySocialLogin_ProviderHelper::get_providers();

			foreach ($providers as $class_name)
			{
				$obj = new $class_name();
				if ( method_exists($obj, $method_name) )
					return $obj->$method_name($current_key_value);
			}

			throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
		}

		public function get_added_field_option_state($db_name, $key_value)
		{
			return $this->get_added_field_options($db_name, $key_value);
		}
	}

?>