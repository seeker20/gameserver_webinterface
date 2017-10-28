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
  $sql = ("SELECT * FROM games WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();

  $value = $stmt->fetch();
  $query = get_server_with_game($value["id"]);
  $value["server"] = array();
  foreach($stmt->fetchAll() as $row) $value["server"][] = $row["id"];
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "active") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Game aktivieren / deaktivieren
  mysql_query("UPDATE games SET active = IF(active = 0, 1, 0) WHERE id = '".mysql_real_escape_string($_GET["id"])."' LIMIT 1");
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "del") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Game loeschen
  $id = mysql_real_escape_string($_GET["id"]);
  $query = get_server_with_game($id);
  while($row = mysql_fetch_assoc($query)){ // Game bei den Servern austragen
    $old = explode(",",$row["games"]);
    $new = array();
    foreach($old as $o) if($o != $id) $new[] = $o;
    mysql_query("UPDATE server SET games = '".implode(",",$new)."' WHERE id = '".$row["id"]."' LIMIT 1");
  }
  mysql_query("DELETE FROM games WHERE id = '".$id."' LIMIT 1"); // Und loeschen
}elseif( (isset($_GET["cmd"]) && $_GET['cmd'] == "sync") && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Game Syncen - Formular
  $server = array();
  $sql =  ("SELECT * FROM server WHERE active = 1 ORDER BY name");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->execute();
  foreach($stmt->fetchAll() as $row) $server[] = $row;

  $server_mit_game = array();
  $query = get_server_with_game($_GET["id"]);
  
  foreach($query->fetchAll() as $row) $server_mit_game[] = $row;

  echo "<form action='index.php?page=games' method='POST'>";
  echo "<input type='hidden' name='gameid' value='".$_GET["id"]."'>";
  echo "<table width='200'>";
  echo "  <tr>";
  echo "    <th colspan='2'>Game-Files auf Server syncen</th>";
  echo "  </tr>";
  echo "  <tr>";
  echo "    <td>Quell-Server:</td>";
  echo "    <td><select name='src'>";
  foreach($server as $s) echo "<option value='".$s["id"]."'>".$s["name"]."</option>";
  echo "    </select></td>";
  echo "  </tr>";
  echo "  <tr>";
  echo "    <td valign='top'>Ziel-Server:</td>";
  echo "    <td><select name='dst[]' size='5' multiple>";
  foreach($server_mit_game as $s) echo "<option value='".$s["id"]."'>".$s["name"]."</option>";
  echo "    </select></td>";
  echo "  </tr>";
  echo "  <tr>";
  echo "    <td colspan='2'><input type='submit' name='sync' value='Sync starten'></td>";
  echo "  </tr>";
  echo "</table>";
  echo "</form><br><br>";
}elseif(isset($_POST["sync"])){
  // Game Syncen - Prozesse starten
  $game = mysql_fetch_assoc(mysql_query("SELECT * FROM games WHERE id = '".mysql_real_escape_string($_POST["gameid"])."' LIMIT 1"));
  $server = array();
  $query = mysql_query("SELECT * FROM server WHERE active = 1");
  while($row = mysql_fetch_assoc($query)) $server[$row["id"]] = $row;

  if(!$server[$_POST["src"]]) echo "<div class='meldung_error'>Quell-Server wurde nicht gefunden.</div><br>";
  else{
    $src = $server[$_POST["src"]];
    $dst = array();
    if(is_array($_POST["dst"])){
      foreach($_POST["dst"] as $d){
        if($d != $_POST["src"] && $server[$d]) $dst[] = $server[$d];
      }
    }elseif($server[$_POST["dst"]]) $dst[] = $server[$_POST["dst"]];
    else{
      echo "<div class='meldung_error'>Keinen Ziel-Server gefunden.</div><br>";
      $error = true;
    }

    if(!$error) foreach($dst as $d) sync_game($src,$d,$game);
  }
}

