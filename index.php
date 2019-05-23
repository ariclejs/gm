<?php 
if( version_compare(PHP_VERSION, "5.2.0", ">=") ) 
{
    $cpm = session_get_cookie_params();
    session_set_cookie_params($cpm["lifetime"], $cpm["path"], $cpm["domain"], $cpm["secure"], true);
}

session_start();
define("_GAMECP_", "gamecp");
$noheader = true;
if( isset($_REQUEST["changeLanguage"]) && isset($_POST["lang"]) ) 
{
    @setcookie("gcplang", $_POST["lang"], @time() + 900000, "", "", true, true);
    $_SESSION["gamecp"]["lang"] = $_POST["lang"];
}

if( check_mysql_ok() == true ) 
{
    require_once("includes/mysql.inc.php");
    require_once("includes/core/includes/gamecp/settings.inc.php");
    if( isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "regkey" ) 
    {
        if( $GameCP->Login($_REQUEST["username"], $_REQUEST["password"], false, true, false) == 0 ) 
        {
            sql_query($safesql->query("UPDATE settings SET value='%s' WHERE name='gcplickey';", array( $GameCP->whitelist($_REQUEST["lickey"], "web") )));
            sql_query("UPDATE settings SET value='' WHERE name='gcplocalkey';");
            session_destroy();
            header("location: index.php?saved=true");
        }
        else
        {
            echo "<h3>Unable to reset key, check login details</h3>";
        }

    }

    if( isset($_REQUEST["showkey"]) ) 
    {
        exit( gcplickey );
    }

    $smarty->assign("gcplang", $lang);
    if( isset($user) && isset($pass) ) 
    {
        require("includes/core/includes/gamecp/login.inc.php");
    }
    else
    {
        if( isset($_COOKIE["gcpemail"]) ) 
        {
            $smarty->assign("gcpemail", $GameCP->whitelist($_COOKIE["gcpemail"]));
        }

        if( isset($_REQUEST["changeLanguage"]) && isset($_POST["lang"]) ) 
        {
            $smarty->assign("gcplang", $GameCP->whitelist($_POST["lang"]));
        }
        else
        {
            if( isset($_COOKIE["gcplang"]) ) 
            {
                $smarty->assign("gcplang", $GameCP->whitelist($_COOKIE["gcplang"]));
            }
            else
            {
                $smarty->assign("gcplang", $GameCP->whitelist($lang));
            }

        }

        if( isset($_REQUEST["callback"]) ) 
        {
            return NULL;
        }

        //if( $_REQUEST["mode"] == "lostpassword" || $_REQUEST["mode"] == "emailpassword" || $_REQUEST["mode"] == "validate" ) 
        if ( isset($_REQUEST["mode"]) &&  ( $_REQUEST["mode"] == "lostpassword" || $_REQUEST["mode"] == "emailpassword" || $_REQUEST["mode"] == "validate" ) )

        {
            if( $_REQUEST["mode"] == "validate" ) 
            {
                $key = unserialize(base64_decode($_REQUEST["key"]));
                $useremail = $key["useremail"];
                $time = $key["time"];
                $usrid = $key["id"];
                $timePassed = $time + 3600;
                if( time() <= $timePassed ) 
                {
                    $passQuery = sql_query($safesql->query("SELECT id FROM users WHERE email='%s' AND id='%i' AND active='1' LIMIT 1;", array( $GameCP->whitelist($useremail, "clean"), $GameCP->whitelist($usrid, "int") ))) or exit( mysql_error() );
                    $doPass = mysql_num_rows($passQuery);
                    if( $doPass == "1" ) 
                    {
                        $GameCP->loadIncludes("panel");
                        $Panel = new Panel();
                        $newpassword = $Panel->RandomPassword();
                        $GameCP->loadIncludes("linux");
                        $Linux = new Linux();
                        $Linux->Password($usrid, $newpassword);
                        $GameCP->SetPassword($usrid, $newpassword, false, true);
                        $GameCP->loadIncludes("email");
                        $Email->templatename = "Lost-Password";
                        $Email->userid = $usrid;
                        $Email->GetTemplateStuff();
                        $extravars = array(  );
                        $extravars[] = array( "var" => "\$Var1", "value" => $newpassword );
                        $Email->ReplaceStuff($extravars);
                        $Email->send();
                        $error = "5";
                        $Event->EventLogAdd($usrid, "User #" . $usrid . " has reset their password.");
                    }
                    else
                    {
                        echo "Unable to validate lost password data.";
                    }

                }
                else
                {
                    echo "Time has expired.";
                }

            }
            else
            {
                if( $_REQUEST["mode"] == "emailpassword" ) 
                {
                    if( isset($_POST["useremail"]) ) 
                    {
                        $useremail = $GameCP->whitelist($_POST["useremail"], "clean");
                        $userInfoQ = sql_query($safesql->query("SELECT id, active FROM users WHERE email='%s' LIMIT 1;", array( $GameCP->whitelist($useremail, "clean") ))) or exit( mysql_error() );
                        $userInfo = mysql_fetch_array($userInfoQ);
                        if( $userInfo["id"] ) 
                        {
                            if( $userInfo["active"] != "1" ) 
                            {
                                $error = "6";
                            }
                            else
                            {
                                $key = base64_encode(serialize(array( "time" => time(), "id" => $userInfo["id"], "useremail" => $useremail )));
                                $validurl = url . "" . "?mode=validate&key=" . $key;
                                $GameCP->loadIncludes("email");
                                $Email->templatename = "Lost-PasswordReset";
                                $Email->userid = $userInfo["id"];
                                $Email->GetTemplateStuff();
                                $extravars = array(  );
                                $extravars[] = array( "var" => "\$Var1", "value" => $validurl );
                                $extravars[] = array( "var" => "\$Var2", "value" => $_SERVER["REMOTE_ADDR"] );
                                $Email->ReplaceStuff($extravars);
                                $Email->send();
                                $error = "4";
                            }

                        }
                        else
                        {
                            $error = "1";
                        }

                    }
                    else
                    {
                        if( $doPass != "0" ) 
                        {
                            $error = "1";
                        }

                        if( !$useremail ) 
                        {
                            $error = "2";
                        }

                        if( !$username ) 
                        {
                            $error = "3";
                        }

                    }

                }

            }

            $smarty->assign("gcpemail", $GameCP->whitelist($_COOKIE["gcpemail"]));
            $smarty->assign("error", $error);
            $smarty->display("portal/lostpassword.tpl");
        }
        else
        {
            //if( $_REQUEST["mode"] == "logout" ) 
            if( isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "logout" ) 
            {
                $url = url;
                if( LOGOUTURL ) 
                {
                    $url = LOGOUTURL;
                    if( !preg_match("/http:/s", $url) && !preg_match("/https:/s", $url) ) 
                    {
                        $url = "http://" . $url;
                    }

                }

                $REMOTE_ADDR = $_SERVER["REMOTE_ADDR"];
                sql_query($safesql->query("DELETE FROM usersonline WHERE ip = '%s'", array( $GameCP->whitelist($REMOTE_ADDR, "clean") ))) or exit( mysql_error() );
                @session_start();
                if( isset($_SESSION["gamecp"]) ) 
                {
                    unset($_SESSION["gamecp"]);
                }

                if( version_compare(PHP_VERSION, "5.2.0", ">=") ) 
                {
                    $cpm = session_get_cookie_params();
                    session_set_cookie_params($cpm["lifetime"], $cpm["path"], $cpm["domain"], $cpm["secure"], true);
                }

                @session_regenerate_id();
                $_SESSION["gamecp"] = array(  );
                if( isset($_SESSION["switch_gamecp"]) ) 
                {
                    unset($_SESSION["switch_gamecp"]);
                }

                header("" . "location: " . $url);
            }
            else
            {
                //if( $_REQUEST["mode"] == "Contact" ) 
                if( isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "Contact" ) 
                {
                    if( captcha == "yes" ) 
                    {
                        require_once(path . "/includes/core/classes/captcha/recaptchalib.php");
                        $smarty->assign("captcha", captcha);
                        $smarty->assign("captchacode", recaptcha_get_html(captchapub, captchapriv, true));
                    }

                    $smarty->display("portal/contact.tpl");
                }
                else
                {
                    //if( $_REQUEST["mode"] == "Contacted" ) 
                    if( isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "Contacted" ) 
                    {
                        if( captcha == "yes" ) 
                        {
                            require_once(path . "/includes/core/classes/captcha/recaptchalib.php");
                            $resp = recaptcha_check_answer(captchapriv, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
                            if( !$resp->is_valid ) 
                            {
                                exit( "Captcha incorrect - please try again.<br><br><a href=\"javascript:history.go(-1)\"><b>Click here</b></a> to go back.<br><br><br>" );
                            }

                        }

                        $GameCP->loadIncludes("email");
                        $cname = $GameCP->whitelist($_REQUEST["name"]);
                        $cnotes = $GameCP->whitelist($_REQUEST["notes"], "striptags");
                        $cemail = $GameCP->whitelist($_REQUEST["email"]);
                        $Email->bcc = emailNotify;
                        $Email->emailto = $cemail;
                        $convert = array(  );
                        $convert[] = array( "var" => "\$cname", "value" => $cname );
                        $convert[] = array( "var" => "\$cemail", "value" => $cemail );
                        $convert[] = array( "var" => "\$cnotes", "value" => $cnotes );
                        $Email->templatename = "ContactUs";
                        $Email->GetTemplateStuff();
                        $Email->ReplaceStuff($convert);
                        $Email->send();
                        $smarty->assign("contacted", true);
                        $smarty->display("portal/contact.tpl");
                    }
                    else
                    {
                        if( isset($_REQUEST["page"]) ) 
                        {
                            $thepage = $GameCP->whitelist($_REQUEST["page"]);
                            $thefile = "includes/template/default/smarty/portal/" . $thepage . ".tpl";
                            if( is_file($thefile) ) 
                            {
                                $smarty->display("" . "portal/" . $thepage . ".tpl");
                            }
                            else
                            {
                                echo "Permission denied.";
                            }

                        }
                        else
                        {
                            //if( $_REQUEST["mode"] == "Announcements" ) 
                            if( isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "Announcements" ) 
                            {
                                $query = "SELECT * FROM news WHERE sid='announcements' ORDER BY id DESC";
                                $result = sql_query($query);
                                $newsList = array(  );
                                while( $row = mysql_fetch_array($result) ) 
                                {
                                    $content = $row["content"];
                                    $newsList[] = array( "id" => $row["id"], "content" => $content, "title" => $row["name"] );
                                }
                                $smarty->assign("newsDataList", $newsList);
                                $smarty->display("portal/announcements.tpl");
                            }
                            else
                            {
                                //if( $_REQUEST["mode"] == "Dashboard" || $_REQUEST["mode"] == "TT" || $_REQUEST["mode"] == "NewTT" ) 
                                if( isset($_REQUEST["mode"]) && ($_REQUEST["mode"] == "Dashboard" || $_REQUEST["mode"] == "TT" || $_REQUEST["mode"] == "NewTT" )) 
                                {
                                    setcookie("gamecp-redirect", $_REQUEST["mode"], time() + 9000);
                                    if( !(isset($_SESSION["gamecp"]["userinfo"]["username"]) && isset($_SESSION["gamecp"]["user"]["password"]) && isset($_SESSION["gamecp"]["userinfo"]["ulevel"])) ) 
                                    {
                                        if( basicindex == "true" || $deviceType != "computer" ) 
                                        {
                                            $smarty->display("portal/basicindex.tpl");
                                        }
                                        else
                                        {
                                            $smarty->display("portal/siteindex.tpl");
                                        }

                                    }
                                    else
                                    {
                                        switch( $GameCP->whitelist($_REQUEST["mode"]) ) 
                                        {
                                            case "Dashboard":
                                                header("" . "location: " . $url . "/system/");
                                                break;
                                            case "TT":
                                                header("" . "location: " . $url . "/system/tt.php?mode=view");
                                                break;
                                            case "NewTT":
                                                header("" . "location: " . $url . "/system/tt.php?mode=new");
                                                break;
                                            default:
                                                header("" . "location: " . $url . "/system/");
                                        }
                                    }

                                }
                                else
                                {
                                    if( basicindex == "true" || $deviceType != "computer" ) 
                                    {
                                        $smarty->display("portal/basicindex.tpl");
                                    }
                                    else
                                    {
                                        $smarty->display("portal/siteindex.tpl");
                                    }

                                }

                            }

                        }

                    }

                }

            }

        }

    }

}
else
{
    if( is_dir("installer") ) 
    {
        header("location: installer/");
    }
    else
    {
        echo "GameCP Installation Incomplete.";
    }

}

if( isset($conn) ) 
{
    mysql_close($conn);
}

unset($conn);
unset($logged_in);
unset($logging_out);
unset($GameCPSettings);
unset($smarty);
function check_mysql_ok()
{
    $file = "includes/mysql.inc.php";
    if( @file_exists($file) ) 
    {
        $lines = file($file);
        if( is_array($lines) ) 
        {
            foreach( $lines as $line_num => $line ) 
            {
                if( preg_match("/mysql_connect/i", $line) || preg_match("/mysql_pconnect/i", $line) ) 
                {
                    return true;
                }

            }
        }

        return false;
    }

    return false;
}

