<?php
/*
	Plugin Name: q2apro User Statistics
	Plugin Author: q2apro.com
*/

	class q2apro_userstatistics_admin
	{

		function option_default($option)
		{
			switch($option)
			{
				case 'q2apro_userstatistics_enabled':
					return 1; // true
				case 'q2apro_userstatistics_months':
					return 'January,February,March,April,May,June,July,August,September,October,November,December'; // true
				case 'q2apro_userstatistics_weekdays':
					return 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
				case 'q2apro_userstatistics_dateformat':
					return 'd.m.Y';
				default:
					return null;				
			}
		}
			
		function allow_template($template)
		{
			return ($template!='admin');
		}       
			
		function admin_form(&$qa_content)
		{                       

			// process the admin form if admin hit Save-Changes-button
			$ok = null;
			if (qa_clicked('q2apro_userstatistics_save'))
			{
				qa_opt('q2apro_userstatistics_enabled', (bool)qa_post_text('q2apro_userstatistics_enabled')); // empty or 1
				qa_opt('q2apro_userstatistics_months', preg_replace('/\s+/', '', trim(qa_post_text('q2apro_userstatistics_months'))));
				qa_opt('q2apro_userstatistics_weekdays', preg_replace('/\s+/', '', trim(qa_post_text('q2apro_userstatistics_weekdays'))));
				qa_opt('q2apro_userstatistics_dateformat', trim(qa_post_text('q2apro_userstatistics_dateformat')));
				$ok = qa_lang('admin/options_saved');
			}
			
			// form fields to display frontend for admin
			$fields = array();
			
			$fields[] = array(
				'type' => 'checkbox',
				'label' => qa_lang('q2apro_userstatistics_lang/enable_plugin'),
				'tags' => 'name="q2apro_userstatistics_enabled"',
				'value' => qa_opt('q2apro_userstatistics_enabled'),
			);
			
			$fields[] = array(
				'type' => 'text',
				'label' => qa_lang('q2apro_userstatistics_lang/monthnames').':',
				'tags' => 'name="q2apro_userstatistics_months"',
				'value' => qa_opt('q2apro_userstatistics_months'),
			);
			
			$fields[] = array(
				'type' => 'text',
				'label' => qa_lang('q2apro_userstatistics_lang/weekdaynames').':',
				'tags' => 'name="q2apro_userstatistics_weekdays"',
				'value' => qa_opt('q2apro_userstatistics_weekdays'),
			);
			
			$fields[] = array(
				'type' => 'text',
				'label' => qa_lang('q2apro_userstatistics_lang/dateformat').':',
				'tags' => 'name="q2apro_userstatistics_dateformat"',
				'value' => qa_opt('q2apro_userstatistics_dateformat'),
			);
			
			$fields[] = array(
				'type' => 'static',
				'note' => qa_lang('q2apro_userstatistics_lang/plugin_page_url').' <a target="_blank" href="'.qa_path('userstats').'">'.qa_opt('site_url').'userstats</a>',
			);
			
			$fields[] = array(
				'type' => 'static',
				'note' => '<span style="font-size:75%;color:#789;">'.strtr( qa_lang('q2apro_userstatistics_lang/contact'), array( 
							'^1' => '<a target="_blank" href="http://www.q2apro.com/plugins/userstatistics">',
							'^2' => '</a>'
						  )).'</span>',
			);
			
			return array(           
				'ok' => ($ok && !isset($error)) ? $ok : null,
				'fields' => $fields,
				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'name="q2apro_userstatistics_save"',
					),
				),
			);
		}
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/