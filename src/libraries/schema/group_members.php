<?php
/**
 * @package    API_Plugins
 * @copyright  Copyright (C) 2009-2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license    GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link       http://www.techjoomla.com
 */
defined('_JEXEC') or die('Restricted access');

/** To build group simple schema
 *
 * @since  1.8.8
 */
class GroupMembersSimpleSchema
{
	public $id;

	public $username;

	public $image;

	public $isself;

	public $cover;

	public $friend_count;

	public $follower_count;

	public $badges;

	public $points;

	public $more_info;
}
