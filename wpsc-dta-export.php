<?php
/*
Plugin Name: WPSC DTA Export
Plugin URI: http://wordpress.org/extend/plugins/wpsc-dta-export/
Description: Export Orders from <a href="http://www.instinct.co.nz">Wordpress Shopping Cart</a> as DTA file.
Version: 1.2-testing
Author: Kolja Schleich
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
		require_once('lib/DTA.php');
		
		if ( !defined( 'WP_CONTENT_URL' ) )
			define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
		if ( !defined( 'WP_PLUGIN_URL' ) )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
			
		$this->plugin_url = WP_PLUGIN_URL.'/'.plugin_basename(__FILE__);

		$this->options = get_option( 'wpsc-dta-export' );
		$this->dta = new DTA(DTA_DEBIT);
			
		// Set file sender
		$this->dta->setAccountFileSender(array(
			"name" 			=> utf8_decode($this->options['receiver']['name']),
			"bank_code"		=> $this->options['receiver']['bank_code'],
			"account_number"	=> $this->options['receiver']['account_number'],
		));
	}
		
	
	/**
	 * gets formfields from database
	 *
	 * @param none
	 * @return array
	 */
	private function getFormFields()
	{
		global $wpdb;
		
		if ( count($this->form_fields) == 0 )
			$this->form_fields = $wpdb->get_results( "SELECT `id`, `name` FROM `".$wpdb->prefix."collect_data_forms` WHERE `active` = '1' ORDER BY `order` ASC" );
			
		return $this->form_fields;
	}
	
	
	/**
	 * gets selection form for form fields
	 *
	 * @param string $form_name
	 * @return string
	 */
	private function getFormFieldSelection( $form_name, $select = 0 )
	{
		$out = '<select size="1" name="'.$form_name.'" id="'.$form_name.'">';
		foreach ( $this->getFormFields() AS $form_field ) {
			$selected = ( $select == $form_field->id ) ? " selected='selected'" : '';
			$out .= '<option value="'.$form_field->id.'"'.$selected.'>'.$form_field->name.'</option>';
		}
		$out .= '</select>';
		return $out;
	}
	private function printFormFieldSelection( $form_name, $select = 0)
	{
		echo $this->getFormFieldSelection( $form_name, $select );
	}
	
	
	/**
	 * saves details of purchase in class
	 *
	 * @param int $purchase_id
	 * @return boolean
	 */
	private function getPurchaseData( $purchase_id )
	{
		global $wpdb;
		$purchase_data = $wpdb->get_results( "SELECT `value`, `form_id` FROM `".$wpdb->prefix."submited_form_data` WHERE log_id = '".$purchase_id."' ORDER BY form_id ASC" );
		if ( $purchase_data ) {
			foreach ( $purchase_data AS $data ) {
				$this->purchase_data[$data->form_id] = $data->value;
			}
			return true;
		}
		return false;
		
	}
	
	
	/**
	 * gets DTA File
	 *
	 * @param none
	 * @return string
	 */
	private function getDTAFile()
	{
	 	global $wpdb;
		
		$filename = 'DTAUS0.TXT';
			
		$last_exported = $this->options['last_exported'];
			
		$purchase_log = $wpdb->get_results( "SELECT `id`, `totalprice` FROM `".$wpdb->prefix."purchase_logs` WHERE id > '".$last_exported."'" );
		
		if ( $purchase_log ) {
			header('Content-Type: text/dta');
    			header('Content-Disposition: inline; filename="'.$filename.'"');
			$num_to_export = count($purchase_log);
				
			foreach ( $purchase_log AS $purchase ) {
				if ( $this->getPurchaseData( $purchase->id ) ) {
					$name = $this->purchase_data[$this->options['payer']['name']];
					$bank_code = $this->purchase_data[$this->options['payer']['bank_code']];
					$account_number = $this->purchase_data[$this->options['payer']['account_number']];
	
					$this->dta->addExchange(
						array(
							"name"			=> utf8_decode($name),
							"bank_code"		=> $bank_code,
							"account_number"	=> $account_number,
						),
						$purchase->totalprice,
						array(
							$options['usage'],
							__( 'Order No: ', 'wpsc-dta-export' ).$purchase->id
						)
					);
				}
				unset($this->purchase_data);
			}
			
			echo $this->dta->getFileContent();
				
			$options['last_exported'] = $purchase_log[$num_to_export-1]->id;
			update_option( 'wpsc-dta-export', $options );
				
			exit();
		} else {
			return false;
		}
	}
		
		
	/**
	 * Print Admin Page
	 *
	 * @param none
	 * @return void
	 */
	public function printAdminPage()
	{
		global $wpdb;
		
		if ( isset($_GET['export']) AND 'dta' == $_GET['export'] )
			$this->getDTAFile();
	
		if ( isset($_POST['update_dta_settings']) && current_user_can( 'edit_dta_settings' ) ) {
			check_admin_referer( 'wpsc-dta-export-update-settings_general' );
			
			$options['receiver']['name'] = $_POST['receiver_name'];
			$options['receiver']['account_number'] = $_POST['receiver_account_number'];
			$options['receiver']['bank_code'] = $_POST['receiver_bank_code'];
			$options['payer']['name'] = $_POST['payer_name'];
			$options['payer']['bank_code'] = $_POST['payer_bank_code'];
			$options['payer']['account_number'] = $_POST['payer_account_number'];
			$options['usage'] = $_POST['usage'];
			
			update_option( 'wpsc-dta-export', $options );
			
			echo '<div id="message" class="updated fade"><p><strong>'.__( 'Settings saved', 'wpsc-dta-export' ).'</strong></p></div>';
		}
			
		if ( $this->options['receiver']['name'] == '' || $this->options['receiver']['account_number'] == '' || $this->options['receiver']['bank_code'] == '')
			echo '<div id="message" class="error"><p><strong>'.__( "Before exporting a DTA File you need to complete the settings!", "wpsc-dta-export" ).'</strong></p></div>';
		?>
		<div class="wrap narrow">
			<h2><?php _e( 'DTA Export', 'wpsc-dta-export' ) ?></h2>
			<form action="index.php" method="get">
				<input type="hidden" name="export" value="dta" />
				<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
				<p><input type="submit" value="<?php _e( 'Download DTA File', 'wpsc-dta-export' ) ?> &raquo;" class="button" /></p>
			</form>
			<p><?php _e( 'Last exported order', 'wpsc-dta-export' ) ?>: <?php echo $this->options['last_exported'] ?></p>
		</div>
		
		<?php if ( current_user_can( 'edit_dta_settings' ) ) : ?>
		<div class="wrap">
			<h2><?php _e( 'Settings', 'wpsc-dta-export' ) ?></h2>
			
			<form action="admin.php?page=wpsc-dta-export.php" method="post">
				<?php wp_nonce_field( 'wpsc-dta-export-update-settings_general' ) ?>
				<h3><?php _e( 'Receiver', 'wpsc-dta-export' ) ?></h3>
				<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="receiver_name"><?php _e( 'Account Owner', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="name" name="receiver_name" value="<?php echo $this->options['receiver']['name'] ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="receiver_account_number"><?php _e( 'Account Number', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="account_number" name="receiver_account_number" value="<?php echo $this->options['receiver']['account_number'] ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="receiver_bank_code"><?php _e( 'Bank Code', 'wpsc-dta-export' ) ?></label></th><td><input type="text" id="bank_code" name="receiver_bank_code" value="<?php echo $this->options['receiver']['bank_code'] ?>" /></td>
				</tr>
				</table>
				
				<h3><?php _e( 'Payer', 'wpsc-dta-export' ) ?></h3>
				<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="payer_name"><?php _e( 'Account Owner', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_name', $this->options['payer']['name'] ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="payer_bank_code"><?php _e( 'Bank Code', 'wpsc-dta-export' ) ?></label></th><td><?php $thiUninstall Plugin for WP 2.7s->printFormFieldSelection( 'payer_bank_code', $this->options['payer']['bank_code'] ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="payer_account_number"><?php _e( 'Account Number', 'wpsc-dta-export' ) ?></label></th><td><?php $this->printFormFieldSelection( 'payer_account_number', $this->options['payer']['account_number'] ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="usage"><?php _e( 'Usage', 'wpsc-dta-export' ) ?></label></th><td><input type="text" name="usage" value="<?php echo $this->options['usage'] ?>" size="30" maxlength="27" /></td>
				</tr>
				</table>
				
				<p class="submit"><input type="submit" name="update_dta_settings" value="<?php _e( 'Save Settings', 'wpsc-dta-export' ) ?> &raquo;" class="button" /></p>
			</form>
		</div>
		<?php endif;
	}


	/**
	 * Add Code to Wordpress Header
	 *
	 * @param none
	 * @return void
	 */
	public function addHeaderCode()
	{
		echo "<link rel='stylesheet' href='".$this->plugin_url."/style.css' type='text/css' />\n";
	}
	
	
	/**
	 * adds admin menu
	 *
	 * @param none
	 * @return void
	 */
	public function addAdminMenu()
	{
		$mypage = add_submenu_page('wp-shopping-cart/display-log.php', __( 'DTA Export', 'wpsc-dta-export' ), __( 'DTA Export', 'wpsc-dta-export' ), 'export_dta', basename(__FILE__), array(&$this, 'printAdminPage'));
		add_action( "admin_print_scripts-$mypage", array(&$this, 'addHeaderCode') );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'pluginActions' ) );
	}
		
		
	/**
	 * pluginActions() - display link to settings page in plugin table
	 *
	 * @param array $links array of action links
	 * @return void
	 */
	public public function pluginActions( $links )
	{
		$settings_link = '<a href="chcounter-widget.php">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
	
		return $links;
	}
	
	
	/**
	 * Initialize Plugin
	 *
	 * @param none
	 * @return void
	 */
	public function init()
	{
		$options = array();
		$options['last_exported'] = 0;
		$options['receiver']['name'] = '';
		$options['receiver']['account_number'] = '';
		$options['receiver']['bank_code'] = '';
		
		add_option( 'wpsc-dta-export', $options, 'DTA Export Options', 'yes' );
		
		/*
		* Add Capability to export DTA Files and change DTA Settings
		*/
		$role = get_role('administrator');
		$role->add_cap('export_dta');
		$role->add_cap('edit_dta_settings');
	}
	
	
	/**
	 * Uninstall plugin
	 *
	 * @param none
	 * @return void
	 */
	public function uninstall()
	{
		delete_option( 'wpsc-dta-export' );
	}
}

$wpsc_dta_export = new WPSC_DTA_Export();

register_activation_hook(__FILE__, array(&$wpsc_dta_export, 'init') );
add_action( 'admin_menu', array(&$wpsc_dta_export, 'addAdminMenu') );

load_plugin_textdomain( 'wpsc-dta-export', false, dirname(plugin_basename(__FILE__)).'/languages' );

if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook(__FILE__, array(&$wpsc_dta_export, 'uninstall'));