<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package CodeIgniter
 * @author  ExpressionEngine Dev Team
 * @copyright  Copyright (c) 2006, EllisLab, Inc.
 * @license http://codeigniter.com/user_guide/license.html
 * @link http://codeigniter.com
 * @since   Version 1.0
 * @filesource
 */

/**
 * PayPal IPN Library
 *
 * A CodeIgniter library to act as a listener for the PayPal Instant Payment Notification
 * (IPN) interface. Uses Doctrine 1.2
 *
 * DISCLAIMER: The author Alex Dean does not accept any liability for any processing errors made by
 * this library, or any financial losses incurred through its use. In particular, this library
 * does NOT fulfil the PayPal IPN requirement to "verify that the payment amount actually
 * matches what you intend to charge. Although not technically an IPN issue, if you do
 * not encrypt buttons, it is possible for someone to capture the original transmission
 * and change the price. Without this check, you could accept a lesser payment than what
 * you expected." (This verification step is out of scope for this library because it
 * would require integration with your product catalogue.)
 *
 * This library focuses on the "post-payment" workflow, i.e. the processing required once
 * the payment has been made and PayPal has posted an Instant Payment Notification call to
 * the IPN controller.
 *
 * This library handles:
 *  - Validating the IPN call
 *  - Extracting the order and line item information from the IPN call
 *  - Interpreting PayPal's payment status
 *  - Storing the order and line item in the database
 *
 * Dependencies:
 *  - Doctrine (tested with 1.2.3), http://codeigniter.com/wiki/Using_Doctrine_with_Code_Igniter/
 *
 * All pre-payment functionality (e.g. posting the checkout information to PayPal) and custom
 * post-payment workflow (e.g. sending emails) is left as an exercise to the reader.
 *
 * This library is inspired by:
 *  - Ran Aroussi's PayPal_Lib for CodeIgniter, http://aroussi.com/ci/
 *  - Micah Carrick's Paypal PHP class, http://www.micahcarrick.com
 *
 * For usage, please see the README.markdown file
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Commerce
 * @author      Alex Dean <alex@keplarllp.com>
 * @link		https://github.com/orderly/codeigniter-paypal-ipn
 * @copyright   Copyright (c) 2011 Alex Dean
 * @version     0.1
 */

class PayPal_IPN
{
    private $_ci; // CodeIgniter instance

    public $ipnData = array(); // Contains the POST values for IPN
    public $order = array(); // Contains the fields pertaining to an order
    public $orderItems = array(); // Contains the order items within an order
    public $orderStatus; // All-important variable: whether the order has been paid for yet or not

    // Configuration constants
    private $isLive; // The flag used to indicate we are operating in the live environment
    private $ipnURL; // The PayPal IPN URL we're using
    private $merchantEmail; // The merchant's email address connected to PayPal

    // Used for logging
    private $logID; // The ID of our IpnLog record
    private $transactionID; // The transaction ID aka txn_id (from PayPal)
    private $transactionType; // The type of transaction (from PayPal)

    // Payment status constants we use, more user-friendly than the PayPal ones
    const PAID = 'PAID';
    const WAITING = 'WAITING';
    const REJECTED = 'REJECTED';

    // The constructor. Loads the helpers and configuration files, sets the configuration constants
    function __construct()
	{
        $this->_ci =& get_instance();
        $this->_ci->load->config('paypal_ipn'); // The custom configuration file for this library

        // Configuration var
        $this->isLive = $this->_ci->config->item('paypal_ipn_use_live_settings');

        // Settings
        $settings = $this->_ci->config->item('paypal_ipn_' . (($this->isLive) ? "live" : "sandbox") . '_settings');
        $this->ipnURL = $settings['url'];
        $this->merchantEmail = $settings['email'];
        $this->debug = $settings['debug'];
    }

