<?php
/*
Plugin Name: Google Analytics Post Pageviews
Plugin URI: http://maxime.sh/google-analytics-post-pageviews
Description: Retrieves and displays the pageviews for each post by linking to your Google Analytics account.
Author: Maxime VALETTE
Author URI: http://maxime.sh
Version: 1.0
*/

define('GAPP_TEXTDOMAIN', 'google-analytics-post-pageviews');

if (function_exists('load_plugin_textdomain')) {
	load_plugin_textdomain(GAPP_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)).'/languages' );
}

add_action('admin_menu', 'gapp_config_page');

function gapp_config_page() {

	if (function_exists('add_submenu_page')) {

        add_submenu_page('options-general.php',
            __('Post Pageviews', GAPP_TEXTDOMAIN),
            __('Post Pageviews', GAPP_TEXTDOMAIN),
            'manage_options', __FILE__, 'gapp_conf');

    }

}

function gapp_api_call($url, $params = array()) {

    $options = get_option('gapp');

    $now = time();

    /* Si le token est expiré, on le refait */

    if ($now > $options['gapp_expires'] && !empty($options['gapp_token_refresh'])) {

        ob_start();

        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL,'https://accounts.google.com/o/oauth2/token');
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,'client_id='.$options['gapp_pnumber'].'.apps.googleusercontent.com&client_secret=zeg4fqbBrvYNvES0aErTHW6a&refresh_token='.urlencode($options['gapp_token_refresh']).'&grant_type=refresh_token');

        $result = curl_exec($ch);
        curl_close($ch);

        $data = ob_get_contents();
        $tjson = json_decode($data);

        ob_end_clean();

        $data = file_get_contents('https://www.googleapis.com/oauth2/v1/userinfo?access_token='.urlencode($options['gapp_token']));
        $ijson = json_decode($data);

        $options['gapp_token'] = $tjson->access_token;
        $options['gapp_token_refresh'] = $tjson->refresh_token;
        $options['gapp_expires'] = time() + $tjson->expires_in;
        $options['gapp_gid'] = $ijson->id;

    }

    $qs = '?access_token='.urlencode($options['gapp_token']);

    foreach ($params as $k => $v) {

        $qs .= '&'.$k.'='.urlencode($v);

    }

    $data = file_get_contents($url.$qs);
    $json = json_decode($data);

    return $json;

}

