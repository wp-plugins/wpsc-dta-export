<?php
/*
Plugin Name: WPSC DTA Export
Plugin URI: http://wordpress.org/extend/plugins/wpsc-dta-export
Description: Export Orders from <a href="https://wordpress.org/plugins/wp-e-commerce/">Wordpress Shopping Cart</a> as DTA file. Tested with WP E-commerce 3.9
Version: 1.6.4
Author: Kolja Schleich
Copyright 2007-2015  Kolja Schleich  (email : kolja [dot] schleich [at] googlemail.com)

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
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPSC_DTA_Export
{
	/**
	 * Object of DTA Payment class from PEAR Package
	 *
	 * @var object
	 */
	private $dta;
		
		
	/**
	 * save form fields
	 *
	 * @var array
	 */
	private $form_fields = array();
	
	
	/**
	 * Constructor. Initialize DTA Class and set Account Sender data
	 *
	 * @param none
	 * @return void
	 */
	public function __construct()
	{
		if ( !defined( 'WP_CONTENT_URL' ) )
			define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
		if ( !defined( 'WP_PLUGIN_URL' ) )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
			
		register_activation_hook(__FILE__, array(&$this, 'activate') );
		load_plugin_textdomain( 'wpsc-dta-export', false, basename(__FILE__, '.php').'/languages' );
		add_action( 'admin_menu', array(&$this, 'addAdminMenu') );
		if ( function_exists('register_uninstall_hook') )
			register_uninstall_hook(__FILE__, array("WPSC_DTA_Export", 'uninstall'));
			
		$this->plugin_url = WP_PLUGIN_URL.'/'.basename(__FILE__, '.php');
	}

	
	/**
	 * get formfields from database
	 *
	 * @param none
	 * @return array of formfields
	 */
	private function getFormFields()
	{
		global $wpdb;
		
		if ( count($this->form_fields) == 0 )
			$this->form_fields = $wpdb->get_results( "SELECT `id`, `name` FROM `".$wpdb->prefix."wpsc_checkout_forms` WHERE `active` = '1' AND `type` != 'heading' ORDER BY `checkout_order` ASC" );
			
		return $this->form_fields;
	}
	
	
	/**
	 * gets selection form for form fields
	 *
	 * @param string $form_name
	 * @param int $select selected option
	 * @return string
	 */
	private function getFormFieldSelection( $form_name, $select = 0 )
	{
		$out = '<select size="1" name="'.$form_name.'" id="'.$form_name.'">';
		foreach ( $this->getFormFields() AS $form_field ) {
			$selected = ( $select == $form_field->id ) ? " selected='selected'" : '';
			$out .= '<option value="'.$form_field->id.'"'.$selected.'>'.stripslashes($form_field->name).'</option>';
		}
		$out .= '</select>';
		return $out;
	}
	private function printFormFieldSelection( $form_name, $select = 0)
	{
		echo $this->getFormFieldSelection( $form_name, $select );
	}
	
	
	/**
	 * get details of purchase
	 *
	 * @param int $purchase_id
	 * @return boolean
	 */
	private function getPurchaseData( $purchase_id )
	{
		global $wpdb;
		$purchase_data = $wpdb->get_results( "SELECT `value`, `form_id` FROM `".$wpdb->prefix."wpsc_submited_form_data` WHERE log_id = '".intval($purchase_id)."' ORDER BY form_id ASC" );
		if ( $purchase_data ) {
			foreach ( $purchase_data AS $data ) {
				$this->purchase_data[$data->form_id] = $data->value;
			}
			return true;
		}
		return false;
		
	}
	
	
	/**
	 * get DTA File
	 *
	 * @param none
	 * @return string
	 */
	public function getDTAFile()
	{
	 	global $wpdb;
		
		$filename = 'DTAUS0.TXT';
		$this->error = false;
		$options = get_option('wpsc-dta-export');
		
		require_once(dirname (__FILE__) . '/Payment_DTA-1.4.3/DTA.php');
		$this->dta = new DTA(DTA_DEBIT);
			
		// Set file sender
		$this->dta->setAccountFileSender(array(
			"name" 			=> $options['receiver']['name'],
			"bank_code"		=> $options['receiver']['bank_code'],
			"account_number"	=> $options['receiver']['account_number'],
		));
		
		$purchase_log = $wpdb->get_results( "SELECT `id`, `totalprice` FROM `".$wpdb->prefix."wpsc_purchase_logs` WHERE `dta_export` = 0" );
		
		if ( $purchase_log ) {
			header('Content-Type: text/dta');
    		header('Content-Disposition: inline; filename="'.$filename.'"');
				
			foreach ( $purchase_log AS $purchase ) {
				$purchase_id = intval($purchase->id);
				if ( $this->getPurchaseData( $purchase_id ) ) {
					$name = stripslashes($this->purchase_data[$options['payer']['name']]);
					$bank_code = intval($this->purchase_data[$options['payer']['bank_code']]);
					$account_number = intval($this->purchase_data[$options['payer']['account_number']]);
	
					// replace umlaute and ß
					$name = ereg_replace("ä","ae",$name);
					$name = ereg_replace("Ä","Ae",$name);
					$name = ereg_replace("ö","oe",$name);
					$name = ereg_replace("Ö","Ue",$name);
					$name = ereg_replace("ü","ue",$name);
					$name = ereg_replace("Ü","Ue",$name);
					$name = ereg_replace("ß","ss",$name);
					
					// ignore purchase if bank code is longer than 8 characters
					if (strlen($bank_code) > 8) $this->error = true;
					if (!is_numeric($bank_code)) $this->error = true;
					if (!is_numeric($account_number)) $this->error = true;
											
					if ( !$this->error ) {
						$this->dta->addExchange(
							array(
								"name"			=> $name,
								"bank_code"		=> $bank_code,
								"account_number"	=> $account_number,
							),
							$purchase->totalprice,
							array(
								$options['usage'][0],
								sprintf($options['usage'][1], $purchase_id)
							)
						);
						
						$wpdb->query( $wpdb->prepare("UPDATE `".$wpdb->prefix."wpsc_purchase_logs` SET `dta_export` = 1 WHERE `id` = '%d'", $purchase_id) );
					}
					
					$this->error = false;
				}
				unset($this->purchase_data);
			}
			
			echo $this->dta->getFileContent();
			exit();
		} else {
			return false;
		}
	}
	
		
	/**
	 * print Admin Page
	 *
	 * @param none
	 * @return void
	 */
	public function printAdminPage()
	{
		global $wpdb;

		$options = get_option('wpsc-dta-export');
		if ( isset($_POST['update_dta_settings']) && current_user_can( 'edit_dta_settings' ) ) {
			check_admin_referer( 'wpsc-dta-export-update-settings_general' );
			
			$options['receiver']['name'] = htmlspecialchars(stripslashes($_POST['receiver_name']));
			$options['receiver']['account_number'] = intval($_POST['receiver_account_number']);
			$options['receiver']['bank_code'] = intval($_POST['receiver_bank_code']);
			$options['payer']['name'] = intval($_POST['payer_name']);
			$options['payer']['bank_code'] = intval($_POST['payer_bank_code']);
			$options['payer']['account_number'] = intval($_POST['payer_account_number']);
			$options['payer']['iban'] = intval($_POST['payer_iban']);
			$options['usage'] = array( htmlspecialchars(stripslashes($_POST['usage1'])), htmlspecialchars(stripslashes($_POST['usage2'])) );
			
			update_option( 'wpsc-dta-export', $options );
	
			echo '<div id="message" class="updated fade"><p><strong>'.__( 'Settings saved', 'wpsc-dta-export' ).'</strong></p></div>';
		}
		
		if ( $options['receiver']['name'] == '' || $options['receiver']['account_number'] == '' || $options['receiver']['bank_code'] == '')
			echo '<div id="message" class="error"><p><strong>'.__( "Before exporting a DTA File you need to complete the settings!", "wpsc-dta-export" ).'</strong></p></div>';
			
		$num_to_export = $wpdb->get_var( "SELECT COUNT(ID) FROM `".$wpdb->prefix."wpsc_purchase_logs` WHERE `dta_export` = 0" );
		?>
		<div class="wrap narrow">
			<h2><?php _e( 'DTA Export', 'wpsc-dta-export' ) ?></h2>
			<form action="<?php echo admin_url() ?>" method="get">
				<input type="hidden" name="export" value="dta" />
				<p><input type="submit" value="<?php _e( 'Download DTA File', 'wpsc-dta-export' ) ?>" class="button button-primary" /></p>
			</form>
			<p><?php _e( 'Number of orders to export', 'wpsc-dta-export' ) ?>: <?php echo $num_to_export ?></p>
		</div>
		
		<?php if ( current_user_can( 'edit_dta_settings' ) ) : ?>
		<div class="wrap">
			<h2><?php _e( 'Settings', 'wpsc-dta-export' ) ?></h2>
			
			<form action="<?php menu_page_url('wpsc-dta-export') ?>" method="post">
				<?php wp_nonce_field( 'wpsc-dta-export-update-settings_general' ) ?>
				<h3><?php _e( 'Receiver', 'wpsc-dta-export' ) ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="receiver_name"><?php _e( 'Account Owner', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="name" name="receiver_name" value="<?php echo $options['receiver']['name'] ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="receiver_account_number"><?php _e( 'Account Number', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="account_number" name="receiver_account_number" value="<?php echo $options['receiver']['account_number'] ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="receiver_bank_code"><?php _e( 'Bank Code', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="bank_code" name="receiver_bank_code" value="<?php echo $options['receiver']['bank_code'] ?>" /></td>
					</tr>
				</table>
					
				<h3><?php _e( 'Payer', 'wpsc-dta-export' ) ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="payer_name"><?php _e( 'Account Owner', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_name', $options['payer']['name'] ) ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="payer_bank_code"><?php _e( 'Bank Code', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_bank_code', $options['payer']['bank_code'] ) ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="payer_account_number"><?php _e( 'Account Number', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_account_number', $options['payer']['account_number'] ) ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="payer_account_number"><?php _e( 'IBAN', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_iban', $options['payer']['iban'] ) ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="usage"><?php _e( 'Usage', 'wpsc-dta-export' ) ?></label></th>
						<td>
							<input type="text" id="usage" name="usage1" value="<?php echo htmlspecialchars($options['usage'][0]) ?>" size="30" maxlength="27" /><br/>
							<input type="text" name="usage2" value="<?php echo htmlspecialchars($options['usage'][1]) ?>" size="30" maxlength="27" /> <span><?php _e( 'Put here a text for the order number. Use %d as placeholder.', 'wpsc-dta-export' ) ?></span>
						</td>
					</tr>
				</table>
				
				<input type='hidden' name='page_options' value='receiver_name,receiver_account_number,receiver_bank_code,payer_name,payer_bank_code,payer_account_number,usage' />
				<p class="submit"><input type="submit" name="update_dta_settings" value="<?php _e( 'Save Settings', 'wpsc-dta-export' ) ?>" class="button button-primary" /></p>
			</form>
		</div>
		<?php endif;
	}
	
	
	/**
	 * add admin menu
	 *
	 * @param none
	 * @return void
	 */
	public function addAdminMenu()
	{
		$plugin = basename(__FILE__,'.php').'/'.basename(__FILE__);
		$mypage = add_options_page(__( 'Shop DTA Export', 'wpsc-dta-export' ), __( 'Shop DTA Export', 'wpsc-dta-export' ), 'export_dta', 'wpsc-dta-export', array(&$this, 'printAdminPage'));

		add_filter( 'plugin_action_links_' . $plugin, array( &$this, 'pluginActions' ) );
	}
		
		
	/**
	 * display link to settings page in plugin table
	 *
	 * @param array $links array of action links
	 * @return new array of plugin actions
	 */
	public function pluginActions( $links )
	{
		$settings_link = '<a href="'.admin_page_url('wpsc-dta-export', 0).'">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
	
		return $links;
	}
	
	
	/**
	 * Activate Plugin
	 *
	 * @param none
	 * @return void
	 */
	public function activate()
	{
		global $wpdb;
		
		$options = array();
		$options['receiver']['name'] = '';
		$options['receiver']['account_number'] = '';
		$options['receiver']['bank_code'] = '';
		$options['payer']['name'] = 0;
		$options['payer']['bank_code'] = 0;
		$options['payer']['account_number'] = 0;
		$options['payer']['iban'] = 0;
		$options['usage'] = array('', '');
		
		add_option( 'wpsc-dta-export', $options, 'DTA Export Options', 'yes' );
		
		/*
		* Add Capability to export DTA Files and change DTA Settings
		*/
		$role = get_role('administrator');
		$role->add_cap('export_dta');
		$role->add_cap('edit_dta_settings');
		
		// Add field to save export status if it doesn't exist
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `".$wpdb->prefix."wpsc_purchase_logs`" );
		if (!in_array('dta_export', $cols))
			$wpdb->query( "ALTER TABLE `".$wpdb->prefix."wpsc_purchase_logs` ADD `dta_export` TINYINT NOT NULL" );
	}
	
	
	/**
	 * uninstall plugin
	 *
	 * @param none
	 * @return void
	 */
	public function uninstall()
	{
		delete_option( 'wpsc-dta-export' );
	}
}

// Run the plugin
$wpsc_dta_export = new WPSC_DTA_Export();

// Export DTA File
if ( isset($_GET['export']) AND 'dta' == $_GET['export'] )
	$wpsc_dta_export->getDTAFile();

