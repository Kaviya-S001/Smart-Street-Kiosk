<!DOCTYPE html>
<html lang="en">
<?php
include("connection/connect.php");
include_once 'product-action.php';
error_reporting(0);
session_start();
if(empty($_SESSION["user_id"]))
{
	header('location:login.php');
}
else{

	$item_total = 0;
	foreach ($_SESSION["cart_item"] as $item)
	{
		$item_total += ($item["price"]*$item["quantity"]);
		
		if(isset($_POST['submit']))
		{
			$SQL="insert into users_orders(u_id,title,quantity,price) values('".$_SESSION["user_id"]."','".$item["title"]."','".$item["quantity"]."','".$item["price"]."')";
			mysqli_query($db,$SQL);
			$success = "Thankyou! Your Order Placed successfully!";
		}
	}

// Handle coupon application
if(isset($_POST['apply_coupon']))
{
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    
    if(empty($coupon_code))
    {
        $coupon_error = "Please enter a coupon code!";
        unset($_SESSION['coupon_applied']);
    }
    else
    {
        $query = mysqli_query($db, "SELECT * FROM coupons WHERE code='$coupon_code' AND status='active'");
        
        if(mysqli_num_rows($query) == 0)
        {
            $coupon_error = "Invalid coupon code!";
            unset($_SESSION['coupon_applied']);
        }
        else
        {
            $coupon = mysqli_fetch_array($query);
            
            if(!empty($coupon['expiry_date']) && $coupon['expiry_date'] < date('Y-m-d'))
            {
                $coupon_error = "This coupon has expired!";
                unset($_SESSION['coupon_applied']);
            }
            elseif($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses'])
            {
                $coupon_error = "This coupon has reached its usage limit!";
                unset($_SESSION['coupon_applied']);
            }
            elseif($item_total < $coupon['min_order'])
            {
                $coupon_error = "Minimum order of ₹" . $coupon['min_order'] . " required for this coupon!";
                unset($_SESSION['coupon_applied']);
            }
            else
            {
                if($coupon['discount_type'] == 'percentage')
                {
                    $discount_amt = ($item_total * $coupon['discount_value']) / 100;
                    $discount_label = $coupon['discount_value'] . '% off';
                }
                else
                {
                    $discount_amt = $coupon['discount_value'];
                    $discount_label = '₹' . $coupon['discount_value'] . ' off';
                }
                
                $_SESSION['coupon_applied'] = array(
                    'coupon_id' => $coupon['coupon_id'],
                    'code' => $coupon['code'],
                    'discount_type' => $coupon['discount_type'],
                    'discount_value' => $coupon['discount_value'],
                    'discount_amount' => $discount_amt,
                    'discount_label' => $discount_label
                );
                $coupon_success = "Coupon '" . $coupon['code'] . "' applied! You save " . $discount_label . "!";
            }
        }
    }
}

// Remove coupon
if(isset($_GET['remove_coupon']))
{
    unset($_SESSION['coupon_applied']);
    header('location:checkout.php');
    exit();
}

// Update coupon usage count when order is placed
if(isset($_POST['submit']) && !empty($_SESSION['coupon_applied']))
{
    $cid = $_SESSION['coupon_applied']['coupon_id'];
    mysqli_query($db, "UPDATE coupons SET used_count = used_count + 1 WHERE coupon_id='$cid'");
    unset($_SESSION['coupon_applied']);
}

// Calculate final total with discount
$discount_display = 0;
$final_total = $item_total;
if(!empty($_SESSION['coupon_applied']))
{
    if($_SESSION['coupon_applied']['discount_type'] == 'percentage')
    {
        $discount_display = ($item_total * $_SESSION['coupon_applied']['discount_value']) / 100;
    }
    else
    {
        $discount_display = $_SESSION['coupon_applied']['discount_value'];
    }
    $final_total = $item_total - $discount_display;
    if($final_total < 0) $final_total = 0;
}
?>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="#">
    <title>FoodieSpot - Checkout</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animsition.min.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .coupon-box {
            background: #f9f9f9;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .coupon-box h5 {
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .coupon-box h5 i {
            color: #ff6b35;
            margin-right: 8px;
        }
        .coupon-input-group {
            display: flex;
            gap: 10px;
        }
        .coupon-input-group input {
            flex: 1;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .coupon-success {
            color: #28a745;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px 15px;
            background: #d4edda;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
        .coupon-error {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px 15px;
            background: #f8d7da;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
        .coupon-applied-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 13px;
            margin-left: 5px;
        }
        .discount-row td {
            color: #28a745 !important;
            font-weight: bold;
        }
        .total-row td {
            font-size: 18px;
        }
    </style>
</head>
<body>
    
    <div class="site-wrapper">
        <header id="header" class="header-scroll top-header headrom">
            <nav class="navbar navbar-dark">
                <div class="container">
                    <button class="navbar-toggler hidden-lg-up" type="button" data-toggle="collapse" data-target="#mainNavbarCollapse">&#9776;</button>
                    <a class="navbar-brand" href="index.php"> <img class="img-rounded" src="images/food-picky-logo.pn" alt=""> </a>
                    <div class="collapse navbar-toggleable-md float-lg-right" id="mainNavbarCollapse">
                        <ul class="nav navbar-nav">
                            <li class="nav-item"> <a class="nav-link active" href="index.php">Home <span class="sr-only">(current)</span></a> </li>
                            <li class="nav-item"> <a class="nav-link active" href="restaurants.php">Cities</a> </li>
                            
						<?php
					if(empty($_SESSION["user_id"]))
						{
							echo '<li class="nav-item"><a href="login.php" class="nav-link active">Login</a> </li>
						  <li class="nav-item"><a href="registration.php" class="nav-link active">Signup</a> </li>';
						}
					else
						{
							echo '<li class="nav-item"><a href="your_orders.php" class="nav-link active">Your orders</a> </li>';
							echo '<li class="nav-item"><a href="#" onclick="showalert()" class="nav-link active">Logout</a> </li>';
						}
					?>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <div class="page-wrapper">
            <div class="top-links">
                <div class="container">
                    <ul class="row links">
                        <li class="col-xs-12 col-sm-4 link-item"><span>1</span><a href="restaurants.php">Choose Restaurant</a></li>
                        <li class="col-xs-12 col-sm-4 link-item"><span>2</span><a href="#">Pick Your favorite food</a></li>
                        <li class="col-xs-12 col-sm-4 link-item active"><span>3</span><a href="checkout.php">Order and Pay online</a></li>
                    </ul>
                </div>
            </div>
			
            <div class="container">
                <span style="color:green; font-size:18px; font-weight:bold;">
                    <?php echo $success; ?>
                </span>
            </div>
            
            <div class="container m-t-30">
                <form action="" method="post">
                    <div class="widget clearfix">
                        <div class="widget-body">
                            <div class="row">
                                <div class="col-sm-12">

                                    <!-- Coupon Code Section -->
                                    <div class="coupon-box">
                                        <h5><i class="fa fa-tag"></i> Have a Coupon Code?</h5>
                                        <?php if(!empty($_SESSION['coupon_applied'])) { ?>
                                            <div class="coupon-success">
                                                <i class="fa fa-check-circle"></i> 
                                                Coupon <strong><?php echo $_SESSION['coupon_applied']['code']; ?></strong> applied! 
                                                <span class="coupon-applied-badge"><?php echo $_SESSION['coupon_applied']['discount_label']; ?></span>
                                                <a href="checkout.php?remove_coupon=1" class="btn btn-sm btn-outline-danger" style="margin-left:10px;">
                                                    <i class="fa fa-times"></i> Remove
                                                </a>
                                            </div>
                                        <?php } else { ?>
                                            <div class="coupon-input-group">
                                                <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code e.g. SAVE20" value="<?php echo htmlspecialchars($_POST['coupon_code']); ?>">
                                                <button type="submit" name="apply_coupon" class="btn btn-warning" style="font-weight:bold;">
                                                    <i class="fa fa-check"></i> Apply
                                                </button>
                                            </div>
                                            <?php if(!empty($coupon_error)) { ?>
                                                <div class="coupon-error">
                                                    <i class="fa fa-exclamation-circle"></i> <?php echo $coupon_error; ?>
                                                </div>
                                            <?php } ?>
                                            <?php if(!empty($coupon_success)) { ?>
                                                <div class="coupon-success">
                                                    <i class="fa fa-check-circle"></i> <?php echo $coupon_success; ?>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>

                                    <!-- Order Summary -->
                                    <div class="cart-totals margin-b-20">
                                        <div class="cart-totals-title">
                                            <h4>Summary</h4>
                                        </div>
                                        <div class="cart-totals-fields">
                                            <table class="table">
                                                <tbody>
                                                    <tr>
                                                        <td>Subtotal</td>
                                                        <td><?php echo "₹" . number_format($item_total, 2); ?></td>
                                                    </tr>
                                                    <?php if($discount_display > 0) { ?>
                                                    <tr class="discount-row">
                                                        <td>
                                                            <i class="fa fa-tag"></i> Discount 
                                                            (<?php echo $_SESSION['coupon_applied']['code']; ?>)
                                                        </td>
                                                        <td>- ₹<?php echo number_format($discount_display, 2); ?></td>
                                                    </tr>
                                                    <?php } ?>
                                                    <tr class="total-row">
                                                        <td class="text-color"><strong>Total</strong></td>
                                                        <td class="text-color">
                                                            <strong>₹<?php echo number_format($final_total, 2); ?></strong>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Payment Options -->
                                    <div class="payment-option">
                                        <ul class="list-unstyled">
                                            <li>
                                                <label class="custom-control custom-radio m-b-20">
                                                    <input name="mod" id="radioStacked1" checked value="COD" type="radio" class="custom-control-input"> 
                                                    <span class="custom-control-indicator"></span> 
                                                    <span class="custom-control-description">Payment on delivery</span>
                                                </label>
                                            </li>
                                            <li>
                                                <label class="custom-control custom-radio m-b-10">
                                                    <a href="payment/index.html">
                                                        <input name="mod" type="radio" value="Pay" class="custom-control-input"> 
                                                        <span class="custom-control-indicator"></span> 
                                                        <span class="custom-control-description">Pay Online <img src="images/paypal.jpg" alt="" width="90"></span>
                                                    </a>
                                                </label>
                                            </li>
                                        </ul>
                                        <p class="text-xs-center">
                                            <input type="submit" onclick="return confirm('This Action will order the Selected food items');" name="submit" class="btn btn-outline-success btn-block" value="Order now">
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/tether.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/animsition.min.js"></script>
    <script src="js/bootstrap-slider.min.js"></script>
    <script src="js/jquery.isotope.min.js"></script>
    <script src="js/headroom.js"></script>
    <script src="js/foodpicky.min.js"></script>
</body>
</html>

<?php
}
?>
