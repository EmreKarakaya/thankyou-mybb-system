<?php
/**
  * Version: 		    Thank you 2.4.3
  * Compatibillity: 	MyBB 1.6.x
  * Website: 		    http://www.mybb.com
  * Autor: 		        Dark Neo
*/

if(!defined("IN_MYBB"))
{
	die("No se permite la inicialización directa de este archivo.");
}

// Enganche que carga los datos del plugin en la caja del mensaje
$plugins->add_hook("postbit", "thx");
// Enganches del mensaje a mostrar, caja del mensaje, anuncios, vista previa y mensaje en todos los demás lados
$plugins->add_hook("postbit_announcement", "thx_code");
$plugins->add_hook("postbit_prev", "thx_code");
$plugins->add_hook("parse_message", "thx_code");
// Enganche utilizado para mostrar los agradecimientos en el perfil de usuario
$plugins->add_hook('member_profile_end', 'thx_memprofile');
// Enganche para la cita en los mensajes
$plugins->add_hook("parse_quoted_message", "thx_quote");
// Enganche para la parte AJAX del sistema
$plugins->add_hook("xmlhttp", "do_action");
// Enganche para el inicio del tema, parte no AJAX del sistema
$plugins->add_hook("showthread_start", "direct_action");
// Enganche al eliminar mensajes para eliminar los datos correspondientes
$plugins->add_hook("class_moderation_delete_post", "deletepost_edit");
// Enganches para la parte de la administración, opciones de reconteo, permisos de los reconteos y demás.
$plugins->add_hook('admin_tools_action_handler', 'thx_admin_action');
$plugins->add_hook('admin_tools_menu', 'thx_admin_menu');
$plugins->add_hook('admin_tools_permissions', 'thx_admin_permissions');
$plugins->add_hook('admin_load', 'thx_admin');
// Enganche para cargar las plantillas a utilizar por el plugin
$plugins->add_hook("global_start", "thx_global_start");
// Enganche para agregar la parte de las alertas del sistema MyAlerts
$plugins->add_hook('myalerts_load_lang', 'thx_load_lang');
$plugins->add_hook('myalerts_alerts_output_start', 'thx_parse');
//Enganche para agregar el botón al editor de texto.
$plugins->add_hook('mycode_add_codebuttons', 'thx_editor');
//Enganche para editar los grupos de usuarios y los usuarios con máximos agradecimientos por día
$plugins->add_hook("admin_user_groups_edit_graph", "thx_edit_group");
//Enganche para guardar los datos enviados por el formulario de máximos agradecimientos por día
$plugins->add_hook("admin_user_groups_edit_commit", "thx_edit_group_do");
/*Enganche para ocultar las etiquetas al editar el mensaje esto aún esta en desarrollo xD.
$plugins->add_hook("editpost_end", "thx_edit");
$plugins->add_hook("xmlhttp", "thx_qedit");*/

function thx_info()
{
	global $mybb, $db, $lang;

	$thx_config_link = thx_getdata($thx_config_link);
	
	return array(
		'name'			=>	$db->escape_string($lang->thx_title),
		'description'	=>	$db->escape_string($lang->thx_desc) . $thx_config_link,
		'website'		=>	'https://github.com/WhiteNeo/thankyou-mybb-system',
		'author'		=>	'Dark Neo',
		'authorsite'	=>	'http://darkneo.skn1.com',
		'version'		=>	'2.4.3',
		'guid'		    =>	'687d4b0701008936e97c6bf3970bb014',
        'compatibility' =>	'16*'
	);
}


