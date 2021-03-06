<?php
require_once ("Person_controller.php");

class Employees extends Person_controller
{
	function __construct()
	{
		parent::__construct('employees');
	}
	
	public function index()
	{
		$data['controller_name'] = $this->get_controller_name();
		$data['table_headers'] = get_people_manage_table_headers();

		$data = $this->security->xss_clean($data);

		$this->load->view('people/manage', $data);
	}
	
	/*
	Returns employee table data rows. This will be called with AJAX.
	*/
	public function search()
	{
		$search = $this->input->get('search');
		$limit  = $this->input->get('limit');
		$offset = $this->input->get('offset');
		$sort   = $this->input->get('sort');
		$order  = $this->input->get('order');

		$employees = $this->Employee->search($search, $limit, $offset, $sort, $order);
		$total_rows = $this->Employee->get_found_rows($search);

		$data_rows = array();
		foreach($employees->result() as $person)
		{
			$data_rows[] = get_person_data_row($person, $this);
		}

		$data_rows = $this->security->xss_clean($data_rows);

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));
	}
	
	/*
	Gives search suggestions based on what is being searched for
	*/
	public function suggest_search()
	{
		$suggestions = $this->security->xss_clean($this->Employee->get_search_suggestions($this->input->post('term')));

		echo json_encode($suggestions);
	}
	
	/*
	Loads the employee edit form
	*/
	public function view($employee_id = -1)
	{
		$person_info = $this->Employee->get_info($employee_id);
		foreach(get_object_vars($person_info) as $property => $value)
		{
			$person_info->$property = $this->security->xss_clean($value);
		}
		$data['person_info'] = $person_info;

		$modules = array();
		foreach($this->Module->get_all_modules()->result() as $module)
		{
			$module->module_id = $this->security->xss_clean($module->module_id);
			$module->grant = $this->security->xss_clean($this->Employee->has_grant($module->module_id, $person_info->person_id));
			
			$modules[] = $module;
		}
		$data['all_modules'] = $modules;

		$permissions = array();
		foreach($this->Module->get_all_subpermissions()->result() as $permission)
		{
			$permission->module_id = $this->security->xss_clean($permission->module_id);
			$permission->permission_id = $this->security->xss_clean($permission->permission_id);
			$permission->grant = $this->security->xss_clean($this->Employee->has_grant($permission->permission_id, $person_info->person_id));
			
			$permissions[] = $permission;
		}
		$data['all_subpermissions'] = $permissions;

		$this->load->view("employees/form", $data);
	}
	
	/*
	Inserts/updates an employee
	*/
	public function save($employee_id = -1)
	{
		$person_data = array(
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'gender' => $this->input->post('gender'),
			'email' => $this->input->post('email'),
			'phone_number' => $this->input->post('phone_number'),
			'address_1' => $this->input->post('address_1'),
			'address_2' => $this->input->post('address_2'),
			'city' => $this->input->post('city'),
			'state' => $this->input->post('state'),
			'zip' => $this->input->post('zip'),
			'country' => $this->input->post('country'),
			'comments' => $this->input->post('comments')
		);
		$grants_data = $this->input->post('grants') != NULL ? $this->input->post('grants') : array();
		
		//Password has been changed OR first time password set
		if($this->input->post('password') != '')
		{
			$employee_data = array(
				'username' => $this->input->post('username'),
				'password' => md5($this->input->post('password'))
			);
		}
		else //Password not changed
		{
			$employee_data = array('username' => $this->input->post('username'));
		}
		
		if($this->Employee->save_employee($person_data, $employee_data, $grants_data, $employee_id))
		{
			$person_data = $this->security->xss_clean($person_data);
			$employee_data = $this->security->xss_clean($employee_data);

			//New employee
			if($employee_id == -1)
			{
				echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('employees_successful_adding').' '.
								$person_data['first_name'].' '.$person_data['last_name'], 'id' => $employee_data['person_id']));
			}
			else //Existing employee
			{
				echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('employees_successful_updating').' '.
								$person_data['first_name'].' '.$person_data['last_name'], 'id' => $employee_id));
			}
		}
		else//failure
		{
			$person_data = $this->security->xss_clean($person_data);

			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('employees_error_adding_updating').' '.
							$person_data['first_name'].' '.$person_data['last_name'], 'id' => -1));
		}
	}
	
	/*
	This deletes employees from the employees table
	*/
	public function delete()
	{
		$employees_to_delete = $this->security->xss_clean($this->input->post('ids'));

		if($this->Employee->delete_list($employees_to_delete))
		{
			echo json_encode(array('success' => TRUE,'message' => $this->lang->line('employees_successful_deleted').' '.
							count($employees_to_delete).' '.$this->lang->line('employees_one_or_multiple')));
		}
		else
		{
			echo json_encode(array('success' => FALSE,'message' => $this->lang->line('employees_cannot_be_deleted')));
		}
	}
}
?>