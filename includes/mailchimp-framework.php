<?php
/**
 * Plugin Name: MailChimp Framework
 * Plugin URI: http://xavisys.com/2009/09/wordpress-mailchimp-framework/
 * Description: MailChimp integration framework and admin interface as well as WebHooks listener.  Requires PHP5.
 * Version: 1.0.0
 * Author: Aaron D. Campbell
 * Author URI: http://xavisys.com/
 * Text Domain: mailchimp-framework
 */

/*  Copyright 2009  Aaron D. Campbell  (email : wp_plugins@xavisys.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * wpMailChimpFramework is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class wpMailChimpFramework
{
    /**
     * @access private
     * @var array Plugin settings
     */
    private $_settings;

    /**
     * @access private
     * @var array Errors
     */
    private $_errors = array();

    /**
     * @access private
     * @var array Notices
     */
    private $_notices = array();

    /**
     * Static property to hold our singleton instance
     * @var wpMailChimpFramework
     */
    static $instance = false;

    /**
     * @access private
     * @var string Name used for options
     */
    private $_optionsName = 'mailchimp-framework';

    /**
     * @access private
     * @var string Name used for options
     */
    private $_optionsGroup = 'mailchimp-framework-options';

    /**
     * @access private
     * @var string API Url
     */
    private $_url = "https://us19.api.mailchimp.com/3.0/";

    /**
     * @access private
     * @var string Query var for listener to watch for
     */
    private $_listener_query_var	= 'mailchimpListener';

    /**
     * Approved MailChimp plugins get a plugin_id, which is needed to use
     * the campaignEcommAddOrder API Method
     *
     * @access private
     * @var string MailChimp Plugin ID
     */
    private $_plugin_id = '1225';

    /**
     * @access private
     * @var int Timeout for server calls
     */
    private $_timeout = 30;

    /**
     * This is our constructor, which is private to force the use of
     * getInstance() to make this a Singleton
     *
     * @return wpMailChimpFramework
     */
    private function __construct() {
        $this->_getSettings();
        $this->_fixDebugEmails();

        // Get the datacenter from the API key
        $datacenter = substr( strrchr($this->_settings['apikey'], '-'), 1 );
        if ( empty( $datacenter ) ) {
            $datacenter = "us19";
        }
        // Put the datacenter and version into the url
        $this->_url = "https://{$datacenter}.api.mailchimp.com/{$this->_settings['version']}/";

        /**
         * Add filters and actions
         */
        add_filter( 'init', array( $this, 'init_locale') );
        add_action( 'admin_init', array($this,'registerOptions') );
        add_action( 'admin_menu', array($this,'adminMenu') );
        add_action( 'template_redirect', array( $this, 'listener' ));
        add_filter( 'query_vars', array( $this, 'addMailChimpListenerVar' ));
        register_activation_hook( __FILE__, array( $this, 'activatePlugin' ) );
        register_deactivation_hook( __FILE__, array($this,'deactivatePlugin'));
        add_filter( 'pre_update_option_' . $this->_optionsName, array( $this, 'optionUpdate' ), null, 2 );
        add_action( 'admin_notices', array($this, 'showMessages'));

    }

    public static function wireConnector($wire_connector){
        if($wire_connector == 'WireConnector'){
            include plugin_dir_path( __DIR__ ) . 'wire-connector/WireConnector.php';

            /**
             *Wire Connector Class Instanciation
             */

            $this->wire_connector = new WireConnector();

        }
    }

    public function showMessages() {
        $this->showErrors();
        $this->showNotices();
    }
    public function showErrors() {
        $this->_getErrors();
        if ( !empty($this->_errors) ) {
            echo '<div class="error fade">';
            foreach ($this->_errors as $e) {
                echo "<p><strong>{$e->error}</strong> ({$e->code})</p>";
            }
            echo '</div>';
        }
        $this->_emptyErrors();
    }

    public function showNotices() {
        $this->_getNotices();
        if ( !empty($this->_notices) ) {
            echo '<div class="updated fade">';
            foreach ($this->_notices as $n) {
                echo "<p><strong>{$n}</strong></p>";
            }
            echo '</div>';
        }
        $this->_emptyNotices();
    }

    public function optionUpdate( $newvalue, $oldvalue ) {
        if ( !empty( $_POST['get-apikey'] ) ) {
            unset( $_POST['get-apikey'] );

            // If the user set their username at the same time as they requested an API key
            if ( empty($this->_settings['username']) ) {
                $this->_settings['username'] = $newvalue['username'];
            }

            // If the user set their password at the same time as they requested an API key
            if ( empty($this->_settings['password']) ) {
                $this->_settings['password'] = $newvalue['password'];
            }

            // Get API keys, if one doesn't exist, the login will create one
            $keys = $this->apikeys();

            // Set the API key
            if ( !empty( $keys ) ) {
                $newvalue['apikey'] = $keys[0]->apikey;
                $this->_addNotice("API Key Added: {$newvalue['apikey']}");
            }
        } elseif ( !empty( $_POST['expire-apikey'] ) ) {
            unset( $_POST['expire-apikey'] );

            // If the user set their username at the same time as they requested to expire the API key
            if ( empty($this->_settings['username']) ) {
                $this->_settings['username'] = $newvalue['username'];
            }

            // If the user set their password at the same time as they requested to expire the API key
            if ( empty($this->_settings['password']) ) {
                $this->_settings['password'] = $newvalue['password'];
            }

            // Get API keys, if one doesn't exist, the login will create one
            $expired = $this->apikeyExpire();

            // Empty the API key and add a notice
            if ( empty($expired['error']) ) {
                $newvalue['apikey'] = '';
                $this->_addNotice("API Key Expired: {$oldvalue['apikey']}");
            }
        } elseif ( !empty( $_POST['regenerate-security-key']) ) {
            unset( $_POST['expire-apikey'] );

            $newvalue['listener_security_key'] = $this->_generateSecurityKey();
            $this->_addNotice("New Security Key: {$newvalue['listener_security_key']}");
        }

        if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
            wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - optionUpdate', "New Value:\r\n".print_r($newvalue, true)."\r\n\r\nOld Value:\r\n".print_r($oldvalue, true)."\r\n\r\nPOST:\r\n".print_r($_POST, true));
        }
        return $newvalue;
    }

    /**
     * If an instance exists, this returns it.  If not, it creates one and
     * retuns it.
     *
     * @return wpMailChimpFramework
     */
    public static function getInstance() {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function activatePlugin($data = null) {

        if(isset($data['ListID']) && isset($data['ListName'])){
            $this->_settings['ListID'] = $data['ListID'];
            $this->_settings['ListName'] = $data['ListName'];
            $this->_settings['Total'] = $data['Total'];
        }elseif(isset($data['PageID']) && isset($data['PageName'])){
            $this->_settings['PageID'] = $data['PageID'];
            $this->_settings['PageName'] = $data['PageName'];
            $this->_settings['ClientPageID'] = $data['ClientPageID'];
            $this->_settings['ClientPageName'] = $data['ClientPageName'];
        }


        $this->_updateSettings();
    }

    private function _getSettings() {
        if (empty($this->_settings)) {
            $this->_settings = get_option( $this->_optionsName );
        }
        if ( !is_array( $this->_settings ) ) {
            $this->_settings = array();
        }
        $defaults = array(
            'username'				=> '',
            'password'				=> '',
            'apikey'				=> '',
            'debugging'				=> 'on',
            'debugging_email'		=> '',
            'listener_security_key'	=> $this->_generateSecurityKey(),
            'version'				=> '3.0',
        );
        $this->_settings = wp_parse_args($this->_settings, $defaults);
    }

    private function _generateSecurityKey() {
        return sha1(time());
    }

    private function _updateSettings() {
        update_option( $this->_optionsName, $this->_settings );
    }

    public function getSetting( $settingName, $default = false ) {
        if ( empty( $this->_settings ) ) {
            $this->_getSettings();
        }
        if ( isset( $this->_settings[$settingName] ) ) {
            return $this->_settings[$settingName];
        } else {
            return $default;
        }
    }

    public function registerOptions() {
        register_setting( $this->_optionsGroup, $this->_optionsName );
    }

    public function adminMenu() {
        add_options_page(__('MailChimp Settings', 'mailchimp-framework'), __('MailChimp', 'mailchimp-framework'), 'manage_options', 'MailChimpFramework', array($this, 'options'));
    }

    /**
     * This is used to display the options page for this plugin
     */
    public function options() {
        ?>
        <style type="text/css">
            #wp_mailchimp_framework table tr th a {
                cursor:help;
            }
            .large-text{width:99%;}
            .regular-text{width:25em;}
        </style>
        <div class="wrap">
            <h2><?php _e('MailChimp Options', 'mailchimp-framework') ?></h2>
            <form action="options.php" method="post" id="wp_mailchimp_framework">
                <?php settings_fields( $this->_optionsGroup ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_username">
                                <?php _e('MailChimp Username', 'mailchimp-framework'); ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_username').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[username]" value="<?php echo esc_attr($this->_settings['username']); ?>" id="<?php echo $this->_optionsName; ?>_username" class="regular-text code" />
                            <ol id="mc_username" style="display:none; list-style-type:decimal;">
                                <li>
                                    <?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
                                </li>
                            </ol>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_password">
                                <?php _e('MailChimp Password', 'mailchimp-framework') ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_password').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="password" name="<?php echo $this->_optionsName; ?>[password]" value="<?php echo esc_attr($this->_settings['password']); ?>" id="<?php echo $this->_optionsName; ?>_password" class="regular-text code" />
                            <ol id="mc_password" style="display:none; list-style-type:decimal;">
                                <li>
                                    <?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
                                </li>
                            </ol>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_apikey">
                                <?php _e('MailChimp API Key', 'mailchimp-framework') ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_apikey').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[apikey]" value="<?php echo esc_attr($this->_settings['apikey']); ?>" id="<?php echo $this->_optionsName; ?>_apikey" class="regular-text code" />
                            <?php if ( empty($this->_settings['apikey']) ) {
                                ?>
                                <input type="submit" name="get-apikey" value="<?php _e('Get API Key', 'mailchimp-framework'); ?>" />
                                <?php
                            } else {
                                ?>
                                <input type="submit" name="expire-apikey" value="<?php _e('Expire API Key', 'mailchimp-framework'); ?>" />
                                <?php
                            }
                            ?>
                            <ol id="mc_apikey" style="display:none; list-style-type:decimal;">
                                <li>
                                    <?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
                                </li>
                            </ol>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_version">
                                <?php _e('MailChimp API version', 'mailchimp-framework') ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_version').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[version]" value="<?php echo esc_attr($this->_settings['version']); ?>" id="<?php echo $this->_optionsName; ?>_version" class="small-text" />
                            <small id="mc_version" style="display:none;">
                                This is the default version to use if one isn't
                                specified.
                            </small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Debugging Mode', 'mailchimp-framework') ?>
                            <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_debugging').toggle(); return false;">
                                <?php _e('[?]', 'mailchimp-framework'); ?>
                            </a>
                        </th>
                        <td>
                            <input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="on" id="<?php echo $this->_optionsName; ?>_debugging-on"<?php checked('on', $this->_settings['debugging']); ?> />
                            <label for="<?php echo $this->_optionsName; ?>_debugging-on"><?php _e('On', 'mailchimp-framework'); ?></label><br />
                            <input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="webhooks" id="<?php echo $this->_optionsName; ?>_debugging-webhooks"<?php checked('webhooks', $this->_settings['debugging']); ?> />
                            <label for="<?php echo $this->_optionsName; ?>_debugging-webhooks"><?php _e('Partial - Only WebHook Messages', 'mailchimp-framework'); ?></label><br />
                            <input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="off" id="<?php echo $this->_optionsName; ?>_debugging-off"<?php checked('off', $this->_settings['debugging']); ?> />
                            <label for="<?php echo $this->_optionsName; ?>_debugging-off"><?php _e('Off', 'mailchimp-framework'); ?></label><br />
                            <small id="mc_debugging" style="display:none;">
                                <?php _e('If this is on, debugging messages will be sent to the E-Mail addresses set below.', 'mailchimp-framework'); ?>
                            </small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_debugging_email">
                                <?php _e('Debugging E-Mail', 'mailchimp-framework') ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_debugging_email').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[debugging_email]" value="<?php echo esc_attr($this->_settings['debugging_email']); ?>" id="<?php echo $this->_optionsName; ?>_debugging_email" class="regular-text" />
                            <small id="mc_debugging_email" style="display:none;">
                                <?php _e('This is a comma separated list of E-Mail addresses that will receive the debug messages.', 'mailchimp-framework'); ?>
                            </small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $this->_optionsName; ?>_listener_security_key">
                                <?php _e('MailChimp WebHook Listener Security Key', 'mailchimp-framework'); ?>
                                <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_listener_security_key').toggle(); return false;">
                                    <?php _e('[?]', 'mailchimp-framework'); ?>
                                </a>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[listener_security_key]" value="<?php echo esc_attr($this->_settings['listener_security_key']); ?>" id="<?php echo $this->_optionsName; ?>_listener_security_key" class="regular-text code" />
                            <input type="submit" name="regenerate-security-key" value="<?php _e('Regenerate Security Key', 'mailchimp-framework'); ?>" />
                            <div id="mc_listener_security_key" style="display:none; list-style-type:decimal;">
                                <p><?php echo _e('This is used to make the listener a little more secure. Usually the key that was randomly generated for you is fine, but you can make this whatever you want.', 'mailchimp-framework'); ?></p>
                                <p class="error"><?php echo _e('Warning: Changing this will change your WebHook Listener URL below and you will need to update it in your MailChimp account!', 'mailchimp-framework'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('MailChimp WebHook Listener URL', 'mailchimp-framework') ?>
                            <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_listener_url').toggle(); return false;">
                                <?php _e('[?]', 'mailchimp-framework'); ?>
                            </a>
                        </th>
                        <td>
                            <?php echo $this->_getListenerUrl(); ?>
                            <div id="mc_listener_url" style="display:none;">
                                <p><?php _e('To set this in your MailChimp account:', 'mailchimp-framework'); ?></p>
                                <ol style="list-style-type:decimal;">
                                    <li>
                                        <?php echo sprintf(__('<a href="%s">Log into your MailChimp account</a>', 'mailchimp-framework'), 'https://admin.mailchimp.com/'); ?>
                                    </li>
                                    <li>
                                        <?php _e('Navigate to your <strong>Lists</strong>', 'mailchimp-framework'); ?>
                                    </li>
                                    <li>
                                        <?php _e("Click the <strong>View Lists</strong> button on the list you want to configure.", 'mailchimp-framework'); ?>
                                    </li>
                                    <li>
                                        <?php _e('Click the <strong>List Tools</strong> menu option at the top.', 'mailchimp-framework'); ?>
                                    </li>
                                    <li>
                                        <?php _e('Click the <strong>WebHooks</strong> link.', 'mailchimp-framework'); ?>
                                    </li>
                                    <li>
                                        <?php echo sprintf(__("Configuration should be pretty straight forward. Copy/Paste the URL shown above into the callback URL field, then select the events and event sources (see the <a href='%s'>MailChimp documentation for more information on events and event sources) you'd like to have sent to you.", 'mailchimp-framework'), 'http://www.mailchimp.com/api/webhooks/'); ?>
                                    </li>
                                    <li>
                                        <?php _e("Click save and you're done!", 'mailchimp-framework'); ?>
                                    </li>
                                </ol>
                            </div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Current MailChimp Status', 'mailchimp-framework') ?>
                            <a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_status').toggle(); return false;">
                                <?php _e('[?]', 'mailchimp-framework'); ?>
                            </a>
                        </th>
                        <td>
                            <?php echo $this->ping(); ?>
                            <p id="mc_status" style="display:none;"><?php _e("The current status of your server's connection to MailChimp", 'mailchimp-framework'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="Submit" value="<?php _e('Update Options &raquo;', 'mailchimp-framework'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    private function _getListenerUrl() {
        return get_bloginfo('url').'/?'.$this->_listener_query_var.'='.urlencode($this->_settings['listener_security_key']);
    }

    /**
     * This function creates a name value pair (nvp) string from a given array,
     * object, or string.  It also makes sure that all "names" in the nvp are
     * all caps (which PayPal requires) and that anything that's not specified
     * uses the defaults
     *
     * @param array|object|string $req Request to format
     *
     * @return string NVP string
     */
    private function _prepRequest($req) {
        $defaults = array();
        $req = wp_parse_args( $req );

        //Always include the apikey if we are not logging in
        if ( $req['method'] != 'login' ) {
            if ( !empty($this->_settings['apikey']) ) {
                $defaults['apikey'] = $this->_settings['apikey'];
            } else {
                $defaults['apikey'] = $this->login();
            }
        }
        unset($req['method']);

        if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
            wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - _prepRequest', "Request:\r\n".print_r($req, true)."\r\n\r\nDefaults:\r\n".print_r($defaults, true)."\r\n\r\nEnd Args:\r\n".print_r(wp_parse_args( $req, $defaults ), true));
        }

        return wp_parse_args( $req, $defaults );
    }

    /**
     * Set the timeout.  The default is 30 seconds
     *
     * @param int $seconds - Timeout in seconds
     *
     * @return bool
     */
    public function setTimeout($seconds){
        $this->_timeout = absint($seconds);
        return true;
    }

    /**
     * Get the current timeout.  The default is 30 seconds
     *
     * @return int - Timeout in seconds
     */
    public function getTimeout(){
        return $this->timeout;
    }

    /**
     * callServer: Function to perform the API call to MailChimp
     * @param string|array $args Parameters needed for call
     *
     * @return array On success return associtive array containing the response from the server.
     */


    public function callServer( $args ) {


        /*$_url = "{$this->_url}{$args['method']}";
        $resp = wp_remote_post( $_url, $reqParams );*/

        $httpHeader = array(
            'Accept: application/xml',
            'Content-Type: application/xml',
            'Authorization: apikey ' . $this->_settings['apikey']
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);

        $responseContent     = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);

        if($response['headers']['http_code'] == 200){
            $resp = 'Everything\'s Chimpy!';

            return $resp;
        }else{

            $resp = "Oops! Something went wrong!";

            return $resp;
        }


        /*		// If the response was valid, decode it and return it.  Otherwise return a WP_Error
                if ( !is_error($resp) && $resp['headers']['http_code'] >= 200 && $resp['headers']['http_code'] < 300 ) {
                    if (function_exists('json_decode')) {
                        $decodedResponse = json_decode( $resp['body'] );
                    } else {
                        global $wp_json;

                        if ( !is_a($wp_json, 'Services_JSON') ) {
                            require_once( 'class-json.php' );
                            $wp_json = new Services_JSON();
                        }

                        $decodedResponse =  $wp_json->decode( $resp['body'] );
                    }
                    // Used for debugging.
                    if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
                        $request = $this->_sanitizeRequest($reqParams['body']);
                        wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - serverCall sent successfully', "Request to {$_url}:\r\n".print_r($request, true)."\r\n\r\nResponse:\r\n".print_r($resp['body'], true)."\r\n\r\nDecoded Response:\r\n".print_r(wp_parse_args($decodedResponse), true));
                    }
                    //$decodedResponse = wp_parse_args($decodedResponse);
                    if ( !empty($decodedResponse->error) ) {
                        $this->_addError($decodedResponse);
                    }

                    return $decodedResponse;
                } else {
                    if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
                        $request = $this->_sanitizeRequest($reqParams['body']);
                        wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - serverCall failed', "Request to {$_url}:\r\n".print_r($request, true)."\r\n\r\nResponse:\r\n".print_r($resp, true));
                    }
                    if ( !is_wp_error($resp) ) {
                        $resp = new WP_Error('http_request_failed', $resp['response']['message'], $resp['response']);
                    }
                    return $resp;
                }*/
    }

    /**
     * Retrieve a set of errors that have occured as a multi-dimensional array.
     *
     * @return array Errors, each with an 'error' and 'code'
     */
    public function getErrors() {
        if ( empty($this->_errors) ) {
            $this->_getErrors();
        }
        return $this->_errors;
    }

    private function _getErrors() {
        $this->_errors = get_option( $this->_optionsName . '-errors', array() );
    }

    private function _addError($error) {
        if ( empty($this->_errors) ) {
            $this->_getErrors();
        }
        $this->_errors[] = $error;
        $this->_setErrors();
    }

    private function _emptyErrors() {
        $this->_errors = array();
        $this->_setErrors();
    }

    private function _setErrors() {
        update_option( $this->_optionsName . '-errors', $this->_errors );
    }

    /**
     * Retrieve a set of notices that have occured.
     *
     * @return array Notices
     */
    public function getNotices() {
        if ( empty($this->_notices) ) {
            $this->_getNotices();
        }
        return $this->_notices;
    }

    private function _getNotices() {
        $this->_notices = get_option( $this->_optionsName . '-notices', array() );
    }

    private function _addNotice($notice) {
        if ( empty($this->_notices) ) {
            $this->_getNotices();
        }
        $this->_notices[] = $notice;
        $this->_setNotices();
    }

    private function _emptyNotices() {
        $this->_notices = array();
        $this->_setNotices();
    }

    private function _setNotices() {
        update_option( $this->_optionsName . '-notices', $this->_notices );
    }

    private function _sanitizeRequest($request) {
        /**
         * Hide sensitive data in the debug E-Mails we send
         */
        //$request['ACCT']	= str_repeat('*', strlen($request['ACCT'])-4) . substr($request['ACCT'], -4);
        //$request['EXPDATE']	= str_repeat('*', strlen($request['EXPDATE']));
        //$request['CVV2']	= str_repeat('*', strlen($request['CVV2']));
        return $request;
    }

    /**
     * This is our listener.  If the proper query var is set correctly it will
     * attempt to handle the response.
     */
    public function listener() {
        // Check that the query var is set and is the correct value.
        if (get_query_var( $this->_listener_query_var ) == $this->_settings['listener_security_key']) {
            $_POST = stripslashes_deep($_POST);
            $this->_processMessage();
            // Stop WordPress entirely
            exit;
        }
    }

    public function _fixDebugEmails() {
        $this->_settings['debugging_email'] = preg_split('/\s*,\s*/', $this->_settings['debugging_email']);
        $this->_settings['debugging_email'] = array_filter($this->_settings['debugging_email'], 'is_email');
        $this->_settings['debugging_email'] = implode(',', $this->_settings['debugging_email']);
    }

    /**
     * Add our query var to the list of query vars
     */
    public function addMailChimpListenerVar($public_query_vars) {
        $public_query_vars[] = $this->_listener_query_var;
        return $public_query_vars;
    }

    /**
     * Throw an action based off the transaction type of the message
     */
    private function _processMessage() {
        do_action("mailchimp-webhook", $_POST);
        if ( !empty($_POST['type']) ) {
            $specificAction = " and mailchimp-webhook-{$_POST['type']}";
            do_action("mailchimp-webhook-{$_POST['type']}", $_POST);
        }

        // Used for debugging.
        if ( ( $this->_settings['debugging'] == 'on' || $this->_settings['debugging'] == 'webhooks' ) && !empty($this->_settings['debugging_email']) ) {
            wp_mail($this->_settings['debugging_email'], 'MailChimp WebHook Listener Test - _processMessage()', "Actions thrown: mailchimp-webhook{$specificAction}\r\n\r\nPassed to action:\r\n".print_r($_POST, true));
        }
    }

    /**
     * Add an API Key to your account. Mailchimp will generate a new key for you
     * it will be returned.  If one is not currently set in the the settings,
     * the new one will be saved there.  A username and password must be set.
     *
     * @param string|array $args Parameters needed for call such as:
     * 			username [optional] - MailChimp Username, setting used by default
     * 			password [optional] - MailChimp Password, setting used by default
     *
     * @return string a new API Key that can be immediately used.
     */
    public function apikeyAdd( $args = null ) {
        $defaults = array(
            'username'	=> $this->_settings['username'],
            'password'	=> $this->_settings['password']
        );
        $args = wp_parse_args( $args, $defaults);
        $args['method'] = 'apikeyAdd';

        $resp = $this->callServer( $args );

        return $resp;
    }

    /**
     * Retrieve a list of all MailChimp API Keys for this User
     *
     * @param string|array $args Parameters needed for call such as:
     * 		string username [optional] - MailChimp Username, setting used by default
     * 		string password [optional] - MailChimp Password, setting used by default
     * 		bool expired [optional] - whether or not to include expired keys, defaults to false
     *
     * @return array an array of API keys including:
     * 		string apikey The api key that can be used
     * 		string created_at The date the key was created
     * 		string expired_at The date the key was expired
     */
    public function apikeys( $args = null ) {
        $defaults = array(
            'username'	=> $this->_settings['username'],
            'password'	=> $this->_settings['password'],
            'expired'	=> false
        );
        $args = wp_parse_args( $args, $defaults);
        $args['method'] = 'apikeys';

        return $this->callServer( $args );
    }

    /**
     * Expire a Specific API Key. Note that if you expire all of your keys, a new, valid one will be created and returned
     * next time you call login(). If you are trying to shut off access to your account for an old developer, change your
     * MailChimp password, then expire all of the keys they had access to. Note that this takes effect immediately, so make
     * sure you replace the keys in any working application before expiring them! Consider yourself warned...
     *
     * @param string|array $args Parameters needed for call such as:
     * 		string username [optional] - MailChimp Username, setting used by default
     * 		string password [optional] - MailChimp Password, setting used by default
     * 		string apikey [optional] - If no apikey is specified, the currently specified one is expired
     *
     * @return bool true if it worked, otherwise an error is thrown.
     */
    public function apikeyExpire( $args = null ) {
        $defaults = array(
            'username'	=> $this->_settings['username'],
            'password'	=> $this->_settings['password'],
        );
        $args = wp_parse_args( $args, $defaults);
        $args['method'] = 'apikeyExpire';

        return $this->callServer( $args );
    }

    /**
     * Add an API Key to your account. Mailchimp will generate a new key for you
     * it will be returned.  If one is not currently set in the the settings,
     * the new one will be saved there.  A username and password must be set.
     *
     * @param string|array $args Parameters needed for call
     *
     * @return string a new API Key that can be immediately used.
     */
    public function login( $args = null ) {
        $defaults = array(
            'username'	=> $this->_settings['username'],
            'password'	=> $this->_settings['password']
        );
        $args = wp_parse_args( $args, $defaults);
        $args['method'] = 'login';

        $resp = $this->callServer( $args );

        return $resp;
    }

    /**
     * "Ping" the MailChimp API - a simple method you can call that will return a constant value as long as everything is good. Note
     * than unlike most all of our methods, we don't throw an Exception if we are having issues. You will simply receive a different
     * string back that will explain our view on what is going on.
     *
     * @return string returns "Everything's Chimpy!" if everything is chimpy, otherwise returns an error message
     */
    public function ping() {
        $args = array();
        $args['method'] = 'ping';
        return $this->callServer( $args );
    }

    /**
     * Have HTML content auto-converted to a text-only format. You can send: plain HTML, an array of Template content, an existing Campaign Id, or an existing Template Id. Note that this will <b>not</b> save anything to or update any of your lists, campaigns, or templates.
     *
     * @param string|array $args Parameters needed for call such as:
     * 		string	type - The type of content to parse. Must be one of: "html", "template", "url", "cid" (Campaign Id), or "tid" (Template Id)
     * 		mixed	content - The content to use. For "html" expects  a single string value, "template" expects an array like you send to campaignCreate, "url" expects a valid & public URL to pull from, "cid" expects a valid Campaign Id, and "tid" expects a valid Template Id on your account.
     *
     * @return string the content pass in converted to text.
     */
    public function generateText( $args = null ) {
        $args = wp_parse_args( $args );
        $args['method'] = 'generateText';

        return $this->callServer( $args );
    }

    /**
     * Retrieve lots of account information including payments made, plan info, some account stats, installed modules,
     * contact info, and more. No private information like Credit Card numbers is available.
     *
     * @section Helper
     *
     * @return array containing the details for the account tied to this API Key
     * 		string		username The Account username
     * 		string		user_id The Account user unique id (for building some links)
     * 		bool		is_trial Whether the Account is in Trial mode (can only send campaigns to less than 100 emails)
     * 		string		timezone The timezone for the Account - default is "US/Eastern"
     * 		string		plan_type Plan Type - "monthly", "payasyougo", or "free"
     * 		int			plan_low <em>only for Monthly plans</em> - the lower tier for list size
     * 		int			plan_high <em>only for Monthly plans</em> - the upper tier for list size
     * 		datetime	plan_start_date <em>only for Monthly plans</em> - the start date for a monthly plan
     * 		int			emails_left <em>only for Free and Pay-as-you-go plans</em> emails credits left for the account
     * 		bool		pending_monthly Whether the account is finishing Pay As You Go credits before switching to a Monthly plan
     * 		datetime	first_payment date of first payment
     * 		datetime	last_payment date of most recent payment
     * 		int			times_logged_in total number of times the account has been logged into via the web
     * 		datetime	last_login date/time of last login via the web
     * 		string		affiliate_link Monkey Rewards link for our Affiliate program
     * 		array		contact Contact details for the account, including: First & Last name, email, company name, address, phone, and url
     * 		array		addons Addons installed in the account and the date they were installed.
     * 		array		orders Order details for the account, include order_id, type, cost, date/time, and any credits applied to the order
     */
    public function getAccountDetails() {
        $args = array();
        $args['method'] = 'getAccountDetails';

        return $this->callServer( $args );
    }

    public function init_locale() {
        $lang_dir = basename(dirname(__FILE__)) . '/languages';
        load_plugin_textdomain('mailchimp-framework', 'wp-content/plugins/' . $lang_dir, $lang_dir);
    }

    public function admin_menu_page(){
        $wire_connector_page = add_menu_page('Wire Connector Plugin', 'Wire Connector', 'administrator', 'mc_wire_connector', 'wc_main_page', 'dashicons-editor-expand', 3);

    }

    public function credential_getter(){

        $credentials['apiKey'] = $this->_settings['apikey'];
        $credentials['url'] = $this->_url;

        return $credentials;

    }

    public function deactivatePlugin()
    {
        unset($this->_settings['apikey']);
        unset($this->_settings['ListID']);
        unset($this->_settings['ListName']);
        unset($this->_settings['PageID']);
        unset($this->_settings['PageName']);
        unset($this->_settings['Total']);

        $this->_updateSettings();
    }

}

// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();