function thx_install()
{
	global $db;
	
	$db->query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."thx (
		txid INT UNSIGNED NOT NULL AUTO_INCREMENT, 
		uid int(10) UNSIGNED NOT NULL, 
		adduid int(10) UNSIGNED NOT NULL, 
		pid int(10) UNSIGNED NOT NULL, 
		time bigint(30) NOT NULL DEFAULT '0', 
		PRIMARY KEY (`txid`), 
		INDEX (`adduid`, `pid`, `time`) 
		);"
	);


	if(!$db->field_exists("thx", "users"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD `thx` INT NOT NULL, ADD `thxcount` INT NOT NULL, ADD `thxpost` INT NOT NULL, ADD `thx_ammount` INT NOT NULL";
	}
	elseif (!$db->field_exists("thxpost", "users"))		
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD `thxpost` INT NOT NULL";
	}
	elseif (!$db->field_exists("thx_ammount", "users"))		
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD `thx_ammount` INT NOT NULL";
	}
	
	if(!$db->field_exists("thx_max_ammount", "usergroups"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."usergroups ADD thx_max_ammount INT NOT NULL DEFAULT '10'";
	}
	
	if($db->field_exists("thx", "posts"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."posts DROP thx";
	}
	
	if(!$db->field_exists("pthx", "posts"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."posts ADD `pthx` INT(10) NOT NULL DEFAULT '0'";
	}
	
	if(is_array($sq))
	{
		foreach($sq as $q)
		{
			$db->query($q);
		}
	}
}


function thx_is_installed()
{
	global $db;
	if($db->table_exists('thx'))
	{
		return true;
	}
	return false;
}


function thx_activate()
{
	global $db, $lang, $cache;
	
    if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}

	if (!$db->field_exists("thx_ammount", "users"))		
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD `thx_ammount` INT NOT NULL";
	}
	
	if(!$db->field_exists("thx_max_ammount", "usergroups"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."usergroups ADD thx_max_ammount INT NOT NULL DEFAULT '10'";
	}
	
	if(is_array($sq))
	{
		foreach($sq as $q)
		{
			$db->query($q);
		}
	}	
	
	$query_tid = $db->write_query("SELECT tid FROM ".TABLE_PREFIX."themes");
	$themetid = $db->fetch_array($query_tid);
	$style = array(
			'name'         => 'thx_buttons.css',
			'tid'          => $themetid['tid'],
			'stylesheet'   => $db->escape_string('.thx_buttons{
		color: #424242;
		background: transparent;
		border: none;
		padding: 0 1px;
		border-radius: 4px;
		text-decoration: none;
		position: relative;
		font-size: 10px;
		font-weight: bold;
}

.thx_buttons:hover,
.thx_buttons:active{
		background: rgb(222, 255, 200);
		color: #4F8A10;
}

.thx_buttons .gracias{
		color: #4F8A10;
		text-decoration: none;	   
}

.thx_buttons .egracias{
		color: #D8000C;
		text-decoration: none;
}

.bad_thx{
		color: #D8000C;
		font-size: 12px;
		font-weight: bold;
		text-decoration: none;
		background: none repeat scroll 0% 0% rgb(216, 227, 237);
		border: 2px solid rgb(189, 225, 253);
		box-shadow: 0px 0px 1em rgb(182, 182, 182);
		border-radius: 4px;
		padding: 3px 5px;
}

.neutral_thx{
		color: #424242;
		font-size: 12px;
		font-weight: bold;
		text-decoration: none;
		background: none repeat scroll 0% 0% #AAAAB1;
		box-shadow: 0px 0px 1em rgb(182, 182, 182);
		padding: 3px 5px;
}

.good_thx{
		color: #ffffff;
		font-size: 12px;
		font-weight: bold;
		text-decoration: none;
		background: none repeat scroll 0% 0% #4b9134;
		box-shadow: 0px 0px 1em rgb(182, 182, 182);
		padding: 3px 5px;	
}

.good_thx a, bad_thx a{
		color: #fff;
}

.thx_avatar{
        background: transparent;
		border: 1px solid #F0F0F0;
		padding: 5px;
		border-radius: 5px;
		width: 30px;
		height: 30px;
}

.info_thx, .exito_thx, .alerta_thx, .error_thx {
       font-size:13px;
       border: 1px solid;
       margin: 10px 0px;
       padding:10px 8px 10px 50px;
       background-repeat: no-repeat;
       background-position: 10px center;
	   text-align: center;
	   font-weight: bold;
	   border-radius: 5px;
}

.info_thx {
       color: #00529B;
       background-color: #BDE5F8;
       background-image: url(images/info.png);
}

.exito_thx {
       background-color: #DFF2BF;
       background-image:url(images/exito.png);
}

.alerta_thx {
       color: #9F6000;
       background-color: #FEEFB3;
       background-image: url(images/alerta.png);
}

.error_thx {
       color: #D8000C;
       background-color: #FFBABA;
       background-image: url(images/error.png);
}

.thx_list{
		color: #069;
		font-size: 10px;
		font-weight: bold;
		border: 1px solid #424242;
		border-radius: 4px;
		padding: 15px;
		margin: 10px -10px;
		background: #CCC;
		width: 100%;
}

.thx_list_avatar{
		width: 19px;
		height: 19px;
		border-style: double;
		color: #D8DFEA;
		padding: 2px;
		background-color: #FCFDFD;
		border-radius: 4px;
}'),
			'lastmodified' => TIME_NOW,
			'sid' => -2
		);
		$sid = $db->insert_query('themestylesheets', $style);
		$db->update_query('themestylesheets', array('cachefile' => "thx_buttons.css"), "sid='{$sid}'", 1);
		$query = $db->simple_select('themes', 'tid');
		while($theme = $db->fetch_array($query))
		{
			require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
			update_theme_stylesheet_list($theme['tid']);
		}

	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = (int) $db->fetch_field($query, "gid");

	if ($gid){

	$settings = array();
	$settings[] = array(
		"name" => "thanks",
		"title" => $db->escape_string($lang->thx_alerts_title),
		"description" => $db->escape_string($lang->thx_alerts_title_desc), 
		"optionscode" => "yesno", 
		"value" => "1" 
	);

		$i = 20;
		foreach($settings as $setting)
		{
			$setting['name'] = "myalerts_alert_".$setting['name'];
			$setting['disporder'] = $i;
			$setting['gid'] = $gid;
			
			$db->insert_query("settings", $setting);
			$i++;
		}
	}
	else if(!$gid){
		echo $db->escape_string($lang->thx_alerts_install_error); 
	}

	if($db->table_exists("alert_settings")){
	$thx_array = array(
		'id'		=> "NULL",
		'code'		=> "thanks",
	);
	$db->insert_query("alert_settings", $thx_array);
	}

	$templategrouparray = array(
		'prefix' => 'thanks',
		'title'  => 'DNT thanks plugin'
	);
	$db->insert_query("templategroups", $templategrouparray);

	$templatearray = array(
		'title' => 'thanks_postbit_list',
		'template' => "<div id=\"thx_menu_{\$post[\'pid\']}_popup\" style=\"display:none;\">
	<div class=\"thx_list\" id=\"thx{\$post[\'pid\']}\">
		<span id=\"thx_list{\$post[\'pid\']}\">{\$entries}</span><br />
		<a href=\"{\$mybb->settings[\'bburl\']}/thx.php?thanked_pid={\$post[\'pid\']}&my_post_key={\$mybb->post_code}\">{\$lang->thx_details}</a>
	</div>
</div>
<script type=\"text/javascript\">if(use_xmlhttprequest == \"1\"){new PopupMenu(\"thx_menu_{\$post[\'pid\']}\");}</script>",
		'sid' => '-2'
		);	
		$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'thanks_memprofile',
		'template' => "<br />
<table id=\"thx_profile\" border=\"0\" cellspacing=\"{\$theme[\'borderwidth\']}\" cellpadding=\"{\$theme[\'tablespace\']}\" class=\"tborder\">
<tr>
<td class=\"thead\"><strong>{\$lang->thx_title}</strong></td>
</tr>
<tr>
<td class=\"trow1\">
{\$memprofile[\'thx_detailed_info\']}
<br />
{\$memprofile[\'thx_info\']}
</td>
</tr>
</table>
<br />",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'thanks_hide_tag',
		'template' => "<div class=\"alerta_thx message\">{\$msg}</div>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'thanks_unhide_tag',
		'template' => "<div class=\"exito_thx message\">{\$msg}</div>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_guests_tag',
		'template' => "<div class=\"error_thx message\">{\$msg}</div>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_admins_tag',
		'template' => "<div class=\"info_thx message\">{\$msg}</div>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_results',
		'template' => "<tr>
	<td class=\"{\$trow}\">{\$gived[\'avatar\']}{\$gived[\'username\']}</td>			
	<td class=\"{\$trow}\">{\$gived[\'txid\']}</td>		
	<td class=\"{\$trow}\"><a href=\"{\$gived[\'url\']}\">{\$lang->thx_details}</a></td>	
	<td class=\"{\$trow}\">{\$gived[\'ugavatar\']}{\$gived[\'ugname\']}</td>
	<td class=\"{\$trow}\" align=\"center\">{\$gived[\'time\']}</td>
</tr>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_results_none',
		'template' => "<tr>
	<td class=\"trow1\" colspan=\"5\" align=\"center\">{\$lang->thx_empty}</td>
</tr>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_content',
		'template' => "<table border=\"0\" cellspacing=\"{\$theme[\'borderwidth\']}\" cellpadding=\"{\$theme[\'tablespace\']}\" class=\"tborder\">
	<tr>
		<td class=\"thead\" colspan=\"5\">
			<strong>{\$lang->thx_system_dnt}</strong>
		</td>
	</tr>
	<tr>
		<td class=\"tcat smalltext\"><strong>{\$lang->thx_user}</strong></td>
		<td class=\"tcat smalltext\"><strong>{\$lang->thx_id}</strong></td>
		<td class=\"tcat smalltext\"><strong>{\$lang->thx_details}</strong></td>
		<td class=\"tcat smalltext\"><strong>{\$lang->thx_added}</strong></td>
		<td class=\"tcat smalltext\"><strong>{\$lang->thx_date}</strong></td>
	</tr>
	{\$users_list}
</table>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'thanks_page',
		'template' => "<html>
	<head>
		<title>{\$mybb->settings[\'bbname\']} - {\$lang->thx_system_dnt}</title>
		{\$headerinclude}
	</head>
	<body>
		{\$header}
		{\$multipage}
		{\$content}
		{\$multipage}
		{\$footer}
	</body>
</html>",
		'sid' => '-2'
		);
	$db->insert_query("templates", $templatearray);	

	$thx_task = array(
		"title" => "Thanks per day",
		"description" => "Set all counters to 0 for every user on max ammount",
		"file" => "thx",
		"minute" => '0',
		"hour" => '0',
		"day" => '*',
		"month" => '*',
		"weekday" => '*',
		"nextrun" => time() + (1*24*60*60),
		"enabled" => '1',
		"logging" => '1'
	);
	$db->insert_query("tasks", $thx_task);
	
	$thx_group = array(
		"name"			=> "Gracias",
		"title"			=> $db->escape_string($lang->thx_opt_title),
		"description"	=> $db->escape_string($lang->thx_opt_desc),
		"disporder"		=> "3",
		"isdefault"		=> "1"
	);	
	$db->insert_query("settinggroups", $thx_group);
	$gid = $db->insert_id();
	
	$thx[]= array(
		"name"			=> "thx_active",
		"title"			=> $db->escape_string($lang->thx_opt_enable),
		"description"	=> $db->escape_string($lang->thx_opt_enable_desc),
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 1,
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_count",
		"title"			=> $db->escape_string($lang->thx_count_title),
		"description"	=> $db->escape_string($lang->thx_count_desc),
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 2,
		"gid"			=> intval($gid),
	);

	$thx[] = array(
		"name"			=> "thx_counter",
		"title"			=> $db->escape_string($lang->thx_counter_title),
		"description"	=> $db->escape_string($lang->thx_counter_desc),
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 3,
		"gid"			=> intval($gid),
	);	
	$thx[] = array(
		"name"			=> "thx_del",
		"title"			=> $db->escape_string($lang->thx_del_title),
		"description"	=> $db->escape_string($lang->thx_del_desc),
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 4,
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_hidemode",
		"title"			=> $db->escape_string($lang->thx_date_title),
		"description"	=> $db->escape_string($lang->thx_date_desc),
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 5,
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_hidesystem",
		"title"			=> $db->escape_string($lang->thx_hide_title),
		'description'   => $db->escape_string($lang->thx_hide_desc),
		"optionscode" 	=> "yesno",
		"value"			=> 1,
		"disporder"		=> 6,
		"gid"			=> intval($gid),
	);

	$thx[] = array(
		"name"			=> "thx_hidesystem_tag",
		"title"			=> $db->escape_string($lang->thx_hidetag_title),
		'description'   => $db->escape_string($lang->thx_hidetag_desc),
		"optionscode" 	=> "text",
		"value"			=> $db->escape_string($lang->thx_hidetag_value),
		"disporder"		=> 7,
		"gid"			=> intval($gid),
	);	

	$thx[] = array(
		"name"			=> "thx_editor_button",
		"title"			=> $db->escape_string($lang->thx_ebutton_title),
		"description"   => $db->escape_string($lang->thx_ebutton_desc),
		"optionscode" 	=> "yesno",
		"value"			=> 1,
		"disporder"		=> 8,
		"gid"			=> intval($gid),
	);

	$thx[] = array(
		"name"			=> "thx_hidesystem_gid",
		"title"			=> $db->escape_string($lang->thx_gid_title),
		"description"   => $db->escape_string($lang->thx_gid_desc),
		"optionscode" 	=> "text",
		"value"			=> 4,
		"disporder"		=> 9,
		"gid"			=> intval($gid),
	);	

	$thx[] = array(
		"name"			=> "thx_hidesystem_notgid",
		"title"			=> $db->escape_string($lang->thx_ngid_title),
		"description"   => $db->escape_string($lang->thx_ngid_desc),
		"optionscode" 	=> "text",
		"value"			=> '1,5,7',
		"disporder"		=> 10,
		"gid"			=> intval($gid),
	);	
		
    $thx[] = array(
        'name' 			=> "thx_reputation",
        'title' 		=> $db->escape_string($lang->thx_rep_title),
        'description' 	=> $db->escape_string($lang->thx_rep_desc),
        'optionscode' 	=> 'select \n1='.$db->escape_string($lang->thx_rep_op1).' \n2='.$db->escape_string($lang->thx_rep_op2).' \n3='.$db->escape_string($lang->thx_rep_op3).' \n4='.$db->escape_string($lang->thx_rep_op4),
        'value' 		=> 3,
        'disporder' 	=> 11,
        'gid' 			=> intval($gid)
    );  	

    $thx[] = array(
        'name' 			=> "thx_version",
        'title' 		=> "version",
        'description' 	=> "Version del plugin",
        'optionscode' 	=> 'text',
        'value' 		=> 243,
        'disporder' 	=> 12,
        'gid' 			=> 0
    );  	
	
	foreach($thx as $t)
	{
		$db->insert_query("settings", $t);
	}

	require MYBB_ROOT."inc/adminfunctions_templates.php";	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'button_rep\']}').'#', '{$post[\'button_rep\']}{$post[\'thx_counter\']}{$post[\'thx_list\']}{$post[\'thanks\']}');	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'message\']}').'#', '<div id="thxpid_{$post[\'pid\']}">{$post[\'message\']}</div>');		
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'message\']}').'#', '<div id="thxpid_{$post[\'pid\']}">{$post[\'message\']}</div>');		
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'button_rep\']}').'#', '{$post[\'button_rep\']}{$post[\'thx_counter\']}{$post[\'thx_list\']}{$post[\'thanks\']}');		
	find_replace_templatesets("showthread", "#".preg_quote('{$headerinclude}').'#','{$headerinclude}'."\n".'<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/thx.js"></script>');
	find_replace_templatesets("codebuttons", "#".preg_quote('<script type="text/javascript" src="jscripts/editor.js?ver=1609"></script>').'#','<script type="text/javascript" src="jscripts/editor.js?ver=1609"></script>'."\n".'{$lang->thx_codebutton}');
	find_replace_templatesets('member_profile', '#{\$profilefields}#', '{\$profilefields}
{\$memprofile[\'thx_details\']}');

	$cache->update_usergroups();
   	$cache->update_forums();
	$cache->update_tasks();	

	rebuild_settings();
}


function thx_deactivate()
{
	global $db, $cache;
    	$db->delete_query('themestylesheets', "name='thx_buttons.css'");
		$query = $db->simple_select('themes', 'tid');
		while($theme = $db->fetch_array($query))
		{
			require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
			update_theme_stylesheet_list($theme['tid']);
		}

	require '../inc/adminfunctions_templates.php';
	find_replace_templatesets("postbit", '#'.preg_quote('<div id="thxpid_{$post[\'pid\']}">{$post[\'message\']}</div>').'#', '{$post[\'message\']}', 0);	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thx_list\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thanks\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thx_counter\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('<div id="thxpid_{$post[\'pid\']}">{$post[\'message\']}</div>').'#', '{$post[\'message\']}', 0);	
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thx_list\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thanks\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thx_counter\']}').'#', '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/thx.js"></script>').'#', '', 0);
	find_replace_templatesets("codebuttons", "#".preg_quote('{$lang->thx_codebutton}').'#', '', 0);
	find_replace_templatesets("member_profile", "#".preg_quote('{$memprofile[\'thx_details\']}').'#', '', 0);
	
	$db->delete_query("settings", "name IN ('thx_active', 'thx_count', 'thx_counter', 'thx_del', 'thx_hidemode', 'thx_hidesystem', 'thx_hidesystem_tag', 'thx_editor_button', 'thx_hidesystem_gid', 'thx_hidesystem_notgid', 'thx_reputation', 'thx_version')");
	$db->delete_query("settinggroups", "name='Gracias'");
	$db->delete_query("templategroups", "prefix='thanks'");
	$db->delete_query("templates", "title IN('thanks_postbit_list', 'thanks_memprofile', 'thanks_hide_tag', 'thanks_unhide_tag', 'thanks_guests_tag', 'thanks_admins_tag', 'thanks_results', 'thanks_results_none', 'thanks_content', 'thanks_page')");
	if($db->table_exists("alert_settings")){
		$db->delete_query("alert_settings", "code='thanks'");
	}
	$db->delete_query("settings", "name='myalerts_alert_thanks'");
	$db->delete_query('tasks', 'file=\'thx\'');
	
	$cache->update_usergroups();
   	$cache->update_forums();	
	$cache->update_tasks();	
	
	rebuild_settings();
}

