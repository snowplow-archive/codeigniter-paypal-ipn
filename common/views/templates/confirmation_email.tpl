<html>
<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" bgcolor='#e6e6e6' font-family='verdana'>
<table width="100%"cellpadding="10" cellspacing="0" class="backgroundTable" bgcolor='#e6e6e6' >
<tr>
<td valign="top" align="center">

<table width="801px" cellpadding="0" cellspacing="0" bgcolor='#ffffff'>
<tr height="122px"><td valign="top" align="center"><img src="http://CHANGEME.com/assets/images/801x122-email-header.png" alt="Logo" title="Logo" /></td></tr>
<tr height="110px"><td align="left" valign="top" style="padding-top:20px;padding-left:20px;padding-right:20px">
 <p style="font-size:20px; font-family: Trebuchet MS,Helvetica;">Thank you for your CHANGEME purchase</p>
 <p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">This is a confirmation email.  <br>Your order has been received and will be dispatched within CHANGEME business day.</p>
</td></tr>
</table>

<table width="801px" cellpadding="0" cellspacing="0" bgcolor='#ffffff'>

<tr><td align="right" valign="top" style="padding-top:5px;padding-left:10px;padding-right:10px;padding-bottom:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">Name</p></td><td align="left" valign="top" style="padding-top:5px;padding-left:10px;padding-right:10px;padding-bottom:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$first_name} {$last_name}</p></td></tr>
<tr><td align="right" valign="top" style="padding-top:5px;padding-left:10px;padding-right:10px;padding-bottom:20px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">Delivery address</p></td><td align="left" valign="top" style="padding-top:5px;padding-left:10px;padding-right:10px;padding-bottom:20px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$address_name}<br>{$address_street}<br>{$address_city}<br>{$address_state}<br>{$address_zip}<br>{$address_country}</p></td></tr>
</table>

<table width="801px" cellpadding="0" bgcolor='#ffffff'>
<tr><td style="padding-left:20px;padding-right:5px;padding-top:10px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;font-weight:bold;">Item</p></td><td style="padding-left:5px;padding-right:5px;padding-top:10px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;font-weight:bold;">Quantity</p></td><td style="padding-left:5px;padding-right:5px;padding-top:10px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;font-weight:bold;">Cost per item</p></td><td style="padding-left:5px;padding-right:5px;padding-top:10px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;font-weight:bold;">Total cost</p></td></tr>
{foreach from=$items item=i}
<tr><td style="padding-left:20px;padding-right:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$i.item_name}</td><td align="right" style="padding-left:5px;padding-right:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$i.quantity}</p></td><td align="right" style="padding-left:5px;padding-right:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$i.cost_per_item|string_format:"%.2f"}</p></td><td align="right" style="padding-left:5px;padding-right:20px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$i.mc_gross|string_format:"%.2f"}</p></td></tr>
{/foreach}
{* In the line below, change mc_shipping to shipping for web_accept transaction type instead of cart *}
<tr><td style="padding-left:20px;padding-right:5px;padding-top:5px;" colspan="3"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">Shipping</p></td><td align="right" style="padding-left:20px;padding-right:20px;padding-top:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">{$mc_shipping|string_format:"%.2f"}</p></td></tr>
<tr><td style="padding-left:20px;padding-right:5px;padding-top:5px;" colspan="3"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">Discount</p></td><td align="right" style="padding-left:20px;padding-right:20px;padding-top:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">-{$discount|string_format:"%.2f"}</p></td></tr>
<tr><td style="padding-left:20px;padding-right:5px;padding-top:0px;" colspan="3"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">TOTAL</p></td><td align="right" style="padding-left:20px;padding-right:20px;padding-top:5px;"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;font-weight:bold;">{$mc_gross|string_format:"%.2f"}</p></td></tr>
<tr><td style="padding-left:20px;padding-right:20px;padding-top:15px;padding-bottom:20px;" colspan="4"><p style="font-size:12px; font-family: Lucida Sans Unicode,Lucida Grande;">If you have any questions regarding your order, don't hesitate to contact us on CHANGEME@CHANGEME.com</p>
</table>

</td>
</tr>
</table>
</body>
</html>
