<?php

/**
 * Document pages
 *
 * @author Marcus Johansson <me @ marcusmailbox.com>
 * @version 0.10-beta
 */
class controllerDocument extends router
{
    /**
     * Deletes a document
	 * 
     * @param array $args Page arguments
	 * 
     * @return array Variables to render a page
     */		
    public function page_delete_document($args)
    {
        $_SESSION['delete_' . $args[2]] = true;
        $args['index'] = $args[0];
        $args['document_type'] = $args[1];
        $args['name'] = $args[2];

        $vars['content'] = $this->renderPart('document_delete', $args);
        $vars['title'] = 'Delete ' . $args[0];

        return $vars;
    }

    /**
     * Confirms deletion of a document
	 * 
     * @param array $args Page arguments
	 * 
     * @return array Variables to render a page
     */		
    public function page_delete_document_confirm($args)
    {
        if (isset($_SESSION['delete_' . $args[2]])) {
            unset($_SESSION['delete_' . $args[2]]);
            self::$query_loader->callWithCheck($args[0] . '/' . $args[1] . '/' . $args[2], 'DELETE', '', 'document/search_documents/' . $args[0]);
        }
        trigger_error('Not correctly done', E_USER_ERROR);
    }

    /**
     * Edit a document
	 * 
     * @param array $args Page arguments
	 * 
     * @return array Variables to render a page
     */	
    public function page_edit_document($args)
    {
        $link = implode('/', $args);

        $state = self::$query_loader->call('_cluster/state', 'GET');

        if (!isset($state['metadata']['indices'][$args[0]]['mappings'][$args[1]])) {
            trigger_error("No mapping exists for " . $args[1], E_USER_ERROR);
        }

        $fields[] = '_parent';
        foreach ($state['metadata']['indices'][$args[0]]['mappings'][$args[1]]['properties'] as $field => $data) {
            $fields[] = $field;
        }

        $args['mappings'] = $state['metadata']['indices'][$args[0]]['mappings'][$args[1]];

        $result = self::$query_loader->call($args[0] . '/' . $args[1] . '/_search', 'POST', '{"fields":' . json_encode($fields) . ',"query":{"ids":{"values":["' . $args[2] . '"]}},"from": "0","size": "1"}');

        $args['data'] = $result['hits']['hits'][0];

        $form = new form($this->form_create_document($args));

        $form->createForm();

        $arguments['field'] = $args[0];
        $arguments['form'] = $form->renderForm();
        $vars['javascript'][] = 'custom/forms.js';
        $vars['javascript'][] = 'custom/edit_documents.js';
        $vars['content'] = $this->renderPart('document_edit_document', $arguments);
        $vars['title'] = 'Edit document: ' . $args[2];

        return $vars;
    }

    /**
     * Create a document
	 * 
     * @param array $args Page arguments
	 * 
     * @return array Variables to render a page
     */	
    public function page_create_document($args)
    {
        $state = self::$query_loader->call('_cluster/state', 'GET');

        if (!isset($state['metadata']['indices'][$args[0]]['mappings'][$args[1]])) {
            trigger_error("No mapping exists for " . $args[1], E_USER_ERROR);
        }

        $args['mappings'] = $state['metadata']['indices'][$args[0]]['mappings'][$args[1]];

        $form = new form($this->form_create_document($args));

        $form->createForm();

        $arguments['field'] = $args[0];
        $arguments['form'] = $form->renderForm();
        $vars['javascript'][] = 'custom/forms.js';
        $vars['javascript'][] = 'custom/edit_documents.js';
        $vars['content'] = $this->renderPart('document_create_document', $arguments);
        $vars['title'] = 'Create document of type: ' . $args[1];

        return $vars;
    }

