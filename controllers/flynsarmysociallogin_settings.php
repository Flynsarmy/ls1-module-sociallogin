<?

	class FlynsarmySocialLogin_Settings extends Backend_SettingsController
	{
		public $app_tab = 'system';
		public $app_page = 'settings';
		public $app_module_name = 'System';

		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior, Cms_PageSelector';

		public $form_model_class = 'FlynsarmySocialLogin_Configuration';
		public $form_redirect = null;

		public function __construct()
		{
			parent::__construct();
		}

		public function index()
		{
			$this->app_page_title = 'Social Login Settings';

			try
			{
				$obj = new FlynsarmySocialLogin_Configuration();
				$this->viewData['form_model'] = $obj->load();
			}
			catch (exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}
		}

		protected function index_onSave()
		{
			try
			{
				$obj = new FlynsarmySocialLogin_Configuration();
				$obj = $obj->load();

				$is_new_record = $obj->is_new_record();
				if ( $is_new_record )
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $obj);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $obj);

				$obj->save(post($this->form_model_class, array()), $this->formGetEditSessionKey());

				if ( $is_new_record )
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $obj);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $obj);

				echo Backend_Html::flash_message('Configuration saved.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>