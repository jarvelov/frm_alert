<?php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

/** Frm_Alert_Field
* Adds an alert field to Formidable which can trigger actions on certain events such as
    a field's value is updated,
    value has not been updated for a specific time period
    etc.
* Notes: Requires Formidable v2.0+
*/

class Frm_Alert_Field Extends Frm_Alert {

    function __construct() {
        //Load styles and scripts
        if( class_exists('Frm_Alert') ) {
            $controller = $this->init_controller();
            $controller->load_file('frm_alert_field_css', 'css/frm_alert_field.css');
            $controller->load_file('frm_alert_field_js', 'js/frm_alert_field.js', true);
        }

        //Add alert field to available Formidable fields
        add_filter('frm_pro_available_fields', array($this, 'add_alert_field') );

        //Set up default settings for alert field
        add_filter('frm_before_field_created', array($this, 'set_alert_field_defaults') );

        //Show the field in the form builder
        add_action('frm_display_added_fields', array($this, 'alert_field_admin') );

        //Create field options
        add_action('frm_field_options_form', array($this, 'alert_field_options'), 10, 3);

        //Show field in the front end
        add_action('frm_form_fields', array($this, 'alert_field_front_end'), 10, 2);

        //Update field settings when saving
        add_filter( 'frm_update_field_options', array($this, 'alert_field_update_options'), 10, 3 );
    }

    private function init_controller() {
        try {
            return new Frm_Alert_Controller();
        } catch(Exception $e) {
            return false;
        }
    }

    /** get_alert_field_defaults()
    * Returns an array with default values
    **/
    private function get_alert_field_defaults() {
        $defaults_array = array(
            'operators' => array(
                 '==' => 'equal to',
                 '!=' => 'NOT equal to',
                 '>' => 'greater than',
                 '<' => 'less than',
                 '>=' => 'greater than or equal to',
                 '<=' => 'less than or equal to'
            ),
            'repeat_period' => array(
                'hourly' => 'Hourly',
                'twicedaily' => 'Twice Daily',
                'daily' => 'Daily'
            ),
            'trigger_action_on' => array(
                'created' => 'Created',
                'updated' => 'Updated',
                'created_updated' => 'Created and updated'
            ),
            'schedule_delay_units' => array(
                1 => 'seconds',
                60 => 'minutes',
                3600 => 'hours',
                86400 => 'days'
            ),
            'actions' => array(
                'email' => 'Send E-mail',
                'frm_action' => 'Trigger Formidable Action'
            ),
            'alert_trigger_value' => NULL,
            'alert_trigger_value_select' => NULL,
            'alert_trigger_value_custom_value' => NULL,
            'trigger_fields_select' => NULL,
            'trigger_field_condition_operator' => NULL,
            'trigger_field_action' => NULL,
            'alert_action_email' => NULL,
            'alert_action_frm_action' => NULL,
            'alert_delay_active' => 'off',
            'alert_schedule_delay_unit' => NULL,
            'alert_schedule_delay_number' => NULL,
            'alert_trigger_action' => NULL
        );

        return $defaults_array;
    }

    private function get_form_field_names_and_values($form_id) {
        $controller = $this->init_controller();
        $fields = $controller->get_form_field_names_and_values($form_id);

        return $fields;
    }

    /** add_alert_field
    * Add alert field to available formidable fields
    * Hook for: frm_pro_available_fields
    */
    public function add_alert_field($fields){
        $fields['frm_alert_field'] = __('Alert Field'); // the key for the field and the label
        return $fields;
    }

    /** set_alert_field_defaults
    * Set default options for the alert field
    * Hook for: frm_before_field_created
    */
    public function set_alert_field_defaults($field_data){
        if($field_data['type'] != 'frm_alert_field'){
            return $field_data;
        }

        $field_data['name'] = __('Alert Field');
        $defaults = $this->get_alert_field_defaults();

        foreach($defaults as $key => $value) {
            $field_data['field_options'][$key] = $value;
        }

        return $field_data;
    }

    /** alert_field_admin
    * Add button to display in the in form builder
    * Hook for: frm_display_added_fields
    */
    public function alert_field_admin($field){
        if ( $field['type'] != 'frm_alert_field') {
          return;
        }

        $field_name = 'item_meta['. $field['id'] .']';
    }

    /** alert_field_update_options
    * Update field option values when the form is updated
    * Hook for: frm_update_field_options
    */
    public function alert_field_update_options( $field_options, $field, $values ) {
        if($field->type != 'frm_alert_field')
            return $field_options;

        $defaults = $this->get_alert_field_defaults();

        foreach ($defaults as $option => $default_value) {
            $field_options[ $option ] = isset( $values['field_options'][ $option . '_' . $field->id ] ) ? $values['field_options'][ $option . '_' . $field->id ] : $default_value;
        }

        return $field_options;
    }

