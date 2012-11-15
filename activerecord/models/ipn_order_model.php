<?php

/**
 * Ipn_order_model
 *
 * @package    codeigniter-paypal-ipn
 * @author     Alexander Dean, alex@keplarllp.com
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 *
 * This file is copyright (c) 2011 Alexander Dean, github@keplarllp.com
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

class Ipn_order_model extends CI_Model
{
    // Define the order and order items table names
    const ORDER_TABLE = 'ipn_orders';
    const ORDER_ITEM_TABLE = 'ipn_order_items';

    // Standard constructor
    function __construct()
    {
        parent::__construct();
    }

    // Save the order, performing an insert or update as appropriate
    function saveOrder($order, $orderItems, $transactionID)
    {
        // Define the transaction query once
        $orderQuery = array('txn_id' => $transactionID);

        // Check the transaction ID to see if the order needs an insert or update
        $existingOrder = $this->db->get_where(self::ORDER_TABLE, $orderQuery, 1, 0);

        // Set the update/insert date
        $upsertTime = date('Y-m-d H:i:s');
        $order['updated_at'] = $upsertTime; // All inserts/updates set the updated_at time

        // If it exists, get the ID and do an update
        if ($existingOrder->num_rows() > 0)
        {
            $orderID = $existingOrder->row()->id; // Grab the ID
            $this->db->update(self::ORDER_TABLE, $order, $orderQuery); // Do the update
        }
        // Else do an insert and then get the ID
        else
        {
            $order['created_at'] = $upsertTime; // A new order needs a created_at time too
            $this->db->insert(self::ORDER_TABLE, $order);
            $orderID = $this->db->insert_id();
        }

        // Now let's save the order's line items
        foreach ($orderItems as $item)
        {
            // Define the order item query
            $orderItemQuery = array(
                'item_name' => $item['item_name'],
                'item_number' => $item['item_number'],
                'order_id'  => $orderID
            );

            // Add the order ID and datestamp into the item to update/insert
            $item['order_id'] = $orderID;
            $item['updated_at'] = $upsertTime;

            // Now try to retrieve the order item
            $existingOrderItem = $this->db->get_where(self::ORDER_ITEM_TABLE, $orderItemQuery, 1, 0);

            // If the order item exists, update
            if ($existingOrderItem->num_rows() > 0)
            {
                $this->db->update(self::ORDER_ITEM_TABLE, $item, $orderItemQuery);
            }
            // Else insert the order item
            else
            {
                $item['created_at'] = $upsertTime; // Insert needs a created_at time as well
                $this->db->insert(self::ORDER_ITEM_TABLE, $item);
            }
        }
    }
}