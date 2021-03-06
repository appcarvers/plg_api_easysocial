<?php
/**
 * @package    API_Plugins
 * @copyright  Copyright (C) 2009 - 2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license    GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link       http://www.techjoomla.com
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.html.html');
jimport('joomla.application.component.controller');
jimport('joomla.application.component.model');
jimport('joomla.user.helper');
jimport('joomla.user.user');

require_once JPATH_SITE . '/components/com_api/libraries/authentication/user.php';
require_once JPATH_SITE . '/components/com_api/libraries/authentication/login.php';
require_once JPATH_SITE . '/components/com_api/models/key.php';
require_once JPATH_SITE . '/components/com_api/models/keys.php';
require_once JPATH_SITE . '/components/com_api/models/keys.php';

/**
 * EasysocialApiResourceSociallogin class
 *
 * @since  1.0
 */
class EasysocialApiResourceSociallogin extends ApiResource
{
	public $provider = '';

	public $accessToken = '';

	public $socialProfData = '';

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @return mixed
	 */
	public function get()
	{
		// $this->plugin->setResponse(JText::_('PLG_API_EASYSOCIAL_UNSUPPORTED_METHOD_MESSAGE'));
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @return mixed
	 */
	public function post()
	{
		$app               = JFactory::getApplication();
		$accessToken = $app->input->get('access_token', '', 'STR');
		$provider    = $app->input->get('provider', '', 'STR');

		if ($accessToken)
		{
			$objFbProfileData = $this->jfbGetUser($accessToken, $provider);
			$is_use_jfb = JComponentHelper::isEnabled('com_jfbconnect', true);
			$userId = $this->jfbGetUserFromMap($objFbProfileData, $is_use_jfb);

			if ($userId)
			{
				$this->jfbLogin($userId);
			}
			else
			{
				if (!isset($objFbProfileData->email) && $provider == 'facebook')
				{
						$reg_dt          = new stdClass;
						$reg_dt->code    = 200;
						$reg_dt->email   = 0;
						$reg_dt->warning = 'Email id not found in Facebook data unable to register user,
						Do you want to complete registration process';

						// Todo add constant
						$reg_dt->data    = $objFbProfileData;
						$this->plugin->setResponse($reg_dt);

						return false;
				}

				if ($provider == 'facebook')
				{
					$this->jfbRegister($accessToken, $provider, $is_use_jfb);
				}
				else
				{
					$this->jfbRegister($objFbProfileData, $provider, $is_use_jfb);
				}

				$userId = $this->jfbGetUserFromMap($objFbProfileData, $is_use_jfb);

				if ($userId)
				{
					$this->jfbLogin($userId);
				}
			}
		}
		else
		{
			$this->badRequest(JText::_('PLG_API_EASYSOCIAL_BAD_REQUEST'));
		}
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param   STRING  $mesage  error message
	 * @param   INT     $code    error code
	 *
	 * @return  mixed
	 */
	public function badRequest($mesage, $code)
	{
			$code         = $code ? $code : 403;
			$obj          = new stdClass;
			$obj->code    = $code;
			$obj->message = $mesage;
			$this->plugin->setResponse($obj);
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param  INTEGER  $userId  user id
	 *
	 * @return mixed
	 */
	public function jfbLogin($userId)
	{
		$this->plugin->setResponse($this->keygen($userId));
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param  STRING  $accessToken  access tocken
	 *
	 * @param  STRING  $provider     provider
	 *
	 * @param  STRING  $is_use_jfb   is_use_jfb
	 *
	 * @return mixed
	 */
	public function jfbRegister($accessToken, $provider, $is_use_jfb)
	{
		if ($provider == 'facebook')
		{
				$objFbProfileData = $this->jfbGetUser($accessToken, $provider);
		}
		else
		{
				$objFbProfileData = $accessToken;
		}

		if ($objFbProfileData->id)
		{
			if ($provider == 'facebook')
			{
					$username = $objFbProfileData->name;
			}
			else
			{
					$username = $objFbProfileData->name->givenName;
			}

			if ($username)
			{
				$isUserExist = 1;

				$loopCounter = 0;

				while ($isUserExist)
				{
					$isUserExist = $this->checkUserNameIsExist($username);

					if ($isUserExist)
					{
							$username .= '_' . $loopCounter ++;
					}
				}

				if ($provider == 'facebook')
				{
						$userId = $this->joomlaCreateUser($username, $objFbProfileData->name, $objFbProfileData->email, $objFbProfileData);
				}
				else
				{
						$userId = $this->joomlaCreateUser($username, $objFbProfileData->displayName,
						$objFbProfileData->emails[0]->value, $objFbProfileData);
				}

				if ($userId && $is_use_jfb)
				{
						$this->jfbCreateUser($userId, $objFbProfileData, $provider, $accessToken);
				}
			}
		}
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param   OBJECT  $objFbProfileData  profile data
	 *
	 * @param   BOOLEAN $is_use_jfb        jfb user
	 *
	 * @return  mixed
	 */
	public function jfbGetUserFromMap($objFbProfileData, $is_use_jfb)
	{
		$db     = JFactory::getDBO();
		$query  = $db->getQuery(true);
		$userId = 0;

		if ($is_use_jfb)
		{
				$query->select(' j_user_id ');
				$query->from('#__jfbconnect_user_map');
				$query->where(" provider_user_id = " . $db->quote($objFbProfileData->id));
				$db->setQuery($query);
				$userId = $db->loadResult();
		}

		if (!$userId)
		{
			if ($provider == 'facebook')
			{
					$query = $db->getQuery(true);
					$query->select(' id ');
					$query->from('#__users');
					$query->where(" email = " . $db->quote($objFbProfileData->email));
					$db->setQuery($query);
					$userId = $db->loadResult();
			}
			else
			{
					$query = $db->getQuery(true);
					$query->select(' id ');
					$query->from('#__users');
					$query->where(" email = " . $db->quote($objFbProfileData->emails[0]->value));
					$db->setQuery($query);
					$userId = $db->loadResult();
			}
		}

		return $userId;
	}

	/**
	 * function to get user from #_jfbconnect_user_map
	 *
	 * @param   OBJECT  $accessToken  accessToken
	 * 
	 * @param   STRING  $provider     provider
	 *
	 * @return  mixed
	 */
	public function jfbGetUser($accessToken, $provider)
	{
		if ($provider == 'facebook')
		{
				$url          = 'https://graph.facebook.com/v2.2/me';
				$token_params = array(
								"access_token" => $accessToken,
								"fields" => 'id,name,gender,email,location,website,picture,relationship_status'
				);

			return $this->makeRequest($url, $token_params);
		}

		if ($provider == 'google')
		{
				$url          = 'https://www.googleapis.com/plus/v1/people/me';
				$token_params = array(
								"access_token" => $accessToken
				);

			return $this->makeRequest($url, $token_params);
		}
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param   OBJECT  $jUserId           user id
	 * @param   OBJECT  $objFbProfileData  profile data
	 * @param   STRING  $provider          provider
	 * @param   STRING  $accessToken       access tocken
	 *
	 * @return  mixed
	 */
	public function jfbCreateUser($jUserId, $objFbProfileData, $provider, $accessToken)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Manupulate paramteres to save
		$params = array(
						'profile_url' => 'https://www.facebook.com/app_scoped_user_id/' . $objFbProfileData->id,
						'avatar_thumb' => $objFbProfileData->picture->data->url
		);

		$columns = array(
						'j_user_id',
						'provider_user_id',
						'created_at',
						'updated_at',
						'access_token',
						'authorized',
						'params',
						'provider'
		);

		// Insert values.
		$values = array(
						$jUserId,
						$objFbProfileData->id,
						$db->quote(date('Y-m-d H:i:s')),
						$db->quote(date('Y-m-d H:i:s')),
						$db->quote('"' . $accessToken . '"'),
						1,
						$db->quote(json_encode($params)),
						$db->quote($provider)
		);

		// Prepare the insert query.
		$query->insert($db->quoteName('#__jfbconnect_user_map'))->columns($db->quoteName($columns))->values(implode(',', $values));

		// Set the query using our newly populated query object and execute it.
		$db->setQuery($query);
		$result = $db->execute();

		return $result;
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param   STRING  $url     8get profile url
	 * @param   STRING  $params  profile scopes
	 *
	 * @return  mixed
	 */
	public function makeRequest($url, $params)
	{
			$url .= '?' . http_build_query($params);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($ch, CURLOPT_USERAGENT, '');
			$output = curl_exec($ch);
			curl_getinfo($ch);
			curl_close($ch);
			$output = json_decode($output);

			return $output;
	}

	/**
	 * Typical view method for MVC based architecture
	 *
	 * @param   INT  $userId  user id
	 *
	 * @return  mixed
	 */
	public function keygen($userId)
	{
		$kmodel = new ApiModelKey;
		$key    = null;

		// Get login user hash
		$kmodel->setState('user_id', $userId);
		$log_hash = $kmodel->getList();
		$log_hash = $log_hash[count($log_hash) - count($log_hash)];
		$obj      = new stdClass;

		if ($log_hash->hash)
		{
				$key = $log_hash->hash;
		}
		elseif ($key == null || empty($key))
		{
				// Create new key for user
				$data   = array(
								'userid' => $userId,
								'domain' => '',
								'state' => 1,
								'id' => '',
								'task' => 'save',
								'c' => 'key',
								'ret' => 'index.php?option=com_api&view=keys',
								'option' => 'com_api',
								JSession::getFormToken() => 1
				);
				$result = $kmodel->save($data);
				$key    = $result->hash;
		}

		if (!empty($key))
		{
				$obj->auth = $key;
				$obj->code = '200';
				$obj->id   = $userId;
		}
		else
		{
				$this->badRequest(JText::_('PLG_API_EASYSOCIAL_BAD_REQUEST'));
		}

		return ($obj);
	}

	/**
	 * Check username is already exists
	 *
	 * @param   STRING  $username  username
	 *
	 * @return  mixed
	 */
	public function checkUserNameIsExist($username)
	{
		$db    = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(' id ');
		$query->from('#__users');
		$query->where(" username = " . $db->quote($username));
		$db->setQuery($query);

		return $db->loadResult();
	}

	/**
	 * Creates joomla user
	 *
	 * @param   STRING  $username          username
	 * @param   STRING  $name              name
	 * @param   STRING  $email             email
	 * @param   STRING  $objFbProfileData  objFbProfileData
	 *
	 * @return  mixed
	 */
	public function joomlaCreateUser($username, $name, $email, $objFbProfileData)
	{
		$data           = array();
		$data['username']   = $username;
		$data['password']   = JUserHelper::genRandomPassword(8);
		$data['name']       = $name;
		$this->sendRegisterEmail($data);
		$data['email']      = $email;
		$data['enabled']    = 0;
		$data['activation'] = 0;

		$GLOBALS['message'];
		jimport('joomla.user.helper');
		$authorize = JFactory::getACL();
		$user      = clone JFactory::getUser();
		$user->set('username', $data['username']);
		$user->set('password', $data['password']);
		$user->set('name', $data['name']);
		$user->set('email', $data['email']);
		$user->set('block', $data['enabled']);
		$user->set('activation', $data['activation']);

		// Password encryption
		$salt           = JUserHelper::genRandomPassword(32);
		$crypt          = JUserHelper::getCryptedPassword($user->password, $salt);
		$user->password = "$crypt:$salt";

		// User group/type
		$user->set('id', '');
		$user->set('usertype', 'Registered');

		if (JVERSION >= '1.6.0')
		{
			$userConfig = JComponentHelper::getParams('com_users');

			// Default to Registered.
			$defaultUserGroup = $userConfig->get('new_usertype', 2);
			$user->set('groups', array($defaultUserGroup));
		}
		else
		{
			$user->set('gid', $authorize->get_group_id('', 'Registered', 'ARO'));
		}

		$date = JFactory::getDate();
		$user->set('registerDate', $date->toSql());

		// True on success, false otherwise
		if (!$user->save())
		{
			$user->getError();

			return false;
		}
		else
		{
			

			$userid = $user->id;

			if ($userid)
			{
				$username  = explode(" ", $objFbProfileData->name);

				// Assign badge for the person.
				$badge = FD::badges();
				$badge->log('com_easysocial', 'registration.create', $user->id, JText::_('COM_EASYSOCIAL_REGISTRATION_BADGE_REGISTERED'));
			}
		}
	}

	/**
	 * Function create easysocial profile.
	 *
	 * @param   ARRAY   $log_user  log_user
	 * @param   OBJECT  $fields    fields
	 *
	 * @return  mixed
	 */
	public function createEsprofile($log_user, $fields)
	{

		if (JComponentHelper::isEnabled('com_easysocial', true))
		{
			$epost = $fields;
			require_once JPATH_ADMINISTRATOR . '/components/com_easysocial/includes/foundry.php';

			// Get all published fields apps that are available in the current form to perform validations
			$fieldsModel = FD::model('Fields');

			// Get current user.
			$my = FD::user($log_user);

			// Only fetch relevant fields for this user.
			$options = array(
				'profile_id' => $my->getProfile()->id,
				'data' => true,
				'dataId' => $my->id,
				'dataType' => SOCIAL_TYPE_USER,
				'visible' => SOCIAL_PROFILES_VIEW_EDIT,
				'group' => SOCIAL_FIELDS_GROUP_USER
			);

			$fields = $fieldsModel->getCustomFields($options);
			$epost = $this->createFieldArr($fields, $epost);

			// Load json library.
			$json = FD::json();

			// Initialize default registry
			$registry = FD::registry();

			// Get disallowed keys so we wont get wrong values.
			$disallowed = array(FD::token(), 'option', 'task', 'controller');

			// Process $_POST vars
			foreach ($epost as $key => $value)
			{
				if (!in_array($key, $disallowed))
				{
					if (is_array($value) && $key != 'es-fields-11')
					{
							$value = $json->encode($value);
					}

					$registry->set($key, $value);
				}
			}

			// Convert the values into an array.
			$data = $registry->toArray();

			// Perform field validations here. Validation should only trigger apps that are loaded on the form
			// @trigger onRegisterValidate
			$fieldsLib = FD::fields();

			// Get the general field trigger handler
			$fieldsLib->getHandler();

			// Bind the my object with appropriate data.
			$my->bind($data);

			// Save the user object.
			$my->save();

			// Reconstruct args
			$args = array(&$data, &$my);

			// @trigger onRegisterAfterSave
			$fieldsLib->trigger('onRegisterAfterSave', SOCIAL_FIELDS_GROUP_USER, $fields, $args);

			// Bind custom fields for the user.
			$my->bindCustomFields($data);

			// Reconstruct args
			$args = array( &$data, &$my);

			// @trigger onEditAfterSaveFields
			$fieldsLib->trigger('onEditAfterSaveFields', SOCIAL_FIELDS_GROUP_USER, $fields, $args);
		}
	}

	/**
	 * Function create easysocial profile.
	 *
	 * @param   ARRAY   $fields  fields
	 * @param   OBJECT  $post    post
	 *
	 * @return  mixed
	 */
	public function createFieldArr($fields, $post)
	{
		$fld_data = array();
		$app      = JFactory::getApplication();
		require_once JPATH_SITE . '/plugins/api/easysocial/libraries/uploadHelper.php';

		// For upload photo
		if ($post['avatar'])
		{
			$path = $post['avatar'];
			$filename = substr(strrchr($path, "/"), 1);
			$tempurl = explode('?', $filename);
			$upload_obj = new EasySocialApiUploadHelper;

			// Ckecking upload cover
			$phto_obj         = $upload_obj->ajax_avatar($tempurl[0]);
			$avtar_pth        = str_replace(".png", ".jpg", $phto_obj['temp_path']);
			$avtar_scr        = str_replace(".png", ".jpg", $phto_obj['temp_uri']);
			$avtar_typ        = 'upload';
			$avatar_file_name = $tempurl[0];
		}

		foreach ($fields as $field)
		{
			$fobj = new stdClass;
			$fullname = $post['first_name'] . " " . " " . $post['last_name'];
			$fld_data['first_name'] = (!empty($post['first_name'])) ? $post['first_name'] : $app->input->get('name', '', 'STRING');
			$fld_data['last_name']  = $post['last_name'];

			$fobj->first = $fld_data['first_name'];
			$fobj->last = $post['last_name'];
			$fobj->name = $fullname;

			switch ($field->unique_key)
			{
				case 'HEADER':
					break;
				case 'JOOMLA_FULLNAME':
					$fld_data['es-fields-' . $field->id] = $fobj;
					break;
				case 'JOOMLA_USERNAME':
					$fld_data['es-fields-' . $field->id] = $app->input->get('username', '', 'STRING');
					break;
				case 'JOOMLA_PASSWORD':
					$fld_data['es-fields-' . $field->id] = $app->input->get('password', '', 'STRING');
					break;
				case 'JOOMLA_EMAIL':
					$fld_data['es-fields-' . $field->id] = $app->input->get('email', '', 'STRING');
					break;
				case 'GENDER':
					$fld_data['es-fields-' . $field->id] = isset($post['GENDER']) ? $post['GENDER'] : '';
					break;
				case 'AVATAR':
					$fld_data['es-fields-' . $field->id] = Array(
									'source' => $avtar_scr,
									'path' => $avtar_pth,
									'data' => '',
									'type' => $avtar_typ,
									'name' => $avatar_file_name
					);
				break;
			}
		}

		return $fld_data;
	}

	/**
	 * Send registration email.
	 *
	 * @param   ARRAY  $base_dt  base_dt
	 *
	 * @return  mixed
	 */
	public function sendRegisterEmail($base_dt)
	{
		$config       = JFactory::getConfig();
		$params       = JComponentHelper::getParams('com_users');
		$sendpassword = $params->get('sendpassword', 1);

		$lang = JFactory::getLanguage();
		$lang->load('com_users', JPATH_SITE, '', true);

		$data['fromname']   = $config->get('fromname');
		$data['mailfrom']   = $config->get('mailfrom');
		$data['sitename']   = $config->get('sitename');
		$data['siteurl']    = JUri::root();
		$data['activation'] = $base_dt['activation'];

		// Handle account activation/confirmation emails.
		if ($data['activation'] == 0)
		{
			// Set the link to confirm the user email.
			$uri  = JUri::getInstance();
			$base  = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf('COM_USERS_EMAIL_ACCOUNT_DETAILS', $base_dt['name'], $data['sitename']);

			if ($sendpassword)
			{
				$msg = 'Hello %s,\n\nThank you for registering at %s. Your account is created and activated.';
				$msg .= '\nYou can login to %s using the following username and password:\n\nUsername: %s\nPassword: %s';
				$emailBody = JText::sprintf($msg, $base_dt['name'], $data['sitename'], $base_dt['app'], $base_dt['username'], $base_dt['password']);
			}
		}
		elseif ($data['activation'] == 1)
		{
			// Set the link to activate the user account.
			$uri  = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));

			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);
			$emailSubject = JText::sprintf('COM_USERS_EMAIL_ACCOUNT_DETAILS', $base_dt['name'], $data['sitename']);

			if ($sendpassword)
			{
				$emailBody = JText::sprintf('COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY',
					$base_dt['name'], $data['sitename'], $data['activate'], $base_dt['app'], $base_dt['username'], $base_dt['password']
					);
			}
		}

		// Send the registration email.
		$return = JFactory::getMailer()->sendMail(
		$data['mailfrom'], $data['fromname'], $base_dt['email'], $emailSubject, $emailBody
		);

		return $return;
	}
}
