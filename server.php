<?php
###########################################################################
# Gameserver Webinterface                                                 #
# Copyright (C) 2010 Torsten Amshove <torsten@amshove.net>                #
#                                                                         #
# This program is free software; you can redistribute it and/or modify    #
# it under the terms of the GNU General Public License as published by    #
# the Free Software Foundation; either version 2 of the License, or       #
# (at your option) any later version.                                     #
#                                                                         #
# This program is distributed in the hope that it will be useful,         #
# but WITHOUT ANY WARRANTY; without even the implied warranty of          #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           #
# GNU General Public License for more details.                            #
#                                                                         #
# You should have received a copy of the GNU General Public License along #
# with this program; if not, write to the Free Software Foundation, Inc., #
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.             #
###########################################################################

if($_SESSION["ad_level"] >= 4){

// Wird fuer das Formular verwendet um zwischen add und edit zu unterscheiden
$submit_name = "add";
$submit_value = "Hinzuf&uuml;gen";
$display = "none";

if( (isset($_GET["cmd"]) && $_GET['cmd'] == "edit") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Es wurde auf edit geklickt - hier werden die Daten fuer das Formular eingelesen
  $submit_name = "edit";
  $submit_value = "&Auml;ndern";
  $display = "block";
  $query = ("SELECT * FROM server WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  $value = $stmt->fetch();
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "del") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Server loeschen
  $sql = ("DELETE FROM server WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  
  $sql = ("DELETE FROM running WHERE serverid = :id");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "active") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Server aktivieren / deaktivieren
  $sql = ("UPDATE server SET active = IF(active = 0, 1, 0) WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "access") && is_numeric($_GET["id"]) && !empty($_GET["id"]) && !empty($_POST["pw"])){
  // Zugang einrichten
  $sql = ("SELECT * FROM server WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  $server = $stmt->fetch();
  install_access($server,$_POST["pw"]);
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "access") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Passwort abfragen um den Zugang einzurichten
  $server = mysql_fetch_assoc(mysql_query("SELECT * FROM server WHERE id = '".mysql_real_escape_string($_GET["id"])."' LIMIT 1"));
  echo "<div class='meldung_notify'>";
  echo "Um den dauerhaften Zugang vom Webinterface auf den Server einzurichten muss einmalig das Passwort angegeben werden - das wird nicht gespeichert.<br>";
  echo "<form action='index.php?page=server&cmd=access&id=".$_GET["id"]."' method='POST'>";
  echo "Passwort f&uuml;r <b>".$server["user"]."@".$server["ip"]." (".$server["name"].")</b>: <input type='password' name='pw' size='10'><input type='submit' value='Zugang einrichten'>";
  echo "</form></div><br>";
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "reboot") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Server rebooten
  $sql = ("SELECT * FROM server WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  $server = $stmt->fetch();
  reboot_server($server);
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "shutdown") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Server herunterfahren
  $sql = ("SELECT * FROM server WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  $server = $stmt->fetch();
  shutdown_server($server);
}elseif( isset($_GET["cmd"]) && $_GET['cmd'] == "shutdown_all"){
  // Alle Server herunterfahren
  $sql = ("SELECT * FROM server");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->execute();
  foreach($stmt->fetchAll() as $row) shutdown_server($row);
}

// Formular wurde abgeschickt
if(isset($_POST["add"]) || isset($_POST["edit"])){
  if(empty($_POST["ip"]) || empty($_POST["name"]) || empty($_POST["user"])){
    echo "<div class='meldung_error'>IP, User und Name m&uuml;ssen angegeben werden!</div><br>";
    $display = "block";
    $value = $_POST;
    if(isset($_POST["edit"])){
      $submit_name = "edit";
      $submit_value = "&Auml;ndern";
      $display = "block";
    }
  }else{
    $id = mysql_real_escape_string($_POST["id"]);
    $name = mysql_real_escape_string($_POST["name"]);
    $ip = mysql_real_escape_string($_POST["ip"]);
    $user = mysql_real_escape_string($_POST["user"]);
    $score = mysql_real_escape_string($_POST["score"]);
    $notes = mysql_real_escape_string($_POST["notes"]);
    if(is_array($_POST["games"])) $games = mysql_real_escape_string(implode(",",$_POST["games"]));
    else $games = "";
    if($_POST["add"]){
      // Server hinzufuegen
      mysql_query("INSERT INTO server SET name = '".$name."', ip = '".$ip."', user = '$user', score = '".$score."', games = '".$games."', notes = '$notes', active = '1'");
      echo "<div class='meldung_ok'>Server eingetragen</div><br><div class='meldung_notify'><b>NICHT VERGESSEN:</b> Damit das Webinterface Zugang zum Server bekommt, bitte auf \"Zugang einrichten\" des jeweiligen Servers klicken.</div><br>";
    }elseif($_POST["edit"]){
      // Server aendern
      mysql_query("UPDATE server SET name = '".$name."', ip = '".$ip."', user = '$user', score = '".$score."', games = '".$games."', notes = '$notes' WHERE id = '".$id."' LIMIT 1");
    }
  }
}
if(isset($value) && isset($value['games']))
if(!is_array($value["games"])) $value["games"] = explode(",",$value["games"]); // Wenn aus DB ausgelesen, ist das noch kein Array

// Formular
echo "<a href='#' onClick='document.getElementById(\"formular\").style.display = \"block\";'>Server hinzuf&uuml;gen</a><br>";

$id   = isset($value['id']) ? $value['id'] : "";
$name = isset($value['name']) ? $value['name'] : "";
$ip   = isset($value['ip']) ? $value['ip'] : "";
$user = isset($value['user']) ? $value['user'] : "";

echo "<form action='index.php?page=server' method='POST' id='formular' style='display: $display;'>
<input type='hidden' name='id' value='".$id."'>
<table>
  <tr>
    <th colspan='2'>&nbsp;</th>
  </tr>
  <tr>
    <td>Name:</td>
    <td><input type='text' name='name' value='".$name."'></td>
  </tr>
  <tr>
    <td width='50'>IP:</td>
    <td><input type='text' name='ip' value='".$ip."'></td>
  </tr>
  <tr>
    <td width='50'>User:</td>
    <td><input type='text' name='user' value='".$user."'></td>
  </tr>
  <tr>
    <td>Games:</td>
    <td><select name='games[]' size=5 multiple>";
$games = array();
$query = ("SELECT id, icon, name FROM games ORDER BY name");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
foreach($stmt->fetchAll() as $row){
  $games[$row["id"]]["name"] = $row["name"];
  $games[$row["id"]]["icon"] = $row["icon"];
  echo "<option value='".$row["id"]."' ";
  if(in_array($row["id"],$value["games"])) echo "selected='selected'";
  echo ">".$row["name"]."</option>";
}
echo "</select></td>
  </tr>
  <tr>
    <td>Score:</td>
    <td><input type='text' name='score' value='".$value["score"]."'></td>
  </tr>
  <tr>
    <td>Notizen:</td>
    <td><textarea name='notes'>".$value["notes"]."</textarea></td>
  </tr>
  <tr>
    <td colspan='2' align='center'><input type='submit' name='".$submit_name."' value='".$submit_value."'></td>
  </tr>
</table>
</form>";

echo "<br><br>";

// Tabelle
echo "<table class='hover_row'>
  <tr>
    <th width='40'>Aktiv</th>
    <th width='40'>Ping</th>
    <th width='40'>Login</th>
    <th width='200'>Name</th>
    <th width='200'>Games</th>
    <th width='50'>Score</th>
    <th width='200'>Notizen</th>
    <th width='200'>&nbsp;</th>
  </tr>";

$query = ("SELECT * FROM server ORDER BY name");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
foreach($stmt->fetchAll() as $row){
  $ping_color = "#FF0000";
  $login_color = "#FF0000";
  if($row["active"] == 1 && host_check_ping($row)){
    $ping_color = "#00FF00";
    if(host_check_login($row)) $login_color = "#00FF00";
  }

  echo "<tr>
    <td valign='top' style='background-color: ".($row["active"] == 1 ? "#00FF00" : "#FF0000").";' align='center'><a href='index.php?page=server&cmd=active&id=".$row["id"]."'>chg</a></td>
    <td valign='top' style='background-color: $ping_color;'>&nbsp;</td>
    <td valign='top' style='background-color: $login_color;'>".$row["user"]."</td>
    <td valign='top' title='".$row["ip"]."'>".$row["name"]."</td>
    <td valign='top'>";
  foreach(explode(",",$row["games"]) as $g){
    echo "<img src='images/".$games[$g]["icon"]."' title='".$games[$g]["name"]."' height='$image_height'> ";
  }
  echo "</td>
    <td valign='top'>".$row["score"]."</td>
    <td valign='top'>".nl2br($row["notes"])."</td>
    <td valign='top' align='center'><a href='index.php?page=server&cmd=edit&id=".$row["id"]."'>edit</a> | <a href='index.php?page=server&cmd=del&id=".$row["id"]."' onClick='return confirm(\"Server wirklich l&ouml;schen?\");'>del</a> | <a href='index.php?page=server&cmd=access&id=".$row["id"]."'>Zugang einrichten</a><br>
    <a href='index.php?page=server&cmd=reboot&id=".$row["id"]."' onClick='return confirm(\"Server wirklich rebooten?\");'>reboot</a> | <a href='index.php?page=server&cmd=shutdown&id=".$row["id"]."' onClick='return confirm(\"Server wirklich herunterfahren?\");'>shutdown</a></td>
  </tr>";
}

echo "</table>";
echo "<a href='index.php?page=server&cmd=shutdown_all' onClick='return confirm(\"Wirklich alle Server herunterfahren?\");'>shutdown all</a>";
}
?>
