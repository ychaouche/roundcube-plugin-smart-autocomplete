<?php



class smart_autocomplete extends rcube_plugin
{



    /**
     * RC instance
     */
    public $rc;



    /**
     * DB Table name to be used
     */
    public $db_table_name = 'smart_autocomplete';



    /**
     * Do not autosuggest entries longer than X characters (autocollected noreply addresses)
     */
    public $max_email_length = 40;



    /**
     * RC plugin initialization routine
     */
    public function init ()
    {
        $this->rc = rcmail::get_instance();
        $this->dbh = $this->rc->get_dbh();

        // Get configuration
        $this->db_table_name = $this->rc->config->get('db_table_autocomplete', $this->rc->db->table_name($this->db_table_name));
        $this->max_email_length = $this->rc->config->get('smart_autocomplete_max_email_length', $this->max_email_length);

        // Backend tasks
        if ($this->rc->task == 'mail') {
            $this->add_hook('contacts_autocomplete_after', array($this, 'contacts_autocomplete_search'));
        }
        $this->register_action('plugin.smart_autocomplete.register_accepted_suggestion', array($this, 'register_accepted_suggestion'));

        // Frontend
        $this->include_script('smart_autocomplete.js');
    }



    /**
     * HOOK CALLBACK: Take suggestions that RC came up with, and enhance them
     */
    public function contacts_autocomplete_search ($args)
    {
        $search_string  = $args['search'];
        $rc_suggestions = $args['contacts'];

        // Generate array keys for RC suggestions, to be able to remove duplicates when merging
        $rc_suggestions = $this->_rc_suggestions_generate_uniq_ids($rc_suggestions);

        // Search for learned autosuggestions
        $exact_matches  = $this->_search_for_matches($search_string, 'exact');
        $prefix_matches = $this->_search_for_matches($search_string, 'prefix');

        // Merge final contacts array - later keys do not overwrite earlier ones,
        // effectively shedding duplicates.
        $contacts_assoc = $exact_matches + $prefix_matches + $rc_suggestions;

        // Ignore contacts with too long emails
        foreach ($contacts_assoc as $contact_id => $contact_data) {
            if (!empty($contact_data['email'])) {
                if (strlen($contact_data['email']) > $this->max_email_length) {
                    unset($contacts_assoc[$contact_id]);
                }
            }
        }

        // Limit output to X entries, as configured in RC
        $config_autocomplete_max = (int) $this->rc->config->get('autocomplete_max', 15);
        $contacts_assoc = array_slice($contacts_assoc, 0, $config_autocomplete_max);

        // Replace associative keys with indexed ones
        // (if not, autosuggestions stop working entirely as JSON does not support associative arrays)
        $contacts = array_values($contacts_assoc);

        return array('contacts' => $contacts);
    }



    /**
     * Generate uniq IDs for RC suggestions
     */
    protected function _rc_suggestions_generate_uniq_ids ($rc_suggestions)
    {
        $rc_suggestions_assoc = array();
        foreach ($rc_suggestions as $rc_suggestion) {
            $contact_uniq_id = $this->_generate_uniq_id($rc_suggestion);
            $rc_suggestions_assoc[$contact_uniq_id] = $rc_suggestion;
        }
        return $rc_suggestions_assoc;
    }



    /**
     * Generate uniq ID for contact
     */
    protected function _generate_uniq_id ($contact_data)
    {
        if ('group' == $contact_data['type']) {
            $contact_uniq_id = $contact_data['source'] .'-group-'. $contact_data['id'];
        } else {
            $contact_uniq_id = $contact_data['source'] .'-person-'. $contact_data['id'] .'-'. $contact_data['email'];
        }
        return $contact_uniq_id;
    }



    /**
     * Do the actual match searching
     */
    protected function _search_for_matches ($search_string, $mode='exact')
    {
        switch ($mode) {
            case 'exact':
                $search_string_query = "search_string = '$search_string'";
                break;
            case 'prefix':
                $search_string_query = "search_string LIKE '$search_string%' AND search_string != '$search_string'";
                break;
            default:
                throw new Exception("Internal error, invalid search mode: $mode");
        }

        // Check if entry is already present in database
        $result = $this->rc->db->query("
            SELECT *
            FROM " . $this->db_table_name . "
            WHERE
                1
                AND user_id = ?
                AND $search_string_query
            ORDER BY
                accepted_count DESC,
                accepted_datetime_last DESC
            ",
            $this->rc->user->ID
        );

        $contacts = array();
        while ($result && ($suggestion = $this->rc->db->fetch_assoc($result))) {
            $abook = $this->rc->get_address_book($suggestion['accepted_source']);

            // Handle: group
            if ('group' == $suggestion['accepted_type']) {
                $group_data = $abook->get_group($suggestion['accepted_id']);
                // TODO FIXME add code to remove data about groups that are no longer present

                // Get group members count
                $abook->reset();
                $abook->set_group($suggestion['accepted_id']);
                $group_members_tmp   = $abook->count();
                $group_members_count = $group_members_tmp->count;
                $abook->reset();

                $contact = array(
                    'type'   => 'group',
                    'name'   => $group_data['name'] . ' (' . intval($group_members_count) . ')',
                    'id'     => $suggestion['accepted_id'],
                    'source' => $suggestion['accepted_source'],
                );

                $contact_uniq_id = $this->_generate_uniq_id($contact);
            }
            // Handle: regular person
            else {

                $contact_data = $abook->get_record($suggestion['accepted_id'], true);
                // TODO FIXME add code to remove data about people that are no longer present

                $email = $suggestion['accepted_email'];
                // FIXME TODO Check if email is still present in the contact, otherwise use default/first one

                // Ignore contacts with too long emails
                if (strlen($email) > $this->max_email_length) {
                    continue;
                }

                // Format contact
                $name_tmp     = rcube_addressbook::compose_list_name($contact_data);
                $contact_name = format_email_recipient($email, $name_tmp);

                $contact = array(
                    'type'   => 'person',
                    'name'   => $contact_name,
                    'email'  => $email,
                    'id'     => $suggestion['accepted_id'],
                    'source' => $suggestion['accepted_source'],
                );

                $contact_uniq_id = $this->_generate_uniq_id($contact);
            }

            $contacts[$contact_uniq_id] = $contact;
        }

        return $contacts;
    }



