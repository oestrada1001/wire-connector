<?php

/**
 * Plugin Name: Wire Connector
 * Plugin URI: https://www.github.com/o.estrada1001
 * Description: Wire Connector allows you to create a affiliate/referral program with your Mailchimp account.
 * Version: 1.0.0
 * Author: Oscar Estrada
 * Author URI: https://www.oescar-estrada.com/
 * Text Domain: wire_connector
 */


use includes\WC_MailChimp\MailChimp as newMailChimp;

include plugin_dir_path( __FILE__) . 'WireConnectorAutoloader.php';

/*
 * WireConnector Class.
 *
 * WireConnector uses the Mailchimp Class in order to allow us to make API calls to Mailchimp. WireConnector takes care
 * of creating a unique page as well as unique forms to update the Mailchimp Database and also creates shortcodes
 * that we can use in our custom page.
 *
 */
class WireConnector extends WireConnectorShareable {

    protected $WireConnectorSettingOptions;
    protected $WireConnectorShortcodes;
    protected $mailchimp_api;


    /**
     * WireConnector constructor.
     *
     *  __construct initializes the wpMailChimpFramework and the newMailChimp classes in order to
     * be accessible from within the class.
     *
     */
    public function __construct()
    {
        wpMailChimpFramework::getInstance();
        $this->WireConnectorShortcodes = new WireConnectorShortcodes();
        $this->WireConnectorSettingOptions = new WireConnectorSettingOptions();
        $this->mailchimp_api = static::mailchimp_methods();

        add_action('admin_menu', array($this,'admin_menu_page'));
        add_filter('query_vars', array($this, 'add_query_variables'));
        register_activation_hook( __FILE__, array($this,'create_wire_connector_page'));
        add_action('admin_post_nopriv_submit-form', 'public_form_action'); // If the user in not logged in
        add_action('admin_post_submit-form', 'private_form_action'); // If the user is logged in
        add_filter('wp_kses_allowed_html', function ($allowedposttags, $context){
            if($context == 'post'){
                $allowedposttags['input']['value'] = 1;
            }
            return $allowedposttags;
        }, 10,2);
    }

    public function retrieve_client_link()
    {

        $clientPageID = $this->WireConnectorSettingOptions->getSetting('ClientPageID');
        return $pageLink = get_page_link($clientPageID);
    }


    private function retrieve_page_link()
    {
        $pageID = $this->WireConnectorSettingOptions->getSetting('PageID');
        $pageLink = get_page_link($pageID);

        return $pageLink;

    }

    /**
     *
     * Creates an Admin Page for the dashboard, adds javascript, and bootstrap.
     *
     * Goals: This is where the admin will be able to see their lists, new subscribers, and what subscribers have
     * referred  new users.
     *
     */
    public function admin_menu_page()
    {
        $wire_connector_page = add_menu_page('Wire Connector Plugin', 'Wire Connector', 'administrator', 'main_wc_page', array($this,'main_wc_page'), 'dashicons-editor-expand', 3);

        add_action( 'load-' . $wire_connector_page, array($this,'add_javascript'));
        add_action( 'load-' . $wire_connector_page, array($this,'add_bootstrap'));

    }



    /**
     * Creates the Custom Page and sets the page id on _settings
     *
     * Needs: Check to see if the page already exist before creating one.
     *
     */
    public function create_wire_connector_page($pageName)
    {

        $my_post = array(
            'post_title'    => wp_strip_all_tags($pageName),
            'post_content'  => '[goal number="2"][merge_field name="RLINK"][merge_field name="PLINK"]',
            'post_status'   => 'private',
            'post_author'   => 1,
            'post_type'     => 'page',
        );

        $page_id = wp_insert_post($my_post);

        return $page_id;
    }

    /**
     * Wrapper that is called from within functions.php
     *
     * Insert all the code that needs to be called after the subscription submit button here.
     */
    public function nopriv_function_wrapper()
    {
        $type = $_POST['type'];
        $email = sanitize_email($_POST['email']);

        $this->referred_action_steps($_POST);

        $this->regular_action_steps($_POST);

    }

    private function referred_action_steps($post)
    {
        if($post['type'] == 'referred'){

            $mailchimp_api = static::mailchimp_methods();

            $this->check_subscriber_referred_score($post['list'], $post['sub']);

            $refBy = $mailchimp_api->user_merge_field($post['list'], $post['sub'], 'USERID');

            $post['refBy'] = $refBy;

            $this->add_new_subscriber($post);

        }
    }

    private function regular_action_steps($post)
    {
        if($post['type'] == 'regular'){

            $post['refBy'] = 0;

            $this->add_new_subscriber($post);

        }
    }