    /**
     * Json document for creating a nested form
	 * 
     * @param array $args Page arguments
     */	
    public function page_form_nested($args)
    {
        $state = self::$query_loader->call('_cluster/state', 'GET');

        if (!isset($state['metadata']['indices'][$args[0]]['mappings'][$args[1]])) {
            trigger_error("No mapping exists for " . $args[1], E_USER_ERROR);
        }

        $args['mappings'] = self::$query_loader->nestMapping($state['metadata']['indices'][$args[0]]['mappings'][$args[1]], explode('.', $args[2]));
		
        $form = new form($this->form_nested($args));

        echo json_encode(array('form' => $form->createFields(), 'test' => $args[2]));
    }

    /**
     * Create a document post page
	 * 
     * @param array $args Page arguments
     */	
    public function page_create_document_post($args)
    {
        $state = self::$query_loader->call('_cluster/state', 'GET');

        if (!isset($state['metadata']['indices'][$args[0]]['mappings'][$args[1]])) {
            trigger_error("No mapping exists for " . $args[1], E_USER_ERROR);
        }

        $args['mappings'] = $state['metadata']['indices'][$args[0]]['mappings'][$args[1]];
		    	
        $form = new form($this->form_create_document($args));
        $results = $form->getResults();

        $id = $results['_id'];
        $_SESSION['create_another'] = isset($results['create_another']) && $results['create_another'] ? 1 : 0;

        $idprint = '';
        if($id) $idprint = '/' . $id;

        $parent = isset($results['_parent']) && $results['_parent'] ? '?parent=' . $results['_parent'] : '';

        $url = $results['index'] . '/' . $results['document_type'] . $idprint . $parent;

        unset($results['_id']);
        unset($results['submit']);
        unset($results['index']);
        unset($results['document_type']);
        unset($results['create_another']);
        unset($results['_parent']);

        $postdata = self::$query_loader->nonAssocArrays($results);

        $redirect = $_SESSION['create_another'] ? 'document/create_document/' . $args[0] . '/' . $args[1] : 'document/search_documents/' . $args[0];
        self::$query_loader->callWithCheck($url, 'POST', json_encode($postdata), $redirect);

        $this->redirect('document/search_documents/' . $args[0]);
    }

