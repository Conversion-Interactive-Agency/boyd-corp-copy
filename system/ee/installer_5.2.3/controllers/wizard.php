<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2019, EllisLab Corp. (https://ellislab.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

/**
 * ExpressionEngine Installation and Update Wizard
 */
class Wizard extends CI_Controller {

	public $version           = '5.2.3';	// The version being installed
	public $installed_version = ''; 		// The version the user is currently running (assuming they are running EE)
	public $schema            = NULL;		// This will contain the schema object with our queries
	public $languages         = array(); 	// Available languages the installer supports (set dynamically based on what is in the "languages" folder)
	public $mylang            = 'english';// Set dynamically by the user when they run the installer
	public $is_installed      = FALSE;	// Does an EE installation already exist?  This is set dynamically.
	public $next_update       = FALSE;	// The next update file that needs to be loaded, when an update is performed.
	public $remaining_updates = 0; 		// Number of updates remaining, in the event the user is updating from several back
	public $refresh           = FALSE;	// Whether to refresh the page for the next update.  Set dynamically
	public $refresh_url       = '';		// The URL where the refresh should go to.  Set dynamically
	public $base_path         = '';
	public $theme_path        = '';
	public $root_theme_path   = '';

	// Default page content - these are in English since we don't know the user's language choice when we first load the installer
	public $content           = '';
	public $title             = 'ExpressionEngine Installation and Update Wizard';
	public $header            = '';
	public $subtitle          = '';

	private $current_step = 1;
	private $steps        = 3;
	private $addon_step   = FALSE;

	public $now;
	public $year;
	public $month;
	public $day;

	// These are the methods that are allowed to be called via $_GET['m']
	// for either a new installation or an update. Note that the function names
	// are prefixed but we don't include the prefix here.
	public $allowed_methods = array('install_form', 'do_install', 'do_update');

	// Absolutely, positively must always be installed
	public $required_modules = array(
		'channel',
		'comment',
		'consent',
		'member',
		'stats',
		'rte',
		'file',
		'filepicker',
		'relationship',
		'search'
	);

	public $theme_required_modules = array();

	// Native First Party ExpressionEngine Modules (everything else is in third
	// party folder)
	public $native_modules = array('blacklist', 'channel', 'comment', 'commerce', 'consent',
		'email', 'emoticon', 'file', 'forum', 'gallery', 'ip_to_nation',
		'jquery', 'member', 'metaweblog_api', 'moblog', 'pages', 'query', 'relationship',
		'rss', 'rte', 'search', 'simple_commerce', 'stats', 'wiki', 'filepicker');

	// Third Party Modules may send error messages if something goes wrong.
	public $module_install_errors = array(); // array that collects all error messages

	// These are the values we need to set during a first time installation
	public $userdata = array(
		'app_version'           => '',
		'ext'                   => '.php',
		'ip'                    => '',
		'database'              => 'mysql',
		'db_hostname'           => 'localhost',
		'db_username'           => '',
		'db_password'           => '',
		'db_name'               => '',
		'db_prefix'             => 'exp',
		'db_char_set'           => 'utf8',
		'db_collat'             => 'utf8_unicode_ci',
		'site_label'            => '',
		'site_name'             => 'default_site',
		'site_url'              => '',
		'site_index'            => 'index.php',
		'cp_url'                => '',
		'username'              => '',
		'password'              => '',
		'password_confirm'      => '',
		'screen_name'           => '',
		'email_address'         => '',
		'webmaster_email'       => '',
		'deft_lang'             => 'english',
		'theme'                 => 'default',
		'default_site_timezone' => '',
		'redirect_method'       => 'redirect',
		'upload_folder'         => 'uploads/',
		'cp_images'             => 'cp_images/',
		'avatar_path'           => '../images/avatars/',
		'avatar_url'            => 'images/avatars/',
		'photo_path'            => '../images/member_photos/',
		'photo_url'             => 'images/member_photos/',
		'signature_img_path'    => '../images/signature_attachments/',
		'signature_img_url'     => 'images/signature_attachments/',
		'pm_path'               => '../images/pm_attachments',
		'captcha_path'          => '../images/captchas/',
		'theme_folder_path'     => '../themes/',
		'modules'               => array(),
		'install_default_theme' => 'n',
		'utf8mb4_supported'     => NULL,
		'share_analytics'       => 'n'
	);

	// These are the default values for the config array.  Since the
	// EE and legacy CI config files are one in the same now we use this data when we
	// write the initial config file using $this->write_config_data()
	public $ci_config = array(
		'subclass_prefix'    => 'EE_',
		'log_threshold'      => 0,
		'log_date_format'    => 'Y-m-d H:i:s',
		'encryption_key'     => NULL,

		// Enabled for cleaner view files and compatibility
		'rewrite_short_tags' => TRUE
	);

	/**
	 * Constructor
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();

		// retain in case third-party add-ons expect IS_CORE to be defined
		define('IS_CORE', FALSE);

		define('USERNAME_MAX_LENGTH', 75);
		define('PASSWORD_MAX_LENGTH', 72);
		define('URL_TITLE_MAX_LENGTH', 200);
		define('PATH_CACHE',  SYSPATH.'user/cache/');
		define('PATH_TMPL',   SYSPATH.'user/templates/');
		define('DOC_URL', 'https://docs.expressionengine.com/v5/');

		// Third party constants
		define('PATH_THIRD',  SYSPATH.'user/addons/');

		$req_source = $this->input->server('HTTP_X_REQUESTED_WITH');
		define('AJAX_REQUEST',	($req_source == 'XMLHttpRequest') ? TRUE : FALSE);

		$this->output->enable_profiler(FALSE);

		$this->userdata['app_version'] = $this->version;
		$this->userdata['default_site_timezone'] = date_default_timezone_get();

 		// Load the helpers we intend to use
 		$this->load->helper(array('form', 'url', 'html', 'directory', 'file', 'email', 'security', 'date', 'string'));

		// Load the language pack.  English is loaded on the installer home
		// page along with some radio buttons for each installed language pack.
		// Based on the users's choice we build the language into our URL string
		// and use that info to load the desired language file on each page

		$this->load->library('logger');

		$this->load->add_package_path(EE_APPPATH);

		$this->load->library('localize');
		$this->load->library('cp');
		$this->load->library('functions');
		$this->load->library('session');
		$this->load->driver('cache');
		$this->load->helper('language');
		$this->lang->loadfile('installer');
		$this->load->library('progress');

		$this->load->model('installer_template_model', 'template_model');

		// Update notices are used to print info at the end of
		// the update
		$this->load->library('update_notices');

		// Set the theme URLs
		$this->load->add_theme_cascade(APPPATH.'views/');

		// First try the current directory, if they are running the system with an admin.php file
		$this->base_path = substr($_SERVER['SCRIPT_FILENAME'], 0, -strlen(SELF));

		if (is_dir($this->base_path.'themes'))
		{
			$this->theme_path = $this->base_path.'themes/';
		}
		else
		{
			// Must be in a public system folder so try one level back from
			// current folder. Replace only the LAST occurance of the system
			// folder name with nil incase the system folder name appears more
			// than once in the path.
			$this->base_path = preg_replace('/\b'.preg_quote(SYSDIR).'(?!.*'.preg_quote(SYSDIR).')\b/', '', $this->base_path);
			$this->theme_path = $this->base_path.'themes/';
		}

		$this->base_path = str_replace('//', '/', $this->base_path);
		$this->root_theme_path = $this->theme_path;
		define('PATH_THEMES', $this->root_theme_path.'ee/');
		define('URL_THEMES', $this->root_theme_path.'ee/');
		$this->theme_path .= 'ee/site/';
		$this->theme_path = str_replace('//', '/', $this->theme_path);
		$this->root_theme_path = str_replace('//', '/', $this->root_theme_path);

		// Set the time
		$time = time();
		$this->now   = gmmktime(gmdate("H", $time), gmdate("i", $time), gmdate("s", $time), gmdate("m", $time), gmdate("d", $time), gmdate("Y", $time));
		$this->year  = gmdate('Y', $this->now);
		$this->month = gmdate('m', $this->now);
		$this->day   = gmdate('d', $this->now);

		ee('App')->setupAddons(SYSPATH . 'ee/EllisLab/Addons/');
	}

	/**
	 * Remap - Intercepts the request and dynamically determines what we should
	 * do
	 * @return void
	 */
	public function _remap()
	{
		$this->set_base_url();

		// Run our pre-flight tests.
		// This function generates its own error messages so if it returns FALSE
		// we bail out.
		if ( ! $this->preflight())
		{
			return FALSE;
		}

		$action = ee()->input->get('M') ?: FALSE;

		// If we're not at a defined stage, this is the first step.
		if ( ! $action)
		{
			if ($this->is_installed)
			{
				return $this->update_form();
			}
			else
			{
				return $this->install_form();
			}
		}

		// OK, at this point we have determined whether an existing EE
		// installation exists and we've done all our error trapping and
		// connected to the DB if needed

		// Is the action allowed?
		if ( ! in_array($action, $this->allowed_methods)
			OR ! method_exists($this, $action))
		{
			show_error(lang('invalid_action'));
		}

		// Call the action
		$this->$action();
	}

	/**
	 * Pre-flight Tests - Does all of our error checks
	 * @return void
	 */
	private function preflight()
	{
		// Is the config file readable?
		if ( ! include($this->config->config_path))
		{
			$this->set_output('error', array('error' => lang('unreadable_config')));
			return FALSE;
		}

		// Determine current version
		$this->installed_version = ee()->config->item('app_version');
		if (strpos($this->installed_version, '.') == FALSE) {
			$this->installed_version = implode(
				'.',
				str_split($this->installed_version)
			);
		}

		// Is the config file writable?
		if ( ! is_really_writable($this->config->config_path))
		{
			$this->set_output('error', array('error' => lang('unwritable_config')));
			return FALSE;
		}

		// Is the cache folder writable?
		if ( ! is_really_writable(PATH_CACHE))
		{
			$this->set_output('error', array('error' => lang('unwritable_cache_folder')));
			return FALSE;
		}

		// No config? This means it's a first time install...hopefully. There's
		// always a chance that the user nuked their config files. During
		// installation later we'll double check the existence of EE tables once
		// we know the DB connection values
		if ( ! isset($config))
		{
			// Is the email template file available? We'll check since we need
			// this later
			if ( ! file_exists(EE_APPPATH.'/language/'.$this->userdata['deft_lang'].'/email_data.php'))
			{
				$this->set_output('error', array('error' => lang('unreadable_email')));
				return FALSE;
			}

			// Are the DB schemas available?
			if ( ! is_dir(APPPATH.'schema/'))
			{
				$this->set_output('error', array('error' => lang('unreadable_schema')));
				return FALSE;
			}

			// set the image path and theme folder path
			$this->userdata['theme_folder_path'] = $this->root_theme_path;

			// At this point we are reasonably sure that this is a first time
			// installation. We will set the flag and bail out since we're done
			$this->is_installed = FALSE;
			return TRUE;
		}

		// Check for database.php, otherwise get normal config
		try
		{
			$db = $this->getDbConfig();
		}
		catch (Exception $e)
		{
			$this->set_output('error', array('error' => lang('database_no_data')));
			return FALSE;
		}

		// Can we connect?
		if ($this->db_connect($db) !== TRUE)
		{
			$this->set_output('error', array('error' => lang('database_no_config')));
			return FALSE;
		}

		// Try to include the RequirementsChecker class and check server requirements
		require_once(APPPATH.'updater/EllisLab/ExpressionEngine/Updater/Service/Updater/RequirementsChecker.php');

		$this->requirements = new RequirementsChecker($db);

		if (($result = $this->requirements->check()) !== TRUE)
		{
			$failed = array_map(function($requirement) {
				return $requirement->getMessage();
			}, $result);

			$this->is_installed = isset($config);
			$this->set_output('error', array('error' => implode('<br>', $failed)));
			return FALSE;
		}

		// EXCEPTIONS
		// We need to deal with a couple possible issues.

		// In 2.10.0, we started putting .'s in the app_verson config. The rest
		// of the code assumes this to be true, so we need to tweak their old config.
		if (strpos($config['app_version'], '.') === FALSE)
		{
			$cap = $config['app_version'];
			$config['app_version'] = "{$cap[0]}.{$cap[1]}.{$cap[2]}";
		}


		// OK, now let's determine if the update files are available and whether
		// the currently installed version is older then the most recent update

		// If this returns false it means the "updates" folder was not readable
		if ( ! $this->fetch_updates($config['app_version']))
		{
			$this->set_output('error', array('error' => lang('unreadable_update')));
			return FALSE;
		}

		// Make sure the Member module is installed in the case the user is
		// upgrading from an old Core installation
		if (ee('Addon')->get('member') !== NULL && ! ee('Addon')->get('member')->isInstalled())
		{
			ee()->load->library('addons');
			ee()->addons->install_modules(array('member'));
		}

		// If this is FALSE it means the user is running the most current
		// version. We will show the "you are running the most current version"
		// template
		if ($this->next_update === FALSE)
		{
			$this->assign_install_values();

			$vars['installer_path'] = '/'.SYSDIR.'/installer';

			// Set the path to the site and CP
			$host = ($this->isSecure()) ? 'https://' : 'http://';

			if (isset($_SERVER['HTTP_HOST']) AND $_SERVER['HTTP_HOST'] != '')
			{
				$host .= $_SERVER['HTTP_HOST'].'/';
			}

			$self = ( ! isset($_SERVER['PHP_SELF']) OR $_SERVER['PHP_SELF'] == '') ? '' : substr($_SERVER['PHP_SELF'], 1);

			// Since the CP access file can be inside or outside of the "system"
			// folder we will do a little test to help us set the site_url item
			$_selfloc = (is_dir('./installer/')) ? SELF.'/'.SYSDIR : SELF;

			$this->userdata['site_url'] = $host.substr($self, 0, - strlen($_selfloc));

			$vars['site_url'] = rtrim($this->userdata['site_url'], '/').'/'.$this->userdata['site_index'];

			$this->logger->updater("Update complete. Now running version {$this->version}.");

			// List any update notices we have
			$vars['update_notices'] = $this->update_notices->get();

			// Did we just install?
			$member_count = ee()->db->count_all_results('members');
			$last_visit = ee()->db->select('last_visit')
				->where('last_visit', 0)
				->count_all_results('members');
			$type = ($member_count == 1 && $last_visit == 1) ? 'install' : 'update';

			$this->is_installed = TRUE;
			$this->show_success($type, $vars);
			return FALSE;
		}

		// Before moving on, let's load the update file to make sure it's readable
		$ud_file = 'ud_'.$this->next_ud_file.'.php';

		if ( ! include(APPPATH.'updates/'.$ud_file))
		{
			$this->set_output('error', array('error' => lang('unreadable_files')));
			return FALSE;
		}

		// Assign the config and DB arrays to class variables so we don't have
		// to reload them.
		$this->_config = $config;
		$this->_db = $db;

		// Set the flag
		$this->is_installed = TRUE;

		// Onward!
		return TRUE;
	}

	/**
	 * New installation form
	 * @return void
	 */
	private function install_form($errors = FALSE)
	{
		// Reset current step
		$this->current_step = 1;

		// Assign the _POST array values
		$this->assign_install_values();

		$vars = array(
			'action' => $this->set_qstr('do_install')
		);

		// Are there any errors to display? When the user submits the
		// installation form, the $this->do_install() function is called. In
		// the event of errors the form will be redisplayed with the error
		// message
		$vars['errors'] = $errors;

		$this->subtitle = lang('required_fields');

		// Display the form and pass the userdata array to it
		$this->title = sprintf(lang('install_title'), '');
		$this->header = sprintf(lang('install_title'), $this->version);
		$this->set_output('install_form', array_merge($vars, $this->userdata));
	}

	/**
	 * Checks if the database host is valid
	 *
	 * @return boolean TRUE if successful, FALSE otherwise
	 */
	public function valid_db_host()
	{
		return $this->db_validation(2002, function () {
			ee()->form_validation->set_message(
				'valid_db_host',
				lang('database_invalid_host')
			);
		});
	}

	/**
	 * Check if the database given is valid
	 *
	 * @return boolean TRUE if successful, FALSE otherwise
	 */
	public function valid_db_database()
	{
		return $this->db_validation(1049, function() {
			ee()->form_validation->set_message(
				'valid_db_database',
				lang('database_invalid_database')
			);
		});
	}

	/**
	 * Given a MySQL error number, will check to see if that error was thrown
	 * and call the given callable if it is
	 *
	 * @param int $error_number The MySQL error number
	 * @param Callable $callable The function to call in case the error was thrown
	 * @return boolean TRUE if successful, FALSE otherwise
	 */
	private function db_validation($error_number, Closure $callable)
	{
		if (! ee()->input->post('db_hostname')
			|| ! ee()->input->post('db_name')
			|| ! ee()->input->post('db_username'))
		{
			$callable();
			return FALSE;
		}

		if ( ! isset($this->db_connect_attempt))
		{
			$this->db_connect_attempt = $this->db_connect(array(
				'hostname' => ee()->input->post('db_hostname'),
				'database' => ee()->input->post('db_name'),
				'username' => ee()->input->post('db_username'),
				'password' => ee()->input->post('db_password'),
				'dbprefix' => $this->getDbPrefix()
			));
		}

		if ($this->db_connect_attempt === $error_number)
		{
			$callable();
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Abstraction to retrieve the default or user over-ridden database prefix
	 */
	private function getDbPrefix()
	{
		return ($this->userdata['db_prefix'] == '') ? 'exp_' : preg_replace("#([^_])/*$#", "\\1_", $this->userdata['db_prefix']);
	}

	private function serverSupportsUtf8mb4()
	{
		static $supported;

		if (is_null($supported))
		{
			$msyql_server_version = ee('Database')->getConnection()->getNative()->getAttribute(PDO::ATTR_SERVER_VERSION);

			$supported = version_compare($msyql_server_version, '5.5.3', '>=');
		}

		return $supported;
	}

	private function clientSupportsUtf8mb4()
	{
		static $supported;

		if (is_null($supported))
		{
			$client_info = ee('Database')->getConnection()->getNative()->getAttribute(PDO::ATTR_CLIENT_VERSION);

			if (strpos($client_info, 'mysqlnd') === 0)
			{
				$msyql_client_version = preg_replace('/^mysqlnd ([\d.]+).*/', '$1', $client_info);
				$supported = version_compare($msyql_client_version, '5.0.9', '>=');
			}
			else
			{
				$msyql_client_version = $client_info;
				$supported = version_compare($msyql_client_version, '5.5.3', '>=');
			}
		}

		return $supported;
	}

	private function isUtf8mb4Supported()
	{
		return ($this->serverSupportsUtf8mb4() && $this->clientSupportsUtf8mb4());
	}

	/**
	 * Form validation callback for checking DB prefixes
	 *
	 * @param  string $db_prefix DB Prefix to validate
	 * @return boolean           TRUE if valid, FALSE if not
	 */
	public function valid_db_prefix($db_prefix)
	{
		// DB Prefix has some character restrictions
		if ( ! preg_match("/^[0-9a-zA-Z\$_]*$/", $db_prefix))
		{
			ee()->form_validation->set_message(
				'valid_db_prefix',
				lang('database_prefix_invalid_characters')
			);
			return FALSE;
		}

		// The DB Prefix should not include "exp_"
		if ( strpos($db_prefix, 'exp_') !== FALSE)
		{
			ee()->form_validation->set_message(
				'valid_db_prefix',
				lang('database_prefix_contains_exp_')
			);
			return FALSE;
		}

		return TRUE;
	}

	public function license_agreement($value)
	{
		if ($value !== 'y')
		{
			ee()->form_validation->set_message(
				'license_agreement',
				lang('license_agreement_not_accepted')
			);
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Perform the installation
	 * @return void
	 */
	private function do_install()
	{
		// Make sure the current step is the correct number
		$this->current_step = 2;

		// Assign the _POST array values
		$this->assign_install_values();
		$this->load->library('javascript');

		// Setup some basic configs for validation
		ee()->config->set_item('un_min_len', 4);
		ee()->config->set_item('pw_min_len', 5);

		// Setup form validation
		ee()->lang->loadfile('myaccount');
		ee()->load->library('form_validation');
		ee()->form_validation->set_error_delimiters('<em>', '</em>');
		ee()->form_validation->set_rules(array(
			array(
				'field' => 'db_hostname',
				'label' => 'lang:db_hostname',
				'rules' => 'required|callback_valid_db_host'
			),
			array(
				'field' => 'db_name',
				'label' => 'lang:db_name',
				'rules' => 'required|callback_valid_db_database'
			),
			array(
				'field' => 'db_username',
				'label' => 'lang:db_username',
				'rules' => 'required'
			),
			array(
				'field' => 'db_prefix',
				'label' => 'lang:db_prefix',
				'rules' => 'required|max_length[30]|callback_valid_db_prefix'
			),
			array(
				'field' => 'username',
				'label' => 'lang:username',
				'rules' => 'required|valid_username'
			),
			array(
				'field' => 'install_default_theme',
				'label' => 'lang:install_default_theme',
				'rules' => 'callback_themes_user_writable|callback_template_path_writeable'
			),
			array(
				'field' => 'password',
				'label' => 'lang:password',
				'rules' => 'required|valid_password[username]'
			),
			array(
				'field' => 'email_address',
				'label' => 'lang:email_address',
				'rules' => 'required|valid_email'
			),
			array(
				'field' => 'license_agreement',
				'label' => 'lang:license_agreement',
				'rules' => 'callback_license_agreement'
			)
		));

		// Bounce if anything failed
		if ( ! ee()->form_validation->run())
		{
			return $this->install_form();
		}

		// Start our error trapping
		$errors = array();

		// extract the port from the hostname if they specified one
		$this->setupDatabasePort();

		// Connect to the database.  We pass a multi-dimensional array since
		// that's what is normally found in the database config file
		$db = array(
			'port'     => $this->userdata['db_port'],
			'hostname' => $this->userdata['db_hostname'],
			'username' => $this->userdata['db_username'],
			'password' => $this->userdata['db_password'],
			'database' => $this->userdata['db_name'],
			'dbdriver' => 'mysqli',
			'dbprefix' => $this->getDbPrefix(),
			'swap_pre' => 'exp_',
			'db_debug' => TRUE, // We show our own errors
			'cache_on' => FALSE,
			'autoinit' => FALSE, // We'll initialize the DB manually
			'char_set' => $this->userdata['db_char_set'],
			'dbcollat' => $this->userdata['db_collat']
		);

		// we did some db connections on form validation callbacks so let's reset here to test specific compatibilities
		ee('Database')->closeConnection();

		$this->db_connect_attempt = $this->db_connect($db);
		if ($this->db_connect_attempt === 1044 OR $this->db_connect_attempt === 1045)
		{
			$errors[] = lang('database_invalid_user');
		}
		elseif ($this->db_connect_attempt === FALSE)
		{
			$errors[] = lang('database_no_connect');
		}
		elseif($this->db_connect_attempt === TRUE)
		{
			// Fallback to UTF8 if we cannot do UTF8MB4
			if ( ! $this->isUtf8mb4Supported())
			{
				if (is_null($this->userdata['utf8mb4_supported']))
				{
					$which = '';

					if ( ! $this->clientSupportsUtf8mb4())
					{
						$which = lang('client');

						if ( ! $this->serverSupportsUtf8mb4())
						{
							$which .= ' ' . lang('and') . ' ';
						}
					}

					if ( ! $this->serverSupportsUtf8mb4())
					{
						$which .= lang('server');
					}

					$this->userdata['utf8mb4_supported'] = FALSE;
					$errors[] = sprintf(lang('utf8mb4_not_supported'), $which);
				}
			}
			else
			{
				$db['char_set'] = $this->userdata['db_char_set'] = 'utf8mb4';
				$db['dbcollat'] = $this->userdata['db_collat'] = 'utf8mb4_unicode_ci';

				ee('Database')->closeConnection();
				$this->db_connect($db);
			}
		}

		// Does the specified database schema type exist?
		if ( ! file_exists(APPPATH.'schema/mysqli_schema.php'))
		{
			$errors[] = lang('unreadable_dbdriver');
		}

		// Were there errors?
		// If so we display the form and pass the userdata array to it
		if (count($errors) > 0)
		{
			$this->userdata['errors'] = $errors;
			$this->set_output('install_form', $this->userdata);
			return FALSE;
		}

		// --------------------------------------------------------------------

		// Set the screen name to be the same as the username
		$this->userdata['screen_name'] = $this->userdata['username'];

		// Load the DB schema
		require APPPATH.'schema/mysqli_schema.php';
		$this->schema = new EE_Schema();
		$this->schema->version = $this->version;

		// Assign the userdata array to the schema class
		$this->schema->userdata   =& $this->userdata;
		$this->schema->theme_path =& $this->theme_path;

		// Time
		$this->schema->now   = $this->now;
		$this->schema->year  = $this->year;
		$this->schema->month = $this->month;
		$this->schema->day   = $this->day;

		// --------------------------------------------------------------------

		// Safety check: Is the user trying to install to an existing installation?
		// This can happen if someone mistakenly nukes their config.php file
		// and then trying to run the installer...

		$query = ee()->db->query($this->schema->sql_find_like());

		if ($query->num_rows() > 0 AND ! isset($_POST['install_override']))
		{
			return $this->set_output('error', array(
				'error' => lang('install_detected_msg')
			));
		}

		// --------------------------------------------------------------------

		// No errors?  Move our tanks to the front line and prepare for battle!

		// We no longer need this:
		unset($this->userdata['password_confirm']);
		unset($_POST['password_confirm']);

		// We assign some values to the Schema class
		$this->schema->default_entry = $this->default_channel_entry();

		// Encrypt the password and unique ID
		ee()->load->library('auth');
		$hashed_password = ee()->auth->hash_password($this->userdata['password']);
		$this->userdata['password']  = $hashed_password['password'];
		$this->userdata['salt']      = $hashed_password['salt'];
		$this->userdata['unique_id'] = ee('Encrypt')->generateKey();

		// --------------------------------------------------------------------

		// This allows one to override the functions in Email Data below, thus allowing custom speciality templates
		if (file_exists($this->theme_path.$this->userdata['theme'].'/speciality_templates.php'))
		{
			require $this->theme_path.$this->userdata['theme'].'/speciality_templates.php';
		}

		// Load the email template
		require_once EE_APPPATH.'/language/'.$this->userdata['deft_lang'].'/email_data.php';

		// Install Database Tables!
		if ( ! $this->schema->install_tables_and_data())
		{
			$this->set_output('error', array('error' => lang('improper_grants')));
			return FALSE;
		}

		// Write the config file
		// it's important to do this first so that our site prefs and config file
		// visible for module and accessory installers
		if ($this->write_config_data() == FALSE)
		{
			$this->set_output('error', array('error' => lang('unwritable_config')));
			return FALSE;
		}

		// Add any modules required by the theme to the required modules array
		if ($this->userdata['theme'] != '' && isset($this->theme_required_modules[$this->userdata['theme']]))
		{
			$this->required_modules = array_merge($this->required_modules, $this->theme_required_modules[$this->userdata['theme']]);
		}

		// Install Modules!
		if ( ! $this->install_modules())
		{
			$this->set_output('error', array('error' => lang('improper_grants')));
			return FALSE;
		}

		// Install Site Theme!
		// This goes last because a custom installer might create Member Groups
		// besides the default five, which might affect the Template Access
		// permissions.
		if ($this->userdata['install_default_theme'] == 'y'
			&& ! $this->install_site_theme())
		{
			$this->set_output('error', array('error' => lang('improper_grants')));
			return FALSE;
		}

		// Build our success links
		$vars['installer_path'] = '/'.SYSDIR.'/installer';
		$vars['site_url'] = rtrim($this->userdata['site_url'], '/').'/'.$this->userdata['site_index'];

		// If errors are thrown, this is were we get the "human" names for those modules
		$vars['module_names'] = $this->userdata['modules'];

		// A flag used to determine if module install errors need to be shown in the view
		$vars['errors'] = count($this->module_install_errors);

		// The list of errors into a variable passed into the view
		$vars['error_messages'] = $this->module_install_errors;

		// Woo hoo! Success!
		$this->show_success('install', $vars);
	}

	public function template_path_writeable($radio)
	{
		if ( ! is_really_writable(PATH_TMPL))
		{
			ee()->form_validation->set_message(
				'template_path_writeable',
				lang('unwritable_templates')
			);
			return FALSE;
		}

		return TRUE;
	}

	public function themes_user_writable($radio)
	{
		if ( $radio == 'y' && ! is_really_writable($this->root_theme_path.'user'))
		{
			ee()->form_validation->set_message(
				'themes_user_writable',
				lang('unwritable_themes_user')
			);
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Split off the port, if given one (e.g. 192.168.10.2:4055)
	 */
	private function setupDatabasePort()
	{
		$db_hostname = $this->userdata['db_hostname'];

		if (strpos($db_hostname, ':') !== FALSE)
		{
			list($hostname, $port) = explode(':', $db_hostname);

			$this->userdata['db_hostname'] = $hostname;
			$this->userdata['db_port'] = $port;
		}
		else
		{
			$this->userdata['db_port'] = NULL;
		}
	}

	/**
	 * Get the DB Config, whether it's from database.php or config.php
	 *
	 * @return array Array of currently selected db group's db information. Must
	 *               contain 'database', 'username', and 'hostname'.
	 */
	public function getDbConfig()
	{
		$db_config = ee('Database')->getConfig();

		try
		{
			return $db_config->getGroupConfig();
		}
		catch (Exception $e)
		{
			// Suppress errors, if we can't find it, move along
			if (@include_once(SYSPATH.'/user/config/database.php'))
			{
				$group_config = $db[$db_config->getActiveGroup()];

				if ( ! empty($group_config))
				{
					return $group_config;
				}
			}

			throw new \Exception(lang('database_no_data'));
		}
	}

	/**
	 * Show installation or upgrade succes page
	 * @param  string $type               'update' or 'install'
	 * @param  array  $template_variables Anything to parse in the template
	 * @return void
	 */
	private function show_success($type = 'update', $template_variables = array())
	{
		$cp_login_url = $this->userdata['cp_url'].'?/cp/login&return=&after='.$type;

		// Try to rename automatically if there are no errors
		if ($this->rename_installer()
			&& empty($template_variables['errors'])
			&& empty($template_variables['error_messages']))
		{
			ee()->load->helper('url');
			redirect($cp_login_url);
		}

		// Are we back here from a input?
		if (ee()->input->get('download'))
		{
			ee()->load->helper('download');
			force_download(
				'mailing_list.zip',
				file_get_contents(SYSPATH.'user/cache/mailing_list.zip')
			);
		}

		// Make sure the title and subtitle are correct, current_step should be
		// the same as the number of steps
		$this->current_step = $this->steps;
		$this->title = sprintf(lang($type.'_success'), $this->version);
		$this->subtitle = lang('completed');

		// Put the version number in the success note
		$template_variables['success_note'] = sprintf(lang($type.'_success_note'), $this->version);

		// Send them to their CP via the form
		$template_variables['action'] = $this->set_qstr('show_success');
		$template_variables['method'] = 'get';
		$template_variables['cp_login_url'] = $cp_login_url;

		// Only show download button if mailing list export exists
		$template_variables['mailing_list'] = (file_exists(SYSPATH.'/user/cache/mailing_list.zip'));

		$this->set_output('success', $template_variables);
	}

	/**
	 * Assigns the values submitted in the settings form
	 * @return void
	 */
	private function assign_install_values()
	{
		// Set the path to the site and CP
		$host = ($this->isSecure()) ? 'https://' : 'http://';

		if (isset($_SERVER['HTTP_HOST']) AND $_SERVER['HTTP_HOST'] != '')
		{
			$host .= $_SERVER['HTTP_HOST'].'/';
		}

		$self = ( ! isset($_SERVER['PHP_SELF']) OR $_SERVER['PHP_SELF'] == '') ? '' : substr($_SERVER['PHP_SELF'], 1);
		$self = htmlspecialchars($self, ENT_QUOTES);

		$this->userdata['cp_url'] = ($self != '') ? $host.$self : $host.SELF;

		// Since the CP access file can be inside or outside of the "system" folder
		// we will do a little test to help us set the site_url item
		$_selfloc = (is_dir('./ee/installer/')) ? SELF.'/'.SYSDIR : SELF;

		// Set the site URL
		$this->userdata['site_url'] = $host.substr($self, 0, - strlen($_selfloc));

		// Set the URL for use in the form action
		$this->userdata['action'] = $this->set_qstr('do_install');

		$this->userdata['redirect_method']	= (DIRECTORY_SEPARATOR == '/') ? 'redirect' : 'refresh';

		// Assign the _POST values submitted via the form to our main data array
		foreach ($this->userdata as $key => $val)
		{
			if ($this->input->post($key) !== FALSE)
			{
				// module options is an array of checkboxes, so include all of them
				// but check any that the user submitted checked
				if ($key == 'modules')
				{
					foreach ($this->input->post($key) as $name)
					{
						$this->userdata[$key][$name]['checked'] = TRUE;
					}
				}
				else
				{
					$this->userdata[$key] = $this->input->post($key);

					// Be a bit more friendly by trimming most inputs, but leave passwords as-is
					if (! in_array($key, array('db_password', 'password', 'password_confirm')))
					{
						$this->userdata[$key] = trim($this->userdata[$key]);
					}
				}
			}
		}

		// Make sure the site_url has a trailing slash
		$this->userdata['site_url'] = preg_replace("#([^/])/*$#", "\\1/", $this->userdata['site_url']);
	}

	/**
	 * Show the update form
	 * @return void
	 */
	private function update_form()
	{
		$this->title = sprintf(lang('update_title'), $this->installed_version, $this->version);
		$vars['action'] = $this->set_qstr('do_update');
		$this->set_output('update_form', $vars);
	}

	/**
	 * Perform the update
	 * @return void
	 */
	private function do_update()
	{
		// Make sure the current step is the correct number
		$this->current_step = ($this->addon_step) ? 3 : 2;

		// ensures the Installer_Extensions lib is loaded which prevents extension hooks from running
		$this->load->library('extensions');

		$this->load->library('javascript');

		// if any of the underlying code uses caching, make sure we do nothing
		ee()->config->set_item('cache_driver', 'dummy');

		$next_version = $this->next_update;
		$this->progress->prefix = $next_version.': ';

		// Is this a call from the Progress Indicator?
		if ($this->input->get('progress') == 'yes')
		{
			echo $this->progress->get_state();
			exit;
		}
		elseif ($this->input->get('progress') == 'no')	// done with this step, moving on...
		{
			// End URL
			$this->refresh = TRUE;
			$this->refresh_url = $this->set_qstr('do_update&agree=yes');
			$this->title = sprintf(lang('updating_title'), $this->version);
			$this->subtitle = sprintf(lang('running_updates'), $next_version);
			return $this->set_output(
				'update_msg',
				array(
					'remaining_updates' => $this->remaining_updates,
					'next_version'		=> $this->progress->prefix.lang('version_update_text')
				)
			);
		}

		// Clear any latent status messages still present in the PHP session
		$this->progress->clear_state();

		// Set a liberal execution time limit, some of these updates are pretty
		// big.
		@set_time_limit(0);

		// Instantiate the updater class
		if (class_exists('Updater'))
		{
			$UD = new Updater;
		}
		else
		{
			$class = '\EllisLab\ExpressionEngine\Updater\Version_' . str_replace('.', '_', $next_version) . '\Updater';
			$UD = new $class;
		}

		$method = 'do_update';

		$this->load->library('smartforge');

		$this->logger->updater("Updating to {$next_version}");

		if ($this->config->item('ud_next_step') != FALSE)
		{
			$method = $this->config->item('ud_next_step');

			if ( ! method_exists($UD, $method))
			{
				$this->set_output('error', array('error' => str_replace('%x', htmlentities($method), lang('update_step_error'))));
				return FALSE;
			}
		}

		// is there a survey for this version?
		$survey_view = 'survey_'.$this->next_ud_file;

		// if (file_exists(APPPATH.'views/surveys/survey_'.$this->next_update.'.php'))
		// {
		// 	$this->load->library('survey');

		// 	// if we have data, send it on to the updater, otherwise, ask
		// 	// permission and show the survey
		// 	if ( ! $this->input->get_post('participate_in_survey'))
		// 	{
		// 		$data = array(
		// 			'action_url'            => $this->set_qstr('do_update&agree=yes'),
		// 			'ee_version'            => $this->next_update,
		// 			'participate_in_survey' => array(
		// 				'name'    => 'participate_in_survey',
		// 				'id'      => 'participate_in_survey',
		// 				'value'   => 'y',
		// 				'checked' => TRUE
		// 			)
		// 		);

		// 		foreach ($this->survey->fetch_anon_server_data() as $key => $val)
		// 		{
		// 			if (in_array($key, array('php_extensions', 'addons')))
		// 			{
		// 				$val = implode(', ', json_decode($val));
		// 			}

		// 			$data['anonymous_server_data'][$key] = $val;
		// 		}

		// 		$this->set_output('surveys/survey_'.$this->next_update, $data);
		// 		return FALSE;
		// 	}
		// 	elseif ($this->input->get_post('participate_in_survey') == 'y')
		// 	{
		// 		// if any preprocessing needs to be done on the POST data, we do
		// 		// it here
		// 		if (method_exists($UD, 'pre_process_survey'))
		// 		{
		// 			$UD->pre_process_survey();
		// 		}

		// 		$this->survey->send_survey($this->next_update);
		// 	}
		// }

		if (($status = $UD->{$method}()) === FALSE)
		{
			$error_msg = lang('update_error');

			if ( ! empty($UD->errors))
			{
				ee()->load->helper('html');
				$error_msg .= "</p>".ul($UD->errors)."<p>";
			}

			$this->set_output('error', array('error' => $error_msg));
			return FALSE;
		}

		if ($status !== TRUE)
		{
			$this->config->set_item('ud_next_step', $status);
			$this->next_update = $this->installed_version;
		}
		elseif ($this->remaining_updates == 1)
		{
			// If this is the last application update, run the module updater
			$this->update_modules();
		}

		// Update the config file
		$this->config->_update_config(array('app_version' => $this->next_update.$UD->version_suffix), array('ud_next_step' => ''));

		// EE's application settings are now in the config, so we need to make
		// two on the fly switches for the rest of the wizard to work.
		$this->set_base_url();
		$this->config->set_item('enable_query_strings', TRUE);

		// Set the refresh value
		$this->refresh = TRUE;
		$this->refresh_url = $this->set_qstr('do_update&agree=yes');

		// Kill the refresh if we're progressing with js
		if ($this->input->get('ajax_progress') == 'yes')
		{
			$this->refresh = FALSE;
		}

		$this->title = sprintf(lang('updating_title'), $this->version);
		$this->subtitle = sprintf(lang('running_updates'), $next_version);
		$this->set_output(
			'update_msg',
			array(
				'remaining_updates' => $this->remaining_updates,
				'next_version'      => $this->progress->prefix.lang('version_update_text')
			)
		);
	}

	/**
	 * Determine which update should be performed - Reads though the "updates"
	 * directory and makes a list of all available updates
	 * @param int $current_version The version we're currently running without
	 *                             dots (e.g. 300 or 292)
	 * @return boolean             TRUE if successful, FALSE if not
	 */
	private function fetch_updates($current_version = 0)
	{
		$next_update = FALSE;
		$next_ud_file = FALSE;

		$remaining_updates = 0;

		$path = APPPATH.'updates/';

		if ( ! is_readable($path))
		{
			return FALSE;
		}

		$files = new FilesystemIterator($path);

		foreach ($files as $file)
		{
			$file_name = $file->getFilename();

			if (preg_match('/^ud_0*(\d+)_0*(\d+)_0*(\d+).php$/', $file_name, $m))
			{
				$file_version = "{$m[1]}.{$m[2]}.{$m[3]}";

				if (version_compare($file_version, $current_version, '>'))
				{
					$remaining_updates++;

					if ( ! $next_update || version_compare($file_version, $next_update, '<'))
					{
						$next_update = $file_version;
						$next_ud_file = substr($file_name, 3, -4);
					}
				}
			}
		}

		$this->next_update = $next_update;
		$this->next_ud_file = $next_ud_file;
		$this->remaining_updates = $remaining_updates;

		return TRUE;
	}

	/**
	 * Connect to the database
	 *
	 * @param array $db Associative array containing db connection data
	 * @return boolean  TRUE if successful, FALSE if not
	 */
	private function db_connect($db)
	{
		if (count($db) == 0)
		{
			return FALSE;
		}

		$db_object = ee()->load->database($db, TRUE, TRUE);

		// Force caching off
		$db_object->save_queries = TRUE;

		// Ask for exceptions so we can show proper errors in the form
		$db_object->db_exception = TRUE;

		try
		{
			$db_object->initialize();
		}
		catch (Exception $e)
		{
			// If they're using localhost, fall back to 127.0.0.1
			if ($db['hostname'] == 'localhost')
			{
				ee('Database')->closeConnection();
				$this->userdata['db_hostname'] = '127.0.0.1';
				$db['hostname'] = '127.0.0.1';

				return $this->db_connect($db);
			}

			return ($e->getCode()) ?: FALSE;
		}

		ee()->remove('db');
		ee()->set('db', $db_object);

		return TRUE;
	}

	/**
	 * Get an actual path to certain items, namely global images, themes, and
	 * javascript.
	 * @param string  $path  The path to determine
	 * @param integer $depth How many levels up we are from the original
	 *                       directory
	 * @return string The realized path
	 */
	private function set_path($path = '', $depth = 0)
	{
		if ( ! is_dir($path) && $depth < 10)
		{
			$path = $this->set_path('../'.$path, ++$depth);
		}

		return $path;
	}

	/**
	 * Set output
	 * Loads the "container" view file and sets the content
	 * @param string $view  The name of the view to load
	 * @param array  $template_variables Associative array to pass to view
	 * @return void
	 */
	private function set_output($view, $template_variables = array())
	{
		ee()->load->library('view');

		// If we're dealing with an error, change the title to indicate that
		if ($view == "error")
		{
			$this->title = $this->is_installed
				? lang('update_failed')
				: lang('install_failed');
			$this->subtitle = $this->is_installed
				? sprintf(lang('error_updating'), $this->installed_version, $this->version)
				: sprintf(lang('error_installing'), $this->version);
		}

		$javascript_basepath = $this->set_path('themes/ee/asset/javascript/');
		$javascript_dir = (is_dir($javascript_basepath.'src/'))
			? 'src/'
			: 'compressed/';

		$version = explode('.', $this->version, 2);
		$data = array(
			'title'             => $this->title,
			'header'            => $this->header,
			'subtitle'          => $this->subtitle,
			'refresh'           => $this->refresh,
			'refresh_url'       => $this->refresh_url,
			'ajax_progress'     => (ee()->input->get('ajax_progress') == 'yes'),
			'javascript_path'   => $javascript_basepath.$javascript_dir,

			'version'           => $this->version,
			'version_major'     => $version[0],
			'version_minor'     => $version[1],
			'installed_version' => $this->installed_version,

			'next_version'      => substr($this->next_update, 0, 1).'.'.substr($this->next_update, 1, 1).'.'.substr($this->next_update, 2, 1),
			'languages'         => $this->languages,
			'theme_url'         => $this->set_path('themes'),

			'action'            => '',
			'method'            => 'post',
			'retry_link'        => $this->is_installed ? $this->set_qstr('do_update') : $this->set_qstr('do_install')
		);

		if ($this->is_installed)
		{
			// for some reason 'charset' is not set in this context and will
			// throw a PHP warning
			$msm_config = new MSM_Config();
			$msm_config->default_ini['charset'] = 'UTF-8';
			$msm_config->site_prefs('');
			$msm_config->load(); // Must come after site_prefs() so config.php can override
			$data['theme_url'] = $msm_config->item('theme_folder_url');
			$data['javascript_path'] = $data['theme_url'].'ee/asset/javascript/'.$javascript_dir;
		}

		$data = array_merge($data, $template_variables);

		ee()->load->helper('language');
		ee()->load->view('container', array_merge(
			array('content' => ee()->load->view($view, $data, TRUE)),
			$data
		));
	}

	/**
	 * Set the base URL and index values so our links work properly
	 * @return void
	 */
	private function set_base_url()
	{
		// We completely kill the site URL value.  It's now blank.
		// This enables us to use only the "index.php" part of the URL.
		// Since we do not know where the CP access file is being loaded from
		// we need to use only the relative URL
		$this->config->set_item('site_url', '');

		// We set the index page to the SELF value.
		// but it might have been renamed by the user
		$this->config->set_item('index_page', SELF);
		$this->config->set_item('site_index', SELF); // Same with the CI site_index
	}

	/**
	 * Create the query string needed for form actions
	 * @param string  $method The method name for the action
	 */
	private function set_qstr($method = '')
	{
		$query_string = 'C=wizard&M='.$method.'&language='.$this->mylang;
		return $this->config->item('index_page').'?'.$query_string;
	}

	/**
	 * Install the default site theme
	 * @return boolean  TRUE if successful, FALSE if not
	 */
	function install_site_theme()
	{
		// Set the site_short_name as default_site since that's used when
		// creating and saving template files
		ee()->config->set_item('site_short_name', 'default_site');

		if ($this->userdata['theme'] != ''&& $this->userdata['theme'] != 'none')
		{
			// Install any default structure and content that the theme may have
			if (file_exists(APPPATH.'/site_themes/'.$this->userdata['theme'].'/channel_set.json'))
			{
				$theme = ee('ThemeInstaller');
				$theme->setInstallerPath(APPPATH);
				$theme->setSiteURL($this->userdata['site_url']);
				$theme->setBasePath($this->base_path);
				$theme->setThemePath($this->root_theme_path);
				$theme->setThemeURL($this->set_path('themes'));
				$theme->install($this->userdata['theme']);
			}
		}

		return TRUE;
	}


	/**
	 * Install the Modules
	 * @return boolean  TRUE if successful, FALSE if not
	 */
	private function install_modules()
	{
		ee()->load->library('addons');
		$this->module_install_errors = ee()->addons->install_modules($this->required_modules);

		$consent = ee('Addon')->get('consent');
		$consent->installConsentRequests();

		return TRUE;
	}

	/**
	 * Write the config file
	 * @return boolean  TRUE if successful, FALSE if not
	 */
	private function write_config_data()
	{
		$captcha_url = '{base_url}images/captchas/';

		foreach (array('avatar_path', 'photo_path', 'signature_img_path', 'pm_path', 'captcha_path', 'theme_folder_path') as $path)
		{
			$prefix = ($path != 'theme_folder_path') ? $this->root_theme_path : '';
			$this->userdata[$path] = rtrim(realpath($prefix.$this->userdata[$path]), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			$this->userdata[$path] = str_replace($this->base_path, '{base_path}', $this->userdata[$path]);
		}

		$config = array(
			'db_port'                   => $this->userdata['db_port'],
			'db_hostname'               => $this->userdata['db_hostname'],
			'db_username'               => $this->userdata['db_username'],
			'db_password'               => $this->userdata['db_password'],
			'db_database'               => $this->userdata['db_name'],
			'db_dbprefix'               => $this->getDbPrefix(),
			'db_char_set'               => $this->userdata['db_char_set'],
			'db_collat'                 => $this->userdata['db_collat'],
			'app_version'               => $this->userdata['app_version'],
			'debug'                     => '1',
			'site_index'                => $this->userdata['site_index'],
			'site_label'                => $this->userdata['site_label'],
			'base_path'                 => $this->base_path,
			'base_url'                  => $this->userdata['site_url'],
			'cp_url'                    => str_replace($this->userdata['site_url'], '{base_url}', $this->userdata['cp_url']),
			'site_url'                  => '{base_url}',
			'theme_folder_url'          => '{base_url}themes/',
			'webmaster_email'           => $this->userdata['email_address'],
			'webmaster_name'            => '',
			'channel_nomenclature'      => 'channel',
			'max_caches'                => '150',
			'cache_driver'              => 'file',
			'captcha_url'               => $captcha_url,
			'captcha_path'              => $this->userdata['captcha_path'],
			'captcha_font'              => 'y',
			'captcha_rand'              => 'y',
			'captcha_require_members'   => 'n',
			'require_captcha'           => 'n',
			'enable_sql_caching'        => 'n',
			'force_query_string'        => 'n',
			'show_profiler'             => 'n',
			'include_seconds'           => 'n',
			'cookie_domain'             => '',
			'cookie_path'               => '/',
			'cookie_prefix'             => '',
			'website_session_type'      => 'c',
			'cp_session_type'           => 'c',
			'cookie_httponly'           => 'y',
			'allow_username_change'     => 'y',
			'allow_multi_logins'        => 'y',
			'password_lockout'          => 'y',
			'password_lockout_interval' => '1',
			'require_ip_for_login'      => 'y',
			'require_ip_for_posting'    => 'y',
			'require_secure_passwords'  => 'n',
			'allow_dictionary_pw'       => 'y',
			'name_of_dictionary_file'   => '',
			'xss_clean_uploads'         => 'y',
			'redirect_method'           => $this->userdata['redirect_method'],
			'deft_lang'                 => $this->userdata['deft_lang'],
			'xml_lang'                  => 'en',
			'send_headers'              => 'y',
			'gzip_output'               => 'n',
			'is_system_on'              => 'y',
			'allow_extensions'          => 'y',
			'date_format'               => '%n/%j/%Y',
			'time_format'               => '12',
			'include_seconds'           => 'n',
			'server_offset'             => '',
			'default_site_timezone'     => date_default_timezone_get(),
			'mail_protocol'             => 'mail',
			'email_newline'             => '\n', // single-quoted for portability
			'smtp_server'               => '',
			'smtp_username'             => '',
			'smtp_password'             => '',
			'email_smtp_crypto'         => 'ssl',
			'email_debug'               => 'n',
			'email_charset'             => 'utf-8',
			'email_batchmode'           => 'n',
			'email_batch_size'          => '',
			'mail_format'               => 'plain',
			'word_wrap'                 => 'y',
			'email_console_timelock'    => '5',
			'log_email_console_msgs'    => 'y',
			'log_search_terms'          => 'y',
			'un_min_len'                => '4',
			'pw_min_len'                => '5',
			'allow_member_registration' => 'n',
			'allow_member_localization' => 'y',
			'req_mbr_activation'        => 'email',
			'new_member_notification'   => 'n',
			'mbr_notification_emails'   => '',
			'require_terms_of_service'  => 'y',
			'default_member_group'      => '5',
			'profile_trigger'           => 'member',
			'member_theme'              => 'default',
			'enable_avatars'            => 'y',
			'allow_avatar_uploads'      => 'n',
			'avatar_url'                => '{base_url}'.$this->userdata['avatar_url'],
			'avatar_path'               => $this->userdata['avatar_path'],
			'avatar_max_width'          => '100',
			'avatar_max_height'         => '100',
			'avatar_max_kb'             => '50',
			'enable_photos'             => 'n',
			'photo_url'                 => '{base_url}'.$this->userdata['photo_url'],
			'photo_path'                => $this->userdata['photo_path'],
			'photo_max_width'           => '100',
			'photo_max_height'          => '100',
			'photo_max_kb'              => '50',
			'allow_signatures'          => 'y',
			'sig_maxlength'             => '500',
			'sig_allow_img_hotlink'     => 'n',
			'sig_allow_img_upload'      => 'n',
			'sig_img_url'               => '{base_url}'.$this->userdata['signature_img_url'],
			'sig_img_path'              => $this->userdata['signature_img_path'],
			'sig_img_max_width'         => '480',
			'sig_img_max_height'        => '80',
			'sig_img_max_kb'            => '30',
			'prv_msg_enabled'           => 'y',
			'prv_msg_allow_attachments' => 'y',
			'prv_msg_upload_path'       => $this->userdata['pm_path'],
			'prv_msg_max_attachments'   => '3',
			'prv_msg_attach_maxsize'    => '250',
			'prv_msg_attach_total'      => '100',
			'prv_msg_html_format'       => 'safe',
			'prv_msg_auto_links'        => 'y',
			'prv_msg_max_chars'         => '6000',
			'enable_template_routes'    => 'y',
			'strict_urls'               => 'y',
			'site_404'                  => '',
			'save_tmpl_revisions'       => 'n',
			'max_tmpl_revisions'        => '5',
			'save_tmpl_files'           => 'y',
			'deny_duplicate_data'       => 'y',
			'redirect_submitted_links'  => 'n',
			'enable_censoring'          => 'n',
			'censored_words'            => '',
			'censor_replacement'        => '',
			'banned_ips'                => '',
			'banned_emails'             => '',
			'banned_usernames'          => '',
			'banned_screen_names'       => '',
			'ban_action'                => 'restrict',
			'ban_message'               => 'This site is currently unavailable',
			'ban_destination'           => 'http://www.yahoo.com/',
			'enable_emoticons'          => 'y',
			'emoticon_url'              => '{base_url}'.'images/smileys/',
			'recount_batch_total'       => '1000',
			'image_resize_protocol'     => 'gd2',
			'image_library_path'        => '',
			'thumbnail_prefix'          => 'thumb',
			'word_separator'            => 'dash',
			'use_category_name'         => 'n',
			'reserved_category_word'    => 'category',
			'auto_convert_high_ascii'   => 'n',
			'new_posts_clear_caches'    => 'y',
			'auto_assign_cat_parents'   => 'y',
			'new_version_check'         => 'y',
			'enable_throttling'         => 'n',
			'banish_masked_ips'         => 'y',
			'max_page_loads'            => '10',
			'time_interval'             => '8',
			'lockout_time'              => '30',
			'banishment_type'           => 'message',
			'banishment_url'            => '',
			'banishment_message'        => 'You have exceeded the allowed page load frequency.',
			'enable_search_log'         => 'y',
			'max_logged_searches'       => '500',
			'memberlist_order_by'       => "member_id",
			'memberlist_sort_order'     => "desc",
			'memberlist_row_limit'      => "20",
			'is_site_on'                => 'y',
			'theme_folder_path'         => $this->userdata['theme_folder_path'],
		);

		// Default Administration Prefs
		$admin_default = array(
			'site_index',
			'base_url',
			'base_path',
			'cp_url',
			'site_url',
			'theme_folder_url',
			'webmaster_email',
			'webmaster_name',
			'channel_nomenclature',
			'max_caches',
			'captcha_url',
			'captcha_path',
			'captcha_font',
			'captcha_rand',
			'captcha_require_members',
			'require_captcha',
			'enable_sql_caching',
			'force_query_string',
			'show_profiler',
			'include_seconds',
			'cookie_domain',
			'cookie_path',
			'website_session_type',
			'cp_session_type',
			'allow_username_change',
			'allow_multi_logins',
			'password_lockout',
			'password_lockout_interval',
			'require_ip_for_login',
			'require_ip_for_posting',
			'require_secure_passwords',
			'allow_dictionary_pw',
			'name_of_dictionary_file',
			'xss_clean_uploads',
			'redirect_method',
			'deft_lang',
			'xml_lang',
			'send_headers',
			'gzip_output',
			'date_format',
			'time_format',
			'include_seconds',
			'server_offset',
			'default_site_timezone',
			'mail_protocol',
			'email_newline',
			'smtp_server',
			'smtp_username',
			'smtp_password',
			'email_smtp_crypto',
			'email_debug',
			'email_charset',
			'email_batchmode',
			'email_batch_size',
			'mail_format',
			'word_wrap',
			'email_console_timelock',
			'log_email_console_msgs',
			'log_search_terms',
			'deny_duplicate_data',
			'redirect_submitted_links',
			'enable_censoring',
			'censored_words',
			'censor_replacement',
			'banned_ips',
			'banned_emails',
			'banned_usernames',
			'banned_screen_names',
			'ban_action',
			'ban_message',
			'ban_destination',
			'enable_emoticons',
			'emoticon_url',
			'recount_batch_total',
			'new_version_check',
			'enable_throttling',
			'banish_masked_ips',
			'max_page_loads',
			'time_interval',
			'lockout_time',
			'banishment_type',
			'banishment_url',
			'banishment_message',
			'enable_search_log',
			'max_logged_searches',
			'theme_folder_path',
			'is_site_on'
		);

		$site_prefs = array();

		foreach($admin_default as $value)
		{
			$site_prefs[$value] = $config[$value];
		}

		ee()->db->where('site_id', 1);
		ee()->db->update('sites', array('site_system_preferences' => base64_encode(serialize($site_prefs))));

		// Default Members Prefs
		$member_default = array(
			'un_min_len',
			'pw_min_len',
			'allow_member_registration',
			'allow_member_localization',
			'req_mbr_activation',
			'new_member_notification',
			'mbr_notification_emails',
			'require_terms_of_service',
			'default_member_group',
			'profile_trigger',
			'member_theme',
			'enable_avatars',
			'allow_avatar_uploads',
			'avatar_url',
			'avatar_path',
			'avatar_max_width',
			'avatar_max_height',
			'avatar_max_kb',
			'enable_photos',
			'photo_url',
			'photo_path',
			'photo_max_width',
			'photo_max_height',
			'photo_max_kb',
			'allow_signatures',
			'sig_maxlength',
			'sig_allow_img_hotlink',
			'sig_allow_img_upload',
			'sig_img_url',
			'sig_img_path',
			'sig_img_max_width',
			'sig_img_max_height',
			'sig_img_max_kb',
			'prv_msg_enabled',
			'prv_msg_allow_attachments',
			'prv_msg_upload_path',
			'prv_msg_max_attachments',
			'prv_msg_attach_maxsize',
			'prv_msg_attach_total',
			'prv_msg_html_format',
			'prv_msg_auto_links',
			'prv_msg_max_chars',
			'memberlist_order_by',
			'memberlist_sort_order',
			'memberlist_row_limit'
		);

		$site_prefs = array();

		foreach($member_default as $value)
		{
			$site_prefs[$value] = $config[$value];
		}

		ee()->db->where('site_id', 1);
		ee()->db->update('sites', array('site_member_preferences' => base64_encode(serialize($site_prefs))));

		// Default Templates Prefs
		$template_default = array(
			'enable_template_routes',
			'strict_urls',
			'site_404',
			'save_tmpl_revisions',
			'max_tmpl_revisions',
		);
		$site_prefs = array();

		foreach($template_default as $value)
		{
			$site_prefs[$value] = $config[$value];
		}

		ee()->db->where('site_id', 1);
		ee()->db->update('sites', array('site_template_preferences' => base64_encode(serialize($site_prefs))));

		// Default Channels Prefs
		$channel_default = array(
			'image_resize_protocol',
			'image_library_path',
			'thumbnail_prefix',
			'word_separator',
			'use_category_name',
			'reserved_category_word',
			'auto_convert_high_ascii',
			'new_posts_clear_caches',
			'auto_assign_cat_parents',
			'enable_comments',
			'comment_word_censoring',
			'comment_moderation_override',
			'comment_edit_time_limit'
		);

		$site_prefs = array();

		foreach($channel_default as $value)
		{
			if (isset($config[$value]))
			{
				$site_prefs[$value] = $config[$value];
			}
		}

		ee()->db->where('site_id', 1);
		ee()->db->update('sites', array('site_channel_preferences' => base64_encode(serialize($site_prefs))));

		// Remove Site Prefs from Config
		foreach(array_merge($admin_default, $member_default, $template_default, $channel_default) as $value)
		{
			unset($config[$value]);
		}

		// Write the config file data
		$this->write_config_from_template($config);

		return TRUE;
	}

	/**
	 * Write config file from the template file
	 * @param array $config Config data to write to the config file
	 * @return boolean  TRUE if successful, FALSE if not
	 */
	private function write_config_from_template($config = array())
	{
		// Grab the existing config file
		if (count($config) == 0)
		{
			require $this->config->config_path;
		}

		// Add the CI config items to the array
		foreach ($this->ci_config as $key => $val)
		{
			$config[$key] = $val;
		}

		$config['encryption_key'] = ee('Encrypt')->generateKey();
		$config['session_crypt_key'] = ee('Encrypt')->generateKey();

		if (isset($config['site_index']))
		{
			$config['index_page'] = $config['site_index'];
		}

		if ($this->userdata['share_analytics'] == 'y')
		{
			$config['share_analytics'] = 'y';
		}

		// Fetch the config template
		$data = read_file(APPPATH.'config/config_tmpl.php');

		// Swap out the values
		foreach ($config as $key => $val)
		{
			// go ahead and prep all items here once, so we do not
			// have to do it again for $extra_config items below
			if (is_bool($val))
			{
				$config[$key] = ($val == TRUE) ? 'TRUE' : 'FALSE';
			}
			else
			{
				$val = str_replace("\\\"", "\"", $val);
				$val = str_replace("\\'", "'", $val);
				$val = str_replace('\\\\', '\\', $val);

				$val = str_replace('\\', '\\\\', $val);
				$val = str_replace("'", "\\'", $val);
				$val = str_replace("\"", "\\\"", $val);

				$config[$key] = $val;
			}

			if (strpos($data, '{'.$key.'}') !== FALSE)
			{
				$data = str_replace('{'.$key.'}', $config[$key], $data);
				unset($config[$key]);
			}
		}

		// any unanticipated keys that aren't in our template?
		$extra_config = '';

		// Remove site_label from $config since we don't want
		// it showing up in the config file.
		if ($config['site_label'])
		{
			unset($config['site_label']);
		}

		// Create extra_config, unset defaults
		$defaults = default_config_items();
		foreach ($config as $key => $val)
		{
			// Bypass defaults and empty values
			if (empty($val) || (isset($defaults[$key]) && $defaults[$key] == $val))
			{
				continue;
			}

			$extra_config .= "\$config['{$key}'] = '{$val}';\n";
		}

		$data = str_replace('{extra_config}', $extra_config, $data);

		// Did we have any {values} that didn't get replaced?
		// This looks for instances with quotes
		$data = preg_replace("/['\"]\{\S+\}['\"]/", '""', $data);
		// And this looks for instances without quotes
		$data = preg_replace("/\{\S+\}/", '""', $data);

		// Write config file
		if ( ! $fp = fopen($this->config->config_path, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $data, strlen($data));
		flock($fp, LOCK_UN);
		fclose($fp);

		// Clear any caches of the config file
		if (function_exists('apc_delete_file'))
		{
			@apc_delete_file($this->config->config_path) || apc_clear_cache();
		}

		if (function_exists('opcache_invalidate'))
		{
			// Check for restrict_api path restriction
			if (($opcache_api_path = ini_get('opcache.restrict_api')) && stripos(SYSPATH, $opcache_api_path) !== 0)
			{
				return TRUE;
			}

			opcache_invalidate($this->config->config_path);
		}

		return TRUE;
	}

	/**
	 * Update modules (first party only)
	 * @return void
	 */
	private function update_modules()
	{
		ee()->db->select('module_name, module_version');
		$query = ee()->db->get('modules');

		foreach ($query->result() as $row)
		{
			$module = strtolower($row->module_name);

			// Only update first-party modules
			if ( ! in_array($module, $this->native_modules))
			{
				continue;
			}

			// Send version to update class and let it do any required work
			if (in_array($module, $this->native_modules))
			{
				$path = EE_APPPATH.'/modules/'.$module.'/';
			}
			else
			{
				$path = PATH_THIRD.$module.'/';
			}

			if (file_exists($path.'upd.'.$module.'.php'))
			{
				$this->load->add_package_path($path);

				$class = ucfirst($module).'_upd';

				if ( ! class_exists($class))
				{
					require $path.'upd.'.$module.'.php';
				}

				$UPD = new $class;
				$UPD->_ee_path = EE_APPPATH;

				if ($UPD->version > $row->module_version && method_exists($UPD, 'update') && $UPD->update($row->module_version) !== FALSE)
				{
					ee()->db->update('modules', array('module_version' => $UPD->version), array('module_name' => ucfirst($module)));
				}

				$this->load->remove_package_path($path);
			}
		}
	}

	/**
	 * Get the default channel entry data
	 * @return string
	 */
	private function default_channel_entry()
	{
		return read_file(APPPATH.'language/'.$this->userdata['deft_lang'].'/channel_entry_lang.php');
	}

	/**
	 * Checks to see if we're allowed to automatically rename the installer dir
	 *
	 * @return bool TRUE if we can rename, FALSE if we can't
	 */
	public function canRenameAutomatically()
	{
		if (version_compare($this->version, '3.0.0', '=')
			&& file_exists(SYSPATH.'user/cache/mailing_list.zip'))
		{
			return FALSE;
		}

		if ( ! empty($template_variables['error_messages']))
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Rename the installer
	 * @return void
	 */
	private function rename_installer()
	{
		if ( ! $this->canRenameAutomatically())
		{
			return FALSE;
		}

		// Generate the new path by suffixing a dotless version number
		$new_path = str_replace(
			'installer',
			'installer_'.$this->version,
			APPPATH
		);

		// Move the directory
		return @rename(APPPATH, $new_path);
	}

	/**
	 * Is this an https:// connection?
	 *
	 * @return bool Is it https?
	 */
	private function isSecure()
	{
		if ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
			|| $_SERVER['SERVER_PORT'] == '443')
		{
			return TRUE;
}

		return FALSE;
	}
}

// EOF
