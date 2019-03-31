<?php

Class WireConnectorSettingOptions{

    public function __construct()
    {
        add_action('admin_init', array($this, 'page_settings'));
        add_action('admin_init', array($this, 'goal_settings'));
        add_action('admin_init', array($this, 'list_settings'));
        add_action('admin_init', array($this, 'email_settings'));
    }

    public function updateSettings($associatedArray)
    {
        foreach($associatedArray as $key => $value){
            update_option($key, $value);
        }
    }

    public function getSetting($settingName)
    {
        $settingValue = get_option($settingName);

        return $settingValue;
    }

    public function email_settings()
    {
        register_setting('wire_connector_pages', 'AdminEmail');

        add_settings_section('wc_email', 'Admin Email', array($this, 'wc_email_settings'), 'main_wc_page');
    }

    public function wc_email_settings()
    {
        $adminEmail = esc_attr( get_option('AdminEmail'));

        ?>
        <label for='adminEmail'>User Profile Page:</label>
        <input type='text' name='adminEmail' placeholder='<?php echo $adminEmail; ?>'>
        <?php
    }

    public function page_settings()
    {

        register_setting('wire_connector_pages', 'PageName');
        register_setting('wire_connector_pages', 'PageID');
        register_setting('wire_connector_pages', 'ClientPageName');
        register_setting('wire_connector_pages', 'ClientPageID');
        register_setting('wire_connector_pages', 'ClientPageID');

        add_settings_section('wc_pages', 'Wire Connector Pages', array($this, 'wc_pages_settings'), 'main_wc_page');

    }

    public function wc_pages_settings()
    {
        $pageName = esc_attr( get_option('PageName'));
        $clientPageName = esc_attr( get_option('ClientPageName'));
        ?>
        <label for='pageName'>User Profile Page:</label>
        <input type='text' name='pageName' placeholder='<?php echo $pageName; ?>'>
        <br>
        <label for='clientPageName'>New Subscriber Page:</label>
        <input type='text' name='clientPageName' placeholder='<?php echo $clientPageName; ?>'>
        <?php
    }


    public function goal_settings()
    {
        register_setting('wire_connector_goals','FirstGoal');
        register_setting('wire_connector_goals','SecondGoal');
        register_setting('wire_connector_goals','ThirdGoal');
        register_setting('wire_connector_goals','FourthGoal');

        add_settings_section('wc_goals', 'Wire Connector Goals', array($this,'wc_goals_settings'), 'main_wc_page');

    }

    public function wc_goals_settings()
    {
        $firstGoal = esc_attr(get_option('FirstGoal' ));
        $secondGoal = esc_attr(get_option('SecondGoal'));
        $thirdGoal = esc_attr(get_option('ThirdGoal'));
        $fourthGoal = esc_attr(get_option('FourthGoal'));

        ?>
        <label for='firstGoal'>First Goal:</label>
        <input type='text' name='firstGoal' placeholder='<?php echo $firstGoal; ?>'>
        <br>
        <label for='secondGoal'>Second Goal:</label>
        <input type='text' name='secondGoal' placeholder='<?php echo $secondGoal; ?>'>
        <br>
        <label for='thirdGoal'>Third Goal:</label>
        <input type='text' name='thirdGoal' placeholder='<?php echo $thirdGoal; ?>'>
        <br>
        <label for='forthGoal'>Fourth Goal:</label>
        <input type='text' name='fourthGoal' placeholder='<?php echo $fourthGoal; ?>'>
        <?php

    }

    public function list_settings()
    {
        register_setting('wire_connector_list', 'ListName');
        register_setting('wire_connector_list', 'ListID');

        add_settings_section('wc_list', 'Wire Connector List', array($this, 'wc_list_settings'), 'main_wc_page');
    }

    public function wc_list_settings()
    {
        $mailchimp_api = WireConnector::mailchimp_methods();
        $listIDs = $mailchimp_api->getListID();

        ?>
        <div class="form-group">
            <label for="listID">Select List</label>
            <select name="listID" class="custom-select">
                <option value="">Choose Option</option>
                <?php foreach($listIDs as $listArray => $listName){
                    foreach($listName as $list => $listKey){ ?>
                        <option value="<?php echo $listArray.','.$listKey; ?>"><?php echo $listArray; ?></option>
                    <?php   }
                } ?>

            </select>
        </div>
        <?php
    }


}