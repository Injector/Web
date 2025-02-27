<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $userbank, $theme; if(!defined("IN_SB")){echo "Ошибка доступа!";die();}if(isset($GLOBALS['IN_ADMIN']))define('CUR_AID', $userbank->GetAid());

if(isset($_POST['action']) && $_POST['action'] == "importBans")
{
	$bannedcfg = file($_FILES["importFile"]["tmp_name"]);
	$bancnt = 0;

	foreach($bannedcfg AS $ban)
	{
		$line = explode(" ", trim($ban));

		if($line[1] == "0")
		{
			if(validate_ip($line[2])) // if its an banned_ip.cfg
			{
				$check = $GLOBALS['db']->Execute("SELECT ip FROM `" . DB_PREFIX . "_bans` WHERE ip = ? AND RemoveType IS NULL", array($line[2]));

				if($check->RecordCount() == 0)
				{
					$bancnt++;
					$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
						(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
					$GLOBALS['db']->Execute($pre, array("", $line[2], "Импортированный бан", 0, 0, "Импорт из banned_ip.cfg", \UserManager::getMyId(), $_SERVER['REMOTE_ADDR'], 1));
				}
			} else { // if its an banned_user.cfg
				if (!validate_steam($line[2])) {
					if (($accountId = getAccountId($line[2])) !== -1) {
						$steam = renderSteam2($accountId, 0);
					} else {
						continue;
					}
				} else {
					$steam = $line[2];
				}
				$check = $GLOBALS['db']->Execute("SELECT authid FROM `" . DB_PREFIX . "_bans` WHERE authid = ? AND RemoveType IS NULL", array($steam));
				if($check->RecordCount() == 0)
				{
					if(!isset($_POST['friendsname']) || $_POST['friendsname'] != "on" || ($pname = GetCommunityName($steam)) == "")
						$pname = "Импортированный бан";
					
					$bancnt++;
					$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_bans(created,authid,ip,name,ends,length,reason,aid,adminIp,type) VALUES
						(UNIX_TIMESTAMP(),?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?,?)");
					$GLOBALS['db']->Execute($pre, array($steam, "", $pname, 0, 0, "Импорт из banned_user.cfg", \UserManager::getMyId(), $_SERVER['REMOTE_ADDR'], 0));
				}
			}
		}
	}
	if($bancnt > 0)
		$log = new CSystemLog("m", "Баны импортированы", "$bancnt Бан(ы) импортированы");

	echo "<script>setTimeout(\"ShowBox('Импорт банов', '$bancnt бан".($bancnt!=1?"s have":" был")." импортирован.', 'green', '', false, 5000)\", 800);</script>";
}

if(isset($_GET["rebanid"]))
{
	echo '<script type="text/javascript">xajax_PrepareReban("'.(int)$_GET["rebanid"].'");</script>';
}
if((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
	echo "<script type=\"text/javascript\">setTimeout(\"ShowBox('Загрузка..','<i>Ждите!</i>', 'blue', '', false, 5000);\", 800);xajax_PastePlayerData('".(int)$_GET['sid']."', '".addslashes($_GET['pName'])."');</script>";
}

echo '<div id="admin-page-content">';
	// Add Ban
echo '<div id="0" style="display:none;">';
$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN));
$theme->assign('demoEnabled', (\App::options()->demoEnabled == true));
$theme->assign('customreason', ((isset($GLOBALS['config']['bans.customreasons'])&&$GLOBALS['config']['bans.customreasons']!="")?unserialize($GLOBALS['config']['bans.customreasons']):false));
$theme->display('page_admin_bans_add.tpl');
echo '</div>';

	// Protests
echo '<div id="1" style="display:none;">';
echo '<div id="tabsWrapper" style="margin:0px;">
<div id="tabs"><br>
<ul class="tab-nav text-center fw-nav">
<li id="utab-p0" class="active"><a href="index.php?p=admin&c=bans#^1~p0" id="admin_utab_p0" onclick="Swap2ndPane(0,\'p\');" class="tip"> Активные </a></li> 
<li id="utab-p1" class="nonactive"><a href="index.php?p=admin&c=bans#^1~p1" id="admin_utab_p1" onclick="Swap2ndPane(1,\'p\');" class="tip"> Архив </a></li> 
</ul>
</div>
</div>';
		// current protests