    /**
     * search document
	 * 
     * @param array $args Page arguments
	 * 
     * @return array Variables to render a page
     */	
    public function page_search_documents($args)
    {
        //refresh
        self::$query_loader->call('_refresh', 'POST');

        $operator = $this->getString('operator', 'AND');
        $query = $this->getString('search', '');
        $type = $this->getString('type');
        $fields = is_array($this->getString('fields')) ? $this->getString('fields') : array();

        $args['operator'] = $operator;
        $args['query'] = $query;
        $args['type'] = $type;
        $args['mapping_types'] = array('' => 'All');

        $state = self::$query_loader->call('_cluster/state', 'GET');

        $mapping = $state['metadata']['indices'][$args[0]]['mappings'];
        foreach ($mapping as $key => $value) {
            $args['mapping_types'][$key] = $key;

            $types[] = $key;
            $mapfields[$key] = self::$query_loader->getValueFields($value);
        }

        foreach ($mapfields as $key => $value) {
            foreach ($value as $mapkey => $map) {
                foreach ($map as $datafield) {
                    $trimmed = trim($datafield, '.');
                    $newfields[$trimmed] = $trimmed;
                }
            }
        }
        $args['fields'] = $newfields;
        $args['chosen_fields'] = $fields;
        $form = new form($this->form_search_documents($args));

        $form->createForm();

        $arguments['form'] = $form->renderForm();

        $typestring = '';
        if($type) $typestring = '/' . $type;

        if ($query) {
            $data['query']['text']['_all']['query'] = $query;
            if ($operator) {
              	$data['query']['text']['_all']['operator'] = $operator;
            }
        } else {
            $data['query']['match_all'] = array();
        }

        $url = $args[0] . $typestring . '/_search';

        $result = self::$query_loader->call($url, 'GET', json_encode($data));

        $i = 0;
        $newresults = array();
        foreach ($result['hits']['hits'] as $resultdata) {
            $link = 'document/edit_document/' . $args[0] . '/' . $resultdata['_type'] . '/' . $resultdata['_id'];

            $newresults[$i]['_id'] = l($link, $resultdata['_id']);
            $newresults[$i]['_document'] = $resultdata;

            foreach ($fields as $field) {
                $newresults[$i][$resultdata['_type'] . '.' . $field] = '';
            }

            foreach ($fields as $field) {
                $parts = explode('.', $field);
                $result = self::$query_loader->findResult($parts, $resultdata['_source']);
                if ($result) {
                    $newresults[$i][$resultdata['_type'] . '.' . $field] = l($link, substr($result, 0, 50));
                }
            }
            $i++;
        }

        $arguments['data'] = '<table>';

        $arguments['data'] .= '<tr><td>_id</id>';
        if (isset($args['fields'])) {
            foreach ($args['fields'] as $field) {
                if (in_array($field, $fields)) {
                    $arguments['data'] .= '<td>' . $field . '</td>';
                }
            }
        }
        $arguments['data'] .= '</tr>';

        $t = 0;
        $colspan = count($fields)+1;
        foreach ($newresults as $result) {
            $class = $t&1 ? 'even' : 'odd';
            $arguments['data'] .= '<tr name="row_' . $t . '" class="title ' . $class . '">';
            $i = 0;
            foreach ($result as $key => $col) {
                if ($key != '_document') {
                    $class = $i&1 ? 'class="even"' : 'class="odd"';
                    $arguments['data'] .= '<td ' . $class . '>' . $col . '</td>';
                    $i++;
                }
            }
            $arguments['data'] .= '</tr><tr id="row_' . $t . '_document" class="fulldocument"><td colspan=' . $colspan .'><pre>';
            $arguments['data'] .= self::$query_loader->prettyJson(json_encode($result['_document'])) . '</pre></td></tr>';
            $t++;
        }

        $arguments['data'] .= '</table>';

        $vars['javascript'][] = 'custom/forms.js';
        $vars['javascript'][] = 'custom/edit_documents.js';
        $vars['content'] = $this->renderPart('document_search_documents', $arguments);
        $vars['title'] = 'Search documents';

        return $vars;
    }

    /**
     * search document post page
	 * 
     * @param array $args Page arguments
     */	
    public function page_search_documents_post($args)
    {
        $form = new form($this->form_search_documents($args));
        $results = $form->getResults();

        unset($results['submit']);

        $this->redirect('document/search_documents/' . $args[0], $results);
    }
	
    /**
     * Form for creating/editing documents
	 * 
     * @param array $args Form arguments
	 * 
     * @return array Form array
     */	
    private function form_create_document($args)
    {
        $args[0] = isset($args[0]) ? $args[0] : '';
        $args[1] = isset($args[1]) ? $args[1] : '';
		
        $form['_init'] = array(
            'name' => 'create_document',
            'action' => 'document/create_document_post/' . $args[0] . '/' . $args[1]
        );

        $form['document_type'] = array(
            '_value' => $args[1],
            '_type' => 'hidden'
        );

        $form['index'] = array(
            '_value' => $args[0],
            '_type' => 'hidden'
        );

        $form['doc'] = array(
            '_type' => 'fieldset'
        );

        $form['doc']['_id'] = array(
            '_type' => isset($args['data']['_id']) ? 'hidden' : 'textField',
            '_label' => '_id',
            '_description' => 'Elasticsearch id. If not added, elasticsearch will create an automatic id.',
            '_value' => isset($args['data']['_id']) ? $args['data']['_id'] : '',
        );
		
		if (!isset($args['data']['fields'])) {
			$args['data']['fields'] = array();
		}
       	$newform['doc'] = $this->create_form_field($args['mappings'], $args['data']['fields']);
		
        $form = array_merge_recursive($form, $newform);

        if (isset($args['mappings']['_parent'])) {
            $form['doc']['_parent'] = array(
                '_type' => 'textField',
                '_label' => 'Parent id',
                '_description' => 'If you want to make this a child, specify the parent id.',
                '_value' => isset($args['data']['fields']['_parent']) ? $args['data']['fields']['_parent'] : ''
            );
        }

        if (isset($args[2])) {
            $form['doc']['delete'] = array(
                '_value' => 'Delete document',
                '_type' => 'button'
            );
        } else {
            $form['doc']['create_another'] = array(
                '_type' => 'checkbox',
                '_label' => 'Create another ' . $args[1],
                '_value' => isset($_SESSION['create_another']) && $_SESSION['create_another'] ? true : false
            );
        }

        $form['doc']['submit'] = array(
            '_value' => 'Save document',
            '_type' => 'submit'
        );

        return $form;
    }

