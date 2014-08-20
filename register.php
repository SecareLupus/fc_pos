<?php
// register.php
// This app displays a textbox for skus and specific quantity boxes for specific items.
// The textbox passes back a string of skus. The item qty boxes pass vars called qty[$ID] with a quantity number
// There is also a pulldown for member name and fields for entering guest members. The pulldown will automatically generate
// discounts

// this app does not extract POST

// totalPrice in transactions table is the total price of items purchased. Does not include GiftCerts or preorders

// The second round, it shows a list of items with quantity that can be selected (default 1)

// OUTLINE
// send bug report
// ADM set date
// use set invEventID
// Set invEventID as one in progress or new one
// POST Data
//   look for quick add of new member
//   set qty for singles
//   apply discount
//   check member info
//   convert textarea data
//   process duplicate IDs
//   process skus if any
//   special items with qty box
//   process items which may have had qty change
//      apply bulk discounts
// existing items which were not in POST
// display total
// display member account/credit balance
// complete transaction if applicable
// display form
// POST data
// member - duh
// Arrays indexed by ID: sku, ID, quantity

// VERSIONS
// 1.2 Adds ability to select member-customers and process discounts
// 1.3 Adds variable price items and magic singles. Also includes Magic bulk discount
// 1.31 Adds bug reporting
// 1.32 Adds ability to set date if you have ADM
// 1.33 Adds drafts and draft ncs and redoes singles
// 1.4 New system for credit purchases


   include ('funcs.inc');
   include ('member.inc');
   include ('inventory.inc');
   include ('credits.inc');
   include ('giftcert.inc');
   include ('friendcomputer.inc');
   $title = 'In Store Sales';
   include ('header.php');
   $noRefresh = noRefreshCheck(); // to bypass this, uncomment next line
   $noRefresh = TRUE;
   $specOrder = new specialOrder;
   $newMember = new newMember;
   $giftCert = new giftCert;
   $creditSpending = new creditSpending;

   $taxdate = date("ynj");
   if($taxdate == '14816' || $taxdate == '14817') $salesTaxHoliday = TRUE;
   
   if($salesTaxHoliday) echo "<table border cellpadding=3><tr><td><font size=+3>ZOMG! It's a sales tax holiday!!!</font></td></tr></table>\n";

   // Check for current cash count
   if(!cashCountInFourHours())
   {
      echo "<h2><font color=RED>WARNING:<br>No cash count in last four hours.<br>You should do a cash count soon!</font></h2>";
   }
   $lastcount = cashCountTime();
   $lastDate = date_create($lastcount);
   echo "<br>Last Cash Count: " . $lastDate->format("l, F jS H:i") . "<p>\n"; 

   if(!$noRefresh && $_POST['close'] == 1)
   {
      echo "<table><tr><td bgcolor=RED><font color=WHITE><b>Notice:<br>Page refresh attempted</b></font></td></tr></table>";
      echo "<a href='register.php'>Click here for new transaction</a>";
      include('footer.php');
      die();
   }

   echo"<hr>";
   // if there is a bug report, we send it
   if(strlen($_POST['bug']) > 2)
   {
      reportBug('register.php');
   }

   if($_SESSION['reg'] != 1)
   {
      echo "You must have Register Use permissions to run the register.<p>";
      include('footer.php');
      die();
   }

   // allow the user to enter a date in get and then allow it to stick around as we update
   if(isset($_POST['date']))
   {
      $_GET['date'] = $_POST['date'];
   }
   
   if(isset($_GET['date']))
   {
      if(!checkDateNum($_GET['date']))
      {
         echo "<table border><tr><td><b>Message from Friend Computer<p>
               You have attempted to enter an invalid date of " . $_GET['date'] . ". It has been eliminated.</td></tr></table><p>";
         unset($_GET['date']);
      }
      else
      {
         $specDate = $_GET['date'];
         echo "<table border><tr><td><b>Message from Friend Computer<p>
               You have designated the date for this transaction to be " . $_GET['date'] . "</td></tr></table><p>";
      }
   }


   $cxn = open_stream();

   // if an invEvent_ID was provided, use it
   if(isset($_GET['ID']) || isset($_POST['IEID']))
   {
      $ID = (isset($_GET['ID'])) ? $_GET['ID'] : $_POST['IEID'];
      $sql = "SELECT staffID, closed, customerID FROM transactions WHERE ID='$ID'";
      $result = query($cxn, $sql);
      if($cxn->rows_affected > 0)
      {
         $row = mysqli_fetch_assoc($result);
         if(($row['closed'] == 0) && ($row['staffID'] == $_SESSION['ID']))
         {   
            $_SESSION['transaction_ID'] = $ID;
                        $_POST['member'] = $row['customerID'];
         }
         else
         {
            if($row['closed'] != 0) echo "Transaction #$ID already closed<br>";
            if($row['staffID'] != $_SESSION['ID']) echo "Only original user may resume transaction<br>";
         }
      }
      else
      {
         $sql = "INSERT INTO transactions
                             (staffID, closed, ID)
        	             VALUES
        	             (" . $_SESSION['ID'] . ", 0, " . $_GET['ID'] . ")";
         query($cxn, $sql);
         $_SESSION['transaction_ID'] = $_GET['ID'];
      }
   } // end if
   
   // check to see if there is already an active Inventory Event in progress in this session. If not, assign one
   if(!($_SESSION['transaction_ID'] > 0))
   {
      // check to see if user has an open transaction and uses that if there is one
      $sql = "SELECT ID
         	FROM transactions
               WHERE staffID='" . $_SESSION['ID'] . "'
                 AND closed='0'";
      $row = queryAssoc($cxn, $sql); 
      if($row['ID'] > 0)
      {
         $_SESSION['transaction_ID'] = $row['ID'];
      }
      else // otherwise make a new one
      {
         $sql = "INSERT INTO transactions
        			  (staffID, closed)
        			  VALUES
        			  (" . $_SESSION['ID'] . ", 0)";
         if(query($cxn, $sql))
         {  
            $_SESSION['transaction_ID'] = $cxn->insert_id;

/*         $sql = "SELECT ID
		   FROM transactions
		  WHERE ID=(SELECT MAX(ID) FROM transactions)
		    AND staffID=" . $_SESSION['ID'];
	 if($result = query($cxn, $sql))
	 {
	    $row = mysqli_fetch_assoc($result);
	    $_SESSION['transaction_ID'] = $row['ID'];
	 } */
	 }
      }
   }

   $sql = "SELECT staffID, closed FROM transactions WHERE ID='" . $_SESSION['transaction_ID'] . "'";
   if(!($result = query($cxn, $sql)))
      displayErrorDie("Unable to check transaction validity");
   $row = mysqli_fetch_assoc($result);
   if(($row['closed'] == 1) OR 
      (($row['staffID'] != $_SESSION['ID']) AND ($row['staffID'] > 0)))
   {
      print_r($row);
      extract($row);
      $post = var_export($_POST, TRUE);
      $session = var_export($_SESSION, TRUE);
      $body = "Register did that weird thing!
               Date: " . date('l dS \of F Y h:i:s A') . "
               POST data: $post

               SESSION data: $session
               
               Closed: $closed
               
               StaffID: $staffID\n\n";

      if(mail('webmaster@pvgaming.org', "FC/It's that weird thing!", $body))
      {
         echo "Message sent to Michael<p>";
      }
      else
      {
         displayError("Error: Unable to send report to Michael. Pretty ironic, isn't it?");
      }

      displayErrorDie("Friend Computer has encountered a serious error!<br>
      This transaction (#" . $_SESSION['transaction_ID'] . ") is closed or the staffID is incorrect!<br>
      Michael has been notified!<p>
      </font><font color=BLUE>You should log out and log back in and try again. Hopefully that will fix the problem.</font>");
   }

   // $TID is just an alias
   $TID = $_SESSION['transaction_ID'];
   echo "Transaction #" . $TID . "<p>\n";
   echo "<form action='register.php' method='post'>\n";

   // check for POST data
   if((isset($_POST['submit'])) || ($_POST['close'] == 1))
   {
      // Check for Gift Certificate Conversion
      if($_POST['giftcertnum'] > 0)
      {
         if(giftCertToAccount($_POST['giftcertnum'], $_POST['member']))
            echo "Gift certificate transferred to account.<p>";
         else
            echo "Error transferring gift certificate.<p>";
      }
      
      // Look for QUICK ADD of NEW MEMBER
      // give 5% discount if an email address was given
      if($newMember->process()) $_POST['discount'] += 5;
   
      // Magic Singles
      if($_POST['price'][778] > 0)
      {
         $_POST['qty'][778] = 1;
      }

      $discount = $_POST['discount'];
      if($discount > 90) $discount = 0; // check to make sure discount is proper, allowing negative discounts

      // check member information
      $member = $_POST['member'];
      $memDiscount = memberDiscount($member); // defined in inventory.inc
      // on July 1, we will need to update the function and set it to include the FC discount
      $discount += $memDiscount;
      $taxexempt = taxExempt($member);
      
      // spend credits for ten percent discount
      $disChange = $creditSpending->discount();
      if($disChange > 0)
         echo "<b>50 Hedon 10% Discount Selected</b><p>";
      $discount += $disChange;
      if($discount > 90) $discount = 90;


      // take the list from the textarea and turn it into a useful array
      if(strlen($_POST['sku']) > 0)
      {
         $skuList = explode("\n", trim($_POST['sku']));
         if(end($skuList) == '') array_pop($skuList); // remove a trailing empty sku
      }

      // add all the duplicate IDs to the sku list so they will be displayed
      // meaning skus for which there are multiple items
      if(is_array($_POST['splitID']))
      {
         foreach($_POST['splitID'] as $thisID)
         {
            array_push($skuList, $thisID);
         }
      }

      // if there are skus to be processed, we process them and display the data
      // this means if they are new we ask for all the data
      // they are already existing items, we ask for price, quantity and cost
      if(is_array($skuList))
      {
         $first = TRUE; // if this is true and affected is >0, then it will display the header

         $newItems = array();
         foreach($skuList as $s)
         {
            $s = trim($s);
            $s = $cxn->real_escape_string($s);
            $sql = "SELECT * FROM items WHERE UPC='$s' OR alternate1='$s' OR alternate2='$s' OR ID='$s' OR description LIKE '%$s%' ORDER BY description";
            $result = query($cxn, $sql);
            $affected = mysqli_affected_rows($cxn);
            
            // if the item does not already exist, we put it in a list to report as invalid
            if($affected == 0)
            {
               $invalidID .= (isset($invalidID)) ? ', ' . $s: $s;
            }

            else if($affected == 1) // if there is exactly one match
            {
               // check to see if that item is already on the order
               // if it is, add one to qty
               $row = mysqli_fetch_assoc($result);
               if(is_array($_POST['qty']) && array_key_exists($row['ID'], $_POST['qty']))
               {
                  $thisID = $row['ID'];
                  $_POST['qty'][$thisID]++; // if they enter it again, they might want it again

                  continue;
               }
               else
               {
                  $thisID = $row['ID'];
                  $_POST['qty'][$thisID] = 1;
               }
               
               extract($row);
               $price = rightPrice($price, $salePrice);
               $onSale = SalePrice($salePrice);
               echo "<p>";
            }

            // multiple items can use the same sku because sometimes companies screw up like that, so we can deal with it
            // this displays each of the items with that sku and lets the user pick which one and enter the info at the same time
            else if(($some = mysqli_affected_rows($cxn)) > 1)
            {
               echo "<b>You have $some items with the same lookup of '$s'</b><br>
                     Check the ones you would like to use and enter quantity.<p>";
               while($row = mysqli_fetch_assoc($result))
               {
                  extract($row);
                  $onSale = salePrice($salePrice);
                  echo "Quantity <input type='text' name='qty[$ID]' size=4 maxlength=4>
                        <b>$ID</b>: $description" . (($onSale) ? "<br><b>Sale Price: " . money($salePrice) . "</b><br>Regular " : "<br>") . "
                        Price \$";
                  printf("%01.2f", $price);
                  echo "<p>";
               } // end while
            } // end else if
         } // end foreach
      } // end if is array skulist
      
      // Here, we look at the special items that have their own qty box
      $specqty = $_POST['specqty'];
      if(is_array($specqty))
      {
         foreach($specqty as $ID => $sq)
         {
            if($sq > 0) $_POST['qty'][$ID] += $sq;
         }
      }

      // This section looks at items which have had the chance to have qty change
      $first = TRUE;
      $qty = $_POST['qty'];
      if(is_array($qty))
      {
         // for checking to see if it already exists
         $checkID = $cxn->prepare("SELECT ID FROM soldItem WHERE transactionID=? AND itemID=?");
         $checkID->bind_param('ii', $TID, $thisID);

         // for setting up new soldItem rows
         $setSI = $cxn->prepare("INSERT INTO soldItem
                                 (transactionID, itemID, onSale, price, qty, cost, tax)
                                 VALUES
                                 ('$TID', ?, ?, ?, ?, ?, ?)");
         $setSI->bind_param('iididd', $thisID, $onSale, $p, $q, $c, $tax);
            
         // for updating in case it already exists
         $updateSI = $cxn->prepare("UPDATE soldItem
                                       SET price=?,
                                           qty=?,
                                           cost=?,
                                           tax=?
                                     WHERE itemID=?
                                       AND transactionID=?");
         $updateSI->bind_param('diddii', $p, $q, $c, $tax, $thisID, $TID);

         // for getting the info for displaying
         $displayItem = $cxn->prepare("SELECT description FROM items WHERE ID=?");
         $displayItem->bind_param('i', $thisID);
         $displayItem->bind_result($description);
            
         // to acquire price and cost
         $getPC = $cxn->prepare("SELECT price, salePrice, cost, margin, tax, department FROM items WHERE ID=?");
         $getPC->bind_param('i', $thisID);
         $getPC->bind_result($p, $sp, $c, $margin, $tax, $dept);
         
         foreach($qty as $thisID => $q)
         {
            // if the quantity is set to 0, then the item is taken off the list
            if($q <> 0)
            {
               queryB($checkID); // SELECT ID FROM soldItem WHERE transactionID=? AND itemID=?
               $checkID->fetch();
               queryB($getPC); // SELECT price, cost, margin, tax, department FROM items WHERE ID=?
               $getPC->fetch();
               $p = rightPrice($p, $sp);
               $onSale = (salePrice($sp)) ? 1 : 0;
               
               if($salesTaxHoliday || $taxexempt)
               {
                  $tax = 0;
               }



               // apply margin cost
               if($margin > 0)
               {
                  $cost = $p * ((100 - $margin) / 100);
               }

               // check for Magic discount
               $p = round($p, 2);
               if(eregi("magicboost", $dept))
               {
                  if($q == 1) $p = 3.99;
                  else if ($q >= 2 && $q <= 4) $p = 3.89;
                  else if ($q >= 5 && $q <= 9) $p = 3.79;
                  else if ($q >= 10 && $q < 36) $p = 3.49;
                  else if ($q >= 36)
                  {
                     $p = 3.05555555555555555;
                     $magicbox = TRUE;
                     
                     $noMagicCap = $_POST['noMagicCap'];
                     if($noMagicCap != 1)
                     {
                        $discount = ($discount >= 1) ? 0 : $discount;
                        echo "<b>Magic Box being purchased, so discount is currently limited to $discount.</b><p>\n";
                        if($_SESSION['adm'] == 1)
                        {
                           echo "<input type='checkbox' name='noMagicCap' value='1'> Override?<p>\n";
                        }
                     }
                     else
                     {
                        echo "<input type='checkbox' name='noMagicCap' value='1' checked> Magic Discount Override Engaged<p>\n";
                     }
                  }
               }

               if(eregi("Heroclix", $dept) && $p == 11.99)
               {
                  if($q == 1) $p = 11.99;
                  else if ($q >= 10) 
                  {   
                     $p = 10;
                     $noMagicCap = $_POST['noMagicCap'];
                     if($noMagicCap != 1)
                     {
                        $discount = ($discount >= 10) ? 10 : $discount;
                        echo "<b>Brick being purchased, so discount is 10% or less</b><p>\n";
                        if($_SESSION['adm'] == 1)
                        {
                           echo "<input type='checkbox' name='noMagicCap' value='1'> Override?<p>\n";
                        }
                     }
                     else
                     {
                        echo "<input type='checkbox' name='noMagicCap' value='1' checked> Magic/Heroclix Discount Override Engaged<p>\n";
                     }
                  }
               }

               // no discount with donations
               $donationIDs = array(1220, 2220, 1327);
               if(in_array($thisID, $donationIDs))
               {
                  $discount = 0;
                  echo "Discount removed due to Donation<p>";
               }

               // no discount for Events
               $eventIDs= array(2262, 1957, 780, 1123, 1938, 1160,  1356, 2030, 1957, 1958, 2435, 2436, 2437);
               if(in_array($thisID, $eventIDs))
               {
                  $discount = 0;
                  echo "Discount removed due to type of event<p>";
               }
               // no discount for memberships
               $membershipIDs= array(1443, 1446, 1445, 1444, 1442);
               if(in_array($thisID, $membershipIDs))
               {
                  $discount = 0;
                  echo "Discount removed due to sale  being purchase of membership<p>";
               }               
               // no discount for consignment singles
               
               $singlesIDs= range(2329, 2336);
               if(in_array($thisID, $singlesIDs))
               {
                  $discount = 0;
                  echo "Discount removed due to consignment singles <p>";
               }
            
               // check for used novel book discount
               if($thisID == 1213)
               {
                  $q += $qty[1212]; // r2
                  if($q >= 1 && $q <= 2) $p = 2;
                  else if($q >= 3 && $q <= 9) $p = 1 && $c = .4;
                  else if($q >= 10) ($p = .80) && ($c = .32);
                  $q -= $qty[1212]; // r2
               }
               if($thisID == 1212)
               {
                  $q += $qty[1213]; // b2
                  if($q >= 1 && $q <= 2) $p = 2;
                  else if($q >= 3 && $q <= 9) $p = 1;
                  else if($q >= 10) $p = .80;
                  $q -= $qty[1213]; // b2
               }
               
               // checck for hard cover discount
               if($thisID == 1215)
               {
                  if($q >= 1 && $q <= 3) $p = 3;
                  else if($q >= 4 && $q <= 9) $p = 1.5;
                  else if($q >= 10) $p = 1.2;
               }
                  

               // setting p for VAR items before we save the itemSold
               if(ereg("^VAR", $dept))
               {
                  $p = (isset($_POST['price'][$thisID])) ? $_POST['price'][$thisID] : $p;
               }

               if($checkID->affected_rows == 0)
               {
                  queryB($setSI);// INSERT INTO soldItem
                                 // (transactionID, itemID, price, qty, cost, tax)
                                 // VALUES
                                 // ('$TID', $thisID, $p, $q, $c, $tax)
               }
               else
               {
                  queryB($updateSI);
               }

               // then we display it
               queryB($displayItem);
               $displayItem->fetch();

               if(ereg("^VAR", $dept))
               {
                  displayRegisterItemPrice($thisID, $qty[$thisID], $p, $description);
               }
               else
               {
                  displayRegisterItem($thisID, $qty[$thisID], $p, $description);
               }

               // set post[qty] so that it is not displayed again
               $_POST['qty'][$thisID] = $qty[$thisID];
            } // end if q<>0

            // if q is 0 we remove it
            else
            {
               $removeItem = $cxn->prepare("DELETE FROM soldItem WHERE itemID='$thisID' AND transactionID='$TID'");
               $removeItem->execute();
            }
            
         }
         $checkID->close();
         $setSI->close();
         $updateSI->close();
         $displayItem->close();
         $getPC->close();
      }  // if is array qty
   }
   
   // Now we look at all items being sold which are not represented in post.
   // anything in the post will have a qty[ID], so we don't display those
   $stmt = $cxn->prepare("SELECT i.description description,
                                 s.itemID,
                                 s.price,
                                 s.qty
                            FROM soldItem s
                            JOIN items i
                              ON s.itemID=i.ID
                           WHERE transactionID='$TID'
                        ORDER BY description");
   $stmt->bind_result($description, $thisID, $p, $qty);
   queryB($stmt);
   while($stmt->fetch())
   {
      if((($_POST['submit'] != 'submit')
       && ($_POST['close'] != 1))
     || (is_array($POST['qty']) && !array_key_exists($thisID, $_POST['qty'])))
      {
         $p = round($p, 2);
         displayRegisterItem($thisID, $qty, $p, $description);
      }
   }
   $stmt->close();

      
   // Finish displaying items and display total

   if(!isset($invalidID)) // if it's all good, determine total and display it
   {
      // we go through the itemSold list one by one so we can tally them up
      $price = round($price, 2);
      $cost = round($cost, 2);
      $reviewSold = $cxn->prepare("SELECT itemID, price, qty, cost, tax FROM soldItem WHERE transactionID='$TID'");
      $reviewSold->bind_result($itemID, $price, $qty, $cost, $tax);
      queryB($reviewSold);


      // initializing variables
      $totalCost = 0;
      $totalPrice = $specOrder->total + $giftCert->newCert; // this is added without applying discount and such
      $totalNonPurchases = $specOrder->total + $giftCert->newCert; // this will be subtracted before putting it into database
      $totalTax = 0;

      // make the discount a multiplier
      $discMult = ((100 - $discount) / 100);

      $itemIDs = array();
      $qtys = array();

      while($reviewSold->fetch()) // fetch is an stmt function
      {
         // running tally
         $price = rightPrice($price, $salePrice);
         $totalPrice += round(($price * $qty * $discMult), 2);
         $totalTax += ($tax == 1) ? round($price * $qty * $discMult * $TAXRATE , 2) : 0;

         // if we are closing, we'll need a list of itemIDs so we can run through them to update quantities below
         if($_POST['close'] == 1)
         {
            // apply margin cost
            if($margin > 0)
            {
               $cost = $p * ((100 - $margin) / 100);
            }
            
            array_push($itemIDs, $itemID);
            array_push($qtys, $qty);
            $totalCost += $cost * $qty;
         }
      } // end while

      $reviewSold->close();

      // display member account/credit balance
      if($_POST['member'] > 0)
      {
         $accountLeft = getAccountBalance($_POST['member']);
         $avail = getAvailBalance($_POST['member']) + $specOrder->deliverSum();

         echo "<table border><tr><td colspan=2><b>Member Info for " . printMemberString($_POST['member'], 1) . "</b></td></tr>";
         if($taxexempt) echo "<tr><td><b>Member is TAX EXEMPT</b></td></tr>\n";
         echo "<tr><td>Account Balance: \$";
         printf("%01.2f", $accountLeft);
         echo "<br>Available Balance: ". money($avail);

         if($_POST['pay'] == 4)
         {
            echo "<br>Remaining Available Account after sale: \$";
            $after = $avail - ($totalPrice + $totalTax);
            if ($after < 0) echo "<font color='RED'>";
            else echo "<font color='BLUE'>";
            printf("%01.2f", $after);
            echo "</font>";
         }

         echo "<br>Hedon Balance: " . getCreditTotal($_POST['member']);

	 // Frequent Gamer Rewards
         echo "</td><td>\n";
         FG_showInfo($_POST['member'], $totalPrice - $totalNonPurchases);

         // Show currently ordered credit spending next to member info box
         echo "</td>";
         if($creditSpending->creditsSpent)
         {
            if(!$_POST['close'] == 1) 
            {
               echo "<td>";
               $creditSpending->displayCurrent();
            }
            else
            {
               // DEDUCT CREDITS for credit changes
               // we do this first because it if it fails we don't want to do anything else.
               // we do it here so it appears next to the member info box
               if($creditSpending->creditsSpent && $_POST['pay'] > 0)
               {
                  echo "<td bgcolor=BLACK>";
                  $creditCost = $creditSpending->conclude();
                  if(strcmp($creditCost,"insuf") == 0)
                  {
                     $fail = TRUE;
                     echo "<font color=white>Error: Failure to deduct Hedons for Hedon changes.</font><br>";
                  }
                  else // if CreditCost is valid
                  {
                     $totalCost += $creditCost;
                  }
               }
            }
         }

         echo "</td></tr></table>";
   
      }
   
      // display gift certificate info if applcable
      if($giftCert->certNum > 0)
      {
         $giftCert->displayInfo();
      }
   
      // if the complete transaction button was pressed then we update everything
      // if there were any invalid skus, we don't close yet
      // will not go through if the page was refreshed
      if($_POST['close'] == 1 && $noRefresh)
      {
         // payment must be specified unless purchase is Hedons only
         if($_POST['pay'] == 0)
         {
               echo "<style>
p.nopay {position: absolute; 
         background-color: FF0000;
         top: 50;
         left: 250;
         width: 600px;
         height: 200px;
         font-color: FFFFFF;
         text-align: center;
         vertical-align: center;
         font-size: 40px;
         font-weight: bold;}
</style>
               <p class=nopay><br><br>You must select a payment type</p>";
               $fail = TRUE;
         }         
         
         // if there is a discount we need to apply it to the item soldItems
         if($discount > 0)
         {
            $sql = "UPDATE soldItem SET price=(price * $discMult) WHERE transactionID='$TID'";
            query($cxn, $sql);
         }
      
         // PAYMENTS
         // set all the payment variables
         $pay = round((($specOrder->pickUp == TRUE) ? 4 : $_POST['pay']), 2);
         $cashpay = round(((($_POST['cashpay'] <> 0) || ($_POST['cashpay'] < 0)) ? $_POST['cashpay'] : 0),2);
         $ccpay = round((($_POST['ccpay'] > 0) ? $_POST['ccpay'] : 0),2);
         $checkpay = round(((($_POST['checkpay'] > 0) || ($_POST['checkpay'] < 0)) ? $_POST['checkpay'] : 0),2);
         $accountpay = round((($_POST['accountpay'] <> 0) ? $_POST['accountpay'] : 0),2);
         $giftpay = round((greaterThanZero($_POST['giftpay'])),2);
         $pay = ($pay >= 0 && $pay <=6) ? $pay : 1;
         $payment = $totalPrice + $totalTax;
         
         // check to make sure that split payment is correct before doing anything else
         if(!$fail && $pay == 6)
         {
            $paysum = round(($cashpay + $ccpay + $checkpay + $accountpay + $giftpay), 2);
            $payment = round($payment, 2);
            if($paysum != $payment)
            {
               $fail = TRUE;
               Echo "<font color=RED>Payments must add up to total.</font><br>
                     Transaction Total: " . money($payment) . "<br>
                     Payment Total: " . money($paysum) . "<p>";
            }
         }

         // deal with payment on gift certificate
         if(!$fail && ($pay == 5 || ($pay == 6 && $giftpay > 0)))
         {
            $total = ($giftpay > 0) ? $giftpay : $payment;
            if(!$giftCert->redeem($total))
            {
               $fail = TRUE;
               echo "Error: Failure to redeem gift certificate<br>";
            }
         }

         if(!$fail)
         {
            $fail = !($specOrder->deliver($totalPrice));
            if($fail) echo "Error: Failure to deliver Special Order<br>";
         }

         if(!$fail && ($giftCert->newCert > 0))
         {
            echo "Check<p>";
            if($gcnum = $giftCert->sell())
            {
               echo "<b>Gift Certificate number $gcnum sold for " . money($giftCert->newCert) . "<p>\n";
            }
            else
            {
               $fail = TRUE;
               echo "<font color=RED>Error posting Gift Certificate</font>";
            }
         }

         $message = $newMember->message . $specOrder->message;
         if(strlen($message) > 0)
         {           
            echo "<table border cellpadding=5><tr><td>$message</td></tr></table><p>";
         }

         // deal with payment on account
         if(!$fail && ($pay == 4 || ($pay == 6 && $accountpay > 0)))
         {
            // check to make sure there is a member
            if($member > 0)
            {
               $total = round((($accountpay > 0) ? $accountpay : $payment), 2);

               if ($total <= ($avail + .01))
               {
                  $sql = "INSERT INTO storeAccount (memberID, transactionID, whenAcct, amount)
                                            VALUES ('$member', '$TID', NOW(), '-$total')";
                  if(!query($cxn, $sql)) 
                  {
                     $fail = TRUE;
                     echo "Failure to insert store account transaction<br>";
                  }
                  else 
                  {
                     $fail = FALSE;
                  }
               }
               else
               {
                  echo "<table border><tr><td><font color=RED>Insufficent money available in account.<br>
                        Total: $total, Avail: $avail</font></td></tr></table>\n";
                  $fail = TRUE;
               }
            }
            else
            {
               echo "<table border><tr><td><font color=RED>Member must be selected to pay with account</font></td></tr></table>\n";
               $fail = TRUE;
            }
         }

         if(!$fail && $specOrder->toProcess)
         {
            $fail = !($specOrder->process());
            if($fail) echo "Error: Failure to process special order<br>";
         }

         if(!$fail)
         {
            
            // This puts the payment into the right column. This allows for split payments when that functionality is created, which is now
            switch($pay)
            {
               case 1 : $paysql = "payMethod='$pay', cash='$payment'";
                        break;
               case 2 : $paysql = "payMethod='$pay', creditcard='$payment'";
                        break;
               case 3 : $paysql = "payMethod='$pay', checkpay='$payment'";
                        break;
               case 4 : $paysql = "payMethod='$pay', account='$payment'";
                        break;
               case 5 : $paysql = "payMethod='$pay', giftCert='$payment'";
                        break;
               case 6 : $paysql = "payMethod='$pay', cash='$cashpay', creditcard='$ccpay', checkpay='$checkpay', account='$accountpay', giftCert='$giftpay'";
                        break;
               case 0 : displayError("Payment type not selected. You should not see this. FC should have stopped before here.");
            }
            

            // this will be used each time through to update the item inventory
            // this was moved inside of this if statement so that we don't change quatity until we are sure that this worked.
            $updateItem = $cxn->prepare("UPDATE items
                                            SET qty=qty - ?
                                          WHERE ID=?");
            $updateItem->bind_param('ii', $qty, $itemID);

            foreach($itemIDs as $itemID)
            {
               queryB($updateItem);
            }

            $updateItem->close();

            // SPECIAL ITEMS THAT DO THINGS
            // L5R League - 1707
/*            if(in_array(1707, $itemIDs))
            {
               $sql = "INSERT INTO league (leagueID, whenplayed, submitter, player, points, game)
                                   VALUES ('$lleagueID', NOW(), '{$_SESSION['ID']}', $member, 0, 'NEW')";
               if(query($cxn, $sql))
               {
                  echo "<table border><tr><td>" . printMemberString($member,1) . " added to L5R League</td></tr></table><p>";
               }
            }*/

            // MTG League - 1160
            if((in_array(1160, $itemIDs)) || (in_array(1195, $itemIDs)))
            {
               $sql = "INSERT INTO league (leagueID, whenplayed, submitter, player, points, game)
                                   VALUES ('$mleagueID', NOW(), '{$_SESSION['ID']}', $member, 0, 'NEW')";
               if(query($cxn, $sql))
               {
                  echo "<table border><tr><td>" . printMemberString($member,1) . " added to Magic League</td></tr></table><p>";
               }
            }

            // Std MTG League - 1783
/*            if(in_array(1783, $itemIDs))
            {
               $sql = "INSERT INTO league (leagueID, whenplayed, submitter, player, points, game)
                                   VALUES ('$mleagueID', NOW(), '{$_SESSION['ID']}', $member, 0, 'NEW')";
               if(query($cxn, $sql))
               {
                  echo "<table border><tr><td>" . printMemberString($member,1) . " added to Standard Magic League</td></tr></table><p>";
               }
            }*/


            // CLOSE AND UPDATE TRANSACTION
            // This section reduces totalPrice so that it does not include special orders or gift cert purchases
            $totalPrice -= $totalNonPurchases;
            
            // this updates and closes the transaction
            $sql = (isset($specDate) && ($_SESSION['adm'] == 1)) ?
                   "UPDATE transactions
                       SET totalPrice='$totalPrice',
                           totalCost='$totalCost',
                           tax='$totalTax',
                           $paysql,
                           whensale='$specDate',
                           closed='1',
                           customerID='$member'
                     WHERE ID='$TID'" :
                   "UPDATE transactions
                       SET totalPrice='$totalPrice',
                           totalCost='$totalCost',
                           tax='$totalTax',
                           $paysql,
                           whensale=NOW(),
                           closed='1',
                           customerID='$member'
                     WHERE ID='$TID'";
            query($cxn, $sql);
      
            // display total
            $totalPrice += $totalNonPurchases;
            $total = $totalPrice + $totalTax;

            switch($pay)
            {
               case 1 : echo "<table bgcolor=006600 border><tr><td><center>
                              <font color=WHITE><b>Cash Payment</b><br>\n";
                        break;
               case 2 : echo "<table bgcolor=660099 border><tr><td><center>
                              <font color=WHITE><b>Credit Card Payment</b><br>\n";
                        break;
               case 3 : echo "<table bgcolor=BLUE border><tr><td><center>
                              <font color=WHITE><b>Check Payment</b><br>\n";
                        break;
               case 4 : echo "<table bgcolor=BLACK border><tr><td><center>
                              <font color=WHITE><b>Worlds Apart Account Payment</b><br>
                              Remaining Balance: \$";
                        printf("%01.2f", getAccountBalance($_POST['member']));
                        echo "<br>\n";
                        break;
               case 5 : echo "<table bgcolor=ORANGE border><tr><td><center>
                              <font color=BLACK><b>Gift Certificate</b><br>
                              Gift Certificate #" . $giftCert->certNum . "<br>
                              Remaining Balance: " . money(giftCertBalance($giftCert->certNum)) . "<br>\n";
                        break;
               case 6 : echo "<table bgcolor=666666 border><tr><td><center>
                              <font color=WHITE><b>Mixed Payment</b><br>";
                        if($_POST['accountpay'] > 0)
                        {
                           echo "Remaining Balance : \$";
                           printf("%01.2f", getAccountBalance($_POST['member']));
                           echo "<br>\n";
                        }
                        break;
            }


            echo "Price: \$";
            printf("%01.2f", $totalPrice);
            echo "<br>Tax: \$";
            printf("%01.2f", $totalTax);
            echo "<p><font size=+5>Your Total</font><hr>
                  <font size=+3>\$";
            printf("%01.2f", $total);
            echo "</font></font>";
            echo ($pay == 6) ? "</td>" : "</td></tr></table><p>";

            if($pay == 6) // mixed payment
            {
               echo "<td>
                     <b>Mixed Payment</b><p>
                     <table bgcolor=WHITE cellpadding=3>";

               if($cashpay <> 0)
               {
                  echo "<tr><td bgcolor=006600>
                        <font color=WHITE>Cash</td>
                        <td>" . money($cashpay) . "</td></tr>\n";
               }
               if($checkpay <> 0)
               {
                  echo "<tr><td bgcolor=BLUE>
                        <font color=WHITE>Check</td>
                        <td>" . money($checkpay) . "</td></tr>\n";
               }
               if($ccpay <> 0)
               {
                  echo "<tr><td bgcolor=660099>
                        <font color=WHITE>Credit Card</td>
                        <td>" . money($ccpay) . "</td></tr>\n";
               }
               if($accountpay <> 0)
               {
                  echo "<tr><td bgcolor=BLACK>
                        <font color=WHITE>Account</td>
                        <td>" . money($accountpay) . "</td></tr>\n";
               }
               if($giftpay <> 0)
               {
                  echo "<tr><td bgcolor=ORANGE>
                        <font color=BLACK>Gift Certificate</td>
                        <td>" . money($giftpay) . "</td></tr>\n";
               }
               echo "</table>\n";
            }

            // give Hedons for sales
            $hedonsComm = $totalPrice / 100;
            $hedonsComm = ($hedonsComm < .1) ? .1 : $hedonsComm;
            $hedonsComm = round($hedonsComm, 2);
            if(transferCredits(0, $_SESSION['ID'], $hedonsComm, "TransID $TID, total price: $totalPrice", 1))
               echo "$hedonsComm Hedons Given to YOU!<p>";
            else
               echo "Error giving you Hedons just now<p>";
            

            // give credit for referrals
            if($refString = giveReferralCredit($member, $totalPrice, $TID))
               echo "<table border cellpadding=5><tr><td>$refString</td></tr></table><br>";

            echo "<a href='register.php'>Click here for another transaction</a><p>
                  <a href='viewprintablereceipt.php?ID={$_SESSION['transaction_ID']}' target='receipt'>View Printable Receipt</a><br>";
            unset($_SESSION['transaction_ID']);
            
            include('footer.php');
            exit();
         } // end if
         else // if fail is true meaning something is wrong with the transaction
         {
            echo "<p><b>Transaction not concluded due to errors</b>";
         }
      }      

      if($noRefresh) // if the order is not concluded yet
      {
         echo "<table><tr><td><table>";

         if($discount != 0)
         {
            echo "<tr><td><FONT COLOR='000066'>Applied Discount</font></td><td>$discount %</td></tr>\n";
         }

         echo "<tr><td>Current total Price</td><td>\$";
         printf("%01.2f", $totalPrice);
         echo "</td></tr>
               <tr><td>Current total Tax</td><td>\$";
         printf("%01.2f", $totalTax);
         echo "</td></tr>
               <tr><td>Current total Purchase</td><td>\$";
         printf("%01.2f", $totalPrice + $totalTax);
         echo "</td></tr>";
         
         // display account balance
         $member=$_POST['member'];
         $accountBal = ($member != 0) ? getAccountBalance($member) : 0;
         if($accountBal != 0)
         {
            echo "<tr><td colspan=2>";
            printMember($member, 1);
            echo " has an account balance of \$";
            printf("%0.2f", $accountBal);

            /*if($avail <= ($totalPrice + $totalTax))
            {
               echo "<br><font color=RED>This member has insufficent account to cover this purchase<br>with account only!</font>";
            }*/
         }
         
         // show outstanding preorders, special orders, and requests
        // if(checkMember($_POST['member'])) $specOrder->showOrders();

         // closing table
         //echo "</td>";
         
         // info for split payments. Splits are tricky because they need to add up correctly.
         if($_POST['pay'] == 6)
         {
            echo "<td valign=top>";

            $payment = round($totalPrice,2) + round($totalTax,2);

            $cashpay = round($_POST['cashpay'], 2);
            $ccpay = round($_POST['ccpay'],2 );
            $checkpay = round($_POST['checkpay'], 2);
            $accountpay = round($_POST['accountpay'], 2);
            $giftpay = round($_POST['giftpay'], 2);
            $paysum = round(($cashpay + $ccpay + $checkpay + $accountpay + $giftpay), 2);
            
            // round everything because it's dumb
            $paysum = round($paysum, 2);
            $payment = round($payment, 2);
                        
            if($paysum != $payment)
            {
               echo "The sum of your split payment is incorrect.<br>";
               echo ($paysum < $payment) ? "The payment is short by <font color=RED>" . money($payment - $paysum) . "</font>"
                                         : "The payment is over by <font color=GREEN>" . money($paysum - $payment) . "</font>";
               echo "<hr>";
            }
            
            echo "<center><b>Mixed Payments</b></center>
                  <table border><tr><td width=100>
                  <center>
                  Cash:<br>
                  <input type='text' name='cashpay' value='" . $_POST['cashpay'] . "' size=7 maxlength=8></center></td>
                  <td width=100><center>
                  Credit Card:<br>
                  <input type='text' name='ccpay' value='" . $_POST['ccpay'] . "' size=7 maxlength=8></center></td>
                  </td>
                  <td rowspan=2>";
            $giftCert->displaySelectForm();
            echo "Amount to Redeem: <input type='text' name='giftpay' value='" . $_POST['giftpay'] . "' size=7  maxlength=8></center></td>
                  </tr>
                  <tr><td><center>
                  Check:<br>
                  <input type='text' name='checkpay' value='" . $_POST['checkpay'] . "' size=7 maxlength=8></center></td>
                  </td><td><center>
                  Account:<br>
                  <input type='text' name='accountpay' value='" . $_POST['accountpay'] . "' size=7 maxlength=8></center></td>
                  </tr></table></td></tr>";
         } // end if

         echo "</table><p>";
      } // end else
   } // end if !isset invalid
   
   // if there are any invalid skus, we show them now
   else if(isset($invalidID))
   {
      echo "The following lookups were not found in the system:<br>$invalidID<p>";
   }
   
   $discount -= $memDiscount; // this is so the member discount is not added multiple times
   $discount -= $creditSpending->discount(); // ditto for credit discount

   //echo "<table><tr><td width=200></td><td>\n";
   //$specOrder->displayOrderable();
   //echo "</td></tr></table></align>\n";

   include('salesbox.inc'); // this is in an inc file so that it can be changes without reuploading register

    noRefresh(); // this goes before very submit button where there could be a problem with refreshing
    echo"<button name='submit' value='submit'>Update Order</button>
         <button name='close' value='1'>Conclude Order</button><p>";

    $newMember->displayQuickForm();

    $specOrder->displayForm();
    
    echo"<hr>Convert gift certificate to account<br>
         <i>This will convert the entire contents of a gift certificate into account for the current member.</i><br>
         Gift certificate number: <input name='giftcertnum' size=10 maxlength=10><hr>";
         
    echo"<hr>If you have encounted an undesirable behaviour in this application, please describe it in the box below and press
         the submit button as usual. This will inform the High Programer and action will be taken as appropriate. This will
         not cause you to lose your data in any way, we hope.<br>
         <textarea name='bug' cols=40 rows=2></textarea>";

   if(isset($specDate))
      echo "<input type='hidden' name='date' value='$specDate'>";

   echo "</form><p>";

   $version = '1.4';
   include('footer.php');
?>