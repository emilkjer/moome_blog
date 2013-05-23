<?php

class Sessions extends Koken_Controller {

	function __construct()
    {
    	$this->caching = false;
		$this->auto_authenticate = false;
        parent::__construct();
    }

	function index()
	{
		// GC old sessions
		if ($this->method !== 'delete')
		{
			$gc = new Application;
			$gc->where('role', 'god')->where('created_on <', strtotime('-14 days'))->get();
			$gc->delete_all();
		}

		if ($this->method == 'get')
		{
			$auth = $this->authenticate();
			if ($auth)
			{
				$user_id = $auth[0];
				$u = new User();
				$u->get_by_id($user_id);
				if ($u->exists())
				{
					$this->set_response_data(array(
						'token' => $auth[1],
						'user' => $u->to_array()
					));
				}
				else
				{
					$this->error('404', 'User not found.');
				}
			}
			else
			{
				$this->error('404', 'Session not found.');
			}
		}
		else
		{
			switch($this->method)
			{
				case 'post':
					$u = new User;
					if ($this->input->post('email') && $this->input->post('password'))
					{
						$u->where('email', $this->input->post('email'))
							->limit(1)
							->get();

						if ($u->exists() && $u->check_password($this->input->post('password')))
						{
							$a = new Application();
							$a->user_id = $u->id;
							$a->token = koken_rand();
							$a->role = 'god';
							$a->save();

							setcookie(	'koken_session',
										serialize(
											array(
												'token' => $a->token,
												'user' => $u->to_array()
											)
										),
										$this->input->post('remember') === 'on' ? time()+60*60*24*14 : 0,
										'/',
										null,
										false, // only https? TODO: Hook this up in the future if possible
										true // httponly
									);
						}
						else
						{
							$this->error('404', 'User not found.');
						}
					}
					else
					{
						$this->error('403', 'Required parameters "email" and/or "password" are not present.');
					}
					$this->redirect("/sessions");
					break;
				case 'delete':
					$auth = $this->authenticate();
					if (!$auth)
					{
						$this->error('401', 'Not authorized to perform this action.');
					}
					$a = new Application();
					$a->where('token', $auth[1])->get();
					$a->delete();

					setcookie(	'koken_session',
								'',
								time() - 3600,
								'/',
								null,
								false, // only https? TODO: Hook this up in the future if possible
								true // httponly
							);
					exit;
					break;
			}
		}
	}
}

/* End of file sessions.php */
/* Location: ./system/application/controllers/sessions.php */