echo '<div id="p0">';
$ItemsPerPage = SB_BANS_PER_PAGE;
$page = 1;
if (isset($_GET['ppage']) && $_GET['ppage'] > 0)
{
	$page = intval($_GET['ppage']);
}
$protests = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
$protests_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv = '0' ORDER BY pid DESC");
$page_count = $protests_count['count'];
$PageStart = intval(($page-1) * $ItemsPerPage);
$PageEnd = intval($PageStart+$ItemsPerPage);
if ($PageEnd > $page_count) $PageEnd = $page_count;
if ($page > 1)
{
	$prev = CreateLinkR('<- Предыдущие',"index.php?p=admin&c=bans&ppage=" .($page-1). "#^1");
}
else
{
	$prev = "";
}
if ($PageEnd < $page_count)
{
	$next = CreateLinkR('Следующие ->',"index.php?p=admin&c=bans&ppage=" .($page+1). "#^1");
}
else
	$next = "";

$page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

if (strlen($prev) > 0)
	$page_nav .= ' | <b>'.$prev.'</b>';
if (strlen($next) > 0)
	$page_nav .= ' | <b>'.$next.'</b>';

$pages = ceil($page_count/$ItemsPerPage);
if($pages > 1) {
	$page_nav .= '&nbsp;<select onchange="changePage(this,\'P\',\'\',\'\');">';
	for($i=1;$i<=$pages;$i++) {
		if($i==$page) {
			$page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
			continue;
		}
		$page_nav .= '<option value="' . $i . '">' . $i . '</option>';
	}
	$page_nav .= '</select>';
}