function thx_uninstall()
{
	global $db;

	if($db->field_exists("thx", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP thx, DROP thxcount, DROP thxpost, DROP thx_ammount");
	}

	if($db->field_exists("thx_max_ammount", "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP thx_max_ammount");
	}
	
	if($db->field_exists("pthx", "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts DROP pthx");
	}
	
	if($db->table_exists("thx"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."thx");
	}
	
}

function thx_getdata($thx_config_link)
{
	global $mybb, $db, $lang, $thx_config_link, $alertas;

	$thx_config_link = '';

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	if ($mybb->settings['thx_active'] == 1)
	{
		$thx_config_link = '<div style="float: right;"><a href="index.php?module=config&action=change&search=Gracias" style="color:#035488; background: url(../images/usercp/options.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> '. $db->escape_string($lang->thx_config) . '</a></div>';
		if($mybb->settings['myalerts_enabled'] == 1){
			$alertas = myalerts_info();
			$thx_alerts = $alertas['version'];
		}
		if($mybb->settings['myalerts_enabled'] == 1&& $mybb->settings['myalerts_alert_thanks'] == 0){
			$thx_config_link .= '<div style="float: left;"><a href="index.php?module=config&action=change&search=myalerts" style="color: rgba(136, 17, 3, 1); background: url(../images/error.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> '. $db->escape_string($lang->thx_config_alerts) . '</a></div><br />';
			$thx_config_link .= '<br /><div><span style="color: rgba(34, 136, 3, 1); background: url(../images/valid.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> <a href="'.$mybb->settings['bburl'].'/inc/plugins/MyAlerts/force_enable_alerts.php"> '. $db->escape_string($lang->thx_config_alerts_force_all) . '</a></div>';
		}
		else if($mybb->settings['myalerts_enabled'] == 1 && $mybb->settings['myalerts_alert_thanks'] == 1 && $thx_alerts >= 1.05){
			$thx_config_link .= '<div style="float: left;"><span style="color: rgba(34, 136, 3, 1); background: url(../images/valid.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> ' . $db->escape_string($lang->thx_config_alerts_thx) . '</a></div><br />';		
			$thx_config_link .= '<br /><div><span style="color: rgba(34, 136, 3, 1); background: url(../images/valid.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> <a href="'.$mybb->settings['bburl'].'/inc/plugins/MyAlerts/force_enable_alerts.php"> '. $db->escape_string($lang->thx_config_alerts_force_all) . '</a></div>';
		}
		else if($mybb->settings['myalerts_enabled'] == 1 && $thx_alerts <= 1.04){
			$thx_config_link .= '<div style="float: left;"><span style="color: rgba(136, 17, 3, 1); background: url(../images/error.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;">  ' . $db->escape_string($lang->thx_config_alerts_none) . '</a></div><br />';		
		}
		
	}
	else if ($mybb->settings['thx_active'] == 0)
	{
		$thx_config_link = '<div style="float: right; color: rgba(136, 17, 3, 1); background: url(../images/error.gif) no-repeat 0px 18px; padding: 18px; text-decoration: none;"> '. $db->escape_string($lang->thx_disabled) . '</div>';
	}
		
	return $thx_config_link;
}

function thx_global_start()
{

	global $mybb, $session, $lang;

    if ($mybb->settings['thx_active'] == 0 || !empty($session->is_spider))
    {
        return false;
    }
		
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	if(isset($GLOBALS['templatelist']))
	{
		$GLOBALS['templatelist'] .= ",thanks_postbit_list,thanks_memprofile, thanks_hide_tag,thanks_unhide_tag,thanks_guests_tag,thanks_admins_tag,thanks_results,thanks_results_none,thanks_content,thanks_page";
	}
}

function thx_code(&$message)
{
    global $db, $post, $mybb, $lang, $session, $theme, $altbg, $templates, $thx_cache, $forum, $fid, $pid, $announcement, $postrow, $hide_tag;

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
		{
			$lang->load("thx");
		}

    if ($mybb->settings['thx_active'] == 0 || !$mybb->settings['thx_hidesystem']  || !empty($session->is_spider))
        {
          return false;
        }
		
        $forum_gid = explode(',', $mybb->settings['thx_hidesystem_gid']);
		$forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
		$url = $mybb->settings['bburl'];
        $hide_tag = $mybb->settings['thx_hidesystem_tag'];	

		if(THIS_SCRIPT == "syndication.php" || THIS_SCRIPT == "search.php"){
		   $msg = $lang->thx_hide_sindycation; 
		   eval("\$caja = \"".$templates->get("thanks_guests_tag",1,0)."\";");		  
		   $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);	
		}
		
		if($forum['fid'] == 0 || $forum['fid'] == ''){$forum['fid'] = $fid;}
		
		if($post['pid'] == 0 || $post['pid'] == ''){
		switch(THIS_SCRIPT)
		{
		case "printthread.php" : $post['pid'] = $postrow['pid'];break;
		case "portal.php" : $post['pid'] = $announcement['pid'];$forum_fid = $announcement['fid'];break;
		default: $post['pid'] = $pid;
		}
		}

		if($mybb->input['highlight']){
			$message = preg_replace("#$hide_tag(.*)$hide_tag#is",$lang->thx_hide_text,$message);
		}
				
        $must_thanks = $mybb->settings['thx_hidesystem_tag'];

    if(in_array($mybb->user['usergroup'], $forum_gid))
    {
	  $msg = "$1";
	  eval("\$caja = \"".$templates->get("thanks_admins_tag",1,0)."\";");		  
      $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);      
	}
      
    else if(in_array($mybb->user['usergroup'], $forum_notgid) || $mybb->user['uid'] == 0)
    {	 
		if($mybb->input['highlight']){		
			$message = preg_replace("#$hide_tag(.*)$hide_tag#is",$lang->thx_hide_text,$message);
		}	
	   $msg = $lang->thx_hide_register; 
	   eval("\$caja = \"".$templates->get("thanks_guests_tag",1,0)."\";");		  
	   $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);
    }
    else{

	  if ($mybb->user['uid'] == $post['uid'])
       {
	   $msg = "$1";
	   eval("\$caja = \"".$templates->get("thanks_unhide_tag",1,0)."\";");		  
       $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);
       }

     if($mybb->user['uid'] != $post['uid'])
     {
     $thx_user = intval($mybb->user['uid']);
	 $query=$db->query("SELECT th.txid, th.uid, th.adduid, th.pid, th.time, u.username, u.usergroup, u.displaygroup, u.avatar
		FROM ".TABLE_PREFIX."thx th
		JOIN ".TABLE_PREFIX."users u
		ON th.adduid=u.uid
		WHERE th.pid='{$post[pid]}' AND th.adduid ='{$thx_user}'
		ORDER BY th.time ASC"
	);

	while($record = $db->fetch_array($query))
	{
	if($record['adduid'] == $mybb->user['uid'])
	{
	   $msg = "$1";
	   eval("\$caja = \"".$templates->get("thanks_unhide_tag",1,0)."\";");		  
       $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);
	}
	else
	{
		if($mybb->input['highlight']){		
			$message = preg_replace("#$hide_tag(.*)$hide_tag#is",$lang->thx_hide_text,$message);
		}
		$msg = $lang->thx_hide_text;  
	    eval("\$caja = \"".$templates->get("thanks_hide_tag",1,0)."\";");		 
        $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);
	}
    $done = true;
    }
		$msg = $lang->thx_hide_text;  
	    eval("\$caja = \"".$templates->get("thanks_hide_tag",1,0)."\";");		 
        $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is",$caja,$message);
		}
	}
}

