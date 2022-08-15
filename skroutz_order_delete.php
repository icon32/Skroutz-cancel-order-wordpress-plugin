<?php
/*
Plugin Name: Skroutz Order Canceletion
Plugin URI: https://lithosdigital.gr
Description: This Plugin will cancel the orders within skroutz.
Author: Dionisis Bolanis
Author URI: https://bolanis.eu
License: GPL2
Version: 1.1
*/

function skroutz_order_canceletions_meta_box() {
  $screens = [ 'shop_order'];
  foreach ( $screens as $screen ) {
      add_meta_box(
          'skroutz_order_canceletions_box',    
          'Skroutz Order Details',     
          'skroutz_order_canceletions_field',  
          $screen   ,                     
          'side',
          'high'
      );
  }
}
add_action( 'add_meta_boxes', 'skroutz_order_canceletions_meta_box' );

//Order Tracking Number Field
function skroutz_order_canceletions_field( $post ) {

  function skroutz_order_get_info($order_id){
    $headers = [
      'Accept: application/vnd.skroutz+json; version=3.0',
      'Authorization: Bearer cWeFE40cz9l4Vy5k5-W_uVJX74b7A8Knw4Odrs1vJB12szDtUa30dcGfKGmDnknl599N794qKJKZeTgAoI_oOg=='
    ];
    //Call Skroutz API
    $call = curl_init("https://api.skroutz.gr/merchants/cps/orders/" . $order_id);
    curl_setopt($call, CURLOPT_HTTPHEADER, $headers );
    curl_setopt($call, CURLOPT_RETURNTRANSFER, true );
    $skroutz_responce = curl_exec($call);
    curl_close($call);
    //Decode Skroutz date making them json
    $skroutz_data = json_decode($skroutz_responce, true);
    
    if($skroutz_data['errors'][0]['code'] == 'order_error'){ //Check for Errors in the Api
      $skroutz_errors = $skroutz_data['errors'][0]['messages'] ;
      foreach($skroutz_errors as $errors){
        echo $errors.'<br>';
      }
    }else{ // If no errors display order details
      // var_dump($skroutz_data);
      // echo '<br><br><br>';
      
      foreach($skroutz_data as $data){
        echo 'Αριθμός Παραγγελίας: ' . $data['code'] . '<br>';
        $date = explode("T",$data['date']);
        $date = $date[0];
        echo 'Ημερομινία Παραγγελίας: ' . $date . '<br>';
        echo 'Χρέωση: ' . $data['revenue'] . '€ <br>';
        echo 'Ποσοστό: ' . $data['commission'] . '€ <br>';
        echo 'Κατάσταση Παραγγελίας: ' . $data['state'] . '<br>';
        if($data['state'] == 'Χρεώθηκε' ){
          if($data['charged'] == 1){
            $skroutz_charged = 'Ναι';
          }else{
            $skroutz_charged = 'Όχι';
          }
          echo 'Χρεώθηκε: ' . $skroutz_charged . '<br>';
          $date = explode("T",$data['charged_at']);
          $date = $date[0];
          echo 'Ημερωμινία Χρέωσης: ' . $date . '<br>';
          echo '<br>';
          echo '<strong>Λόγος Ακυρωσης:</strong> <br>';
          echo '<div><select id="cancel_reason" name="cancel_reason">';

          echo '<option value="-">-</option> ';
          foreach($data['reject_options'] as $reason){
            echo '<option value="'.$reason['name'].'">'.$reason['description'].'</option> ';
          }
          echo '</select></div>';

          ?>
          <div style="margin-top:5px;">
          <textarea name="canceletion_reason" id="canceletion_reason" style="display:none;width:100%;height:100px;" ></textarea>
          </div>
          <script>
            jQuery(document).ready(function($) {
              console.log('This is skroutz mech');
              $( "#cancel_reason" ).change(function() {
                if($('#cancel_reason').val() == 'other'){
                  $('#canceletion_reason').css('display', 'block');
                }else{
                  $('#canceletion_reason').css('display', 'none');
                }
              });
              })
          </script>
          <?php 
        }elseif($data['state'] == 'Ακυρωμένη'){
          echo 'Λόγος Ακυρωσης: ' . $data['rejection_reason'] . '<br>';
          //echo 'Refunded At: ' . $data['refunded_at'] . '<br>';
        }elseif($data['state'] == 'Πιστώθηκε'){
          echo 'Λόγος Ακυρωσης: ' . $data['rejection_reason'] . '<br>';
          $date = explode("T",$data['refunded_at']);
          $date = $date[0];
          echo 'Επιστροφή Χριμάτων: ' . $date . ' '.$time. '<br>';
          //var_dump($date);
        }
        //var_dump($skroutz_data);
      }
    }
  }
  
  skroutz_order_get_info($post->ID);
  
  // echo '<br><br>';
  // echo '<strong>Ο μηχανισμός δεν είναι έτοιμος, μην τον δοκιμάσετε.</strong>';
  ?>
  
  <?php
}

