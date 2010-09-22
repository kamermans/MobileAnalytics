<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: MobileAnalytics.php 2967 2010-09-21 21:26:01 terawurfl $
 * 
 * @category Piwik_Plugins
 * @package Piwik_MobileAnalytics
 */

/**
 *
 * @package Piwik_MobileAnalytics
 */
class Piwik_MobileAnalytics extends Piwik_Plugin{
	/**
	 * @var TeraWurflRemoteClient
	 */
	protected $wurflObj;
	public static $requiredCapabilities = array('is_wireless_device','brand_name','model_name');
	
	public function getInformation(){
		$info = array(
			'description' => 'Reports detailed information about mobile visitors.',
			'homepage' => 'http://www.tera-wurfl.com',
			'author' => 'Steve Kamerman',
			'author_homepage' => 'http://www.stevekamerman.com',
			'version' => '0.1',
			'TrackerPlugin' => true, // this plugin must be loaded during the stats logging
		);
		return $info;
	}
	
	public function getListHooksRegistered(){
		$hooks = array(
			'ArchiveProcessing_Day.compute' => 'archiveDay',
			'ArchiveProcessing_Period.compute' => 'archivePeriod',
			'Tracker.newVisitorInformation' => 'logMobileInfo',
			'WidgetsList.add' => 'addWidget',
			'Menu.add' => 'addMenu',
			'API.getReportMetadata' => 'getReportMetadata',
			'AdminMenu.add' => 'addAdminMenu'
		);
		return $hooks;
	}

	public function getReportMetadata($notification){
		$reports = &$notification->getNotificationObject();
		// Devices by Name
		$reports[] = array(
			'category' => 'Mobile Analytics',
			'name' => 'Mobile Analytics',
			'module' => 'MobileAnalytics',
			'action' => 'getDeviceName',
			'dimension' => 'Mobile Device',
		);
		// Devices by Brand
		$reports[] = array(
			'category' => 'Mobile Analytics',
			'name' => 'Mobile Analytics',
			'module' => 'MobileAnalytics',
			'action' => 'getDeviceBrand',
			'dimension' => 'Brand Name',
		);
		// Mobile vs. Non-mobile
		$reports[] = array(
			'category' => 'Mobile Analytics',
			'name' => 'Mobile Analytics',
			'module' => 'MobileAnalytics',
			'action' => 'getDeviceMobile',
			'dimension' => 'Mobile',
		);
	}
	
	function install(){
		// add columns to the visit table
		$query = "ALTER IGNORE TABLE `".Piwik_Common::prefixTable('log_visit')."`
		ADD `mobile` TINYINT( 1 ) NULL,
		ADD `mobile_brand` VARCHAR( 100 ) NULL,
		ADD `mobile_model` VARCHAR( 100 ) NULL,
		ADD `mobile_id` VARCHAR( 64 ) NULL";
		
		// if the column already exist do not throw error. Could be installed twice...
		try {
			Piwik_Exec($query);
		}catch(Exception $e){
			if(!Zend_Registry::get('db')->isErrNo($e, '1060')){
				throw $e;
			}
		}
		if(!self::settingsExist()) self::createSettings();
	}
	
	function uninstall(){
		// remove columns from the visit table
		$query = "ALTER TABLE `".Piwik_Common::prefixTable('log_visit')."` DROP `mobile`, DROP `mobile_brand`, DROP `mobile_model`, DROP `mobile_id`";
		Piwik_Exec($query);
	}
	
	function addWidget(){
		Piwik_AddWidget('Mobile Analytics', 'Devices by Model Name', 'MobileAnalytics', 'getDeviceName');
		Piwik_AddWidget('Mobile Analytics', 'Devices by Brand Name', 'MobileAnalytics', 'getDeviceBrand');
		Piwik_AddWidget('Mobile Analytics', 'Mobile vs. Desktop', 'MobileAnalytics', 'getDeviceMobile');
	}
	
	function addMenu(){
		Piwik_AddMenu('Mobile Analytics', '', array('module' => 'MobileAnalytics', 'action' => 'index'), true, 30);
	}
	
	function addAdminMenu(){
		Piwik_AddAdminMenu('Moblie Analytics', 
							array('module' => 'MobileAnalytics', 'action' => 'adminMenu'),
							Piwik::isUserIsSuperUser(),
							$order = 30);
	}
	
	function postLoad(){
		Piwik_AddAction('template_headerMobileDevices', array('Piwik_MobileAnalytics','headerMobileDevices'));
		Piwik_AddAction('template_footerMobileDevices', array('Piwik_MobileAnalytics','footerMobileDevices'));
	}

	function archivePeriod( $notification ){
		$maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
		$archiveProcessing = $notification->getNotificationObject();
		// Devices by Name
		$dataTableToSum = array( 'MobileAnalytics_mobileDevices' );
		$archiveProcessing->archiveDataTable($dataTableToSum, null, $maximumRowsInDataTable);
		// Devices by Brand
		$dataTableToSum = array( 'MobileAnalytics_mobileBrands' );
		$archiveProcessing->archiveDataTable($dataTableToSum, null, $maximumRowsInDataTable);
		// Devices by mobile/non-mobile
		$dataTableToSum = array( 'MobileAnalytics_mobile' );
		$archiveProcessing->archiveDataTable($dataTableToSum, null, $maximumRowsInDataTable);
	}

