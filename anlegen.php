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

if($_SESSION["ad_level"] >= 1){

if(isset($_POST["anlegen"])){
  // Game starten
  if($_POST["server"] <= 0){
    echo "<div class='meldung_error'>Es wurde kein Server ausgew&auml;hlt, auf dem das Game gestartet werden soll. Wenn kein Server ausgew&auml;hlt werden konnte, sind vermutlich alle Server voll.</div><br>";
  }else{
    $query = ("SELECT * FROM games WHERE id = :game LIMIT 1");
    $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
    $stmt->bindValue(":game",$_POST["game"]);
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query = ("SELECT * FROM server WHERE id = :server LIMIT 1");
    $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
    $stmt->bindValue(":server",$_POST["server"]);
    $stmt->execute();
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $port = get_port($server,$game["start_port"],$game["port_blacklist"]); // Port ermitteln - erster freier Port ab start_port
    if(!$port) echo "<div class='meldung_error'>Konnte keinen freien Port finden - Server erreichbar?</div><br>";
    else{
      $port1 = get_port($server,$port+1,$game["port_blacklist"]); // Port ermitteln - erster freier Port ab ermittelten Port
      if(!$port1) echo "<div class='meldung_error'>Konnte keinen freien Port finden - Server erreichbar?</div><br>";
      else{
        // Werden Token verwendet und gibt es einen freien?
        $token = "";
        if($game["token_pool"] > 0){
          $query = ("SELECT * FROM token WHERE id = :tokenpool LIMIT 1");
          $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
          $stmt->bindValue(":tokenpool",$game["token_pool"]);
          $stmt->execute();
          $token_pool = $stmt->fetch();
          $token = get_token($token_pool);
        }
        if($token === false) echo "<div class='meldung_error'>Alle Token bereits vergeben - keinen freien Token gefunden.</div><br>";
        else{
          $vars = parse_cmd($game["cmd"]); // Variablen aus cmd auslesen
          $cmd = str_replace("##port##",$port,$game["cmd"]);
          $values = "port => $port<br>";
  
          if(strstr($cmd,"##port1##")){
            $cmd = str_replace("##port1##",$port1,$cmd);
            $values .= "port1 => $port1<br>";
          }
  
          if(strstr($cmd,"##token##")){
            $cmd = str_replace("##token##",$token,$cmd);
            $values .= "token => $token<br>";
          }
  
          foreach($vars as $v){
            // Variablen durch Werte ersetzen
            $cmd = str_replace($v,$_POST[$v],$cmd);
            $values .= substr($v,2,-2)." => ".$_POST[$v]."<br>";
          }
          if($_SESSION["ad_level"] >= 4) echo "<div class='meldung_notify'><b>CMD:</b> $cmd</div><br>"; // Fuer Admins wird der Befehl mit angezeigt
          $screen = $game["name"]."_".$port;
          if(!starte_cmd($server,$cmd,$screen,$game["folder"])){ // Server starten ...
            echo "<div class='meldung_error'>Server konnte nicht gestartet werden.</div><br>";
          }else{
            // Und in die "running"-Tabelle einfuegen
            $sql = ("INSERT INTO running SET screen = :screen, serverid = :serverid, gameid = :gameid, port = :port, cmd = :cmd, score = :score, token_pool = :tokenID, token = :token, vars = :values");
            
            $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
            $stmt->bindValue(":screen",$screen);
            $stmt->bindValue(":serverid",$server['id']);
            $stmt->bindValue(":gameid",$game['id']);
            $stmt->bindValue(":port",$port);
            $stmt->bindValue(":cmd",str_replace("'","\'",$cmd));
            $stmt->bindValue(":score",$game['score']);
            $stmt->bindValue(":tokenID",$token_pool['id']);
            $stmt->bindValue(":token",$token);
            $stmt->bindValue(":values",str_replace("'","\'",$values));
            $stmt->execute();
            echo "<div class='meldung_ok'>Server erfolgreich gestartet.</div><br>";
          }
          unlink($tmp_dir."/".$server["ip"]."_".$port); // Lockfile loeschen
          unlink($tmp_dir."/".$server["ip"]."_".$port1); // Lockfile loeschen
        }
      }
    }
  }
}

// Auflisten der Games
$query = ("SELECT id, icon, name FROM games ORDER BY name");
$stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
$stmt->execute();
foreach($stmt->fetchAll() as $row){
  echo "<a href='index.php?page=anlegen&game=".$row["id"]."'><img src='images/".$row["icon"]."' height='$image_height'> ".$row["name"]."</a><br>";
}

echo "<br>";

// Wenn ein Game angeklickt wurde, Formular anzeigen
if(isset($_GET["game"])){
  $query = ("SELECT * FROM games WHERE id = :game LIMIT 1");
  $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($query);
  $stmt->bindValue(":game",$_GET['game']);
  $stmt->execute();
  $game = $stmt->fetch(PDO::FETCH_ASSOC);

  if($game["active"] == 0) echo "<div class='meldung_error'>Das Game ist deaktiviert - hier bastelt gerade jemand - dieses Game bitte nur f&uuml;r Testzwecke starten!</div><br>";

  echo "<form action='index.php?page=anlegen' method='POST'>
  <input type='hidden' name='game' value='".$game["id"]."'>
  <table>
    <tr>
      <td>Zielserver:</td>
      <td><select name='server'>";
$query = get_server_with_game($game["id"]); // Zeigt nur die Server an, auf denen das Game laeuft ...
foreach($query->fetchAll() as $row){
  
  if(host_check_login($row)){ // ... und auch nur wenn der Server online ist
    echo "<option value='".$row["id"]."' ";
    $sql =("SELECT SUM(score) AS score FROM running GROUP BY serverid HAVING serverid =:id");
    $stmt = Core::getInstance()->getInterfaceDB()->getPDO()->prepare($sql);
    $stmt->bindValue(":id",$row['id']);
    $stmt->execute();
    $nrow = $stmt->fetch();
    $score_used = $nrow["score"];
    if(($row["score"]-$score_used) < $game["score"]) echo "disabled"; // Wenn der Score zu hoch ist, Server ausgrauen
    echo ">".$row["name"]."</option>";
  }
}
  echo "</select></td>
    </tr>";
  $defaults = explode(";",$game["defaults"]);
  $vars = parse_cmd($game["cmd"]);
  $i=0;
  foreach($vars as $var){
    // Pro Variable wird ein Formular-Feld angezeigt und mit den Defaults gefuellt, wenn vorhanden
    echo "<tr>
      <td>".substr($var,2,-2).":</td>
      <td><input type='text' name='".$var."' value='".$defaults[$i]."' size='70'></td>
    </tr>";
    $i++;
  }
  echo "<tr>
      <td colspan='2' align='center'><input type='submit' name='anlegen' value='starten'></td>
    </tr>
  </table></form>";
}
}
?>
