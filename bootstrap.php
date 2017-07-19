<?php
/**
 * Scan-to-Login for Wordpress
 * Created: June 2013
 * Creator: ZapGroup
 * Website: http://www.zapper.com/
 * API Version: 1.1.60
 */
header('Content-type: text/javascript');
$merchantId = get_option('scantologin_merchant_id');
$siteId = get_option('scantologin_site_id');
$parentContainer = get_option('scantologin_parent_container');
$containerPosition = get_option('scantologin_position');
        
$selfRegistrationAllowed = get_option('scantologin_self_registration_allowed', 'false');
$demoMode = get_option('scantologin_demo_mode', 'false');
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING);
$scan_to_login_language = new s2l_language($lang);

if (defined('S2L_API_URL')) {
    $api_url = S2L_API_URL;
}else{
    $api_url = 'https://zapapi.zapzap.mobi/zappertech';
}

$baseUrl = home_url();

if($merchantId && $siteId) { ?>
<?php if(false) { ?><script><?php } ?>
(function($) {
$(document).ready(function() {
        var baseUrl = '<?php echo $baseUrl; ?>/';
        var allowRegistration = <?php echo $selfRegistrationAllowed; ?>;
        var placeHolder = $('<div id="scantologin-container"><div id="logo-container" class="zapperLogo"><div class="scantologin-qrcode-placeholder"></div></div><div id="scantologin-end-container"><span id="scantologin-available-for"></span><a href="http://www.zapper.com/" target="_blank" id="scantologin-zapper-link">www.zapper.com</a></div></div>');
    
        $('<?php echo $parentContainer; ?>').<?php echo $containerPosition; ?>(placeHolder);

        var qrCode = new ZapperTech.QrCode({
                merchantId: <?php echo $merchantId ?>,
                siteId: <?php echo $siteId ?>,
                selector: placeHolder,
                baseUrl: "<?php echo $api_url?>"
        });
        
        qrCode.registrationRequest(function(data) {
                if(!allowRegistration) {
                    var error = '<?php echo $scan_to_login_language->getString('registration_error');?>';
                    alert(error);
                    qrCode.registrationRespond({
                            success: false,
                            errors: [error],
                            username: '',
                            password: ''
                    });

                } else {
                    var email = data.getAnswer(qrCode.QUESTIONTYPE.email);
                    var firstName = data.getAnswer(qrCode.QUESTIONTYPE.firstName);
                    var lastName = data.getAnswer(qrCode.QUESTIONTYPE.lastName);
                    var username = data.getAnswer(6);
                    var userpassword = data.getAnswer(7);
                    var phoneNumber = data.getAnswer(8);
                    var addressLine1 = data.getAnswer(12);
                    var addressLine2 = data.getAnswer(13);                    
                    var addressCity = data.getAnswer(14);
                    var addressZip = data.getAnswer(16);                    
                    var addressCountryIso = data.getAnswer(17);
                    
                    var password = data.Password; // auto generated
                
                    if (!username) {
                        username = email;
                    }
                    if (userpassword) {
                        password = userpassword;
                    }

		$.ajax({
                    url: baseUrl + '?zappertech=register',
                    type: 'POST',
                    data: {
                        email: email,                        
                        firstName: firstName,
                        lastName: lastName,
                        password: password,
                        username: username,
                        phoneNumber: (phoneNumber !== null ? phoneNumber : ''),
                        addressLine1: (addressLine1 !== null ? addressLine1 : ''),
                        addressLine2: (addressLine2 !== null ? addressLine2 : ''),
                        addressCity: (addressCity !== null ? addressCity : ''),
                        addressZip: (addressZip !== null ? addressZip : ''),
                        addressCountryIso: (addressCountryIso !== null ? addressCountryIso : '')
                    },
                dataType: 'json'
		}).done(function(result) {
                    var response = {
                        success: result.success,
                        errors: result.errors,
                        username: result.username,
                        password: result.password
                    };
                    qrCode.registrationRespond(response);
                    if(result.success === false) {
                        var alertMessage = '<?php echo $scan_to_login_language->getString('registration_failed');?>';
                        $(response.errors).each(function(i, error) {
                            alertMessage += "\n" + error + "\n";
                        });
                        alert(alertMessage);
                    } else {
                        login({
                            Username: result.username,
                            Password: result.password
                        });
                    }
                });
            }
	});
        
        var login = function(data) {
            $.ajax({
                url: baseUrl + '?zappertech=authenticate',
                type: 'POST',
                data: {
                        username: data.Username,
                        password: data.Password
                },
                dataType: 'json',
                complete: function(result){

                    var response = {
                        success: result.success,
                        errors: result.errors,
                        username: result.username,
                        password: result.password
                    };

                    qrCode.loginRespond(response, function() {

                        if (response.success) {
                            $.ajax({
                                url: baseUrl + '?zappertech=login',
                                type: 'POST',
                                data: {
                                        username: data.Username,
                                        password: data.Password
                                },
                                dataType: 'json',
                                complete: function(result){
                                    if(result.success){
                                        window.location.href = baseUrl + 'wp-admin/profile.php';
                                    }
                                }
                            });
                        }
                    });
                }
            });
        };
        qrCode.loginRequest(login);
        qrCode.start();
    });
})(jQuery);
<?php if(false) { ?></script><?php } ?>
<?php } else { 
//Please configure merchant and site ID.
}


