<?php

/**
 * CiviCRM WordPress Member Sync "Groups" compatibility class.
 *
 * Class for encapsulating compatibility with the "Groups" plugin.
 *
 * Groups version 2.8.0 changed the way that access restrictions are implemented
 * and switched from "access control based on capabilities" to "access control
 * based on group membership". Furthermore, the legacy functionality does not
 * work as expected any more.
 *
 * As a result, the "groups_read_cap_add" and "groups_read_cap_delete" methods
 * used by this class cannot be relied upon any more.
 *
 * @since 0.3.9
 *
 * @package Civi_WP_Member_Sync
 */
class Civi_WP_Member_Sync_Groups {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.3.9
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * "Groups" plugin enabled flag.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var object $enabled True if "Groups" is enabled, false otherwise.
	 */
	public $enabled = false;



	/**
	 * Constructor.
	 *
	 * @since 0.3.9
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Store reference to plugin.
		$this->plugin = $plugin;

		// Initialise first.
		add_action( 'civi_wp_member_sync_initialised', array( $this, 'initialise' ) );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.3.9
	 */
	public function initialise() {

		// Test for "Groups" plugin on init.
		add_action( 'init', array( $this, 'register_hooks' ) );

	}



	/**
	 * Getter for the "enabled" flag.
	 *
	 * @since 0.4.2
	 *
	 * @return bool $enabled True if Groups is enabled, false otherwise.
	 */
	public function enabled() {

		// --<
		return $this->enabled;

	}



	//##########################################################################



	/**
	 * Register "Groups" plugin hooks if it's present.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 */
	public function register_hooks() {

		// Bail if we don't have the "Groups" plugin.
		if ( ! defined( 'GROUPS_CORE_VERSION' ) ) return;

		return;

		// Set enabled flag.
		$this->enabled = true;

		// Hook into rule add.
		add_action( 'civi_wp_member_sync_rule_add_capabilities', array( $this, 'groups_add_cap' ) );

		// Hook into rule edit.
		add_action( 'civi_wp_member_sync_rule_edit_capabilities', array( $this, 'groups_edit_cap' ) );

		// Hook into rule delete.
		add_action( 'civi_wp_member_sync_rule_delete_capabilities', array( $this, 'groups_delete_cap' ) );

		// Hook into manual sync process, before sync.
		add_action( 'civi_wp_member_sync_pre_sync_all', array( $this, 'groups_pre_sync' ) );

		// Hook into save post and auto-restrict. (DISABLED)
		//add_action( 'save_post', array( $this, 'groups_intercept_save_post' ), 1, 2 );

		// Bail if "Groups" is not version 2.8.0 or greater.
		if ( version_compare( GROUPS_CORE_VERSION, '2.8.0', '<' ) ) return;

		// Filter script dependencies on the "Add Rule" and "Edit Rule" pages.
		add_filter( 'civi_wp_member_sync_rules_css_dependencies', array( $this->plugin->admin, 'dependencies_css' ), 10, 1 );
		add_filter( 'civi_wp_member_sync_rules_js_dependencies', array( $this->plugin->admin, 'dependencies_js' ), 10, 1 );

		// Declare AJAX handlers.
		add_action( 'wp_ajax_civi_wp_member_sync_get_groups', array( $this, 'search_groups' ), 10 );

		// Hook into Rule Save process.
		add_action( 'civi_wp_member_sync_rule_pre_save', array( $this, 'rule_pre_save' ), 10, 4 );

		// Hook into Rule Apply process.
		add_action( 'civi_wp_member_sync_rule_apply_caps_current', array( $this, 'rule_apply_caps_current' ), 10, 5 );
		add_action( 'civi_wp_member_sync_rule_apply_caps_expired', array( $this, 'rule_apply_caps_expired' ), 10, 5 );
		add_action( 'civi_wp_member_sync_rule_apply_roles_current', array( $this, 'rule_apply_current' ), 10, 4 );
		add_action( 'civi_wp_member_sync_rule_apply_roles_expired', array( $this, 'rule_apply_expired' ), 10, 4 );

		// Hook into Capabilities and Roles lists.
		add_action( 'civi_wp_member_sync_list_caps_th_after_current', array( $this, 'list_current_header' ) );
		add_action( 'civi_wp_member_sync_list_caps_td_after_current', array( $this, 'list_current_row' ), 10, 2 );
		add_action( 'civi_wp_member_sync_list_caps_th_after_expiry', array( $this, 'list_expiry_header' ) );
		add_action( 'civi_wp_member_sync_list_caps_td_after_expiry', array( $this, 'list_expiry_row' ), 10, 2 );
		add_action( 'civi_wp_member_sync_list_roles_th_after_current', array( $this, 'list_current_header' ) );
		add_action( 'civi_wp_member_sync_list_roles_td_after_current', array( $this, 'list_current_row' ), 10, 2 );
		add_action( 'civi_wp_member_sync_list_roles_th_after_expiry', array( $this, 'list_expiry_header' ) );
		add_action( 'civi_wp_member_sync_list_roles_td_after_expiry', array( $this, 'list_expiry_row' ), 10, 2 );

		// Hook into Capabilities and Roles add screens.
		add_action( 'civi_wp_member_sync_cap_add_after_current', array( $this, 'rule_add_current' ), 10, 1 );
		add_action( 'civi_wp_member_sync_cap_add_after_expiry', array( $this, 'rule_add_expiry' ), 10, 1 );
		add_action( 'civi_wp_member_sync_role_add_after_current', array( $this, 'rule_add_current' ), 10, 1 );
		add_action( 'civi_wp_member_sync_role_add_after_expiry', array( $this, 'rule_add_expiry' ), 10, 1 );

		// Hook into Capabilities and Roles edit screens.
		add_action( 'civi_wp_member_sync_cap_edit_after_current', array( $this, 'rule_edit_current' ), 10, 2 );
		add_action( 'civi_wp_member_sync_cap_edit_after_expiry', array( $this, 'rule_edit_expiry' ), 10, 2 );
		add_action( 'civi_wp_member_sync_role_edit_after_current', array( $this, 'rule_edit_current' ), 10, 2 );
		add_action( 'civi_wp_member_sync_role_edit_after_expiry', array( $this, 'rule_edit_expiry' ), 10, 2 );

	}