$delete = array();
$protest_list = array();
foreach($protests as $prot)
{
	$prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);
	$protestb = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
		FROM ".DB_PREFIX."_bans AS ba
		LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
		LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
		WHERE bid = \"". (int)$prot['bid'] . "\"");
	if(!$protestb)
	{
		$delete[] = $prot['bid'];
		continue;
	}

	$prot['name'] = $protestb[3];
	$prot['authid'] = $protestb[2];
	$prot['ip'] = $protestb['ip'];

	$prot['date'] = SBDate($dateformat, $protestb['created']);
	if ($protestb['ends'] == 'never')
		$prot['ends'] = 'never';
	else
		$prot['ends'] = SBDate($dateformat, $protestb['ends']);
	$prot['ban_reason'] = htmlspecialchars($protestb['reason']);

	$prot['admin'] = $protestb[11];
	if(!$protestb[12])
		$prot['server'] = "ВЕБ бан";
	else
		$prot['server'] = $protestb[12];
	$prot['datesubmitted'] = SBDate($dateformat, $prot['datesubmitted']);
			//COMMENT STUFF
			//-----------------------------------
	$view_comments = true;
	$commentres = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
		(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
		(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
		FROM `".DB_PREFIX."_comments` AS C
		WHERE type = 'P' AND bid = '".(int)$prot['pid']."' ORDER BY added desc");

	if($commentres->RecordCount()>0) {
		$comment = array();
		$morecom = 0;
		while(!$commentres->EOF) {
			$cdata = array();
			$cdata['morecom'] = ($morecom==1?true:false);
			if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
				$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P&cid='.$commentres->fields['cid'],'Редактировать комментарий');
				if($userbank->HasAccess(ADMIN_OWNER)) {
					$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(".$commentres->fields['cid'].",'P',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
				}
			}
			else {
				$cdata['editcomlink'] = "";
				$cdata['delcomlink'] = "";
			}

			$cdata['comname'] = $commentres->fields['comname'];
			$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
			$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
			$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

			if(!empty($commentres->fields['edittime'])) {
				$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
				$cdata['editname'] = $commentres->fields['editname'];
			}
			else {
				$cdata['edittime'] = "";
				$cdata['editname'] = "";
			}

			$morecom = 1;
			array_push($comment,$cdata);
			$commentres->MoveNext();
		}
	}
	else
		$comment = "None";

	$prot['commentdata'] = $comment;
	$prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Add Comment','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P');
	try {
		$sid = \CSteamId::factory("{$prot['authid']}");
		$prot['commid'] = $sid->CommunityID;
	} catch (\Exception $e) {
				// suppress.
	}
			//-----------------------------------------

	array_push($protest_list, $prot);

}
		if(count($delete) > 0) {//time for protest cleanup
			$ids = rtrim(implode(',', $delete), ',');
			$cnt = count($delete);
			$GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_protests SET archiv = '2' WHERE bid IN($ids) limit $cnt");
		}

		$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS));
		$theme->assign('permission_editban', 	$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('protest_nav', $page_nav);
		$theme->assign('protest_list', $protest_list);
		$theme->assign('protest_count', $page_count-(isset($cnt)?$cnt:0));
		$theme->display('page_admin_bans_protests.tpl');
		echo '</div>';

		$protestsarchiv = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_protests` WHERE archiv > '0' ORDER BY pid DESC");
		// archived protests
		echo '<div id="p1" style="display:none;">';
		
		$ItemsPerPage = SB_BANS_PER_PAGE;
		$page = 1;
		if (isset($_GET['papage']) && $_GET['papage'] > 0)
		{
			$page = intval($_GET['papage']);
		}
		$protestsarchiv = $GLOBALS['db']->GetAll("SELECT p.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = p.archivedby) AS archivedby FROM `" . DB_PREFIX . "_protests` p WHERE archiv > '0' ORDER BY pid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
		$protestsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(pid) AS count FROM `" . DB_PREFIX . "_protests` WHERE archiv > '0' ORDER BY pid DESC");
		$page_count = $protestsarchiv_count['count'];
		$PageStart = intval(($page-1) * $ItemsPerPage);
		$PageEnd = intval($PageStart+$ItemsPerPage);
		if ($PageEnd > $page_count) $PageEnd = $page_count;
		if ($page > 1)
		{
			$prev = CreateLinkR('<- предыдущая',"index.php?p=admin&c=bans&papage=" .($page-1). "#^1~p1");
		}
		else
		{
			$prev = "";
		}
		if ($PageEnd < $page_count)
		{
			$next = CreateLinkR('следующая ->',"index.php?p=admin&c=bans&papage=" .($page+1). "#^1~p1");
		}
		else
			$next = "";

		$page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

		if (strlen($prev) > 0)
			$page_nav .= ' | <b>'.$prev.'</b>';
		if (strlen($next) > 0)
			$page_nav .= ' | <b>'.$next.'</b>';

		$pages = ceil($page_count/$ItemsPerPage);
		if($pages > 1) {
			$page_nav .= '&nbsp;<select onchange="changePage(this,\'PA\',\'\',\'\');">';
			for($i=1;$i<=$pages;$i++) {
				if($i==$page) {
					$page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
					continue;
				}
				$page_nav .= '<option value="' . $i . '">' . $i . '</option>';
			}
			$page_nav .= '</select>';
		}

		$delete = array();
		$protest_list_archiv = array();
		foreach($protestsarchiv as $prot)
		{
			$prot['reason'] = wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true);

			if($prot['archiv'] != "2") {
				$protestb = $GLOBALS['db']->GetRow("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, email,ad.user, CONCAT(se.ip,':',se.port), se.sid
					FROM ".DB_PREFIX."_bans AS ba
					LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
					LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
					WHERE bid = \"". (int)$prot['bid'] . "\"");
				if(!$protestb) {
					$GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_protests` SET archiv = '2' WHERE pid = '". (int)$prot['pid'] . "';");
					$prot['archiv'] = "2";
					$prot['archive'] = "бан был удален.";
				} else {
					$prot['name'] = $protestb[3];
					$prot['authid'] = $protestb[2];
					$prot['ip'] = $protestb['ip'];

					$prot['date'] = SBDate($dateformat, $protestb['created']);
					if ($protestb['ends'] == 'never')
						$prot['ends'] = 'never';
					else
						$prot['ends'] = SBDate($dateformat, $protestb['ends']);
					$prot['ban_reason'] = htmlspecialchars($protestb['reason']);
					$prot['admin'] = $protestb[11];
					if(!$protestb[12])
						$prot['server'] = "ВЕБ бан";
					else
						$prot['server'] = $protestb[12];
					if($prot['archiv'] == "1")
						$prot['archive'] = "протест отправлен в архив.";
					else if($prot['archiv'] == "3")
						$prot['archive'] = "срок бана истек.";
					else if($prot['archiv'] == "4")
						$prot['archive'] = "игрок был разбанен.";
				}
			} else {
				$prot['archive'] = "бан был удален.";
			}
			$prot['datesubmitted'] = SBDate($dateformat, $prot['datesubmitted']);
			//COMMENT STUFF
			//-----------------------------------
			$view_comments = true;
			$commentres = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
				FROM `".DB_PREFIX."_comments` AS C
				WHERE type = 'P' AND bid = '".(int)$prot['pid']."' ORDER BY added desc");

			if($commentres->RecordCount()>0) {
				$comment = array();
				$morecom = 0;
				while(!$commentres->EOF) {
					$cdata = array();
					$cdata['morecom'] = ($morecom==1?true:false);
					if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P&cid='.$commentres->fields['cid'],'Редактировать комментарий');
						if($userbank->HasAccess(ADMIN_OWNER)) {
							$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'P',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
						}
					}
					else {
						$cdata['editcomlink'] = "";
						$cdata['delcomlink'] = "";
					}

					$cdata['comname'] = $commentres->fields['comname'];
					$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
					$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
					$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

					if(!empty($commentres->fields['edittime'])) {
						$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
						$cdata['editname'] = $commentres->fields['editname'];
					}
					else {
						$cdata['edittime'] = "";
						$cdata['editname'] = "";
					}

					$morecom = 1;
					array_push($comment,$cdata);
					$commentres->MoveNext();
				}
			}
			else
				$comment = "None";

			$prot['commentdata'] = $comment;
			$prot['protaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$prot['pid'].'&ctype=P');
			try {
				$sid = \CSteamId::factory("{$prot['authid']}");
				$prot['commid'] = $sid->CommunityID;
			} catch (\Exception $e) {
				// suppress.
			}
			//-----------------------------------------

			array_push($protest_list_archiv, $prot);

		}

		$theme->assign('permission_protests', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_PROTESTS));
		$theme->assign('permission_editban', 	$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('aprotest_nav', $page_nav);
		$theme->assign('protest_list_archiv', $protest_list_archiv);
		$theme->assign('protest_count_archiv', $page_count);
		$theme->display('page_admin_bans_protests_archiv.tpl');
		echo '</div>';
		echo '</div>';



	//Submissions page
		echo '<div id="2" style="display:none;">';
		echo '<div id="tabsWrapper" style="margin:0px;">
		<div id="tabs"><br>
		<ul class="tab-nav text-center fw-nav">
		<li id="utab-s0" class="active">
		<a href="index.php?p=admin&c=bans#^2~s0" id="admin_utab_s0" onclick="Swap2ndPane(0,\'s\');" class="tip" title="Показать жалобы :: Показать активные жалобы." target="_self">Активные</a>
		</li>
		<li id="utab-s1" class="nonactive">
		<a href="index.php?p=admin&c=bans#^2~s1" id="admin_utab_s1" onclick="Swap2ndPane(1,\'s\');" class="tip" title="Показать архив :: Показать жалобы в архиве." target="_self">Архив</a>
		</li>
		</ul>
		</div>
		</div>';


		echo '<div id="s0">'; // current submissions
		$ItemsPerPage = SB_BANS_PER_PAGE;
		$page = 1;
		if (isset($_GET['spage']) && $_GET['spage'] > 0)
		{
			$page = intval($_GET['spage']);
		}
		$submissions = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
		$submissions_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv = '0' ORDER BY subid DESC");
		$page_count = $submissions_count['count'];
		$PageStart = intval(($page-1) * $ItemsPerPage);
		$PageEnd = intval($PageStart+$ItemsPerPage);
		if ($PageEnd > $page_count) $PageEnd = $page_count;
		if ($page > 1)
		{
			$prev = CreateLinkR('<- предыдущая',"index.php?p=admin&c=bans&spage=" .($page-1). "#^2");
		}
		else
		{
			$prev = "";
		}
		if ($PageEnd < $page_count)
		{
			$next = CreateLinkR('следующая ->',"index.php?p=admin&c=bans&spage=" .($page+1). "#^2");
		}
		else
			$next = "";

		$page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

		if (strlen($prev) > 0)
			$page_nav .= ' | <b>'.$prev.'</b>';
		if (strlen($next) > 0)
			$page_nav .= ' | <b>'.$next.'</b>';

		$pages = ceil($page_count/$ItemsPerPage);
		if($pages > 1) {
			$page_nav .= '&nbsp;<select onchange="changePage(this,\'S\',\'\',\'\');">';
			for($i=1;$i<=$pages;$i++) {
				if($i==$page) {
					$page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
					continue;
				}
				$page_nav .= '<option value="' . $i . '">' . $i . '</option>';
			}
			$page_nav .= '</select>';
		}
		
		$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS));
		$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('submission_count', $page_count);
		$submission_list = array();
		foreach($submissions AS $sub)
		{
			$sub['name'] = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
			$sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);
			
			$dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
				WHERE demtype = \"S\" AND demid = " .(int)$sub['subid']);

			if($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
				$sub['demo'] =  "<a href=\"getdemo.php?id=". $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Получить демо</a>";
			else
				$sub['demo'] = "<a href=\"#\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Нет демо</a>";

			$sub['submitted'] = SBDate($dateformat, $sub['submitted']);

			$mod = $GLOBALS['db']->GetRow("SELECT m.name FROM `".DB_PREFIX."_submissions` AS s
				LEFT JOIN `".DB_PREFIX."_mods` AS m ON m.mid = s.ModID
				WHERE s.subid = ".(int)$sub['subid']);
			$sub['mod'] = $mod['name'];

			if(empty($sub['server']))
				$sub['hostname'] = '<i><font color="#677882">Другой сервер...</font></i>';
			else
				$sub['hostname'] = "";
			
				//COMMENT STUFF
				//-----------------------------------
			$view_comments = true;
			$commentres = $GLOBALS['db']->Execute(
				"SELECT cid, aid, commenttxt, added, edittime,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
				FROM `".DB_PREFIX."_comments` AS C
				WHERE type = 'S' AND bid = '".(int)$sub['subid']."' ORDER BY added desc");

			if($commentres->RecordCount()>0) {
				$comment = array();
				$morecom = 0;
				while(!$commentres->EOF) {
					$cdata = array();
					$cdata['morecom'] = ($morecom==1?true:false);
					if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S&cid='.$commentres->fields['cid'],'Редактировать комментарий');
						if($userbank->HasAccess(ADMIN_OWNER)) {
							$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'S',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
						}
					}
					else {
						$cdata['editcomlink'] = "";
						$cdata['delcomlink'] = "";
					}

					$cdata['comname'] = $commentres->fields['comname'];
					$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
					$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
					$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

					if(!empty($commentres->fields['edittime'])) {
						$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
						$cdata['editname'] = $commentres->fields['editname'];
					}
					else {
						$cdata['edittime'] = "";
						$cdata['editname'] = "";
					}

					$morecom = 1;
					array_push($comment,$cdata);
					$commentres->MoveNext();
				}
			}
			else
				$comment = "None";

			$sub['commentdata'] = $comment;
			$sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S');
				//----------------------------------------

			array_push($submission_list, $sub);
		}
		$theme->assign('submission_nav', $page_nav);
		$theme->assign('submission_list', $submission_list);
		$theme->display('page_admin_bans_submissions.tpl');
		echo '</div>';

		// submission archiv
		echo '<div id="s1" style="display:none;">';
		$ItemsPerPage = SB_BANS_PER_PAGE;
		$page = 1;
		if (isset($_GET['sapage']) && $_GET['sapage'] > 0)
		{
			$page = intval($_GET['sapage']);
		}
		$submissionsarchiv = $GLOBALS['db']->GetAll("SELECT s.*, (SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = s.archivedby) AS archivedby FROM `" . DB_PREFIX . "_submissions` s WHERE archiv > '0' ORDER BY subid DESC LIMIT " . intval(($page-1) * $ItemsPerPage) . "," . intval($ItemsPerPage));
		$submissionsarchiv_count = $GLOBALS['db']->GetRow("SELECT count(subid) AS count FROM `" . DB_PREFIX . "_submissions` WHERE archiv > '0' ORDER BY subid DESC");
		$page_count = $submissionsarchiv_count['count'];
		$PageStart = intval(($page-1) * $ItemsPerPage);
		$PageEnd = intval($PageStart+$ItemsPerPage);
		if ($PageEnd > $page_count) $PageEnd = $page_count;
		if ($page > 1)
		{
			$prev = CreateLinkR('<- prev',"index.php?p=admin&c=bans&sapage=" .($page-1). "#^2~s1");
		}
		else
		{
			$prev = "";
		}
		if ($PageEnd < $page_count)
		{
			$next = CreateLinkR('next ->',"index.php?p=admin&c=bans&sapage=" .($page+1). "#^2~s1");
		}
		else
			$next = "";

		$page_nav = 'Показано&nbsp;'.$PageStart.'&nbsp;-&nbsp;'.$PageEnd.'&nbsp;из&nbsp;'.$page_count.'&nbsp;результатов';

		if (strlen($prev) > 0)
			$page_nav .= ' | <b>'.$prev.'</b>';
		if (strlen($next) > 0)
			$page_nav .= ' | <b>'.$next.'</b>';

		$pages = ceil($page_count/$ItemsPerPage);
		if($pages > 1) {
			$page_nav .= '&nbsp;<select onchange="changePage(this,\'SA\',\'\',\'\');">';
			for($i=1;$i<=$pages;$i++) {
				if($i==$page) {
					$page_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
					continue;
				}
				$page_nav .= '<option value="' . $i . '">' . $i . '</option>';
			}
			$page_nav .= '</select>';
		}
		
		$theme->assign('permissions_submissions', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_SUBMISSIONS));
		$theme->assign('permissions_editsub', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_OWN_BANS));
		$theme->assign('submission_count_archiv', $page_count);
		$submission_list_archiv = array();
		foreach($submissionsarchiv AS $sub)
		{
			$sub['name'] = wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true);
			$sub['reason'] = wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true);
			
			$dem = $GLOBALS['db']->GetRow("SELECT filename FROM " . DB_PREFIX . "_demos
				WHERE demtype = \"S\" AND demid = " .(int)$sub['subid']);

			if($dem && !empty($dem['filename']) && @file_exists(SB_DEMOS . "/" . $dem['filename']))
				$sub['demo'] =  "<a href=\"getdemo.php?id=". $sub['subid'] . "&type=S\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Получить демо</a>";
			else
				$sub['demo'] = "<a href=\"#\"><img src=\"images/demo.png\" border=\"0\" style=\"vertical-align:middle\" /> Нет демо</a>";

			$sub['submitted'] = SBDate($dateformat, $sub['submitted']);

			$mod = $GLOBALS['db']->GetRow("SELECT m.name FROM `".DB_PREFIX."_submissions` AS s
				LEFT JOIN `".DB_PREFIX."_mods` AS m ON m.mid = s.ModID
				WHERE s.subid = ".(int)$sub['subid']);
			$sub['mod'] = $mod['name'];
			if(empty($sub['server']))
				$sub['hostname'] = '<i><font color="#677882">Другой сервер...</font></i>';
			else
				$sub['hostname'] = "";
			if($sub['archiv'] == "3")
				$sub['archive'] = "игрок был забанен.";
			else if($sub['archiv'] == "2")
				$sub['archive'] = "жалоба была подтверждена.";
			else if($sub['archiv'] == "1")
				$sub['archive'] = "жалоба была отправлена в архив.";
				//COMMENT STUFF
				//-----------------------------------
			$view_comments = true;
			$commentres = $GLOBALS['db']->Execute(
				"SELECT cid, aid, commenttxt, added, edittime,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
				(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
				FROM `".DB_PREFIX."_comments` AS C
				WHERE type = 'S' AND bid = '".(int)$sub['subid']."' ORDER BY added desc");

			if($commentres->RecordCount()>0) {
				$comment = array();
				$morecom = 0;
				while(!$commentres->EOF) {
					$cdata = array();
					$cdata['morecom'] = ($morecom==1?true:false);
					if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S&cid='.$commentres->fields['cid'],'Редактировать комментарий');
						if($userbank->HasAccess(ADMIN_OWNER)) {
							$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Удалить комментарий\" target=\"_self\" onclick=\"Удалить комментарий(".$commentres->fields['cid'].",'S',-1);\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
						}
					}
					else {
						$cdata['editcomlink'] = "";
						$cdata['delcomlink'] = "";
					}

					$cdata['comname'] = $commentres->fields['comname'];
					$cdata['added'] = SBDate($dateformat,$commentres->fields['added']);
					$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
					$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);

					if(!empty($commentres->fields['edittime'])) {
						$cdata['edittime'] = SBDate($dateformat,$commentres->fields['edittime']);
						$cdata['editname'] = $commentres->fields['editname'];
					}
					else {
						$cdata['edittime'] = "";
						$cdata['editname'] = "";
					}

					$morecom = 1;
					array_push($comment,$cdata);
					$commentres->MoveNext();
				}
			}
			else
				$comment = "None";

			$sub['commentdata'] = $comment;
			$sub['subaddcomment'] = CreateLinkR('<img src="images/details.png" border="0" alt="" style="vertical-align:middle" /> Добавить комментарий','index.php?p=banlist&comment='.(int)$sub['subid'].'&ctype=S');
				//----------------------------------------

			array_push($submission_list_archiv, $sub);
		}
		$theme->assign('asubmission_nav', $page_nav);
		$theme->assign('submission_list_archiv', $submission_list_archiv);
		$theme->display('page_admin_bans_submissions_archiv.tpl');
		echo '</div>';
		echo '</div>';

		echo '<div id="3" style="display:none;">';
		$theme->assign('permission_import', $userbank->HasAccess(ADMIN_OWNER|ADMIN_BAN_IMPORT));
		if(ini_get('safe_mode')==1)
			$requirements = false;
		else
			$requirements = true;
		$theme->assign('extreq', $requirements);
		$theme->display('page_admin_bans_import.tpl');
		echo '</div>';

		echo '<div id="4" style="display:none;">';
		$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN));
		$theme->assign('groupbanning_enabled', $GLOBALS['config']['config.enablegroupbanning']==1?true:false);
		if(isset($_GET['fid'])) {
			$theme->assign('list_steam_groups', $_GET['fid']);
		} else {
			$theme->assign('list_steam_groups', false);
		}
		$theme->display('page_admin_bans_groups.tpl');
		echo '</div>';
		?>






		<script type="text/javascript">
			var did = 0;
			var dname = "";
			function demo(id, name)
			{
				$('demo.msg').setHTML("<b>" + name + "</b>");
				$('demo1.msg').style.display = "block";
				did = id;
				dname = name;
			}

			function changeReason(szListValue)
			{
				$('dreason').style.display = (szListValue == "other" ? "block" : "none");
				$('txtReason').focus();
			}


			function ProcessBan()
			{
				var err = 0;
				var reason = $('listReason')[$('listReason').selectedIndex].value;

				if (reason == "other")
					reason = $('txtReason').value;

				if(!$('nickname').value)
				{
					$('nick.msg').setHTML('Введите ник игрока, которому хотите дать бан');
					$('nick.msg').setStyle('display', 'block');
					err++;
				}else
				{
					$('nick.msg').setHTML('');
					$('nick.msg').setStyle('display', 'none');
				}

				if($('steam').value.length < 10 && !$('ip').value)
				{
					$('steam.msg').setHTML('Введите реальный STEAM ID или Community ID');
					$('steam.msg').setStyle('display', 'block');
					err++;
				}else
				{
					$('steam.msg').setHTML('');
					$('steam.msg').setStyle('display', 'none');
				}

				if($('ip').value.length < 7 && !$('steam').value)
				{
					$('ip.msg').setHTML('Введите реальный IP адрес');
					$('ip.msg').setStyle('display', 'block');
					err++;
				}else
				{
					$('ip.msg').setHTML('');
					$('ip.msg').setStyle('display', 'none');
				}


				if(!reason)
				{
					$('reason.msg').setHTML('Выберите причину бана.');
					$('reason.msg').setStyle('display', 'block');
					err++;
				}else
				{
					$('reason.msg').setHTML('');
					$('reason.msg').setStyle('display', 'none');
				}

				if(err)
					return 0;

				xajax_AddBan($('nickname').value,
					$('type').value,
					$('steam').value,
					$('ip').value,
					$('banlength').value,
					did,
					dname,
					reason,
					$('fromsub').value,
					$('demo_link').value);
			}
			function ProcessGroupBan()
			{
				if(!$('groupurl').value)
				{
					$('groupurl.msg').setHTML('Введите ссылку на группу, которую баните');
					$('groupurl.msg').setStyle('display', 'block');
				}else
				{
					$('groupurl.msg').setHTML('');
					$('groupurl.msg').setStyle('display', 'none');
					xajax_GroupBan($('groupurl').value, "no", "no", $('groupreason').value, "");
				}
			}
			function CheckGroupBan()
			{
				var last = 0;
				for(var i=0;$('chkb_' + i);i++)
				{
					if($('chkb_' + i).checked == true)
						last = $('chkb_' + i).value;
				}
				for(var i=0;$('chkb_' + i);i++)
				{
					if($('chkb_' + i).checked == true)
						xajax_GroupBan($('chkb_' + i).value, "yes", "yes", $('groupreason').value, last);
				}
			}
		</script>
	</div>