    private function add_new_subscriber($postArray)
    {

        $mailchimp_api = static::mailchimp_methods();
        $memberUrl = $mailchimp_api->membersUrl($postArray['list']);

        $latestSubscriberHash = $this->retrieve_last_member_id($postArray['list'], $memberUrl);

        $latestID = $mailchimp_api->user_merge_field($postArray['list'], $latestSubscriberHash, 'USERID');

        $latestID++;

        $data = array(
            'email_address' => sanitize_email($postArray['email']),
            'status' => 'subscribed',
            'merge_fields' =>  array(
                'USERID' => $latestID,
                'REFD' => 0,
                'REFBY' => $postArray['refBy'],
                'AWARDED' => 0,
            )
        );


        $mailchimp_api->post($memberUrl, $data);

        $newMemberHash = $this->retrieve_last_member_id($postArray['list'], $memberUrl);

        $pageLink = $this->getPageUrl();
        $clientPageLink = $this->getPageUrl('ClientPageID');

        $pageLinks = array($pageLink, $clientPageLink);


        $this->create_and_apply_new_member_links($pageLinks, $latestID, $newMemberHash, $postArray['list']);

        $this->retrieve_redirect_client_link($newMemberHash, $latestID, $postArray['list']);
    }

    private function create_and_apply_new_member_links($pageLinks, $latestID, $newMemberHash, $listID)
    {
        $mailchimp_api = static::mailchimp_methods();
        $newSubscriberLinks = $this->mailchimp_api->createMemberLinks($pageLinks, $latestID, $newMemberHash, $listID);
        $memberSubscriberLink = $this->mailchimp_api->membersUrlWithHash($listID, $newMemberHash);

        $mailchimp_api->updateMemberMergeField($memberSubscriberLink, 'PLINK', $newSubscriberLinks['Profile Link']);
        $mailchimp_api->updateMemberMergeField($memberSubscriberLink, 'RLINK', $newSubscriberLinks['Referral Link']);

    }

    private function retrieve_last_member_id($list,$memberUrl)
    {
        $mailchimp_api = static::mailchimp_methods();
        $memberArray = $mailchimp_api->get($memberUrl);

        $oldMemberPosition = count($memberArray['members']) - 1;

        $latestSubscriberHash = $memberArray['members'][$oldMemberPosition]['id'];

        return $latestSubscriberHash;
    }

    private function check_subscriber_referred_score($list, $subscriberHash)
    {
        $mailchimp_api = static::mailchimp_methods();
        $subscriber_score = $mailchimp_api->user_merge_field($list, $subscriberHash, 'REFD');
        $subscriber_link = $mailchimp_api->membersUrlWithHash($list, $subscriberHash);

        $firstGoal = get_option('FirstGoal');
        $secondGoal = get_option('SecondGoal');
        $thirdGoal = get_option('ThirdGoal');
        $fourthGoal = get_option('FourthGoal');

        if($subscriber_score < $firstGoal){

            $this->refd_by_incrementor($subscriber_link, 'REFD', $subscriber_score, $firstGoal);

        }elseif($subscriber_score >= $firstGoal && $subscriber_score < $secondGoal ){

            $this->refd_by_incrementor($subscriber_link, 'REFD', $subscriber_score, $secondGoal);

        }elseif($subscriber_score >= $secondGoal && $subscriber_score < $thirdGoal){

            $this->refd_by_incrementor($subscriber_link, 'REFD', $subscriber_score, $thirdGoal);

        }elseif($subscriber_score >= $thirdGoal && $subscriber_score < $fourthGoal){

            $this->refd_by_incrementor($subscriber_link, 'REFD', $subscriber_score, $fourthGoal);

        }elseif($subscriber_score > $fourthGoal){

            $this->refd_by_incrementor($subscriber_link, 'REFD', $subscriber_score, $fourthGoal);

        }

    }

    private function refd_by_incrementor($subscriberLink, $merge_field_name, $subscriber_refd, $goalSet)
    {

        $mailchimp_api = static::mailchimp_methods();

        $subscriber_refd++;

        if($subscriber_refd == $goalSet){

            //$subscriberEmail = $mailchimp_api->retrieveMemberData($subscriberLink, 'email_address');

            //Insert Mail Notification
            try{
                /*$to = get_option('AdminEmail');
                $subject = 'A subscriber just reach a goal!';
                $body = "$subscriberEmail has reached the goal you set as $goalSet";
                $headers = array('Content-Type: text/html; charset=UTF-8');

                wp_mail( $to, $subject, $body, $headers );*/
            }catch(Exception $e){

            }

            echo 'Email Sent';
        }

        $mailchimp_api->updateMemberMergeField($subscriberLink, $merge_field_name, $subscriber_refd);

        return $subscriber_refd;
    }

