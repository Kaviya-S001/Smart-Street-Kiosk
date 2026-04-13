<?php
session_start();
include("connection/connect.php");

// Validate coupon code
if(isset($_POST['apply_coupon']))
{
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    $cart_total = $_POST['cart_total'];
    
    if(empty($coupon_code))
    {
        $_SESSION['coupon_error'] = "Please enter a coupon code!";
        unset($_SESSION['coupon_applied']);
    }
    else
    {
        // Check if coupon exists
        $query = mysqli_query($db, "SELECT * FROM coupons WHERE code='$coupon_code' AND status='active'");
        
        if(mysqli_num_rows($query) == 0)
        {
            $_SESSION['coupon_error'] = "Invalid coupon code!";
            unset($_SESSION['coupon_applied']);
        }
        else
        {
            $coupon = mysqli_fetch_array($query);
            
            // Check expiry
            if(!empty($coupon['expiry_date']) && $coupon['expiry_date'] < date('Y-m-d'))
            {
                $_SESSION['coupon_error'] = "This coupon has expired!";
                unset($_SESSION['coupon_applied']);
            }
            // Check usage limit
            elseif($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses'])
            {
                $_SESSION['coupon_error'] = "This coupon has reached its usage limit!";
                unset($_SESSION['coupon_applied']);
            }
            // Check minimum order
            elseif($cart_total < $coupon['min_order'])
            {
                $_SESSION['coupon_error'] = "Minimum order of ₹" . $coupon['min_order'] . " required for this coupon!";
                unset($_SESSION['coupon_applied']);
            }
            else
            {
                // Calculate discount
                if($coupon['discount_type'] == 'percentage')
                {
                    $discount = ($cart_total * $coupon['discount_value']) / 100;
                    $discount_label = $coupon['discount_value'] . '% off';
                }
                else
                {
                    $discount = $coupon['discount_value'];
                    $discount_label = '₹' . $coupon['discount_value'] . ' off';
                }
                
                // Store coupon in session
                $_SESSION['coupon_applied'] = array(
                    'coupon_id' => $coupon['coupon_id'],
                    'code' => $coupon['code'],
                    'discount_type' => $coupon['discount_type'],
                    'discount_value' => $coupon['discount_value'],
                    'discount_amount' => $discount,
                    'discount_label' => $discount_label
                );
                unset($_SESSION['coupon_error']);
            }
        }
    }
    header('location:checkout.php');
    exit();
}

// Remove coupon
if(isset($_GET['remove_coupon']))
{
    unset($_SESSION['coupon_applied']);
    unset($_SESSION['coupon_error']);
    header('location:checkout.php');
    exit();
}
?>