    /**
     * Form for creating each field in the create document form
	 * 
     * @param array $args Form arguments
	 * 
     * @return array Form array
     */	
    private function create_form_field($mappings, $fields, $parent = '', $newkey = 0)
    {
        if (isset($mappings['properties'])) {
            foreach ($mappings['properties'] as $name => $data) {
                $typename = isset($data['type']) ? $data['type'] : '';

                $labelname = $name;
                $realname = $name;
                if ($parent) {
                    $labelname = $name;
                    $name = $parent . '[' . $newkey . '][' . $name . ']';
                }

                $form[$name]['_label'] = $labelname;

                if (isset($data['null_value'])) {
                    $form[$name]['_value'] = $data['null_value'];
                }
				
                switch ($typename) {
                    case 'string':
                        $form[$name]['_type'] = 'textArea';
                        $form[$name]['_rows'] = 2;
						$form[$name]['_label'] = $labelname;
						$form[$name]['_underlabel'] = '(' . $typename . ')' . ' ("," delimiter for multiple values, eg "flight","mode")';
                        break;
					case 'boolean':
						$form[$name]['_type'] = 'radios';
						$form[$name]['_options'] = array(
							1 => 'true',
							0 => 'false'
						);
						break;
                    case 'nested':
						$form[$name]['_type'] = 'nested';
						break;
                    case 'object':
                    case '':
                        $form[$name]['_type'] = 'fieldset';
						$typename = 'object';
                        break;
                    default:
                        $form[$name]['_type'] = 'textField';
						$form[$name]['_label'] = $labelname;
						$form[$name]['_underlabel'] = '(' . $typename . ')' . ' ("," delimiter for multiple values, eg "flight","mode")';
                        break;
                }

                if ($typename == 'nested') {
                    unset($form[$name]['_label']);
					if(!isset($fields[$name])) $fields[$name] = array();
                    $form[$name]['_script'] = 'counter[\'' . $name . '\'] = ' . count($fields[$name]) . ';';
                    foreach ($fields[$name] as $key => $value) {
                        $form[$name][$name . '_fieldset_' . $key] = $this->create_form_field($mappings['properties'][$name], $value, $name, $key);
                        $form[$name][$name . '_fieldset_' . $key]['_type'] = 'fieldset';
                        $form[$name][$name . '_fieldset_' . $key]['_class'] = 'nested-fieldset';
                        $form[$name][$name . '_fieldset_' . $key]['_label'] = $name . ' <div class="close-nested">[-]</a>';
                    }
				} elseif ($typename == 'object') {
					foreach($data['properties'] as $key => $value)
					{
						$senddata = isset($fields[$name][0]) ? $fields[$name][0] : array();
                    	$form[$name] = $this->create_form_field($data, $senddata, $name);
						$form[$name]['_type'] = 'fieldset';
						$form[$name]['_label'] = $name;
					}
				} else {
                    if (isset($fields[$realname])) {
                    	if (is_array($fields[$realname])) {
                    		$fields[$realname] = '"' . implode('","', $fields[$realname]) . '"';
                    	}
                        $form[$name]['_value'] = $fields[$realname];
                    }
                }
            }
        }
        return $form;
    }

