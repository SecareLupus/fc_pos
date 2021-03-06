<?php
/**
 * @file accounttransactions.php
 * @brief accounttransactions.php shows users their ledger of store credit transactions.
 * 
 * This file includes:
 * funcs.inc:
 * - checkDateNum()
 * - displayRefs()
 * - getAccountBalance()
 * - getAvailBalance()
 * - displayAccount()
 * 
 * Possible Arguments:
 * GET:
 * - ID - If an ID is specified, the page will display that member's
 *   store credit ledger, not the active user's.
 * - qty - If a qty is specified, only the most block of that many 
 *   transactions will be shown.
 * - start - If a date is specified, only transactions and referals after that
 *   date will be included in the list.
 * 
 * @link http://www.worldsapartgames.org/fc/accounttransactions.php @endlink
 * 
 * @author    Michael Whitehouse 
 * @author    Creidieki Crouch 
 * @author    Desmond Duval 
 * @copyright 2009-2014 Pioneer Valley Gaming Collective
 * @version   1.8b
 * @since     Project has existed since time immemorial.
 */

$title = "Account Transactions";
$version = "1.8d";
require_once 'funcs.inc';
require_once 'header.php';

if ($_GET['ID'] > 0) {
    $ID = $_GET['ID'];
} else {
    $ID = $_SESSION['ID'];
}

if ($_GET['qty'] > 0) {
    $qty = $_GET['qty'];
} else {
    $qty = 0; // no limit
}

// if refs are to be displayed
if (checkDateNum($_GET['start'])) {
    echo "<font size=+3>Referral Bonuses</font><br>\n";
    displayRefs($_GET['start'], $ID);
    echo"<hr>";
}   

$total = getAccountBalance($ID);
$avail = getAvailBalance($ID);
echo "<font size=+3>Store Account Transactions</font><br>
Total Account: " . money($total) . "<br>
Total Available: " . money($avail) . "<hr>";
displayAccount($qty, $ID);

require_once 'footer.php';
?>
