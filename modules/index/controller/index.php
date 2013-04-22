<?php 

class controllerIndex extends router
{
	public function __construct() {

	}
	
	public function page_refresh($args)
	{
		parent::$queryLoader->callWithCheck($args[0] . '/_refresh', 'POST', null, 'index/edit/' . $args[0]);	
	}

	public function page_flush($args)
	{
		parent::$queryLoader->callWithCheck($args[0] . '/_flush', 'POST', null, 'index/edit/' . $args[0]);	
	}
	
	public function page_gateway_snapshot($args)
	{
		parent::$queryLoader->callWithCheck($args[0] . '/_gateway/snapshot', 'POST', null, 'index/edit/' . $args[0]);	
	}

	public function page_close($args)
	{
		parent::$queryLoader->callWithCheck($args[0] . '/_close', 'POST', null, 'index/edit/' . $args[0]);	
	}
	
	public function page_open($args)
	{
		parent::$queryLoader->callWithCheck($args[0] . '/_open', 'POST', null, 'index/edit/' . $args[0]);	
	}	
			
	public function page_export($args)
	{
		$vars['content'] = $this->renderPart('index_export', $args);
		$vars['title'] = 'Export index structure ' . $args[0];	
		return $vars;
	}
	
	public function page_export_emq($args)
	{
		$state = parent::$queryLoader->call('_cluster/state', 'GET');
		
		$settings = $state['metadata']['indices'][$args[0]]['settings'];
		$mappings = $state['metadata']['indices'][$args[0]]['mappings'];
		
		$array = $this->toArray(array($settings));
		unset($array['index']['version']);
		
		$json['settings'] = $array['index'];
		
		$json['mappings'] = $mappings;
		
		header('Content-type: text/plain');
		header('Content-Disposition: attachment; filename="' . $args[0] . '.emq"');
		
		echo $args[0];
		echo "\r\n\r\n";
		echo 'POST';
		echo "\r\n\r\n";
		echo json_encode($json); 		
	}
	
	public function page_export_bash($args)
	{
		$state = parent::$queryLoader->call('_cluster/state', 'GET');
		
		$settings = $state['metadata']['indices'][$args[0]]['settings'];
		$mappings = $state['metadata']['indices'][$args[0]]['mappings'];
		
		$array = $this->toArray(array($settings));
		unset($array['index']['version']);
		
		$json['settings'] = $array['index'];
		
		$json['mappings'] = $mappings;
		
		header('Content-type: text/plain');
		header('Content-Disposition: attachment; filename="create_' . $args[0] . '".sh');
		
		echo "#!/bin/sh\r\n\r\n";
		echo "curl -XPOST '" . parent::$config['servers']['host'] . ":" . parent::$config['servers']['port'] . "/" . $args[0] . "' -d '" . json_encode($json) . "'";
	}
	
	public function page_index($args)
	{
		$vars['content'] = '';
		$vars['title'] = '?';
		return $vars;
	}
	
	public function page_create($args)
	{
		$form = new form($this->form_create_index($args));
		
		$form->createForm();
		
		$arguments['form'] = $form->renderForm();
		$vars['javascript'][] = 'custom/es_fields.js';
		$vars['javascript'][] = 'custom/forms.js';
		$vars['content'] = $this->renderPart('index_create_index', $arguments);
		$vars['title'] = 'Create index';
		return $vars;	
	
	}
	
	public function page_create_index_post($args)
	{
		$form = new form($this->form_create_index($args));
		$results = $form->getResults();
		
		parent::$queryLoader->callWithCheck($results['name'], 'PUT', '', 'index/edit/' . $results['name']);
		
		$this->redirect('index/edit/' . $results['name']);
	}
	
