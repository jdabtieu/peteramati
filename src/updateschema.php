<?php
// updateschema.php -- HotCRP function for updating old schemata
// HotCRP is Copyright (c) 2006-2012 Eddie Kohler and Regents of the UC
// See LICENSE for open-source distribution terms

function _update_schema_haslinenotes($conf) {
    $conf->ql("lock tables CommitNotes write");
    $hashes = array(array(), array(), array(), array());
    $result = $conf->ql("select hash, notes from CommitNotes");
    while (($row = edb_row($result))) {
        $x = Contact::commit_info_haslinenotes(json_decode($row[1]));
        $hashes[$x][] = $row[0];
    }
    foreach ($hashes as $x => $h)
        if (count($h))
            $conf->ql("update CommitNotes set haslinenotes=$x where hash in ('" . join("','", $h) . "')");
    $conf->ql("unlock tables");
    return true;
}

function _update_schema_pset_commitnotes($conf) {
    return $conf->ql("drop table if exists CommitInfo")
        && $conf->ql("alter table CommitNotes add `pset` int(11) NOT NULL DEFAULT '0'")
        && $conf->ql("alter table CommitNotes drop key `hash`")
        && $conf->ql("alter table CommitNotes add unique key `hashpset` (`hash`,`pset`)")
        && $conf->ql("update CommitNotes, RepositoryGrade set CommitNotes.pset=RepositoryGrade.pset where CommitNotes.hash=RepositoryGrade.gradehash");
}

function update_schema_drop_keys_if_exist($conf, $table, $key) {
    $indexes = Dbl::fetch_first_columns($conf->dblink, "select distinct index_name from information_schema.statistics where table_schema=database() and `table_name`='$table'");
    $drops = [];
    foreach (is_array($key) ? $key : [$key] as $k)
        if (in_array($k, $indexes))
            $drops[] = ($k === "PRIMARY" ? "drop primary key" : "drop key `$k`");
    if (count($drops))
        return $conf->ql("alter table `$table` " . join(", ", $drops));
    else
        return true;
}