function thx_quote(&$quoted_post)
{
    global $mybb, $session, $templates, $lang, $hide_tag;

		if ($mybb->settings['thx_active'] == '0' || $mybb->settings['thx_hidesystem'] == '0')
        {
          return false;
        }

        else if ($mybb->settings['thx_hidesystem'] == '1'){
		  $hide_tag = $mybb->settings['thx_hidesystem_tag'];	
          $quoted_post['message'] = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is","", $quoted_post['message']);
        }
}

function thx(&$post)
{
	global $db, $mybb, $lang ,$session, $theme, $altbg, $templates, $thx_cache, $thx_counter , $forum, $message;
	
	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
    $forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
	if(in_array($mybb->user['usergroup'], $forum_notgid) && THIS_SCRIPT == "showthread.php"){
	return false;
	}

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}

	if($b = $post['pthx'])
	{
		$entries = build_thank($post['pid'], $b);
	}
	else
	{
		$entries = "";
	}
	
	if($mybb->settings['thx_counter'] == 1){
	$count = intval($post['pthx']);
	if ($count == 0){$count="<a href=\"thx.php?thanked_pid={$post['pid']}&my_post_key={$mybb->post_code}\" id=\"thx_menu_{$post['pid']}\"><span id=\"counter{$post['pid']}\"><span class=\"neutral_thx\">".$count."</span></span></a>";}
	else if ($count >= 1){$count="<a href=\"thx.php?thanked_pid={$post['pid']}&my_post_key={$mybb->post_code}\" id=\"thx_menu_{$post['pid']}\"><span id=\"counter{$post['pid']}\"><span class=\"good_thx\"> ".$count." </span></span></a>";}
	else {$count="<a href=\"thx.php?thanked_pid={$post['pid']}&my_post_key={$mybb->post_code}\" id=\"thx_menu_{$post['pid']}\"><span id=\"counter{$post['pid']}\"><span class=\"bad_thx\"> ".$count." </span></span></a>";}
	}
	else{$count="<span id=\"counter{$post['pid']}\"></span>";}
	$post['thx_counter'] = $count;
	if($mybb->user['uid'] == $post['uid']){
	$post['thanks'] = "";
	eval("\$post['thx_list'] .= \"".$templates->get("thanks_postbit_list")."\";");
	}
 	if($mybb->user['uid'] != 0 && $mybb->user['uid'] != $post['uid'])
	{
		$ammount =intval($mybb->user['thx_ammount']);
		$max_ammount = intval($mybb->usergroup['thx_max_ammount']);		
		if($mybb->settings['thx_reputation'] == 3 || $mybb->settings['thx_reputation'] == 4){
			$post['button_rep'] = "";
		}
		if($ammount > $max_ammount){		
			$post['thanks'] = "<img src=\"images/error.gif\" alt=\"thx per day exceed\" />";
		}				
		else if(!$b){
			// Verify if AJAX enabled for MyBB
			if($mybb->settings['use_xmlhttprequest'] == 1){
				$post['thanks'] = "<a id=\"a{$post['pid']}\" onclick=\"javascript:return thx({$post['pid']});\" href=\"showthread.php?action=thank&amp;tid={$post['tid']}&amp;pid={$post['pid']}\">
			<span class=\"thx_buttons\" id=\"sp_{$post['pid']}\"><span class=\"gracias\"> {$lang->thx_button_add}</span></span></a>";			
			}
			else{
				$post['thanks'] = "<a id=\"a{$post['pid']}\" href=\"showthread.php?action=thank&amp;tid={$post['tid']}&amp;pid={$post['pid']}\">
			<span class=\"thx_buttons\" id=\"sp_{$post['pid']}\"><span class=\"gracias\"> {$lang->thx_button_add}</span></span></a>";
			}
		}
		else if($mybb->settings['thx_del'] == "1"){
			// Verify if AJAX enabled for MyBB
			if($mybb->settings['use_xmlhttprequest'] == 1){		
			$post['thanks'] = "<a id=\"a{$post['pid']}\" onclick=\"javascript:return rthx({$post['pid']});\" href=\"showthread.php?action=remove_thank&amp;tid={$post['tid']}&amp;pid={$post['pid']}\">
		<span class=\"thx_buttons\" id=\"sp_{$post['pid']}\"><span class=\"egracias\"> {$lang->thx_button_del}</span></span></a>";	 
			}
			else{
			$post['thanks'] = "<a id=\"a{$post['pid']}\" href=\"showthread.php?action=remove_thank&amp;tid={$post['tid']}&amp;pid={$post['pid']}\">
		<span class=\"thx_buttons\" id=\"sp_{$post['pid']}\"><span class=\"egracias\"> {$lang->thx_button_del}</span></span></a>";	 
			}			
		}		
		else{
			$post['thanks'] = "";
		}
			eval("\$post['thx_list'] .= \"".$templates->get("thanks_postbit_list")."\";");
	}

	$thx_pid = $post['pid'];
	if($mybb->settings['thx_count'] == "1")
	{
		$protect = "&my_post_key={$mybb->post_code}";	
		$post['thanks_count'] = $lang->sprintf($lang->thx_thank_count, $post['thx'], $post['uid'].$protect, $post['pid']);
		$post['thanked_count'] = $lang->sprintf($lang->thx_thanked_count, $post['thxcount'], $post['uid'].$protect, $post['pid']);
		$post['user_details'] .= "<br />" .$post['thanks_count'] . "<br />" . $post['thanked_count'];
	}
	else if ($mybb->settings['thx_count'] == "0"){
		$post['thanks_count'] = "<span id=\"thx_thanked_{$post['pid']}\" style=\"display:none\"></span>";
		$post['thanked_count'] = "<span id=\"thx_thanks_{$post['pid']}\" style=\"display:none\"></span>";
		$post['user_details'] .= $post['thanks_count'] . "" . $post['thanked_count'];
	}
}

