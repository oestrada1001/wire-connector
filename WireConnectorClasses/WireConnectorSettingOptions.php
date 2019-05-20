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

        register_setting('wire_connector_prizes','FifthPrize');
        register_setting('wire_connector_prizes','SixthPrize');
        register_setting('wire_connector_prizes','SeventhPrize');
        register_setting('wire_connector_prizes','EightPrize');
        register_setting('wire_connector_prizes','NinthPrize');


        add_settings_section('wc_prizes', 'Wire Connector Prizes', array($this,'wc_prizes_settings'), 'goal_wc_page');
    }

    public function wc_prizes_settings()
    {
        $firstPrize = esc_attr(get_option('FirstPrize' ));
        $secondPrize = esc_attr(get_option('SecondPrize'));
        $thirdPrize = esc_attr(get_option('ThirdPrize'));
        $fourthPrize = esc_attr(get_option('FourthPrize'));

        $fifthPrize = esc_attr(get_option('FifthPrize'));
        $sixthPrize = esc_attr(get_option('SixthPrize'));
        $seventhPrize = esc_attr(get_option('SeventhPrize'));
        $eightPrize = esc_attr(get_option('EightPrize'));
        $ninthPrize = esc_attr(get_option('NinthPrize'));

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
        <br>
        <label for='forthPrize'>Fifth Prize:</label>
        <input type='text' name='fifthPrize' placeholder='<?php echo $fifthPrize; ?>'>
        <br>
        <label for='forthPrize'>Sixth Prize:</label>
        <input type='text' name='sixthPrize' placeholder='<?php echo $sixthPrize; ?>'>
        <br>
        <label for='forthPrize'>Seventh Prize:</label>
        <input type='text' name='seventhPrize' placeholder='<?php echo $seventhPrize; ?>'>
        <br>
        <label for='forthPrize'>Eight Prize:</label>
        <input type='text' name='eightPrize' placeholder='<?php echo $eightPrize; ?>'>
        <br>
        <label for='forthPrize'>Ninth Prize:</label>
        <input type='text' name='ninthPrize' placeholder='<?php echo $ninthPrize; ?>'>
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

        register_setting('wire_connector_goals','FifthGoal');
        register_setting('wire_connector_goals','SixthGoal');
        register_setting('wire_connector_goals','SeventhGoal');
        register_setting('wire_connector_goals','EightGoal');
        register_setting('wire_connector_goals','NinthGoal');

        add_settings_section('wc_goals', 'Wire Connector Goals', array($this,'wc_goals_settings'), 'goal_wc_page');

    }

    public function wc_goals_settings()
    {
        $firstGoal = esc_attr(get_option('FirstGoal' ));
        $secondGoal = esc_attr(get_option('SecondGoal'));
        $thirdGoal = esc_attr(get_option('ThirdGoal'));
        $fourthGoal = esc_attr(get_option('FourthGoal'));

        $fifthGoal = esc_attr(get_option('FifthGoal'));
        $sixthGoal = esc_attr(get_option('SixthGoal'));
        $seventhGoal = esc_attr(get_option('SeventhGoal'));
        $eightGoal = esc_attr(get_option('EightGoal'));
        $ninthGoal = esc_attr(get_option('NinthGoal'));

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
        <br>
        <label for='forthGoal'>Fifth Goal:</label>
        <input type='text' name='fifthGoal' placeholder='<?php echo $fifthGoal; ?>'>
        <br>
        <label for='forthGoal'>Sixth Goal:</label>
        <input type='text' name='sixthGoal' placeholder='<?php echo $sixthGoal; ?>'>
        <br>
        <label for='forthGoal'>Seventh Goal:</label>
        <input type='text' name='seventhGoal' placeholder='<?php echo $seventhGoal; ?>'>
        <br>
        <label for='forthGoal'>Eight Goal:</label>
        <input type='text' name='eightGoal' placeholder='<?php echo $eightGoal; ?>'>
        <br>
        <label for='forthGoal'>Ninth Goal:</label>
        <input type='text' name='ninthGoal' placeholder='<?php echo $ninthGoal; ?>'>
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