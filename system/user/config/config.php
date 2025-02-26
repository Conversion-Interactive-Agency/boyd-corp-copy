<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// ExpressionEngine Config Items
$config['index_page'] = '';
$config['is_system_on'] = 'y';
$config['multiple_sites_enabled'] = 'n';
$config['show_ee_news'] = 'n';
$config['cookie_secure']= 'y';
$config['debug'] = '1'; // '1' for low debugging, '2' for medium, '3' for high

// ExpressionEngine Config Items
// Find more configs and overrides at
// https://docs.expressionengine.com/latest/general/system_configuration_overrides.html
$config['app_version'] = '5.2.3';
$config['encryption_key'] = '7f32eb78052390f35b5dd047df09255dd43498b1';
$config['session_crypt_key'] = '9cd207f065a1346f5d3961f061247319203230a7';

switch($_SERVER['HTTP_HOST']) {
    case 'corp-boydbros-com.ddev.site':
        $config['site_url'] = "https://corp-boydbros-com.ddev.site";
        $dbConnection = array (
            'hostname' => 'db',
            'database' => 'db',
            'username' => 'db',
            'password' => 'db',
            'dbprefix' => 'exp_',
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
            'port'     => ''
        );
        break;

    case 'dev.boydbros.com':
        $config['site_url'] = "https://dev.boydbros.com";
        $dbConnection = array (
            'hostname' => 'localhost',
            'database' => 'boydbros-com_staging',
            'username' => 'forge',
            'password' => 'WSIhwuZyeJJYlVcnGz3Z',
            'dbprefix' => 'exp_',
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
            'port'     => ''
        );
        break;

    default :
        $config['site_url'] = "https://boydbros.com";
        $dbConnection = array (
            'hostname' => 'localhost',
            'database' => 'boydbros-com',
            'username' => 'forge',
            'password' => 'WSIhwuZyeJJYlVcnGz3Z',
            'dbprefix' => 'exp_',
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
            'port'     => ''
        );
}
$config['database'] = array(
    'expressionengine' => $dbConnection
);

// EOF