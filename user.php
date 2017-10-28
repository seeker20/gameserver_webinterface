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

if($_SESSION["ad_level"] >= 5){

// Wird fuer das Formular verwendet um zwischen add und edit zu unterscheiden
$submit_name = "add";
$submit_value = "Hinzuf&uuml;gen";
$display = "none";

$cmd = (isset($_GET['cmd'])) ? $_GET['cmd'] : "";
if($cmd == "edit" && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // Es wurde auf edit geklickt - hier werden die Daten fuer das Formular eingelesen
  $submit_name = "edit";
  $submit_value = "&Auml;ndern";
  $display = "block";
  $sql = ("SELECT id, login, name, ad_level FROM user WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  $value = $stmt->fetch();
}elseif($cmd == "del" && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // User loeschen
  $sql = ("DELETE FROM user WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
}elseif($cmd == "pw" && is_numeric($_GET["id"]) && !empty($_GET["id"])){
  // PW zuruecksetzen auf default-pw
  $sql =("UPDATE user SET pw = :pw WHERE id = :id LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
  $stmt->bindValue(":pw",sha1($default_pw));
  $stmt->bindValue(":id",$_GET['id']);
  $stmt->execute();
  echo "<div class='meldung_ok'>Passwort neu gesetzt: $default_pw</div><br>";
}

// Formular wurde abgeschickt
if(isset($_POST["add"]) || isset($_POST["edit"])){
  if(empty($_POST["login"])){ // Login-Name darf nicht leer sein
    echo "<div class='meldung_error'>Der login-Name muss angegeben werden!</div><br>";
    $display = "block";
    $value = $_POST;
    if(isset($_POST["edit"])){
      $submit_name = "edit";
      $submit_value = "&Auml;ndern";
      $display = "block";
    }
  }else{
    $id = $_POST["id"];
    $login = $_POST["login"];
    $name = $_POST["name"];
    $rechte = $_POST["ad_level"];
    if(isset($_POST["add"])){
      // User mit default-pw anlegen
      $sql = ("INSERT INTO user (login,name,ad_level,pw) VALUES(:login,:name,:rechte,:pw)");
      $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
      $stmt->bindValue(":login",$login);
      $stmt->bindValue(":name",$name);
      $stmt->bindValue(":rechte",$rechte);
      $stmt->bindValue(":pw",sha1($default_pw));
      $stmt->execute();
      echo "<div class='meldung_ok'>User angelegt</div><br><div class='meldung_notify'>Passwort: <b>$default_pw</b></div><br>";
    }elseif(isset($_POST["edit"])){
      // User editieren
      $sql = ("UPDATE user SET login = :login, name = :name, ad_level = :rechte WHERE id = :id LIMIT 1");
      $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
      $stmt->bindValue(":login",$login);
      $stmt->bindValue(":name",$name);
      $stmt->bindValue(":rechte",$rechte);
      $stmt->bindValue(":id",$id);
      $stmt->execute();
      echo "<div class='meldung_ok'>User ge&auml;ndert.</div><br>";
    }
  }
}

// Formular
echo "<a href='#' onClick='document.getElementById(\"formular\").style.display = \"block\";'>User hinzuf&uuml;gen</a><br>";

$id = (isset($value['id'])) ? $value['id'] : "";
$login = (isset($value['login'])) ? $value['login'] : "";
$name = (isset($value['name'])) ? $value['name'] : "";

echo "<form action='index.php?page=user' method='POST' id='formular' style='display: $display;'>
<input type='hidden' name='id' value='".$id."'>
<table>
  <tr>
    <th colspan='2'>&nbsp;</th>
  </tr>
  <tr>
    <td width='50'>Login:</td>
    <td><input type='text' name='login' value='".$login."'></td>
  </tr>
  <tr>
    <td>Name:</td>
    <td><input type='text' name='name' value='".$name."'></td>
  </tr>
  <tr>
    <td>Rechte:</td>
    <td><select name='ad_level'>";
foreach($ad_level as $k => $v){
  echo "<option value='$k' ";
  if(isset($value['ad_level']) && $value["ad_level"] == $k) echo "selected='selected'";
  echo ">$v</option>";
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
echo "<table>
  <tr>
    <th width='100'>Login</th>
    <th width='200'>Name</th>
    <th width='100'>Rechte</th>
    <th width='150'>&nbsp;</th>
  </tr>";

$sql = ("SELECT id, login, name, ad_level FROM user ORDER BY login");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
$stmt->execute();
foreach($stmt->fetchAll() as $row){
  echo "<tr>
    <td>".$row["login"]."</td>
    <td>".$row["name"]."</td>
    <td>".$ad_level[$row["ad_level"]]."</td>
    <td align='center'><a href='index.php?page=user&cmd=edit&id=".$row["id"]."'>edit</a> | <a href='index.php?page=user&cmd=pw&id=".$row["id"]."'>reset PW</a> | <a href='index.php?page=user&cmd=del&id=".$row["id"]."' onClick='return confirm(\"User wirklich l&ouml;schen?\");'>del</a></td>
  </tr>";
}

echo "</table>";
}
?>