    /**
     * Wrapper that is called from within functions.php
     *
     * Insert all the code that needs to be called after admin page POST/Get Submissions.
     *
     * Form Keys: createPage, createMergeFields, createSubscriberLinks
     */
    public function priv_function_wrapper()
    {

        $admin_url = admin_url('?page=main_wc_page');

        $data = $this->dynamic_post_goal_setter();

        $data = $this->create_new_pages($data);

        $old_page_id = get_option('PageID');

        $this->WireConnectorSettingOptions->updateSettings($data);

        $new_page_id = get_option('PageID');

        $this->update_page_merge_links($old_page_id, $new_page_id, $data);


        wp_redirect($admin_url);

    }

    private function create_new_pages($data)
    {

        if(!empty($data['ClientPageName']) && !empty($data['PageName'])){

            $clientPageID = $this->create_wire_connector_page($data['ClientPageName']);
            $page_id = $this->create_wire_connector_page($data['PageName']);


            $data['PageID'] = $page_id;
            $data['ClientPageID'] = $clientPageID;

            return $data;

        }else{

            unset($data['ListID']);
            unset($data['ListName']);

            return $data;
        }

    }

    private function update_page_merge_links($old_page_id, $new_page_id, $data)
    {
        if($old_page_id != $new_page_id){

            $pageLink = $this->retrieve_page_link();
            $pageNewSubLink = $this->retrieve_client_link();

            $pageLinks = array($pageLink, $pageNewSubLink);

            $mailchimp_api = static::mailchimp_methods();
            $mailchimp_api->initiateMergeFields($data['ListID']);
            $mailchimp_api->initiateUniqueIdentifiers($pageLinks, $data['ListID']);

        }
    }


    private function dynamic_post_goal_setter()
    {
        $data = array();

        foreach($_POST as $key => $value){

            if(empty($value)){
                unset($key);
                continue;
            }

            $pattern = '/^([A-Z0-9]*[a-z][a-z0-9]*[A-Z]|[a-z0-9]*[A-Z][A-Z0-9]*[a-z])[A-Za-z0-9]*/';

            if(preg_match($pattern, $key)){

                $key = ucfirst($key);

                if($key == 'ListID'){

                    $list = explode(',', $value);

                    $data['ListName'] = $list[0];
                    $data[$key] =  $list[1];

                    continue;

                }

                $data[$key] = $value;

            }

        }

        return $data;

    }

    private function retrieve_redirect_client_link($subID, $userID, $listID)
    {
        $pageLink = $this->retrieve_page_link();

        $clientRedirectPageLink = $pageLink.'?id='.$userID.'&list='.$listID.'&sub='.$subID;

        wp_redirect($clientRedirectPageLink);

    }


    public function add_javascript()
    {
        wp_enqueue_script( 'wc_wire_javascript', plugins_url('includes/js/bootstrap.min.js', __FILE__ ), array('jquery'), null, true );
    }

    public function add_bootstrap()
    {
        wp_enqueue_script( 'wc_wire_bootstrap', plugins_url('includes/css/bootstrap.min.css', __FILE__ ), array('bootstrap'), null, true);
    }

    /**
     * Returns an array with the parameters which will ultimately be passed to query_var to tell wordpress
     * which parameters to allow in GET.
     *
     * @return array
     */
    public function add_query_variables($parameters)
    {
        $parameters[] = "id";
        $parameters[] = "sub";
        $parameters[] = "list";

        return $parameters;
    }
    
    /**
     * Retrieves the MailChimp Class in order to allow us to use the MailChimp API within this class.
     *
     * @return newMailChimp|mixed|wpMailChimpFramework
     * @throws Exception
     */
    public static function mailchimp_methods()
    {
        $mailchimp_api = wpMailChimpFramework::getInstance();
        $mailchimp_api = $mailchimp_api->credential_getter();


        try {
            $mailchimp_api = new newMailChimp( $mailchimp_api['apiKey'], $mailchimp_api['url'] );

            return $mailchimp_api;
        }catch(Exception $e){
            wp_redirect(404);
        }

    }
    

    private function getPageUrl( $pageID = null )
    {
        if(is_null($pageID)){
            $page_id = get_option('PageID');

            $page_url = get_page_link($page_id);
        }

        if($pageID == 'ClientPageID'){
            $page_id = get_option('ClientPageID');

            $page_url = get_page_link($page_id);
        }

        return $page_url;
    }


