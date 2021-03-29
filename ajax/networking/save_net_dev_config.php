<?php
/*
 Save settings of network devices (type, name, PW, APN ...)
  
 Called by js saveNetDeviceSettings (App/js/custom.js)
*/


require '../../includes/csrf.php';

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (isset($_POST['interface'])) {
    $int = $_POST['interface'];
    $cfg = [];
    $file = $int.".ini";
    $cfgfile="/etc/wvdial.conf";
    if ( $int == "mobiledata") {
        $cfg['pin'] = $_POST["pin-mobile"];
        $cfg['apn'] = $_POST["apn-mobile"];
        $cfg['apn_user'] = $_POST["apn-user-mobile"];
        $cfg['apn_pw'] = $_POST["apn-pw-mobile"];
        if (file_exists($cfgfile)) {
            if($cfg["pin"] !== "") exec('sudo /bin/sed -i  "s/CPIN=\".*\"/CPIN=\"'.$cfg["pin"].'\"/gi" '.$cfgfile);
            if($cfg["apn"] !== "") exec('sudo /bin/sed -i "s/\"IP\"\,\".*\"/\"IP\"\,\"'.$cfg["apn"].'\"/gi" '.$cfgfile);
            if($cfg["apn_user"] !== "") exec('sudo /bin/sed -i "s/^username = .*$/Username = '.$cfg["apn_user"].'/gi" '.$cfgfile);
            if($cfg["apn_pw"] !== "") exec('sudo /bin/sed -i "s/^password = .*$/Password = '.$cfg["apn_pw"].'/gi" '.$cfgfile);
        }
    } else if ( preg_match("/netdevices/",$int)) {
        if(!isset($_POST['opts']) ) {
            $jsonData = ['return'=>0,'output'=>['No valid data to add/delete udev rule ']];
            echo json_encode($jsonData);
            return;
        } else {
            $opts=explode(" ",$_POST['opts'] );
            $dev=$opts[0];
            $vid=$_POST["int-vid-".$dev];
            $pid=$_POST["int-pid-".$dev];
            $mac=$_POST["int-mac-".$dev];
            $name=trim($_POST["int-name-".$dev]);
            $name=preg_replace("/[^a-z0-9]/", "", strtolower($name));
            $type=$_POST["int-type-".$dev];
            $newtype=$_POST["int-new-type-".$dev];
            $udevfile=$_SESSION["udevrules"]["udev_rules_file"]; // default file /etc/udev/rules.d/80-net-devices.rules";

            // find the rule prototype and prefix
            $rule = "";
            foreach($_SESSION["udevrules"]["network_devices"] as $devt) {
                if($devt["type"]==$newtype) {
                    $rulenew = $devt["udev_rule"];
                    $prefix = $devt["name_prefix"];
                }
            }

            // check for an existing rule and delete lines with same MAC or same VID/PID
            if (!empty($vid) && !empty($pid)) {
                $rule = '^.*ATTRS{idVendor}==\"' . $vid . '\".*ATTRS{idProduct}==\"' . $pid . '\".*$';
                exec('sudo sed -i "/'.$rule.'/Id" '.$udevfile);  // clear all entries with this VID/PID
                $rule = '^.*ATTRS{idProduct}==\"' . $pid . '\".*ATTRS{idVendor}==\"' . $vid . '\".*$';
                exec('sudo sed -i "/'.$rule.'/Id" '.$udevfile);  // clear all entries with this VID/PID
            }
            if (!empty($mac)) {
                exec('sudo sed -i "/^.*'.$mac.'.*$/d" '.$udevfile);  // clear all entries with same MAC
            }
            // create new entry
            if ( ($type != $newtype) || !empty($name) ) { // new device type or new name
                if (empty($name)) $name = $prefix."*";
                if (!empty($mac)) $rule = preg_replace("/\\\$MAC\\\$/i", $mac, $rulenew);
                if (!empty($vid)) $rule = preg_replace("/\\\$IDVENDOR\\\$/i", $vid, $rule);
                if (!empty($pid)) $rule = preg_replace("/\\\$IDPRODUCT\\\$/i", $pid, $rule);
                if (!empty($name)) $rule = preg_replace("/\\\$DEVNAME\\\$/i",$name,$rule);
                if (!empty($rule)) exec('echo \''.$rule.'\' | sudo /usr/bin/tee -a '.$udevfile);
            }
            $ret=print_r($ret,true);
            $jsonData = ['return'=>0,'output'=>['Settings changed for device '.$dev. '<br>Changes will only be in effect after reconnecting the device'  ] ];
            echo json_encode($jsonData);
            return;
        }
    } else {
        $ip = $_POST[$int.'-ipaddress'];
        $netmask = mask2cidr($_POST[$int.'-netmask']);
        $dns1 = $_POST[$int.'-dnssvr'];
        $dns2 = $_POST[$int.'-dnssvralt'];

        $cfg['interface'] = $int;
        $cfg['routers'] = $_POST[$int.'-gateway'];
        $cfg['ip_address'] = $ip."/".$netmask;
        $cfg['domain_name_server'] = $dns1." ".$dns2;
        $cfg['static'] = $_POST[$int.'-static'];
        $cfg['failover'] = $_POST[$int.'-failover'];
    }
    if (write_php_ini($cfg, RASPI_CONFIG.'/networking/'.$file)) {
        $jsonData = ['return'=>0,'output'=>['Successfully Updated Network Configuration']];
    } else {
        $jsonData = ['return'=>1,'output'=>['Error saving network configuration to file']];
    }
} else {
    $jsonData = ['return'=>2,'output'=>'Unable to detect interface'];
}

echo json_encode($jsonData);