/*Sucede al hacer una edición completa...
function thx_edit(){
    global $mybb, $post, $message, $fid, $lang, $session, $theme, $altbg, $templates;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
    $forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
	if(in_array($mybb->user['usergroup'], $forum_notgid) && THIS_SCRIPT == "showthread.php"){
	return false;
	}

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	if(THIS_SCRIPT == "editpost.php" || $mybb->user['uid'] != $post['uid'] && THIS_SCRIPT == "editpost.php")
	{
	    $hide_tag = $mybb->settings['thx_hidesystem_tag'];	
        $message = preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is","",$message);
	}
}

//Sucede al hacer una edición rápida del mensaje...
function thx_qedit(){
	global $mybb, $post;
	if(THIS_SCRIPT == "showthread.php" && $mybb->input['action'] == "edit_post"){
	$post = get_post($mybb->input['pid']);
	
	if(!$post['pid'])
	{
		xmlhttp_error($lang->post_doesnt_exist);
	}

	if($mybb->input['action'] == "edit_post" && $mybb->user['uid'] != $post['uid'])
	{
		
		if($mybb->input['do'] == "get_post"){
		xmlhttp_error("Can't use this button");

		$hide_tag = $mybb->settings['thx_hidesystem_tag'];			
		$post['message'] .= preg_replace("#\[$hide_tag\](.*?)\[/$hide_tag\]#is","",$post['message']);	
		
		// Enviar el contenido del mensaje cambiado a la plantilla - no funciona :( debes agregar el código directo.
		eval("\$inline_editor = \"".$templates->get("xmlhttp_inline_post_editor")."\";");
		}
	}
	}
	else{
		return false;
	}
}*/	

function thx_memprofile()
{
	global $db, $mybb, $lang, $session, $memprofile, $cache, $templates;
	
    if ($mybb->settings['thx_active'] == 0 || !empty($session->is_spider))
    {
        return false;
    }
	
    $forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
	if(in_array($mybb->user['usergroup'], $forum_notgid)){
		return false;
	}

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}

		$protect = "&my_post_key={$mybb->post_code}";	
		$memprofile['thanks_count'] = $lang->sprintf($lang->thx_thank_count, $memprofile['thx'], $memprofile['uid'].$protect, $memprofile['pid']);
		$memprofile['thanked_count'] = $lang->sprintf($lang->thx_thanked_count, $memprofile['thxcount'], $memprofile['uid'].$protect, $memprofile['pid']);
		$memprofile['thx_info'] = "<br />" .$memprofile['thanks_count'] . "<br />" . $memprofile['thanked_count'];
		$memprofile['thx_detailed_info'] = $lang->sprintf($lang->thx_thank_details, $memprofile['thx'], $memprofile['thxpost'],$memprofile['thxcount']);	
		$ammount =intval($mybb->user['thx_ammount']);
		$max_ammount = intval($mybb->usergroup['thx_max_ammount']);		
		//echo var_dump($memprofile);
		if($memprofile['uid'] == $mybb->user['uid']){
		$memprofile['thx_info'] .= $lang->sprintf($lang->thx_thank_details_extra, $ammount, $max_ammount);
		}		
		eval("\$memprofile['thx_details'] .= \"".$templates->get("thanks_memprofile")."\";");		
		
}

function thx_load_lang()
{
	global $lang;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}

	if (!$lang->thanks) {
		$lang->load('thx');
	}
}

function thx_addAward()
{
	global $db, $mybb, $Alerts, $post;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}

	$uid = $post['uid'];
	$tid = $post['tid'];
	$pid = $post['pid'];
	$subject = $post['subject'];
	$fid = $post['fid'];
	
	if ($mybb->settings['myalerts_enabled'] AND $Alerts instanceof Alerts){
	
			$Alerts->addAlert((int) $uid, 'thanks', (int)$tid, (int) $mybb->user['uid'], 
			array(
				'tid' 		=> $tid,
				'pid'		=> $pid,
				't_subject' => $subject,
				'fid'		=> $fid
			)); 
	}
}

