/**
 * Piwik - Web Analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: generalSettings.js 2967 2010-08-20 15:12:43Z vipsoft $
 */

function getSettingsAJAX()
{
	var ajaxRequest = piwikHelper.getStandardAjaxConf('ajaxLoading', 'ajaxError');
	var request = '';
	request += 'module=MobileAnalytics';
	request += '&action=setSettings';
	request += '&format=json';
	request += '&TeraWurflMode='+escape(getTeraWurflMode());
	request += '&TeraWurflURL='+escape(getTeraWurflURL());
	request += '&TeraWurflPath='+escape(getTeraWurflPath());
 	request += '&token_auth=' + piwik.token_auth;
	ajaxRequest.data = request;
	return ajaxRequest;
}
function changeTeraWurflMode(mode){
	$('.twmode').hide();
	$('#mode_'+mode).show();
}
function getTeraWurflMode(){
	return $('input[name="TeraWurflMode"]:checked').attr('value')
}
function getTeraWurflURL(){
	return $('#TeraWurflURL').val();
}
function getTeraWurflPath(){
	return $('#TeraWurflPath_'+getTeraWurflMode()).val();
}
$(document).ready( function() {
	changeTeraWurflMode(getTeraWurflMode());
	$('input[name=TeraWurflMode]').click(function(){
		changeTeraWurflMode($(this).attr('value'));
	});
	$('#settingsSubmit').click( function() {
		$.ajax( getSettingsAJAX() );
	});
	$('input').keypress( function(e) {
			var key=e.keyCode || e.which;
			if (key==13) {
				$('#settingsSubmit').click();
			}
		}
	)
});