    public function main_wc_page()
    {

        $admin_url = $this->admin_url();
        $mcFramework = wpMailChimpFramework::getInstance();
        $pageName = get_option('PageName');
        $listName = get_option('ListName');
        $clientPageName = get_option('ClientPageName');

        $mailchimp_api = static::mailchimp_methods();
        $listIDs = $mailchimp_api->getListID();

        $apiKey = empty($mcFramework->getSetting('apikey'));

        if($apiKey == 1){
            ?>
            <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
            <div class="wrap">
                <h1 class="text-center">Please Configure Mailchimp under the Setting Tab.</h1>
            </div>

            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

            <?php
            exit;
        }


        ?>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">

        <style>

        </style>
        <div class="wrap">
            <?php

            echo "<form method='post' action='$admin_url'>";
                settings_fields('wire_connector_pages');
                settings_fields('wire_connector_goals');
            echo "<input type='hidden' name='action' value='submit-form'>";
                do_settings_sections('main_wc_page');


                submit_button();

                echo "</form>";

             if(empty($pageName)){  ?>
                <h1 style="text-align:center;">Wire Connector</h1>
                <br>
                <p style="font-size:16px">This page will be used to create dynamic links for your subscribers.</p>
                <em style="font-size:14px"><b>User Profile Example:</b>www.yoursite.com/page-name/?id=X&list=XXXXX&sub=XXXXXXXXXXXXXX</em>
                <br>
                <em style="font-size:14px"><b>New Subscriber Example:</b>www.yoursite.com/page-name/?list=XXXXX&sub=XXXXXXXXXXXXXX</em>
                <br>
                <br>
                <form class="form-inline" action="<?php echo $admin_url; ?>" method="post">

                    <input type="hidden" name="formName" value="createPage">
                    <input type="hidden" name="action" value="submit-form">

                    <div class="form-group mb-2">
                        <label for="page">User Profile Page:</label>
                    </div>

                    <div class="form-group mx-sm-3 mb-2">
                        <input class="form-control" name="pageName" type="text">
                    </div>

                    <div class="form-group mb-2">
                        <label for="page">New Subscriber Page:</label>
                    </div>

                    <div class="form-group mx-sm-3 mb-2">
                        <input class="form-control" name="clientPageName" type="text">
                    </div>

                    <div class="form-group mb-2">
                        <button id="pageName" class="btn btn-success">Create Pages</button>
                    </div>
                </form>
            <?php }elseif(empty($listName)){ ?>

                <form action="<?php echo $admin_url; ?>" method="post" class="col-5">
                    <div class="form-group">
                        <h1 style="text-align:center;">Wire Connector</h1>
                        <p style="text-align:center;">Next, select the list you want to apply Wire Connector to.</p>
                    </div>

                    <input type="hidden" name="formName" value="startWireConnector">
                    <input type="hidden" name="action" value="submit-form">

                    <div class="form-group">
                        <label for="listID">Select List</label>
                        <select name="listID" class="custom-select" required>
                            <option value="">Choose Option</option>
                            <?php foreach($listIDs as $listArray => $listName){
                                foreach($listName as $list => $listKey){ ?>
                                    <option value="<?php echo $listArray.','.$listKey; ?>"><?php echo $listArray; ?></option>
                                <?php   }
                            } ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="totalSet">Set Referral Goal</label>
                        <input type="number" class="form-control" name="totalSet">
                    </div>

                    <div class="form-group">
                        <lable for="prizeSet">Set the Prize</lable>
                        <input type="text" class="form-control" name="prizeSet">
                    </div>

                    <button class="btn btn-success" value="submit">Start Wire Connector</button>
                </form>
            <?php }else{ ?>

                <h1 class="text-center"><?php echo $listName ?> - <?php echo $pageName; ?></h1>

                <!--<form action="<?php echo $admin_url; ?>" method="post">
                <input type="hidden" name="action" value="submit-form">
                <input type="hidden" name="formName" value="resetMergeFields">
                <div class="form-group">
                    <button class="btn btn-danger">Reset Merge Fields</button>
                </div>

            </form>

            <form action="<?php echo $admin_url; ?>" method="post">
                <input type="hidden" name="action" value="submit-form">
                <input type="hidden" name="formName" value="deleteMergeFields">
                <div class="form-group">
                    <button class="btn btn-danger">Delete Merge Fields</button>
                </div>
            </form>-->

                <h3>Wire Connector has been successfully setup.</h3>

            <?php

            }

            /*if(is_null(get_option('first_goal'))){*/

              /*  settings_errors();
                echo "<form method='post' action='options.php'>";
                settings_fields('wire_connector_goals');

                do_settings_sections('main_wc_page');

                submit_button();

                echo "</form>";*/

            /*}*/

            ?>

        </div>

        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

        <script>

        </script>
        <?php
    }



}

try{
    $wc = new WireConnector();
}catch(Exception $e){
    print_r($e);
}