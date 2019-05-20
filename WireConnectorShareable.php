<?php

abstract class WireConnectorShareable{


    /**
     * Returns the admin url that is needed in order to submit forms.
     *
     * @return string
     */
    public function admin_url()
    {
        $admin_url = get_admin_url() . "admin-post.php";

        return $admin_url;
    }


    public function verify_subscriber($id, $list, $sub){
        if(!is_admin()){
            $mailchimp_api = WireConnector::mailchimp_methods();
            $user_id = $mailchimp_api->user_merge_field($list, $sub, 'USERID');
            $list_verification = $mailchimp_api->get('lists/'.$list);

            if($user_id != $id || $list_verification['status'] == null || is_null($id)){
                if(isset($list_verification['status']) || $list_verification == 404){
                    $newSubscriber = $this->retrieve_client_link();

                    return "<script>location.href='$newSubscriber';</script>";
                    exit;
                }
                $referred_subscriber_link = $this->retrieve_client_link().'?list='.$list.'&sub='.$sub;
                return "<script>location.href='$referred_subscriber_link';</script>";
                exit;
            }
        }
    }



    /**
     * This method is used to return the form to the subscription_form method. If you need to style the form, add classes and/or id's
     * to the HTML tags and style with CSS. You may also use inline styling.
     *
     * @param $admin_url    string: the url which points to admin_post.php
     * @param $type string        Type of form: referred, regular
     * @param $get = null | optional         array: The parameters that are needed to create a API call.
     *
     * @return string       string: returns html form
     */
    public function form_style($admin_url, $type, $get = 'null')
    {
        switch ($type){
            case 'referred':
                $refByForm = "
                    <form action=\"$admin_url\" method=\"post\">
                      <input type=\"hidden\" name=\"action\" value=\"submit-form\">
                      <input type=\"hidden\" name=\"type\" value=\"referred\">
                      <input type=\"hidden\" name=\"id\" value=\"{$get['id']}\">
                      <input type=\"hidden\" name=\"sub\" value=\"{$get['sub']}\">
                      <input type=\"hidden\" name=\"list\" value=\"{$get['list']}\">
                      <input type=\"email\" name=\"email\" value=\"\" style=\"display:inline-block; width:50%;\" placeholder=\"Email Address\" required>
                      <input type=\"submit\" value=\"Submit\">
                    </form>";
                return $refByForm;
                break;
            case 'regular':
                $regularSub = "
                    <form action=\"$admin_url\" method=\"post\">
                      <input type=\"hidden\" name=\"action\" value=\"submit-form\">
                      <input type=\"hidden\" name=\"type\" value=\"regular\">
                      <input type=\"hidden\" name=\"list\" value=\"{$get}\">
                      <input type=\"email\" name=\"email\" value=\"\" style=\"display:inline-block; width:50%;\" placeholder=\"Email Address\" required>
                      <input type=\"submit\" value=\"Submit\">
                    </form>";
                return $regularSub;
                break;
        }
    }

}