    // The key functionality in this library. Extracts the fields from the IPN notification and then
    // sends them back to PayPal to make sure that the post is not bogus.
    // We also carry out the following validations as per PayPal's guidelines (https://cms.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=developer/e_howto_admin_IPNIntro):
    // - "Verify that you are the intended recipient of the IPN message by checking the email address in the message;
    //    this handles a situation where another merchant could accidentally or intentionally attempt to use your listener."
    // - "Avoid duplicate IPN messages. Check that you have not already processed the transaction identified by the transaction
    //    ID returned in the IPN message. You may need to store transaction IDs returned by IPN messages in a file or database so
    //    that you can check for duplicates. If the transaction ID sent by PayPal is a duplicate, you should not process it again."
    //    Actually PayPal is wrong on this last one - duplicate transaction IDs are fine, PayPal can send multiple different
    //    messages with the same transaction ID. That's why we store the md5 instead.
    public function validateIPN()
    {
        // Set these all to null
        $this->logID = null;
        $this->transactionID = null;
        $this->transactionType = null;

        // First check that we have post data.
        $usingCache = FALSE;
        if (!empty($_POST))
        {
            $ipnDataRaw = $_POST;

            // If we're in debugging mode, then we cache this POST data in the log for potential use later
            if ($this->debug)
            {
                $this->_cacheIPN($ipnDataRaw);
            }
        }
        else
        {
            // If we're in debug mode, let's try to get the last cached IPN data instead
            if ($this->debug)
            {
                if ($ipnDataRaw = $this->_getCachedIPN())
                {
                    $usingCache = TRUE;
                }
                else
                {
                    $this->_logTransaction('IPN', 'ERROR', 'No POST data and no cached IPN data');
                    return FALSE;
                }
            }
            else // Not in debug mode
            {
                $this->_logTransaction('IPN', 'ERROR', 'No POST data'); // Nulls because we don't have a transaction type or ID yet
                return FALSE;
            }
        }

        // First thing we do is to log this transaction. This way if the script silently fails, there is still a record of the IPN call.
        // (If the script succeeds, or another error occurs, then this error message will be overwritten).
        $this->_logTransaction('IPN', 'ERROR', 'Script failed silently, detail field contains the serialized data from PayPal', serialize($ipnDataRaw));

        // Before doing anything else, let's clean up our post data.
        foreach (array_keys($ipnDataRaw) as $field)
        {
            if (!$usingCache)
            {
                $value = $this->_ci->input->post($field); // Note that CodeIgniter standardises line returns to \n
                // Put line feeds back to \r\n for PayPal otherwise multi-line data will be rejected as INVALID
                $ipnDataRaw[$field] = str_replace("\n", "\r\n", $value);
            }

            // Let's also store this in this class, turning empty strings back to null to avoid breaking Doctrine later
            $this->ipnData[$field] = ($ipnDataRaw[$field] == '') ? null : $ipnDataRaw[$field];
        }

        // Let's now set the transaction type and transaction ID, we'll use these for logging.
        $this->transactionID = isset($this->ipnData['txn_id']) ? $this->ipnData['txn_id'] : null;
        $this->transactionType = isset($this->ipnData['txn_type']) ? $this->ipnData['txn_type'] : null;

        // Now we need to check that we haven't received this message from PayPal before.
        // Luckily we store an md5 hash of each IPN dataset so we can cross-check whether this one is new.
        $ipnDataHash = md5(serialize($ipnDataRaw));
        if (!$usingCache && $this->_checkForDuplicates($ipnDataHash))
        {
            $this->_logTransaction('IPN', 'ERROR', 'This is a duplicate call: md5 hash ' . $ipnDataHash . ' already logged');
            return FALSE;
        }

        // Now we need to ask PayPal to tell us if it sent this notification
        $ipnResponse = $this->_postData($this->ipnURL, array_merge(
                                                                    array('cmd' => '_notify-validate'),
                                                                    $ipnDataRaw
                                                            ));
        if ($ipnResponse === FALSE) // Bail out if we have an error.
        {
            return FALSE;
        }

        // Check that PayPal says that the IPN call we received is not invalid
        if (stristr("INVALID", $ipnResponse))
        {
            // Invalid IPN transaction.  Check the log for details.
            $this->_logTransaction('IPN', 'ERROR', 'PayPal rejected the IPN call as invalid - potentially call was spoofed or was not checked within 30s', $ipnResponse);
            return FALSE;
        }

        // The IPN transaction is a genuine one - now we need to validate its contents.
        // First we check that the receiver email matches our email address.
        if ($this->ipnData['receiver_email'] != $this->merchantEmail)
        {
            $this->_logTransaction('IPN', 'ERROR', 'Receiver email ' . $this->ipnData['receiver_email'] . ' does not match merchant\'s', $ipnResponse);
            return FALSE;
        }

        // Now we check that PayPal and this listener agree on whether this is a test or not
        $testIPN = (isset($this->ipnData['test_ipn']) && $this->ipnData['test_ipn'] == 1);
        if ($testIPN == $this->isLive)
        {
            $this->_logTransaction('IPN', 'ERROR', 'Listener\'s environment does not match test_ipn flag ' . $this->ipnData['test_ipn'], $ipnResponse);
            return FALSE;
        }

        // The final check is of the payment status. We need to surface this
        // as a class variable so that the calling code can decide how to respond.
        //
        // PayPal has various different payment statuses' - we list them below,
        // but for simplicity we generalise these into three categories:
        //  - PAID - meaning the merchant can now dispatch the order
        //  - WAITING - wait for another update from PayPal with this transaction_id
        //  - REJECTED - meaning that payment failed and the order should be rejected
        // The following page was invaluable in understanding the different PayPal payment
        // statuses: http://www.coderprofile.com/networks/discussion-forum/1305/paypal-help-ipn-payment_status
        //
        // We throw an error if the payment_status code is unrecognised.
        switch ($this->ipnData['payment_status'])
        {
            case "Completed": // Order has been paid for
                $this->orderStatus = self::PAID;
                break;
            case "Pending": // Payment is still waiting to go through
            case "Processed": // Mostly used to indicate that a cheque has been received and is currently going through the verification process
                $this->orderStatus = self::WAITING;
                break;
            case "Voided": // Bounced or cancelled check
            case "Expired": // Credit card company didn't recognise card
            case "Reversed": // Credit card holder has got the credit card co to reverse the charge
                $this->orderStatus = self::REJECTED;
                break;
            default:
                $this->_logTransaction('IPN', 'ERROR', 'Payment status of ' . $this->ipnData['payment_status'] . ' is not recognised', $ipnResponse);
                return FALSE;
        }

        // Phew! We have a valid IPN transaction, log it.
        $this->_logTransaction('IPN', 'SUCCESS', 'Parsing, authentication and validation complete', $ipnResponse);
        return true;
    }

