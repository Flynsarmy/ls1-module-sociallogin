<?php

	class FlynsarmySocialLogin_Customer_Provider extends Core_Configuration_Model
	{
		public $table_name = 'flynsarmysociallogin_customer_providers';

		public $belongs_to = array(
			'customer'=>array('class_name'=>'Shop_Customer', 'foreign_key'=>'shop_customer_id')
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('provider_id', 'Login Provider', db_varchar)->order('asc')->validation()->fn('trim');
			$this->define_column('provider_token', 'Login Provider Token', db_varchar)->validation()->fn('trim');
		}

		/**
		 * Find a CustomerProvider by the confirmation code
		 * @param  string $confirmation_code Confirmation code provided by an association email
		 * @return FlynsarmySocialLogin_Customer_Provider CustomerProvider associated with this confirmation code
		 */
		public static function find_by_confirmation_code( $confirmation_code )
		{
			return self::create()->where('is_enabled=?', 0)
				->where('CONCAT(id, shop_customer_id, provider_token)=?', $confirmation_code)
				->find();
		}

		/**
		 * Returns the Provider associated with this CustomerProvider
		 * @return Provider or null if missing or not enabled
		 */
		public function get_provider()
		{
			return FlynsarmySocialLogin_ProviderHelper::get_provider( $this->provider_id );
		}
	}

?>
