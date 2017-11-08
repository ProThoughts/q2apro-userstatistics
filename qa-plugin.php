<?php
/*
	Plugin Name: q2apro User Statistics
	Plugin URI: http://www.q2apro.com/plugins/userstatistics
	Plugin Description: A complete user statistic with detailed points per post and thumbs and more
	Plugin Version: 1.0
	Plugin Date: 2016-04-24
	Plugin Author: q2apro.com
	Plugin Author URI: http://www.q2apro.com/
	Plugin Minimum Question2Answer Version: 1.6
	Plugin Update Check URI: http://www.q2apro.com/pluginupdate?id=75
	
	Licence: Copyright © q2apro.com - All rights reserved
*/

if ( !defined('QA_VERSION') )
{
	header('Location: ../../');
	exit;
}

// admin
qa_register_plugin_module('module', 'q2apro-userstatistics-admin.php', 'q2apro_userstatistics_admin', 'q2apro User Statistics Admin');

// language file
qa_register_plugin_phrases('q2apro-userstatistics-lang-*.php', 'q2apro_userstatistics_lang');

// page
qa_register_plugin_module('page', 'q2apro-userstatistics-page.php', 'q2apro_userstatistics', 'User Statistics Page');


/*
	Omit PHP closing tag to help avoid accidental output
*/