function thx_parse(&$alert)
{
	global $mybb, $lang, $parser;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	if (!$lang->thanks) {
		$lang->load('thx');
	}

    require_once  MYBB_ROOT.'inc/class_parser.php';
    $parser = new postParser;

    if (empty($alert['avatar'])) {
        $alert['avatar'] = htmlspecialchars_uni($mybb->settings['myalerts_default_avatar']);
    }
    $alert['userLink'] = get_profile_link($alert['uid']);
    $alert['user'] = format_name($alert['username'], $alert['usergroup'], $alert['displaygroup']);
    $alert['user'] = build_profile_link($alert['user'], $alert['uid']);

    if ($alert['unread'] == 1) {
        $alert['unreadAlert'] = ' unreadAlert';
    } else {
        $alert['unreadAlert'] = '';
    }

	if ($alert['alert_type'] == 'thanks' AND $mybb->user['myalerts_settings']['thanks'])
	{
        $alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
        $alert['message'] = $lang->sprintf($lang->thanks_alert, $alert['user'], $alert['postLink'], htmlspecialchars_uni($parser->parse_badwords($alert['content']['t_subject'])),$alert['dateline']);
		$alert['rowType'] = 'thanks';
	}
}

function do_action()
{
	global $mybb, $db, $lang, $theme, $templates, $count, $forum, $thread, $post, $attachcache, $parser, $pid,$tid,$ammount, $max_ammount;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}

	if(($mybb->input['action'] != "thankyou"  &&  $mybb->input['action'] != "remove_thankyou") || $mybb->request_method != "post")
	{
		return false;
	}
	
    $forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
	
	if(in_array($mybb->user['usergroup'], $forum_notgid) && THIS_SCRIPT == "showthread.php"){
		return false;
	}
	
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	$ammount =intval($mybb->user['thx_ammount']);
	$max_ammount = intval($mybb->usergroup['thx_max_ammount']);	
	// if you get max thanks per day get an error...
	if($ammount > $max_ammount){
		$error = $lang->sprintf($lang->thx_exceed, $max_ammount);
		xmlhttp_error($error);
		return;
	}

	$post = get_post($mybb->input['pid']);
	
	if(!$post['pid'])
	{
		xmlhttp_error($lang->post_doesnt_exist);
	}

	$pid = intval($mybb->input['pid']);
	$tid = intval($mybb->input['tid']);
	
    if(!verify_post_check($mybb->input['my_post_key'])){
		xmlhttp_error($lang>thx_cant_thank);
	}
	
	if ($mybb->input['action'] == "thankyou")
	{
		do_thank($pid);
		if($mybb->settings['thx_counter'] == "1"){
			$count = intval($post['pthx'] + 1);
		}
		if($mybb->settings['thx_reputation'] == 2 || $mybb->settings['thx_reputation'] == 4){
			thx_addAward();
		}
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		del_thank($pid);
		if($mybb->settings['thx_counter'] == "1"){
			$count = intval($post['pthx'] - 1);
		}
	}	

		if($mybb->settings['thx_count'] == 1){
			$query = $db->query("
				SELECT uid, thxcount
				FROM ".TABLE_PREFIX."users
				WHERE uid='".intval($post['uid'])."'
				LIMIT 1");
			while($thx = $db->fetch_array($query))
			{
				$thxcount = intval($thx['thxcount']);
			}	
		}
		else if($mybb->settings['thx_count'] == "0"){
			$thxcount = "";
		}
		
	$nonead = 0;
	$list = build_thank($pid, $nonead);
	if($mybb->settings['thx_counter'] == "1"){
		if ($count == 0){
			$count="<span class=\"neutral_thx\">".$count."</span>";
		}
		else if ($count >= 1){
			$count="<span class=\"good_thx\">".$count."</span>";
		}
		else if ($count > 0){
			$count="<span class=\"bad_thx\">".$count."</span>";
		}
	}
	else{
		$count="<span id=\"counter{$post['pid']}\"></span>";
	}
	
	$post['thx_counter'] = $count;
	
	require_once MYBB_ROOT."inc/functions_post.php";
	if(!$parser)
	{
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
	}

	$unapproved_shade = '';
	if($post['visible'] == 0 && $post_type == 0)
	{
		$altbg = $unapproved_shade = 'trow_shaded';
	}
	elseif($altbg == 'trow1')
	{
		$altbg = 'trow2';
	}
	else
	{
		$altbg = 'trow1';
	}
	switch($post_type)
	{
		case 1: 
			global $forum;
			$parser_options['allow_html'] = $forum['allowhtml'];
			$parser_options['allow_mycode'] = $forum['allowmycode'];
			$parser_options['allow_smilies'] = $forum['allowsmilies'];
			$parser_options['allow_imgcode'] = $forum['allowimgcode'];
			$parser_options['allow_videocode'] = $forum['allowvideocode'];
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = 0;
			break;
		case 2: 
			global $message, $pmid;
			$parser_options['allow_html'] = $mybb->settings['pmsallowhtml'];
			$parser_options['allow_mycode'] = $mybb->settings['pmsallowmycode'];
			$parser_options['allow_smilies'] = $mybb->settings['pmsallowsmilies'];
			$parser_options['allow_imgcode'] = $mybb->settings['pmsallowimgcode'];
			$parser_options['allow_videocode'] = $mybb->settings['pmsallowvideocode'];
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = $pmid;
			break;
		case 3: 
			global $announcementarray, $message;
			$parser_options['allow_html'] = $announcementarray['allowhtml'];
			$parser_options['allow_mycode'] = $announcementarray['allowmycode'];
			$parser_options['allow_smilies'] = $announcementarray['allowsmilies'];
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			break;
		default: 
			global $forum, $thread, $tid;
			$oldforum = $forum;
			$id = intval($post['pid']);
			$parser_options['allow_html'] = $forum['allowhtml'];
			$parser_options['allow_mycode'] = $forum['allowmycode'];
			$parser_options['allow_smilies'] = $forum['allowsmilies'];
			$parser_options['allow_imgcode'] = $forum['allowimgcode'];
			$parser_options['allow_videocode'] = $forum['allowvideocode'];
			$parser_options['filter_badwords'] = 1;
			
			if(!$post['username'])
			{
				$post['username'] = $lang->guest;
			}
			
			if($post['userusername'])
			{
				$parser_options['me_username'] = $post['userusername'];
			}
			else
			{
				$parser_options['me_username'] = $post['username'];
			}
			break;
	}
	
	$post = $parser->parse_message($post['message'], $parser_options);

	header("Content-type: text/xml; charset={$charset}");

	if(!$mybb->settings['thx_del']){
	$output = "<thankyou>
				<list><![CDATA[{$list}]]></list>
				<thxcount><![CDATA[{$thxcount}]]></thxcount>				
				<count><![CDATA[{$count}]]></count>
				<button><![CDATA[";	
	if($mybb->input['action'] == "thankyou")
	{
		$output .= "";
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		$output .= "<span class=\"gracias\">{$lang->thx_button_add}</span>";
	}	
	$output .= "]]></button>
			    <post><![CDATA[{$post}]]></post>	
				<del>{$mybb->settings['thx_del']}</del>	
			 </thankyou>";
	echo $output;	
	}
	else{
	$output = "<thankyou>
				<list><![CDATA[{$list}]]></list>
				<thxcount><![CDATA[{$thxcount}]]></thxcount>								
				<count><![CDATA[{$count}]]></count>				
				<button><![CDATA[";	
	if($mybb->input['action'] == "thankyou")
	{
		$output .= "<span class=\"egracias\">{$lang->thx_button_del}</span>";
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		$output .= "<span class=\"gracias\">{$lang->thx_button_add}</span>";
	}	
	$output .= "]]></button>
			    <post><![CDATA[{$post}]]></post>	
				<del>{$mybb->settings['thx_del']}</del>	
			 </thankyou>";
	echo $output;
	}
}

function thx_editor(&$edit_lang){
	global $mybb, $lang;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider) || $mybb->settings['thx_editor_button'] == 0)
	{
		return false;
	}

	if(!$lang->thx)
	{
		$lang->load('thx');
	}
	$hide = htmlspecialchars_uni($mybb->settings['thx_hidesystem_tag']);
	$lang->thx_codebutton = <<<EOF
<script type="text/javascript" src="jscripts/{$hide}.js"></script>
EOF;

	$edit_lang[] = 'editor_thankyou';
	$edit_lang[] .= 'editor_hide';
	return $edit_lang;	
}

function direct_action()
{
	global $mybb, $lang, $tid, $pid;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	if($mybb->input['action'] != "thank"  &&  $mybb->input['action'] != "remove_thank")
	{
		return false;
	}

    $forum_notgid = explode(',', $mybb->settings['thx_hidesystem_notgid']);
	if(in_array($mybb->user['usergroup'], $forum_notgid) && THIS_SCRIPT == "showthread.php"){
	return false;
	}
	
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	$pid=intval($mybb->input['pid']);
	
	if($mybb->input['action'] == "thank" )
	{
		do_thank($pid);
		if($mybb->settings['thx_reputation'] == 4){
			thx_addAward();
		}
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		del_thank($pid);
	}
	
	redirect(get_post_link($pid, $tid)."#pid{$pid}");

}

function build_thank(&$pid, &$is_thx)
{
	global $db, $mybb, $lang, $thx_cache, $message;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
		
	$is_thx = 0;
	
	$pid = intval($pid); 
	if ($pid == 0 || $pid == ''){$pid == intval($mybb->input['pid']);}
	
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}

	$dir = $lang->thx_dir;
	
	$query=$db->query("SELECT th.txid, th.uid, th.adduid, th.pid, th.time, u.username, u.usergroup, u.displaygroup, u.avatar
		FROM ".TABLE_PREFIX."thx th
		JOIN ".TABLE_PREFIX."users u
		ON th.adduid=u.uid
		WHERE th.pid='{$pid}'
		ORDER BY th.time DESC
		LIMIT 0, 10"
	);

	while($record = $db->fetch_array($query))
	{
		if($record['adduid'] == $mybb->user['uid'])
		{
			$is_thx++;
		}
		$date = my_date($mybb->settings['dateformat'].' '.$mybb->settings['timeformat'], $record['time']);
		if(!isset($thx_cache['showname'][$record['username']]))
		{
			$url = get_profile_link($record['adduid']);
			$name = format_name($record['username'], $record['usergroup'], $record['displaygroup']);
            $avatar = htmlspecialchars_uni($record['avatar']);
            if($avatar != '')
            {
			$thx_cache['showname'][$record['username']] = "<a href=\"$url\" dir=\"$dir\"><img src=\"$avatar\" class=\"thx_list_avatar\"> $name</a><br />";
            }
            else
            {
            $thx_cache['showname'][$record['username']] = "<a href=\"$url\" dir=\"$dir\"><img src=\"images/default_avatar.gif\" class=\"thx_list_avatar\">$name</a><br />";
            }
		}

		if($mybb->settings['thx_hidemode'])
		{
			$entries .= "<span title=\"".$date."\">".$thx_cache['showname'][$record['username']]."</span><br />";
		}
		else
		{
			$entries .= $thx_cache['showname'][$record['username']]." <span class=\"smalltext\">(".$date.")</span><br />";
		}
	}
	
	return $entries;
}

function do_thank(&$pid)
{
	global $db, $mybb, $lang;
	
	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
		
	$pid = intval($pid);
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}

	$check_query = $db->simple_select("thx", "count(*) as c" ,"adduid='{$mybb->user['uid']}' AND pid='$pid'", array("limit"=>"1"));
			
	$tmp=$db->fetch_array($check_query);
	if($tmp['c'] != 0)
	{
		return false;
	}
		
	$check_query = $db->simple_select("posts", "uid", "pid='$pid'", array("limit"=>1));
	if($db->num_rows($check_query) == 1)
	{
		
		$tmp=$db->fetch_array($check_query);
		
		if($tmp['uid'] == $mybb->user['uid'])
		{
			return false;
		}		
			
		$database = array (
			"uid" =>$tmp['uid'],
			"adduid" => $mybb->user['uid'],
			"pid" => $pid,
			"time" => time()
		);
		
		$time = time();
		if($mybb->settings['thx_reputation'] == 1 || $mybb->settings['thx_reputation'] == 2){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx_ammount=thx_ammount+1,thx=thx+1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+1,thxpost=CASE( SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost+1 ELSE thxpost END WHERE uid='{$database['uid']}' LIMIT 1",					
	        "UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+1 WHERE pid='{$pid}' LIMIT 1",
			);
		}else if($mybb->settings['thx_reputation'] == 3 || $mybb->settings['thx_reputation'] == 4){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx_ammount=thx_ammount+1,thx=thx+1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+1, reputation = reputation+1,thxpost=CASE( SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost+1 ELSE thxpost END WHERE uid='{$database['uid']}' LIMIT 1",					
	        "UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+1 WHERE pid='{$pid}' LIMIT 1",
            "INSERT INTO ".TABLE_PREFIX."reputation (uid, adduid, pid, reputation, dateline, comments) VALUES ('{$tmp['uid']}', '{$mybb->user['uid']}', '{$pid}', 1, '{$time}', '{$lang->thx_thankyou}')"
			);
		}else{
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx_ammount=thx_ammount+1,thx=thx+1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+1, thxpost=CASE( SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost+1 ELSE thxpost END WHERE uid='{$database['uid']}' LIMIT 1",					
	        "UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+1 WHERE pid='{$pid}' LIMIT 1"
			);		
		}				
		
	    unset($tmp);
				  
		foreach($sq as $q)
		{
			$db->query($q);
		}
		$db->insert_query("thx", $database);
	}	
}

