<?php

class FlynsarmySocialLogin_ProviderHelper
{
	public static $providers = null;
	public static $active_providers = array();

	/**
	 * Returns a list of providers. Providers must be in a module
	 * /provider folder and must follow the naming convention
	 * <module_name>_<provider_id>_provider.php
	 * @return array of provider class names
	 */
	public static function get_providers()
	{
		if (self::$providers !== null)
			return self::$providers;

		$modules = Core_ModuleManager::listModules();
		foreach ($modules as $module_id=>$module_info)
		{
			$class_path = PATH_APP."/modules/".$module_id."/auth_types";
			if (file_exists($class_path))
			{
				$iterator = new DirectoryIterator($class_path);

				foreach ($iterator as $file)
				{

					if (!$file->isDir() && preg_match('/^'.$module_id.'_[^\.]*\.php$/i', $file->getFilename()))
						require_once($class_path.'/'.$file->getFilename());
				}
			}
		}

		$classes = get_declared_classes();
		self::$providers = array();
		foreach ($classes as $class)
		{
			if (preg_match('/_SocialAuthType$/i', $class) && get_parent_class($class) == 'FlynsarmySocialLogin_SocialAuthTypeBase')
				self::$providers[] = $class;
		}

		return self::$providers;
	}

	/**
	 * Finds a given provider
	 * @param $id the id of the provider declared in get_info
	 * @param $only_enabled discard non-enabled providers from the search
	 * @return provider object on success, null on failure
	 */
	public static function get_provider($id, $only_enabled=true)
	{
		if ( empty($id) )
			return null;

		$providers = FlynsarmySocialLogin_ProviderHelper::get_providers();
		foreach ($providers as $class_name)
		{
			$provider = new $class_name();
			if ( $provider->info['id'] == $id )
			{
				if ( $only_enabled && !$provider->is_enabled() )
					return null;
					// return $this->handle_error(array(
					// 	'debug' => "Provider '$id' is not found or enabled.",
					// 	'customer' => "We were unable to determine who you were trying to log in with."
					// ));

				return $provider;
			}
		}

		return null;
	}

	/**
	 * Returns a list of active Provider objects
	 * @param (optional) array $order - array of provider_ids
	 * @return array of provider objects
	 */
	public static function get_active_providers($order=array())
	{
		if ( !self::$active_providers )
		{
			$active_providers = array();
			$providers = self::get_providers();
			foreach ($providers as $class_name)
			{
				$obj = new $class_name();
				if ( $obj->is_enabled() )
					$active_providers[] = $obj;
			}

			//Cache the provider obj list
			self::$active_providers = $active_providers;
		}

		return $order ?
			self::sort_active_providers( self::$active_providers, $order ) :
			self::$active_providers;
	}

	/**
	 * Returns a list of Provider objects in a given sort order. If any
	 * providers don't appear in the order list they'll appear unsorted at
	 * the end
	 * @param array $providers - list of provider objects
	 * @param array $order - array of provider ids
	 * @return array of provider objects
	 */
	public static function sort_active_providers( array $providers, array $order )
	{
		$new_order = array();
		if ( !$providers )
			return $new_order;

		foreach ( $order as $provider_id )
			foreach ( $providers as $key=>$provider )
				if ( $provider->info['id'] == $provider_id )
				{
					$new_order[] = $provider;
					unset($providers[$key]);
				}

		if ( sizeof($providers) )
			$new_order = array_merge($new_order, $providers);

		return $new_order;
	}
}