    /** get_alert_condition_fields
    *
    */
    private function get_alert_condition_fields($field, $form_fields) {
        $defaults = $this->get_alert_field_defaults();
        $trigger_field = '<select name="field_options[trigger_fields_select_' . $field['id'] . '" class="trigger_fields_select">';
        $trigger_field .= '<option value="">— Select —</option>';
        $trigger_values = NULL;
        //trigger_fields
        foreach ($form_fields as $key => $value) {
            $trigger_field_selected = selected($field['trigger_fields_select'], $key, false );
            $trigger_field .= '<option value="' . $key . '" ' . $trigger_field_selected . '>' . $value['name'] . '</option>';
            $trigger_value_show = ($trigger_field_selected) ? 'active_value' : 'inactive_value';
            $trigger_values .= '<div class="alert_trigger_fields_container ' . $trigger_value_show . '" id="alert_trigger_value_option_' . $key . '">';

            //trigger_values
            if( is_array($value['value']) ) {
                $trigger_values .= '<select name="field_options[alert_trigger_value_select_' . $field['id'] . ']" id="alert_trigger_value_' . $key . '" class="alert_trigger_value_select">';
                $trigger_values .= '<option value="">— Select —</option>';

                foreach ($value['value'] as $value_key => $value_value) {
                    $trigger_value_selected = selected($field['alert_trigger_value_select'], $value_value['value'], false );
                    $trigger_values .= '<option value="' . $value_value['value'] . '" ' . $trigger_value_selected . '>' . $value_value['label'] . '</option>';
                }

                $trigger_value_selected = selected($field['alert_trigger_value_select'], 'custom_value', false );
                $trigger_value_custom_show = ($trigger_value_selected) ? 'active_value' : 'inactive_value';
                $trigger_values .= '<option value="custom_value" ' . $trigger_value_selected . '>Custom Value</option>';
                $trigger_values .= '</select>';

                $trigger_values .= '<input type="text" name="field_options[alert_trigger_value_custom_value_' . $field['id']  . ']" placeholder="Enter a custom value" value="' . esc_attr($field['alert_trigger_value_custom_value']) . '" id="alert_trigger_value_custom_value_' . $key . '" class="alert_trigger_value_custom_value ' . $trigger_value_custom_show . '" />';
            } else {
                $trigger_values .= '<input type="text" name="field_options[alert_trigger_value_' . $field['id'] . ']" value="' . esc_attr($field['alert_trigger_value']) . '" id="alert_trigger_value_' . $key . '" />';
            }

            $trigger_values .= '</div>'; // /.alert_trigger_fields_container
        }
        $trigger_field .= '</select>';

        //trigger condition operators
        $trigger_operator = '<select name="field_options[trigger_field_condition_operator_' . $field['id'] . ']">';
        $trigger_operator .= '<option value="">— Select —</option>';
        foreach ($defaults['operators'] as $key => $value) {
            $selected = selected($field['trigger_field_condition_operator'], $key, false );
            $trigger_operator .= '<option value="' . htmlspecialchars($key) . '" '. $selected . '>' . $value . '</option>';
        }
        $trigger_operator .= '</select>';

        $html = '<div class="alert_settings_container">';

        $html .= '<div class="alert_trigger_field_container">';
        $html .= $trigger_field;
        $html .= '</div>'; // /.alert_trigger_field_container

        $html .= '<div class="alert_trigger_operator_container">';
        $html .= $trigger_operator;
        $html .= '</div>'; // /.alert_trigger_operator_container

        $html .= '<div class="alert_trigger_value_container">';
        $html .= $trigger_values;
        $html .= '</div>'; // /.alert_trigger_value_container

        $html .= '</div>'; // /.alert_settings_container

        return $html;
    }
    /** get_alert_action_fields
    *
    */
    private function get_alert_action_fields($field) {
        $defaults = $this->get_alert_field_defaults();

        //What action to perform when conditions are met
        $trigger_action = '<select name="field_options[trigger_field_action_' . $field['id'] . ']" class="trigger_field_action">';
        $trigger_action .= '<option value="">— Select —</option>';
        foreach ($defaults['actions'] as $key => $value) {
            $selected = selected($field['trigger_field_action'], $key, false );
            $selected_key = (isset($selected_key) AND ($selected_key !== false)) ? $selected_key : ($selected ? $key : false);
            $trigger_action .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
        }
        $trigger_action .= '</select>';

        //Actions
        $active = ($selected_key == 'email') ? 'active_value' : 'inactive_value';
        $alert_action_fields = '<div class="alert_action_field ' . $active . '" id="alert_action_email">';
        $alert_action_fields .= '<input type="email" name="field_options[alert_action_email_' . $field['id'] . ']" class="alert_actions" id="alert_action_email_value" placeholder="Ex. me@mydomain.com" value="' . $field['alert_action_email'] . '" />';
        $alert_action_fields .= '</div>'; // /.alert_action_field

        $active = ($selected_key == 'frm_action') ? 'active_value' : 'inactive_value';

        $alert_action_fields .= '<div class="alert_action_field ' . $active . '" id="alert_action_frm_action">';
        $alert_action_fields .= '<input type="number" name="field_options[alert_action_frm_action_' . $field['id'] . ']" class="alert_actions" id="alert_action_frm_action_value" placeholder="Formidable ID, ex. 1388" value="' . $field['alert_action_frm_action'] . '" />';
        $alert_action_fields .= '</div>'; // /.alert_action_field

        $html = '<div class="alert_actions_container">';

        $html .= '<div class="alert_trigger_action_container">';
        $html .=  $trigger_action;
        $html .= '</div>'; // /.alert_trigger_action_container

        $html .= '<div class="alert_action_fields_container">';
        $html .= $alert_action_fields;
        $html .= '</div>'; // /.alert_action_fields_container

        $html .= '</div>'; // /.alert_actions_container

        return $html;
    }

