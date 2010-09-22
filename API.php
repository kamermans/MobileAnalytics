<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: API.php 2967 2010-09-21 21:26:01 terawurfl $
 * 
 * @category Piwik_Plugins
 * @package Piwik_MobileAnalytics
 */

/**
 * 
 * @package Piwik_MobileAnalytics
 */
class Piwik_MobileAnalytics_API{
	static private $instance = null;
	
	static public function getInstance(){
		if (self::$instance == null){            
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}
	// Devices by Model Name
	public function getDeviceName( $idSite, $period, $date ){
		Piwik::checkUserHasViewAccess( $idSite );
		$archive = Piwik_Archive::build($idSite, $period, $date );
		$dataTable = $archive->getDataTable('MobileAnalytics_mobileDevices');
		$dataTable->filter('Sort', array(Piwik_Archive::INDEX_NB_VISITS));
		$dataTable->queueFilter('ReplaceColumnNames');
		$dataTable->queueFilter('ReplaceSummaryRowLabel');
		return $dataTable;
	}
	// Devices by Brand Name
	public function getDeviceBrand( $idSite, $period, $date ){
		Piwik::checkUserHasViewAccess( $idSite );
		$archive = Piwik_Archive::build($idSite, $period, $date );
		$dataTable = $archive->getDataTable('MobileAnalytics_mobileBrands');
		$dataTable->filter('Sort', array(Piwik_Archive::INDEX_NB_VISITS));
		$dataTable->queueFilter('ReplaceColumnNames');
		$dataTable->queueFilter('ReplaceSummaryRowLabel');
		return $dataTable;
	}
	// Mobile vs. Non-Mobile
	public function getDeviceMobile( $idSite, $period, $date ){
		Piwik::checkUserHasViewAccess( $idSite );
		$archive = Piwik_Archive::build($idSite, $period, $date );
		$dataTable = $archive->getDataTable('MobileAnalytics_mobile');
		$dataTable->filter('Sort', array(Piwik_Archive::INDEX_NB_VISITS));
		$dataTable->queueFilter('ColumnCallbackReplace', array('label', array('Piwik_MobileAnalytics_API','mobileLabel')));
		$dataTable->queueFilter('ReplaceColumnNames');
		$dataTable->queueFilter('ReplaceSummaryRowLabel');
		return $dataTable;
	}
	
	// Callbacks
	public static function mobileLabel($in){
		switch($in){
			case "0":
				return "Desktop";
				break;
			case "1":
				return "Mobile";
				break;
			default:
				return "Unknown";
				break;
		}
	}
}