function gapp_conf() {

	$options = get_option('gapp');

    if (!isset($options['gapp_pnumber'])) $options['gapp_pnumber'] = null;
    if (!isset($options['gapp_psecret'])) $options['gapp_psecret'] = null;
    if (!isset($options['gapp_gid'])) $options['gapp_gid'] = null;
    if (!isset($options['gapp_gmail'])) $options['gapp_gmail'] = null;
    if (!isset($options['gapp_token'])) $options['gapp_token'] = null;
    if (!isset($options['gapp_token_refresh'])) $options['gapp_token_refresh'] = null;
    if (!isset($options['gapp_expires'])) $options['gapp_expires'] = null;
    if (!isset($options['gapp_wid'])) $options['gapp_wid'] = null;
    if (!isset($options['gapp_cache'])) $options['gapp_cache'] = 60;
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $options['gapp_startdate'])) $options['gapp_startdate'] = '2007-09-29';

	$updated = false;

    if ($_GET['state'] == 'init' && $_GET['code']) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'code='.urlencode($_GET['code']).'&client_id='.$options['gapp_pnumber'].'.apps.googleusercontent.com&client_secret='.$options['gapp_psecret'].'&redirect_uri='.admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php').'&grant_type=authorization_code');

        $result = curl_exec($ch);

        if ($result === false) {

            echo '<div id="message" class="error"><p>';
            _e('There was something wrong with Google:', GAPP_TEXTDOMAIN);
            echo ' '.curl_error($ch);
            echo "</p></div>";

        }

        curl_close($ch);

        $tjson = json_decode($result);

        $options['gapp_token'] = $tjson->access_token;
        $options['gapp_token_refresh'] = $tjson->refresh_token;
        $options['gapp_expires'] = time() + $tjson->expires_in;

        update_option('gapp', $options);

        $ijson = gapp_api_call('https://www.googleapis.com/oauth2/v1/userinfo');

        $options['gapp_gid'] = $ijson->id;
        $options['gapp_gmail'] = $ijson->email;

        update_option('gapp', $options);

        if (!empty($options['gapp_token']) && !empty($options['gapp_gmail'])) {

            $updated = true;

        }

    } elseif ($_GET['state'] == 'reset') {

        $options['gapp_gid'] = null;
        $options['gapp_gmail'] = null;
        $options['gapp_token'] = null;
        $options['gapp_token_refresh'] = null;
        $options['gapp_expires'] = null;

        update_option('gapp', $options);

        $updated = true;

    } elseif ($_GET['state'] == 'clear') {

        $options['gapp_pnumber'] = null;
        $options['gapp_psecret'] = null;

        update_option('gapp', $options);

        $updated = true;

    }

	if (isset($_POST['submit'])) {

		check_admin_referer('gapp', 'gapp-admin');

		if (isset($_POST['gapp_pnumber'])) {
            $options['gapp_pnumber'] = $_POST['gapp_pnumber'];
		}

        if (isset($_POST['gapp_psecret'])) {
            $options['gapp_psecret'] = $_POST['gapp_psecret'];
        }

        if (isset($_POST['gapp_wid'])) {
            $options['gapp_wid'] = $_POST['gapp_wid'];
        }

		update_option('gapp', $options);

		$updated = true;

	}

    echo '<div class="wrap">';

    if ($updated) {

	    echo '<div id="message" class="updated fade"><p>';
	    _e('Configuration updated.', GAPP_TEXTDOMAIN);
	    echo '</p></div>';

    }

    if (!empty($options['gapp_token'])) {

        echo '<h2>'.__('Post Pageviews Usage', GAPP_TEXTDOMAIN).'</h2>';

        echo '<p>'.__('To display the pageviews number of a particular post, insert this PHP code in your template:', GAPP_TEXTDOMAIN).'</p>';

        echo '<input type="text" class="regular-text code" value="&lt;?php echo gapp_get_post_pageviews(); &gt;"/>';

        echo '<p>'.__('This code must be placed in The Loop. If not, you can specify the post ID.', GAPP_TEXTDOMAIN).'</p>';

    }

    echo '<h2>'.__('Post Pageviews Settings', GAPP_TEXTDOMAIN).'</h2>';

    if (empty($options['gapp_token'])) {

        if (empty($options['gapp_pnumber']) || empty($options['gapp_psecret'])) {

            echo '<p>'.__('In order to connect to your Google Analytics Account, you need to create a new project in the <a href="https://code.google.com/apis/console/" target="_blank">Google API Console</a>.', GAPP_TEXTDOMAIN).'</p>';

            echo '<form action="'.admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php').'" method="post" id="gapp-conf">';

            echo '<h3><label for="gapp_pnumber">'.__('Project Number:', GAPP_TEXTDOMAIN).'</label></h3>';
            echo '<p><input type="text" id="gapp_pnumber" name="gapp_pnumber" value="'.$options['gapp_pnumber'].'" style="width: 400px;" /></p>';

            echo '<p>'.__('Then, create an OAuth Client ID in "API Access". Enter this URL for the Redirect URI field:', GAPP_TEXTDOMAIN).'<br/>';
            echo admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php');
            echo '</p>';

            echo '<h3><label for="gapp_psecret">'.__('Client secret:', GAPP_TEXTDOMAIN).'</label></h3>';
            echo '<p><input type="text" id="gapp_psecret" name="gapp_psecret" value="'.$options['gapp_psecret'].'" style="width: 400px;" /></p>';

            echo '<p class="submit" style="text-align: left">';
            wp_nonce_field('gapp', 'gapp-admin');
            echo '<input type="submit" name="submit" value="'.__('Save', GAPP_TEXTDOMAIN).' &raquo;" /></p></form></div>';

        } else {

            $url_auth = 'https://accounts.google.com/o/oauth2/auth?client_id='.$options['gapp_pnumber'].'.apps.googleusercontent.com&redirect_uri=';
            $url_auth .= admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php');
            $url_auth .= '&scope=https://www.googleapis.com/auth/analytics.readonly+https://www.googleapis.com/auth/userinfo.email+https://www.googleapis.com/auth/userinfo.profile&response_type=code&access_type=offline&state=init&approval_prompt=force';

            echo '<p><a href="'.$url_auth.'">'.__('Connect to Google Analytics', GAPP_TEXTDOMAIN).'</a></p>';

            echo '<p><a href="'.admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php').'&state=clear">'.__('Clear the API keys').' &raquo;</a></p>';

        }

    } else {

        echo '<p>'.__('You are connected to Google Analytics with the e-mail address:', GAPP_TEXTDOMAIN).' '.$options['gapp_gmail'].'.</p>';

        echo '<p><a href="'.admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php').'&state=reset">'.__('Disconnect from Google Analytics').' &raquo;</a></p>';

        echo '<form action="'.admin_url('options-general.php?page=google-analytics-post-pageviews/google-analytics-post-pageviews.php').'" method="post" id="gapp-conf">';

        echo '<h3><label for="gapp_wid">'.__('Use this website to retrieve pageviews numbers:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><select id="gapp_wid" name="gapp_wid" style="width: 400px;" />';

        echo '<option value=""';
        if (empty($options['gapp_wid'])) echo ' SELECTED';
        echo '>'.__('None', GAPP_TEXTDOMAIN).'</option>';

        $wjson = gapp_api_call('https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles');

        foreach ($wjson->items as $item) {

            echo '<option value="'.$item->id.'"';
            if ($options['gapp_wid'] == $item->id) echo ' SELECTED';
            echo '>'.$item->name.'</option>';

        }

        echo '</select></p>';

        echo '<h3><label for="gapp_cache">'.__('Cache time:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><select id="gapp_cache" name="gapp_cache">';

        echo '<option value="60"';
        if ($options['gapp_cache'] == 60) echo ' SELECTED';
        echo '>'.__('One hour', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="360"';
        if ($options['gapp_cache'] == 360) echo ' SELECTED';
        echo '>'.__('Six hours', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="720"';
        if ($options['gapp_cache'] == 720) echo ' SELECTED';
        echo '>'.__('12 hours', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="1440"';
        if ($options['gapp_cache'] == 1440) echo ' SELECTED';
        echo '>'.__('One day', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="10080"';
        if ($options['gapp_cache'] == 10080) echo ' SELECTED';
        echo '>'.__('One week', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="20160"';
        if ($options['gapp_cache'] == 20160) echo ' SELECTED';
        echo '>'.__('Two weeks', GAPP_TEXTDOMAIN).'</option>';

        echo '</select></p>';

        echo '<h3><label for="gapp_startdate">'.__('Start date for the analytics:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><input type="text" id="gapp_psecret" name="gapp_startdate" value="'.$options['gapp_startdate'].'" /></p>';

        echo '<p class="submit" style="text-align: left">';
        wp_nonce_field('gapp', 'gapp-admin');
        echo '<input type="submit" name="submit" value="'.__('Save', GAPP_TEXTDOMAIN).' &raquo;" /></p></form></div>';

    }

}

function gapp_get_post_pageviews($ID = null) {

    $options = get_option('gapp');

    if ($ID) {

        $gaTransName = 'ga-transient-'.$ID;
        $permalink = get_permalink($ID);

    } else {

        $gaTransName = 'ga-transient-'.get_the_ID();
        $permalink = get_permalink();

    }

    $totalResult = get_transient($gaTransName);

    if ($totalResult !== false) {

        $totalResult = number_format($totalResult, 0, ',', ' ');

        return $totalResult;

    } else {

        $pageURL = parse_url($permalink);

        $json = gapp_api_call('https://www.googleapis.com/analytics/v3/data/ga',
            array('ids' => 'ga:'.$options['gapp_wid'],
                'start-date' => $options['gapp_startdate'],
                'end-date' => date('Y-m-d'),
                'metrics' => 'ga:pageviews',
                'filters' => 'ga:pagePath=@'.$pageURL['path'],
                'max-results' => 1000)
        );

        $totalResult = $json->totalsForAllResults->{'ga:pageviews'};

        if (is_numeric($totalResult) && $totalResult > 0) {

            set_transient($gaTransName, $totalResult, 60 * 60 * $options['gapp_cache']);

            $totalResult = number_format($totalResult, 0, ',', ' ');

            return $totalResult;

        } else {

            set_transient($gaTransName, '0', 60 * $options['gapp_cache']);

            return 0;

        }

    }

}