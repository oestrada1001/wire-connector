<?php

Class WireConnectorSettingOptions{

    public function __construct()
    {
        add_action('admin_init', array($this, 'page_settings'));
        add_action('admin_init', array($this, 'goal_settings'));
        add_action('admin_init', array($this, 'list_settings'));
        add_action('admin_init', array($this, 'email_settings'));
        add_action('admin_init', array($this, 'prize_settings'));
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

    public function prize_settings()
    {
        register_setting('wire_connector_prizes','FirstPrize');
        register_setting('wire_connector_prizes','SecondPrize');
        register_setting('wire_connector_prizes','ThirdPrize');
        register_setting('wire_connector_prizes','FourthPrize');

        add_settings_section('wc_prizes', 'Wire Connector Prizes', array($this,'wc_prizes_settings'), 'goal_wc_page');
    }

    public function wc_prizes_settings()
    {
        $firstPrize = esc_attr(get_option('FirstPrize' ));
        $secondPrize = esc_attr(get_option('SecondPrize'));
        $thirdPrize = esc_attr(get_option('ThirdPrize'));
        $fourthPrize = esc_attr(get_option('FourthPrize'));

        ?>
        <label for='firstPrize'>First Prize:</label>
        <input type='text' name='firstPrize' placeholder='<?php echo $firstPrize; ?>'>
        <br>
        <label for='secondPrize'>Second Prize:</label>
        <input type='text' name='secondPrize' placeholder='<?php echo $secondPrize; ?>'>
        <br>
        <label for='thirdPrize'>Third Prize:</label>
        <input type='text' name='thirdPrize' placeholder='<?php echo $thirdPrize; ?>'>
        <br>
        <label for='forthPrize'>Fourth Prize:</label>
        <input type='text' name='fourthPrize' placeholder='<?php echo $fourthPrize; ?>'>
        <?php
    }

    public function email_settings()
    {
        register_setting('wire_connector_email', 'AdminEmail');

        add_settings_section('wc_email', 'Admin Email', array($this, 'wc_email_settings'), 'mail_wc_page');
    }

    public function wc_email_settings()
    {
        $adminEmail = esc_attr( get_option('AdminEmail'));

        ?>
        <label for='adminEmail'>Email:</label>
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

        add_settings_section('wc_pages', 'Wire Connector Pages', array($this, 'wc_pages_settings'), 'page_wc_page');

    }

    public function wc_pages_settings()
    {
        $pageName = esc_attr( get_option('PageName'));
        $clientPageName = esc_attr( get_option('ClientPageName'));
        ?>
        <label for='pageName'>User Profile Page:</label>
        <input type='text' name='pageName' placeholder='<?php echo $pageName; ?>' required>
        <br>
        <label for='clientPageName'>New Subscriber Page:</label>
        <input type='text' name='clientPageName' placeholder='<?php echo $clientPageName; ?>' required>
        <?php
    }


    public function goal_settings()
    {
        register_setting('wire_connector_goals','FirstGoal');
        register_setting('wire_connector_goals','SecondGoal');
        register_setting('wire_connector_goals','ThirdGoal');
        register_setting('wire_connector_goals','FourthGoal');

        add_settings_section('wc_goals', 'Wire Connector Goals', array($this,'wc_goals_settings'), 'goal_wc_page');

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

        add_settings_section('wc_list', 'Wire Connector List', array($this, 'wc_list_settings'), 'page_wc_page');
    }

    public function wc_list_settings()
    {
        $mailchimp_api = WireConnector::mailchimp_methods();
        $listIDs = $mailchimp_api->getListID();

        ?>
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
        <?php
    }


}