    /** get_alert_schedule_fields
    *
    */
    private function get_alert_schedule_fields($field) {
        $defaults = $this->get_alert_field_defaults();

        $delay_active = checked( $field['alert_delay_active'], 'on', false );
        $active = ($delay_active) ? 'active_value' : 'inactive_value';
        //Delay start - i.e. when to trigger alert action the first time

        //How many units to delay triggering action for
        $schedule_delay = '<div class="alert_setting ' . $active . '">';
        $schedule_delay .= '<label class="alert_label" for="schedule_delay_number">Delay for</label>';
        $schedule_delay .= '<input type="number" name="field_options[alert_schedule_delay_number_' . $field['id'] . ']" id="schedule_delay_number" value="' . esc_attr($field['alert_schedule_delay_number']) . '"/>';

        //Trigger delay time units
        $schedule_delay .= '<select name="field_options[alert_schedule_delay_unit_' . $field['id'] . ']" id="schedule_delay_units">';
        $schedule_delay .= '<option value="">— Select —</option>';
        foreach ($defaults['schedule_delay_units'] as $key => $value) {
            $selected = selected($field['alert_schedule_delay_unit'], $key, false );
            $schedule_delay .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
        }
        $schedule_delay .= '</select>';
        $schedule_delay .= '</div>'; // /.alert_setting

        //Action is scheduled on this event
        $schedule_start = '<div class="alert_start_setting">';
        $schedule_start .= '<label class="alert_label" for="trigger_action_on">Schedule action when an entry is </label>';
        $schedule_start .= '<select name="field_options[alert_trigger_action_' . $field['id'] . ']" id="trigger_action_on">';
        $schedule_start .= '<option value="">— Select —</option>';
        foreach ($defaults['trigger_action_on'] as $key => $value) {
            $selected = selected($field['alert_trigger_action'], $key, false );
            $schedule_start .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
        }
        $schedule_start .= '</select>';
        $schedule_start .= '</div>'; // /.alert_start_setting

        $html = '<div class="alert_schedule_container">';
        $html .= '<div class="alert_schedule_start_container">';
        $html .= $schedule_start;
        $html .= '</div>'; // /.alert_schedule_start_container

        $html .= '<div class="alert_delay_container">';
        $html .= '<div class="alert_active_container">';
        $html .= '<label class="alert_label" for="alert_delay_active">Delay action</label>';
        $html .= '<input type="checkbox" id="alert_delay_active" name="field_options[alert_delay_active_' . $field['id'] . ']" '  . $delay_active . ' />';
        $html .= '</div>'; // /.alert_active_container
        $html .= $schedule_delay;
        $html .= '</div>'; // /.alert_delay_container

        $html .= '</div>'; // /.alert_schedule_container

        return $html;
    }


    /** alert_field_options
    * Add options to configure field in form builder
    * Hook for: frm_field_options_form
    */
    public function alert_field_options($field, $display, $values){
        if ( $field['type'] != 'frm_alert_field' ) {
          return;
        }

        $defaults = $this->get_alert_field_defaults();

        foreach($defaults as $key => $value) {
            if( !isset($field[$key])) {
                $field[$key] = $value;
            }
        }

        //Get all fields in form to build trigger alert option
        $form_id = intval($field['form_id']);
        $form_fields = $this->get_form_field_names_and_values($form_id);

        $html = '<tr><td><label>Alert Condition</label></td><td>';

        $html .= $this->get_alert_condition_fields($field, $form_fields);

        $html .= '</td></tr>';

        $html .= '<tr><td><label>Alert Action</label></td><td>';

        $html .= $this->get_alert_action_fields($field);

        $html .= '</td></tr>';

        $html .= '<tr><td><label>Alert Schedule</label></td><td>';

        $html .= $this->get_alert_schedule_fields($field);

        $html .= '</td></tr>';

        echo $html;
    }

    /** alert_field_front_end
    * Hook for: frm_form_fields
    * Show alert field when form is viewed on the front end
    */
    public function alert_field_front_end($field, $field_name){
      if ( $field['type'] != 'frm_alert_field' ) {
        return;
      }

      $field['value'] = stripslashes_deep($field['value']);
    ?>
    <input type="text" id="field_<?php echo $field['field_key'] ?>" name="item_meta[<?php echo $field['id'] ?>" value="<?php echo esc_attr($field['value']) ?>" <?php do_action('frm_field_input_html', $field) ?>/>
    <?php
    }
}

?>
