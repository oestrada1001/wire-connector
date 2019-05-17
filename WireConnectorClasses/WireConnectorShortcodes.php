<?php

class WireConnectorShortcodes extends WireConnectorShareable {

    
    public function __construct()
    {

        //subscription_form
        //merge_field takes the merge field name as a parameter
        //Example [merge_field name='REFD']
        add_shortcode('subscription_form', array($this,'subscription_form_shortcode'));
        add_shortcode('subscriber_default_details', array($this,'subscriber_default_shortcode'));
        add_shortcode('subscriber_percentage', array($this,'subscriber_percentage_shortcode'));
        add_shortcode('subscriber_difference', array($this,'subscriber_difference_shortcode'));
        add_shortcode('subscriber_email', array($this,'subscriber_email_shortcode'));
        add_shortcode('merge_field', array($this,'merge_field_shortcode'));
        add_shortcode('goal', array($this, 'goal_shortcode'));
    }

    public function verify_subscriber($id, $list, $sub)
    {
        if(!current_user_can('edit_pages')){

            $WireConnector = new WireConnector();

            $mailchimp_api = WireConnector::mailchimp_methods();
            $list_verification = $mailchimp_api->get('lists/'.$list);

            $userExist = $mailchimp_api->get('lists/'.$list.'/members/'.$sub);

            if($list_verification['id'] != $list || $userExist['status'] == 404){

                $newSubscriber = $WireConnector->retrieve_client_link();

                $this->wrong_area_redirect($newSubscriber);

                die();

                /*$referred_subscriber_link = $WireConnector->retrieve_client_link();
                $referred_subscriber_link = $referred_subscriber_link.'?list='.$list.'&sub='.$sub;
                $this->wrong_area_redirect($referred_subscriber_link);*/
            }

        }

    }

    private function wrong_area_redirect($redirectLink)
    {
        ?>

        <div class="jumbotron">
            <h1 class="display-4">Opps...Wrong Area</h1>
            <p class="lead">In order to access this area you must first become a subscriber, but do not worry its easy! Just click on the button below!</p>
            <hr class="my-4">
            <p>If the button is not working, feel free to copy and paste the following link into the address bar.<a href="<?php echo $redirectLink; ?>"><?php echo $redirectLink; ?></a></p>
            <p class="lead">
                <a class="btn btn-primary btn-lg" href="<?php echo $redirectLink; ?>" role="button">Click Here!</a>
            </p>
        </div>

        <?php

    }



    /**
     * subscribers_default_details returns HTML code with the user's total amount of users he has referred
     * and the total amount needed to win a prize. This shortcode is added to page by default. Remove this
     * and use set_goal and subscriber_referred to customize the code.
     *
     *
     * @return string   string: Returns HTML with the amount of subscribers the user has and the total amount set.
     */
    public function subscriber_default_shortcode()
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );

        if(!empty($get)){
            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])){
            $goal = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'REFD');
            $total = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'TOTAL');

            $html = "<p>Your Score: $total<br>The Goal: $goal</p>";
            return $html;
        }

    }

    /**
     * Subscription Form decide which shortcode to submit depending on the parameters that are passed through the URL.
     * If the user id(merge_field: USERID) is present along with the sub and list, the form will not appear because
     * the user should be the owner of the account.
     *
     * @return string
     */
    public function subscription_form_shortcode()
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );


        $admin_url = $this->admin_url();
        $force_list = get_option('ListID');


        if(empty($get['id']) && !empty($get['sub']) && !empty($get['list'])){
            $mailchimp_api= WireConnector::mailchimp_methods();

            $list_verification = $mailchimp_api->get('lists/'.$get['list']);

            if($list_verification['status'] == 404){
                return $this->form_style($admin_url, 'regular', $force_list);
            }

            $member_verification = $mailchimp_api->get('lists/'.$get['list'].'/members/'.$get['sub']);

            if($member_verification['status'] == 404){
                return $this->form_style($admin_url, 'regular', $force_list);
            }
        }

        if(!empty($get['id']) && !empty($get['sub']) && !empty($get['list'])){
            return '';
        }elseif(empty($get['id']) && !empty($get['sub']) && !empty($get['list'])){
            return $this->form_style($admin_url,'referred', $get);
        }else{
            return $this->form_style($admin_url,'regular', $force_list);
        }


    }


    public function subscriber_email_shortcode()
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );

        if(!empty($get)){
            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])){
            $memberUrl = $mailchimp_api->membersUrlWithHash($get['list'], $get['sub']);
            return $mailchimp_api->retrieveMemberData($memberUrl, 'email_address');
        }
    }

    public function merge_field_shortcode( $attr = '')
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );


        if(!empty($get)){

            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])){
            $memberUrl = $mailchimp_api->membersUrlWithHash($get['list'], $get['sub']);
            $merge_fields = $mailchimp_api->retrieveMemberData($memberUrl, 'merge_fields');

            $merge_field = shortcode_atts( array(
                'name' => 'Please include merge field name.'
            ), $attr);

            $name = $merge_field['name'];

            return $merge_fields[$name];

        }
    }

    public function goal_shortcode( $attr = '')
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );

        if(!empty($get)){
            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])){

            $goal = shortcode_atts( array(
                'number' => 'Please include Goal Number.'
            ), $attr);

            $goalNumber = strtolower($goal['number']);

            switch($goalNumber){
                case 'one':
                case '1':
                case 1:
                    $number = 'FirstGoal';
                    break;
                case 'two':
                case '2':
                case 2:
                    $number = 'SecondGoal';
                    break;
                case 'three':
                case '3':
                case 3:
                    $number = 'ThirdGoal';
                    return $number;
                    break;
                case 'fourth':
                case '4':
                case 4:
                    $number = 'FourthGoal';
                    return $number;
                    break;
                default:
                    $number = 'Invalid';

            }

            if($number == 'Invalid'){
                $invalid = 'Invalid Input: Please use a number.';
                return $invalid;
            }

            $number = get_option($number);

            return $number;

        }
    }


    public function subscriber_percentage_shortcode()
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );

        if(!empty($get)){
            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])) {
            $refd = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'REFD');
            $total = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'TOTAL');

            $percentage = ($refd / $total) * 100;

            return floor($percentage);
        }

    }

    public function subscriber_difference_shortcode()
    {
        $get = array('id' => get_query_var('id'),
            'sub' => get_query_var('sub'),
            'list' => get_query_var('list')
        );

        if(!empty($get)){
            $this->verify_subscriber($get['id'], $get['list'], $get['sub']);
        }

        $mailchimp_api = WireConnector::mailchimp_methods();

        if(!empty($get['sub']) && !empty($get['list'])) {
            $refd = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'REFD');
            $total = $mailchimp_api->user_merge_field($get['list'], $get['sub'], 'TOTAL');

            $difference = $total - $refd;

            return $difference;
        }

    }

}