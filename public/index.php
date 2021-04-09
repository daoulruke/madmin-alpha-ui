<?php

    session_start();

    //Check environment file exists - ideally impossible to fail
    if(file_exists('../.env')) {
        $env_vars = file('../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } else {
        echo '[NO-ENVIRONMENT]' . PHP_EOL;
        exit;
    }

    foreach ($env_vars AS $env_var) {

        if (strpos(trim($env_var), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $env_var, 2);

        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }

    }

    header('Access-Control-Allow-Origin: https://' . $_ENV['AUTH_DOMAIN']);

    $mysqli = mysqli_connect($_ENV['DB_SERVER'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);

    //Default values, likely to be overridden if portal found
    //$_SESSION['portal']['client_id'] = 'bjxYsi4ZHFfD7D4VpVvEdRFyGTtw9ug9';
    //$_SESSION['portal']['client_secret'] = '4mZrXxO30VT3JeQN-6x7md-97Mvh677ADcVLDx3ZIxVT233xUSc-OJLkhsnMU20u';

    //$scope = 'openid%20account%20email%20phone';
    $scope = 'openid%20account%20email';
    $audience = 'https://' . $_ENV['API_DOMAIN'];
    $state = 'T3JeQN-6x7md-97Mvh677ADcVLDx3ZIx';
    $nonce = 'vh677ADcVLDx3ZIxVT233xUSc-OJLkhs';

    //Attempt to derive subdomain
    $parsedUrl = parse_url($_SERVER['HTTP_HOST']);
    $host = explode('.', $parsedUrl['path']);
    $subdomain = $host[0];

    if(isset($_GET['portal'])) {
        $subdomain = $_GET['portal'];
    }

    //echo $subdomain;

    if(!isset($_SESSION['portal']['subdomain']) || ($_SESSION['portal']['subdomain'] != $subdomain)) {

        if (isset($subdomain)) {

            //Check environment variables exist - ideally impossible to fail
            if($_ENV['DB_SERVER'] && $_ENV['DB_NAME'] && $_ENV['DB_USER'] && $_ENV['DB_PASS']) {

                //If subdomain found, check for portal
                $sql = 'SELECT * FROM portal WHERE subdomain = "' . $subdomain . '"';
                $result = mysqli_query($mysqli, $sql);

                if(mysqli_num_rows($result)) {
                    $_SESSION['portal'] = mysqli_fetch_assoc($result);
                }

                unset($result);

            } else {
                echo '[NO-ENVIRONMENT-DATA]' . PHP_EOL;
                exit;
            }

        }

    }

    if(isset($_GET['portal']) && isset($_SESSION['portal'])) {
        echo json_encode($_SESSION['portal']);
        exit;
    }

    if(isset($_SESSION['portal'])) {

        $auth0_login_uri = 'https://' . $_ENV['AUTH_DOMAIN'] . '/authorize?response_mode=form_post&response_type=code%20id_token%20token&client_id=' . $_SESSION['portal']['client_id'] . '&redirect_uri=' . 'https://' . $_SESSION['portal']['subdomain'] . '.' . $_ENV['UI_DOMAIN'] . '&scope=' . $scope . '&audience=' . $audience . '&state=' . $state . '&nonce=' . $nonce;
        $auth0_logout_uri = 'https://' . $_ENV['AUTH_DOMAIN'] . '/v2/logout?client_id=' . $_SESSION['portal']['client_id'] . '&returnTo=https://' . $_SESSION['portal']['subdomain'] . '.' . $_ENV['UI_DOMAIN'];

    } else if (!isset($_ENV['portal'])) {

        //No session portal, or default portal found - ideally impossible
        echo '[NO-PORTAL]' . PHP_EOL;
        exit;

    }


    if(isset($_GET['logout']) && $_GET['logout'] == 1) {

        if(isset($_SESSION['portal']['logout_uri'])) {
            $logout_uri = $_SESSION['portal']['logout_uri'];
        } else {
            $logout_uri = $auth0_logout_uri;
        }

        session_unset();
        session_destroy();

        header('Location: ' . $logout_uri);
        //echo $logout_uri;
        //exit;

    }


    if(isset($_POST['code'])) {
        $_SESSION['auth0'] = $_POST;
    } else if(isset($_GET['iss'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    function base64UrlEncode($text)
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }

    function base64UrlDecode($data, $strict = false)
    {

      // Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
      $b64 = strtr($data, '-_', '+/');

      // Decode Base64 string and return the original data
      return base64_decode($b64, $strict);

    }

    if($_SESSION['auth0']['access_token']) {

        $jwt = $_SESSION['auth0']['access_token'];

        // split the token
        $tokenParts = explode('.', $jwt);
        $header = base64UrlDecode($tokenParts[0]);
        $payload = base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $_SESSION['access_token'] = json_decode($payload, TRUE);

        // check the expiration time - note this will cause an error if there is no 'exp' claim in the token
        $tokenExpired = ($_SESSION['access_token']['exp'] - time()) < 0;

        // build a signature based on the header and payload using the secret
        $base64UrlHeader = base64UrlEncode($header);
        $base64UrlPayload = base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $_SESSION['portal']['client_secret'], true);
        $base64UrlSignature = base64UrlEncode($signature);

        // verify it matches the signature provided in the token
        $signatureValid = ($base64UrlSignature === $signatureProvided);

        array_merge($_SESSION['access_token'], json_decode($header, TRUE));

        if ($tokenExpired) {
            $_SESSION['access_token']['expired'] = 'TOKEN EXPIRED ' . time();
        } else {
            $_SESSION['access_token']['expired'] = 'TOKEN CURRENT ' . time();
        }

        if ($signatureValid) {
            //$_SESSION['access_token']['signature'] = 'SIGNATURE VALID';
        } else {
            //$_SESSION['access_token']['signature'] = 'SIGNATURE INVALID';
        }

    }

    if($_SESSION['auth0']['id_token']) {

        $jwt = $_SESSION['auth0']['id_token'];

        // split the token
        $tokenParts = explode('.', $jwt);
        $header = base64UrlDecode($tokenParts[0]);
        $payload = base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $_SESSION['id_token'] = json_decode($payload, TRUE);

        // build a signature based on the header and payload using the secret
        $base64UrlHeader = base64UrlEncode($header);
        $base64UrlPayload = base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $_SESSION['portal']['client_secret'], true);
        $base64UrlSignature = base64UrlEncode($signature);

        // verify it matches the signature provided in the token
        $signatureValid = ($base64UrlSignature === $signatureProvided);

        array_merge($_SESSION['id_token'], json_decode($header, TRUE));

        if ($signatureValid) {
            //$_SESSION['id_token']->signature = 'SIGNATURE VALID';
        } else {
            //$_SESSION['id_token']->signature = 'SIGNATURE INVALID';
        }

    }

    if(isset($_SESSION['id_token']['https://core-api/waad_email'])) {
        $_SESSION['id_token']['email'] = $_SESSION['id_token']['https://core-api/waad_email'];
    }

    //Attempt to match on third_party_code
    if(isset($_SESSION['id_token']['sub'])) {

        $sql = 'SELECT * FROM person WHERE third_party_code = "' . $_SESSION['id_token']['sub'] . '"';
        $result = mysqli_query($mysqli, $sql);

        if(mysqli_num_rows($result)) {

            $_SESSION['auth_user'] = mysqli_fetch_assoc($result);

        } else {

            //Attempt to match on email
            $sql = 'SELECT * FROM person WHERE email_address = "' . $_SESSION['id_token']['email'] . '"';
            $result = mysqli_query($mysqli, $sql);

            if(mysqli_num_rows($result)) {

                $_SESSION['auth_user'] = mysqli_fetch_assoc($result);

                //If match found, populate third_party_code
                if($_SESSION['auth_user']['id'] && !$_SESSION['auth_user']['sub']) {
                    $sql = 'UPDATE person SET third_party_code = "' . $_SESSION['id_token']['sub'] . '" WHERE id = ' . $_SESSION['auth_user']['id'];
                    mysqli_query($mysqli, $sql);
                }

            } else {

                //No unannounced visitors permitted
                header('Location: https://kiwiproperty.madmin.com?logout=1');
                exit;

                $nickname = isset($_SESSION['id_token']['nickname']) ? $_SESSION['id_token']['nickname'] : $_SESSION['id_token']['email'];

                //Create new user
                $sql = 'INSERT INTO person (
                    name,
                    firm_id,
                    email_address,
                    third_party_code,
                    status
                ) VALUES ("' .
                    $nickname . '","1","' .
                    $_SESSION['id_token']['email'] . '","' .
                    $_SESSION['id_token']['sub'] . '",
                    "ACTIVE"
                )';

                mysqli_query($mysqli, $sql);

                $person_id = mysqli_insert_id($mysqli);

                //Create new firm
                $sql = 'SET @auth_user_id = '. $person_id . ';' . 'INSERT INTO firm (name, status) VALUES ("Your Organisation", "ACTIVE");';
                mysqli_query($mysqli, $sql);
                $firm_id = mysqli_insert_id($mysqli);
                //echo $sql;

                $sql = 'SELECT * FROM firm ORDER BY id DESC';
                $result = mysqli_query($mysqli, $sql);
                $firm = mysqli_fetch_assoc($result);
                $firm_id = $firm['id'];

                //echo 'FIRM_ID: ' . $firm_id;

                $sql = 'UPDATE person SET firm_id = ' . $firm_id . ' WHERE id = ' . $person_id;
                //echo $sql;

                mysqli_query($mysqli, $sql);
                //exit;
                $sql = 'SELECT * FROM person WHERE id = ' . $person_id;
                $result = mysqli_query($mysqli, $sql);
                $_SESSION['auth_user'] = mysqli_fetch_assoc($result);

            }

        }

    }

    if(isset($_GET['logout']) && $_GET['logout'] == 1) {

    echo $_SESSION['portal']['logout_uri'];
    exit;

        if(isset($_SESSION['portal']['logout_uri'])) {
            $logout_uri = $_SESSION['portal']['logout_uri'];
        } else {
            $logout_uri = $auth0_logout_uri;
        }

        session_unset();
        session_destroy();

        //header('Location: ' . $logout_uri);
        echo $logout_uri;
        exit;

    }

    if(!isset($_SESSION['auth0'])) {

        if(isset($_SESSION['portal']['login_uri'])) {
            $login_uri = $_SESSION['portal']['login_uri'];
        } else {
            $login_uri = $auth0_login_uri;
        }

        header('Location: ' . $login_uri);
        exit;

    }

    if(isset($_SESSION['auth_user']['id'])) {

        $attribute_code = 'all:application';

        $sql = 'SELECT application.*
            FROM application
            JOIN person
             ON person.id = ' . $_SESSION['auth_user']['id'] . '
            JOIN attribute
             ON attribute.code = "' . $attribute_code . '"
            JOIN attribute_person
             ON attribute_person.person_id = person.id
             AND attribute_person.archived_at IS NULL
             AND attribute_person.attribute_id = attribute.id
            JOIN application_firm
             ON application_firm.application_id = application.id
             AND application_firm.firm_id = person.firm_id
            ORDER BY application_firm.sort ASC';


        $applications = mysqli_query($mysqli, $sql);
        $application_count = mysqli_num_rows($applications);
        //$applications = mysqli_fetch_array($result);

        if(!$application_count) {
            header('https://kiwiproperty.madmin.com');
            exit;
        }

    } else {
        echo 'NO AUTH-USER: NO APPLICATIONS';
    }

?>
<!DOCTYPE html>
<html>

    <head>

        <link href="https://fonts.googleapis.com/css?family=Material+Icons" rel="stylesheet" type="text/css">
        <link href="https://cdn.jsdelivr.net/npm/quasar@1.15.4/dist/quasar.min.css" rel="stylesheet" type="text/css">
        <link href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=" rel="icon" type="image/x-icon" />

        <style>

            @font-face {
                font-family: 'SFMono';
                src: url("/fonts/SF-Mono-Regular.otf") format("opentype");
            }

            @font-face {
                font-family: 'SFMono';
                font-weight: bold;
                src: url("/fonts/SF-Mono-Bold.otf") format("opentype");
            }

            @keyframes fadein {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }

            @media (orientation: portrait) {

                #masthead {
                    padding-left: 10vw !important;
                    padding-right: 10vw !important;
                    width: 100vw !important;
                    height: 7vw !important;
                }

                .q-toolbar {
                    min-height: 0 !important;
                    padding: 0 !important;
                }

                .q-toolbar__title {
                    font-family: 'SFMono' !important;
                    font-size: 3vw !important;
                    line-height: 3vw !important;
                }

                .head-icon {
                    font-size: 2.4vw !important;
                    line-height: 2.4vw !important;
                }

                .nav-icons  {
                    width: 100vw !important;
                    padding: 1.2vw !important;
                    font-size: 10vw !important;
                }

                .app-icon {
                    height: 16vw;
                    width: 16vw;
                    margin: 2vw;
                    margin-bottom: 0;
                    border-radius: 2vw;
                }

                .app-label {
                    font-size: 3vw;
                }

                #portal_logo {
                    height: 4vw;
                    padding-left: 1.2vw;
                    object-fit: contain;
                }

            }

            @media (orientation: landscape) {

                #masthead {
                    padding-left: 4vh !important;
                    padding-right: 4vh !important;
                    width: 104vh !important;
                    height: 7vh !important;
                }

                .q-toolbar {
                    min-height: 0 !important;
                    padding: 0 !important;
                }

                .q-toolbar__title {
                    font-family: 'SFMono' !important;
                    font-size: 3vh !important;
                    line-height: 3vh !important;
                }

                .head-icon {
                    font-size: 2.4vh !important;
                    line-height: 2.4vh !important;
                }

                .nav-icons {
                    padding-left: 2vh !important;
                    padding-right: 2vh !important;
                    width: 104vh !important;
                    padding-top: 2vh !important;
                    font-size: 10vh !important;
                }

                .app-icon {
                    height: 16vh;
                    width: 16vh;
                    margin: 2vh;
                    margin-bottom: 0;
                    border-radius: 2vh;
                }

                .app-label {
                    font-size: 2.4vh;
                }

                #portal_logo {
                    height: 4vh;
                    padding-left: 1.2vh
                    object-fit: contain;
                }

            }

            .app-label {
                font-family: 'SFMono' !important;
                text-align: center;
                line-height: center;
            }

            a {
                color: black;
                text-decoration: none;
            }

            #settings {
                padding: 1vh;
                height: 80vh;
                width: 96vh;
                margin: 2vh;
                border-radius: 2vh;
            }

            #card_for_tabs {
                width:100%;
            }

            .overlay-yellow {
                color: yellow;
                font-weight: bold;
                font-size: 50%;
                position: absolute;
                z-index:100;
                transform: rotate(-45deg);
            }

            .overlay-black {
                color: black;
                font-weight: bold;
                font-size: 60%;
                position: absolute;
                z-index:99;
                transform: rotate(-45deg);
            }

        </style>

    </head>

    <body style="background-color:white">

        <div id="q-app">
            <template>
                <q-layout view="hHh lpr lfr">

                    <q-page-container>

                        <div class="row">

                            <div class="col-grow"></div>

                            <div>

                                <q-header class="bg-dark row justify-center">
                                    <div id="masthead" class="row text-white">

                                        <q-toolbar>

                                            <div class="col-3 text-left">
                                                <img id="portal_logo" @click="leftDrawer = !leftDrawer" class="vertical-middle" src="<?php echo $_SESSION['portal']['logo_base64']; ?>">
                                            </div>

                                            <q-toolbar-title class="col-6 text-center">PORTAL<?php //echo $_SESSION['portal']['name']; ?></q-toolbar-title>

                                            <div class="col-3 text-right">
                                                <q-btn @click="rightDrawer = !rightDrawer" class="head-icon" dense flat round icon="account_circle"></q-btn>
                                                <q-btn title="SIGN OUT" type="a" href="<?php echo 'https://' . $_SESSION['portal']['subdomain'] . '.' . $_ENV['UI_DOMAIN'] . '?logout=1'; ?>" class="head-icon" dense flat round icon="cancel"></q-btn>
                                            </div>

                                        </q-toolbar>

                                    </div>
                                </q-header>

                                <q-drawer v-model="rightDrawer" side="right" overlay bordered>
                                    <?php isset($links) ? var_dump($links) : '<br>NO LINK DATA</br>'; ?>
                                </q-drawer>

                                <q-drawer v-model="leftDrawer" side="left" overlay bordered>
                                    <table>

                                        <?php

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">PORTAL</th></tr>';
                                            if(isset($_SESSION['portal']) && $_SESSION['portal']) {
                                                foreach($_SESSION['portal'] AS $key => $value) {
                                                    echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                                                }
                                            }

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">AUTH0</th></tr>';
                                            if(isset($_SESSION['auth0']) && $_SESSION['auth0'] ) {
                                                foreach($_SESSION['auth0'] AS $key => $value) {
                                                    echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                                                }
                                            }

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">ID TOKEN</th></tr>';
                                            if(isset($_SESSION['id_token']) && $_SESSION['id_token'] ) {
                                                foreach($_SESSION['id_token'] AS $key => $value) {
                                                    foreach($_SESSION['id_token'] AS $key => $value) {
                                                        if(is_array($value)) {
                                                            foreach($value AS $k => $v) {
                                                                echo '<tr><th style="text-align:left">' . $key . '['.$k.']</th><td>' . $v . '</td></tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                                                        }
                                                    }
                                                }
                                            }

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">ACCESS TOKEN</th></tr>';
                                            if(isset($_SESSION['access_token']) && $_SESSION['access_token'] ) {
                                                foreach($_SESSION['access_token'] AS $key => $value) {
                                                    if(is_array($value)) {
                                                        foreach($value AS $k => $v) {
                                                            echo '<tr><th style="text-align:left">' . $key . '['.$k.']</th><td>' . $v . '</td></tr>';
                                                        }
                                                    } else {
                                                        echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                                                    }
                                                }
                                            }

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">AUTH_USER</th></tr>';
                                            if(isset($_SESSION['auth_user']) && $_SESSION['auth_user'] ) {
                                                foreach($_SESSION['auth_user'] AS $key => $value) {
                                                    echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                                                }
                                            }

                                            echo '<tr><th style="background-color:black;color:white;text-align:left" colspan="2">&nbsp;</th></tr>';
                                            if(!isset($_SESSION['auth0']) || !isset($_SESSION['portal'])) {
                                                echo '<tr><th style="text-align:left" colspan="2"><a href="' . $auth0_login_uri . '">LOGIN</a></th></tr>';
                                            } else {
                                                //echo '<tr><th style="text-align:left" colspan="2"><a href="' . $auth0_logout_uri . '">LOGOUT</a></th></tr>';
                                                echo '<tr><th style="text-align:left" colspan="2"><a href="https://' . $_SESSION['portal']['subdomain'] . '.' . $_ENV['UI_DOMAIN'] . '?logout=1">LOGOUT</a></th></tr>';
                                            }

                                        ?>

                                    </table>
                                </q-drawer>

                                <div>

                                    <div id="nav-icons" v-if="liveMode" class="row justify-center nav-icons">

                                        <?php

                                        while($application = mysqli_fetch_array($applications)) {

                                            if($application['type'] == 'TEST') {
                                                $test_badge = '<div class="overlay-yellow">TEST</div><div class="overlay-black">TEST</div>';
                                            } else {
                                                $test_badge = '';
                                            }

                                            echo '<a href="' . $application['auth_path'] . '"><div class="madmin-icon"><i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="color: ' . $application['color_stroke'] . ';background-color: ' . $application['color_fill'] . ';">' . $application['icon'] . $test_badge . '</i><div class="app-label">' . $application['name'] . '</div></div></a>';

                                        }

                                            $placeholder_count = (20 - $application_count);

                                            $file = fopen('../icons/material_design_icons.csv', 'r');
                                            $icons = fgetcsv($file);
                                            fclose($file);

                                            $i = 0;

                                            $placeholder_count = 1;

                                            while($i < $placeholder_count) {
                                                //$icon = $icons[array_rand($icons)];
                                                //echo '<div class="madmin-icon"><q-icon :style="{\'background-color\': getRandomColor()}" class="app-icon col-shrink" name="' . $icon . '"></q-icon><div class="app-label"></div></div>';
                                                echo '<div class="madmin-icon"><q-icon style="background-color:gainsboro" class="app-icon col-shrink"></q-icon><div class="app-label"></div></div>';
                                                $i++;
                                            }

                                        ?>
                                        <!--
                                        <div class="madmin-icon" @click="liveMode=false;testMode=true;settings=false;">
                                            <i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color: pink;color:black">science</i>
                                            <div class="app-label">TEST</div>
                                        </div>

                                        <div class="madmin-icon" @click="settings=true;testMode=false;liveMode=false">
                                            <i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color:gainsboro;color:grey">lock</i>
                                            <div class="app-label">SETTINGS</div>
                                        </div>
                                        -->

                                    </div>

                                    <div id="nav-icons-test" v-if="testMode" class="row justify-center nav-icons">

                                        <?php //if($_SESSION['auth_user']['firm_id'] == 1994) : ?>

                                            <!--<a href="https://activate.sitevitals-beta.com/auth/auth0"><div class="madmin-icon"><i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color: rgb(252, 96, 39);">power</i><div class="app-label">ACTIVATE</div></div></a>-->
                                            <a href="https://kiwiproperty.apo.nz/auth/auth0"><div class="madmin-icon"><i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color: pink;">auto_graph</i><div class="app-label">APO</div></div></a>
                                            <a href="https://kiwiproperty.sitevitals-beta.com/auth/auth0"><div class="madmin-icon"><i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color: pink;">domain</i><div class="app-label">SITEVITALS</div></div></a>

                                        <?php //endif; ?>

                                        <?php

                                            if($_SESSION['auth_user']['firm_id'] == 1994) {
                                                $x = 16;
                                            } else {
                                                $x = 16;
                                            }

                                            $file = fopen('../icons/material_design_icons.csv', 'r');
                                            $icons = fgetcsv($file);
                                            fclose($file);

                                            $i = 0;

                                            while($i < $x) {
                                                $icon = $icons[array_rand($icons)];
                                                //echo '<div class="madmin-icon"><q-icon :style="{\'background-color\': getRandomColor()}" class="app-icon col-shrink" name="' . $icon . '"></q-icon><div class="app-label"></div></div>';
                                                echo '<div class="madmin-icon"><q-icon style="background-color:gainsboro" class="app-icon col-shrink"></q-icon><div class="app-label"></div></div>';
                                                $i++;
                                            }

                                        ?>

                                        <div class="madmin-icon" @click="liveMode=true;testMode=false;settings=false">
                                            <i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color:yellow;color:black">offline_bolt</i>
                                            <div class="app-label">LIVE</div>
                                        </div>

                                        <div class="madmin-icon" @click="settings=true;testMode=false;liveMode=false">
                                            <i aria-hidden="true" role="presentation" class="app-icon col-shrink material-icons q-icon notranslate" style="background-color:gainsboro;color:grey">lock</i>
                                            <div class="app-label">SETTINGS</div>
                                        </div>

                                    </div>

                                    <div id="settings" v-if="settings" class="row bg-grey-6">

                                        <q-card id="card_for_tabs" class="q-ma-md" flat>

                                            <q-tabs
                                                v-model="tab"
                                                active-color="dark"
                                                indicator-color="transparent"
                                                class="black"
                                                align="justify"
                                            >
                                                <q-tab name="account" label="Account" size="lg"animated class="bg-grey-2"></q-tab>
                                                <q-tab name="applications" label="Applications" size="lg" class="bg-grey-6"></q-tab>
                                                <q-tab name="preferences" label="Preferences" size="lg" class="bg-grey-6"></q-tab>
                                            </q-tabs>



                                                <q-tab-panels v-model="tab"  animated class="bg-grey-2">

                                                    <q-tab-panel name="account">
                                                        <div class="row">
                                                        <div class="col-6">
                                                            <q-form
                                                                class="q-gutter-md"
                                                            >

                                                                <q-input
                                                                    outlined
                                                                    type="text"
                                                                    v-model="name"
                                                                    label="Name *"
                                                                    size="lg"
                                                                ></q-input>

                                                                <q-input
                                                                    outlined
                                                                    type="email"
                                                                    v-model="email"
                                                                    label="Email Address *"
                                                                ></q-input>

                                                                <q-input
                                                                    outlined
                                                                    type="password"
                                                                    v-model="password"
                                                                    label="Password *"
                                                                ></q-input>

                                                                <q-select outlined v-model="start_app" :options="start_apps" label="Start-Up Application"></q-select>

                                                                <q-select outlined v-model="delegate_id" :options="delegate_ids" label="Delegate Account"></q-select>

                                                                </div>
                                                            <div class="col-6"></div>
                                                        </div>
<br>
                                                                    <div class="row">
                                                                        <div class="col-4">
                                                                            <q-btn label="CANCEL" type="reset" class="full-width" size="lg" outline></q-btn>
                                                                        </div>
                                                                        <div class="col-4">
                                                                            <q-btn label="SAVE" type="submit" class="full-width bg-dark" color="white" size="lg" flat></q-btn>
                                                                        </div>
                                                                    </div>

                                                            </q-form>

                                                  </q-tab-panel>

                                                  <q-tab-panel name="applications">
                                                    <div class="text-h6">Applications</div>
                                                  </q-tab-panel>

                                                  <q-tab-panel name="preferences">
                                                    <div class="text-h6">Preferences</div>
                                                </q-tab-panel>

                                            </q-tab-panels>

                                        </q-card>

                                    </div>

                                </div>

                            </div>

                            <div class="col-grow"></div>

                        </div>

                        <!--<div class="text-center q-pa-lg text-grey-4">&copy; MMXX MADMIN</div>-->

                    </q-page-container>

                </q-layout>
            </template>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/vue@^2.0.0/dist/vue.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/quasar@1.15.4/dist/quasar.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/axios@0.21.1/dist/axios.min.js"></script>
        <!--<script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.jss"></script>-->

        <script>

            new Vue({
                el: '#q-app',
                data: function () {
                    return {
                        leftDrawer: false,
                        rightDrawer: false,
                        tab: 'account',
                        liveMode: true,
                        testMode: false,
                        settings: false,
                        name: null,
                        email: null,
                        password: null,
                        start_app: null,
                        start_apps: [
                            'APO', 'SITEVITALS'
                        ],
                        delegate_id: null,
                        delegate_ids: [],
                    }
                },
                methods: {
                    getRandomColor: function() {
                        return '#' + (Math.random()*0xFFFFFF<<0).toString(16);
                    }
                },
            });

        </script>

    </body>
</html>