    /*
     * ACTION: Callback for storing accepted autosuggestions via Ajax call
     */
    public function register_accepted_suggestion ()
    {
        $search_string   = rcube_utils::get_input_value('search_string',   rcube_utils::INPUT_GPC);
        $accepted_type   = rcube_utils::get_input_value('accepted_type',   rcube_utils::INPUT_GPC);
        $accepted_id     = rcube_utils::get_input_value('accepted_id',     rcube_utils::INPUT_GPC);
        $accepted_email  = rcube_utils::get_input_value('accepted_email',  rcube_utils::INPUT_GPC);
        $accepted_source = rcube_utils::get_input_value('accepted_source', rcube_utils::INPUT_GPC);

        // Validate input
        if (!preg_match('/^.+$/', $search_string)) {
            throw new Exception("Invalid input: search_string=$search_string");
        }
        if (!preg_match('/^[a-z]+$/', $accepted_type)) {
            throw new Exception("Invalid input: accepted_type=$accepted_type");
        }
        if (!preg_match('/^[1-9][0-9]*$/', $accepted_id)) {
            throw new Exception("Invalid input: accepted_id=$accepted_id");
        }
        if (!empty($accepted_email)) {
            if (!rcube_utils::check_email(rcube_utils::idn_to_ascii($accepted_email))) {
                throw new Exception("Invalid input: accepted_email=$accepted_email");
            }
        }
        if (!preg_match('/^[a-z]+$/', $accepted_source)) {
            throw new Exception("Invalid input: accepted_source=$accepted_source");
        }


        // Fix contact type - if empty, make it "person"
        if (empty($accepted_type)) {
            $accepted_type = 'person';
        }


        // Check if entry is already present in database
        if (empty($accepted_email)) { // Group probably
            $result = $this->rc->db->query("
                SELECT id
                FROM " . $this->db_table_name . "
                WHERE
                    1
                    AND user_id = ?
                    AND search_string   = ?
                    AND accepted_type   = ?
                    AND accepted_id     = ?
                    AND accepted_email IS NULL
                    AND accepted_source = ?
                ",
                $this->rc->user->ID,
                $search_string,
                $accepted_type,
                $accepted_id,
                $accepted_source
            );
        } else { // Regular contact
            $result = $this->rc->db->query("
                SELECT id
                FROM " . $this->db_table_name . "
                WHERE
                    1
                    AND user_id = ?
                    AND search_string   = ?
                    AND accepted_type   = ?
                    AND accepted_id     = ?
                    AND accepted_email  = ?
                    AND accepted_source = ?
                ",
                $this->rc->user->ID,
                $search_string,
                $accepted_type,
                $accepted_id,
                $accepted_email,
                $accepted_source
            );
        }
        if ($result && ($suggestion = $this->rc->db->fetch_assoc($result))) {
            $result = $this->rc->db->query("
                UPDATE ". $this->db_table_name ."
                SET
                    accepted_count         = accepted_count+1,
                    accepted_datetime_last = NOW()
                WHERE id = ?",
                $suggestion['id']
            );
        } else {
            $result = $this->rc->db->query("
                INSERT INTO ". $this->db_table_name ."
                SET
                    user_id = ?,
                    search_string   = ?,
                    accepted_type   = ?,
                    accepted_id     = ?,
                    accepted_email  = ?,
                    accepted_source = ?,
                    accepted_count          = 1,
                    accepted_datetime_first = NOW(),
                    accepted_datetime_last  = NOW()
                ",
                $this->rc->user->ID,
                $search_string,
                $accepted_type,
                $accepted_id,
                $accepted_email,
                $accepted_source
            );
        }

        /*
         * THINK TODO:
         * Check if longer search strings for this entry already exist,
         * and delete them?
         * Now they even out eventually. Maybe once shorter prefix accepted_count is larger than longer one?
         */
    }
}
