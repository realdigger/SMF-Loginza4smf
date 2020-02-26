<?php

/* * ********************************************************************************
 * Subs-Loginza.php                                                                *
 * **********************************************************************************
 * SMF: Simple Machines Forum                                                      *
 * Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
 * =============================================================================== *
 * Software Version:           Loginza for SMF 2.x                                 *
 * Copyright 2010 by:          Digger                                              *
 * Support, News, Updates at:  http://mysmf.ru                                     *
 * ******************************************************************************** */

// google, yandex, mailruapi, mailru, vkontakte, facebook, twitter, loginza, myopenid, webmoney, rambler, flickr, lastfm, verisign, aol, steam, openid.

if (!defined('SMF')) {
    die('Hacking attempt...');
}

$modSettings['loginza_name_delimer'] = '.';
$modSettings['loginza_providers'] = 'yandex,vkontakte,rambler,google,mailruapi,webmoney,steam,openid,loginza' . ',facebook,twitter,myopenid,flickr,lastfm,verisign,aol';
$modSettings['loginza_provider'] = 'vkontakte';

require_once $sourcedir . '/Loginza/LoginzaAPI.class.php';

function loginza_widget()
{
    global $modSettings;

    $LoginzaAPI = new LoginzaAPI();
    return $LoginzaAPI->getWidgetUrl() . '&providers_set=' . $modSettings['loginza_providers'];
}

function loginza_login($identity = '', $provider = '')
{
    global $smcFunc, $sourcedir, $user_settings;
    require_once $sourcedir . '/LogInOut.php';

    if (empty($identity) || empty($provider)) {
        return false;
    }

    $request = $smcFunc['db_query'](
        '',
        '
		SELECT id_member, member_name, id_group, additional_groups, passwd, password_salt, is_activated
		FROM {db_prefix}members
		WHERE loginza_provider="' . $provider . '" AND loginza_identity="' . $identity . '"
		LIMIT 1',
        array()
    );

    $user_settings = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($user_settings)) {
        return false;
    }

    DoLogin();
}

function loginza_register($token = '')
{
    global $modSettings, $sourcedir, $smcFunc;

    $LoginzaAPI = new LoginzaAPI();
    $UserProfile = $LoginzaAPI->getAuthInfo($token);
    if (empty($UserProfile)) {
        return false;
    }

    // Already register? Try to login.
    $registered = loginza_login($UserProfile->identity, $UserProfile->provider);

    // Register new user
    if (empty($registered)) {
        $regOptions = array();
        require_once $sourcedir . '/Loginza/LoginzaUserProfile.class.php';
        require_once $sourcedir . '/Subs-Members.php';

        $LoginzaProfile = new LoginzaUserProfile($UserProfile);

        if (empty($LoginzaProfile)) {
            return false;
        }

        // Provider value for fake email & username
        $url = parse_url($UserProfile->provider);
        $provider = str_replace(array('www.', '@'), '', $url['host']);

        // Main user fields
        $regOptions['username'] = $LoginzaProfile->normalize(
            $LoginzaProfile->genNickName(),
            $modSettings['loginza_name_delimer']
        );
        $regOptions['email'] = (!empty($UserProfile->email)) ? $UserProfile->email : $regOptions['username'] . '@' . $provider . '.local';
        $password = $LoginzaProfile->genRandomPassword(8);
        $regOptions['password'] = $password;
        $regOptions['password_check'] = $password;
        $regOptions['interface'] = 'guest';

        // Additional user fields
        $regOptions['extra_register_vars']['real_name'] = $LoginzaProfile->genDisplayName();
        $site = $LoginzaProfile->genUserSite();
        if (!empty($site)) {
            $regOptions['extra_register_vars']['website_title'] = $site;
            $regOptions['extra_register_vars']['website_url'] = $site;
        }
        if (!empty($UserProfile->photo)) {
            $regOptions['extra_register_vars']['avatar'] = $UserProfile->photo;
        }
        if (!empty($UserProfile->gender)) {
            $regOptions['extra_register_vars']['gender'] = $UserProfile->gender == 'M' ? 1 : ($UserProfile->gender == 'F' ? 2 : 0);
        }
        if (!empty($UserProfile->dob)) {
            $regOptions['extra_register_vars']['birthdate'] = $UserProfile->dob;
        }
        if (!empty($UserProfile->address->home->city)) {
            $regOptions['extra_register_vars']['location'] = $UserProfile->address->home->city;
        }
        if (!empty($UserProfile->im->icq)) {
            $regOptions['extra_register_vars']['icq'] = $UserProfile->im->icq;
        }

        // We don't need activation
        $regOptions['require'] = 'nothing';
        $regOptions['check_password_strength'] = false;

        $regOptions['extra_register_vars']['hide_email'] = 1;
        $regOptions['extra_register_vars']['loginza_identity'] = $UserProfile->identity;
        $regOptions['extra_register_vars']['loginza_provider'] = $UserProfile->provider;

        // Check if the username is in use
        if (isReservedName($regOptions['username'])) {
            $regOptions['username'] = $regOptions['username'] . $modSettings['loginza_name_delimer'] . ($modSettings['latestMember'] + 1);
        }

        // Cut if username too long
        $regOptions['username'] = substr($regOptions['username'], 0, 25);

        // Check if the email address is in use.
        $request = $smcFunc['db_query'](
            '',
            '
      SELECT id_member
      FROM {db_prefix}members
      WHERE email_address = {string:email_address}
      LIMIT 1',
            array(
                'email_address' => $regOptions['email'],
            )
        );

        if ($smcFunc['db_num_rows']($request) != 0) {
            $regOptions['email'] = $regOptions['username'] . '@' . $provider . '.local';
        }
        $smcFunc['db_free_result']($request);

        $modSettings['disableRegisterCheck'] = true;

        registerMember($regOptions);
        loginza_login($UserProfile->identity, $UserProfile->provider);
    }
}
