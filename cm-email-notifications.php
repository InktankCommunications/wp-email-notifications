<?php

/**
 * Plugin Name:       Campaign Monitor Email Notifications
 * Description:       Sends an email notification to a Campaign Monitor list on post publish
 * Version:           1.0.0
 * Author:            Inktank Communications - Rafael Youakeem
 * Author URI:        https://inktankcommunications.com
 */

/*
 * Require Campaign Monitor SDK
 */
require_once 'libs/createsend-php/csrest_campaigns.php';
require_once 'libs/createsend-php/csrest_clients.php';

/*
 * Plugin constants
 */
if (!defined('CM_EMAIL_NOTIFICATIONS_URL')) {
    define('CM_EMAIL_NOTIFICATIONS_URL', plugin_dir_url(__FILE__));
}

if (!defined('CM_EMAIL_NOTIFICATIONS_PATH')) {
    define('CM_EMAIL_NOTIFICATIONS_PATH', plugin_dir_path(__FILE__));
}

/*
 * Main class
 */
/**
 * Class CMNotifier
 *
 * This class creates the option page
 */
class CMNotifier
{

    /**
     * CMNotifier constructor.
     *
     * The main plugin actions registered for WordPress
     */
    public function __construct()
    {

        // Admin page calls:
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('wp_ajax_store_admin_data', array($this, 'storeAdminData'));
        add_action('admin_enqueue_scripts', array($this, 'addAdminScripts'));

        // Email Notification Logic
        add_action('transition_post_status', array($this, 'sendEmailNotification'), 10, 3);
    }

    /**
     * Adds the 'Email Notifications' label to the WordPress Admin Sidebar Menu
     */
    public function addAdminMenu()
    {
        add_menu_page(
            __('Email Notifications', 'cmnotifier'),
            __('Email Notifications', 'cmnotifier'),
            'manage_options',
            'cmnotifier',
            array($this, 'adminLayout'),
            ''
        );
    }

    /**
     * Callback for the Ajax request
     *
     * Updates the options data
     *
     * @return void
     */
    public function storeAdminData()
    {

        if (wp_verify_nonce($_POST['security'], $this->_nonce) === false) {
            die('Invalid Request!');
        }

        $data = $this->getData();

        foreach ($_POST as $field => $value) {

            if (substr($field, 0, 3) !== "cm_" || empty($value)) {
                continue;
            }

            // We remove the cmnotifier_ prefix to clean things up
            $field = substr($field, 3);

            $data[$field] = $value;

        }

        update_option($this->option_name, $data);

        echo __('Saved!', 'cmnotifier');
        die();

    }

    /**
     * Get client templates
     *
     * @param string $clientID
     * @return array
     */
    private function getClientTemplates($clientID)
    {

        $templates = array();

        $auth = $this->getAuth();
        $clients = new CS_REST_Clients($clientID, $auth);

        $templatesResult = $clients->get_templates();
        $response = $templatesResult->response;

        if (is_array($response)) {
            $templates = $response;
        }

        return $templates;

    }

    /**
     * Get client lists
     *
     * @param string $clientID
     * @return array
     */
    private function getClientLists($clientID)
    {

        $lists = array();

        $auth = $this->getAuth();
        $clients = new CS_REST_Clients($clientID, $auth);

        $listsResult = $clients->get_lists();
        $response = $listsResult->response;

        if (is_array($response)) {
            $lists = $response;
        }

        return $lists;

    }

    /**
     * The security nonce
     *
     * @var string
     */
    private $_nonce = 'cmnotifier_admin';

