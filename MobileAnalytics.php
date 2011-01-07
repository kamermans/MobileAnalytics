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
	public static $requiredCapabilities = array(
		'is_wireless_device',
		'brand_name',
		'model_name',
		'resolution_width',
		'resolution_height',
		'full_flash_support',
		'flash_lite_version',
		'mobile_browser',
		'device_os',
		'ajax_xhr_type',
		'ajax_support_javascript',
	);
	
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
			//'API.getReportMetadata' => 'getReportMetadata',
			'AdminMenu.add' => 'addAdminMenu'
		);
		return $hooks;
	}

	
	function install(){
		// add columns to the visit table
		$query = "ALTER IGNORE TABLE `".Piwik_Common::prefixTable('log_visit')."`
 ADD `mobile` tinyint(1) DEFAULT NULL,
 ADD `mobile_brand` varchar(100) DEFAULT NULL,
 ADD `mobile_model` varchar(100) DEFAULT NULL,
 ADD `mobile_id` varchar(64) DEFAULT NULL,
 ADD `mobile_browser` varchar(64) DEFAULT NULL,
 ADD `mobile_resolution` varchar(9) DEFAULT NULL,
 ADD `mobile_js` tinyint(4) DEFAULT NULL,
 ADD `mobile_flash` varchar(16) DEFAULT NULL,
 ADD `mobile_os` varchar(32) DEFAULT NULL,
 ADD `mobile_ajax` tinyint(4) DEFAULT NULL,
 ADD KEY `index_mobile` (`mobile`)";
		
		// if the column already exists do not throw error. Could be installed twice...
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
		$query = "ALTER TABLE `".Piwik_Common::prefixTable('log_visit')."`
 DROP `mobile`,
 DROP `mobile_brand`,
 DROP `mobile_model`,
 DROP `mobile_id`,
 DROP `mobile_browser`,
 DROP `mobile_resolution`,
 DROP `mobile_js`,
 DROP `mobile_flash`,
 DROP `mobile_os`,
 DROP `mobile_ajax`
 DROP KEY `index_mobile`";
		Piwik_Exec($query);
	}
	
	function addWidget(){
		$widgets = array(
			'getDeviceMobile'		=> 'Mobile vs. Desktop',
			'getDeviceName'			=> 'Devices by Model Name',
			'getDeviceBrand'		=> 'Devices by Brand Name',
			'getDeviceBrowser'		=> 'Mobile Browsers',
			'getDeviceResolution'	=> 'Screen Resolutions',
			'getDeviceJS'			=> 'JavaScript Support',
			'getDeviceAJAX'			=> 'AJAX Support',
			'getDeviceFlash'		=> 'Flash Support',
			'getDeviceOS'			=> 'Operating System',
		);
		foreach($widgets as $method=>$label){
			Piwik_AddWidget('Mobile Analytics', $label, 'MobileAnalytics', $method);
		}
	}
	
	function addMenu(){
		Piwik_AddMenu('Mobile Analytics', '', array('module' => 'MobileAnalytics', 'action' => 'index'), true, 30);
	}
	
	function addAdminMenu(){
		Piwik_AddAdminMenu('Mobile Analytics', 
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
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileDevices' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileBrands' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileBrowser' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileResolution' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileJS' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileFlash' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileOS' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobileAJAX' ), null, $maximumRowsInDataTable);
		$archiveProcessing->archiveDataTable(array( 'MobileAnalytics_mobile' ), null, $maximumRowsInDataTable);
	}

	/**
	 * Daily archive: processes the report Visits by Mobile Device
	 */
	function archiveDay($notification){
		/**
		 * @var Piwik_ArchiveProcessing_Day
		 */
		$archiveProcessing = $notification->getNotificationObject();
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileDevices', 'mobile_model');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileBrands', 'mobile_brand');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileBrowser', 'mobile_browser');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileResolution', 'mobile_resolution');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileJS', 'mobile_js');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileFlash', 'mobile_flash');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileOS', 'mobile_os');
		$this->archiveGenericMobileData($archiveProcessing, 'MobileAnalytics_mobileAJAX', 'mobile_ajax');
		
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
	
	protected function archiveGenericMobileData(&$archiveProcessing,$recordName,$labelSQL,$sort_column = Piwik_Archive::INDEX_NB_VISITS){
		$interestByProvider = $this->getMobileArrayInterestForLabel($archiveProcessing, $labelSQL);
		$tableProvider = $archiveProcessing->getDataTableFromArray($interestByProvider);
		//$tableProvider->filter('ColumnCallbackDeleteRow', array('label', 'strlen'));
		$columnToSortByBeforeTruncation = $sort_column;
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
			if($this->wurflObj->getDeviceCapability('is_wireless_device')){
				if($this->wurflObj->getDeviceCapability('full_flash_support')){
                        $flash = "Flash Player";
                }elseif(preg_match('/^(\d)/',$this->wurflObj->getDeviceCapability('flash_lite_version'),$matches)){
                        $flash = "Flash Lite ".$matches[1];
                }else{
                        $flash = "None";
                }
				
				$visitorInfo['mobile'] = 1;
				$visitorInfo['mobile_id'] = $this->wurflObj->capabilities['id'];
				$visitorInfo['mobile_brand'] = trim($this->wurflObj->getDeviceCapability('brand_name'));
				$visitorInfo['mobile_model'] = trim($visitorInfo['mobile_brand'] . ' ' . $this->wurflObj->getDeviceCapability('model_name'));
				$visitorInfo['mobile_browser'] = trim($this->wurflObj->getDeviceCapability('mobile_browser'));
				$visitorInfo['mobile_resolution'] = $this->wurflObj->getDeviceCapability('resolution_width').'x'.$this->wurflObj->getDeviceCapability('resolution_height');
				$visitorInfo['mobile_js'] = $this->wurflObj->getDeviceCapability('ajax_support_javascript')? 1:0;
				$visitorInfo['mobile_flash'] = $flash;
				$visitorInfo['mobile_os'] = trim($this->wurflObj->getDeviceCapability('device_os'));
				$visitorInfo['mobile_ajax'] = ($this->wurflObj->getDeviceCapability('ajax_xhr_type') != 'none')? 1:0;
			}else{
				$visitorInfo['mobile'] = 0;
				$visitorInfo['mobile_id'] = $this->wurflObj->capabilities['id'];
				$visitorInfo['mobile_brand'] = null;
				$visitorInfo['mobile_model'] = null;
				$visitorInfo['mobile_browser'] = null;
				$visitorInfo['mobile_resolution'] = null;
				$visitorInfo['mobile_js'] = null;
				$visitorInfo['mobile_flash'] = null;
				$visitorInfo['mobile_os'] = null;
				$visitorInfo['mobile_ajax'] = null;
			}
		}catch(Exception $e){
			error_log($e->getMessage());
		}
	}
	
	public function getMobileArrayInterestForLabel(&$archiveProcessing,$label){
		$query = "SELECT 	$label as label,
							count(distinct visitor_idcookie) as nb_uniq_visitors, 
							count(*) as nb_visits,
							sum(visit_total_actions) as nb_actions, 
							max(visit_total_actions) as max_actions, 
							sum(visit_total_time) as sum_visit_length,
							sum(case visit_total_actions when 1 then 1 else 0 end) as bounce_count,
							sum(case visit_goal_converted when 1 then 1 else 0 end) as nb_visits_converted
				FROM ".$archiveProcessing->logTable."
				WHERE visit_last_action_time >= ?
						AND visit_last_action_time <= ?
						AND idsite = ?
						AND mobile = 1
				GROUP BY label
				ORDER BY nb_visits DESC";
		$query = $archiveProcessing->db->query($query, array( $archiveProcessing->getStartDatetimeUTC(), $archiveProcessing->getEndDatetimeUTC(), $archiveProcessing->idsite ));
		$interest = array();
		while($row = $query->fetch()){
			if(!isset($interest[$row['label']])) $interest[$row['label']]= $archiveProcessing->getNewInterestRow();
			$archiveProcessing->updateInterestStats( $row, $interest[$row['label']]);
		}
		return $interest;
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