	//##########################################################################



	/**
	 * Search for groups on the "Add Rule" and "Edit Rule" pages.
	 *
	 * We still need to exclude groups which are present in the "opposite"
	 * select - i.e. exclude current groups from expiry and vice versa.
	 *
	 * @since 0.4
	 */
	public function search_groups() {

		// Go direct.
		global $wpdb;

		// Grab comma-separated excludes.
		$exclude = isset( $_POST['exclude'] ) ? trim( $_POST['exclude'] ) : '';

		// Parse excludes.
		$excludes = array();
		if ( ! empty( $exclude ) ) {
			$excludes = explode( ',', $exclude );
		}

		// Construct AND clause.
		$and = '';
		if ( ! empty( $excludes ) ) {
			$exclude = implode( ',', array_map( 'intval', array_map( 'trim', $excludes ) ) );
			if ( strlen( $exclude ) > 0 ) {
				$and = 'AND group_id NOT IN (' . $exclude . ')';
			}
		}

		// Do query.
		$group_table = _groups_get_tablename( 'group' );
		$like = '%' . $wpdb->esc_like( trim( $_POST['s'] ) ) . '%';
		$sql = $wpdb->prepare( "SELECT * FROM $group_table WHERE name LIKE %s $and", $like );
		$groups = $wpdb->get_results( $sql );

		// Add items to output array.
		$json = array();
		foreach( $groups AS $group ) {
			$json[] = array(
				'id' => $group->group_id,
				'name' => esc_html( $group->name ),
			);
		}

		// Send data.
		echo json_encode( $json );
		exit();

	}



	//##########################################################################



