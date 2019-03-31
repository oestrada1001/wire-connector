<?php


spl_autoload_register('WireConnectorRequiredClasses');

function WireConnectorRequiredClasses()
{

    $plugin_path = plugin_dir_path( __FILE__ );

    //Dependencies Loaded Manually
    require_once $plugin_path . 'includes/MailChimp.php';
    require_once $plugin_path . 'includes/mailchimp-framework.php';
    require_once $plugin_path . 'WireConnectorShareable.php';

    $plugin_path = $plugin_path . 'WireConnectorClasses/';

    $handle = opendir($plugin_path);

    //Other Files and Classes Are loaded from WireConnectorClasses.
    //Naming Convention: The File must start with 'WireConnector' and end with 'php'.
    while (false !== ($class = readdir($handle))) {

        $pattern = '/^WireConnector.*php/';

        if ($class != "." && $class != ".." && preg_match( $pattern, $class)) {

            require_once $plugin_path . $class;

        }

    }

    closedir($handle);


}