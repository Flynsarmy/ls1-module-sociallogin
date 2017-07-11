<?

	class FlynsarmySocialLogin_Customers extends Backend_Controller
	{
		public $app_tab = 'flynsarmysociallogin';
		public $app_page = 'customers';
		public $app_module_name = "Social Login";

		public $implement = 'Db_FormBehavior';

		protected $required_permissions = array('flynsarmymailchimp:manage_lists_and_subscribers');

		public function __construct()
		{
			parent::__construct();
		}

		public function index()
		{
			$this->app_page_title = 'Subscribers';
			$this->viewData['body_class'] = 'resource_manager';

			//Cache lists
			try {
				$lists = $this->get_lists(true);
			} catch (Exception $ex) {
				Phpr::$session->flash['error'] = $ex->getMessage();
				$this->viewData['fatalError'] = true;
				//$this->handlePageError($ex);
				return;
			}
			if ( !Phpr::$session->get('flynsarmymailchimp_cur_resource_folder') && sizeof($lists) )
				Phpr::$session->set(
					'flynsarmymailchimp_cur_resource_folder',
					$lists[0]['id']
				);
		}

		public function index_onShowLoginProvidersModal()
		{
			exit('hi');
		}
	}

?>