    /**
     * Form for searching documents
	 * 
     * @param array $args Form arguments
	 * 
     * @return array Form array
     */	
    private function form_search_documents($args)
    {
        $form['_init'] = array(
            'name' => 'create_field',
            'action' => 'document/search_documents_post/' . $args[0]
        );

        $form['doc'] = array(
            '_type' => 'fieldset'
        );

        $form['doc']['search'] = array(
            '_type' => 'textField',
            '_label' => 'Search',
            '_description' => 'This does a query on all fields that are included in all.',
            '_value' => $args['query']
        );

        $form['doc']['operator'] = array(
            '_type' => 'select',
            '_label' => 'Operator',
            '_description' => 'The operator for the query. AND or OR',
            '_options' => array(
                'AND' => 'AND',
                'OR' => 'OR'
            ),
            '_value' => $args['operator']
        );

        $form['doc']['type'] = array(
            '_type' => 'select',
            '_label' => 'Document type',
            '_description' => 'The document type to search for. All searches in all document types.',
            '_options' => isset($args['mapping_types']) ? $args['mapping_types'] : array(),
            '_value' => $args['type']
        );

        if (isset($args['fields'])) {
            $form['doc']['fields'] = array(
                '_label' => 'Fields to show in table',
                '_type' => 'checkboxes',
                '_options' => $args['fields'],
                '_value' => $args['chosen_fields']
            );
        }

        $form['doc']['submit'] = array(
            '_value' => 'Search',
            '_type' => 'submit'
        );

        return $form;
    }

    /**
     * Form for a nested object
	 * 
     * @param array $args Form arguments
	 * 
     * @return array Form array
     */	
    private function form_nested($args)
    {
        $args[0] = isset($args[0]) ? $args[0] : '';
        $args[1] = isset($args[1]) ? $args[1] : '';

        $form['_init'] = array(
            'name' => '',
            'action' => ''
        );

        $form['nested'] = array(
            '_type' => 'fieldset',
            '_class' => 'nested-fieldset',
            '_label' => $args[2] . ' <div class="close-nested">[-]</a>'
        );

        if (isset($args['mappings']['properties'])) {
            foreach ($args['mappings']['properties'] as $name => $data) {
                $typename = isset($data['type']) ? $data['type'] : '';

                $newname = $typename == 'nested' ? $args[2] . '.' . $name : $args[2] . '___' . $name;

                $form['nested'][$newname] = array(
                    '_label' =>  str_replace('___', '.', $name) . ' (' . $typename . ')'
                );

                $endbrack = strstr($args[2], '.') ? ']' : '';
                $form['nested'][$newname]['_alternative_name'] = str_replace('.', '][][', preg_replace('/\./', '[', $args[2], 1)) . $endbrack . '[' . $args[3] . '][' . $name . ']';

                if (isset($data['null_value'])) {
                    $form['nested'][$newname]['_value'] = $data['null_value'];
                }

                if (isset($args['data']['fields'][$newname])) {
                    $form['nested'][$newname]['_value'] = $args['data']['fields'][$name];
                }

                switch ($typename) {
                    case 'string':
					case 'multi_field':
                        $form['nested'][$newname]['_type'] = 'textArea';
                        $form['nested'][$newname]['_rows'] = 2;
                        break;
					case 'boolean':
                        $form['nested'][$newname]['_type'] = 'radios';
                        $form['nested'][$newname]['_options'] = array(
							1 => 'true',
							0 => 'false'
						);
                        break;						
                    case 'integer':
                        $form['nested'][$newname]['_type'] = 'textField';
                        break;
                    case 'float':
                        $form['nested'][$newname]['_type'] = 'textField';
                        break;
                    case 'date':
                        $form['nested'][$newname]['_type'] = 'textField';
                        break;
					case 'nested':
                        $form['nested'][$newname]['_type'] = 'nested';
                        break;
                    default:
                        $form['nested'][$newname]['_type'] = 'textField';
                        break;						
                }

            }
        }

        return $form;
    }
}