	public function page_edit($args)
	{
		$output = '';
		$vars['mapping_types'] = array();

		$state = parent::$queryLoader->call('_cluster/state', 'GET');

		// If the index does not exist
		if(!isset($state['metadata']['indices'][$args[0]]['settings']))
		{
			$vars['content'] = 'You are trying to reach an index that does not exist.';
			$vars['title'] = 'Index ' . $args[0] . ' does not exist.';
			return $vars;			
		}
		
		$vars['state'] = $state['metadata']['indices'][$args[0]]['state']; 
		
		$settings = parent::$queryLoader->call($args[0] . '/_settings', 'GET');
		
		// Get analyzers
		$array = $this->toArray(array($state['metadata']['indices'][$args[0]]['settings']));
		
		$vars['analyzers'] = array();
		if(isset($array['index']['analysis']['analyzer']))
		{
			foreach($array['index']['analysis']['analyzer'] as $name => $value)
			{
				if(!in_array($name, $vars['analyzers'])) $vars['analyzers'][] = $name;
			}
		}

		$mapping = $state['metadata']['indices'][$args[0]]['mappings'];

		// Render each field
		foreach($mapping as $key => $value)
		{
			$vars['mapping_types'][] = $key;
		}

		$vars['aliases'] = array();
		if(isset($state['metadata']['indices'][$args[0]]['aliases']))
		{
			$vars['aliases'] = $state['metadata']['indices'][$args[0]]['aliases'];
		}		

		$vars['name'] = $args[0];
		
		$output .= $this->renderPart('index_edit', $vars);
		
		$vars['content'] = $output;
		$vars['title'] = 'Edit ' . $args[0];
		return $vars;		
	}
	
	public function page_create_document_type($args)
	{
		// Get all the document types for possible parents
		$state = parent::$queryLoader->call('_cluster/state', 'GET');	

		$mapping = $state['metadata']['indices'][$args[0]]['mappings'];
		
		$vars['mappings'] = $mapping;
		$vars['name'] = $args[0];
		
		$form = new form($this->form_create_document_type($vars));
		
		$form->createForm();
		
		$arguments['form'] = $form->renderForm();
		$vars['javascript'][] = 'custom/es_fields.js';
		$vars['javascript'][] = 'custom/forms.js';
		$vars['content'] = $this->renderPart('index_create_index', $arguments);;
		$vars['title'] = 'Create document type for ' . $args[0];
		return $vars;	
	}
	
	public function page_create_document_type_post($args)
	{
		$form = new form($this->form_create_document_type($args));
		$results = $form->getResults();
		
		$data[$results['name']] = array();
		$data[$results['name']]['_source']['enabled'] = $results['source'];
		
		$url = $args[0] .'/' . $results['name'] . '/_mapping';
		
		parent::$queryLoader->callWithCheck($url, 'PUT', json_encode($data), 'index/edit/' . $args[0]);
		
		$this->redirect('index/edit/' . $args[0]);
	}	
	
	public function page_delete($args)
	{
		$_SESSION['delete_' . $args[0]] = true;
		$args['name'] = $args[0];
		$vars['content'] = $this->renderPart('index_delete', $args);
		$vars['title'] = 'Delete ' . $args[0];
		return $vars;
	}
	
	public function page_delete_confirm($args)
	{
		if(isset($_SESSION['delete_' . $args[0]]))
		{
			unset($_SESSION['delete_' . $args[0]]);
			parent::$queryLoader->callWithCheck($args[0], 'DELETE', '', 'start');
			$this->redirect('start');	
		}
		trigger_error('Not correctly done', E_USER_ERROR);
	}
	
	public function page_create_alias($args)
	{
		$form = new form($this->form_create_alias($args));
		
		$form->createForm();
		
		$arguments['form'] = $form->renderForm();
		$vars['javascript'][] = 'custom/es_fields.js';
		$vars['javascript'][] = 'custom/forms.js';
		$vars['content'] = $this->renderPart('index_create_index', $arguments);;
		$vars['title'] = 'Create alias for ' . $args[0];
		return $vars;		
	}

	
	public function page_create_alias_post($args)
	{
		$form = new form($this->form_create_alias($args));
		$results = $form->getResults();
		
		$data['actions'][0]['add']['index'] = $args[0];
		$data['actions'][0]['add']['alias'] = $results['name'];
		
		parent::$queryLoader->callWithCheck('_aliases', 'POST', json_encode($data), 'index/edit/' . $args[0]);
	}
	
