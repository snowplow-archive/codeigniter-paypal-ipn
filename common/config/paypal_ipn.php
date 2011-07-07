<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------
| PAYPAL_IPN
| -------------------------------------------------------------------
| This file contains configuration variables for the PayPal_IPN library.
| Update this file to configure the environment and enable/disable
| logging/debugging features.
|
| This file is copyright (c) 2011 Alexander Dean, alex@keplarllp.com
|
| This file is part of codeigniter-paypal-ipn
| 
| codeigniter-paypal-ipn is free software: you can redistribute it and/or modify it under the
| terms of the GNU Affero General Public License as published by the Free Software Foundation,
| either version 3 of the License, or (at your option) any later version.
|  
| codeigniter-paypal-ipn is distributed in the hope that it will be useful, but WITHOUT ANY
| WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
| PURPOSE. See the GNU Affero General Public License for more details.
|  
| You should have received a copy of the GNU Affero General Public License along with
| codeigniter-paypal-ipn. If not, see <http://www.gnu.org/licenses/>.
*/

// Set this to TRUE or FALSE
$config['paypal_ipn_use_live_settings'] = FALSE;

// Constants for the live environment
$config['paypal_ipn_live_settings'] = array(
    'email' => 'sales@CHANGEME.com', // Your merchant email address
    'url' => 'https://www.paypal.com/cgi-bin/webscr', // PayPal's IPN handler for validating the data
    'debug' => FALSE // Whether we want debugging enabled (see below for explanation...)
);
// Debugging simply caches the latest PayPal IPN post data into the database, and retrieves it if
// validateIPN is called without post data. This is useful for directly trying a validateIPN call from
// the controller.

// Constants for the sandbox environment
$config['paypal_ipn_sandbox_settings'] = array(
    'email' => 'system_CHANGEME_biz@CHANGEME.com',
    'url' => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
    'debug' => TRUE
);

/* End of file paypal_ipn.php */
/* Location: ./system/application/config/paypal_ipn.php */