	/**
	 * Intercept Rule Apply when method is "capabilities" and membership is "current".
	 *
	 * We need this method because the two related actions have different
	 * signatures - `civi_wp_member_sync_rule_apply_caps_current` also passes
	 * the capability, which we don't need.
	 *
	 * @since 0.4
	 *
	 * @param WP_User $user The WordPress user object.
	 * @param int $membership_type_id The ID of the CiviCRM membership type.
	 * @param int $status_id The ID of the CiviCRM membership status.
	 * @param array $capability The membership type capability added or removed.
	 * @param array $association_rule The rule used to apply the changes.
	 */
	public function rule_apply_caps_current( $user, $membership_type_id, $status_id, $capability, $association_rule ) {

		// Pass through without capability param.
		$this->rule_apply_current( $user, $membership_type_id, $status_id, $association_rule );

	}



	/**
	 * Intercept Rule Apply when method is "capabilities" and membership is "expired".
	 *
	 * We need this method because the two related actions have different
	 * signatures - `civi_wp_member_sync_rule_apply_caps_current` also passes
	 * the capability, which we don't need.
	 *
	 * @since 0.4
	 *
	 * @param WP_User $user The WordPress user object.
	 * @param int $membership_type_id The ID of the CiviCRM membership type.
	 * @param int $status_id The ID of the CiviCRM membership status.
	 * @param array $capability The membership type capability added or removed.
	 */
	public function rule_apply_caps_expired( $user, $membership_type_id, $status_id, $capability, $association_rule ) {

		// Pass through without capability param.
		$this->rule_apply_expired( $user, $membership_type_id, $status_id, $association_rule );

	}



	/**
	 * Intercept Rule Apply when membership is "current".
	 *
	 * @since 0.4
	 *
	 * @param WP_User $user The WordPress user object.
	 * @param int $membership_type_id The ID of the CiviCRM membership type.
	 * @param int $status_id The ID of the CiviCRM membership status.
	 * @param array $association_rule The rule used to apply the changes.
	 */
	public function rule_apply_current( $user, $membership_type_id, $status_id, $association_rule ) {

		// Remove the user from the expired groups.
		if ( ! empty( $association_rule['expiry_groups'] ) ) {
			foreach( $association_rule['expiry_groups'] AS $group_id ) {
				$this->group_member_delete( $user->ID, $group_id );
			}
		}

		// Add the user to the current groups.
		if ( ! empty( $association_rule['current_groups'] ) ) {
			foreach( $association_rule['current_groups'] AS $group_id ) {
				$this->group_member_add( $user->ID, $group_id );
			}
		}

	}



	/**
	 * Intercept Rule Apply when membership is "expired".
	 *
	 * @since 0.4
	 *
	 * @param WP_User $user The WordPress user object.
	 * @param int $membership_type_id The ID of the CiviCRM membership type.
	 * @param int $status_id The ID of the CiviCRM membership status.
	 * @param array $association_rule The rule used to apply the changes.
	 */
	public function rule_apply_expired( $user, $membership_type_id, $status_id, $association_rule ) {

		// Remove the user from the current groups.
		if ( ! empty( $association_rule['current_groups'] ) ) {
			foreach( $association_rule['current_groups'] AS $group_id ) {
				$this->group_member_delete( $user->ID, $group_id );
			}
		}

		// Add the user to the expired groups.
		if ( ! empty( $association_rule['expiry_groups'] ) ) {
			foreach( $association_rule['expiry_groups'] AS $group_id ) {
				$this->group_member_add( $user->ID, $group_id );
			}
		}

	}



	//##########################################################################