function updateSchema($conf) {
    global $OK;
    // avoid error message about timezone, set to $Opt
    // (which might be overridden by database values later)
    if (function_exists("date_default_timezone_set") && $conf->opt("timezone"))
        date_default_timezone_set($conf->opt("timezone"));
    while (($result = $conf->ql("insert into Settings set name='__schema_lock', value=1 on duplicate key update value=1"))
           && $result->affected_rows == 0)
        time_nanosleep(0, 200000000);
    $conf->update_schema_version(null);

    error_log($conf->dbname . ": updating schema from version " . $conf->sversion);

    if ($conf->sversion == 58
	&& $conf->ql("alter table ContactInfo add `college` tinyint(1) NOT NULL DEFAULT '0'")
	&& $conf->ql("alter table ContactInfo add `extension` tinyint(1) NOT NULL DEFAULT '0'"))
	$conf->update_schema_version(59);
    if ($conf->sversion == 59
        && $conf->ql("alter table Repository add `lastpset` int(11) NOT NULL DEFAULT '0'")
        && $conf->ql("update Repository join ContactLink on (ContactLink.type=" . LINK_REPO . " and ContactLink.link=Repository.repoid) set Repository.lastpset=greatest(Repository.lastpset,ContactLink.pset)"))
	$conf->update_schema_version(60);
    if ($conf->sversion == 60
        && $conf->ql("alter table Repository add `working` int(1) NOT NULL DEFAULT '1'"))
        $conf->update_schema_version(61);
    if ($conf->sversion == 61
        && $conf->ql("alter table ContactInfo add `dropped` tinyint(1) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(62);
    if ($conf->sversion == 62
        && $conf->ql("alter table ContactInfo drop column `dropped`")
        && $conf->ql("alter table ContactInfo add `dropped` int(11) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(63);
    if ($conf->sversion == 63
        && $conf->ql("alter table Repository add `snapcommitat` int(11) NOT NULL DEFAULT '0'")
        && $conf->ql("alter table Repository add `snapcommitline` varchar(100)"))
        $conf->update_schema_version(64);
    if ($conf->sversion == 64
        && $conf->ql("drop table if exists `CommitNotes`")
        && $conf->ql("alter table `Repository` modify `snaphash` binary(40)")
        && $conf->ql("create table `CommitNotes` ( `hash` binary(40) NOT NULL, `notes` BLOB NOT NULL, UNIQUE KEY `hash` (`hash`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8"))
        $conf->update_schema_version(65);
    if ($conf->sversion == 65
        && $conf->ql("drop table if exists `RepositoryGrade`")
        && $conf->ql("create table `RepositoryGrade` ( `repoid` int(11) NOT NULL, `pset` int(11) NOT NULL, `gradehash` binary(40), `gradercid` int(11), UNIQUE KEY `repopset` (`repoid`,`pset`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8"))
        $conf->update_schema_version(66);
    if ($conf->sversion == 66
        && $conf->ql("alter table RepositoryGrade add `hidegrade` tinyint(1) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(67);
    if ($conf->sversion == 67
        && $conf->ql("drop table if exists `ExecutionQueue`")
        && $conf->ql("create table `ExecutionQueue` ( `queueid` int(11) NOT NULL AUTO_INCREMENT, `queueclass` varchar(20) NOT NULL, `repoid` int(11) NOT NULL, `insertat` int(11) NOT NULL, `updateat` int(11) NOT NULL, `runat` int(11) NOT NULL, `status` int(1) NOT NULL, `lockfile` varchar(1024), PRIMARY KEY (`queueid`), KEY `queueclass` (`queueclass`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8"))
        $conf->update_schema_version(68);
    if ($conf->sversion == 68
        && $conf->ql("alter table Repository add `notes` VARBINARY(32767)")
        && $conf->ql("alter table CommitNotes modify `notes` VARBINARY(32767)"))
        $conf->update_schema_version(69);
    if ($conf->sversion == 69
        && $conf->ql("alter table CommitNotes add `haslinenotes` tinyint(1) NOT NULL DEFAULT '0'")
        && $conf->ql("update CommitNotes set haslinenotes=1 where notes like '%\"linenotes\":{\"%'"))
        $conf->update_schema_version(70);
    if ($conf->sversion == 70
        && $conf->ql("alter table ExecutionQueue add `nconcurrent` int(11)"))
        $conf->update_schema_version(71);
    if ($conf->sversion == 71
        && _update_schema_haslinenotes($conf))
        $conf->update_schema_version(72);
    if ($conf->sversion == 72
        && _update_schema_pset_commitnotes($conf))
        $conf->update_schema_version(73);
    if ($conf->sversion == 73
        && $conf->ql("drop table if exists `ContactGrade`")
        && $conf->ql("create table `ContactGrade` ( `cid` int(11) NOT NULL, `pset` int(11) NOT NULL, `gradercid` int(11), `notes` varbinary(32767), UNIQUE KEY `cidpset` (`cid`,`pset`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8"))
        $conf->update_schema_version(74);
    if ($conf->sversion == 74
        && $conf->ql("alter table ContactGrade add `hidegrade` tinyint(1) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(75);
    if ($conf->sversion == 75
        && $conf->ql("alter table Settings modify `data` varbinary(32767) DEFAULT NULL"))
        $conf->update_schema_version(76);
    if ($conf->sversion == 76
        && $conf->ql("alter table Repository add `otherheads` varbinary(8192) DEFAULT NULL"))
        $conf->update_schema_version(77);
    if ($conf->sversion == 77
        && $conf->ql("alter table Repository change `otherheads` `heads` varbinary(8192) DEFAULT NULL"))
        $conf->update_schema_version(78);
    if ($conf->sversion == 78
        && $conf->ql("alter table CommitNotes add `repoid` int(11) NOT NULL DEFAULT '0'")
        && $conf->ql("alter table CommitNotes add `nrepo` int(11) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(79);
    if ($conf->sversion == 79
        && $conf->ql("alter table ContactInfo add `passwordTime` int(11) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(80);
    if ($conf->sversion == 80
        && $conf->ql("alter table ExecutionQueue add `autorun` tinyint(1) NOT NULL DEFAULT '0'"))
        $conf->update_schema_version(81);
    if ($conf->sversion == 81
        && $conf->ql("alter table ExecutionQueue add `psetid` int(11)")
        && $conf->ql("alter table ExecutionQueue add `runnername` varbinary(128)"))
        $conf->update_schema_version(82);
    if ($conf->sversion == 82
        && $conf->ql("alter table ExecutionQueue add `hash` binary(40)"))
        $conf->update_schema_version(83);
    if ($conf->sversion == 83
        && $conf->ql("create table `RepositoryGradeRequest` ( `repoid` int(11) NOT NULL, `pset` int(11) NOT NULL, `hash` binary(40) DEFAULT NULL, `requested_at` int(11) NOT NULL, UNIQUE KEY `repopsethash` (`repoid`,`pset`,`hash`) ) ENGINE=MyISAM DEFAULT CHARSET=utf8"))
        $conf->update_schema_version(84);
    if ($conf->sversion == 84
        && $conf->ql("drop table if exists Partner"))
        $conf->update_schema_version(85);
    if ($conf->sversion == 85
        && $conf->ql("drop table if exists Chair")
        && $conf->ql("drop table if exists ChairAssistant")
        && $conf->ql("drop table if exists PCMember"))
        $conf->update_schema_version(86);
    if ($conf->sversion == 86
        && $conf->ql("alter table RepositoryGrade add `placeholder` tinyint(1) NOT NULL DEFAULT '0'")
        && $conf->ql("alter table RepositoryGrade add `placeholder_at` int(11) DEFAULT NULL"))
        $conf->update_schema_version(87);
    if ($conf->sversion == 87
        && $conf->ql("alter table `ContactInfo` add `anon_username` varbinary(40) DEFAULT NULL")
        && $conf->ql("alter table `ContactInfo` add unique key `anon_username` (`anon_username`)"))
        $conf->update_schema_version(88);
    if ($conf->sversion == 88
        && $conf->ql("create table `ContactImage` ( `contactImageId` int(11) NOT NULL AUTO_INCREMENT, `contactId` int(11) NOT NULL, `mimetype` varbinary(128) DEFAULT NULL, `data` varbinary(32768) DEFAULT NULL, PRIMARY KEY (`contactImageId`), KEY `contactId` (`contactId`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8")
        && $conf->ql("alter table `ContactInfo` add `contactImageId` int(11) DEFAULT NULL"))
        $conf->update_schema_version(89);
    if ($conf->sversion == 89
        && $conf->ql("alter table ContactImage change `data` `data` mediumblob DEFAULT NULL"))
        $conf->update_schema_version(90);
    if ($conf->sversion == 90
        && $conf->ql("alter table ExecutionQueue add `inputfifo` varchar(1024) DEFAULT NULL"))
        $conf->update_schema_version(91);
    if ($conf->sversion == 91
        && update_schema_drop_keys_if_exist($conf, "CommitNotes", ["hashpset", "PRIMARY"])
        && $conf->ql("alter table CommitNotes add primary key (`hash`,`pset`)")
        && update_schema_drop_keys_if_exist($conf, "ContactGrade", ["cidpset", "PRIMARY"])
        && $conf->ql("alter table ContactGrade add primary key (`cid`,`pset`)")
        && $conf->ql("alter table ActionLog ENGINE=InnoDB")
        && $conf->ql("alter table Capability ENGINE=InnoDB")
        && $conf->ql("alter table CapabilityMap ENGINE=InnoDB")
        && $conf->ql("alter table CommitNotes ENGINE=InnoDB")
        && $conf->ql("alter table ContactGrade ENGINE=InnoDB")
        && update_schema_drop_keys_if_exist($conf, "ContactInfo", ["name", "affiliation", "email_3", "firstName_2", "lastName"])
        && $conf->ql("alter table ContactInfo ENGINE=InnoDB")
        && $conf->ql("alter table ContactLink ENGINE=InnoDB")
        && $conf->ql("alter table ExecutionQueue ENGINE=InnoDB")
        && $conf->ql("alter table Formula ENGINE=InnoDB")
        && $conf->ql("alter table MailLog ENGINE=InnoDB")
        && $conf->ql("alter table OptionType ENGINE=InnoDB")
        && $conf->ql("alter table PaperConflict ENGINE=InnoDB")
        && $conf->ql("alter table PaperStorage ENGINE=InnoDB")
        && $conf->ql("alter table PsetGrade ENGINE=InnoDB")
        && $conf->ql("alter table Repository ENGINE=InnoDB")
        && $conf->ql("alter table RepositoryGrade ENGINE=InnoDB")
        && $conf->ql("alter table RepositoryGradeRequest ENGINE=InnoDB")
        && $conf->ql("alter table Settings ENGINE=InnoDB"))
        $conf->update_schema_version(92);
    if ($conf->sversion == 92
        && $conf->ql("alter table ContactInfo add `github_username` varbinary(120) DEFAULT NULL"))
        $conf->update_schema_version(93);

    $conf->ql("delete from Settings where name='__schema_lock'");
}