	public function page_delete_alias($args)
	{
		$_SESSION['delete_alias_' . $args[0]] = true;
		$args['index'] = $args[0];
		$args['name'] = $args[1];
		$vars['content'] = $this->renderPart('index_delete_alias', $args);
		$vars['title'] = 'Delete alias ' . $args[0];
		return $vars;		
	}
	
	public function page_delete_alias_confirm($args)
	{
		if(isset($_SESSION['delete_alias_' . $args[0]]))
		{
			unset($_SESSION['delete_alias_' . $args[0]]);
			$data['actions'][0]['remove']['index'] = $args[0];
			$data['actions'][0]['remove']['alias'] = $args[1];
			
			parent::$queryLoader->callWithCheck('_aliases', 'POST', json_encode($data), 'index/edit/' . $args[0]);
		}
		trigger_error('Not correctly done', E_USER_ERROR);		
	}
	
	private function compute_nest_fields($fields = array())
	{
		$output = '';
		foreach($fields as $name => $data)
		{
			$output .= '--' . $name . ' (' . $data['type'] . ')<br>';
			if(isset($data['properties']))
			{
				$output .= $this->compute_nest_fields($data['properties']);
			}
		}
		return $output;
	}
	
	
	private function form_create_index($args)
	{
		$form['_init'] = array(
			'name' => 'create_index',
			'action' => 'index/create_index_post'
		);

		$form['general'] = array(
			'_type' => 'fieldset',
			'_label' => 'General index settings'
		);
		
		$form['general']['name'] = array(
			'_label' => 'Name',
			'_validation' => array(
				'required' => true
			),
			'_type' => 'textField',
			'_description' => 'This is the name of the index. No whitespace allowed.'
		);
		
		$form['general']['submit'] = array(
			'_value' => 'Create index',
			'_type' => 'submit'
		);
		return $form;		
	}

	private function form_create_alias($args)
	{
		
		$args[0] = isset($args[0]) ? $args[0] : '';
				
		$form['_init'] = array(
			'name' => 'create_index',
			'action' => 'index/create_alias_post/' . $args[0]
		);

		$form['general'] = array(
			'_type' => 'fieldset'
		);
		
		$form['general']['name'] = array(
			'_label' => 'Name',
			'_validation' => array(
				'required' => true
			),
			'_type' => 'textField',
			'_description' => 'This is the name of the alias. No whitespace allowed.'
		);
		
		$form['general']['submit'] = array(
			'_value' => 'Create alias',
			'_type' => 'submit'
		);
		return $form;		
	}

	private function form_create_document_type($args)
	{
		$args['name'] = isset($args['name']) ? $args['name'] : '';
		$args['mappings'] = isset($args['mappings']) ? $args['mappings'] : array();
		
		$form['_init'] = array(
			'name' => 'create_index',
			'action' => 'index/create_document_type_post/' . $args['name']
		);

		$form['general'] = array(
			'_type' => 'fieldset'
		);
		
		$form['general']['name'] = array(
			'_label' => 'Name',
			'_validation' => array(
				'required' => true
			),
			'_type' => 'textField',
			'_description' => 'This is the name of the document type. No whitespace allowed.'
		);
		
		$form['general']['source'] = array(
			'_label' => 'Store source',
			'_type' => 'radios',
			'_description' => 'The _source field is an automatically generated field that stores the actual JSON that was used as the indexed document. It is not indexed (searchable), just stored. When executing “fetch” requests, like get or search, the _source field is returned by default.

<p>Though very handy to have around, the source field does incur storage overhead within the index. For this reason, it can be disabled.</p>',
			'_options' => array(
				true => 'True',
				false => 'False'
			),
			'_value' => true
		);

		
		$form['general']['submit'] = array(
			'_value' => 'Create document type',
			'_type' => 'submit'
		);
		return $form;		
	}

	protected function menu_items()
	{
		return array(
			'path' => 'index/create',
			'title' => 'Create index',
			'weight' => 1
		);
	}
	
}

?>