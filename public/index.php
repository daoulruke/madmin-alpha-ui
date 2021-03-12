<?php

    session_start();
    header("Access-Control-Allow-Origin: https://madmin.auth0.com");




    //$scope = 'openid%20profile%20email%20phone';
    $scope = 'openid%20profile%20email';
    $audience = 'https://api.madmin-beta.com';
    $state = 'T3JeQN-6x7md-97Mvh677ADcVLDx3ZIx';
    $nonce = 'vh677ADcVLDx3ZIxVT233xUSc-OJLkhs';

    //Default values, likely to be overridden if portal found
    //$portal['client_id'] = 'bjxYsi4ZHFfD7D4VpVvEdRFyGTtw9ug9';
    //$portal['client_secret'] = '4mZrXxO30VT3JeQN-6x7md-97Mvh677ADcVLDx3ZIxVT233xUSc-OJLkhsnMU20u';
    $portal['login_uri'] = 'https://madmin-beta.com/login';
    $portal['logout_uri'] = 'https://madmin-beta.com/logout';


    //Attempt to derive subdomain
    $parsedUrl = parse_url($_SERVER['HTTP_HOST']);
    $host = explode('.', $parsedUrl['path']);
    $subdomain = $host[0];
    $_SESSION['subdomain'] = $subdomain;

    if ($subdomain == 'madmin-beta') {

        //Either there is no subdomain, or 'madmin-beta' is the subdomain, in both cases destroy variable
        unset($subdomain);

    } else if (isset($subdomain)) {

        //If subdomain found, check for portal
        $mysqli = mysqli_connect("localhost", "api_madmin_com", "api_madmin_com", "api_madmin_com");

        $sql = 'SELECT * FROM portal WHERE subdomain = "' . $subdomain . '"';
        $result = mysqli_query($mysqli, $sql);

        if(mysqli_num_rows($result)) {
            $portal = mysqli_fetch_assoc($result);
            //echo '<div style="width:600px;margin:100px;"><pre style="font-size:10px;white-space:pre-line;word-wrap: break-word;word-break:break-all;">{<br>"portal":<br>' . json_encode($portal,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '<br>}</pre></div>';
            //echo json_encode($portal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        unset($result);

    }

    //$auth0_login_uri = isset($portal['sso_login_path']) ? 'https://madmin.auth0.com/authorize?response_mode=form_post&response_type=code%20id_token%20token&client_id=' . $portal['client_id'] . '&redirect_uri=' . $portal['sso_login_path'] . '&scope=' . $scope . '&audience=' . $audience . '&state=' . $state . '&nonce=' . $nonce : '';
    $auth0_login_uri = 'https://madmin.auth0.com/authorize?response_mode=form_post&response_type=code%20id_token%20token&client_id=' . $portal['client_id'] . '&redirect_uri=' . 'https://kiwiproperty.madmin-beta.com' . '&scope=' . $scope . '&audience=' . $audience . '&state=' . $state . '&nonce=' . $nonce;

    $auth0_logout_uri = 'https://madmin.auth0.com/v2/logout?client_id=' . $portal['client_id'] . '&returnTo=https://kiwiproperty.madmin-beta.com?logout=1';

    $_SESSION['portal'] = $portal;
    if(isset($_POST['code'])) {
        $_SESSION['auth0'] = $_POST;

        //echo $_GET['code'];
        //exit;
    }

    //var_dump($_SESSION['auth0']);

    if(isset($_GET['logout']) && $_GET['logout'] == 1) {
        $logged_out_uri = $_SESSION['auth0']['logged_out_uri'];
        //echo 'X' . $_SESSION['auth0']['logged_out_uri'];
        unset($_SESSION['auth0']);
        header('Location: ' . $portal['logged_out_uri']);
        exit;
    }

    if(!isset($_SESSION['auth0']) && !$_SESSION['auth0']) {
        //echo $auth0_login_uri;
        header('Location: ' . $auth0_login_uri);
        exit;
    } else {
        //ECHO 'Y';
        //var_dump($_SESSION['auth0']);
        //echo $auth0_logout_uri;
        //exit;
    }

?>
<!DOCTYPE html>
<html>

  <head>

    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900|Material+Icons" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/quasar@1.15.4/dist/quasar.min.css" rel="stylesheet" type="text/css">

    <style>

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
                padding-left: 2vh !important;
                padding-right: 2vh !important;
                width: 100vh !important;
                height: 7vh;
                font-size: 0.6em;
            }

            #nav-icons  {
                width: 100vw !important;
                padding: 2vw !important;
                font-size: 24vw !important;
            }

        }

        @media (orientation: landscape) {

            #masthead {
                padding-left: 1vh !important;
                padding-right: 1vh !important;
                width: 120vh !important;
                height: 7vh;
                font-size: 0.6em;
            }

            #nav-icons {
                width: 120vh !important;
                padding-top: 1vh !important;
                font-size: 16vh !important;
            }

        }

        .app-icon {
            border: 8px solid;
            border-radius: 20px;
            margin: 1vh;
        }

    </style>

  </head>

  <body>

    <div id="q-app">

        <template>
          <q-layout view="hHh LpR lfr">

            <q-drawer v-model="left" side="left" overlay bordered>
                <?php isset($links) ? var_dump($links) : '<br>NO LINK DATA</br>'; ?>
            </q-drawer>

            <q-drawer v-model="right" side="right" overlay bordered>
                <table>
                <?php
                    if(isset($_SESSION['portal']) && $_SESSION['portal']) {
                        foreach($_SESSION['portal'] AS $key => $value) {
                            echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                        }
                    }
                    if(isset($_SESSION['auth0']) && $_SESSION['auth0'] ) {
                        foreach($_SESSION['auth0'] AS $key => $value) {
                            echo '<tr><th style="text-align:left">' . $key . '</th><td>' . $value . '</td></tr>';
                        }
                    }
                    if(!isset($_SESSION['auth0']) || !isset($_SESSION['portal'])) {
                        echo '<tr><th style="text-align:left" colspan="2"><a href="' . $auth0_login_uri . '">LOGIN</a></th></tr>';
                    } else {
                        echo '<tr><th style="text-align:left" colspan="2"><a href="' . $auth0_logout_uri . '">LOGOUT</a></th></tr>';
                    }
                ?>
                </table>
            </q-drawer>

            <q-page-container>

                <div class="row">

                    <div class="col-grow">

                    </div>

                    <div>

                        <q-header class="bg-dark row justify-center">
                            <div id="masthead" class="row bg-dark text-white justify-between">
                                <q-toolbar>
                                    <q-btn dense flat round icon="stars" @click="left = !left"></q-btn>
                                    <q-toolbar-title>Madmin</q-toolbar-title>
                                    <q-btn dense flat round icon="menu" @click="right = !right"></q-btn>
                                </q-toolbar>
                            </div>
                        </q-header>

                        <div>

                            <div id="nav-icons" class="row justify-center">

                                <?php

                                    $file = fopen('material_design_icons.csv', 'r');
                                    $icons = fgetcsv($file);
                                    fclose($file);

                                    $i = 0;

                                    while($i < 20) {
                                        $icon = $icons[array_rand($icons)];
                                        echo '<q-icon :style="{color: getRandomColor()}" class="app-icon col-shrink" name="' . $icon . '"></q-icon>';
                                        $i++;
                                    }

                                ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-grow">

                    </div>

                </div>

            </q-page-container>

          </q-layout>
        </template>


    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@^2.0.0/dist/vue.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@1.15.4/dist/quasar.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@0.21.1/dist/axios.min.js"></script>

    <script>

    new Vue({
         el: '#q-app',
         data: function () {
           return {
               left: false,
               right: false
           }
         },
         methods: {
             getRandomColor: function() {
               return '#' + (Math.random()*0xFFFFFF<<0).toString(16);
           }
         },
       })

    </script>

  </body>
</html>