// Add/Edit Formular wurde abgeschickt
if(isset($_POST["add"]) || isset($_POST["edit"])){
  $error = false;

  if(empty($_POST["name"]) || empty($_POST["cmd"])){ // Name und cmd duerfen nicht leer sein
    echo "<div class='meldung_error'>Name und CMD m&uuml;ssen angegeben werden!</div><br>";
    $error = true;
  }elseif(!preg_match("/^[a-zA-Z0-9_-]*$/",$_POST["name"])){ // Name ueberpruefen
    echo "<div class='meldung_error'>Der Name darf nur aus Buchstaben, Zahlen, Binde- und Unterstrichen bestehen!</div><br>";
    $error = true;
  }elseif(!preg_match("/^[0-9,]*$/",$_POST["port_blacklist"])){ // Port-Blacklist muss Kommasepariert sein
    echo "<div class='meldung_error'>Die Port Blacklist darf nur aus Zahlen und Kommas bestehen!</div><br>";
  }

  if($error){
    $display = "block";
    $value = $_POST;
    if($_POST["edit"]){
      $submit_name = "edit";
      $submit_value = "&Auml;ndern";
      $display = "block";
    }
  }else{
    $id = mysql_real_escape_string($_POST["id"]);
    $icon = mysql_real_escape_string($_POST["icon"]);
    $name = mysql_real_escape_string($_POST["name"]);
    $folder = mysql_real_escape_string($_POST["folder"]);
    $cmd = mysql_real_escape_string($_POST["cmd"]);
    $defaults = mysql_real_escape_string($_POST["defaults"]);
    $start_port = mysql_real_escape_string($_POST["start_port"]);
    $port_blacklist = mysql_real_escape_string($_POST["port_blacklist"]);
    $score = mysql_real_escape_string($_POST["score"]);
    $token_pool = mysql_real_escape_string($_POST["token_pool"]);
    $connect_cmd = mysql_real_escape_string($_POST["connect_cmd"]);
    if(is_array($_POST["server"])) $server = $_POST["server"];
    else $server = "";
    if($_POST["add"]){
      // Game anlegen
      mysql_query("INSERT INTO games SET icon = '".$icon."', name = '".$name."', folder = '".$folder."', cmd = '".$cmd."', defaults = '".$defaults."', start_port = '".$start_port."', port_blacklist = '".$port_blacklist."', score = '".$score."', token_pool = '".$token_pool."', connect_cmd = '".$connect_cmd."', active = '1'");
      $id = mysql_insert_id();
    }elseif($_POST["edit"]){
      // Game aendern
      mysql_query("UPDATE games SET icon = '".$icon."', name = '".$name."', folder = '".$folder."', cmd = '".$cmd."', defaults = '".$defaults."', start_port = '".$start_port."', port_blacklist = '".$port_blacklist."', score = '".$score."', token_pool = '".$token_pool."', connect_cmd = '".$connect_cmd."' WHERE id = '".$id."' LIMIT 1");
      $query = get_server_with_game($id);
      while($row = mysql_fetch_assoc($query)){ // Alle Verweise zu dem Game loeschen - werden gleich wiederhergestellt
        $old = explode(",",$row["games"]);
        $new = array();
        foreach($old as $o) if($o != $id) $new[] = $o;
        mysql_query("UPDATE server SET games = '".implode(",",$new)."' WHERE id = '".$row["id"]."' LIMIT 1");
      }
    }
    // Anlegen der angegebenen Verweise zu den Servern
    $query = mysql_query("SELECT id, games FROM server WHERE id IN (".implode(",",$server).")");
    while($row = mysql_fetch_assoc($query)){
      $old = explode(",",$row["games"]);
      $old[] = $id;
      mysql_query("UPDATE server SET games = '".implode(",",$old)."' WHERE id = '".$row["id"]."' LIMIT 1");
    }
  }
}

if(!isset($value["server"])  || !is_array($value["server"])) $value["server"] = array(); // Workaround um Fehler zu vermeiden

// Formular
echo "<a href='#' onClick='document.getElementById(\"formular\").style.display = \"block\";'>Game hinzuf&uuml;gen</a><br>";

$id = isset($value['id']) ? $value['id'] : "";
$name = isset($value['name']) ? $value['name'] : "";
$folder = isset($value['folder']) ? $value['folder'] : "";
$cmd = isset($value['cmd']) ? $value['cmd'] : "";
$defaults = isset($value['defaults']) ? $value['defaults'] : "";
$startport = isset($value['start_port']) ? $value['start_port'] : "";
$blackport = isset($value['port_blacklist']) ? $value['port_blacklist'] : "";
$score = isset($value['score']) ? $value['score'] : "";
$connectcm = isset($value['connect_cmd']) ? $value['connect_cmd'] : "";
echo "<form action='index.php?page=games' method='POST' id='formular' style='display: $display;'>
<input type='hidden' name='id' value='".$id."'>
<table>
  <tr>
    <th colspan='2'>&nbsp;</th>
  </tr>
  <tr>
    <td width='50'>Icon:</td>
    <td><select name='icon'>";