function del_thank(&$pid)
{
	global $mybb, $db;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
		
	$pid = intval($pid);
	if($mybb->settings['thx_del'] != "1")
	{
		return false;
	}

	$check_query = $db->simple_select("thx", "`uid`, `txid`" ,"adduid='{$mybb->user['uid']}' AND pid='$pid'", array("limit"=>"1"));		
	
	if($db->num_rows($check_query))
	{
		$data = $db->fetch_array($check_query);
		$uid = intval($data['uid']);
		$thxid = intval($data['txid']);
		unset($data);
		
		$time = time();

		if($mybb->settings['thx_reputation'] == 1){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid='{$uid}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid='{$pid}' LIMIT 1"
		);
		$db->delete_query("thx", "txid='{$thxid}'", "1");
	    }else if($mybb->settings['thx_reputation'] == 2){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid='{$uid}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid='{$pid}' LIMIT 1"
		);
		$db->delete_query("alerts", "from_id='{$mybb->user['uid']}' && unread='1' && alert_type='thanks'");		
		$db->delete_query("thx", "txid='{$thxid}'", "1");		
	    }else if($mybb->settings['thx_reputation'] == 3){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, reputation=reputation-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid='{$uid}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid='{$pid}' LIMIT 1"
		);
		$db->delete_query("reputation", "adduid='{$mybb->user['uid']}' && pid='{$pid}'");
		$db->delete_query("thx", "txid='{$thxid}'", "1");		
	    }else if($mybb->settings['thx_reputation'] == 4){
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, reputation=reputation-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid='{$uid}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid='{$pid}' LIMIT 1"
		);
		$db->delete_query("reputation", "adduid='{$mybb->user['uid']}' && pid='{$pid}'");
		$db->delete_query("alerts", "from_id='{$mybb->user['uid']}' && unread='1' && alert_type='thanks'");		
		$db->delete_query("thx", "txid='{$thxid}'", "1");		
	    }else{
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid='{$mybb->user['uid']}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid='{$pid}' LIMIT 1) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid='{$uid}' LIMIT 1",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid='{$pid}' LIMIT 1"
		);
		$db->delete_query("thx", "txid='{$thxid}'", "1");
		}
		
		foreach($sq as $q)
		{
			$db->query($q);
		}
	}
}

function deletepost_edit($pid)
{
	global $db;

	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
		
	$pid = intval($pid);
	$q = $db->simple_select("thx", "uid, adduid", "pid='{$pid}'");
	
	$postnum = $db->num_rows($q);
	if($postnum <= 0)
	{
		return false;
	}
	
	$adduids = array();
	
	while($r = $db->fetch_array($q))
	{
		$uid = intval($r['uid']);
		$adduids[] = $r['adduid'];
	}
	
	$adduids = implode(", ", $adduids);
	
	$sq = array();
	$sq[] = "UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=thxpost-1 WHERE uid='{$uid}'";
	$sq[] = "UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid IN ({$adduids})";
	
	foreach($sq as $q)
	{
		$db->query($q);
	}
	
	$db->delete_query("thx", "pid={$pid}", $postnum);	
}

