<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 2967 2010-09-21 21:26:01 terawurfl $
 * 
 * @category Piwik_Plugins
 * @package Piwik_MobileAnalytics
 */

/**
 *
 * @package Piwik_MobileAnalytics
 */
class Piwik_MobileAnalytics_Controller extends Piwik_Controller {
	
	public function index(){
		$view = Piwik_View::factory('index');
		$view->report_by_mobile = $this->getDeviceMobile(true);
		$view->report_by_model  = $this->getDeviceName(true);
		$view->report_by_brand  = $this->getDeviceBrand(true);
		echo $view->render();
	}
	
	public function debug(){
		if(!Piwik_MobileAnalytics::settingsExist()){
			echo "Does NOT exist";
			//Piwik_MobileAnalytics::createSettings();
		}else{
			echo "Exists";
		}
/*		
		$config = Zend_Registry::get('config');
		$mobile = $config->MobileAnalytics->toArray();
		$mobile["test2"] = "val2";
		$config->MobileAnalytics = $mobile;
		$config->__destruct();
*/
		echo "<pre>".var_export(Zend_Registry::get('config'),true)."</pre>";
	}
	public function adminMenu(){
		Piwik::checkUserIsSuperUser();
		$view = Piwik_View::factory('settings');
		if(!Piwik_MobileAnalytics::settingsExist()) Piwik_MobileAnalytics::createSettings();
		$view->config = Zend_Registry::get('config')->MobileAnalytics->toArray();
		$this->setBasicVariablesView($view);
		$view->topMenu = Piwik_GetTopMenu();
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();
	}
	
	public function setSettings(){
		$response = new Piwik_API_ResponseBuilder(Piwik_Common::getRequestVar('format'));
		try{
			Piwik::checkUserIsSuperUser();
			$this->checkTokenInUrl();
			$mode = Piwik_Common::getRequestVar('TeraWurflMode');
			$url  = Piwik_Common::getRequestVar('TeraWurflURL');
			$path = Piwik_Common::getRequestVar('TeraWurflPath');
			switch($mode){
				case 'TeraWurfl':
				case 'TeraWurflEnterprise':
					if(!(file_exists($path) && (require_once $path))){
						echo $response->getResponseException(new Exception("Could not save settings: The file ".htmlentities($path)." does not exist"));
						return;
					}elseif(!class_exists($mode)){
						echo $response->getResponseException(new Exception("Could not save settings: The file ".htmlentities($path)." exists but does not contain the required class ".htmlentities($mode)));
						return;
					}else{
						Piwik_MobileAnalytics::saveSetting('TeraWurflMode',$mode);
						Piwik_MobileAnalytics::saveSetting('TeraWurflPath',$path);
					}
					break;
				case 'TeraWurflRemoteClient':
					require_once PIWIK_INCLUDE_PATH . '/plugins/MobileAnalytics/TeraWurflRemoteClient.php';
					try{
						$this->wurflObj = new TeraWurflRemoteClient($url);
						@$this->wurflObj->getCapabilitiesFromAgent(null,Piwik_MobileAnalytics::$requiredCapabilities);
					}catch(Exception $e){
						echo $response->getResponseException(new Exception("Could not save settings: A request to the Tera-WURFL web service (".htmlentities($url).") failed."));
						return;
					}
					Piwik_MobileAnalytics::saveSetting('TeraWurflURL',$url);
					Piwik_MobileAnalytics::saveSetting('TeraWurflMode',$mode);
					break;
				default:
				case 'disabled':
					Piwik_MobileAnalytics::saveSetting('TeraWurflMode','disabled');
					break;
			}
			echo $response->getResponse();
		}catch(Exception $e){
			echo $response->getResponseException($e);
		}
	}
	
	public function getDeviceName($fetch = false){
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName,  __FUNCTION__, "MobileAnalytics.getDeviceName" );
		$this->setPeriodVariablesView($view);
		$column = 'nb_visits';
		$view->setColumnsToDisplay( array('label',$column) );
		$view->setColumnTranslation('label', 'Mobile Device');
		$view->setSortedColumn( $column	 );
		$view->setLimit( 20 );
		return $this->renderView($view, $fetch);
	}
	public function getDeviceBrand($fetch = false){
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName,  __FUNCTION__, "MobileAnalytics.getDeviceBrand" );
		$this->setPeriodVariablesView($view);
		$column = 'nb_visits';
		$view->setColumnsToDisplay( array('label',$column) );
		$view->setColumnTranslation('label', 'Mobile Device Brand Name');
		$view->setSortedColumn( $column	 );
		$view->setLimit( 20 );
		return $this->renderView($view, $fetch);
	}
	public function getDeviceMobile($fetch = false){
		$view = Piwik_ViewDataTable::factory('graphPie');
		$view->init( $this->pluginName,  __FUNCTION__, "MobileAnalytics.getDeviceMobile" );
		$this->setPeriodVariablesView($view);
		$column = 'nb_visits';
		$view->setColumnsToDisplay( array('label',$column) );
		$view->setColumnTranslation('label', 'Mobile / Non-Mobile');
		$view->setSortedColumn( $column	 );
		$view->setLimit( 2 );
		return $this->renderView($view, $fetch);
	}
}