    // Function to extract the order and order items from the $ipnData
    public function extractOrder()
    {
        // First extract the actual order record itself
        foreach ($this->ipnData as $key=>$value)
        {
            // This is very simple: the order fields are any fields which do not end in a number
            // (because those fields belong to the order items)
            if (preg_match("/.*?(\d+)$/", $key) == 0)
            {
                $this->order[$key] = $value;
            }
        }

        // Let's store the payment status too
        $this->order['order_status'] = $this->orderStatus;

        // Now retrieve the line items which belong to this order
        $numItems = (int)$this->order['num_cart_items'];
        $totalBeforeDiscount = 0;
        for ($i = 1; $i <= $numItems; $i++)
        {
            $this->orderItems[$i] = array();
            $this->orderItems[$i]['item_name'] = $this->ipnData['item_name' . $i];
            $this->orderItems[$i]['item_number'] = $this->ipnData['item_number' . $i];
            $this->orderItems[$i]['quantity'] = $this->ipnData['quantity' . $i];
            $this->orderItems[$i]['mc_gross'] = $this->ipnData['mc_gross_' . $i]; // Note the trailing underscore. PayPal oddity
            $this->orderItems[$i]['mc_handling'] = $this->ipnData['mc_handling' . $i];
            $this->orderItems[$i]['mc_shipping'] = $this->ipnData['mc_shipping' . $i];
            $this->orderItems[$i]['tax'] = (isset($this->ipnData['tax' . $i]) ? $this->ipnData['tax' . $i] : null); // Tax is not always set on an item
            $this->orderItems[$i]['cost_per_item'] = intval($this->ipnData['mc_gross_' . $i]) / intval($this->ipnData['quantity' . $i]); // Should be fine because quantity can never be 0
            $totalBeforeDiscount +=  $this->orderItems[$i]['mc_gross'];
        }

        // And calculate the discount, as it's useful to add this into emails etc
        $this->order['discount'] = $totalBeforeDiscount - $this->order['mc_gross'];
    }

    // Ugly function to deal with the fact that a lot of people don't have curl or similar setup.
    // If you do have curl setup in PHP, and @philsturgeon's cURL library for CodeIgniter installed,
    // you can replace the contents of this function with:
    // $this->_ci->load->library('curl');
    // $response = $this->_ci->curl->simple_post($url, $postData);
    // // Error logging
    // return $response;
    function _postData($url, $postData)
    {
        // Put the postData into a string
        $postString = '';
        foreach ($postData as $field=>$value)
        {
            $postString .= $field . '=' . urlencode(stripslashes($value)) . '&'; // Trailing & at end of post string is forgivable
        }

        $parsedURL = parse_url($url);

        // fsockopen is a bit odd - it just takes the host without the scheme - unless you want to use
        // ssl, in which case you need to use ssl://, not http://
        $ipnURL = ($parsedURL['scheme'] == "https") ? "ssl://" . $parsedURL['host'] : $parsedURL['host'];
        // Likewise, if using ssl, then need to change port from 80 to 443
        $ipnPort = ($parsedURL['scheme'] == "https") ? 443 : 80;
        $fp = fsockopen($ipnURL, $ipnPort, $errorNumber, $errorString, 30);

        // Log and return if we have an error
        if (!$fp)
        {
            $this->_logTransaction("IPN", "ERROR", "fsockopen error number $errorNumber: $errorString connecting to " . $parsedURL['host'] . " on port " . $ipnPort);
            return FALSE;
        }

        fputs($fp, "POST $parsedURL[path] HTTP/1.1\r\n");
        fputs($fp, "Host: $parsedURL[host]\r\n");
        fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
        fputs($fp, "Content-length: " . strlen($postString) . "\r\n");
        fputs($fp, "Connection: close\r\n\r\n");
        fputs($fp, $postString . "\r\n\r\n");

        $response = '';
        while (!feof($fp))
        {
            $response .= fgets($fp, 1024);
        }
        fclose($fp); // Close connection

        if (strlen($response) == 0)
        {
            $this->_logTransaction("IPN", "ERROR", "Response from PayPal was empty");
            return FALSE;
        }

        return $response;
    }