$icons = scandir("images");
foreach($icons as $i){
  if($i == "." || $i == ".." || $i == ".svn") continue;
  echo "<option ";
  if(isset($value['icon']) &&   $value["icon"] == $i) echo "selected = \"selected\"";
  echo ">".$i."</option>";
}
echo "</select></td>
  </tr>
  <tr>
    <td>Name:</td>
    <td><input type='text' name='name' value='".$name."'></td>
  </tr>
  <tr>
    <td>Ordner:</td>
    <td><input type='text' name='folder' value='".$folder."'></td>
  </tr>
  <tr>
    <td>CMD:</td>
    <td><input type='text' name='cmd' value='".$cmd."' size='100'><br>
        ##port## & ##port1## f&uuml;r Ports, ##token## f&uuml;r Token, ##var1## .. f&uuml;r weitere Variablen</td>
  </tr>
  <tr>
    <td>Defaults:</td>
    <td><input type='text' name='defaults' value='".$defaults."' size='100'><br>
        In der Reihenfolge der Variablen im CMD (ohne ##port## & ##port1## & ##token##) - Getrennt durch ;</td>
  </tr>
  <tr>
    <td>Startport:</td>
    <td><input type='text' name='start_port' value='".$startport."'></td>
  </tr>
  <tr>
    <td>Port Blacklist:</td>
    <td><input type='text' name='port_blacklist' value='".$blackport."'><br>
        Ports, die ausgelassen werden sollen - Kommasepariert (z.B. f&uuml;r cs:go 27020,27021,27022,27023,27024)</td>
  </tr>
  <tr>
    <td>Score:</td>
    <td><input type='text' name='score' value='".$score."'></td>
  </tr>
  <tr>
    <td>Token-Pool:</td>
    <td><select name='token_pool'><option value='0'>---</option>";
$sql = "SELECT id, name FROM token ORDER BY name";
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
$stmt->execute();
foreach($stmt->fetchAll() as $row){
  echo "<option value='".$row["id"]."' ";
  if($row["id"] == $value["token_pool"]) echo "selected='selected'";
  echo ">".$row["name"]."</option>";
}
echo "</select></td>
  </tr>
  <tr>
    <td>Connect CMD:</td>
    <td><input type='text' name='connect_cmd' value='".$connectcm."' size='100'><br>
        Variablen wie in CMD werden ersetzt - zus&auml;tzlich gibt es noch ##ip## f&uuml;r die IP des laufenden Servers - Beispiel: steam://connect/##ip##:##port##</td>
  </tr>
  <tr>
    <td>Server:</td>
    <td><select name='server[]' size='5' multiple>";
$sql = ("SELECT id, name FROM server ORDER BY name");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
$stmt->execute();
foreach($stmt->fetchAll() as $row){
  echo "<option value='".$row["id"]."' ";
  if(in_array($row["id"],$value["server"])) echo "selected='selected'";
  echo ">".$row["name"]."</option>";
}
echo "</select></td>
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
    <th width='50'>Icon</th>
    <th width='100'>Name</th>
    <th width='350'>defaults</th>
    <th width='50'>Startport</th>
    <th width='50'>Score</th>
    <th width='100'>Token-Pool</th>
    <th width='100'>Connect CMD</th>
    <th width='100'>&nbsp;</th>
  </tr>";

$sql = ("SELECT * FROM games ORDER BY name");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
$stmt->execute();
foreach($stmt->fetchAll() as $row){
    $token_pool_name = "-";
    if($row["token_pool"] > 0) {
        $sql = ("SELECT name FROM token WHERE id=:id LIMIT 1");
        $stmt2 = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
        $stmt2->bindValue(":id",$row['token_pool']);
        $stmt2->execute();
        $token_pool_name = $stmt2->fetch()['name'];
    }
  

  echo "<tr>
    <td valign='top' style='background-color: ".($row["active"] == 1 ? "#00FF00" : "#FF0000").";' align='center'><a href='index.php?page=games&cmd=active&id=".$row["id"]."'>chg</a></td>
    <td align='center'><img src='images/".$row["icon"]."' height='$image_height'></td>
    <td>".$row["name"]."</td>
    <td>".$row["defaults"]."</td>
    <td>".$row["start_port"]."</td>
    <td>".$row["score"]."</td>
    <td>".$token_pool_name."</td>
    <td>".(empty($row["connect_cmd"]) ? "-" : "X")."</td>
    <td align='center'><a href='index.php?page=games&cmd=edit&id=".$row["id"]."'>edit</a> | <a href='index.php?page=games&cmd=del&id=".$row["id"]."' onClick='return confirm(\"Game wirklich l&ouml;schen?\");'>del</a> | <a href='index.php?page=games&cmd=sync&id=".$row["id"]."'>sync</a></td>
  </tr>";
}

echo "</table>";

echo "<br><br>";

$syncs = sync_list();
if(count($syncs) > 1){
  echo "<table>";
  echo "  <tr>";
  echo "    <th width='300'>Aktive Sync-Prozesse:</th>";
  echo "  </tr>";
  foreach($syncs as $s){
    echo "<tr>";
    echo "  <td>$s</td>";
    echo "</tr>";
  }
  echo "</table>";
}
}
?>
