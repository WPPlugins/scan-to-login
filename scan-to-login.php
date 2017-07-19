<?php
/*
Plugin Name: Scan-to-Login
Plugin URI: 
Description: Scan-to-Login Wordpress Plugin.
Version: 2.1.201
API Version: 1.1.200
JS Version: 1.1.120
Author: zap-group
Author URI: 
License: GPL
*/

require 's2l_language.inc';

class Scantologin {

    function enqueue_scripts() {
        wp_enqueue_script('jquery');
        if (defined('S2L_API_URL')) {
            $env_url = S2L_API_URL;
        }else{
            $env_url = 'https://zapapi.zapzap.mobi/zappertech';
        }
        if(get_option('scantologin_js_file') === 'local'){
            wp_enqueue_script('zappertech', plugins_url('zappertech.js', __FILE__));
        }else{
            wp_enqueue_script('zappertech', $env_url.'/1.1.120/bundles/zappertech');
        }
        wp_enqueue_script('bootstrap', home_url() . '/index.php?zappertech=bootstrap&lang='.get_locale());
        wp_enqueue_style('scantologin', plugins_url('scantologin.css', __FILE__));
    }
    
    function hijack() {
        $zapperAction = filter_input(INPUT_GET, 'zappertech', FILTER_SANITIZE_STRING);
        if($zapperAction !== null) {
            switch ($zapperAction) {
                case 'bootstrap':
                    include 'bootstrap.php';
                    break;
                case 'register':
                    $this->register_user();
                    break;
                case 'authenticate':
                    $this->authenticate_user();
                    break;
                case 'login':
                    $this->log_user_in();
                    break;
            }
            exit();
        }
    }
    
