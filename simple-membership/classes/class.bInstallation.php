<?php

/**
 * Description of BInstallation
 *
 * @author nur
 */
class BInstallation {

    public static function do_multisite() {
        global $wpdb;
        $networkwide = filter_input(INPUT_GET, 'networkwide');
        // check if it is a network activation - if so, run the activation function for each blog id
        if ($networkwide == 1) {
            $old_blog = $wpdb->blogid;
            // Get all blog ids
            $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                SimpleWpMembership::installer();
                SimpleWpMembership::initdb();
            }
            switch_to_blog($old_blog);
            return;
        }
    }
    public static function installer() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = '';
        if (!empty($wpdb->charset)){
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }else{
            $charset_collate = "DEFAULT CHARSET=utf8";
        }
        if (!empty($wpdb->collate)){
            $charset_collate .= " COLLATE $wpdb->collate";
        }

        $sql = "CREATE TABLE " . $wpdb->prefix . "swpm_members_tbl (
			member_id int(12) NOT NULL PRIMARY KEY AUTO_INCREMENT,
			user_name varchar(32) NOT NULL,
			first_name varchar(32) DEFAULT '',
			last_name varchar(32) DEFAULT '',
			password varchar(64) NOT NULL,
			member_since date NOT NULL DEFAULT '0000-00-00',
			membership_level smallint(6) NOT NULL,
			more_membership_levels VARCHAR(100) DEFAULT NULL,
			account_state enum('active','inactive','expired','pending','unsubscribed') DEFAULT 'pending',
			last_accessed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			last_accessed_from_ip varchar(64) NOT NULL,
			email varchar(64) DEFAULT NULL,
			phone varchar(64) DEFAULT NULL,
			address_street varchar(255) DEFAULT NULL,
			address_city varchar(255) DEFAULT NULL,
			address_state varchar(255) DEFAULT NULL,
			address_zipcode varchar(255) DEFAULT NULL,
			home_page varchar(255) DEFAULT NULL,
			country varchar(255) DEFAULT NULL,
			gender enum('male','female','not specified') DEFAULT 'not specified',
			referrer varchar(255) DEFAULT NULL,
			extra_info text,
			reg_code varchar(255) DEFAULT NULL,
			subscription_starts date DEFAULT NULL,
			initial_membership_level smallint(6) DEFAULT NULL,
			txn_id varchar(64) DEFAULT '',
			subscr_id varchar(32) DEFAULT '',
			company_name varchar(100) DEFAULT '',
			notes text DEFAULT NULL,
			flags int(11) DEFAULT '0',
			profile_image varchar(255) DEFAULT ''
          )" . $charset_collate . ";";
        dbDelta($sql);

        $sql = "CREATE TABLE " . $wpdb->prefix . "swpm_membership_tbl (
			id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
			alias varchar(127) NOT NULL,
			role varchar(255) NOT NULL DEFAULT 'subscriber',
			permissions tinyint(4) NOT NULL DEFAULT '0',
			subscription_period int(11) NOT NULL DEFAULT '-1',
			subscription_unit   VARCHAR(20)        NULL,
			loginredirect_page  text NULL,
			category_list longtext,
			page_list longtext,
			post_list longtext,
			comment_list longtext,
			attachment_list longtext,
			custom_post_list longtext,
			disable_bookmark_list longtext,
			options longtext,
                        show_older_posts  tinyint(1) NOT NULL DEFAULT '0',
			campaign_name varchar(60) NOT NULL DEFAULT ''
          )" . $charset_collate . " AUTO_INCREMENT=1 ;";
        dbDelta($sql);
        $sql = "SELECT * FROM " . $wpdb->prefix . "swpm_membership_tbl WHERE id = 1";
        $results = $wpdb->get_row($sql);
        if (is_null($results)) {
            $sql = "INSERT INTO  " . $wpdb->prefix . "swpm_membership_tbl  (
			id ,
			alias ,
			role ,
			permissions ,
			subscription_period ,
			subscription_unit,
			loginredirect_page,
			category_list ,
			page_list ,
			post_list ,
			comment_list,
			disable_bookmark_list,
			options,
			campaign_name
			)VALUES (1 , 'Content Protection', 'administrator', '15', '0',NULL,NULL, NULL , NULL , NULL , NULL,NULL,NULL,'');";
            $wpdb->query($sql);
        }
        $sql = "CREATE TABLE " . $wpdb->prefix . "swpm_membership_meta_tbl (
                        id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        level_id int(11) NOT NULL,
                        meta_key varchar(255) NOT NULL,
                        meta_label varchar(255) NULL,
                        meta_value text,
                        meta_type varchar(255) NOT NULL DEFAULT 'text',
                        meta_default text,
                        meta_context varchar(255) NOT NULL DEFAULT 'default',
                        KEY level_id (level_id),
                        UNIQUE KEY meta_key_id (level_id,meta_key)
          )" . $charset_collate . " AUTO_INCREMENT=1 ;";
        dbDelta($sql);
    }

    public static function initdb() {
        $settings = BSettings::get_instance();

        $installed_version = $settings->get_value('swpm-active-version');

        //Set other default settings values
        $reg_prompt_email_subject = "Complete your registration";
        $reg_prompt_email_body = "Dear {first_name} {last_name}" .
                "\n\nThank you for joining us!" .
                "\n\nPlease complete your registration by visiting the following link:" .
                "\n\n{reg_link}" .
                "\n\nThank You";
        $reg_email_subject = "Your registration is complete";
        $reg_email_body = "Dear {first_name} {last_name}\n\n" .
                "Your registration is now complete!\n\n" .
                "Registration details:\n" .
                "Username: {user_name}\n" .
                "Password: {password}\n\n" .
                "Please login to the member area at the following URL:\n\n" .
                "{login_link}\n\n" .
                "Thank You";

        $upgrade_email_subject = "Subject for email sent after account upgrade";
        $upgrade_email_body = "Dear {first_name} {last_name}" .
                "\n\nYour Account Has Been Upgraded." .
                "\n\nThank You";
        $reset_email_subject = get_bloginfo('name') . ": New Password";
        $reset_email_body = "Dear {first_name} {last_name}" .
                "\n\nHere is your new password" .
                "\n\nUser name: {user_name}" .
                "\n\nPassword: {password}" .
                "\n\nThank You";
        if (empty($installed_version)) {
            //Do fresh install tasks

            /*             * * Create the mandatory pages (if they are not there) ** */
            miscUtils::create_mandatory_wp_pages();
            /*             * * End of page creation ** */
            $settings->set_value('reg-complete-mail-subject', stripslashes($reg_email_subject))
                    ->set_value('reg-complete-mail-body', stripslashes($reg_email_body))
                    ->set_value('reg-prompt-complete-mail-subject', stripslashes($reg_prompt_email_subject))
                    ->set_value('reg-prompt-complete-mail-body', stripslashes($reg_prompt_email_body))
                    ->set_value('upgrade-complete-mail-subject', stripslashes($upgrade_email_subject))
                    ->set_value('upgrade-complete-mail-body', stripslashes($upgrade_email_body))
                    ->set_value('reset-mail-subject', stripslashes($reset_email_subject))
                    ->set_value('reset-mail-body', stripslashes($reset_email_body))
                    ->set_value('email-from', trim(get_option('admin_email')));
        }
        if (version_compare($installed_version, SIMPLE_WP_MEMBERSHIP_VER) == -1) {
            //Do upgrade tasks
        }

        $settings->set_value('swpm-active-version', SIMPLE_WP_MEMBERSHIP_VER)->save(); //save everything.
    }
}