    /**
     * Adds Admin Scripts for the Ajax call
     */
    public function addAdminScripts()
    {
        wp_enqueue_script('cmnotifier-admin', CM_EMAIL_NOTIFICATIONS_URL . 'assets/js/admin.js', array(), 1.0);
        $admin_options = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            '_nonce' => wp_create_nonce($this->_nonce),
        );
        wp_localize_script('cmnotifier-admin', 'cmnotifier_exchanger', $admin_options);
    }

    /**
     * The option name
     *
     * @var string
     */
    private $option_name = 'cmnotifier_data';

    /**
     * Returns the saved options data as an array
     *
     * @return array
     */
    private function getData()
    {
        return get_option($this->option_name, array());
    }

    /**
     * Returns the saved api key as an array
     *
     * @return array
     */
    private function getAuth()
    {
        $data = $this->getData();
        return array('api_key' => $data['api_key']);

    }

    /**
     * Outputs the Admin Dashboard layout containing the form with all its options
     *
     * @return void
     */
    public function adminLayout()
    {

        $data = $this->getData();
        $templates = $this->getClientTemplates($data['client_id']);
        $lists = $this->getClientLists($data['client_id']);

        ?>

        <div class="wrap">
            <h3><?php _e('Email Notifications Configuration', 'cmnotifier');?></h3>
            <hr>
            <form id="cmnotifier-admin-form">
                <table class="form-table">
                    <tr>
                        <th><label for="cm_api_key"><?php _e('API Key', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_api_key"
                            id="cm_api_key"
                            type="text"
                            size="60"
                            value="<?php echo (isset($data['api_key'])) ? $data['api_key'] : ''; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="cm_api_key"><?php _e('Client ID', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_client_id"
                            id="cm_client_id"
                            type="text"
                            size="60"
                            value="<?php echo (isset($data['client_id'])) ? $data['client_id'] : ''; ?>"/>
                        </td>
                    </tr>

                    <?php if (empty($templates)): ?>
                        <tr>
                            <td>
                                <p class="notice notice-error">
                                    <?php _e('An error happened on the WordPress side. Make sure your API Key and Client ID are correct.', 'cmnotifier');?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>
                                <label for="cm_chosen_template"><?php _e('Choose a Template', 'cmnotifier');?></label>
                            </th>
                            <td>
                                <select name="cm_chosen_template"
                                    id="cm_chosen_template">
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template->TemplateID; ?>" <?php echo ($template->TemplateID === $data['chosen_template']) ? 'selected' : '' ?>>
                                            <?php echo $template->Name; ?>
                                        </option>
                                    <?php endforeach;?>
                                </select>
                            </td>
                        </tr>
                    <?php endif;?>

                    <?php if (empty($lists)): ?>
                        <tr>
                            <td>
                                <p class="notice notice-error">
                                    <?php _e('An error happened on the WordPress side. Make sure your API Key and Client ID are correct.', 'cmnotifier');?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>
                                <label for="cm_chosen_list"><?php _e('Choose a List', 'cmnotifier');?></label>
                            </th>
                            <td>
                                <select name="cm_chosen_list"
                                    id="cm_chosen_list">
                                    <?php foreach ($lists as $list): ?>
                                        <option value="<?php echo $list->ListID; ?>" <?php echo ($list->ListID === $data['chosen_list']) ? 'selected' : '' ?>>
                                            <?php echo $list->Name; ?>
                                        </option>
                                    <?php endforeach;?>
                                </select>
                            </td>
                        </tr>
                    <?php endif;?>

                    <tr>
                        <th><label for="cm_from"><?php _e('From Name', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_from"
                            id="cm_from"
                            type="text"
                            size="40"
                            value="<?php echo (isset($data['from'])) ? $data['from'] : ''; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="cm_from_email"><?php _e('From Email', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_from_email"
                            id="cm_from_email"
                            type="email"
                            size="40"
                            value="<?php echo (isset($data['from_email'])) ? $data['from_email'] : ''; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="cm_replyto_email"><?php _e('ReplyTo Email', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_replyto_email"
                            id="cm_replyto_email"
                            type="email"
                            size="40"
                            value="<?php echo (isset($data['replyto_email'])) ? $data['replyto_email'] : ''; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="cm_confirmation_email"><?php _e('Confirmation Email', 'cmnotifier');?></label></th>
                        <td>
                        <input name="cm_confirmation_email"
                            id="cm_confirmation_email"
                            type="email"
                            size="40"
                            value="<?php echo (isset($data['confirmation_email'])) ? $data['confirmation_email'] : ''; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            <button class="button-primary" type="submit"><?php _e('Save', 'cmnotifier');?></button>
                        </th>
                    </tr>
                </table>
            </form>

        </div>

        <?php

    }

    /**
     * Send an email notification to the CM list on post publish
     *
     * @return void
     */
    public function sendEmailNotification($new_status, $old_status, $post)
    {
        $data = $this->getData();

        // Only if the api key is saved
        if (empty($data) || !isset($data['api_key'])) {
            return;
        }

        if ('publish' === $new_status && 'publish' !== $old_status && $post->post_type === 'post') {
            $postTitle = $post->post_title;
            $postExcerpt = $post->post_excerpt;
            $postPermalink = get_post_permalink();
            $auth = $this->getAuth();

            $campaigns = new CS_REST_Campaigns(null, $auth);

            $clientID = $data['client_id'];

            var_dump('List');
            var_dump($data['chosen_list']);

            $campaign_info = array(
                'Subject' => $postTitle,
                'Name' => $postTitle,
                'FromName' => $data['from'],
                'FromEmail' => $data['from_email'],
                'ReplyTo' => $data['from_email'],
                'ListIDs' => array($data['chosen_list']),
                'TemplateID' => $data['chosen_template'],
                'TemplateContent' => array(
                    'Singlelines' => array(
                        array('Content' => $postTitle, 'Href' => $postPermalink),
                    ),
                    'Multilines' => array(
                        array('Content' => $postExcerpt),
                    ),
                ),
            );

            $campaignSchedule = array(
                'ConfirmationEmail' => $data['confirmation_email'],
                'SendDate' => 'immediately',
            );

            // Create the campaign and get it's ID
            $campaignID = $campaigns->create_from_template($clientID, $campaign_info);

            if (is_string($campaignID->response)) {
                // Set the current campaign to the current one
                $campaigns->set_campaign_id($campaignID->response);

                // Send the current campaign
                $campaigns->send($campaignSchedule);
            }
        }

    }

}

/*
 * Starts our plugin class, easy!
 */
new CMNotifier();

?>