    function log_user_in(){
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);  
        $user = wp_signon(array('user_login'=>$username, 'user_password'=>$password));
        if(!$user){
            $response['success'] = FALSE;
        }else{
            $response['success'] = TRUE;
        }
        return $response;
    }
    
    function register_user() {
        $scan_to_login_language = new s2l_language();
        $response = array();

        $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
        $phoneNumber = filter_input(INPUT_POST, 'phoneNumber', FILTER_SANITIZE_STRING);
        $addressLine1 = filter_input(INPUT_POST, 'addressLine1', FILTER_SANITIZE_STRING);
        $addressLine2 = filter_input(INPUT_POST, 'addressLine2', FILTER_SANITIZE_STRING);
        $addressCity = filter_input(INPUT_POST, 'addressCity', FILTER_SANITIZE_STRING);
        $addressZip = filter_input(INPUT_POST, 'addressZip', FILTER_SANITIZE_STRING);
        $addressCountryIso = filter_input(INPUT_POST, 'addressCountryIso', FILTER_SANITIZE_STRING);
        
        // User preferred login  
        $cust_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        // Validation
        $response['errors'] = array();
        $response['success'] = FALSE;
        $response['username'] = '';
        $response['password'] = '';
        
        if(empty($firstName)){
            $response['errors'][] = $scan_to_login_language->getString('enter_first_name');
        }
        if(empty($lastName)){
            $response['errors'][] = $scan_to_login_language->getString('enter_last_name');
        }
        // Must have both
        if(empty($email) && empty($cust_username)){
            $response['errors'][] = $scan_to_login_language->getString('enter_email_address_or_username');
        }
        // Check for both 
        if(username_exists($email) || username_exists($cust_username)){
            $response['errors'][] = $scan_to_login_language->getString('already_registered');
        }
        if(empty($password)){
            $response['errors'][] = $scan_to_login_language->getString('enter_password');
        }
        // Registration
        if(empty($response['errors'])) {
            $userId = wp_create_user($cust_username, $password, $email);
            if(is_wp_error($userId)) {
                $response['errors'][] = $userId->get_error_message();
            } else {
                wp_update_user(array(
                    'ID'            => $userId,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName
                ));
                $response['success'] = TRUE;
                $response['username'] = $cust_username;
                $response['password'] = $password;
            }
        }

        $this->json_encoded($response);
    }
	
	function authenticate_user() {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);//sanitize_text_field($_POST['username']);
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);//sanitize_text_field($_POST['password']);

            $user = wp_authenticate_username_password(NULL, $username, $password);

            $response = array();
            $response['errors'] = array();
            $response['success'] = FALSE;
            $response['username'] = $username;
            $response['password'] = $password;
		
		if (is_wp_error($user)) {
			$response['errors'] = $user;

        } else {
			$response['success'] = TRUE;
			$response['username'] = $username;
			$response['password'] = $password;
		}

        $this->json_encoded($response);
	}

    function json_encoded($data) {
        @header('Cache-Control: no-cache, must-revalidate');
        @header('Expires: Mon, 26 July 1997 05:00:00 GMT');
        @header('Content-type: application/json');

        echo json_encode($data);
        exit();
    }
	
    function add_admin_menu() {
        add_options_page( 'Scan-to-Login Options', 'Scan-to-Login', 'manage_options', 'scantologin', array($this, 'admin_page'));
    }
    
    function admin_page_save($post) {
        $scan_to_login_language = new s2l_language();
        $results = array(
            'errors' => array(),
            'merchantId' => 0,
            'siteId' => 0,
            'scantologin_parent_container' => '#loginform',
            'scantologin_position' => 'prepend',
            'scantologin_js_file' => 'hosted',
            'saved' => false,
            'scantologin_self_registration_allowed' => 'true',
            'scantologin_demo_mode' => 'false'
        );
        
        if(isset($post['scantologin_merchant_id'])) {
            $results['merchantId'] = $post['scantologin_merchant_id'];
            if(!is_numeric($post['scantologin_merchant_id'])){
                $results['errors'][] = $scan_to_login_language->getString('enter_merchant_id');
            } else {
                update_option('scantologin_merchant_id', $results['merchantId']);
            }
        } else {
            if (empty($post['finish'])) {
                $results['errors'][] = $scan_to_login_language->getString('enter_merchant_id');    
            } 
        }
        if(isset($post['scantologin_site_id'])) { 
            $results['siteId'] = $post['scantologin_site_id'];
            if(!is_numeric($post['scantologin_site_id'])){
                $results['errors'][] = $scan_to_login_language->getString('enter_site_id');
            } else {
                update_option('scantologin_site_id', $results['siteId']);
            }
        } else {
            if (empty($post['finish'])) {
                $results['errors'][] = $scan_to_login_language->getString('enter_site_id');
            }
        }
        
        if(isset($post['scantologin_parent_container'])) { 
            $results['scantologin_parent_container'] = $post['scantologin_parent_container'];
            if($post['scantologin_parent_container'] === ''){
                $results['errors'][] = $scan_to_login_language->getString('must_enter_scantologin_parent_container');
            } else {
                update_option('scantologin_parent_container', $results['scantologin_parent_container']);
            }
        } 
        if(isset($post['scantologin_position'])) { 
            $results['scantologin_position'] = $post['scantologin_position'];
            if($post['scantologin_position'] === ''){
                $results['errors'][] = $scan_to_login_language->getString('must_select_position');
            } else {
                update_option('scantologin_position', $results['scantologin_position']);
            }
        } 
        if(isset($post['scantologin_js_file'])) { 
            $results['scantologin_js_file'] = $post['scantologin_js_file'];
            if($post['scantologin_js_file'] === ''){
                $results['errors'][] = $scan_to_login_language->getString('must_select_js_file');
            } else {
                update_option('scantologin_js_file', $results['scantologin_js_file']);
            }
        }
        
        if(empty($results['errors'])) {
            update_option('scantologin_self_registration_allowed', $results['scantologin_self_registration_allowed']);
            update_option('scantologin_demo_mode', $results['scantologin_demo_mode']);
            $results['saved'] = true;
        }
        return $results;
    }

    function admin_page() {
        global $wp;

        $scan_to_login_language = new s2l_language();
        $merchantId = get_option('scantologin_merchant_id', 0);
        $siteId = get_option('scantologin_site_id', 0);
        
        $demoMode = false;
        $saved = false;
        $errors = array();

        $prepend_position_selected = '';
        $append_position_selected = '';
        $server_js_file_selected = '';
        $local_js_file_selected = '';
        if(get_option('scantologin_position') === 'append'){
            $append_position_selected = 'selected="selected"';
        }else{
            $prepend_position_selected = 'selected="selected"';//Default to prepend
        }
        if(get_option('scantologin_js_file') === 'local'){
            $local_js_file_selected = 'selected="selected"';
        }else{
            $server_js_file_selected = 'selected="selected"';//Default to hosted file
        }
        
        // save default settings page
        if(isset($_POST['submit'])) {
            $prepend_position_selected = '';
            $append_position_selected = '';
            $server_js_file_selected = '';
            $local_js_file_selected = '';
            $saveResults = $this->admin_page_save($_POST);
            $merchantId = $saveResults['merchantId'];
            $siteId = $saveResults['siteId'];
            $scantologin_parent_container = $saveResults['scantologin_parent_container'];

            if($saveResults['scantologin_position'] === strtolower($scan_to_login_language->getString('append'))){
                $append_position_selected = 'selected="selected"';
            }else{
                $prepend_position_selected = 'selected="selected"';//Default to prepend
            }
            
            if($saveResults['scantologin_js_file'] === strtolower($scan_to_login_language->getString('local_js_file'))){
                $local_js_file_selected = 'selected="selected"';
            }else{
                $server_js_file_selected = 'selected="selected"';//Default to hosted file
            }
            $selfRegistrationAllowed = $saveResults['scantologin_self_registration_allowed'];
            $demoMode = false;
            $saved = $saveResults['saved'];
            $errors = $saveResults['errors'];
        }

        // set up steps for "wizard"

        // get the clean settings page url for navigation & try handle sites under subfolders
        $current_server = site_url();
        $current_server = rtrim($current_server, '/');
        $current_uri_parts = explode('/', $_SERVER['REQUEST_URI']);

        if (!empty($current_uri_parts)) {
            foreach ($current_uri_parts as $i => $u) {
                if ($u == 'wp-admin') {
                    break;
                } else {
                    if (!empty($u)) {
                        unset($current_uri_parts[$i]);    
                    }
                }
            }
            array_values($current_uri_parts);
        }

        $current_settings_url = strtok($current_server . implode('/', $current_uri_parts), '?') .  '?page=scantologin';
        
        $step = null;

        // step 1
        if (empty($merchantId) && empty($siteId) && empty($_GET['msid']) & empty($_GET['step'])) {
            
            $step = 1;
            
            if (defined('S2L_API_URL')) {
                $zapper_url = 'http://qa.zapper.com/cms/installation.php?w=' . base64_encode($current_settings_url . ']wordpress');
            } else {
                $zapper_url = 'http://zapper.com/cms/installation.php?w=' . base64_encode($current_settings_url . ']wordpress');
            }
            
        }
        
        // step 2
        if (!empty($_GET['msid']) || (!empty($_GET['step']) && $_GET['step'] == 2) ) {

            $step = 2;
            
            if (!empty($_GET['msid'])) {
                $merchantsiteId = explode(']', base64_decode($_GET['msid']));
                if (isset($merchantsiteId[0]) && isset($merchantsiteId[1])) {
                    $merchantId = $merchantsiteId[0];
                    $siteId = $merchantsiteId[1];
                }
            }
        }

        // step 3
        if (!empty($_GET['step']) && $_GET['step'] == 3) {
            $step = 3;
            $result = $this->admin_page_save($_POST);
        }
        
        if ( !empty($_POST['finish']) )  {
            $step = null;

            $result = $this->admin_page_save($_POST);
        }

        $scantologin_parent_container = get_option('scantologin_parent_container', '#loginform');
        $selfRegistrationAllowed = get_option('scantologin_self_registration_allowed', 'true');
?>

        <div class="wrap">
            <div id="icon-options-general" class="icon32"><br></div>
            <h2><?php echo $scan_to_login_language->getString('s2l_options');?></h2>
            <?php if($saved) { ?>
            <div class="settings-error updated"><p><?php echo $scan_to_login_language->getString('options_saved');?></p></div>
            <?php } ?>
            <?php if(!empty($errors)) { ?>
            <div class="settings-error updated">
                <?php foreach($errors as $error) { ?>
                <p><?php echo $error ?></p>
                <?php } ?>
            </div>
            <?php } ?>

            <?php if (!empty($step) && $step == 1):?>
                    <h3><?php echo $scan_to_login_language->getString('step1_heading');?></h3>
                    <p>Retrieve your Merchant and Site ID at zapper.com. <a href="<?php echo $zapper_url?>">Click here to do this now.</a></p>
                    <p>If you already have a Merchant and Site ID you can continue to the <a href="<?php echo $current_settings_url.'&step=2'?>">next step</a>.</p>
            <?php elseif (!empty($step) && $step == 2):?>
                <form method="post" action="<?php echo $current_settings_url?>&step=3">
                    <h3><?php echo $scan_to_login_language->getString('step2_heading');?></h3>
                    <table>
                        <tr>
                            <td style="width: 200px;"><?php echo $scan_to_login_language->getString('merchant_id');?></td>
                            <td><input type="text" name="scantologin_merchant_id" value="<?php echo $merchantId ?>" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('site_id');?></td>
                            <td><input type="text" name="scantologin_site_id" value="<?php echo $siteId ?>" /></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="step2" id="submit" class="button button-primary" value="<?php echo $scan_to_login_language->getString('next_step');?>"/>
                    </p>
                </form>

            <?php elseif (!empty($step) && $step == 3):?>
                <form method="post" action="<?php echo $current_settings_url?>">
                    <h3><?php echo $scan_to_login_language->getString('step3_heading');?></h3>
                    <table>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('element');?></td>
                            <td><input type="text" name="scantologin_parent_container" value="<?php echo $scantologin_parent_container ?>" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('position');?></td>
                        <td><select name="scantologin_position">
                            <option value="append" title="" <?php echo $append_position_selected;?>><?php echo $scan_to_login_language->getString('append');?></option>
                            <option value="prepend" title="" <?php echo $prepend_position_selected;?>><?php echo $scan_to_login_language->getString('prepend');?></option>
                        </select></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('js_file');?></td>
                        <td><select name="scantologin_js_file">
                            <option value="hosted" title="" <?php echo $server_js_file_selected;?>><?php echo $scan_to_login_language->getString('server_js_file');?></option>
                            <option value="local" title="" <?php echo $local_js_file_selected;?>><?php echo $scan_to_login_language->getString('local_js_file');?></option>
                        </select></td>
                        </tr>
                        <tr>
                            <td style="padding-top: 3px;"><?php echo $scan_to_login_language->getString('allow_any_user_registration');?></td>
                            <td>
                                <input <?php if($selfRegistrationAllowed === 'true') { ?>checked="checked"<?php } ?> style="margin: 2px 0 0 2px;" type="checkbox" name="scantologin_self_registration_allowed" value="true" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="finish" id="submit" class="button button-primary" value="<?php echo $scan_to_login_language->getString('finish_step');?>"/>
                    </p>
                </form>
            <?php else:?>

                <!-- Start standard form after wizard -->
                <form method="post" action="">
                    <br>
                    <table>
                        <tr>
                            <td style="width: 200px;"><?php echo $scan_to_login_language->getString('merchant_id');?></td>
                            <td><input type="text" name="scantologin_merchant_id" value="<?php echo $merchantId ?>" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('site_id');?></td>
                            <td><input type="text" name="scantologin_site_id" value="<?php echo $siteId ?>" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('element');?></td>
                            <td><input type="text" name="scantologin_parent_container" value="<?php echo $scantologin_parent_container ?>" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('position');?></td>
                        <td><select name="scantologin_position">
                            <option value="append" title="" <?php echo $append_position_selected;?>><?php echo $scan_to_login_language->getString('append');?></option>
                            <option value="prepend" title="" <?php echo $prepend_position_selected;?>><?php echo $scan_to_login_language->getString('prepend');?></option>
                        </select></td>
                        </tr>
                        <tr>
                            <td><?php echo $scan_to_login_language->getString('js_file');?></td>
                        <td><select name="scantologin_js_file">
                            <option value="hosted" title="" <?php echo $server_js_file_selected;?>><?php echo $scan_to_login_language->getString('server_js_file');?></option>
                            <option value="local" title="" <?php echo $local_js_file_selected;?>><?php echo $scan_to_login_language->getString('local_js_file');?></option>
                        </select></td>
                        </tr>
                        <tr>
                            <td style="padding-top: 3px;"><?php echo $scan_to_login_language->getString('allow_any_user_registration');?></td>
                            <td>
                                <input <?php if($selfRegistrationAllowed === 'true') { ?>checked="checked"<?php } ?> style="margin: 2px 0 0 2px;" type="checkbox" name="scantologin_self_registration_allowed" value="true" />
                            </td>
                        </tr>
                    </table>
                    <p><a href="#" id="scantologin-show-advanced-options" style="display:none;"><?php echo $scan_to_login_language->getString('show_advanced_options');?></a></p>
                    <div id="scantologin-advanced-options" style="background: #f3f3f3; width: 350px; padding: 1px 10px 10px 10px; display: none;">
                        <h3><?php echo $scan_to_login_language->getString('advanced_options');?></h3>
                        <table>
                            <tr>
                                <td style="width: 200px;"><?php echo $scan_to_login_language->getString('demo_mode');?></td>
                                <td>
                                    <input <?php if($demoMode === 'true') { ?>checked="checked"<?php } ?> type="checkbox" name="scantologin_demo_mode" value="true" />
                                </td>
                            </tr>
                        </table>
                    </div>
                    <script>
                        (function($){
                            $(function(){
                                $('#scantologin-show-advanced-options').on('click', function() {
                                   $('#scantologin-advanced-options').show(); 
                                   return false;
                                });
                            });
                        })(jQuery);
                    </script>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $scan_to_login_language->getString('save_changes');?>"/>
                    </p>
                </form>
                <!-- End standard form after wizard -->
            <?php endif;?>
        </div>
    <?php }
}

//Add an option for settings on plugin page:
function my_plugin_admin_action_links($links, $file) {
    static $plugin;
    if (!$plugin) {
        $plugin = plugin_basename(__FILE__);
    }
    if ($file == $plugin) {
        $settings_link = '<a href="options-general.php?page=scantologin">Settings</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}

$scantologin = new Scantologin();
add_option( 'scantologin_merchant_id', '0' );
add_option( 'scantologin_site_id', '0' );
add_option( 'scantologin_parent_container', '#loginform' );
add_option( 'scantologin_position', 'prepend' );
add_option( 'scantologin_demo_mode', 'false' );
add_option( 'scantologin_js_file', 'hosted');
add_option( 'scantologin_self_registration_allowed', 'true');
add_action('wp_enqueue_scripts', array($scantologin, 'enqueue_scripts'));
add_action('login_form', array($scantologin, 'enqueue_scripts'));
add_action('init', array($scantologin, 'hijack'));
add_action('admin_menu', array($scantologin, 'add_admin_menu'));
add_filter('plugin_action_links', 'my_plugin_admin_action_links', 10, 2);