	/**
	 * Add a WordPress user to a "Groups" group.
	 *
	 * @since 0.4
	 *
	 * @param int $user_id The ID of the WordPress user to add to the group.
	 * @param int $group_id The ID of the "Groups" group.
	 * @return bool $success True on success, false otherwise.
	 */
	public function group_member_add( $user_id, $group_id ) {

		// Bail if they are already a group member.
		if ( Groups_User_Group::read( $user_id, $group_id ) ) {
			return true;
		}

		// Add user to group.
		$success = Groups_User_Group::create( array(
			'user_id'  => $user_id,
			'group_id' => $group_id,
		));

		// Maybe log on failure?
		if ( ! $success ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not add user to group.', 'civicrm-groups-sync' ),
				'user_id' => $user_id,
				'group_id' => $group_id,
				'backtrace' => $trace,
			), true ) );
		}

		// --<
		return $success;

	}



	/**
	 * Delete a WordPress user from a "Groups" group.
	 *
	 * @since 0.4
	 *
	 * @param int $user_id The ID of the WordPress user to delete from the group.
	 * @param int $group_id The ID of the "Groups" group.
	 * @return bool $success True on success, false otherwise.
	 */
	public function group_member_delete( $user_id, $group_id ) {

		// Bail if they are not a group member.
		if ( ! Groups_User_Group::read( $user_id, $group_id ) ) {
			return true;
		}

		// Delete user from group.
		$success = Groups_User_Group::delete( $user_id, $group_id );

		// Maybe log on failure?
		if ( ! $success ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not delete user from group.', 'civicrm-groups-sync' ),
				'user_id' => $user_id,
				'group_id' => $group_id,
				'backtrace' => $trace,
			), true ) );
		}

		// --<
		return $success;

	}



	//##########################################################################



	/**
	 * Amend the association rule that is about to be saved.
	 *
	 * @since 0.4
	 *
	 * @param array $rule The new or updated association rule.
	 * @param array $data The complete set of association rule.
	 * @param str $mode The mode ('add' or 'edit').
	 * @param str $method The sync method.
	 */
	public function rule_pre_save( $rule, $data, $mode, $method ) {

		// Init "current" groups.
		$current = array();

		// Get the "current" groups.
		if (
			isset( $_POST['cwms_groups_select_current'] ) AND
			is_array( $_POST['cwms_groups_select_current'] ) AND
			! empty( $_POST['cwms_groups_select_current'] )
		) {

			// Grab array of group IDs.
			$current = $_POST['cwms_groups_select_current'];

			// Sanitise array items.
			array_walk( $current, function( &$item ) {
				$item = absint( trim( $item ) );
			});

		}

		// Init "expiry" groups.
		$expiry = array();

		// Get the "expiry" groups.
		if (
			isset( $_POST['cwms_groups_select_expiry'] ) AND
			is_array( $_POST['cwms_groups_select_expiry'] ) AND
			! empty( $_POST['cwms_groups_select_expiry'] )
		) {

			// Grab array of group IDs.
			$expiry = $_POST['cwms_groups_select_expiry'];

			// Sanitise array items.
			array_walk( $expiry, function( &$item ) {
				$item = absint( trim( $item ) );
			});

		}

		// Add to the rule.
		$rule['current_groups'] = $current;
		$rule['expiry_groups'] = $expiry;

		// --<
		return $rule;

	}



	//##########################################################################



	/**
	 * Show the Current Group header.
	 *
	 * @since 0.4
	 */
	public function list_current_header() {

		// Echo markup.
		echo '<th>' . __( 'Current Group(s)', 'civicrm-wp-member-sync' ) . '</th>';

	}



	/**
	 * Show the Expired Group header.
	 *
	 * @since 0.4
	 */
	public function list_expiry_header() {

		// Echo markup.
		echo '<th>' . __( 'Expiry Group(s)', 'civicrm-wp-member-sync' ) . '</th>';

	}



	/**
	 * Show the Current Groups.
	 *
	 * @since 0.4
	 *
	 * @param int $key The current key (type ID).
	 * @param array $item The current item.
	 */
	public function list_current_row( $key, $item ) {

		// Build list.
		$markup = '&mdash;';
		if ( ! empty( $item['current_groups'] ) ) {
			$markup = $this->markup_get_list_items( $item['current_groups'] );
		}

		// Echo markup.
		echo '<td>' . $markup . '</td>';

	}



	/**
	 * Show the Expired Groups.
	 *
	 * @since 0.4
	 *
	 * @param int $key The current key (type ID).
	 * @param array $item The current item.
	 */
	public function list_expiry_row( $key, $item ) {

		// Build list.
		$markup = '&mdash;';
		if ( ! empty( $item['expiry_groups'] ) ) {
			$markup = $this->markup_get_list_items( $item['expiry_groups'] );
		}

		// Echo markup.
		echo '<td>' . $markup . '</td>';

	}



	/**
	 * Show the Current Group.
	 *
	 * @since 0.4
	 *
	 * @param array $status_rules The status rules.
	 */
	public function rule_add_current( $status_rules ) {

		// Include template file.
		include( CIVI_WP_MEMBER_SYNC_PLUGIN_PATH . 'assets/templates/groups-add-current.php' );

	}



	/**
	 * Show the Expired Group.
	 *
	 * @since 0.4
	 *
	 * @param array $status_rules The status rules.
	 */
	public function rule_add_expiry( $status_rules ) {

		// Include template file.
		include( CIVI_WP_MEMBER_SYNC_PLUGIN_PATH . 'assets/templates/groups-add-expiry.php' );

	}



	/**
	 * Show the Current Group.
	 *
	 * @since 0.4
	 *
	 * @param array $status_rules The status rules.
	 * @param array $selected_rule The rule being edited.
	 */
	public function rule_edit_current( $status_rules, $selected_rule ) {

		// Build options.
		$options_html = '';
		if ( ! empty( $selected_rule['current_groups'] ) ) {
			$options_html = $this->markup_get_options( $selected_rule['current_groups'] );
		}

		// Include template file.
		include( CIVI_WP_MEMBER_SYNC_PLUGIN_PATH . 'assets/templates/groups-edit-current.php' );

	}



	/**
	 * Show the Expired Group.
	 *
	 * @since 0.4
	 *
	 * @param array $status_rules The status rules.
	 * @param array $selected_rule The rule being edited.
	 */
	public function rule_edit_expiry( $status_rules, $selected_rule ) {

		// Build options.
		$options_html = '';
		if ( ! empty( $selected_rule['expiry_groups'] ) ) {
			$options_html = $this->markup_get_options( $selected_rule['expiry_groups'] );
		}

		// Include template file.
		include( CIVI_WP_MEMBER_SYNC_PLUGIN_PATH . 'assets/templates/groups-edit-expiry.php' );

	}



	//##########################################################################



	/**
	 * Get the markup for a pseudo-list generated from a list of groups data.
	 *
	 * @since 0.4
	 *
	 * @param array $group_ids The array of group IDs.
	 */
	public function markup_get_list_items( $group_ids ) {

		// Init options.
		$options_html = '';
		$options = array();

		if ( ! empty( $group_ids ) ) {

			// Get the groups.
			$groups = Groups_Group::get_groups( array(
				'order_by' => 'name',
				'order' => 'ASC',
				'include' => $group_ids,
			));

			// Add options to build array.
			foreach( $groups AS $group ) {
				$options[] = esc_html( $group->name );
			}

			// Construct markup.
			$options_html = implode( "<br />\n", $options );

		}

		// --<
		return $options_html;

	}



	/**
	 * Get the markup for options generated from a list of groups data.
	 *
	 * @since 0.4
	 *
	 * @param array $group_ids The array of group IDs.
	 */
	public function markup_get_options( $group_ids ) {

		// Init options.
		$options_html = '';
		$options = array();

		if ( ! empty( $group_ids ) ) {

			// Get the groups.
			$groups = Groups_Group::get_groups( array(
				'order_by' => 'name',
				'order' => 'ASC',
				'include' => $group_ids,
			));

			// Add options to build array.
			foreach( $groups AS $group ) {
				$options[] = '<option value="' . $group->group_id . '" selected="selected">' . esc_html( $group->name ) . '</option>';
			}

			// Construct markup.
			$options_html = implode( "\n", $options );

		}

		// --<
		return $options_html;

	}



	//##########################################################################



	/**
	 * When an association rule is created, add capability to "Groups" plugin.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * @param array $data The association rule data.
	 */
	public function groups_add_cap( $data ) {

		// Add it as "read post" capability.
		$this->groups_read_cap_add( $data['capability'] );

		// Get existing capability.
		$capability = Groups_Capability::read_by_capability( $data['capability'] );

		// Bail if it already exists.
		if ( false !== $capability ) return;

		// Create a new capability.
		$capability_id = Groups_Capability::create( array( 'capability' => $data['capability'] ) );

	}



	/**
	 * When an association rule is edited, edit capability in "Groups" plugin.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * @param array $data The association rule data.
	 */
	public function groups_edit_cap( $data ) {

		// Same as add.
		$this->groups_add_cap( $data );

	}



	/**
	 * When an association rule is deleted, delete capability from "Groups" plugin.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * @param array $data The association rule data.
	 */
	public function groups_delete_cap( $data ) {

		// Delete from "read post" capabilities.
		$this->groups_read_cap_delete( $data['capability'] );

		// Get existing.
		$capability = Groups_Capability::read_by_capability( $data['capability'] );

		// Bail if it doesn't exist.
		if ( false === $capability ) return;

		// Delete capability.
		$capability_id = Groups_Capability::delete( $capability->capability_id );

	}



	/**
	 * Add "read post" capability to "Groups" plugin.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * @param array $capability The capability to add.
	 */
	public function groups_read_cap_add( $capability ) {

		// Init with Groups default.
		$default_read_caps = array( Groups_Post_Access::READ_POST_CAPABILITY );

		// Get current.
		$current_read_caps = Groups_Options::get_option( Groups_Post_Access::READ_POST_CAPABILITIES, $default_read_caps );

		// Bail if we have it already.
		if ( in_array( $capability, $current_read_caps ) ) return;

		// Add the new capability.
		$current_read_caps[] = $capability;

		// Resave option.
		Groups_Options::update_option( Groups_Post_Access::READ_POST_CAPABILITIES, $current_read_caps );

	}



	/**
	 * Delete "read post" capability from "Groups" plugin.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * @param array $capability The capability to delete.
	 */
	public function groups_read_cap_delete( $capability ) {

		// Init with Groups default.
		$default_read_caps = array( Groups_Post_Access::READ_POST_CAPABILITY );

		// Get current.
		$current_read_caps = Groups_Options::get_option( Groups_Post_Access::READ_POST_CAPABILITIES, $default_read_caps );

		// Get key if capability is present.
		$key = array_search( $capability, $current_read_caps );

		// Bail if we don't have it.
		if ( $key === false ) return;

		// Delete the capability.
		unset( $current_read_caps[$key] );

		// Resave option.
		Groups_Options::update_option( Groups_Post_Access::READ_POST_CAPABILITIES, $current_read_caps );

	}



	/**
	 * Before a manual sync, make sure "Groups" plugin is in sync.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 */
	public function groups_pre_sync() {

		// Get sync method.
		$method = $this->plugin->admin->setting_get_method();

		// Bail if we're not syncing capabilities.
		if ( $method != 'capabilities' ) return;

		// Get rules.
		$rules = $this->plugin->admin->rules_get_by_method( $method );

		// If we get some.
		if ( $rules !== false AND is_array( $rules ) AND count( $rules ) > 0 ) {

			// Add capability to "Groups" plugin if not already present.
			foreach( $rules AS $rule ) {
				$this->groups_add_cap( $rule );
			}

		}

	}



	/**
	 * Auto-restrict a post based on the post type.
	 *
	 * @since 0.2.3
	 * @since 0.3.9 Moved into this class.
	 *
	 * This is a placeholder in case we want to extend this plugin to handle
	 * automatic content restriction.
	 *
	 * @param int $post_id The numeric ID of the post.
	 * @param object $post The WordPress post object.
	 */
	public function groups_intercept_save_post( $post_id, $post ) {

		// Bail if something went wrong.
		if ( ! is_object( $post ) OR ! isset( $post->post_type ) ) return;

		// Do different things based on the post type.
		switch( $post->post_type ) {

			case 'post':
				// Add your default capabilities.
				Groups_Post_Access::create( array( 'post_id' => $post_id, 'capability' => 'Premium' ) );
				break;

			default:
				// Do other stuff.

		}

	}



} // Class ends.