    /* Code below this point is all ORM-specific. In this version, it is dependent on Doctrine 1.2 */

    // Save an IPN record (insert/update depending on if there is an existing row or not)
    // Doctrine version
    function _cacheIPN($ipnDataRaw)
    {
        // If we don't already have a cache row,
        if (!($cache = Doctrine::getTable('IpnLog')->findOneByListener_nameAndTransaction_type('IPN', 'cache')))
        {
            $cache = new IpnLog; // Create a new one
        }

        // Let's log the raw IPN data - either update or insert
        $cache->listener_name = 'IPN';
        $cache->transaction_type = 'cache';
        $cache->detail = serialize($ipnDataRaw);
        $cache->save();
    }

    // Retrieve the cached IPN record if there is one, false if there isn't
    // Doctrine version
    function _getCachedIPN()
    {
        if (!($cache = Doctrine::getTable('IpnLog')->findOneByListener_nameAndTransaction_type('IPN', 'cache')))
        {
            return FALSE;
        }
        else
        {
            return unserialize($cache->detail);
        }
    }

    // Check for a duplicate IPN call using the md5 hash
    function _checkForDuplicates($hash)
    {
        return Doctrine::getTable('IpnLog')->findOneByIpn_data_hash($hash);
    }

    // Function to persist the order and the order items to the database.
    // Note is that an order may already exist in the system, and this IPN
    // call is just to update the record - e.g. changing its payment status.
    // Doctrine version
    public function saveOrder()
    {
        // First check if the order needs an insert or an update
        if (!($ipnOrder = Doctrine::getTable('IpnOrder')->findOneByTxn_id($this->ipnData['txn_id']))) // Update
        {
            $ipnOrder = new IpnOrder;
        }

        // Let's save/merge the order down
        $ipnOrder->merge($this->order);
        $ipnOrder->save();

        // Now let's save the order's line items
        foreach ($this->orderItems as $item)
        {
            // We need to check if we have already saved this into the database...
            if (!($ipnOrderItem = Doctrine::getTable('IpnOrderItem')->findOneByItem_nameAndOrder_id($item['item_name'], $ipnOrder->id)))
            {
                $ipnOrderItem = new IpnOrderItem;
            }

            // Let's store all of the fields
            $ipnOrderItem->merge($item);
            $ipnOrderItem->order_id = $ipnOrder->id;
            $ipnOrderItem->save();
        }
    }

    // The transaction logger. Currently tracks:
    // - Successful and failed calls by the PayPal IPN
    // - Successful and failed calls by one or more third-party APIs
    // - (In sandbox mode) Caches the last transaction fields so we can run the IPN script directly
    // Doctrine version
    function _logTransaction($listenerName, $transactionStatus, $transactionMessage, $ipnResponse = null)
    {
        // Store the standard log information
        $ipnLog = new IpnLog;
        if (!is_null($this->logID))
        {
            $ipnLog->assignIdentifier($this->logID);
        }
        $ipnLog->listener_name = $listenerName;
        $ipnLog->transaction_type = $this->transactionType;
        $ipnLog->transaction_id = $this->transactionID;
        $ipnLog->status = $transactionStatus;
        $ipnLog->message = $transactionMessage;
        $ipnLog->ipn_data_hash = md5(serialize($this->ipnData));

        // We also log the ipnResponse and the ipnData if we have an error and/or are in the sandbox (test) environment.
        if ($transactionStatus == 'ERROR' || !$this->isLive)
        {
            $detailJSON = array();
            $detailJSON['ipn_data'] = $this->ipnData;
            $detailJSON['ipn_response'] = $ipnResponse;
            $ipnLog->detail = json_encode($detailJSON);
        } else {
            $ipnLog->detail = null; // Turn it back to null (as it might have been set previously)
        }

        // Finally save down the log record.
        $ipnLog->save();
        if (is_null($this->logID))
        {
            $this->logID = $ipnLog->id; // Save the log ID for next time if we need to.
        }
    }
}