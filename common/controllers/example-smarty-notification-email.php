<?php
// codeigniter/controllers/example-smarty-email-notification.php

/* This is an example controller showing how to use the PayPal_IPN library
 * This controller sends an email with the order confirmation, using the
 * Smarty template library. You could easily update it to use Twig
 * templates or similar.
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

class ExampleSmartyEmailNotification extends Controller {

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
                /* HEATH WARNING:
                 *
                 * You'll have to create your own email template using Smarty, Twig or similar for this to work.
                 * If you do not have this, then the code below will cause your Paypal IPN responses to fail
                 * with 500 errors without your knowledge.
                 */
				
                // Configure to send HTML emails.
                $this->load->library('email');
                $mail_config['mailtype'] = 'html';
                $this->email->initialize($mail_config);

                // Prepare the variables to populate the email template:
                $data = $this->paypal_ipn->order;
                $data['items'] = $this->paypal_ipn->orderItems;

                // Now construct the email
                $emailBody = $this->smarty->view('confirmation_email.tpl', $data, TRUE); // You'll have to create your own email template using Smarty, Twig or similar

                // Finish configuring email contents and send.
                $this->email->to($data['payer_email'], ($data['first_name'] . ' ' . $data['last_name']));
                $this->email->bcc('sales@CHANGEME.com');
                $this->email->from('support@CHANGEME.com', 'CHANGEME');
                $this->email->subject('Order confirmation');
                $this->email->message($emailBody);
                $this->email->send();
            }
        }
        else // Just redirect to the root URL
        {
            $this->load->helper('url');
            redirect('/', 'refresh');
        }
    }
}
