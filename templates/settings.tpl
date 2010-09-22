{assign var=showSitesSelection value=false}
{assign var=showPeriodSelection value=false}
{include file="CoreAdminHome/templates/header.tpl"}
{loadJavascriptTranslations plugins='UsersManager'}

{literal}
<script type='text/javascript' src='plugins/MobileAnalytics/templates/settings.js'> </script>
<style>
.twmode{display: none;}
</style>
{/literal}

<h2>Mobile Analytics Settings</h2>
Mobile Analytics uses Tera-WURFL to identify mobile visitors.  You must have Tera-WURFL >= 2.1.2 installed to use this feature.

{ajaxErrorDiv id=ajaxError}
{ajaxLoadingDiv id=ajaxLoading}
<table class="adminTable adminTableNoBorder" style='width:900px;'>
<tr>
	<td colspan="2">
		<label><input type="radio" name="TeraWurflMode" value="disabled" {if $config.TeraWurflMode eq 'disabled'} checked {/if}/> Disabled.  MobileAnalytics will not detect mobile devices in this mode.</label><br/>
		<label><input type="radio" name="TeraWurflMode" value="TeraWurflRemoteClient" {if $config.TeraWurflMode eq 'TeraWurflRemoteClient'} checked {/if}/> Tera-WURFL Webservice.  You must specify the URL to a Tera-WURFL web service.</label><br/>
		<div class="twmode" id="mode_TeraWurflRemoteClient"><label>Web Service URL: <input type="text" id="TeraWurflURL" size="80" value='{$config.TeraWurflURL}'/></label></div>
		<label><input type="radio" name="TeraWurflMode" value="TeraWurfl" {if $config.TeraWurflMode eq 'TeraWurfl'} checked {/if}/> Tera-WURFL Local Install.  You must specify the location of TeraWurfl.php.</label><br/>
		<div class="twmode" id="mode_TeraWurfl"><label>Location of TeraWurfl.php: <input type="text" id="TeraWurflPath_TeraWurfl" size="80" value='{$config.TeraWurflPath}'/></label></div>
		<label><input type="radio" name="TeraWurflMode" value="TeraWurflEnterprise" {if $config.TeraWurflMode eq 'TeraWurflEnterprise'} checked {/if}/> Tera-WURFL Enterprise Local Install.  You must specify the location of TeraWurflEnterprise.php.</label><br/>
		<div class="twmode" id="mode_TeraWurflEnterprise"><label>Location of TeraWurflEnterprise.php: <input type="text" id="TeraWurflPath_TeraWurflEnterprise" size="80" value='{$config.TeraWurflPath}'/></label></div>
	</td>
</tr>
</table>

<input type="button" value="{'General_Save'|translate}" id="settingsSubmit" class="submit" />
<br /><br />

{include file="CoreAdminHome/templates/footer.tpl"}