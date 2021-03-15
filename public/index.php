<?php

    session_start();
    header("Access-Control-Allow-Origin: https://madmin.auth0.com");

    //$scope = 'openid%20profile%20email%20phone';
    $scope = 'openid%20profile%20email%20phone';
    $audience = 'https://api.madmin-beta.com';
    $state = 'T3JeQN-6x7md-97Mvh677ADcVLDx3ZIx';
    $nonce = 'vh677ADcVLDx3ZIxVT233xUSc-OJLkhs';

    //Default values, likely to be overridden if portal found
    //$_SESSION['portal']['client_id'] = 'bjxYsi4ZHFfD7D4VpVvEdRFyGTtw9ug9';
    //$_SESSION['portal']['client_secret'] = '4mZrXxO30VT3JeQN-6x7md-97Mvh677ADcVLDx3ZIxVT233xUSc-OJLkhsnMU20u';

    //Attempt to derive subdomain
    $parsedUrl = parse_url($_SERVER['HTTP_HOST']);
    $host = explode('.', $parsedUrl['path']);
    $subdomain = $host[0];

    //echo $subdomain;

    if(!isset($_SESSION['portal']['subdomain']) || ($_SESSION['portal']['subdomain'] != $subdomain)) {

        if (isset($subdomain)) {

            //If subdomain found, check for portal
            $mysqli = mysqli_connect("localhost", "api_madmin_com", "api_madmin_com", "api_madmin_com");

            $sql = 'SELECT * FROM portal WHERE subdomain = "' . $subdomain . '"';
            $result = mysqli_query($mysqli, $sql);

            if(mysqli_num_rows($result)) {
                $_SESSION['portal'] = mysqli_fetch_assoc($result);
            }

            unset($result);

        }

        $auth0_login_uri = 'https://madmin.auth0.com/authorize?response_mode=form_post&response_type=code%20id_token%20token&client_id=' . $_SESSION['portal']['client_id'] . '&redirect_uri=' . 'https://' . $_SESSION['portal']['subdomain'] . '.madmin-beta.com' . '&scope=' . $scope . '&audience=' . $audience . '&state=' . $state . '&nonce=' . $nonce;
        $auth0_logout_uri = 'https://madmin.auth0.com/v2/logout?client_id=' . $_SESSION['portal']['client_id'] . '&returnTo=https://' . $_SESSION['portal']['subdomain'] . '.madmin-beta.com?logout=1';

    }



    if(isset($_POST['code'])) {
        $_SESSION['auth0'] = $_POST;
    }

    if(isset($_GET['logout']) && $_GET['logout'] == 1) {

        $portal_logged_out_uri = $_SESSION['portal']['logged_out_uri'];

        session_unset();
        session_destroy();

        header('Location: ' . $portal_logged_out_uri);
        exit;

    }

    if(!isset($_SESSION['auth0']) && !$_SESSION['auth0']) {
        header('Location: ' . $auth0_login_uri);
        exit;
    }

?>
<!DOCTYPE html>
<html>

    <head>

        <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900|Material+Icons" rel="stylesheet" type="text/css">
        <link href="https://cdn.jsdelivr.net/npm/quasar@1.15.4/dist/quasar.min.css" rel="stylesheet" type="text/css">

        <style>

            @font-face {
                font-family: 'SFMono';
                src: url("../fonts/SFMono.otf") format("opentype");
            }

            @font-face {
                font-family: 'SFMono';
                font-weight: bold;
                src: url("../fonts/SFMono.otf") format("opentype");
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

                .q-toolbar__title {
                    font-family: 'SFMono' !important;
                    font-size: 3vw !important;
                    line-height: 3vw !important;
                }

                .head-icon {
                    font-size: 2.5vw !important;
                    line-height: 2.5vw !important;
                }

                #nav-icons  {
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

            }

            @media (orientation: landscape) {

                #masthead {
                    padding-left: 5vh !important;
                    padding-right: 5vh !important;
                    width: 110vh !important;
                    height: 7vh !important;
                }

                .q-toolbar__title {
                    font-family: 'SFMono' !important;
                    font-size: 3vh !important;
                    line-height: 3vh !important;
                }

                .head-icon {
                    font-size: 2.5vh !important;
                    line-height: 2.5vh !important;
                }

                #nav-icons {
                    padding-left: 2vh !important;
                    padding-right: 2vh !important;
                    width: 110vh !important;
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
                    font-size: 3vh;
                }

            }

            .app-label {
                font-family: 'SFMono' !important;
                text-align: center;
                line-height: center;
            }

        </style>

    </head>

    <body>

        <div id="q-app">
            <template>
                <q-layout view="hHh lpr lfr">



                    <q-page-container>
                        <div class="row">
                            <div class="col-grow"></div>

                            <div>

                                <q-header class="bg-dark row justify-center">
                                    <div id="masthead" class="row bg-dark text-white justify-between">
                                        <q-toolbar>
                                            <q-btn class="head-icon" dense flat round icon="stars" @click="left = !left"></q-btn>
                                            <q-toolbar-title>Madmin</q-toolbar-title>
                                            <q-btn class="head-icon" dense flat round icon="menu" @click="right = !right"></q-btn>
                                        </q-toolbar>
                                    </div>
                                </q-header>

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

                                <div>

                                    <div id="nav-icons" class="row justify-center">

                                        <?php

                                            $file = fopen('../icons/material_design_icons.csv', 'r');
                                            $icons = fgetcsv($file);
                                            fclose($file);

                                            $i = 0;

                                            while($i < 20) {
                                                $icon = $icons[array_rand($icons)];
                                                echo '<div class="madmin-icon"><q-icon :style="{\'background-color\': getRandomColor()}" class="app-icon col-shrink" name="' . $icon . '"></q-icon><div class="app-label">APP</div></div>';
                                                $i++;
                                            }

                                        ?>

                                    </div>

                                </div>

                            </div>

                            <div class="col-grow"></div>
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