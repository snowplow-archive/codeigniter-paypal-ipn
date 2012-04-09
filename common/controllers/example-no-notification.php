<?php
// codeigniter/controllers/example.php

/* This is an example controller showing how to use the PayPal_IPN library. This is a
 * simple example: it does not send any notification emails or similar, it simply
 * logs the order to the database.
 * 
 * This file is copyright (c) 2011 Alexander Dean, alex@keplarllp.com
 * 
 * This file is part of codeigniter-paypal-ipn
 * 
 * codeigniter-paypal-ipn is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 * 
 * codeigniter-paypal-ipn is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 * PURPOSE. See the GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * codeigniter-paypal-ipn. If not, see <http://www.gnu.org/licenses/>.
 */

class ExampleNoNotification extends Controller {

    // To handle the IPN post made by PayPal (uses the Paypal_Lib library).
    function ipn()
    {
        $this->load->library('PayPal_IPN'); // Load the library

        // Try to get the IPN data.
        if ($this->paypal_ipn->validateIPN())
        {
            // Succeeded, now let's extract the order
            $this->paypal_ipn->extractOrder();

            // And we save the order now (persist and extract are separate because you might only want to persist the order in certain circumstances).
            $this->paypal_ipn->saveOrder();

            // Now let's check what the payment status is and act accordingly
            if ($this->paypal_ipn->orderStatus == PayPal_IPN::PAID)
            {
                /* HEALTH WARNING:
                 *
                 * Please note that this PAID block does nothing. In other words, this controller will not respond to a successful order
                 * with any notification such as email or similar. You will have to identify paid orders by checking your database.
                 *
                 * If you want to send email notifications on successful receipt of an order, please see the alternative, Smarty template-
                 * based example controller: example-smarty-email-notification.php
                 */
            }
        }
        else // Just redirect to the root URL
        {
            $this->load->helper('url');
            redirect('/', 'refresh');
        }
    }
}