	/**
	 * Daily archive: processes the report Visits by Mobile Device
	 */
	function archiveDay($notification){
		$archiveProcessing = $notification->getNotificationObject();
		// Devices by Name
		$recordName = 'MobileAnalytics_mobileDevices';
		$labelSQL = "mobile_model";
		$interestByProvider = $archiveProcessing->getArrayInterestForLabel($labelSQL);
		$tableProvider = $archiveProcessing->getDataTableFromArray($interestByProvider);
		$columnToSortByBeforeTruncation = Piwik_Archive::INDEX_NB_VISITS;
		$maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
		$archiveProcessing->insertBlobRecord($recordName, $tableProvider->getSerialized($maximumRowsInDataTable, null, $columnToSortByBeforeTruncation));
		destroy($tableProvider);
		// Devices by Brand
		$recordName = 'MobileAnalytics_mobileBrands';
		$labelSQL = "mobile_brand";
		$interestByProvider = $archiveProcessing->getArrayInterestForLabel($labelSQL);
		$tableProvider = $archiveProcessing->getDataTableFromArray($interestByProvider);
		$columnToSortByBeforeTruncation = Piwik_Archive::INDEX_NB_VISITS;
		$maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
		$archiveProcessing->insertBlobRecord($recordName, $tableProvider->getSerialized($maximumRowsInDataTable, null, $columnToSortByBeforeTruncation));
		destroy($tableProvider);
		// Devices by mobile/non-mobile
		$recordName = 'MobileAnalytics_mobile';
		$labelSQL = "mobile";
		$interestByProvider = $archiveProcessing->getArrayInterestForLabel($labelSQL);
		$tableProvider = $archiveProcessing->getDataTableFromArray($interestByProvider);
		$columnToSortByBeforeTruncation = Piwik_Archive::INDEX_NB_VISITS;
		$maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
		$archiveProcessing->insertBlobRecord($recordName, $tableProvider->getSerialized($maximumRowsInDataTable, null, $columnToSortByBeforeTruncation));
		destroy($tableProvider);
	}
	
	/**
	 * Logs the Mobile Device in the log_visit table
	 */
	public function logMobileInfo($notification){
		if(self::trackerGetSetting('TeraWurflMode') == "disabled"){
			return;
		}
		//error_log("Piwik error with plugin: MobileAnalytics: Tera-WURFL settings are not correct, mobile devices will not be tracked until Tera-WURFL can be contacted.");
		$visitorInfo =& $notification->getNotificationObject();
		try {
			$this->initTeraWurfl();
			$visitorInfo['mobile'] = $this->wurflObj->getDeviceCapability('is_wireless_device')? 1: 0;
			$visitorInfo['mobile_brand'] = $this->wurflObj->getDeviceCapability('brand_name');
			$visitorInfo['mobile_model'] = $visitorInfo['mobile_brand'] . ' ' . $this->wurflObj->getDeviceCapability('model_name');
			$visitorInfo['mobile_id'] = $this->wurflObj->capabilities['id'];
		}catch(Exception $e){
			error_log($e->getMessage());
		}
	}
	
	protected function initTeraWurfl(){
		if(!$this->wurflObj){
			$mode = self::trackerGetSetting('TeraWurflMode');
			switch($mode){
				case 'TeraWurflRemoteClient':
				case 'TeraWurflEnterpriseRemoteClient':
					require_once PIWIK_INCLUDE_PATH . '/plugins/MobileAnalytics/TeraWurflRemoteClient.php';
					$this->wurflObj = new $mode(self::trackerGetSetting('TeraWurflURL'));
					@$this->wurflObj->getDeviceCapabilitiesFromAgent(null,self::$requiredCapabilities);
					break;
				case 'TeraWurfl':
				case 'TeraWurflEnterprise':
					require_once self::trackerGetSetting('TeraWurflPath');
					$this->wurflObj = new $mode();
					$this->wurflObj->getDeviceCapabilitiesFromAgent(null);
					break;
			}
		}
	}

	public static function headerMobileDevices($notification){
		$out =& $notification->getNotificationObject();
		$out = '<div id="leftcolumn">';
	}
	
	public static function footerMobileDevices($notification){
		$out =& $notification->getNotificationObject();
		$out = '</div>
			<div id="rightcolumn">
			<h2>Mobile Devices</h2>';
		$out .= Piwik_FrontController::getInstance()->fetchDispatch('MobileAnalytics','getDeviceName');
		$out .= '</div>';
	}
	public static function trackerGetSetting($name){
		return Piwik_Tracker_Config::getInstance()->MobileAnalytics[$name];
	}
	/**
	 * Check if the MobileAnalytics settings exist
	 * @return bool MobileAnalytics settings exist
	 */
	public static function settingsExist(){
		if(!@isset(Zend_Registry::get('config')->MobileAnalytics->TeraWurflMode)){
			return false;
		}
		return true;
	}
	/**
	 * Adds an empty set of settings to the config file.
	 * @return void
	 */
	public static function createSettings(){
		$config = Zend_Registry::get('config');
		$config->MobileAnalytics = array(
			"TeraWurflURL"=>"http://yourserver/Tera-WURFL/webservice.php",
			"TeraWurflPath"=>"/var/www/html/TeraWurfl/TeraWurfl.php",
			"TeraWurflMode"=>"disabled"
		);
		$config->__destruct();
		Piwik::createConfigObject();
	}
	
	public static function saveSetting($name,$value){
		if(!self::settingsExist()) self::createSettings();
		$config = Zend_Registry::get('config');
		$mobile = $config->MobileAnalytics->toArray();
		$mobile[$name] = $value;
		$config->MobileAnalytics = $mobile;
		$config->__destruct();
		Piwik::createConfigObject();
	}

}