//Save Tracking Number
function skroutz_order_canceletions_update( $post_id, $post, $update ) {
  
  if($post->post_type == 'shop_order'){

      //If cancel reason exist (Which will load if there is no error with get api or if the order exist)
    if ( isset( $_POST['cancel_reason']) ) {

      if($_POST['cancel_reason'] == '-'){

        if(isset( $_POST['order_status'])){

          //check if the user is canceling the order and have not set the reason on skroutz metabox 
          if($_POST['order_status'] == 'wc-cancelled'){
                $skroutz_errors = array('status'=>'true', 'type'=>'notice notice-error', 'message'=>'You Havent Canceled the order on Skroutz!!!!');
                update_option('skroutz_order_cancel_error', $skroutz_errors);
                $location = $_SERVER['HTTP_REFERER'];
                wp_safe_redirect($location);
                exit();
          }
        }

      }else{

        if(isset( $_POST['order_status'])){
          if($_POST['order_status'] == 'wc-cancelled'){
            if(isset( $_POST['cancel_reason'])){
              if($_POST['cancel_reason'] != '-'){

                $headers = [
                  'Accept: application/vnd.skroutz+json; version=3.0',
                  'Authorization: Bearer cWeFE40cz9l4Vy5k5-W_uVJX74b7A8Knw4Odrs1vJB12szDtUa30dcGfKGmDnknl599N794qKJKZeTgAoI_oOg=='
                ];
                //Call Skroutz API
                $call = curl_init("https://api.skroutz.gr/merchants/cps/orders/" . $order_id);
                curl_setopt($call, CURLOPT_HTTPHEADER, $headers );
                curl_setopt($ch, CURLOPT_POST, 1);
                
                if(isset( $_POST['cancel_reason'])){
                  curl_setopt($ch, CURLOPT_POSTFIELDS,'{ "reason": { "name" : "' . $_POST['cancel_reason'] . '", "comment": "' . $_POST['canceletion_reason'] . '" } }');
                }else{
                  curl_setopt($ch, CURLOPT_POSTFIELDS,'{ "reason": { "name" : "' . $_POST['cancel_reason'] . '"} }');
                }
                
                curl_setopt($call, CURLOPT_RETURNTRANSFER, true );
                $skroutz_responce = curl_exec($call);
                curl_close($call);
                //Decode Skroutz date making them json
                $skroutz_data = json_decode($skroutz_responce, true);
                $headers = 'From: support@lithosdigital.gr \r\n' ;
                $subject = 'An attemt with skroutz took place WineOutlet ' . home_url();
                $message = 'Skroutz Responce: '.$skroutz_data . 'Order_ID: '. $post_id;
                $sent = wp_mail('d.bolanis@lithosdigital.gr', $subject, $message, $headers);

                $skroutz_errors = array('status'=>'true', 'type'=>'notice notice-success', 'message'=>'You Havent Canceled the order on Skroutz!!!! ' . $skroutz_responce);
                update_option('skroutz_order_cancel_error', $skroutz_errors);
              }
            }
          }
        }

        //Here we make the call to skroutz to cancel the order
        // $skroutz_errors = array('status'=>'true', 'type'=>'notice notice-success', 'message'=>'Skroutz was Updated: Success!');
        // update_option('skroutz_order_cancel_error', $skroutz_errors);
        
      }

    }
  }

}

add_action( 'save_post', 'skroutz_order_canceletions_update', 1, 3 );
 
// Display any Skroutz Errors
function skroutz_order_cancel_error_handler() {
  $skroutz_errors = get_option('skroutz_order_cancel_error');

  if($skroutz_errors['status'] == 'true') {
      echo '<div class="'. $skroutz_errors['type'].' is-dismissible"><p>' . $skroutz_errors['message'] . '</p></div>';
  }   
  $skroutz_errors = array('status'=>'false');
  update_option('skroutz_order_cancel_error', $skroutz_errors);
}
add_action( 'admin_notices', 'skroutz_order_cancel_error_handler' );