function thx_admin_action(&$action)
{
	$action['recount_thanks'] = array ('active'=>'recount_thanks');
}

function thx_admin_menu(&$sub_menu)
{
    global $db, $lang;
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
	$lang->load("thx");
	}
	
	$sub_menu['45'] = array	(
		'id'	=> 'recount_thanks',
		'title'	=> $db->escape_string($lang->thx_recount),
		'link'	=> 'index.php?module=tools/recount_thanks'
	);
}

function thx_admin_permissions(&$admin_permissions)
{
    global $db,$lang;
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	$admin_permissions['recount_thanks'] = $db->escape_string($lang->thx_can_recount);
}

function thx_admin()
{
	global $mybb, $page, $db, $lang;
	require_once MYBB_ROOT.'inc/functions_rebuild.php';
	if($page->active_action != 'recount_thanks')
	{
		return false;
	}

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
		{
			$mybb->input['page'] = 1;
		}
		if(isset($mybb->input['do_recountthanks']))
		{
			if(!intval($mybb->input['thx_chunk_size']))
			{
				$mybb->input['thx_chunk_size'] = 500;
			}

			do_recount();
		}
		else if(isset($mybb->input['do_recountposts']))
		{
			if(!intval($mybb->input['post_chunk_size']))
			{
				$mybb->input['post_chunk_size'] = 500;
			}

			do_recount_post();
		}
	}

	$page->add_breadcrumb_item($db->escape_string($lang->thx_recount), "index.php?module=tools/recount_thanks");
	$page->output_header($db->escape_string($lang->thx_recount));

	$sub_tabs['thankyoulike_recount'] = array(
		'title'			=> $db->escape_string($lang->thx_recount_do),
		'link'			=> "index.php?module=tools/recount_thanks",
		'description'	=> $db->escape_string($lang->thx_upgrade_do)
	);

	$page->output_nav_tabs($sub_tabs, 'thankyoulike_recount');

	$form = new Form("index.php?module=tools/recount_thanks", "post");

	$form_container = new FormContainer($db->escape_string($lang->thx_recount));
	$form_container->output_row_header($db->escape_string($lang->thx_recount_task));
	$form_container->output_row_header($db->escape_string($lang->thx_recount_send), array('width' => 50));
	$form_container->output_row_header("&nbsp;");

	$form_container->output_cell("<label>".$db->escape_string($lang->thx_recount_update)."</label>
	<div class=\"description\">".$db->escape_string($lang->thx_recount_update_desc)."</div>");
	$form_container->output_cell($form->generate_text_box("thx_chunk_size", 100, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->thx_recount_update_button), array("name" => "do_recountthanks")));
	$form_container->construct_row();

	$form_container->output_cell("<label>".$db->escape_string($lang->thx_counter_update)."</label>
	<div class=\"description\">".$db->escape_string($lang->thx_counter_update_desc).".</div>");
	$form_container->output_cell($form->generate_text_box("post_chunk_size", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->thx_recount_update_button), array("name" => "do_recountposts")));
	$form_container->construct_row();

	$form_container->end();

	$form->end();

	$page->output_footer();

	exit;
}

function do_recount()
{
	global $db, $mybb, $lang;

	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	$cur_page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['thx_chunk_size']);
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;

	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thx='0', thxcount='0'");
		$db->write_query("UPDATE ".TABLE_PREFIX."posts SET pthx='0'");
	}

	$query = $db->simple_select("thx", "COUNT(txid) AS thx_count");
	$thx_count = $db->fetch_field($query, 'thx_count');

	$query = $db->query("
		SELECT uid, adduid, pid
		FROM ".TABLE_PREFIX."thx
		ORDER BY time ASC
		LIMIT $start, $per_page
	");

	$post_thx = array();
	$user_thx = array();
	$user_thx_to = array();

	while($thx = $db->fetch_array($query))
	{
		if($post_thx[$thx['pid']])
		{
			$post_thx[$thx['pid']]++;
		}
		else
		{
			$post_thx[$thx['pid']] = 1;
		}
		if($user_thx[$thx['adduid']])
		{
			$user_thx[$thx['adduid']]++;
		}
		else
		{
			$user_thx[$thx['adduid']] = 1;
		}
		if($user_thx_to[$thx['uid']])
		{
			$user_thx_to[$thx['uid']]++;
		}
		else
		{
			$user_thx_to[$thx['uid']] = 1;
		}
	}

	if(is_array($post_thx))
	{
		foreach($post_thx as $pid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+$change WHERE pid='$pid'");
		}
	}
	if(is_array($user_thx))
	{
		foreach($user_thx as $adduid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET thx=thx+$change WHERE uid='$adduid'");
		}
	}
	if(is_array($user_thx_to))
	{
		foreach($user_thx_to as $uid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+$change WHERE uid='$uid'");
		}
	}
	my_check_proceed($thx_count, $end, $cur_page+1, $per_page, "thx_chunk_size", "do_recountthanks", $db->escape_string($lang->thx_update_psuccess));
}

function do_recount_post()
{
	global $db, $mybb, $lang;

	$cur_page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['post_chunk_size']);
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	
	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxpost='0'");
	}

	$query = $db->simple_select("thx", "COUNT(distinct pid) AS post_count");
	$post_count = $db->fetch_field($query, 'post_count');

	$query = $db->query("
		SELECT uid, pid
		FROM ".TABLE_PREFIX."thx
		GROUP BY pid
		ORDER BY pid ASC
		LIMIT $start, $per_page
	");

	while($thx = $db->fetch_array($query))
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxpost=thxpost+1 WHERE uid='{$thx['uid']}'");
	}

	my_check_proceed($post_count, $end, $cur_page+1, $per_page, "post_chunk_size", "do_recountposts", $db->escape_string($lang->thx_update_tsuccess));
}

function my_check_proceed($current, $finish, $next_page, $per_page, $name_chunk, $name_submit, $message)
{
	global $db, $page, $lang;
	
    if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	

	if($finish >= $current)
	{
		flash_message($message, 'success');
		admin_redirect("index.php?module=tools/recount_thanks");
	}
	else
	{
		$page->output_header();

		$form = new Form("index.php?module=tools/recount_thanks", 'post');
        $total = $current - $finish;
		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name_chunk, $per_page);
		echo $form->generate_hidden_field($name_submit, "Actualizar");
		echo "<div class=\"confirm_action\">\n";
		echo $db->escape_string($lang->thx_confirm_next);
		echo "<br />\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($db->escape_string($lang->thx_confirm_button), array('class' => 'button_yes'));
		echo "</p>\n";
		echo "<div style=\"float: right; color: #424242;\">".$db->escape_string($lang->thx_confirm_page)." {$next_page}\n";
		echo "<br />\n";
		echo $db->escape_string($lang->thx_confirm_elements)." {$total}</div>";
		echo "<br />\n";
	    echo "<br />\n";
		echo "</div>\n";		
		$form->end();
		$page->output_footer();
		exit;
	}
}

function thx_edit_group()
{
	global $run_module, $form_container, $form, $mybb, $lang;
		$lang->load("thx", false, true);
		
		$form_container = new FormContainer($lang->thx_admin_thx_group);
		$thx_options = array();
		$thx_options[] = $lang->thx_admin_thx_group_opt1.$form->generate_text_box('thx_max_ammount', $mybb->input['thx_max_ammount'], array('id' => 'max_thx_ammount', 'class' => 'field50'));
		$form_container->output_row($lang->messages, '', '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $thx_options).'</div>');
		$form_container->end();

}

function thx_edit_group_do()
{
	global $updated_group, $mybb;
	if($mybb->input['gid'] != 1)
	{
		$updated_group['thx_max_ammount'] = $mybb->input['thx_max_ammount'];
	}
}

?>