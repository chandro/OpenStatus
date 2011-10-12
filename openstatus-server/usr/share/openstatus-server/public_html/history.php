<?php

/*****************************************************************
* File: history.php
* Desc: Shows the alert history for a specific server
* Req: $_GET['uid'] (int) - `server`.`uid`
*****************************************************************/

$requirelogin = false; // Change this to true if you want to require a valid username and password to view this page
$alerts_require_login = false; // Change this to true if you want to require a login to view alerts
require('../header.php');

$server_uid = intval($_GET['uid']);

if (($auth === false && $requirelogin === false) || $auth === true) {

	if ($auth === true) {
		if (isset($_GET['ack'])) {
			$ack = intval($_GET['ack']);
			$ackq = $db->prepare('UPDATE alerts SET acked = 1 WHERE id = ?');
			$ackq->execute(array($ack));
			header('Location: history.php?uid='.$server_uid);
		}
	}

	/* From http://www.php.net/manual/en/function.filesize.php#100097, removed bytes*/
	function format_kbytes($size) {
		return round($size/1024/1024, 2);
	}

	function gen_color($load) {
		$green = 0;
		$red = 3;
		$colors = array('00FF00', '11FF00', '22FF00', '33FF00', '44FF00', '55FF00', '66FF00', '77FF00', '88FF00', '99FF00', 'AAFF00', 'BBFF00', 'CCFF00', 'DDFF00', 'EEFF00', 'FFFF00', 'FFEE00', 'FFDD00', 'FFCC00', 'FFBB00', 'FFAA00', 'FF9900', 'FF8800', 'FF7700', 'FF6600', 'FF5500', 'FF4400', 'FF3300', 'FF2200', 'FF1100', 'FF0000');
		$count = count($colors)-1;
		$map = intval((($load - $green) * $count) / ($red - $green));
		if($map > $count) { $map = $count; }
		return $colors[$map];
	}

	echo '
			<div class="stats_container" id="stats">';

	$dbs = $db->prepare('SELECT * FROM servers WHERE disabled = 0 AND uid = ? ORDER BY hostname ASC');
	$result = $dbs->execute(array($server_uid));
	$i = 0;
	$provider = '';
	$jsend = '';
	while ($row = $dbs->fetch(PDO::FETCH_ASSOC)) {

		echo '
			<table style="border: 1;" id="servers">
			<thead>
				<tr><th colspan="7">Server: '.$row['hostname'].'</th></tr>
				<tr>
					<th scope="col">Services</th>
					<th scope="col">Last Updated</th>
					<th scope="col">Uptime</th>
					<th scope="col">RAM</th>
					<th scope="col">Disk</th>
					<th scope="col">Load</th>
				</tr>
			</thead>
				<tbody>';


		$i++;
		$provider = $row['provider'];
	   	if ($row['status'] == "0") {
			echo '<tr style="text-align: center" class="offline">';
		} elseif ($row['uptime'] == "n/a") {
			echo '<tr style="text-align: center" class="online-but-no-data">';
		} else {
			echo '<tr style="text-align: center">';
		}
		echo '<td>';

		$dbq = $db->prepare('SELECT * FROM processes WHERE uid = ? ORDER BY name ASC');
		$dbr = $dbq->execute(array($row['uid']));
		echo '<table class="services">';
		while ($service = $dbq->fetch(PDO::FETCH_ASSOC)) {
			echo '<tr><td>'. $service['name'] .'</td><td>'. ($service['status'] == 0 ? '<img src="/images/up.png" />' : '<img src="/images/down.png" />') .'</td></tr>';
		}
		echo '</table>';
		echo '</td>';
		echo '<td><span id="time-'.$i.'"></span></td>';
		$jsend .= '$(function () {
                                $(\'#time-'.$i.'\').countdown({since: "-'.(time()-$row['time']).'S", compact: true});
                        });';

		echo '<td>'. $row['uptime'] .'</td>';
		echo '<td class="5pad">';
		if(empty($row['mtotal'])) {
			echo "N/A";
		} else {
			$mp = ($row['mused']-$row['mbuffers'])/$row['mtotal']*100;
			$used = $row['mused'] - $row['mbuffers'];
			echo '<div class="progress-container"><div class="progress-container-percent" style="width:'. $mp .'%"><div class="bartext">'. $used .'/'. $row['mtotal'] .'MB</div></div></div></td>';
		}
		echo '</td>';
		echo '<td class="5pad">';
		if(isset($row['diskused'])) {
			$mp = ($row['diskused']/$row['disktotal'])*100;
			echo '<div class="progress-container"><div class="progress-container-percent" style="width:'. $mp .'%"><div class="bartext">'. format_kbytes($row['diskused']) .'/'. format_kbytes($row['disktotal']) .'GB</div></div></div>';
		} else {
			echo 'N/A';
		}
		echo '</td>';
		echo '<td class="5pad">';
		echo '<span class="loadavg" style="background-color: #'.gen_color($row['load1']).'">'. sprintf('%.02f', $row['load1']) .'</span>&nbsp;';
		echo '<span class="loadavg" style="background-color: #'.gen_color($row['load5']).'">'. sprintf('%.02f', $row['load5']) .'</span>&nbsp;';
		echo '<span class="loadavg" style="background-color: #'.gen_color($row['load15']).'">'. sprintf('%.02f', $row['load15']) .'</span>&nbsp;';
		echo '</td>';
		echo '</tr>';
	   	if ($row['status'] == "0") {
			echo '<tr style="text-align: center" class="offline">';
		} elseif ($row['uptime'] == "n/a") {
			echo '<tr style="text-align: center" class="online-but-no-data">';
		} else {
			echo '<tr style="text-align: center">';
		}
		echo '<td colspan="6" style="text-align:left;"><strong>Notes: </strong>'.$row['note'].'</td></tr>';
	}

	echo '
				</tbody>
		</table>';

	if (($auth === true && $alerts_require_login === true) || ($requirelogin === false && $alerts_require_login === false)) {
		$alert_query = $db->prepare('SELECT * FROM alerts WHERE server_uid = ? ORDER BY alert_time DESC');
		$alert_query->execute(array($server_uid));

		echo '<table style="border: 1;" id="alerts">
			<thead>
				<tr>
					<th colspan="'.($auth === true ? '5' : '4').'">Alerts</th>
				</tr>
				<tr>
					<th scope="col">Module</th>
					<th scope="col">Date / Time</th>
					<th scope="col">Level</th>
					<th scope="col">Value</th>'.($auth === true ? '
					<th scope="col">Actions</th>' : '').'
				</tr>
			</thead>
			<tbody>';

		while ($alert = $alert_query->fetch(PDO::FETCH_ASSOC)) {
			echo '<tr class="'.$alert['level'].'"><td>'.$alert['module'].'</td><td id="alert-'.$alert['id'].'">'.date('M j, Y g:ia', $alert['alert_time']).'</td><td>'.$alert['level'].'</td><td>'.$alert['value'].'</td>';

			if ($auth === true) {
				echo '<td>';
				if ($alert['acked'] == 0)
					echo '<a href="history.php?ack='.$alert['id'].'&uid='.$alert['server_uid'].'">Acknowledge</a>';
				else
					echo 'N/A';
				echo '</td>';
			}

			echo '</tr>';

		}

		echo '</tbody>
		</table>';
	}

	echo '
				</div>
				<script type="text/javascript">
					'. $jsend .'
				</script>';

}

require('../footer.php');
?>