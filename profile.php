<?php
	/**
	*
	* @package com.Itschi.settings
	* @since 2007/05/25
	*
	*/

	require 'base.php';

	if (!$user->row) {
		login_box();
	}

	$error = '';
	$mode = (empty($_GET['mode'])) ? '' : $_GET['mode'];

	switch ($mode) {
		case 'avatar':

			if (!$config['enable_avatars']) {
				message_box('Funktion ist deaktiviert', '', '');
			}

			if (isset($_GET['delete']) && $user->row['user_avatar']) {
				if (empty($_GET['ok'])) {
					message_box('Willst du Dein Avatar wirklich löschen?', 'profile.php?mode=avatar&delete=1&ok=1', 'Avatar löschen', 'profile.php?mode=avatar', 'Abbrechen');
				} else {
					@unlink('images/avatar/' . $user->row['user_avatar']);
					@unlink('images/avatar/mini/' . $user->row['user_avatar']);

					$db->query('
						UPDATE ' . USERS_TABLE . "
						SET user_avatar = ''
						WHERE user_id = " . $user->row['user_id']
					);

					$user->row['user_avatar'] = '';

					message_box('Der Avatar wurde gelöscht', 'profile.php?mode=avatar', 'zurück zur Übersicht');
				}
			}

			if (!empty($_FILES['file']['name'])) {
				include 'lib/functions/upload.php';

				$size = @getimagesize($_FILES['file']['tmp_name']);
				$ex = strtolower(end(explode('.', $_FILES['file']['name'])));

				$newfile = $user->row['user_id'] . '_' . time() . '.' . $ex;

				if (!in_array($_FILES['file']['type'], array('image/jpg', 'image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png'))) {
					$error = 1;
				} else if ($size[0] < $config['avatar_min_width'] || $size[1] < $config['avatar_min_height']) {
					$error = 2;
				} else {
					if ($size[0] > $config['avatar_max_width'] OR $size[1] > $config['avatar_max_height']) {
						resize($_FILES['file']['tmp_name'], 'images/avatar/' .  $newfile, $config['avatar_max_width'], $config['avatar_max_height'], false);
					} else {
						move_uploaded_file($_FILES['file']['tmp_name'], 'images/avatar/' . $newfile);
					}

					resize('images/avatar/' . $newfile, 'images/avatar/mini/' .  $newfile, 50, 50, true);

					if ($user->row['user_avatar'] != $newfile && $user->row['user_avatar']) {
						@unlink('images/avatar/' . $user->row['user_avatar']);
						@unlink('images/avatar/mini/' . $user->row['user_avatar']);
					}

					$db->query('
						UPDATE ' . USERS_TABLE . "
						SET user_avatar = '" . $newfile . "'
						WHERE user_id = " . $user->row['user_id']
					);

					$user->row['user_avatar'] = $newfile;

					message_box('Der Avatar wurde hochgeladen', 'profile.php?mode=avatar', 'zurück zur Übersicht');
				}
			}

			template::assign(array(
				'DEFAULT_AVATAR'	=>	$config['default_avatar'],
				'AVATAR'		=>	($user->row['user_avatar']) ? $user->row['user_avatar'] : $config['default_avatar'],
				'MIN_WIDTH'		=>	$config['avatar_min_width'],
				'MIN_HEIGHT'		=>	$config['avatar_min_height']
			));

		break;

		case 'account':

			if (isset($_POST['form_email'])) {
				include 'lib/functions/user.php';

				if (md5($_POST['password']) != $user->row['user_password']) {
					$error = 1;
				} else if (!valid_email($_POST['email'])) {
					$error = 2;
				} else if (email_exists($_POST['email'])) {
					$error = 3;
				} else {
					$db->query('
						UPDATE ' . USERS_TABLE . "
						SET user_email = '" . $db->chars($_POST['email']) . "'
						WHERE user_id = " . $user->row['user_id']
					);

					message_box('Die neue E-Mail wurde gespeichert', 'profile.php?mode=account', 'zurück zur Übersicht');
				}

			}

			if (isset($_POST['form_pw']) && isset($_POST['password']) && isset($_POST['oldpassword'])) {
				if (md5($_POST['oldpassword']) != $user->row['user_password']) {
					$error = 1;
				} else if ($_POST['password'] != $_POST['password2']) {
					$error = 2;
				} else if (strlen($_POST['password']) < 6) {
					$error = 3;
				} else {
					$db->query('
						UPDATE ' . USERS_TABLE . "
						SET user_password = '" . md5($_POST['password']) . "'
						WHERE user_id = " . $user->row['user_id']
					);

					message_box('Das neue Passwort wurde gespeichert', 'profile.php?mode=account', 'zurück zur Übersicht');
				}
			}

			template::assign(array(
				'USER_EMAIL'	=>	preg_replace_callback('^([a-zA-Z0-9\.\_\-\+]+)\@^', function($r) {
					$firstChar = mb_substr($r[0], 0, 1);
					$lastChar = mb_substr($r[0], mb_strlen($r[0]) - 2, mb_strlen($r[0]) - 1);

					$max = mb_strlen($r[0] - 2);
					$ret = '';

					for ($i = 1; $i <= $max; $i++) {
						$ret .= '*';
					}

					return $firstChar . $ret . $lastChar;
				}, $user->row['user_email'])
			));

		break;

		case 'delete':

			if (!$config['enable_delete']) {
				message_box('Funktion ist deaktiviert', '', '');
			}

			if (isset($_POST['form_delete'])) {
				if (md5($_POST['password']) != $user->row['user_password']) {
					$error = 1;
				} else if ($_POST['password'] != $_POST['password2']) {
					$error = 2;
				} else {
					include 'lib/functions/user.php';

					delete_user($user->row['user_id']);
				}
			}

			template::assign('REGISTER', date('d.m.Y H:i', $user->row['user_register']));

		break;

		default:

			if (isset($_POST['form_profil'])) {
				$db->query('
					UPDATE ' . USERS_TABLE . "
					SET	user_skype = '" . $db->chars(trim($_POST['skype'])) . "',
						user_icq = '" . $db->chars(trim($_POST['icq'])) . "',
						user_website = '" . $db->chars(trim($_POST['website'])) . "',
						user_signatur = '" . $db->chars(strip(trim($_POST['signatur']))) . "',
						user_signatur_bbcodes = " . (int)!isset($_POST['signatur_bbcodes']) . ',
						user_signatur_smilies = ' . (int)!isset($_POST['signatur_smilies']) . ',
						user_signatur_urls = ' . (int)!isset($_POST['signatur_urls']) . '
					WHERE user_id = ' . $user->row['user_id']
				);

				$user->update_vars();

				message_box('Die Daten wurden gespeichert', 'profile.php', 'zurück zur Übersicht');
			}

			template::assign(array(
				'USERNAME'		=>	htmlspecialchars($user->row['username']),
				'WEBSITE'		=>	htmlspecialchars($user->row['user_website']),
				'ICQ'			=>	htmlspecialchars($user->row['user_icq']),
				'SKYPE'			=>	htmlspecialchars($user->row['user_skype']),
				'SIGNATUR'		=>	htmlspecialchars($user->row['user_signatur']),
				'SIGNATUR_BBCODES'	=>	$user->row['user_signatur_bbcodes'],
				'SIGNATUR_SMILIES'	=>	$user->row['user_signatur_smilies'],
				'SIGNATUR_URLS'		=>	$user->row['user_signatur_urls'],
				'RANK'		=>		$user->rank($user->row['user_id'], $user->row['user_rank'], $user->row['user_posts']),
				'REGISTER'	=>	date('d.m.Y', $user->row['user_register']),
				'POINTS'	=>	number_format($user->row['user_points'], 0, '', '.'),
				'POSTS'		=>	number_format($user->row['user_posts'], 0, '', '.'),
				'PRO'		=>	number_format(@round($user->row['user_posts'] / ($config['posts_num'] + $config['topics_num'])*100, 2), 1, ',', ''),
				'PRODAY'	=>	($config['posts_num'] && $user->row['user_posts']) ? number_format(@round($user->row['user_posts']/((time() - $user->row['user_register'])/86400), 2), 1, ',', '') : 0,
				'SIGNATUR'	=>	($user->row['user_signatur']) ? replace($user->row['user_signatur'], $user->row['user_signatur_bbcodes'], $user->row['user_signatur_smilies'], $user->row['user_signatur_urls']) : false
			));

		break;
	}

	template::assign(array(
		'TITLE_TAG'	=>	'Einstellungen | ',
		'ERROR'		=>	$error,
		'MODE'		=>	$mode,
		'ENABLE_DELETE'	=>	$config['enable_delete'],
		'ENABLE_AVATARS'=>	$config['enable_avatars']
	));

	template::display('profile');
?>